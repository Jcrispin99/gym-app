<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class CustomerApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'company_id' => 'nullable|integer|exists:companies,id',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Partner::query()
            ->with(['company', 'user'])
            ->customers()
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
                $qBuilder->where('first_name', 'like', '%' . $q . '%')
                    ->orWhere('last_name', 'like', '%' . $q . '%')
                    ->orWhere('business_name', 'like', '%' . $q . '%')
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

    public function show(Partner $customer)
    {
        if (! $customer->is_customer) {
            abort(404);
        }

        $customer->load(['company', 'user']);

        $activities = Activity::forSubject($customer)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'data' => $customer,
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

            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'business_name' => 'nullable|string|max:150',

            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',

            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,Other',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'blood_type' => 'nullable|string|max:20',
            'allergies' => 'nullable|string',
            'medical_notes' => 'nullable|string',

            'photo_url' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $partner = Partner::query()
            ->where('document_type', $validated['document_type'])
            ->where('document_number', $validated['document_number'])
            ->first();

        if ($partner) {
            $partner->fill($validated);
            $partner->is_customer = true;
            $partner->save();

            return response()->json([
                'data' => $partner->fresh()->load(['company', 'user']),
            ]);
        }

        $created = Partner::create(array_merge($validated, [
            'is_customer' => true,
            'is_member' => false,
            'status' => 'active',
        ]));

        return response()->json([
            'data' => $created->load(['company', 'user']),
        ], 201);
    }

    public function update(Request $request, Partner $customer)
    {
        if (! $customer->is_customer) {
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
                    ->ignore($customer->id)
                    ->where(fn ($q) => $q->where('document_type', $request->input('document_type'))),
            ],

            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'business_name' => 'nullable|string|max:150',

            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',

            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,Other',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'blood_type' => 'nullable|string|max:20',
            'allergies' => 'nullable|string',
            'medical_notes' => 'nullable|string',

            'photo_url' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $customer->update($validated);
        $customer->is_customer = true;
        $customer->save();

        return response()->json([
            'data' => $customer->fresh()->load(['company', 'user']),
        ]);
    }

    public function destroy(Partner $customer)
    {
        if (! $customer->is_customer) {
            abort(404);
        }

        $customer->delete();

        return response()->json([
            'ok' => true,
        ]);
    }
}

