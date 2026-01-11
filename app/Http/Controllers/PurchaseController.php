<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Partner;
use App\Models\ProductProduct;
use App\Models\Purchase;
use App\Models\Tax;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\SequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Purchase::with(['partner', 'warehouse', 'journal', 'productables.productProduct'])
            ->orderBy('created_at', 'desc');

        // Filtro por estado
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhereHas('partner', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $purchases = $query->paginate(15);

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $suppliers = Partner::where(function($query) {
            $query->where('is_supplier', true)
                  ->orWhere('is_provider', true);
        })->get();
        $warehouses = Warehouse::all();
        $taxes = Tax::active()->get();

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers,
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
            'partner_id' => 'required|exists:partners,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'company_id' => 'nullable|exists:companies,id',
            'vendor_bill_number' => 'nullable|string|max:255',
            'vendor_bill_date' => 'nullable|date',
            'observation' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_product_id' => 'required|exists:product_products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.tax_id' => 'nullable|exists:taxes,id',
        ]);

        DB::transaction(function () use ($validated) {
            // Obtener company_id del usuario o de la sesión
            $companyId = $validated['company_id'] ?? auth()->user()->company_id;

            // Obtener el journal por defecto para compras de esta compañía
            $defaultJournal = Journal::where('type', 'purchase')
                ->where('company_id', $companyId)
                ->first();

            if (! $defaultJournal) {
                throw new \Exception('No se encontró un diario de compras para esta compañía. Por favor crea uno primero.');
            }

            // Generar serie y correlativo usando SequenceService
            $numberParts = SequenceService::getNextParts($defaultJournal->id);

            // Crear compra en estado draft con serie/correlativo
            $purchase = Purchase::create([
                'partner_id' => $validated['partner_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'journal_id' => $defaultJournal->id,
                'company_id' => $companyId,
                'vendor_bill_number' => $validated['vendor_bill_number'] ?? null,
                'vendor_bill_date' => $validated['vendor_bill_date'] ?? null,
                'observation' => $validated['observation'] ?? null,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'serie' => $numberParts['serie'],
                'correlative' => $numberParts['correlative'],
                'total' => 0,
            ]);

            // Crear productables y calcular total
            $total = 0;
            foreach ($validated['products'] as $productData) {
                $tax = isset($productData['tax_id'])
                    ? Tax::find($productData['tax_id'])
                    : null;

                $quantity = (float) $productData['quantity'];
                $price = (float) $productData['price'];
                $subtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $subtotal * ($taxRate / 100);
                $lineTotal = $subtotal + $taxAmount;

                $purchase->productables()->create([
                    'product_product_id' => $productData['product_product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'tax_id' => $productData['tax_id'] ?? null,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            // Actualizar total de la compra
            $purchase->update(['total' => $total]);
        });

        return redirect()->route('purchases.index')
            ->with('success', 'Compra creada como borrador exitosamente.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Purchase $purchase)
    {
        $purchase->load(['productables.productProduct', 'productables.tax']);

        // Load activity log
        $activities = \Spatie\Activitylog\Models\Activity::forSubject($purchase)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        $suppliers = Partner::where(function($query) {
            $query->where('is_supplier', true)
                  ->orWhere('is_provider', true);
        })->get();
        $warehouses = Warehouse::all();
        $taxes = Tax::active()->get();

        return Inertia::render('Purchases/Edit', [
            'purchase' => $purchase,
            'activities' => $activities,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'taxes' => $taxes,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchase $purchase)
    {
        // Solo se pueden editar compras en estado draft
        if ($purchase->status !== 'draft') {
            return redirect()->back()
                ->with('error', 'Solo se pueden editar compras en estado borrador.');
        }

        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'vendor_bill_number' => 'nullable|string|max:255',
            'vendor_bill_date' => 'nullable|date',
            'observation' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_product_id' => 'required|exists:product_products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.tax_id' => 'nullable|exists:taxes,id',
        ]);

        DB::transaction(function () use ($validated, $purchase) {
            // Actualizar compra
            $purchase->update([
                'partner_id' => $validated['partner_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'vendor_bill_number' => $validated['vendor_bill_number'] ?? null,
                'vendor_bill_date' => $validated['vendor_bill_date'] ?? null,
                'observation' => $validated['observation'] ?? null,
            ]);

            // Eliminar productables existentes
            $purchase->productables()->delete();

            // Crear nuevos productables y calcular total
            $total = 0;
            foreach ($validated['products'] as $productData) {
                $tax = isset($productData['tax_id'])
                    ? Tax::find($productData['tax_id'])
                    : null;

                $quantity = (float) $productData['quantity'];
                $price = (float) $productData['price'];
                $subtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $subtotal * ($taxRate / 100);
                $lineTotal = $subtotal + $taxAmount;

                $purchase->productables()->create([
                    'product_product_id' => $productData['product_product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'tax_id' => $productData['tax_id'] ?? null,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            // Actualizar total de la compra
            $purchase->update(['total' => $total]);
        });

        return redirect()->route('purchases.index')
            ->with('success', 'Compra actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
        // Solo se pueden eliminar compras en estado draft
        if ($purchase->status !== 'draft') {
            return redirect()->back()
                ->with('error', 'Solo se pueden eliminar compras en estado borrador.');
        }

        DB::transaction(function () use ($purchase) {
            $purchase->productables()->delete();
            $purchase->delete();
        });

        return redirect()->route('purchases.index')
            ->with('success', 'Compra eliminada exitosamente.');
    }

    /**
     * Post/Publish the purchase (draft -> posted)
     */
    public function post(Purchase $purchase)
    {
        if ($purchase->status !== 'draft') {
            return redirect()->back()
                ->with('error', 'Solo se pueden publicar compras en estado borrador.');
        }

        DB::transaction(function () use ($purchase) {
            // Cambiar estado a posted (serie/correlativo ya fue generado al crear)
            $purchase->update([
                'status' => 'posted',
            ]);

            // Registrar log personalizado
            activity()
                ->performedOn($purchase)
                ->causedBy(auth()->user())
                ->log('Compra Publicada');

            // Registrar movimientos de inventario con KardexService
            $kardexService = new KardexService;

            foreach ($purchase->productables as $productable) {
                $kardexService->registerEntry(
                    $purchase,
                    [
                        'id' => $productable->product_product_id,
                        'quantity' => $productable->quantity,
                        'price' => $productable->price,
                        'subtotal' => $productable->subtotal,
                    ],
                    $purchase->warehouse_id,
                    "Compra {$purchase->serie}-{$purchase->correlative}"
                );
            }
        });

        return redirect()->route('purchases.index')
            ->with('success', 'Compra publicada exitosamente. Inventario actualizado.');
    }

    /**
     * Cancel the purchase (posted -> cancelled)
     */
    public function cancel(Purchase $purchase)
    {
        if ($purchase->status !== 'posted') {
            return redirect()->back()
                ->with('error', 'Solo se pueden cancelar compras publicadas.');
        }

        DB::transaction(function () use ($purchase) {
            // Revertir movimientos de inventario
            $kardexService = new KardexService;

            foreach ($purchase->productables as $productable) {
                $kardexService->registerExit(
                    $purchase,
                    [
                        'id' => $productable->product_product_id,
                        'quantity' => $productable->quantity,
                    ],
                    $purchase->warehouse_id,
                    "Cancelación de Compra {$purchase->serie}-{$purchase->correlative}"
                );
            }

            $purchase->update(['status' => 'cancelled']);

            // Registrar log personalizado
            activity()
                ->performedOn($purchase)
                ->causedBy(auth()->user())
                ->log('Compra Cancelada');
        });

        return redirect()->route('purchases.index')
            ->with('success', 'Compra cancelada. Inventario revertido.');
    }
}
