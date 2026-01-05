<?php

namespace App\Console\Commands;

use App\Models\MembershipFreeze;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UnfreezeExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:unfreeze';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unfreeze subscriptions whose freeze period has ended';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredFreezes = MembershipFreeze::whereDate('freeze_end_date', '<=', Carbon::now())
            ->where('status', 'active')
            ->with('subscription')
            ->get();

        $count = 0;

        foreach ($expiredFreezes as $freeze) {
            $subscription = $freeze->subscription;

            if (! $subscription || $subscription->status !== 'frozen') {
                continue;
            }

            // Calcular días reales congelados (cumplió el período completo)
            $actualDays = Carbon::now()->diffInDays($freeze->freeze_start_date);

            // Actualizar freeze record
            $freeze->update([
                'days_frozen' => $actualDays,
                'status' => 'completed',
            ]);

            // Cambiar status a activo (la fecha ya fue extendida al congelar)
            $subscription->update(['status' => 'active']);
            $subscription->increment('total_days_frozen', $actualDays);

            $this->info("Descongelado: Subscription #{$subscription->id} - {$actualDays} días");
            $count++;
        }

        $this->info("Total de suscripciones descongeladas: {$count}");
    }
}
