<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\PaymentMethod;
use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Sequence;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

function makePosForDocumentValidation(): array
{
    $cashier = User::query()->create([
        'name' => 'Cajero',
        'email' => 'cashier@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $company = Company::create([
        'business_name' => 'Kraken Gym SAC',
        'trade_name' => 'Kraken Gym',
        'ruc' => '20123456002',
        'active' => true,
    ]);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'name' => 'Almacén Principal',
        'location' => null,
    ]);

    $invoiceSequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    $receiptSequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    $invoiceJournal = Journal::create([
        'company_id' => $company->id,
        'name' => 'FACTURA DE VENTA',
        'code' => 'F004',
        'type' => 'sale',
        'is_fiscal' => true,
        'document_type_code' => '01',
        'sequence_id' => $invoiceSequence->id,
    ]);

    $receiptJournal = Journal::create([
        'company_id' => $company->id,
        'name' => 'BOLETA DE VENTA',
        'code' => 'B004',
        'type' => 'sale',
        'is_fiscal' => true,
        'document_type_code' => '03',
        'sequence_id' => $receiptSequence->id,
    ]);

    $posConfig = PosConfig::create([
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'name' => 'POS Principal',
        'is_active' => true,
        'apply_tax' => false,
        'prices_include_tax' => true,
    ]);

    $posConfig->journals()->attach($invoiceJournal->id, [
        'document_type' => 'invoice',
        'is_default' => true,
    ]);
    $posConfig->journals()->attach($receiptJournal->id, [
        'document_type' => 'receipt',
        'is_default' => true,
    ]);

    $session = PosSession::create([
        'user_id' => $cashier->id,
        'pos_config_id' => $posConfig->id,
        'opening_balance' => 0,
        'opening_note' => null,
        'opened_at' => now(),
        'status' => PosSession::STATUS_OPENED,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'Efectivo',
        'is_active' => true,
    ]);

    $category = Category::create([
        'name' => 'General',
        'slug' => 'general',
        'full_name' => 'General',
        'description' => null,
        'parent_id' => null,
        'is_active' => true,
    ]);

    $template = ProductTemplate::create([
        'category_id' => $category->id,
        'name' => 'Producto Test',
        'description' => null,
        'price' => 5.00,
        'is_active' => true,
    ]);

    $variant = ProductProduct::create([
        'product_template_id' => $template->id,
        'sku' => 'TEST-01',
        'barcode' => '7712345678999',
        'price' => 5.00,
        'is_principal' => true,
    ]);

    $dniClient = Partner::create([
        'document_type' => 'DNI',
        'document_number' => '12345678',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'is_customer' => true,
        'status' => 'active',
    ]);

    $rucClient = Partner::create([
        'document_type' => 'RUC',
        'document_number' => '20123456789',
        'business_name' => 'Empresa SAC',
        'is_customer' => true,
        'status' => 'active',
    ]);

    return [
        'cashier' => $cashier,
        'session' => $session,
        'invoiceJournal' => $invoiceJournal,
        'receiptJournal' => $receiptJournal,
        'paymentMethod' => $paymentMethod,
        'variant' => $variant,
        'dniClient' => $dniClient,
        'rucClient' => $rucClient,
    ];
}

test('cliente DNI solo permite boleta', function () {
    $ctx = makePosForDocumentValidation();

    $payload = [
        'journal_id' => $ctx['invoiceJournal']->id,
        'client_id' => $ctx['dniClient']->id,
        'cart' => json_encode([[
            'product_id' => $ctx['variant']->id,
            'name' => 'Producto Test',
            'qty' => 1,
            'price' => 5,
            'subtotal' => 5,
        ]]),
        'payments' => json_encode([[
            'payment_method_id' => $ctx['paymentMethod']->id,
            'amount' => 5,
        ]]),
        'total' => 5,
    ];

    $response = actingAs($ctx['cashier'])->postJson(
        "/pos/{$ctx['session']->id}/process",
        $payload,
        ['X-Inertia' => 'true']
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['journal_id']);
});

test('cliente RUC solo permite factura', function () {
    $ctx = makePosForDocumentValidation();

    $payload = [
        'journal_id' => $ctx['receiptJournal']->id,
        'client_id' => $ctx['rucClient']->id,
        'cart' => json_encode([[
            'product_id' => $ctx['variant']->id,
            'name' => 'Producto Test',
            'qty' => 1,
            'price' => 5,
            'subtotal' => 5,
        ]]),
        'payments' => json_encode([[
            'payment_method_id' => $ctx['paymentMethod']->id,
            'amount' => 5,
        ]]),
        'total' => 5,
    ];

    $response = actingAs($ctx['cashier'])->postJson(
        "/pos/{$ctx['session']->id}/process",
        $payload,
        ['X-Inertia' => 'true']
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['journal_id']);
});

test('cliente RUC puede procesar factura', function () {
    $ctx = makePosForDocumentValidation();

    $payload = [
        'journal_id' => $ctx['invoiceJournal']->id,
        'client_id' => $ctx['rucClient']->id,
        'cart' => json_encode([[
            'product_id' => $ctx['variant']->id,
            'name' => 'Producto Test',
            'qty' => 1,
            'price' => 5,
            'subtotal' => 5,
        ]]),
        'payments' => json_encode([[
            'payment_method_id' => $ctx['paymentMethod']->id,
            'amount' => 5,
        ]]),
        'total' => 5,
    ];

    $response = actingAs($ctx['cashier'])->post("/pos/{$ctx['session']->id}/process", $payload);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();
});
