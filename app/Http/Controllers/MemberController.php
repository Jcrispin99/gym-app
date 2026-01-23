<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class MemberController extends Controller
{
    /**
     * Display a listing of partners for member/customer/supplier views
     */
    public function index(Request $request)
    {
        $members = Partner::query()
            ->with(['company', 'user'])
            ->latest()
            ->members()
            ->get();

        return Inertia::render('Members/Index', [
            'members' => $members,
        ]);
    }

    /**
     * Show the form for creating a new member
     */
    public function create()
    {
        $companies = Company::orderBy('trade_name')->get();

        return Inertia::render('Members/Create', [
            'companies' => $companies,
        ]);
    }

    /**
     * Store a newly created member or update existing partner
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

            // Dirección
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',

            // Info adicional (específica de members)
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,Other',

            // Emergencia
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:20',

            // Médico
            'blood_type' => 'nullable|string|max:5',
            'medical_notes' => 'nullable|string',
            'allergies' => 'nullable|string',

            // Foto
            'photo_url' => 'nullable|string',
        ]);

        // Upsert Logic
        $partner = Partner::where('document_type', $validated['document_type'])
            ->where('document_number', $validated['document_number'])
            ->first();

        if ($partner) {
            $partner->fill($validated);
            $partner->is_member = true;
            $partner->save();
            $message = 'Miembro actualizado exitosamente (Partner existente)';
        } else {
            $validated['is_member'] = true;
            $validated['status'] = 'active';
            Partner::create($validated);
            $message = 'Miembro registrado exitosamente';
        }

        return redirect()->route('members.index')
            ->with('success', $message);
    }

    /**
     * Display the specified member
     */
    public function show(Partner $member)
    {
        if (! $member->isMember()) {
            abort(404);
        }

        $member->load('company');

        return Inertia::render('Members/Show', [
            'member' => $member,
        ]);
    }

    /**
     * Show the form for editing the specified member
     */
    public function edit(Partner $member)
    {
        if (! $member->is_member) {
            abort(404);
        }

        // Load relationships including subscriptions
        $member->load(['user', 'subscriptions.plan']);

        // Get activity log
        $activities = Activity::forSubject($member)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        // Get available membership plans for subscription creation
        $membershipPlans = \App\Models\MembershipPlan::active()
            ->when($member->company_id, function ($q) use ($member) {
                $q->where('company_id', $member->company_id);
            })
            ->orderBy('price', 'asc')
            ->get();

        return Inertia::render('Members/Edit', [
            'member' => $member,
            'activities' => $activities,
            'membershipPlans' => $membershipPlans,
        ]);
    }

    /**
     * Update the specified member in storage
     */
    public function update(Request $request, Partner $member)
    {
        if (! $member->isMember()) {
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
                    ->ignore($member->id)
                    ->where(fn ($q) => $q->where('document_type', $request->input('document_type'))),
            ],

            // Datos personales
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',

            // Dirección
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',

            // Info adicional
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,Other',

            // Emergencia
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:20',

            // Médico
            'blood_type' => 'nullable|string|max:5',
            'medical_notes' => 'nullable|string',
            'allergies' => 'nullable|string',

            // Estado
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $member->update($validated);

        return back()->with('success', 'Miembro actualizado exitosamente');
    }

    /**
     * Remove the specified member from storage
     */
    public function destroy(Partner $member)
    {
        if (! $member->isMember()) {
            abort(404);
        }

        $member->delete();

        return redirect()->route('members.index')
            ->with('success', 'Miembro eliminado exitosamente');
    }

    /**
     * Activate portal access for member
     */
    public function activatePortal(Request $request, Partner $member)
    {
        if (! $member->isMember()) {
            abort(404);
        }

        // Verificar que no tenga ya un user
        if ($member->hasPortalAccess()) {
            return back()->withErrors(['error' => 'Este miembro ya tiene acceso al portal']);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Crear usuario tipo customer
        $user = \App\Models\User::create([
            'name' => $member->full_name,
            'email' => $member->email,
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'user_type' => 'customer',
            'company_id' => $member->company_id,
        ]);

        // Vincular
        $member->update(['user_id' => $user->id]);

        return back()->with('success', 'Acceso al portal activado exitosamente');
    }
}
