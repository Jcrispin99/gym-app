<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Partner;
use App\Models\MembershipSubscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendances
     */
    public function index(Request $request)
    {
        $query = Attendance::with(['partner', 'subscription.plan', 'company', 'registeredBy'])
            ->latest('check_in_time');

        // Filters
        $appliedDate = $request->filled('date') ? $request->date : now()->toDateString();
        $query->whereDate('check_in_time', $appliedDate);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->whereHas('partner', function ($q) use ($request) {
                $q->where('business_name', 'like', "%{$request->search}%")
                    ->orWhere('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('document_number', 'like', "%{$request->search}%");
            });
        }

        $attendances = $query->paginate(20);

        // Stats
        $stats = [
            'today_total' => Attendance::today()->valid()->count(),
            'active_now' => Attendance::whereNull('check_out_time')->count(),
            'today_denied' => Attendance::today()->denied()->count(),
        ];

        return Inertia::render('Attendances/Index', [
            'attendances' => $attendances,
            'stats' => $stats,
            'filters' => array_merge(
                ['date' => $appliedDate],
                $request->only(['status', 'search'])
            ),
        ]);
    }

    /**
     * Show check-in page
     */
    public function checkIn()
    {
        return Inertia::render('Attendances/CheckIn');
    }

    /**
     * AJAX endpoint to lookup partner by DNI
     */
    public function lookupByDni(Request $request)
    {
        $request->validate([
            'dni' => 'required|string',
        ]);

        $partner = Partner::with(['activeSubscription.plan', 'company'])
            ->where('document_type', 'DNI')
            ->where('document_number', $request->dni)
            ->first();

        if (! $partner) {
            return response()->json([
                'found' => false,
                'message' => 'No se encontró ningún miembro con ese DNI',
            ]);
        }

        // Validate if can check in
        $validation = $this->validateCheckIn($partner);

        return response()->json([
            'found' => true,
            'partner' => $partner,
            'validation' => $validation,
        ]);
    }

    /**
     * Store check-in
     */
    public function storeCheckIn(Request $request)
    {
        $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'force' => 'boolean',
        ]);

        $partner = Partner::with('activeSubscription.plan')->findOrFail($request->partner_id);

        $validation = $this->validateCheckIn($partner);

        // If not allowed and not forcing, return error
        if (! $validation['allowed'] && ! $request->force) {
            return redirect()->back()
                ->with('error', $validation['message']);
        }

        // Create attendance
        $subscription = isset($validation['subscription']) ? $validation['subscription'] : null;
        $subscriptionId = $subscription ? $subscription->getKey() : null;
        
        $companyId = $partner->company_id 
            ?? $subscription?->company_id 
            ?? Auth::user()->company_id;

        $attendance = Attendance::create([
            'partner_id' => $partner->id,
            'membership_subscription_id' => $subscriptionId,
            'company_id' => $companyId,
            'check_in_time' => Carbon::now(),
            'status' => $request->force ? 'manual_override' : 'valid',
            'validation_message' => $request->force ? 'Acceso manual aprobado por staff' : $validation['message'],
            'is_manual_entry' => $request->force ?? false,
            'registered_by' => Auth::id(),
        ]);

        // Update subscription counters if valid
        if ($validation['allowed'] && $subscription) {
            $subscription->recordEntry();
        }

        return redirect()->back()
            ->with('success', 'Check-in registrado exitosamente');
    }

    /**
     * Manual check-out
     */
    public function checkOut(Attendance $attendance)
    {
        if ($attendance->check_out_time) {
            return redirect()->back()
                ->with('error', 'Esta asistencia ya tiene check-out registrado');
        }

        $attendance->checkOut();

        return redirect()->back()
            ->with('success', 'Check-out registrado exitosamente');
    }

    /**
     * Validate if partner can check in
     */
    protected function validateCheckIn(Partner $partner): array
    {
        $subscription = $partner->activeSubscription;

        // 1. Has active subscription?
        if (! $subscription) {
            return [
                'allowed' => false,
                'message' => 'No tiene suscripción activa',
                'reason' => 'no_subscription',
            ];
        }

        // 2. Is frozen?
        if ($subscription->status === 'frozen') {
            return [
                'allowed' => false,
                'message' => 'Suscripción congelada',
                'reason' => 'frozen',
                'subscription' => $subscription,
            ];
        }

        // 3. Is expired?
        if ($subscription->isExpired()) {
            return [
                'allowed' => false,
                'message' => 'Suscripción vencida el ' . $subscription->end_date->format('d/m/Y'),
                'reason' => 'expired',
                'subscription' => $subscription,
            ];
        }

        // 4. Daily limit?
        $plan = $subscription->plan;
        if ($plan->max_entries_per_day) {
            $entriesToday = $subscription->attendances()
                ->valid()
                ->whereDate('check_in_time', Carbon::today())
                ->count();

            if ($entriesToday >= $plan->max_entries_per_day) {
                return [
                    'allowed' => false,
                    'message' => "Límite diario alcanzado ({$entriesToday}/{$plan->max_entries_per_day})",
                    'reason' => 'daily_limit',
                    'subscription' => $subscription,
                ];
            }
        }

        // 5. Monthly limit?
        if ($plan->max_entries_per_month) {
            $subscription->resetMonthlyCounterIfNeeded();

            if ($subscription->entries_this_month >= $plan->max_entries_per_month) {
                return [
                    'allowed' => false,
                    'message' => "Límite mensual alcanzado ({$subscription->entries_this_month}/{$plan->max_entries_per_month})",
                    'reason' => 'monthly_limit',
                    'subscription' => $subscription,
                ];
            }
        }

        // 6. Time restriction?
        if ($plan->time_restricted && $plan->allowed_time_start && $plan->allowed_time_end) {
            $now = Carbon::now()->format('H:i');
            $start = Carbon::parse($plan->allowed_time_start)->format('H:i');
            $end = Carbon::parse($plan->allowed_time_end)->format('H:i');

            if ($now < $start || $now > $end) {
                return [
                    'allowed' => false,
                    'message' => "Fuera del horario permitido ({$start} - {$end})",
                    'reason' => 'time_restriction',
                    'subscription' => $subscription,
                ];
            }
        }

        // 7. Day restriction?
        if ($plan->allowed_days && count($plan->allowed_days) > 0) {
            $today = strtolower(Carbon::now()->locale('es')->dayName);
            $allowedDays = array_map('strtolower', $plan->allowed_days);

            if (! in_array($today, $allowedDays)) {
                return [
                    'allowed' => false,
                    'message' => 'Hoy no está permitido según el plan',
                    'reason' => 'day_restriction',
                    'subscription' => $subscription,
                ];
            }
        }

        // All validations passed!
        return [
            'allowed' => true,
            'message' => '✅ Acceso permitido',
            'subscription' => $subscription,
        ];
    }
}
