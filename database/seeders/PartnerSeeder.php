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
        // MEMBERS (Miembros del gym)
        // ========================================

        // Member 1: Miembro activo con portal
        Partner::create([
            'is_member' => true,
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan.perez@gmail.com',
            'phone' => '987654321',
            'address' => 'Av. Larco 123',
            'district' => 'Miraflores',
            'birth_date' => '1990-05-15',
            'gender' => 'M',
            'blood_type' => 'O+',
            'emergency_contact_name' => 'María Pérez',
            'emergency_contact_phone' => '987654322',
            'status' => 'active',
            // user_id se asignará después si quiere portal
        ]);

        // Member 2: Miembro SIN portal (solo datos)
        Partner::create([
            'is_member' => true,
            'document_type' => 'DNI',
            'document_number' => '87654321',
            'first_name' => 'María',
            'last_name' => 'García',
            'email' => 'maria.garcia@gmail.com',
            'phone' => '987123456',
            'birth_date' => '1995-08-20',
            'gender' => 'F',
            'status' => 'active',
        ]);

        // ========================================
        // CUSTOMERS (Clientes POS - NO miembros)
        // ========================================

        Partner::create([
            'is_customer' => true,
            'document_type' => 'DNI',
            'document_number' => '33445566',
            'first_name' => 'Lucía',
            'last_name' => 'Ramos',
            'email' => 'lucia.ramos@gmail.com',
            'phone' => '900111222',
            'status' => 'active',
        ]);

        // ========================================
        // SUPPLIERS (Proveedores)
        // ========================================

        // Supplier 1: Proveedor de equipos
        Partner::create([
            'is_supplier' => true,
            'document_type' => 'RUC',
            'document_number' => '20123456789',
            'business_name' => 'GymEquip Peru S.A.C.',
            'email' => 'ventas@gymequip.com',
            'phone' => '014567890',
            'address' => 'Av. Industrial 456',
            'district' => 'San Juan de Lurigancho',
            'tax_id' => '20123456789',
            'payment_terms' => 30, // 30 días de crédito
            'credit_limit' => 50000.00,
            'provider_category' => 'equipment',
            'status' => 'active',
        ]);

        // Supplier 2: Proveedor de suplementos
        Partner::create([
            'is_supplier' => true,
            'document_type' => 'RUC',
            'document_number' => '20987654321',
            'business_name' => 'Nutrition Plus E.I.R.L.',
            'email' => 'contacto@nutritionplus.pe',
            'phone' => '012345678',
            'address' => 'Jr. Los Pinos 789',
            'district' => 'La Molina',
            'tax_id' => '20987654321',
            'payment_terms' => 15,
            'credit_limit' => 20000.00,
            'provider_category' => 'supplements',
            'status' => 'active',
        ]);

        // Supplier 3: Proveedor de servicios
        Partner::create([
            'is_supplier' => true,
            'document_type' => 'RUC',
            'document_number' => '20555555555',
            'business_name' => 'Clean Services S.R.L.',
            'email' => 'servicios@cleanservices.pe',
            'phone' => '018765432',
            'payment_terms' => 7,
            'provider_category' => 'services',
            'status' => 'active',
        ]);

        // ========================================
        // SUPPLIERS (Proveedores menores)
        // ========================================

        Partner::create([
            'is_supplier' => true,
            'document_type' => 'DNI',
            'document_number' => '45678912',
            'first_name' => 'Carlos',
            'last_name' => 'Rodríguez',
            'business_name' => 'Distribuidora CRL',
            'email' => 'carlos.rodriguez@gmail.com',
            'phone' => '999888777',
            'payment_terms' => 0, // Pago al contado
            'status' => 'active',
        ]);

        // ========================================
        // MIXED ROLE (Member AND Supplier)
        // ========================================

        // Partner que es BOTH member AND supplier
        Partner::create([
            'is_member' => true,
            'is_supplier' => true,
            'document_type' => 'RUC',
            'document_number' => '20111222333',
            'business_name' => 'FitnessPro Consulting',
            'first_name' => 'Roberto',
            'last_name' => 'Martínez',
            'email' => 'roberto@fitnesspro.pe',
            'phone' => '987111222',
            'payment_terms' => 15,
            'status' => 'active',
        ]);
    }
}
