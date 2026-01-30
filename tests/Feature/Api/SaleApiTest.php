<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Sequence;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('sales api supports CRUD and form-options', function () {
    $company = Company::create([
        'business_name' => 'Company ' . Str::uuid()->toString(),
        'trade_name' => 'Trade ' . Str::uuid()->toString(),
        'ruc' => '20' . random_int(100000000, 999999999),
        'address' => 'Av. Test 123',
        'active' => true,
        'is_main_office' => true,
    ]);

    $user = User::query()->create([
        'name' => 'API User',
        'email' => 'api-sale@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
        'company_id' => $company->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Almacén ' . Str::uuid()->toString(),
        'location' => null,
        'company_id' => $company->id,
    ]);

    $customer = Partner::create([
        'company_id' => $company->id,
        'is_customer' => true,
        'is_supplier' => false,
        'is_provider' => false,
        'document_type' => 'DNI',
        'document_number' => (string) random_int(10000000, 99999999),
        'first_name' => 'Cliente',
        'last_name' => 'Test',
        'status' => 'active',
    ]);

    $sequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    Journal::create([
        'company_id' => $company->id,
        'name' => 'Boletas',
        'code' => 'B' . random_int(100, 999),
        'type' => 'sale',
        'is_fiscal' => true,
        'document_type_code' => '03',
        'sequence_id' => $sequence->id,
    ]);

    $tax = Tax::create([
        'name' => 'IGV',
        'rate_percent' => 18,
        'is_active' => true,
        'is_default' => true,
        'tax_type' => 'vat',
        'affectation_type_code' => '10',
        'invoice_label' => 'IGV',
        'is_price_inclusive' => false,
    ]);

    $category = Category::create([
        'name' => 'Cat ' . Str::uuid()->toString(),
        'slug' => 'cat-' . Str::uuid()->toString(),
        'is_active' => true,
    ]);

    $template = ProductTemplate::create([
        'name' => 'Prod ' . Str::uuid()->toString(),
        'description' => null,
        'price' => 10,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $variant = ProductProduct::create([
        'product_template_id' => $template->id,
        'sku' => 'SKU-' . Str::uuid()->toString(),
        'barcode' => null,
        'price' => 10,
        'cost_price' => 5,
        'is_principal' => true,
    ]);

    $index = actingAs($user)->getJson('/api/sales');
    $index->assertOk()->assertJsonStructure(['data', 'meta']);

    $options = actingAs($user)->getJson('/api/sales/form-options');
    $options->assertOk()->assertJsonStructure(['data' => ['customers', 'warehouses', 'taxes']]);

    $create = actingAs($user)->postJson('/api/sales', [
        'partner_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'notes' => 'Nota',
        'products' => [
            [
                'product_product_id' => $variant->id,
                'quantity' => 2,
                'price' => 10,
                'tax_id' => $tax->id,
            ],
        ],
    ]);

    $create->assertCreated()->assertJsonPath('data.status', 'draft');
    $saleId = (int) data_get($create->json(), 'data.id');
    expect($saleId)->toBeGreaterThan(0);

    $show = actingAs($user)->getJson("/api/sales/{$saleId}");
    $show->assertOk()->assertJsonPath('data.id', $saleId);
    $show->assertJsonStructure(['meta' => ['activities', 'originSale', 'creditNotes']]);

    $update = actingAs($user)->putJson("/api/sales/{$saleId}", [
        'partner_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'notes' => 'Nota 2',
        'products' => [
            [
                'product_product_id' => $variant->id,
                'quantity' => 3,
                'price' => 10,
                'tax_id' => $tax->id,
            ],
        ],
    ]);
    $update->assertOk()->assertJsonPath('data.notes', 'Nota 2');

    $delete = actingAs($user)->deleteJson("/api/sales/{$saleId}");
    $delete->assertOk()->assertJsonPath('ok', true);
});

