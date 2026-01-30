<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Sale;
use App\Models\Sequence;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

test('pos refund process creates credit note and activity logs with causer', function () {
    $user = User::query()->create([
        'name' => 'Cajero',
        'email' => 'cajero-refund@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $company = Company::create([
        'business_name' => 'Kraken Gym SAC',
        'trade_name' => 'Kraken Gym',
        'ruc' => '20123456010',
        'active' => true,
    ]);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'name' => 'Almacén Principal',
        'location' => null,
    ]);

    $originSequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 2,
    ]);

    $originJournal = Journal::create([
        'company_id' => $company->id,
        'name' => 'Boletas',
        'code' => 'B004',
        'type' => 'sale',
        'is_fiscal' => true,
        'document_type_code' => '03',
        'sequence_id' => $originSequence->id,
    ]);

    $creditSequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    $creditJournal = Journal::create([
        'company_id' => $company->id,
        'name' => 'Notas de Crédito',
        'code' => 'BC04',
        'type' => 'sale',
        'is_fiscal' => true,
        'document_type_code' => '07',
        'sequence_id' => $creditSequence->id,
    ]);

    $posConfig = PosConfig::create([
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'name' => 'Caja 1',
        'is_active' => true,
        'apply_tax' => false,
        'prices_include_tax' => true,
    ]);

    $posConfig->journals()->attach($creditJournal->id, [
        'document_type' => 'credit_note',
        'is_default' => true,
    ]);

    $session = PosSession::create([
        'user_id' => $user->id,
        'pos_config_id' => $posConfig->id,
        'opening_balance' => 100,
        'opening_note' => null,
        'opened_at' => now(),
        'status' => PosSession::STATUS_OPENED,
    ]);

    $category = Category::create([
        'name' => 'Bebidas',
        'slug' => 'bebidas-refund',
        'full_name' => 'Bebidas',
        'description' => null,
        'parent_id' => null,
        'is_active' => true,
    ]);

    $template = ProductTemplate::create([
        'category_id' => $category->id,
        'name' => 'Agua',
        'description' => null,
        'price' => 20.00,
        'is_active' => true,
    ]);

    $variant = ProductProduct::create([
        'product_template_id' => $template->id,
        'sku' => 'AGUA-REF',
        'barcode' => '7712345678909',
        'price' => 20.00,
        'is_principal' => true,
    ]);

    $originSale = Sale::create([
        'serie' => 'B004',
        'correlative' => '00000001',
        'journal_id' => $originJournal->id,
        'warehouse_id' => $warehouse->id,
        'company_id' => $company->id,
        'user_id' => $user->id,
        'subtotal' => 40.00,
        'tax_amount' => 0.00,
        'total' => 40.00,
        'status' => 'posted',
        'payment_status' => 'paid',
    ]);

    $originSale->products()->create([
        'product_product_id' => $variant->id,
        'quantity' => 2,
        'price' => 20.00,
        'subtotal' => 40.00,
        'tax_id' => null,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 40.00,
    ]);

    $refundMethod = PaymentMethod::create([
        'name' => 'Efectivo',
        'is_active' => true,
    ]);

    $response = actingAs($user)->post("/pos/{$session->id}/refund/process", [
        'origin_sale_id' => $originSale->id,
        'return_items' => [
            [
                'product_product_id' => $variant->id,
                'quantity' => 1,
            ],
        ],
        'sale_items' => [],
        'refund_payment_method_id' => $refundMethod->id,
        'refund_amount' => 20.00,
    ]);

    $response->assertStatus(302);
    $response->assertRedirect("/pos/{$session->id}?clear_cart=1");

    $creditSale = Sale::query()
        ->where('original_sale_id', $originSale->id)
        ->where('pos_session_id', $session->id)
        ->where('status', 'posted')
        ->firstOrFail();

    expect($creditSale->journal?->document_type_code)->toBe('07');

    $creditLog = Activity::forSubject($creditSale)
        ->where('description', 'Devolución POS creada')
        ->latest()
        ->first();

    expect($creditLog)->not->toBeNull();
    expect($creditLog->causer_id)->toBe($user->id);
    expect(data_get($creditLog->properties, 'origin_sale_id'))->toBe($originSale->id);

    $originLog = Activity::forSubject($originSale)
        ->where('description', 'Devolución POS registrada')
        ->latest()
        ->first();

    expect($originLog)->not->toBeNull();
    expect($originLog->causer_id)->toBe($user->id);
    expect(data_get($originLog->properties, 'credit_note_id'))->toBe($creditSale->id);
});

test('sales create credit note creates draft and logs activity', function () {
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin-credit-note@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $company = Company::create([
        'business_name' => 'Kraken Gym SAC',
        'trade_name' => 'Kraken Gym',
        'ruc' => '20123456011',
        'active' => true,
    ]);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'name' => 'Almacén Principal',
        'location' => null,
    ]);

    $originSequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 2,
    ]);

    $originJournal = Journal::create([
        'company_id' => $company->id,
        'name' => 'Boletas',
        'code' => 'B004',
        'type' => 'sale',
        'is_fiscal' => true,
        'document_type_code' => '03',
        'sequence_id' => $originSequence->id,
    ]);

    $creditSequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    Journal::create([
        'company_id' => $company->id,
        'name' => 'Notas de Crédito',
        'code' => 'BC04',
        'type' => 'sale',
        'is_fiscal' => true,
        'document_type_code' => '07',
        'sequence_id' => $creditSequence->id,
    ]);

    $category = Category::create([
        'name' => 'Bebidas',
        'slug' => 'bebidas-sales',
        'full_name' => 'Bebidas',
        'description' => null,
        'parent_id' => null,
        'is_active' => true,
    ]);

    $template = ProductTemplate::create([
        'category_id' => $category->id,
        'name' => 'Agua',
        'description' => null,
        'price' => 10.00,
        'is_active' => true,
    ]);

    $variant = ProductProduct::create([
        'product_template_id' => $template->id,
        'sku' => 'AGUA-02',
        'barcode' => '7712345678910',
        'price' => 10.00,
        'is_principal' => true,
    ]);

    $originSale = Sale::create([
        'serie' => 'B004',
        'correlative' => '00000001',
        'journal_id' => $originJournal->id,
        'warehouse_id' => $warehouse->id,
        'company_id' => $company->id,
        'user_id' => $user->id,
        'subtotal' => 10.00,
        'tax_amount' => 0.00,
        'total' => 10.00,
        'status' => 'posted',
        'payment_status' => 'paid',
    ]);

    $originSale->products()->create([
        'product_product_id' => $variant->id,
        'quantity' => 1,
        'price' => 10.00,
        'subtotal' => 10.00,
        'tax_id' => null,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 10.00,
    ]);

    $response = actingAs($user)->postJson("/api/sales/{$originSale->id}/credit-note");

    $response->assertCreated();

    $creditSaleId = (int) data_get($response->json(), 'data.id');
    expect($creditSaleId)->toBeGreaterThan(0);

    $creditSale = Sale::query()->findOrFail($creditSaleId);
    expect($creditSale->original_sale_id)->toBe($originSale->id);
    expect($creditSale->status)->toBe('draft');

    $originLog = Activity::forSubject($originSale)
        ->where('description', 'Borrador de Nota de Crédito creado')
        ->latest()
        ->first();

    expect($originLog)->not->toBeNull();
    expect($originLog->causer_id)->toBe($user->id);
    expect(data_get($originLog->properties, 'credit_note_id'))->toBe($creditSale->id);

    $creditLog = Activity::forSubject($creditSale)
        ->where('description', 'Nota de Crédito creada desde documento origen')
        ->latest()
        ->first();

    expect($creditLog)->not->toBeNull();
    expect($creditLog->causer_id)->toBe($user->id);
    expect(data_get($creditLog->properties, 'origin_sale_id'))->toBe($originSale->id);
});
