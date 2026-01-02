<?php

namespace Database\Seeders;

use App\Models\User;
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
        
        // Seed users with company assignments
        $this->call(UserSeeder::class);
    }
}
