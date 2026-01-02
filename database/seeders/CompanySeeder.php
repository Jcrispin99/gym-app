<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear casa matriz
        $mainOffice = Company::create([
            'business_name' => 'KRAKEN GYM S.A.C.',
            'trade_name' => 'Kraken Gym - Casa Matriz',
            'ruc' => '20123456789',
            'address' => 'Av. Principal 123',
            'phone' => '987654321',
            'email' => 'contacto@krakengym.com',
            'logo_url' => null,
            'ubigeo' => '150101',
            'urbanization' => 'Los Olivos',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Lima',
            'active' => true,
            'parent_id' => null,
            'branch_code' => null,
            'is_main_office' => true,
        ]);

        // Crear sucursales
        Company::create([
            'business_name' => 'KRAKEN GYM S.A.C.',
            'trade_name' => 'Kraken Gym - San Isidro',
            'ruc' => '20123456789',
            'address' => 'Jr. Las Flores 456',
            'phone' => '912345678',
            'email' => 'sanisidro@krakengym.com',
            'logo_url' => null,
            'ubigeo' => '150130',
            'urbanization' => 'San Isidro',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'San Isidro',
            'active' => true,
            'parent_id' => $mainOffice->id,
            'branch_code' => 'SUC-001',
            'is_main_office' => false,
        ]);

        Company::create([
            'business_name' => 'KRAKEN GYM S.A.C.',
            'trade_name' => 'Kraken Gym - Miraflores',
            'ruc' => '20123456789',
            'address' => 'Av. Larco 789',
            'phone' => '923456789',
            'email' => 'miraflores@krakengym.com',
            'logo_url' => null,
            'ubigeo' => '150122',
            'urbanization' => 'Miraflores',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Miraflores',
            'active' => true,
            'parent_id' => $mainOffice->id,
            'branch_code' => 'SUC-002',
            'is_main_office' => false,
        ]);

        Company::create([
            'business_name' => 'KRAKEN GYM S.A.C.',
            'trade_name' => 'Kraken Gym - Surco',
            'ruc' => '20123456789',
            'address' => 'Av. Primavera 321',
            'phone' => '934567890',
            'email' => 'surco@krakengym.com',
            'logo_url' => null,
            'ubigeo' => '150141',
            'urbanization' => 'Surco',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Santiago de Surco',
            'active' => true,
            'parent_id' => $mainOffice->id,
            'branch_code' => 'SUC-003',
            'is_main_office' => false,
        ]);
    }
}
