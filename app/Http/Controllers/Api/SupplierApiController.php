<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class SupplierApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'company_id' => 'nullable|integer|exists:companies,id',
            'status' => 'nullable|string|in:active,inactive,suspended,blacklisted',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Partner::query()
            ->with('company')
            ->suppliers()
            ->latest();

        if (! empty($validated['company_id'])) {
            $query->where('company_id', (int) $validated['company_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($qBuilder) use ($q) {
                $qBuilder->where('business_name', 'like', '%' . $q . '%')
                    ->orWhere('first_name', 'like', '%' . $q . '%')
                    ->orWhere('last_name', 'like', '%' . $q . '%')
                    ->orWhere('document_number', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        if (! empty($validated['limit'])) {
            $query->limit((int) $validated['limit']);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function formOptions(Request $request)
    {
        $companies = Company::query()
            ->orderBy('trade_name')
            ->get();

        return response()->json([
            'data' => [
                'companies' => $companies,
            ],
        ]);
    }

    public function show(Partner $supplier)
    {
        if (! $supplier->is_supplier) {
            abort(404);
        }

        $supplier->load('company');

        $activities = Activity::forSubject($supplier)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'data' => $supplier,
            'meta' => [
                'activities' => $activities,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'document_type' => 'required|in:DNI,RUC,CE,Passport',
            'document_number' => 'required|string|max:20',
            'business_name' => 'required_if:document_type,RUC|nullable|string|max:150',
            'first_name' => 'required_unless:document_type,RUC|nullable|string|max:100',
            'last_name' => 'required_unless:document_type,RUC|nullable|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'payment_terms' => 'nullable|integer|min:0',
            'provider_category' => 'nullable|string|max:50',
            'supplier_category' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $providerCategory = $validated['provider_category'] ?? $validated['supplier_category'] ?? null;
        unset($validated['supplier_category'], $validated['provider_category']);

        $partner = Partner::query()
            ->where('document_type', $validated['document_type'])
            ->where('document_number', $validated['document_number'])
            ->first();

        if ($partner) {
            $partner->fill($validated);
            $partner->is_supplier = true;
            $partner->provider_category = $providerCategory;
            $partner->save();

            return response()->json([
                'data' => $partner->fresh()->load('company'),
            ]);
        }

        $created = Partner::create(array_merge($validated, [
            'is_supplier' => true,
            'is_member' => false,
            'status' => 'active',
            'provider_category' => $providerCategory,
        ]));

        return response()->json([
            'data' => $created->load('company'),
        ], 201);
    }

    public function update(Request $request, Partner $supplier)
    {
        if (! $supplier->is_supplier) {
            abort(404);
        }

        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'document_type' => 'required|in:DNI,RUC,CE,Passport',
            'document_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('partners', 'document_number')
                    ->ignore($supplier->id)
                    ->where(fn($q) => $q->where('document_type', $request->input('document_type'))),
            ],
            'business_name' => 'required_if:document_type,RUC|nullable|string|max:150',
            'first_name' => 'required_unless:document_type,RUC|nullable|string|max:100',
            'last_name' => 'required_unless:document_type,RUC|nullable|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'payment_terms' => 'nullable|integer|min:0',
            'provider_category' => 'nullable|string|max:50',
            'supplier_category' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,inactive,suspended,blacklisted',
        ]);

        $providerCategory = $validated['provider_category'] ?? $validated['supplier_category'] ?? null;
        unset($validated['supplier_category'], $validated['provider_category']);

        $supplier->update(array_merge($validated, [
            'provider_category' => $providerCategory,
        ]));

        return response()->json([
            'data' => $supplier->fresh()->load('company'),
        ]);
    }

    public function destroy(Partner $supplier)
    {
        if (! $supplier->is_supplier) {
            abort(404);
        }

        $supplier->delete();

        return response()->json([
            'ok' => true,
        ]);
    }
}
