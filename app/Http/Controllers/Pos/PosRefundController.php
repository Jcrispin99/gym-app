<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Jobs\SendSunatInvoice;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Models\PosSessionPayment;
use App\Models\ProductProduct;
use App\Models\Sale;
use App\Services\KardexService;
use App\Services\SequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PosRefundController extends Controller
{
    public function index(PosSession $session)
    {
        if ($session->user_id !== Auth::id() || ! $session->isOpen()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'Sesión inválida');
        }

        $paymentMethods = PaymentMethod::active()->get();

        return Inertia::render('Pos/Refund', [
            'session' => $session,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function orders(Request $request, PosSession $session)
    {
        if ($session->user_id !== Auth::id() || ! $session->isOpen()) {
            throw ValidationException::withMessages(['session' => 'Sesión inválida']);
        }

        $validated = $request->validate([
            'q' => 'nullable|string|max:60',
            'per_page' => 'nullable|integer|min:5|max:50',
            'days' => 'nullable|integer|min:1|max:30',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $days = (int) ($validated['days'] ?? 3);
        $companyId = $session->posConfig->company_id;
        $term = trim((string) ($validated['q'] ?? ''));

        $query = Sale::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereHas('journal', function ($q) {
                $q->whereIn('document_type_code', ['01', '03']);
            })
            ->with(['partner', 'journal'])
            ->orderByDesc('created_at');

        if ($term !== '') {
            $termUpper = strtoupper($term);
            if (str_contains($termUpper, '-')) {
                $parts = explode('-', $termUpper, 2);
                if (count($parts) === 2) {
                    [$serie, $correlative] = $parts;
                    $query->where('serie', $serie)->where('correlative', $correlative);
                }
            } else {
                $query->where(function ($q) use ($term) {
                    $q->where('serie', 'like', "%{$term}%")
                        ->orWhere('correlative', 'like', "%{$term}%")
                        ->orWhereHas('partner', function ($p) use ($term) {
                            $p->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%")
                                ->orWhere('business_name', 'like', "%{$term}%");
                        });
                });
            }
        } else {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(function (Sale $sale) {
                return [
                    'id' => $sale->id,
                    'document' => $sale->document_number,
                    'doc_type' => (string) ($sale->journal?->document_type_code ?? ''),
                    'partner_name' => $sale->partner?->display_name ?? 'Cliente General',
                    'total' => (float) $sale->total,
                    'created_at' => (string) $sale->created_at,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'next_page_url' => $paginated->nextPageUrl(),
                'prev_page_url' => $paginated->previousPageUrl(),
            ],
        ]);
    }

    public function origin(PosSession $session, Sale $sale)
    {
        if ($session->user_id !== Auth::id() || ! $session->isOpen()) {
            throw ValidationException::withMessages(['session' => 'Sesión inválida']);
        }

        if ($sale->company_id !== $session->posConfig->company_id || $sale->status !== 'posted') {
            throw ValidationException::withMessages(['sale' => 'Venta inválida']);
        }

        $sale->loadMissing([
            'journal',
            'partner',
            'products.tax',
            'products.productProduct.product',
        ]);

        $docType = (string) ($sale->journal?->document_type_code ?? '');
        if (! in_array($docType, ['01', '03'], true) || empty($sale->serie) || empty($sale->correlative)) {
            throw ValidationException::withMessages(['sale' => 'Documento origen inválido']);
        }

        $originalQtyByProduct = [];
        foreach ($sale->products as $line) {
            $pid = (int) $line->product_product_id;
            $originalQtyByProduct[$pid] = ($originalQtyByProduct[$pid] ?? 0) + (float) $line->quantity;
        }

        $creditedQtyByProduct = DB::table('productables')
            ->join('sales', 'sales.id', '=', 'productables.productable_id')
            ->where('productables.productable_type', Sale::class)
            ->where('sales.original_sale_id', $sale->id)
            ->where('sales.status', 'posted')
            // Don't count the sale itself if original_sale_id happens to be self (shouldn't happen but safe)
            ->where('sales.id', '!=', $sale->id)
            ->selectRaw('productables.product_product_id, SUM(productables.quantity) as qty')
            ->groupBy('productables.product_product_id')
            ->pluck('qty', 'productables.product_product_id')
            ->all();

        $items = $sale->products
            ->map(function ($line) use ($originalQtyByProduct, $creditedQtyByProduct) {
                $pid = (int) $line->product_product_id;
                $soldQty = (float) ($originalQtyByProduct[$pid] ?? 0);
                $creditedQty = (float) ($creditedQtyByProduct[$pid] ?? 0);
                $available = max(0, $soldQty - $creditedQty);

                return [
                    'product_product_id' => $pid,
                    'name' => $line->productProduct?->product?->name,
                    'sku' => $line->productProduct?->sku,
                    'qty_sold' => $soldQty,
                    'qty_credited' => $creditedQty,
                    'qty_available' => $available,
                    'price' => (float) $line->price,
                    'tax_id' => $line->tax_id,
                    'tax_rate' => (float) $line->tax_rate,
                ];
            })
            ->values();

        return response()->json([
            'id' => $sale->id,
            'document' => $sale->document_number,
            'journal_doc_type' => $docType,
            'partner' => $sale->partner ? [
                'id' => $sale->partner->id,
                'display_name' => $sale->partner->display_name,
                'document_type' => $sale->partner->document_type,
                'document_number' => $sale->partner->document_number,
            ] : null,
            'items' => $items,
        ]);
    }

    public function lookupSale(Request $request, PosSession $session)
    {
        if ($session->user_id !== Auth::id() || ! $session->isOpen()) {
            throw ValidationException::withMessages(['session' => 'Sesión inválida']);
        }

        $validated = $request->validate([
            'document' => 'required|string|max:30',
        ]);

        $document = strtoupper(trim($validated['document']));
        $parts = explode('-', $document, 2);
        if (count($parts) !== 2) {
            throw ValidationException::withMessages(['document' => 'Formato inválido. Ej: B004-00000001']);
        }

        [$serie, $correlative] = $parts;

        $companyId = $session->posConfig->company_id;
        $sale = Sale::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->where('serie', $serie)
            ->where('correlative', $correlative)
            ->whereHas('journal', function ($q) {
                $q->whereIn('document_type_code', ['01', '03']);
            })
            ->with([
                'journal',
                'partner',
                'products.tax',
                'products.productProduct.product',
            ])
            ->first();

        if (! $sale) {
            throw ValidationException::withMessages(['document' => 'No se encontró una venta emitida con ese número.']);
        }

        $originalQtyByProduct = [];
        foreach ($sale->products as $line) {
            $pid = (int) $line->product_product_id;
            $originalQtyByProduct[$pid] = ($originalQtyByProduct[$pid] ?? 0) + (float) $line->quantity;
        }

        $creditedQtyByProduct = DB::table('productables')
            ->join('sales', 'sales.id', '=', 'productables.productable_id')
            ->where('productables.productable_type', Sale::class)
            ->where('sales.original_sale_id', $sale->id)
            ->where('sales.status', 'posted')
            ->selectRaw('productables.product_product_id, SUM(productables.quantity) as qty')
            ->groupBy('productables.product_product_id')
            ->pluck('qty', 'productables.product_product_id')
            ->all();

        $items = $sale->products
            ->map(function ($line) use ($originalQtyByProduct, $creditedQtyByProduct) {
                $pid = (int) $line->product_product_id;
                $soldQty = (float) ($originalQtyByProduct[$pid] ?? 0);
                $creditedQty = (float) ($creditedQtyByProduct[$pid] ?? 0);
                $available = max(0, $soldQty - $creditedQty);

                return [
                    'product_product_id' => $pid,
                    'name' => $line->productProduct?->product?->name,
                    'sku' => $line->productProduct?->sku,
                    'qty_sold' => $soldQty,
                    'qty_credited' => $creditedQty,
                    'qty_available' => $available,
                    'price' => (float) $line->price,
                    'tax_id' => $line->tax_id,
                    'tax_rate' => (float) $line->tax_rate,
                ];
            })
            ->values();

        return response()->json([
            'id' => $sale->id,
            'document' => $sale->document_number,
            'journal_doc_type' => $sale->journal?->document_type_code,
            'partner' => $sale->partner ? [
                'id' => $sale->partner->id,
                'display_name' => $sale->partner->display_name,
                'document_type' => $sale->partner->document_type,
                'document_number' => $sale->partner->document_number,
            ] : null,
            'items' => $items,
        ]);
    }

    public function preview(Request $request, PosSession $session)
    {
        if ($session->user_id !== Auth::id() || ! $session->isOpen()) {
            throw ValidationException::withMessages(['session' => 'Sesión inválida']);
        }

        $validated = $request->validate([
            'origin_sale_id' => 'required|integer|exists:sales,id',
            'return_items' => 'required|array|min:1',
            'return_items.*.product_product_id' => 'required|integer',
            'return_items.*.quantity' => 'required|numeric|min:0.01',
            'sale_items' => 'nullable|array',
            'sale_items.*.product_id' => 'required|integer',
            'sale_items.*.qty' => 'required|numeric|min:0.01',
            'sale_items.*.price' => 'required|numeric|min:0',
        ]);

        $origin = Sale::query()
            ->where('company_id', $session->posConfig->company_id)
            ->where('status', 'posted')
            ->with(['journal', 'partner', 'products.tax', 'products.productProduct.template'])
            ->findOrFail($validated['origin_sale_id']);

        $returnTotal = $this->calculateReturnTotal($origin, $validated['return_items']);

        $saleItems = $validated['sale_items'] ?? [];
        $saleTotal = $this->calculateSaleTotal($session, $saleItems);

        $applied = min($returnTotal, $saleTotal);
        $toPay = max(0, $saleTotal - $returnTotal);
        $toRefund = max(0, $returnTotal - $saleTotal);

        return response()->json([
            'return_total' => round($returnTotal, 2),
            'sale_total' => round($saleTotal, 2),
            'applied_credit_note' => round($applied, 2),
            'to_pay' => round($toPay, 2),
            'to_refund' => round($toRefund, 2),
        ]);
    }

    public function process(Request $request, PosSession $session)
    {
        if ($session->user_id !== Auth::id() || ! $session->isOpen()) {
            return redirect()->route('pos-configs.index')
                ->with('error', 'Sesión inválida');
        }

        $validated = $request->validate([
            'origin_sale_id' => 'required|integer|exists:sales,id',
            'return_items' => 'required|array|min:1',
            'return_items.*.product_product_id' => 'required|integer',
            'return_items.*.quantity' => 'required|numeric|min:0.01',
            'sale_items' => 'nullable|array',
            'sale_items.*.product_id' => 'required|integer',
            'sale_items.*.qty' => 'required|numeric|min:0.01',
            'sale_items.*.price' => 'required|numeric|min:0',
            'sale_journal_id' => 'nullable|integer|exists:journals,id',
            'pay_payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'pay_amount' => 'nullable|numeric|min:0',
            'refund_payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        $session->loadMissing(['posConfig.company', 'posConfig.warehouse', 'posConfig.tax', 'posConfig.journals']);

        $origin = Sale::query()
            ->where('company_id', $session->posConfig->company_id)
            ->where('status', 'posted')
            ->with(['journal', 'partner', 'products.tax', 'products.productProduct.template'])
            ->findOrFail($validated['origin_sale_id']);

        $originDocType = (string) ($origin->journal?->document_type_code ?? '');
        if (! in_array($originDocType, ['01', '03'], true) || empty($origin->serie) || empty($origin->correlative)) {
            return back()->withErrors(['origin_sale_id' => 'Documento origen inválido.']);
        }

        $returnTotal = $this->calculateReturnTotal($origin, $validated['return_items']);
        $saleItems = $validated['sale_items'] ?? [];
        $saleTotal = $this->calculateSaleTotal($session, $saleItems);

        $applied = min($returnTotal, $saleTotal);
        $toPay = max(0, $saleTotal - $returnTotal);
        $toRefund = max(0, $returnTotal - $saleTotal);

        $payAmount = (float) ($validated['pay_amount'] ?? 0);
        $refundAmount = (float) ($validated['refund_amount'] ?? 0);

        if ($toPay > 0 && abs($payAmount - $toPay) > 0.01) {
            return back()->withErrors(['pay_amount' => 'El pago debe ser igual al monto a pagar.']);
        }

        if ($toRefund > 0 && abs($refundAmount - $toRefund) > 0.01) {
            return back()->withErrors(['refund_amount' => 'El reembolso debe ser igual al monto a devolver.']);
        }

        if ($toPay > 0 && empty($validated['pay_payment_method_id'])) {
            return back()->withErrors(['pay_payment_method_id' => 'Selecciona un método de pago.']);
        }

        if ($toRefund > 0 && empty($validated['refund_payment_method_id'])) {
            return back()->withErrors(['refund_payment_method_id' => 'Selecciona un método de reembolso.']);
        }

        $creditNoteJournal = $this->resolveCreditNoteJournal($session, $originDocType);
        if (! $creditNoteJournal) {
            return back()->withErrors(['origin_sale_id' => 'No hay journal 07 (Nota de Crédito) configurado para este POS. En Pos Configs agrega FC/BC como tipo Nota de Crédito.']);
        }

        $saleJournal = null;
        if ($saleTotal > 0) {
            if (empty($validated['sale_journal_id'])) {
                return back()->withErrors(['sale_journal_id' => 'Selecciona el documento de venta.']);
            }

            $saleJournal = $session->posConfig
                ->journals()
                ->where('journals.id', $validated['sale_journal_id'])
                ->first();

            if (! $saleJournal) {
                return back()->withErrors(['sale_journal_id' => 'El documento no pertenece a este POS.']);
            }

            $partnerDocType = (string) ($origin->partner?->document_type ?? 'DNI');
            $requiredDocumentType = $partnerDocType === 'RUC' ? 'invoice' : 'receipt';
            $selectedDocumentType = (string) ($saleJournal->pivot?->document_type ?? '');
            if ($selectedDocumentType !== '' && $selectedDocumentType !== $requiredDocumentType) {
                $documentLabel = $requiredDocumentType === 'invoice' ? 'Factura' : 'Boleta';
                return back()->withErrors(['sale_journal_id' => "El cliente seleccionado requiere {$documentLabel}."]);
            }
        }

        $notePaymentMethod = PaymentMethod::query()->firstOrCreate(
            ['name' => 'Nota de Crédito'],
            ['is_active' => true]
        );

        $creditSale = null;
        $newSale = null;

        DB::transaction(function () use (
            $session,
            $origin,
            $creditNoteJournal,
            $saleJournal,
            $validated,
            $saleTotal,
            $applied,
            $toPay,
            $toRefund,
            $notePaymentMethod,
            &$creditSale,
            &$newSale
        ) {
            $kardexService = new KardexService();

            $noteNumberParts = SequenceService::getNextParts($creditNoteJournal->id);
            $creditSale = Sale::create([
                'serie' => $noteNumberParts['serie'],
                'correlative' => $noteNumberParts['correlative'],
                'journal_id' => $creditNoteJournal->id,
                'partner_id' => $origin->partner_id,
                'warehouse_id' => $session->posConfig->warehouse_id,
                'company_id' => $session->posConfig->company_id,
                'original_sale_id' => $origin->id,
                'pos_session_id' => $session->id,
                'user_id' => Auth::id(),
                'status' => 'posted',
                'payment_status' => 'paid',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'notes' => "Reembolso POS · Origen {$origin->document_number}",
            ]);

            $noteSubtotal = 0;
            $noteTax = 0;
            foreach ($validated['return_items'] as $item) {
                $pid = (int) $item['product_product_id'];
                $qty = (float) $item['quantity'];

                $originLine = $origin->products->firstWhere('product_product_id', $pid);
                if (! $originLine) {
                    throw ValidationException::withMessages(['return_items' => 'Producto inválido para devolución.']);
                }

                $price = (float) $originLine->price;
                $taxRate = (float) ($originLine->tax_rate ?? 0);
                $lineSubtotal = $qty * $price;
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;

                $creditSale->products()->create([
                    'product_product_id' => $pid,
                    'quantity' => $qty,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'tax_id' => $originLine->tax_id,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                ]);

                $noteSubtotal += $lineSubtotal;
                $noteTax += $taxAmount;

                $tracksInventory = $originLine->productProduct?->template?->tracks_inventory ?? true;
                if ($tracksInventory) {
                    $last = $kardexService->getLastRecord($pid, $creditSale->warehouse_id);
                    $unitCost = (float) ($last['cost'] ?? 0);
                    $kardexService->registerEntry(
                        $creditSale,
                        [
                            'id' => $pid,
                            'quantity' => $qty,
                            'price' => $unitCost,
                            'subtotal' => $qty * $unitCost,
                        ],
                        $creditSale->warehouse_id,
                        "Nota de Crédito {$creditSale->document_number} {$origin->document_number}"
                    );
                }
            }

            $creditSale->update([
                'subtotal' => $noteSubtotal,
                'tax_amount' => $noteTax,
                'total' => $noteSubtotal + $noteTax,
            ]);

            if ($saleTotal > 0 && $saleJournal) {
                $saleNumberParts = SequenceService::getNextParts($saleJournal->id);

                $posConfig = $session->posConfig()->with('tax')->first();
                $applyTax = (bool) ($posConfig?->apply_tax ?? true);
                $pricesIncludeTax = (bool) ($posConfig?->prices_include_tax ?? false);
                $tax = $applyTax && $posConfig?->tax_id ? $posConfig->tax : null;
                $taxRate = $applyTax && $tax ? (float) $tax->rate_percent : 0.0;

                $newSale = Sale::create([
                    'serie' => $saleNumberParts['serie'],
                    'correlative' => $saleNumberParts['correlative'],
                    'journal_id' => $saleJournal->id,
                    'partner_id' => $origin->partner_id,
                    'warehouse_id' => $session->posConfig->warehouse_id,
                    'company_id' => $session->posConfig->company_id,
                    'pos_session_id' => $session->id,
                    'user_id' => Auth::id(),
                    'status' => 'posted',
                    'payment_status' => 'paid',
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                    'notes' => "Intercambio POS · NC {$creditSale->document_number}",
                ]);

                $subtotal = 0;
                $totalTax = 0;

                foreach ($validated['sale_items'] as $item) {
                    $product = ProductProduct::find($item['product_id']);
                    if (! $product) {
                        throw ValidationException::withMessages(['sale_items' => 'Producto inválido para venta.']);
                    }

                    $quantity = (float) $item['qty'];
                    $inputUnitPrice = (float) $item['price'];

                    if ($applyTax && $taxRate > 0) {
                        if ($pricesIncludeTax) {
                            $unitNetPrice = $inputUnitPrice / (1 + ($taxRate / 100));
                            $lineSubtotal = $quantity * $unitNetPrice;
                            $lineTotal = $quantity * $inputUnitPrice;
                            $taxAmount = $lineTotal - $lineSubtotal;
                        } else {
                            $unitNetPrice = $inputUnitPrice;
                            $lineSubtotal = $quantity * $unitNetPrice;
                            $taxAmount = $lineSubtotal * ($taxRate / 100);
                            $lineTotal = $lineSubtotal + $taxAmount;
                        }
                    } else {
                        $unitNetPrice = $inputUnitPrice;
                        $lineSubtotal = $quantity * $unitNetPrice;
                        $taxAmount = 0;
                        $lineTotal = $lineSubtotal;
                    }

                    $newSale->products()->create([
                        'product_product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $unitNetPrice,
                        'subtotal' => $lineSubtotal,
                        'tax_id' => $applyTax ? $tax?->id : null,
                        'tax_rate' => $applyTax ? $taxRate : 0,
                        'tax_amount' => $taxAmount,
                        'total' => $lineTotal,
                    ]);

                    $subtotal += $lineSubtotal;
                    $totalTax += $taxAmount;

                    $tracksInventory = $product->template?->tracks_inventory ?? true;
                    if ($tracksInventory) {
                        $kardexService->registerExit(
                            $newSale,
                            ['id' => $product->id, 'quantity' => $quantity],
                            $newSale->warehouse_id,
                            "Venta {$newSale->document_number} (Intercambio)"
                        );
                    }
                }

                $newSale->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $totalTax,
                    'total' => $subtotal + $totalTax,
                ]);

                if ($applied > 0) {
                    PosSessionPayment::create([
                        'pos_session_id' => $session->id,
                        'sale_id' => $newSale->id,
                        'payment_method_id' => $notePaymentMethod->id,
                        'amount' => $applied,
                    ]);
                }

                if ($toPay > 0) {
                    PosSessionPayment::create([
                        'pos_session_id' => $session->id,
                        'sale_id' => $newSale->id,
                        'payment_method_id' => $validated['pay_payment_method_id'],
                        'amount' => $toPay,
                    ]);
                }
            }

            if ($toRefund > 0) {
                PosSessionPayment::create([
                    'pos_session_id' => $session->id,
                    'sale_id' => $creditSale->id,
                    'payment_method_id' => $validated['refund_payment_method_id'],
                    'amount' => -1 * $toRefund,
                ]);
            }

            SendSunatInvoice::dispatch($creditSale->id)->afterCommit();
            if ($newSale) {
                SendSunatInvoice::dispatch($newSale->id)->afterCommit();
            }
        });

        return redirect()->route('pos.dashboard', ['session' => $session->id, 'clear_cart' => 1])
            ->with('success', 'Reembolso procesado exitosamente');
    }

    private function resolveCreditNoteJournal(PosSession $session, string $originDocType): ?Journal
    {
        $preferredCodePrefix = $originDocType === '03' ? 'BC' : 'FC';
        $companyId = $session->posConfig->company_id;

        $preferredCode = $originDocType === '03' ? 'BC04' : 'FC04';

        $journal = $session->posConfig
            ->journals()
            ->where('journals.document_type_code', '07')
            ->wherePivot('document_type', 'credit_note')
            ->where('journals.code', $preferredCode)
            ->first();

        if ($journal) {
            return $journal;
        }

        $journal = $session->posConfig
            ->journals()
            ->where('journals.document_type_code', '07')
            ->wherePivot('document_type', 'credit_note')
            ->first();

        if ($journal) {
            return $journal;
        }

        $journal = $session->posConfig
            ->journals()
            ->where('journals.document_type_code', '07')
            ->where('journals.code', 'like', "{$preferredCodePrefix}%")
            ->first();

        if ($journal) {
            return $journal;
        }

        $journal = $session->posConfig
            ->journals()
            ->where('journals.document_type_code', '07')
            ->first();

        if ($journal) {
            return $journal;
        }

        return Journal::query()
            ->where('company_id', $companyId)
            ->where('document_type_code', '07')
            ->where('code', 'like', "{$preferredCodePrefix}%")
            ->first()
            ?? Journal::query()
            ->where('company_id', $companyId)
            ->where('document_type_code', '07')
            ->first();
    }

    private function calculateReturnTotal(Sale $origin, array $returnItems): float
    {
        $originQtyByProduct = [];
        foreach ($origin->products as $line) {
            $pid = (int) $line->product_product_id;
            $originQtyByProduct[$pid] = ($originQtyByProduct[$pid] ?? 0) + (float) $line->quantity;
        }

        $creditedQtyByProduct = DB::table('productables')
            ->join('sales', 'sales.id', '=', 'productables.productable_id')
            ->where('productables.productable_type', Sale::class)
            ->where('sales.original_sale_id', $origin->id)
            ->where('sales.status', 'posted')
            ->selectRaw('productables.product_product_id, SUM(productables.quantity) as qty')
            ->groupBy('productables.product_product_id')
            ->pluck('qty', 'productables.product_product_id')
            ->all();

        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($returnItems as $item) {
            $pid = (int) $item['product_product_id'];
            $qty = (float) $item['quantity'];

            $origQty = (float) ($originQtyByProduct[$pid] ?? 0);
            $creditedQty = (float) ($creditedQtyByProduct[$pid] ?? 0);
            $available = $origQty - $creditedQty;

            if ($qty <= 0 || $qty > $available + 0.00001) {
                throw ValidationException::withMessages(['return_items' => 'Cantidad a devolver excede lo disponible.']);
            }

            $line = $origin->products->firstWhere('product_product_id', $pid);
            if (! $line) {
                throw ValidationException::withMessages(['return_items' => 'Producto inválido para devolución.']);
            }

            $lineSubtotal = $qty * (float) $line->price;
            $taxRate = (float) ($line->tax_rate ?? 0);
            $taxAmount = $lineSubtotal * ($taxRate / 100);

            $subtotal += $lineSubtotal;
            $tax += $taxAmount;
        }

        return $subtotal + $tax;
    }

    private function calculateSaleTotal(PosSession $session, array $saleItems): float
    {
        if (empty($saleItems)) {
            return 0.0;
        }

        $posConfig = $session->posConfig()->with('tax')->first();
        $applyTax = (bool) ($posConfig?->apply_tax ?? true);
        $pricesIncludeTax = (bool) ($posConfig?->prices_include_tax ?? false);
        $tax = $applyTax && $posConfig?->tax_id ? $posConfig->tax : null;
        $taxRate = $applyTax && $tax ? (float) $tax->rate_percent : 0.0;

        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($saleItems as $item) {
            $quantity = (float) $item['qty'];
            $inputUnitPrice = (float) $item['price'];
            $lineSubtotal = $quantity * $inputUnitPrice;

            if ($applyTax && $taxRate > 0) {
                if ($pricesIncludeTax) {
                    $lineTotal = (float) $lineSubtotal;
                    $net = $lineTotal / (1 + ($taxRate / 100));
                    $taxAmount += $lineTotal - $net;
                    $subtotal += $net;
                } else {
                    $subtotal += $lineSubtotal;
                    $taxAmount += $lineSubtotal * ($taxRate / 100);
                }
            } else {
                $subtotal += $lineSubtotal;
            }
        }

        return $subtotal + $taxAmount;
    }
}
