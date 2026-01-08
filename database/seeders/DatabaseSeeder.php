<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed companies first
        $this->call(CompanySeeder::class);

        // Seed warehouses
        $this->call(WarehouseSeeder::class);

        // Seed taxes
        $this->call(TaxSeeder::class);

        // Seed journals (with sequences)
        $this->call(JournalSeeder::class);

        // Seed users with company assignments
        $this->call(UserSeeder::class);

        // Seed partners
        $this->call(PartnerSeeder::class);

        // Seed membership plans
        $this->call(MembershipPlanSeeder::class);

        // Seed categories
        $this->call(CategorySeeder::class);

        // Seed attributes
        $this->call(AttributeSeeder::class);

        // Seed products
        $this->call(ProductSeeder::class);

        // Seed POS configs
        $this->call(PosConfigSeeder::class);
    }
}
