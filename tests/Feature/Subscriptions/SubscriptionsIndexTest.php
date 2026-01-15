<?php

use App\Models\Company;
use App\Models\MembershipFreeze;
use App\Models\MembershipPlan;
use App\Models\MembershipSubscription;
use App\Models\Partner;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Hash;
use function Pest\Laravel\actingAs;

test('staff can view subscriptions index', function () {
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $company = Company::create([
        'business_name' => 'Kraken Gym SAC',
        'trade_name' => 'Kraken Gym',
        'ruc' => '20123456789',
        'active' => true,
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'is_member' => true,
        'is_customer' => true,
        'is_supplier' => false,
        'document_type' => 'DNI',
        'document_number' => '12345678',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'status' => 'active',
    ]);

    $plan = MembershipPlan::create([
        'company_id' => $company->id,
        'name' => 'Mensual',
        'description' => 'Plan mensual',
        'duration_days' => 30,
        'price' => 120.00,
        'max_entries_per_month' => null,
        'max_entries_per_day' => 1,
        'time_restricted' => false,
        'allowed_time_start' => null,
        'allowed_time_end' => null,
        'allowed_days' => null,
        'allows_freezing' => true,
        'max_freeze_days' => 10,
        'is_active' => true,
    ]);

    $subscription = MembershipSubscription::create([
        'partner_id' => $partner->id,
        'membership_plan_id' => $plan->id,
        'company_id' => $company->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'original_end_date' => now()->addDays(30)->toDateString(),
        'amount_paid' => 120.00,
        'payment_method' => 'efectivo',
        'payment_reference' => null,
        'entries_used' => 0,
        'entries_this_month' => 0,
        'current_month_start' => now()->startOfMonth()->toDateString(),
        'total_days_frozen' => 0,
        'remaining_freeze_days' => 10,
        'status' => 'active',
        'sold_by' => $user->id,
    ]);

    MembershipFreeze::create([
        'membership_subscription_id' => $subscription->id,
        'freeze_start_date' => now()->subDays(2)->toDateString(),
        'freeze_end_date' => now()->addDays(3)->toDateString(),
        'days_frozen' => 2,
        'planned_days' => 5,
        'reason' => 'Viaje',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
        'status' => 'completed',
    ]);

    $response = actingAs($user)->get('/subscriptions');

    $response->assertStatus(200);
    $response->assertInertia(
        fn(Assert $page) => $page
            ->component('Subscriptions/Index')
            ->has('subscriptions.data', 1)
            ->has('plans')
            ->where('subscriptions.data.0.id', $subscription->id)
            ->where('subscriptions.data.0.partner.display_name', $partner->display_name)
            ->where('subscriptions.data.0.plan.name', $plan->name)
            ->has('subscriptions.data.0.freezes', 1)
    );
});

test('non-staff cannot view subscriptions index', function () {
    $user = User::query()->create([
        'name' => 'Cliente',
        'email' => 'cliente@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'customer',
    ]);

    $response = actingAs($user)->get('/subscriptions');

    $response->assertStatus(403);
});

test('staff can view subscription detail', function () {
    $user = User::query()->create([
        'name' => 'Admin 2',
        'email' => 'admin2@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $company = Company::create([
        'business_name' => 'Kraken Gym SAC',
        'trade_name' => 'Kraken Gym',
        'ruc' => '20123456780',
        'active' => true,
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'is_member' => true,
        'is_customer' => true,
        'is_supplier' => false,
        'document_type' => 'DNI',
        'document_number' => '87654321',
        'first_name' => 'Ana',
        'last_name' => 'Torres',
        'status' => 'active',
    ]);

    $plan = MembershipPlan::create([
        'company_id' => $company->id,
        'name' => 'Trimestral',
        'description' => null,
        'duration_days' => 90,
        'price' => 300.00,
        'max_entries_per_month' => null,
        'max_entries_per_day' => 1,
        'time_restricted' => false,
        'allowed_time_start' => null,
        'allowed_time_end' => null,
        'allowed_days' => null,
        'allows_freezing' => true,
        'max_freeze_days' => 15,
        'is_active' => true,
    ]);

    $subscription = MembershipSubscription::create([
        'partner_id' => $partner->id,
        'membership_plan_id' => $plan->id,
        'company_id' => $company->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(90)->toDateString(),
        'original_end_date' => now()->addDays(90)->toDateString(),
        'amount_paid' => 300.00,
        'payment_method' => 'efectivo',
        'payment_reference' => null,
        'entries_used' => 0,
        'entries_this_month' => 0,
        'current_month_start' => now()->startOfMonth()->toDateString(),
        'total_days_frozen' => 0,
        'remaining_freeze_days' => 15,
        'status' => 'active',
        'sold_by' => $user->id,
    ]);

    MembershipFreeze::create([
        'membership_subscription_id' => $subscription->id,
        'freeze_start_date' => now()->toDateString(),
        'freeze_end_date' => now()->addDays(7)->toDateString(),
        'days_frozen' => 7,
        'planned_days' => 7,
        'reason' => 'Viaje',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
        'status' => 'active',
    ]);

    $response = actingAs($user)->get("/subscriptions/{$subscription->id}?return_to=/subscriptions");

    $response->assertStatus(200);
    $response->assertInertia(
        fn(Assert $page) => $page
            ->component('Subscriptions/Show')
            ->where('subscription.id', $subscription->id)
            ->where('subscription.partner.display_name', $partner->display_name)
            ->where('subscription.plan.name', $plan->name)
            ->has('subscription.freezes', 1)
            ->where('returnTo', '/subscriptions')
    );
});
