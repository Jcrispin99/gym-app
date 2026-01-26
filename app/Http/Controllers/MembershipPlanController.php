<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;

class MembershipPlanController extends Controller
{
    /**
     * Display a listing of membership plans.
     */
    public function index()
    {
        $plans = MembershipPlan::with('company')
            ->orderBy('price', 'asc')
            ->get();

        return Inertia::render('MembershipPlans/Index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Show the form for creating a new membership plan.
     */
    public function create()
    {
        $companies = \App\Models\Company::active()->get();

        return Inertia::render('MembershipPlans/Create', [
            'companies' => $companies,
        ]);
    }

    /**
     * Store a newly created membership plan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'max_entries_per_month' => 'nullable|integer|min:1',
            'max_entries_per_day' => 'required|integer|min:1',
            'time_restricted' => 'boolean',
            'allowed_time_start' => 'nullable|date_format:H:i',
            'allowed_time_end' => 'nullable|date_format:H:i',
            'allowed_days' => 'nullable|array',
            'allowed_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'allows_freezing' => 'boolean',
            'max_freeze_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $plan = MembershipPlan::create($validated);

        // --- Crear Producto Asociado (Oculto) ---
        // 1. Obtener categoría Suscripciones
        $category = \App\Models\Category::firstOrCreate(
            ['slug' => Str::slug('Suscripciones')],
            [
                'name' => 'Suscripciones',
                'full_name' => 'Suscripciones',
                'description' => 'Productos internos para planes y suscripciones.',
                'is_active' => false,
            ]
        );

        // 2. Crear Product Template (Oculto)
        $template = \App\Models\ProductTemplate::create([
            'name' => "Plan: {$plan->name}",
            'description' => $plan->description,
            'price' => $plan->price,
            'category_id' => $category->id,
            'is_active' => true,
            'is_pos_visible' => false, // Oculto en POS
            'tracks_inventory' => false,
        ]);

        // 3. Crear Variante
        $variant = \App\Models\ProductProduct::create([
            'product_template_id' => $template->id,
            'sku' => null,
            'barcode' => null,
            'price' => $plan->price,
            'cost_price' => 0,
            'is_principal' => true,
        ]);

        // 4. Vincular al Plan
        $plan->update([
            'product_product_id' => $variant->id,
        ]);
        // ----------------------------------------

        activity()
            ->performedOn($plan)
            ->log('Plan de membresía creado');

        return redirect()->route('membership-plans.index')
            ->with('success', 'Plan de membresía creado exitosamente.');
    }

    /**
     * Show the form for editing the specified membership plan.
     */
    public function edit(MembershipPlan $membershipPlan)
    {
        $companies = \App\Models\Company::active()->get();

        return Inertia::render('MembershipPlans/Edit', [
            'plan' => $membershipPlan,
            'companies' => $companies,
        ]);
    }

    /**
     * Update the specified membership plan.
     */
    public function update(Request $request, MembershipPlan $membershipPlan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'max_entries_per_month' => 'nullable|integer|min:1',
            'max_entries_per_day' => 'required|integer|min:1',
            'time_restricted' => 'boolean',
            'allowed_time_start' => 'nullable|date_format:H:i',
            'allowed_time_end' => 'nullable|date_format:H:i',
            'allowed_days' => 'nullable|array',
            'allowed_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'allows_freezing' => 'boolean',
            'max_freeze_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $membershipPlan->update($validated);

        activity()
            ->performedOn($membershipPlan)
            ->log('Plan de membresía actualizado');

        return redirect()->route('membership-plans.index')
            ->with('success', 'Plan de membresía actualizado exitosamente.');
    }

    /**
     * Remove the specified membership plan.
     */
    public function destroy(MembershipPlan $membershipPlan)
    {
        // Check if plan has active subscriptions
        $activeSubscriptionsCount = $membershipPlan->activeSubscriptions()->count();

        if ($activeSubscriptionsCount > 0) {
            return redirect()->route('membership-plans.index')
                ->with('error', 'No se puede eliminar el plan porque tiene '.$activeSubscriptionsCount.' suscripciones activas.');
        }

        activity()
            ->performedOn($membershipPlan)
            ->log('Plan de membresía eliminado');

        $membershipPlan->delete();

        return redirect()->route('membership-plans.index')
            ->with('success', 'Plan de membresía eliminado exitosamente.');
    }

    /**
     * Toggle the active status of a membership plan.
     */
    public function toggleStatus(MembershipPlan $membershipPlan)
    {
        $membershipPlan->update([
            'is_active' => ! $membershipPlan->is_active,
        ]);

        $status = $membershipPlan->is_active ? 'activado' : 'desactivado';

        activity()
            ->performedOn($membershipPlan)
            ->log("Plan de membresía {$status}");

        return redirect()->route('membership-plans.index')
            ->with('success', "Plan de membresía {$status} exitosamente.");
    }

    /**
     * Get activity log for a membership plan.
     */
    public function activityLog(MembershipPlan $membershipPlan)
    {
        $activities = Activity::forSubject($membershipPlan)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json($activities);
    }
}
