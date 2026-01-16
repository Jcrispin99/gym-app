<?php

use App\Models\Company;
use App\Models\Category;
use App\Models\Journal;
use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Sale;
use App\Models\Sequence;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('user can view orders for own open pos session', function () {
    $user = User::query()->create([
        'name' => 'Cajero',
        'email' => 'cajero@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $company = Company::create([
        'business_name' => 'Kraken Gym SAC',
        'trade_name' => 'Kraken Gym',
        'ruc' => '20123456000',
        'active' => true,
    ]);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'name' => 'Almacén Principal',
        'location' => null,
    ]);

    $sequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    $journal = Journal::create([
        'company_id' => $company->id,
        'name' => 'POS Ventas',
        'code' => 'POSV',
        'type' => 'sale',
        'is_fiscal' => false,
        'document_type_code' => null,
        'sequence_id' => $sequence->id,
    ]);

    $posConfig = PosConfig::create([
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'name' => 'Caja 1',
        'is_active' => true,
        'apply_tax' => false,
        'prices_include_tax' => true,
    ]);

    $session = PosSession::create([
        'user_id' => $user->id,
        'pos_config_id' => $posConfig->id,
        'opening_balance' => 100,
        'opening_note' => null,
        'opened_at' => now(),
        'status' => PosSession::STATUS_OPENED,
    ]);

    $template = ProductTemplate::create([
        'category_id' => Category::create([
            'name' => 'Bebidas',
            'slug' => 'bebidas',
            'full_name' => 'Bebidas',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ])->id,
        'name' => 'Agua',
        'description' => null,
        'price' => 2.00,
        'is_active' => true,
    ]);

    $variant = ProductProduct::create([
        'product_template_id' => $template->id,
        'sku' => 'AGUA-01',
        'barcode' => '7712345678901',
        'price' => 2.00,
        'is_principal' => true,
    ]);

    $sale = Sale::create([
        'serie' => 'B001',
        'correlative' => '00000001',
        'journal_id' => $journal->id,
        'warehouse_id' => $warehouse->id,
        'company_id' => $company->id,
        'pos_session_id' => $session->id,
        'user_id' => $user->id,
        'subtotal' => 2.00,
        'tax_amount' => 0.00,
        'total' => 2.00,
        'status' => 'posted',
        'payment_status' => 'paid',
    ]);

    $sale->products()->create([
        'product_product_id' => $variant->id,
        'quantity' => 1,
        'price' => 2.00,
        'subtotal' => 2.00,
        'tax_id' => null,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 2.00,
    ]);

    $response = actingAs($user)->get("/pos/{$session->id}/orders");

    $response->assertStatus(200);
    $response->assertInertia(
        fn(Assert $page) => $page
            ->component('Pos/Orders')
            ->where('session.id', $session->id)
            ->has('orders', 1)
            ->where('orders.0.id', $sale->id)
            ->has('orders.0.items', 1)
            ->where('orders.0.items.0.product_product_id', $variant->id)
    );
});

test('user can view orders from pos config session history', function () {
    $viewer = User::query()->create([
        'name' => 'Admin Viewer',
        'email' => 'viewer@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $cashier = User::query()->create([
        'name' => 'Cajero 2',
        'email' => 'cajero2@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $company = Company::create([
        'business_name' => 'Kraken Gym SAC',
        'trade_name' => 'Kraken Gym',
        'ruc' => '20123456001',
        'active' => true,
    ]);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'name' => 'Almacén Principal',
        'location' => null,
    ]);

    $sequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    $journal = Journal::create([
        'company_id' => $company->id,
        'name' => 'POS Ventas 2',
        'code' => 'POSV2',
        'type' => 'sale',
        'is_fiscal' => false,
        'document_type_code' => null,
        'sequence_id' => $sequence->id,
    ]);

    $posConfig = PosConfig::create([
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'name' => 'Caja 2',
        'is_active' => true,
        'apply_tax' => false,
        'prices_include_tax' => true,
    ]);

    $session = PosSession::create([
        'user_id' => $cashier->id,
        'pos_config_id' => $posConfig->id,
        'opening_balance' => 50,
        'opening_note' => null,
        'opened_at' => now(),
        'status' => PosSession::STATUS_CLOSED,
        'closed_at' => now(),
        'closing_balance' => 100,
    ]);

    $category = Category::create([
        'name' => 'Snacks',
        'slug' => 'snacks',
        'full_name' => 'Snacks',
        'description' => null,
        'parent_id' => null,
        'is_active' => true,
    ]);

    $template = ProductTemplate::create([
        'category_id' => $category->id,
        'name' => 'Barrita',
        'description' => null,
        'price' => 5.00,
        'is_active' => true,
    ]);

    $variant = ProductProduct::create([
        'product_template_id' => $template->id,
        'sku' => 'BAR-01',
        'barcode' => '7712345678902',
        'price' => 5.00,
        'is_principal' => true,
    ]);

    $sale = Sale::create([
        'serie' => 'B001',
        'correlative' => '00000002',
        'journal_id' => $journal->id,
        'warehouse_id' => $warehouse->id,
        'company_id' => $company->id,
        'pos_session_id' => $session->id,
        'user_id' => $cashier->id,
        'subtotal' => 5.00,
        'tax_amount' => 0.00,
        'total' => 5.00,
        'status' => 'posted',
        'payment_status' => 'paid',
    ]);

    $sale->products()->create([
        'product_product_id' => $variant->id,
        'quantity' => 1,
        'price' => 5.00,
        'subtotal' => 5.00,
        'tax_id' => null,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 5.00,
    ]);

    $response = actingAs($viewer)->get("/pos-configs/{$posConfig->id}/sessions/{$session->id}/orders");

    $response->assertStatus(200);
    $response->assertInertia(
        fn(Assert $page) => $page
            ->component('Pos/Orders')
            ->where('session.id', $session->id)
            ->where('returnTo', "/pos-configs/{$posConfig->id}/sessions")
            ->has('orders', 1)
            ->where('orders.0.id', $sale->id)
    );
});
