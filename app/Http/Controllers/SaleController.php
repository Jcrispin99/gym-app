<?php

namespace App\Http\Controllers;

use App\Jobs\SendSunatInvoice;
use App\Models\Journal;
use App\Models\Partner;
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
        // Solo se pueden editar ventas en borrador
        if ($sale->status !== 'draft') {
            return redirect()->route('sales.index')
                ->with('error', 'Solo se pueden editar ventas en borrador.');
        }

        $sale->load(['products.productProduct']);
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
        // Solo se pueden editar ventas en borrador
        if ($sale->status !== 'draft') {
            return redirect()->route('sales.index')
                ->with('error', 'Solo se pueden editar ventas en borrador.');
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

        DB::transaction(function () use ($sale) {
            // Cambiar estado a posted
            $sale->update(['status' => 'posted']);

            // Reducir inventario
            $kardexService = new KardexService();
            foreach ($sale->products as $line) {
                // Para ventas, usamos registerExit. El costo será calculado automáticamente
                // por el servicio usando el Costo Promedio actual.
                $kardexService->registerExit(
                    $sale,
                    [
                        'id' => $line->product_product_id,
                        'quantity' => $line->quantity
                    ],
                    $sale->warehouse_id,
                    "Venta Publicada {$sale->serie}-{$sale->correlative}"
                );
            }
        });

        SendSunatInvoice::dispatch($sale->id)->afterCommit();

        return redirect()->route('sales.index')
            ->with('success', 'Venta publicada exitosamente.');
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
