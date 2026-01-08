<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\PosConfig;
use App\Models\Tax;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class PosConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener registros necesarios
        $company = Company::first();
        $warehouse = Warehouse::first();
        $defaultCustomer = Partner::customers()->first();
        $tax = Tax::where('rate_percent', 18)->first(); // IGV 18%

        if (!$company || !$warehouse) {
            $this->command->error('❌ No hay Companies o Warehouses. Ejecuta sus seeders primero.');
            return;
        }

        // Crear POS Config
        $posConfig = PosConfig::create([
            'company_id' => $company->id,
            'name' => 'POS Principal',
            'warehouse_id' => $warehouse->id,
            'default_customer_id' => $defaultCustomer?->id,
            'tax_id' => $tax?->id,
            'apply_tax' => true,
            'prices_include_tax' => false,
            'is_active' => true,
        ]);

        // Obtener journals para asociar
        $invoiceJournal = Journal::where('type', 'sale')->where('name', 'like', '%Factura%')->first();
        $receiptJournal = Journal::where('type', 'sale')->where('name', 'like', '%Boleta%')->first();

        // Asociar journals al POS
        if ($invoiceJournal) {
            $posConfig->journals()->attach($invoiceJournal->id, [
                'document_type' => 'invoice',
                'is_default' => true,
            ]);
        }

        if ($receiptJournal) {
            $posConfig->journals()->attach($receiptJournal->id, [
                'document_type' => 'receipt',
                'is_default' => true,
            ]);
        }

        $this->command->info('✅ POS Config creado: ' . $posConfig->name);
        $this->command->info('   - Warehouse: ' . $warehouse->name);
        $this->command->info('   - Tax: ' . ($tax ? $tax->name . ' (' . $tax->rate_percent . '%)' : 'No asignado'));
        $this->command->info('   - Journals asociados: ' . $posConfig->journals->count());
    }
}
