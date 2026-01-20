<?php

namespace App\Http\Controllers;

use App\Jobs\SendSunatInvoice;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\Productable;
use App\Models\ProductProduct;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\SequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Sale::with(['partner', 'warehouse', 'journal', 'user', 'products.productProduct'])
            ->orderBy('created_at', 'desc');

        // Filtro por estado
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filtro por estado de pago
        if ($request->has('payment_status') && $request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
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

        $sales = $query->paginate(15);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'status', 'payment_status']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Partner::customers()->active()->get();
        $warehouses = Warehouse::all();
        $taxes = Tax::active()->get();

        return Inertia::render('Sales/Create', [
            'customers' => $customers,
            'warehouses' => $warehouses,
            'taxes' => $taxes,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
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

        DB::transaction(function () use ($validated) {
            // Obtener company_id del usuario o de la sesión
            $companyId = $validated['company_id'] ?? Auth::user()?->company_id;

            // Obtener el journal por defecto para ventas de esta compañía
            $defaultJournal = Journal::where('type', 'sale')
                ->where('company_id', $companyId)
                ->first();

            if (! $defaultJournal) {
                throw new \Exception('No se encontró un diario de ventas para esta compañía. Por favor crea uno primero.');
            }

            // Generar serie y correlativo usando SequenceService
            $numberParts = SequenceService::getNextParts($defaultJournal->id);

            // Crear venta en estado draft con serie/correlativo
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

            // Crear products y calcular totales
            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['products'] as $productData) {
                $tax = isset($productData['tax_id'])
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

            // Actualizar totales de la venta
            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);
        });

        return redirect()->route('sales.index')
            ->with('success', 'Venta creada como borrador exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale)
    {
        $sale->load(['partner', 'warehouse', 'journal', 'user', 'products.productProduct']);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sale $sale)
    {
        $sale->load(['journal', 'products.productProduct']);
        $customers = Partner::customers()->active()->get();
        $warehouses = Warehouse::all();
        $taxes = Tax::active()->get();

        return Inertia::render('Sales/Edit', [
            'sale' => $sale,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'taxes' => $taxes,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sale $sale)
    {
        if ($sale->status !== 'draft') {
            $validated = $request->validate([
                'notes' => 'nullable|string',
            ]);

            $sale->update([
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()->route('sales.edit', $sale->id)
                ->with('success', 'Notas actualizadas exitosamente.');
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

        DB::transaction(function () use ($validated, $sale) {
            // Actualizar datos básicos
            $sale->update([
                'partner_id' => $validated['partner_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Eliminar products anteriores
            $sale->products()->delete();

            // Crear nuevos products y calcular totales
            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['products'] as $productData) {
                $tax = isset($productData['tax_id'])
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

            // Actualizar totales
            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);
        });

        return redirect()->route('sales.index')
            ->with('success', 'Venta actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale)
    {
        // Solo se pueden eliminar ventas en borrador
        if ($sale->status !== 'draft') {
            return redirect()->route('sales.index')
                ->with('error', 'Solo se pueden eliminar ventas en borrador.');
        }

        $sale->delete();

        return redirect()->route('sales.index')
            ->with('success', 'Venta eliminada exitosamente.');
    }

    /**
     * Post/Publish the sale (draft -> posted)
     */
    public function post(Sale $sale)
    {
        if ($sale->status !== 'draft') {
            return redirect()->route('sales.index')
                ->with('error', 'Solo se pueden publicar ventas en borrador.');
        }

        $sale->loadMissing(['journal', 'products.productProduct.template', 'originalSale.products', 'originalSale.journal']);
        $docType = (string) ($sale->journal?->document_type_code ?? '');

        if ($docType === '07') {
            $original = $sale->originalSale;
            if (! $original) {
                return redirect()->route('sales.index')
                    ->with('error', 'La Nota de Crédito no tiene venta original asociada.');
            }

            if ($original->status !== 'posted') {
                return redirect()->route('sales.index')
                    ->with('error', 'La venta original debe estar publicada.');
            }

            if (empty($original->serie) || empty($original->correlative)) {
                return redirect()->route('sales.index')
                    ->with('error', 'La venta original no tiene numeración.');
            }

            $originalDocType = (string) ($original->journal?->document_type_code ?? '');
            if (! in_array($originalDocType, ['01', '03'], true)) {
                return redirect()->route('sales.index')
                    ->with('error', 'La venta original no es un documento 01/03.');
            }

            $originalQtyByProduct = [];
            foreach ($original->products as $line) {
                $pid = (int) $line->product_product_id;
                $originalQtyByProduct[$pid] = ($originalQtyByProduct[$pid] ?? 0) + (float) $line->quantity;
            }

            $creditedQtyByProduct = DB::table('productables')
                ->join('sales', 'sales.id', '=', 'productables.productable_id')
                ->where('productables.productable_type', Sale::class)
                ->where('sales.original_sale_id', $original->id)
                ->where('sales.status', 'posted')
                ->selectRaw('productables.product_product_id, SUM(productables.quantity) as qty')
                ->groupBy('productables.product_product_id')
                ->pluck('qty', 'productables.product_product_id')
                ->all();

            foreach ($sale->products as $line) {
                $pid = (int) $line->product_product_id;
                $qty = (float) $line->quantity;

                $origQty = (float) ($originalQtyByProduct[$pid] ?? 0);
                if ($origQty <= 0) {
                    return redirect()->route('sales.index')
                        ->with('error', 'La Nota de Crédito contiene productos que no están en la venta original.');
                }

                $creditedQty = (float) ($creditedQtyByProduct[$pid] ?? 0);
                $availableQty = $origQty - $creditedQty;

                if ($qty <= 0 || $qty > $availableQty + 0.00001) {
                    return redirect()->route('sales.index')
                        ->with('error', 'La cantidad a devolver excede lo disponible para devolución.');
                }
            }
        } elseif (! in_array($docType, ['01', '03', ''], true)) {
            return redirect()->route('sales.index')
                ->with('error', 'Tipo de documento no soportado para publicar.');
        }

        DB::transaction(function () use ($sale) {
            // Cambiar estado a posted
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

        SendSunatInvoice::dispatch($sale->id)->afterCommit();

        return redirect()->route('sales.index')
            ->with('success', 'Venta publicada exitosamente.');
    }

    public function createCreditNote(Sale $sale)
    {
        $sale->loadMissing(['products', 'journal']);

        if ($sale->status !== 'posted') {
            return redirect()->route('sales.index')
                ->with('error', 'Solo se pueden crear notas desde ventas publicadas.');
        }

        if (empty($sale->serie) || empty($sale->correlative)) {
            return redirect()->route('sales.index')
                ->with('error', 'La venta no tiene numeración. No se puede crear nota.');
        }

        $docType = (string) ($sale->journal?->document_type_code ?? '');
        if (! in_array($docType, ['01', '03'], true)) {
            return redirect()->route('sales.index')
                ->with('error', 'Solo se pueden crear notas desde documentos 01/03.');
        }

        $companyId = $sale->company_id;
        $creditJournal = Journal::query()
            ->where('company_id', $companyId)
            ->where('type', 'sale')
            ->where('document_type_code', '07')
            ->first();

        if (! $creditJournal) {
            return redirect()->route('sales.index')
                ->with('error', 'No se encontró un diario de Nota de Crédito (07) para esta compañía.');
        }

        $creditSale = null;

        DB::transaction(function () use ($sale, $creditJournal, &$creditSale) {
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

            foreach ($sale->products as $line) {
                $quantity = (float) $line->quantity;
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
            return redirect()->route('sales.index')
                ->with('error', 'No se pudo crear el borrador de Nota de Crédito.');
        }

        return redirect()->route('sales.edit', $creditSale->id)
            ->with('success', 'Borrador de Nota de Crédito creado. Ajusta ítems/cantidades si es parcial.');
    }

    /**
     * Cancel the sale (posted -> cancelled)
     */
    public function cancel(Sale $sale)
    {
        if ($sale->status !== 'posted') {
            return redirect()->route('sales.index')
                ->with('error', 'Solo se pueden cancelar ventas publicadas.');
        }

        DB::transaction(function () use ($sale) {
            // Cambiar estado a cancelled
            $sale->update(['status' => 'cancelled']);

            // Devolver inventario al kardex
            $kardexService = new KardexService();
            foreach ($sale->products as $line) {
                // Para devolución, necesitamos re-ingresar el stock.
                // IMPORTANTE: No usar $line->price (Precio de Venta) porque inflaría el costo.
                // Usamos el costo promedio actual del almacén para la re-entrada.
                $lastRecord = $kardexService->getLastRecord($line->product_product_id, $sale->warehouse_id);
                $currentCost = $lastRecord['cost'] ?? 0;

                $kardexService->registerEntry(
                    $sale,
                    [
                        'id' => $line->product_product_id,
                        'quantity' => $line->quantity,
                        'price' => $currentCost, // Usamos el costo actual
                        'subtotal' => $line->quantity * $currentCost
                    ],
                    $sale->warehouse_id,
                    "Devolución venta cancelada {$sale->serie}-{$sale->correlative}"
                );
            }
        });

        return redirect()->route('sales.index')
            ->with('success', 'Venta cancelada y stock devuelto exitosamente.');
    }

    public function retrySunat(Sale $sale)
    {
        $alreadyAccepted = (bool) (data_get($sale->sunat_response, 'accepted') === true || $sale->sunat_status === 'accepted');

        if ($alreadyAccepted) {
            return back()->with('info', 'La venta ya está aceptada por SUNAT. No se reenviará.');
        }

        $sale->sunat_status = 'pending';
        $sale->sunat_response = null;
        $sale->sunat_sent_at = null;
        $sale->save();

        SendSunatInvoice::dispatch($sale->id)->afterCommit();

        return back()->with('success', 'Envío a SUNAT encolado.');
    }
}
