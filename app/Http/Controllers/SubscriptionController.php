<?php

namespace App\Http\Controllers;

use App\Models\MembershipFreeze;
use App\Models\MembershipSubscription;
use App\Models\MembershipPlan;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,frozen,expired,cancelled',
            'plan_id' => 'nullable|integer|exists:membership_plans,id',
        ]);

        $query = MembershipSubscription::query()
            ->with([
                'partner:id,first_name,last_name,business_name,document_type,document_number,email,phone',
                'plan:id,name,duration_days,price,allows_freezing,max_freeze_days',
                'freezes' => fn($q) => $q->latest('id'),
            ])
            ->latest('id');

        $selectedCompanyIds = session('selected_company_ids', []);
        if (! empty($selectedCompanyIds)) {
            $query->whereIn('company_id', $selectedCompanyIds);
        }

        if (! empty($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['plan_id'] ?? null)) {
            $query->where('membership_plan_id', $filters['plan_id']);
        }

        if (! empty($filters['search'] ?? null)) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('partner', function ($partnerQuery) use ($search) {
                    $partnerQuery
                        ->where('business_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                })->orWhereHas('plan', function ($planQuery) use ($search) {
                    $planQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        $plansQuery = MembershipPlan::query()
            ->select(['id', 'name'])
            ->orderBy('name');

        if (! empty($selectedCompanyIds)) {
            $plansQuery->whereIn('company_id', $selectedCompanyIds);
        }

        return Inertia::render('Subscriptions/Index', [
            'subscriptions' => $query->paginate(20)->withQueryString()->through(function (MembershipSubscription $subscription) {
                return [
                    'id' => $subscription->id,
                    'company_id' => $subscription->company_id,
                    'status' => $subscription->status,
                    'start_date' => $subscription->start_date?->toDateString(),
                    'end_date' => $subscription->end_date?->toDateString(),
                    'original_end_date' => $subscription->original_end_date?->toDateString(),
                    'amount_paid' => (float) $subscription->amount_paid,
                    'payment_method' => $subscription->payment_method,
                    'payment_reference' => $subscription->payment_reference,
                    'entries_used' => (int) $subscription->entries_used,
                    'entries_this_month' => (int) $subscription->entries_this_month,
                    'total_days_frozen' => (int) $subscription->total_days_frozen,
                    'remaining_freeze_days' => (int) $subscription->remaining_freeze_days,
                    'partner' => $subscription->partner ? [
                        'id' => $subscription->partner->id,
                        'display_name' => $subscription->partner->display_name,
                        'document_type' => $subscription->partner->document_type,
                        'document_number' => $subscription->partner->document_number,
                        'email' => $subscription->partner->email,
                        'phone' => $subscription->partner->phone,
                    ] : null,
                    'plan' => $subscription->plan ? [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'duration_days' => (int) $subscription->plan->duration_days,
                        'price' => (float) $subscription->plan->price,
                        'allows_freezing' => (bool) $subscription->plan->allows_freezing,
                        'max_freeze_days' => (int) $subscription->plan->max_freeze_days,
                    ] : null,
                    'freezes' => $subscription->freezes->map(function (MembershipFreeze $freeze) {
                        return [
                            'id' => $freeze->id,
                            'status' => $freeze->status,
                            'freeze_start_date' => $freeze->freeze_start_date?->toDateString(),
                            'freeze_end_date' => $freeze->freeze_end_date?->toDateString(),
                            'days_frozen' => (int) $freeze->days_frozen,
                            'planned_days' => (int) $freeze->planned_days,
                            'reason' => $freeze->reason,
                            'requested_by' => $freeze->requested_by,
                            'approved_by' => $freeze->approved_by,
                        ];
                    })->values(),
                ];
            }),
            'plans' => $plansQuery->get(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'plan_id' => $filters['plan_id'] ?? null,
            ],
        ]);
    }

    public function show(Request $request, MembershipSubscription $subscription)
    {
        $selectedCompanyIds = session('selected_company_ids', []);
        if (! empty($selectedCompanyIds) && ! in_array($subscription->company_id, $selectedCompanyIds)) {
            abort(404);
        }

        $subscription->loadMissing([
            'partner:id,first_name,last_name,business_name,document_type,document_number,email,phone',
            'plan:id,name,duration_days,price,allows_freezing,max_freeze_days',
            'freezes' => fn($q) => $q->latest('id'),
            'freezes.requestedBy:id,name,email',
            'freezes.approvedBy:id,name,email',
        ]);

        $returnTo = $request->query('return_to');
        $returnTo = is_string($returnTo) && str_starts_with($returnTo, '/subscriptions')
            ? $returnTo
            : '/subscriptions';

        $activeFreeze = $subscription->freezes->firstWhere('status', 'active');

        return Inertia::render('Subscriptions/Show', [
            'subscription' => [
                'id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'original_end_date' => $subscription->original_end_date?->toDateString(),
                'amount_paid' => (float) $subscription->amount_paid,
                'payment_method' => $subscription->payment_method,
                'payment_reference' => $subscription->payment_reference,
                'entries_used' => (int) $subscription->entries_used,
                'entries_this_month' => (int) $subscription->entries_this_month,
                'total_days_frozen' => (int) $subscription->total_days_frozen,
                'remaining_freeze_days' => (int) $subscription->remaining_freeze_days,
                'partner' => $subscription->partner ? [
                    'id' => $subscription->partner->id,
                    'display_name' => $subscription->partner->display_name,
                    'document_type' => $subscription->partner->document_type,
                    'document_number' => $subscription->partner->document_number,
                    'email' => $subscription->partner->email,
                    'phone' => $subscription->partner->phone,
                ] : null,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'duration_days' => (int) $subscription->plan->duration_days,
                    'price' => (float) $subscription->plan->price,
                    'allows_freezing' => (bool) $subscription->plan->allows_freezing,
                    'max_freeze_days' => (int) $subscription->plan->max_freeze_days,
                ] : null,
                'active_freeze' => $activeFreeze ? [
                    'id' => $activeFreeze->id,
                    'status' => $activeFreeze->status,
                    'freeze_start_date' => $activeFreeze->freeze_start_date?->toDateString(),
                    'freeze_end_date' => $activeFreeze->freeze_end_date?->toDateString(),
                    'days_frozen' => (int) $activeFreeze->days_frozen,
                    'planned_days' => (int) $activeFreeze->planned_days,
                    'reason' => $activeFreeze->reason,
                    'requested_by' => $activeFreeze->requestedBy ? [
                        'id' => $activeFreeze->requestedBy->id,
                        'name' => $activeFreeze->requestedBy->name,
                        'email' => $activeFreeze->requestedBy->email,
                    ] : null,
                    'approved_by' => $activeFreeze->approvedBy ? [
                        'id' => $activeFreeze->approvedBy->id,
                        'name' => $activeFreeze->approvedBy->name,
                        'email' => $activeFreeze->approvedBy->email,
                    ] : null,
                ] : null,
                'freezes' => $subscription->freezes->map(function (MembershipFreeze $freeze) {
                    return [
                        'id' => $freeze->id,
                        'status' => $freeze->status,
                        'freeze_start_date' => $freeze->freeze_start_date?->toDateString(),
                        'freeze_end_date' => $freeze->freeze_end_date?->toDateString(),
                        'days_frozen' => (int) $freeze->days_frozen,
                        'planned_days' => (int) $freeze->planned_days,
                        'reason' => $freeze->reason,
                        'requested_by' => $freeze->requestedBy ? [
                            'id' => $freeze->requestedBy->id,
                            'name' => $freeze->requestedBy->name,
                            'email' => $freeze->requestedBy->email,
                        ] : null,
                        'approved_by' => $freeze->approvedBy ? [
                            'id' => $freeze->approvedBy->id,
                            'name' => $freeze->approvedBy->name,
                            'email' => $freeze->approvedBy->email,
                        ] : null,
                    ];
                })->values(),
            ],
            'returnTo' => $returnTo,
        ]);
    }

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

        // ... existing subscription creation ...

        $partner = Partner::findOrFail($validated['partner_id']);
        $plan = MembershipPlan::findOrFail($validated['membership_plan_id']);

        // Use provided start date or default to today
        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : Carbon::now();

        $endDate = $startDate->copy()->addDays($plan->duration_days);

        // Transaction to ensure both subscription and sale are created
        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $partner, $plan, $startDate, $endDate) {
            // 1. Create subscription
            $subscription = MembershipSubscription::create([
                'partner_id' => $partner->id,
                'membership_plan_id' => $plan->id,
                'company_id' => $partner->company_id ?? \App\Models\Company::first()->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'original_end_date' => $endDate,
                'amount_paid' => $validated['amount_paid'],
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'sold_by' => Auth::id(),
                'status' => 'active',
                'remaining_freeze_days' => $plan->allows_freezing ? $plan->max_freeze_days : 0,
            ]);

            // 2. Create Sale (Factura/Boleta interna)

            // Determine company (fallback to first if partner has none)
            $companyId = $partner->company_id ?? \App\Models\Company::first()->id;

            // Find default sales journal
            $journal = \App\Models\Journal::where('type', 'sale')
                ->where('company_id', $companyId)
                ->first();

            // Find default warehouse (first one)
            $warehouse = \App\Models\Warehouse::where('company_id', $companyId)->first()
                ?? \App\Models\Warehouse::first();

            $sale = null;

            if ($journal && $warehouse) {
                // Generate sequence
                $sequence = \App\Services\SequenceService::getNextParts($journal->id);

                // Create Sale Header
                $sale = \App\Models\Sale::create([
                    'partner_id' => $partner->id,
                    'warehouse_id' => $warehouse->id,
                    'journal_id' => $journal->id,
                    'company_id' => $companyId,
                    'user_id' => Auth::id(),
                    'notes' => 'Suscripción generada automáticamente: ' . $plan->name,
                    'status' => 'posted', // Directly posted
                    'payment_status' => 'paid', // Assumed paid as it's a subscription entry
                    'serie' => $sequence['serie'],
                    'correlative' => $sequence['correlative'],
                    'subtotal' => $validated['amount_paid'], // Asumiendo precio incluye IGV o es total
                    'tax_amount' => 0, // Simplificación: Todo al subtotal por ahora si no hay desglose
                    'total' => $validated['amount_paid'],
                ]);

                // Create Sale Line
                // TODO: Calcular desglose de impuestos si es necesario
                $sale->products()->create([
                    'product_product_id' => $plan->product_product_id,
                    'quantity' => 1,
                    'price' => $validated['amount_paid'],
                    'subtotal' => $validated['amount_paid'],
                    'tax_id' => null,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total' => $validated['amount_paid'],
                ]);

                // NOTE: No deduct stock via Kardex because plans have tracks_inventory = false
            }

            activity()
                ->performedOn($subscription)
                ->causedBy(Auth::user())
                ->withProperties([
                    'plan_name' => $plan->name,
                    'partner_name' => $partner->full_name,
                    'amount_paid' => $validated['amount_paid'],
                    'payment_method' => $validated['payment_method'],
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'generated_sale' => $sale ? ($sale->serie . '-' . $sale->correlative) : 'N/A',
                ])
                ->log('Suscripción creada');
        });

        return redirect()->back()
            ->with('success', 'Suscripción creada exitosamente.');
    }

    /**
     * Freeze a subscription.
     */
    public function freeze(Request $request, MembershipSubscription $subscription)
    {
        $validated = $request->validate([
            'freeze_start_date' => 'nullable|date|after_or_equal:today',
            'freeze_end_date' => 'nullable|date|after:freeze_start_date',
            'days' => 'nullable|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        if (empty($validated['days']) && empty($validated['freeze_end_date'])) {
            return redirect()->back()
                ->with('error', 'Debes indicar días o un rango de fechas para el congelamiento.');
        }

        if (! empty($validated['freeze_start_date']) && empty($validated['freeze_end_date'])) {
            return redirect()->back()
                ->with('error', 'Si indicas la fecha de inicio, también debes indicar la fecha de fin.');
        }

        // Validations
        if (! in_array($subscription->status, ['active'])) {
            return redirect()->back()
                ->with('error', 'Solo se pueden congelar suscripciones activas.');
        }

        if (! $subscription->plan->allows_freezing) {
            return redirect()->back()
                ->with('error', 'Este plan no permite congelamiento.');
        }

        $originalEndDate = $subscription->end_date->copy();
        $freezeStartDate = Carbon::parse($validated['freeze_start_date'] ?? now()->toDateString());
        $freezeEndDate = ! empty($validated['freeze_end_date'])
            ? Carbon::parse($validated['freeze_end_date'])
            : $freezeStartDate->copy()->addDays((int) $validated['days']);

        try {
            $freeze = $subscription->freezeWithDates(
                $freezeStartDate,
                $freezeEndDate,
                $validated['reason'] ?? 'Congelamiento solicitado',
                Auth::id(),
            );
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $newEndDate = $subscription->end_date->copy();

        activity()
            ->performedOn($subscription)
            ->causedBy(Auth::user())
            ->withProperties([
                'freeze_id' => $freeze->id,
                'freeze_start_date' => $freezeStartDate->toDateString(),
                'freeze_end_date' => $freezeEndDate->toDateString(),
                'days_planned' => $freeze->planned_days,
                'original_end_date' => $originalEndDate->format('Y-m-d'),
                'new_end_date' => $newEndDate->format('Y-m-d'),
                'reason' => $validated['reason'] ?? 'Congelamiento solicitado',
            ])
            ->log('Suscripción congelada');

        return redirect()->back()
            ->with('success', "Suscripción congelada del {$freezeStartDate->format('d/m/Y')} al {$freezeEndDate->format('d/m/Y')}. Nueva fecha de fin: {$newEndDate->format('d/m/Y')}");
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
            ->whereDate('freeze_start_date', '<=', now()->toDateString())
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
        $newEndDate = $subscription->end_date->copy();
        $unusedDaysReturned = 0;

        // Si regresó antes de lo planeado, ajustar fecha y devolver días
        if ($actualDaysFrozen < $plannedDays) {
            $unusedDays = $plannedDays - $actualDaysFrozen;
            $unusedDaysReturned = $unusedDays;

            // Ajustar fecha de fin (quitar los días que no usó)
            $newEndDate = $subscription->end_date->copy()->subDays($unusedDays);

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
        $subscription->update([
            'status' => 'active',
            'end_date' => $newEndDate,
            'remaining_freeze_days' => $subscription->remaining_freeze_days,
        ]);
        $subscription->increment('total_days_frozen', $actualDaysFrozen);

        activity()
            ->performedOn($subscription)
            ->causedBy(Auth::user())
            ->withProperties([
                'actual_days_frozen' => $actualDaysFrozen,
                'planned_days' => $plannedDays,
                'adjusted' => $adjustmentMade,
                'unused_days_returned' => $unusedDaysReturned,
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
            ->causedBy(Auth::user())
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
