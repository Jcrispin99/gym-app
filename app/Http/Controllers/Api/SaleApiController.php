<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendSunatInvoice;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\SequenceService;
use App\Services\SaleRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class SaleApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,posted,cancelled',
            'payment_status' => 'nullable|string|in:unpaid,partial,paid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Sale::query()
            ->with(['partner', 'warehouse', 'journal', 'user', 'products.productProduct'])
            ->orderBy('created_at', 'desc');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhereHas('partner', function ($pq) use ($search) {
                        $pq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function formOptions(Request $request)
    {
        $customers = Partner::query()->customers()->active()->get();
        $warehouses = Warehouse::query()->latest()->get();
        $taxes = Tax::query()->active()->orderByDesc('is_default')->orderBy('name')->get();

        return response()->json([
            'data' => [
                'customers' => $customers,
                'warehouses' => $warehouses,
                'taxes' => $taxes,
            ],
        ]);
    }

    public function show(Sale $sale)
    {
        $sale->load(['partner', 'warehouse', 'journal', 'user', 'products.productProduct', 'products.tax']);

        $activities = Activity::forSubject($sale)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        $originSale = null;
        if (! empty($sale->original_sale_id)) {
            $origin = Sale::query()
                ->with(['journal', 'partner'])
                ->find($sale->original_sale_id);

            if ($origin) {
                $originSale = [
                    'id' => $origin->id,
                    'document' => $origin->document_number,
                    'status' => $origin->status,
                    'journal_code' => $origin->journal?->code,
                    'doc_type' => (string) ($origin->journal?->document_type_code ?? ''),
                    'partner_name' => $origin->partner?->display_name,
                ];
            }
        }

        $creditNotes = [];
        if (empty($sale->original_sale_id)) {
            $creditNotes = Sale::query()
                ->where('original_sale_id', $sale->id)
                ->with(['journal'])
                ->orderByDesc('id')
                ->get()
                ->map(function (Sale $note) {
                    return [
                        'id' => $note->id,
                        'document' => $note->document_number,
                        'status' => $note->status,
                        'journal_code' => $note->journal?->code,
                        'doc_type' => (string) ($note->journal?->document_type_code ?? ''),
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'data' => $sale,
            'meta' => [
                'activities' => $activities,
                'originSale' => $originSale,
                'creditNotes' => $creditNotes,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'nullable|exists:partners,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'company_id' => 'nullable|exists:companies,id',
            'notes' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_product_id' => 'required|exists:product_products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.tax_id' => 'nullable|exists:taxes,id',
        ]);

        $sale = null;

        DB::transaction(function () use ($validated, &$sale) {
            $companyId = $validated['company_id'] ?? Auth::user()?->company_id;

            $defaultJournal = Journal::query()
                ->where('type', 'sale')
                ->where('company_id', $companyId)
                ->first();

            if (! $defaultJournal) {
                throw ValidationException::withMessages([
                    'journal' => ['No se encontró un diario de ventas para esta compañía.'],
                ]);
            }

            $numberParts = SequenceService::getNextParts($defaultJournal->id);

            $sale = Sale::create([
                'partner_id' => $validated['partner_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'journal_id' => $defaultJournal->id,
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'serie' => $numberParts['serie'],
                'correlative' => $numberParts['correlative'],
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['products'] as $productData) {
                $tax = isset($productData['tax_id']) && $productData['tax_id']
                    ? Tax::find($productData['tax_id'])
                    : null;

                $quantity = (float) $productData['quantity'];
                $price = (float) $productData['price'];
                $lineSubtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;

                $sale->products()->create([
                    'product_product_id' => $productData['product_product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'tax_id' => $productData['tax_id'] ?? null,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $totalTax += $taxAmount;
            }

            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);
        });

        if (! $sale instanceof Sale) {
            throw ValidationException::withMessages([
                'sale' => ['No se pudo crear la venta.'],
            ]);
        }

        activity()
            ->performedOn($sale)
            ->event('created')
            ->log('Venta creada como borrador');

        return response()->json([
            'data' => $sale->load(['partner', 'warehouse', 'journal', 'products.productProduct', 'products.tax']),
        ], 201);
    }

    public function update(Request $request, Sale $sale)
    {
        if ($sale->status !== 'draft') {
            $validated = $request->validate([
                'notes' => 'nullable|string',
            ]);

            $sale->update([
                'notes' => $validated['notes'] ?? null,
            ]);

            activity()
                ->performedOn($sale)
                ->event('updated')
                ->log('Notas actualizadas');

            return response()->json([
                'data' => $sale->fresh()->load(['partner', 'warehouse', 'journal', 'products.productProduct', 'products.tax']),
            ]);
        }

        $validated = $request->validate([
            'partner_id' => 'nullable|exists:partners,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_product_id' => 'required|exists:product_products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.tax_id' => 'nullable|exists:taxes,id',
        ]);

        $sale->loadMissing('products');
        $beforeLines = $this->aggregateSaleLines($sale->products->map(function ($p) {
            return [
                'product_product_id' => $p->product_product_id,
                'quantity' => $p->quantity,
                'price' => $p->price,
                'tax_id' => $p->tax_id,
            ];
        })->all());
        $afterLines = $this->aggregateSaleLines($validated['products']);
        $productDiff = $this->diffSaleLines($beforeLines, $afterLines);

        DB::transaction(function () use ($validated, $sale) {
            $sale->update([
                'partner_id' => $validated['partner_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $sale->products()->delete();

            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['products'] as $productData) {
                $tax = isset($productData['tax_id']) && $productData['tax_id']
                    ? Tax::find($productData['tax_id'])
                    : null;

                $quantity = (float) $productData['quantity'];
                $price = (float) $productData['price'];
                $lineSubtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;

                $sale->products()->create([
                    'product_product_id' => $productData['product_product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'tax_id' => $productData['tax_id'] ?? null,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $totalTax += $taxAmount;
            }

            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);
        });

        if (! empty($productDiff['added']) || ! empty($productDiff['removed']) || ! empty($productDiff['changed'])) {
            activity()
                ->performedOn($sale)
                ->event('updated')
                ->withProperties([
                    'products' => $productDiff,
                ])
                ->log('Productos actualizados');
        }

        return response()->json([
            'data' => $sale->fresh()->load(['partner', 'warehouse', 'journal', 'products.productProduct', 'products.tax']),
        ]);
    }

    public function destroy(Sale $sale)
    {
        if ($sale->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden eliminar ventas en borrador.'],
            ]);
        }

        $sale->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function post(Sale $sale)
    {
        if ($sale->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden publicar ventas en borrador.'],
            ]);
        }

        $sale->loadMissing(['journal', 'products.productProduct.template', 'originalSale.products', 'originalSale.journal']);
        $docType = (string) ($sale->journal?->document_type_code ?? '');

        if ($docType === '07') {
            $original = $sale->originalSale;
            if (! $original) {
                throw ValidationException::withMessages([
                    'original_sale' => ['La Nota de Crédito no tiene venta original asociada.'],
                ]);
            }

            if ($original->status !== 'posted') {
                throw ValidationException::withMessages([
                    'original_sale' => ['La venta original debe estar publicada.'],
                ]);
            }

            if (empty($original->serie) || empty($original->correlative)) {
                throw ValidationException::withMessages([
                    'original_sale' => ['La venta original no tiene numeración.'],
                ]);
            }

            $originalDocType = (string) ($original->journal?->document_type_code ?? '');
            if (! in_array($originalDocType, ['01', '03'], true)) {
                throw ValidationException::withMessages([
                    'original_sale' => ['La venta original no es un documento 01/03.'],
                ]);
            }

            $refundService = new SaleRefundService();
            $originalQtyByProduct = $refundService->originalQtyByProduct($original);
            $creditedQtyByProduct = $refundService->creditedQtyByProduct($original->id);

            foreach ($sale->products as $line) {
                $pid = (int) $line->product_product_id;
                $qty = (float) $line->quantity;

                $origQty = (float) ($originalQtyByProduct[$pid] ?? 0);
                if ($origQty <= 0) {
                    throw ValidationException::withMessages([
                        'products' => ['La Nota de Crédito contiene productos que no están en la venta original.'],
                    ]);
                }

                $creditedQty = (float) ($creditedQtyByProduct[$pid] ?? 0);
                $availableQty = $origQty - $creditedQty;

                if ($qty <= 0 || $qty > $availableQty + 0.00001) {
                    throw ValidationException::withMessages([
                        'products' => ['La cantidad a devolver excede lo disponible para devolución.'],
                    ]);
                }
            }
        } elseif (! in_array($docType, ['01', '03', ''], true)) {
            throw ValidationException::withMessages([
                'journal' => ['Tipo de documento no soportado para publicar.'],
            ]);
        }

        DB::transaction(function () use ($sale) {
            $sale->update(['status' => 'posted']);

            $kardexService = new KardexService();
            $docType = (string) ($sale->journal?->document_type_code ?? '');

            if ($docType === '07') {
                $original = $sale->originalSale;
                $ref = $original ? "{$original->serie}-{$original->correlative}" : '';

                foreach ($sale->products as $line) {
                    $tracksInventory = $line->productProduct?->template?->tracks_inventory ?? true;
                    if (! $tracksInventory) {
                        continue;
                    }

                    $last = $kardexService->getLastRecord($line->product_product_id, $sale->warehouse_id);
                    $unitCost = (float) ($last['cost'] ?? 0);
                    $qty = (float) $line->quantity;

                    $kardexService->registerEntry(
                        $sale,
                        [
                            'id' => $line->product_product_id,
                            'quantity' => $qty,
                            'price' => $unitCost,
                            'subtotal' => $qty * $unitCost,
                        ],
                        $sale->warehouse_id,
                        "Nota de Crédito {$sale->serie}-{$sale->correlative} {$ref}"
                    );
                }
            } else {
                foreach ($sale->products as $line) {
                    $kardexService->registerExit(
                        $sale,
                        [
                            'id' => $line->product_product_id,
                            'quantity' => $line->quantity,
                        ],
                        $sale->warehouse_id,
                        "Venta Publicada {$sale->serie}-{$sale->correlative}"
                    );
                }
            }
        });

        $docType = (string) ($sale->journal?->document_type_code ?? '');
        $logProps = [];
        $logMessage = 'Documento publicado';
        if ($docType === '07') {
            $origin = $sale->originalSale;
            $logMessage = 'Nota de Crédito publicada';
            $logProps = [
                'origin_sale_id' => $origin?->id,
                'origin_document' => $origin?->document_number,
            ];
        } elseif (in_array($docType, ['01', '03'], true)) {
            $logMessage = 'Venta publicada';
        }

        activity()
            ->performedOn($sale)
            ->event('updated')
            ->withProperties($logProps)
            ->log($logMessage);

        SendSunatInvoice::dispatch($sale->id)->afterCommit();

        return response()->json([
            'data' => $sale->fresh()->load(['partner', 'warehouse', 'journal', 'products.productProduct', 'products.tax']),
        ]);
    }

    public function cancel(Sale $sale)
    {
        if ($sale->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden cancelar ventas publicadas.'],
            ]);
        }

        $sale->loadMissing(['products', 'journal']);

        DB::transaction(function () use ($sale) {
            $sale->update(['status' => 'cancelled']);

            $kardexService = new KardexService();
            foreach ($sale->products as $line) {
                $lastRecord = $kardexService->getLastRecord($line->product_product_id, $sale->warehouse_id);
                $currentCost = $lastRecord['cost'] ?? 0;

                $kardexService->registerEntry(
                    $sale,
                    [
                        'id' => $line->product_product_id,
                        'quantity' => $line->quantity,
                        'price' => $currentCost,
                        'subtotal' => $line->quantity * $currentCost,
                    ],
                    $sale->warehouse_id,
                    "Devolución venta cancelada {$sale->serie}-{$sale->correlative}"
                );
            }
        });

        activity()
            ->performedOn($sale)
            ->event('updated')
            ->log('Documento cancelado');

        return response()->json([
            'data' => $sale->fresh()->load(['partner', 'warehouse', 'journal', 'products.productProduct', 'products.tax']),
        ]);
    }

    public function createCreditNote(Sale $sale)
    {
        $sale->loadMissing(['products', 'journal']);

        if ($sale->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden crear notas desde ventas publicadas.'],
            ]);
        }

        if (empty($sale->serie) || empty($sale->correlative)) {
            throw ValidationException::withMessages([
                'sale' => ['La venta no tiene numeración. No se puede crear nota.'],
            ]);
        }

        $docType = (string) ($sale->journal?->document_type_code ?? '');
        if (! in_array($docType, ['01', '03'], true)) {
            throw ValidationException::withMessages([
                'journal' => ['Solo se pueden crear notas desde documentos 01/03.'],
            ]);
        }

        $companyId = $sale->company_id;
        $refundService = new SaleRefundService();
        $creditJournal = $refundService->resolveCreditNoteJournalForCompany($companyId, $docType, $sale->journal?->code);

        if (! $creditJournal) {
            throw ValidationException::withMessages([
                'journal' => ['No se encontró un diario de Nota de Crédito (07) para esta compañía.'],
            ]);
        }

        $remainingQtyByProduct = $refundService->availableQtyByProduct($sale);
        $hasAvailable = false;
        foreach ($remainingQtyByProduct as $remaining) {
            if ((float) $remaining > 0) {
                $hasAvailable = true;
                break;
            }
        }

        if (! $hasAvailable) {
            throw ValidationException::withMessages([
                'products' => ['No hay cantidades disponibles para devolución en esta venta.'],
            ]);
        }

        $creditSale = null;

        DB::transaction(function () use ($sale, $creditJournal, $remainingQtyByProduct, &$creditSale) {
            $numberParts = SequenceService::getNextParts($creditJournal->id);

            $creditSale = Sale::create([
                'partner_id' => $sale->partner_id,
                'warehouse_id' => $sale->warehouse_id,
                'journal_id' => $creditJournal->id,
                'company_id' => $sale->company_id,
                'original_sale_id' => $sale->id,
                'user_id' => Auth::id(),
                'notes' => null,
                'status' => 'draft',
                'payment_status' => 'paid',
                'serie' => $numberParts['serie'],
                'correlative' => $numberParts['correlative'],
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            $totalTax = 0;
            $remaining = $remainingQtyByProduct;

            foreach ($sale->products as $line) {
                $pid = (int) $line->product_product_id;
                $availableForProduct = (float) ($remaining[$pid] ?? 0);
                if ($availableForProduct <= 0) {
                    continue;
                }

                $lineQty = (float) $line->quantity;
                $quantity = min($lineQty, $availableForProduct);
                if ($quantity <= 0) {
                    continue;
                }

                $remaining[$pid] = max(0, $availableForProduct - $quantity);
                $price = (float) $line->price;
                $lineSubtotal = $quantity * $price;
                $taxRate = (float) ($line->tax_rate ?? 0);
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;

                $creditSale->products()->create([
                    'product_product_id' => $line->product_product_id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'tax_id' => $line->tax_id,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $totalTax += $taxAmount;
            }

            $creditSale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);
        });

        if (! $creditSale) {
            throw ValidationException::withMessages([
                'sale' => ['No se pudo crear el borrador de Nota de Crédito.'],
            ]);
        }

        activity()
            ->performedOn($sale)
            ->event('updated')
            ->withProperties([
                'credit_note_id' => $creditSale->id,
            ])
            ->log('Borrador de Nota de Crédito creado');

        activity()
            ->performedOn($creditSale)
            ->event('created')
            ->withProperties([
                'origin_sale_id' => $sale->id,
            ])
            ->log('Nota de Crédito creada desde documento origen');

        return response()->json([
            'data' => $creditSale->load(['partner', 'warehouse', 'journal', 'products.productProduct', 'products.tax', 'originalSale.journal', 'originalSale.partner']),
        ], 201);
    }

    public function retrySunat(Sale $sale)
    {
        $alreadyAccepted = (bool) (data_get($sale->sunat_response, 'accepted') === true || $sale->sunat_status === 'accepted');

        if ($alreadyAccepted) {
            return response()->json([
                'ok' => true,
                'info' => 'La venta ya está aceptada por SUNAT. No se reenviará.',
            ]);
        }

        $sale->sunat_status = 'pending';
        $sale->sunat_response = null;
        $sale->sunat_sent_at = null;
        $sale->save();

        activity()
            ->performedOn($sale)
            ->event('updated')
            ->log('Reenvío a SUNAT encolado');

        SendSunatInvoice::dispatch($sale->id)->afterCommit();

        return response()->json([
            'ok' => true,
        ]);
    }

    private function aggregateSaleLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $pid = (int) ($line['product_product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }

            $qty = (float) ($line['quantity'] ?? 0);
            $price = (float) ($line['price'] ?? 0);
            $taxId = $line['tax_id'] ?? null;

            if (! isset($out[$pid])) {
                $out[$pid] = [
                    'quantity' => 0.0,
                    'price' => $price,
                    'tax_id' => $taxId,
                ];
            }

            $out[$pid]['quantity'] += $qty;
            $out[$pid]['price'] = $price;
            $out[$pid]['tax_id'] = $taxId;
        }

        ksort($out);

        return $out;
    }

    private function diffSaleLines(array $before, array $after): array
    {
        $added = [];
        $removed = [];
        $changed = [];

        $ids = array_unique(array_merge(array_keys($before), array_keys($after)));
        sort($ids);

        foreach ($ids as $pid) {
            $b = $before[$pid] ?? null;
            $a = $after[$pid] ?? null;

            if ($b === null && $a !== null) {
                $added[$pid] = $a;
                continue;
            }

            if ($b !== null && $a === null) {
                $removed[$pid] = $b;
                continue;
            }

            $bQty = (float) ($b['quantity'] ?? 0);
            $aQty = (float) ($a['quantity'] ?? 0);
            $bPrice = (float) ($b['price'] ?? 0);
            $aPrice = (float) ($a['price'] ?? 0);
            $bTax = $b['tax_id'] ?? null;
            $aTax = $a['tax_id'] ?? null;

            if (abs($bQty - $aQty) > 0.00001 || abs($bPrice - $aPrice) > 0.00001 || $bTax !== $aTax) {
                $changed[$pid] = [
                    'before' => $b,
                    'after' => $a,
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }
}
