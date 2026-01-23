<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // ========================================
        // CUSTOMER DEFAULT (Cliente Varios)
        // ========================================

        Partner::create([
            'is_customer' => true,
            'document_type' => 'DNI',
            'document_number' => '00000000',
            'first_name' => 'Varios',
            'last_name' => ' ', // Asignamos un apellido genérico por si es campo requerido
            'email' => 'varios@krakengym.com',
            'status' => 'active',
        ]);
    }
}
