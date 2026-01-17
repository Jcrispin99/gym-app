<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\Productable;
use App\Models\ProductProduct;
use App\Models\Partner;
use App\Models\Warehouse;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\Attendance;
use Carbon\Carbon;

class DashboardTestSeeder extends Seeder
{
    public function run()
    {
        // 1. Ensure basic dependencies exist
        $company = Company::first();
        if (!$company) {
            $company = Company::create([
                'name' => 'Kraken Gym Test',
                'description' => 'Test Gym', // Assuming fields
                'logo_url' => null,
            ]);
        }

        $warehouse = Warehouse::first();
        if (!$warehouse) {
            $warehouse = Warehouse::create([
                'name' => 'Main Warehouse',
                'company_id' => $company->id,
                'location' => 'Main St',
            ]);
        }
        
        // Ensure we have some partners
        if (Partner::count() < 10) {
            for ($i = 0; $i < 10; $i++) {
                Partner::updateOrCreate(
                    [
                        'document_number' => '1234567' . $i, 
                        'document_type' => 'DNI'
                    ],
                    [
                        'first_name' => 'Partner ' . $i,
                        'last_name' => 'Test',
                        'email' => "partner{$i}@test.com",
                        'phone' => '555555' . $i,
                        'company_id' => $company->id,
                        'is_customer' => true,
                        'status' => 'active'
                    ]
                );
            }
        }
        $partners = Partner::all();

        // Ensure we have some products
        if (ProductProduct::count() < 5) {
             // Create manually if needed, but likely ProductSeeder ran. 
             // If not, we can't easily create products because of the complex relationship (Product -> ProductProduct)
             // I'll try to rely on existing products. If 0, I'll error out.
        }
        $products = ProductProduct::inRandomOrder()->limit(10)->get();

        if ($products->isEmpty()) {
            $this->command->error('No products found. Please run ProductSeeder or manually add products.');
            return;
        }

        // 2. Generate Sales (Last 30 days + Today)
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(30);

        // Get a journal
        $journal = \App\Models\Journal::where('type', 'sale')->first() 
            ?? \App\Models\Journal::first() 
            ?? \App\Models\Journal::create(['name' => 'Ventas', 'type' => 'sale', 'company_id' => $company->id, 'code' => 'VEN']);

        $this->command->info('Creating Sales...');

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Random number of sales per day (0 to 5)
            $dailySalesCount = rand(0, 5); 
            
            // Boost today's sales for visibility
            if ($date->isToday()) {
                $dailySalesCount = rand(5, 10);
            }

            for ($i = 0; $i < $dailySalesCount; $i++) {
                $partner = $partners->random();
                $isPaid = rand(0, 10) > 2; // 80% paid
                
                $sale = Sale::create([
                    'serie' => 'F001',
                    'correlative' => str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
                    'journal_id' => $journal->id,
                    'date' => $date->copy()->setTime(rand(8, 20), rand(0, 59)),
                    'partner_id' => $partner->id,
                    'warehouse_id' => $warehouse->id,
                    'company_id' => $company->id,
                    'user_id' => \App\Models\User::first()->id ?? 1, // Fallback to 1
                    'status' => 'posted',
                    'payment_status' => $isPaid ? 'paid' : 'unpaid',
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                ]);

                // Add items
                $total = 0;
                $itemCount = rand(1, 4);
                
                for ($j = 0; $j < $itemCount; $j++) {
                    $product = $products->random();
                    $qty = rand(1, 3);
                    $price = $product->price ?? rand(10, 100);
                    $lineTotal = $qty * $price;

                    Productable::create([
                        'productable_id' => $sale->id,
                        'productable_type' => Sale::class,
                        'product_product_id' => $product->id,
                        'quantity' => $qty,
                        'price' => $price,
                        'subtotal' => $lineTotal,
                        'total' => $lineTotal,
                        'tax_amount' => 0,
                        'tax_rate' => 0,
                    ]);

                    $total += $lineTotal;
                }

                $sale->update([
                    'subtotal' => $total,
                    'total' => $total
                ]);
            }
        }

        // 3. Generate Low Stock Inventory (Stock Threshold)
        $this->command->info('Setting Low Stock...');
        $lowStockProducts = $products->take(3); // Pick 3 products to be low stock

        foreach ($lowStockProducts as $product) {
            $qty = rand(0, 4);
            Inventory::create([
                'detail' => 'Manual Adjustment (Seeder)',
                'quantity_in' => 0,
                'cost_in' => 0,
                'total_in' => 0,
                'quantity_out' => 0,
                'cost_out' => 0,
                'total_out' => 0,
                'quantity_balance' => $qty, // Force low stock
                'cost_balance' => 0,
                'total_balance' => 0,
                'product_product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'inventoryable_type' => 'App\Models\User', // Dummy polymorphic
                'inventoryable_id' => 1,
            ]);
        }

        // 4. Generate Attendance (Visitors)
        $this->command->info('Creating Attendance...');
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $visitorsCount = rand(5, 20);
            
            for ($i = 0; $i < $visitorsCount; $i++) {
                Attendance::create([
                    'partner_id' => $partners->random()->id,
                    'check_in_time' => $date->copy()->setTime(rand(6, 22), rand(0, 59)),
                    'status' => 'valid',
                    'company_id' => $company->id,
                ]);
            }
        }

        $this->command->info('Dashboard Test Data Seeded!');
    }
}
