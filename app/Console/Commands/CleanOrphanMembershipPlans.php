<?php

namespace App\Console\Commands;

use App\Models\MembershipPlan;
use Illuminate\Console\Command;

class CleanOrphanMembershipPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'membership:clean-orphans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete membership plans without associated products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orphans = MembershipPlan::whereNull('product_product_id')->get();

        if ($orphans->isEmpty()) {
            $this->info('✅ No hay planes huérfanos (sin productos). Todo está limpio.');

            return 0;
        }

        $this->warn("Encontrados {$orphans->count()} planes sin productos asociados:");
        $this->newLine();

        foreach ($orphans as $plan) {
            $this->line("  - ID {$plan->id}: {$plan->name} (creado: {$plan->created_at->format('Y-m-d H:i')})");
        }

        $this->newLine();

        if (! $this->confirm('¿Deseas eliminar estos planes?', true)) {
            $this->info('Operación cancelada.');

            return 0;
        }

        $count = $orphans->count();
        MembershipPlan::whereNull('product_product_id')->delete();

        $this->info("✅ Se eliminaron {$count} planes huérfanos.");
        $this->info('ℹ️  Ahora puedes correr: php artisan db:seed --class=MembershipPlanSeeder');

        return 0;
    }
}
