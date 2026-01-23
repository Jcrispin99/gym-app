<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers
     */
    public function index(Request $request)
    {
        $customers = Partner::query()
            ->with(['company', 'user'])
            ->latest()
            ->customers()
            ->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
        ]);
    }

    /**
     * Show the form for creating a new customer
     */
    public function create()
    {
        $companies = Company::orderBy('trade_name')->get();

        return Inertia::render('Customers/Create', [
            'companies' => $companies,
        ]);
    }

    /**
     * Store a newly created customer or update existing partner
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',

            // Documentos
            'document_type' => 'required|in:DNI,RUC,CE,Passport',
            'document_number' => 'required|string|max:20', // Removed unique rule for upsert logic

            // Datos personales
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            
            // Optional: Customers might have birthdays for marketing
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,Other',

            'photo_url' => 'nullable|string',
        ]);

        // Upsert Logic
        $partner = Partner::where('document_type', $validated['document_type'])
            ->where('document_number', $validated['document_number'])
            ->first();

        if ($partner) {
            $partner->fill($validated);
            $partner->is_customer = true;
            $partner->save();
            $message = 'Cliente actualizado exitosamente (Partner existente)';
        } else {
            $validated['is_customer'] = true;
            $validated['is_member'] = false; // Default
            $validated['status'] = 'active';
            Partner::create($validated);
            $message = 'Cliente registrado exitosamente';
        }

        return redirect()->route('customers.index')
            ->with('success', $message);
    }

    /**
     * Show the form for editing the specified customer
     */
    public function edit(Partner $customer)
    {
        if (! $customer->is_customer) {
            abort(404);
        }

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, Partner $customer)
    {
        if (! $customer->is_customer) {
            abort(404);
        }

        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',

            // Documentos
            'document_type' => 'required|in:DNI,RUC,CE,Passport',
            'document_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('partners', 'document_number')
                    ->ignore($customer->id)
                    ->where(fn ($q) => $q->where('document_type', $request->input('document_type'))),
            ],

            // Datos personales
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',

            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,Other',
            
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')
            ->with('success', 'Cliente actualizado exitosamente');
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Partner $customer)
    {
        if (! $customer->is_customer) {
            abort(404);
        }

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Cliente eliminado exitosamente');
    }
}
