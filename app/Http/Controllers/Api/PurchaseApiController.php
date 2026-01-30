<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\Purchase;
use App\Models\Tax;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\SequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class PurchaseApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,posted,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Purchase::query()
            ->with(['partner', 'warehouse'])
            ->orderBy('created_at', 'desc');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhereHas('partner', function ($partnerQuery) use ($search) {
                        $partnerQuery->where('business_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
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
        $suppliers = Partner::query()
            ->suppliers()
            ->orderBy('business_name')
            ->orderBy('first_name')
            ->get();

        $warehouses = Warehouse::query()->latest()->get();

        $taxes = Tax::query()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'suppliers' => $suppliers,
                'warehouses' => $warehouses,
                'taxes' => $taxes,
            ],
        ]);
    }

    public function show(Purchase $purchase)
    {
        $purchase->load([
            'partner',
            'warehouse',
            'productables.productProduct',
            'productables.tax',
        ]);

        $activities = Activity::forSubject($purchase)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'data' => $purchase,
            'meta' => [
                'activities' => $activities,
            ],
        ]);
    }

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

        $purchaseId = null;

        $user = $request->user();
        $companyId = $validated['company_id'] ?? $user?->company_id;

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No se pudo determinar la compañía.',
            ]);
        }

        DB::transaction(function () use ($validated, &$purchaseId, $companyId) {
            $defaultJournal = Journal::where('type', 'purchase')
                ->where('company_id', $companyId)
                ->first();

            if (! $defaultJournal) {
                throw ValidationException::withMessages([
                    'journal' => 'No se encontró un diario de compras para esta compañía. Por favor crea uno primero.',
                ]);
            }

            $numberParts = SequenceService::getNextParts($defaultJournal->id);

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

            $purchase->update(['total' => $total]);
            $purchaseId = $purchase->id;
        });

        $purchase = Purchase::query()
            ->with([
                'partner',
                'warehouse',
                'productables.productProduct',
                'productables.tax',
            ])
            ->findOrFail($purchaseId);

        return response()->json([
            'data' => $purchase,
        ], 201);
    }

    public function update(Request $request, Purchase $purchase)
    {
        if ($purchase->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden editar compras en estado borrador.',
            ]);
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
            $purchase->update([
                'partner_id' => $validated['partner_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'vendor_bill_number' => $validated['vendor_bill_number'] ?? null,
                'vendor_bill_date' => $validated['vendor_bill_date'] ?? null,
                'observation' => $validated['observation'] ?? null,
            ]);

            $purchase->productables()->delete();

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

            $purchase->update(['total' => $total]);
        });

        return response()->json([
            'data' => $purchase->fresh()->load([
                'partner',
                'warehouse',
                'productables.productProduct',
                'productables.tax',
            ]),
        ]);
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden eliminar compras en estado borrador.',
            ]);
        }

        DB::transaction(function () use ($purchase) {
            $purchase->productables()->delete();
            $purchase->delete();
        });

        return response()->json([
            'ok' => true,
        ]);
    }

    public function post(Request $request, Purchase $purchase)
    {
        if ($purchase->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden publicar compras en estado borrador.',
            ]);
        }

        DB::transaction(function () use ($purchase) {
            $purchase->update([
                'status' => 'posted',
            ]);

            activity()
                ->performedOn($purchase)
                ->causedBy(request()->user())
                ->log('Compra Publicada');

            $purchase->load('productables');

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

        return response()->json([
            'data' => $purchase->fresh()->load([
                'partner',
                'warehouse',
                'productables.productProduct',
                'productables.tax',
            ]),
        ]);
    }

    public function cancel(Request $request, Purchase $purchase)
    {
        if ($purchase->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden cancelar compras publicadas.',
            ]);
        }

        DB::transaction(function () use ($purchase) {
            $purchase->load('productables');

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

            activity()
                ->performedOn($purchase)
                ->causedBy(request()->user())
                ->log('Compra Cancelada');
        });

        return response()->json([
            'data' => $purchase->fresh()->load([
                'partner',
                'warehouse',
                'productables.productProduct',
                'productables.tax',
            ]),
        ]);
    }
}
