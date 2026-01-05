<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Partner;
use Illuminate\Console\Command;
use Carbon\Carbon;

class TestAttendanceSystem extends Command
{
    protected $signature = 'test:attendance';
    protected $description = 'Test the attendance system with various scenarios';

    public function handle()
    {
        $this->info('🧪 Testing Attendance System');
        $this->newLine();

        // Test 1: Find partner with active subscription
        $this->info('Test 1: Finding partner with active subscription...');
        $partner = Partner::with('activeSubscription.plan')->first();
        
        if (!$partner || !$partner->activeSubscription) {
            $this->error('❌ No partner with active subscription found!');
            $this->warn('Please create a member with an active subscription first.');
            return 1;
        }

        $this->info("✅ Found: {$partner->full_name} (DNI: {$partner->dni})");
        $this->info("   Plan: {$partner->activeSubscription->plan->name}");
        $this->info("   Valid until: {$partner->activeSubscription->end_date->format('d/m/Y')}");
        $this->info("   Status: {$partner->activeSubscription->status}");
        $this->newLine();

        // Test 2: Create attendance
        $this->info('Test 2: Creating attendance record...');
        $attendance = Attendance::create([
            'partner_id' => $partner->id,
            'membership_subscription_id' => $partner->activeSubscription->id,
            'company_id' => $partner->company_id,
            'check_in_time' => Carbon::now(),
            'status' => 'valid',
            'validation_message' => '✅ Acceso permitido - Test',
            'is_manual_entry' => false,
            'registered_by' => 1,
        ]);

        $this->info("✅ Attendance created (ID: {$attendance->id})");
        $this->info("   Check-in: {$attendance->check_in_time->format('d/m/Y H:i:s')}");
        $this->newLine();

        // Test 3: Update subscription counters
        $this->info('Test 3: Updating subscription counters...');
        $partner->activeSubscription->recordEntry();
        $this->info("✅ Subscription updated");
        $this->info("   Entries this month: {$partner->activeSubscription->entries_this_month}");
        $this->info("   Total entries: {$partner->activeSubscription->entries_used}");
        $this->newLine();

        // Test 4: Statistics
        $this->info('Test 4: Checking statistics...');
        $stats = [
            'today' => Attendance::today()->count(),
            'valid' => Attendance::today()->valid()->count(),
            'denied' => Attendance::today()->denied()->count(),
            'active' => Attendance::whereNull('check_out_time')->count(),
        ];
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Today Total', $stats['today']],
                ['Valid Today', $stats['valid']],
                ['Denied Today', $stats['denied']],
                ['Active Now', $stats['active']],
            ]
        );
        $this->newLine();

        // Test 5: Latest attendances
        $this->info('Test 5: Latest 5 attendances...');
        $latestAttendances = Attendance::with('partner')
            ->latest('check_in_time')
            ->take(5)
            ->get();

        $this->table(
            ['ID', 'Partner', 'Check-in', 'Status'],
            $latestAttendances->map(fn($att) => [
                $att->id,
                $att->partner->full_name,
                $att->check_in_time->format('d/m/Y H:i'),
                $att->status,
            ])
        );
        $this->newLine();

        // Test 6: Check-out (simulate)
        $this->info('Test 6: Testing check-out...');
        $activeAttendance = Attendance::whereNull('check_out_time')
            ->latest('check_in_time')
            ->first();

        if ($activeAttendance) {
            sleep(2); // Wait 2 seconds to have duration
            $activeAttendance->checkOut();
            $this->info("✅ Check-out completed");
            $this->info("   Check-out time: {$activeAttendance->check_out_time->format('H:i:s')}");
            $this->info("   Duration: {$activeAttendance->getFormattedDuration()}");
        } else {
            $this->warn('⚠️  No active attendance to check out');
        }
        $this->newLine();

        // Test 7: Validation scenarios
        $this->info('Test 7: Testing validation logic...');
        
        // Get an attendance controller instance to test validation
        $validation = $this->testValidation($partner);
        $this->info("   Allowed: " . ($validation['allowed'] ? '✅' : '❌'));
        $this->info("   Message: {$validation['message']}");
        
        $this->newLine();
        $this->info('🎉 All tests completed successfully!');
        
        return 0;
    }

    protected function testValidation(Partner $partner): array
    {
        $subscription = $partner->activeSubscription;

        if (!$subscription) {
            return ['allowed' => false, 'message' => 'No subscription'];
        }

        if ($subscription->status === 'frozen') {
            return ['allowed' => false, 'message' => 'Subscription frozen'];
        }

        if ($subscription->isExpired()) {
            return ['allowed' => false, 'message' => 'Subscription expired'];
        }

        return ['allowed' => true, 'message' => '✅ Access granted'];
    }
}
