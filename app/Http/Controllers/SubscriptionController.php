<?php

namespace App\Http\Controllers;

use App\Models\MembershipSubscription;
use App\Models\MembershipPlan;
use App\Models\Partner;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Store a new subscription for a member.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'membership_plan_id' => 'required|exists:membership_plans,id',
            'start_date' => 'nullable|date|after_or_equal:today',
            'payment_method' => 'required|in:efectivo,tarjeta,transferencia,yape,plin',
            'payment_reference' => 'nullable|string|max:100',
            'amount_paid' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $partner = Partner::findOrFail($validated['partner_id']);
        $plan = MembershipPlan::findOrFail($validated['membership_plan_id']);

        // Use provided start date or default to today
        $startDate = isset($validated['start_date']) 
            ? Carbon::parse($validated['start_date']) 
            : Carbon::now();
        
        $endDate = $startDate->copy()->addDays($plan->duration_days);

        // Create subscription
        $subscription = MembershipSubscription::create([
            'partner_id' => $partner->id,
            'membership_plan_id' => $plan->id,
            'company_id' => $partner->company_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'original_end_date' => $endDate,
            'amount_paid' => $validated['amount_paid'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'sold_by' => auth()->id(),
            'status' => 'active',
            'remaining_freeze_days' => $plan->allows_freezing ? $plan->max_freeze_days : 0,
        ]);

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->withProperties([
                'plan_name' => $plan->name,
                'partner_name' => $partner->full_name,
                'amount_paid' => $validated['amount_paid'],
                'payment_method' => $validated['payment_method'],
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ])
            ->log('Suscripción creada');

        return redirect()->back()
            ->with('success', 'Suscripción creada exitosamente.');
    }

    /**
     * Freeze a subscription.
     */
    public function freeze(Request $request, MembershipSubscription $subscription)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        // Validations
        if (! in_array($subscription->status, ['active'])) {
            return redirect()->back()
                ->with('error', 'Solo se pueden congelar suscripciones activas.');
        }

        if (! $subscription->plan->allows_freezing) {
            return redirect()->back()
                ->with('error', 'Este plan no permite congelamiento.');
        }

        if ($validated['days'] > $subscription->remaining_freeze_days) {
            return redirect()->back()
                ->with('error', "Solo quedan {$subscription->remaining_freeze_days} días de congelamiento disponibles.");
        }

        // ENFOQUE C: Extensión inmediata de la fecha
        $newEndDate = $subscription->end_date->copy()->addDays($validated['days']);

        // Create freeze record with planned_days
        $freeze = MembershipFreeze::create([
            'membership_subscription_id' => $subscription->id,
            'freeze_start_date' => Carbon::now(),
            'freeze_end_date' => Carbon::now()->addDays($validated['days']),
            'days_frozen' => $validated['days'], // Inicial, se ajustará al descongelar
            'planned_days' => $validated['days'], // Guardamos lo planeado
            'reason' => $validated['reason'] ?? 'Congelamiento solicitado',
            'requested_by' => auth()->id(),
            'approved_by' => auth()->id(),
            'status' => 'active',
        ]);

        // Update subscription - EXTIENDE FECHA INMEDIATAMENTE
        $subscription->update([
            'end_date' => $newEndDate,
            'status' => 'frozen',
            'remaining_freeze_days' => $subscription->remaining_freeze_days - $validated['days'],
        ]);

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->withProperties([
                'days_planned' => $validated['days'],
                'original_end_date' => $subscription->end_date->subDays($validated['days'])->format('Y-m-d'),
                'new_end_date' => $newEndDate->format('Y-m-d'),
                'reason' => $validated['reason'] ?? 'Congelamiento solicitado',
            ])
            ->log('Suscripción congelada');

        return redirect()->back()
            ->with('success', "Suscripción congelada por {$validated['days']} días. Nueva fecha de fin: {$newEndDate->format('d/m/Y')}");
    }

    /**
     * Unfreeze a subscription.
     */
    public function unfreeze(MembershipSubscription $subscription)
    {
        if ($subscription->status !== 'frozen') {
            return redirect()->back()
                ->with('error', 'Esta suscripción no está congelada.');
        }

        // Find active freeze
        $activeFreeze = $subscription->freezes()
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $activeFreeze) {
            return redirect()->back()
                ->with('error', 'No se encontró congelamiento activo.');
        }

        // ENFOQUE C: Calcular días reales y ajustar si regresó antes
        $actualDaysFrozen = Carbon::now()->diffInDays($activeFreeze->freeze_start_date);
        $plannedDays = $activeFreeze->planned_days;

        $message = 'Suscripción reactivada exitosamente.';
        $adjustmentMade = false;

        // Si regresó antes de lo planeado, ajustar fecha y devolver días
        if ($actualDaysFrozen < $plannedDays) {
            $unusedDays = $plannedDays - $actualDaysFrozen;

            // Ajustar fecha de fin (quitar los días que no usó)
            $newEndDate = $subscription->end_date->copy()->subDays($unusedDays);
            $subscription->end_date = $newEndDate;

            // Devolver días no usados
            $subscription->remaining_freeze_days += $unusedDays;

            $adjustmentMade = true;
            $message = "Suscripción reactivada. Regresó {$unusedDays} día(s) antes. Nueva fecha de fin: {$newEndDate->format('d/m/Y')}. Días de congelamiento devueltos: {$unusedDays}";
        }

        // Update freeze record con días reales
        $activeFreeze->update([
            'freeze_end_date' => Carbon::now(),
            'days_frozen' => $actualDaysFrozen, // Días realmente congelados
            'status' => 'completed',
        ]);

        // Update subscription status
        $subscription->update(['status' => 'active']);
        $subscription->increment('total_days_frozen', $actualDaysFrozen);

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->withProperties([
                'actual_days_frozen' => $actualDaysFrozen,
                'planned_days' => $plannedDays,
                'adjusted' => $adjustmentMade,
                'unused_days_returned' => $adjustmentMade ? ($plannedDays - $actualDaysFrozen) : 0,
                'new_end_date' => $subscription->end_date->format('Y-m-d'),
            ])
            ->log('Suscripción descongelada');

        return redirect()->back()
            ->with('success', $message);
    }

    /**
     * Cancel/delete a subscription.
     */
    public function destroy(MembershipSubscription $subscription)
    {
        // Only allow cancelling active or frozen subscriptions
        if (! in_array($subscription->status, ['active', 'frozen'])) {
            return redirect()->back()
                ->with('error', 'Solo se pueden cancelar suscripciones activas o congeladas.');
        }

        activity()
            ->performedOn($subscription)
            ->causedBy(auth()->user())
            ->withProperties([
                'previous_status' => $subscription->status,
                'days_remaining' => $subscription->getDaysRemaining(),
            ])
            ->log('Suscripción cancelada');

        $subscription->update(['status' => 'cancelled']);

        return redirect()->back()
            ->with('success', 'Suscripción cancelada exitosamente.');
    }
}
