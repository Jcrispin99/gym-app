<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers
     */
    public function index()
    {
        $suppliers = Partner::query()
            ->with(['company'])
            ->suppliers() // Using the scope from Partner model
            ->latest()
            ->get();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Show the form for creating a new supplier
     */
    public function create()
    {
        // Companies might be needed if suppliers are linked to companies
        $companies = Company::orderBy('trade_name')->get();

        return Inertia::render('Suppliers/Create', [
            'companies' => $companies,
        ]);
    }

    /**
     * Store a newly created supplier or update existing partner
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',

            // Documents
            'document_type' => 'required|in:DNI,RUC,CE,Passport',
            'document_number' => 'required|string|max:20', // Removed unique rule for upsert logic

            // Basic Info
            'business_name' => 'required_if:document_type,RUC|nullable|string|max:150',
            'first_name' => 'required_unless:document_type,RUC|nullable|string|max:100',
            'last_name' => 'required_unless:document_type,RUC|nullable|string|max:100',

            // Contact
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',

            // Address
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',

            // Supplier specific
            'payment_terms' => 'nullable|string',
            'provider_category' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Upsert Logic: Check if partner exists
        $partner = Partner::where('document_type', $validated['document_type'])
            ->where('document_number', $validated['document_number'])
            ->first();

        if ($partner) {
            // Update existing partner
            $partner->fill($validated);
            $partner->is_supplier = true; // Activate supplier flag
            $partner->save();
            $message = 'Proveedor actualizado exitosamente (Partner existente)';
        } else {
            // Create new partner
            $validated['is_supplier'] = true;
            $validated['is_member'] = false; // Default
            $validated['status'] = 'active';
            Partner::create($validated);
            $message = 'Proveedor registrado exitosamente';
        }

        return redirect()->route('suppliers.index')
            ->with('success', $message);
    }

    /**
     * Show the form for editing the specified supplier
     */
    public function edit(Partner $supplier)
    {
        if (! $supplier->is_supplier) {
            abort(404);
        }

        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    /**
     * Update the specified supplier
     */
    public function update(Request $request, Partner $supplier)
    {
        if (! $supplier->is_supplier) {
            abort(404);
        }

        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',

            // Documents
            'document_type' => 'required|in:DNI,RUC,CE,Passport',
            'document_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('partners', 'document_number')
                    ->ignore($supplier->id)
                    ->where(fn ($q) => $q->where('document_type', $request->input('document_type'))),
            ],

            // Basic Info
            'business_name' => 'required_if:document_type,RUC|nullable|string|max:150',
            'first_name' => 'required_unless:document_type,RUC|nullable|string|max:100',
            'last_name' => 'required_unless:document_type,RUC|nullable|string|max:100',

            // Contact
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',

            // Address
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',

            // Supplier specific
            'payment_terms' => 'nullable|string',
            'provider_category' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor actualizado exitosamente');
    }

    /**
     * Remove the specified supplier
     */
    public function destroy(Partner $supplier)
    {
        if (! $supplier->is_supplier) {
            abort(404);
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor eliminado exitosamente');
    }
}
