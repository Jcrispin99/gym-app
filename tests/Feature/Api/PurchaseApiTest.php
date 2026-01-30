<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Purchase;
use App\Models\Sequence;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('purchases api supports CRUD, options and workflow', function () {
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
        'email' => 'api-purchase@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
        'company_id' => $company->id,
    ]);

    $sequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    Journal::create([
        'name' => 'Compras',
        'code' => 'COMP' . random_int(10, 99),
        'type' => 'purchase',
        'is_fiscal' => false,
        'document_type_code' => null,
        'sequence_id' => $sequence->id,
        'company_id' => $company->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Almacén ' . Str::uuid()->toString(),
        'location' => null,
        'company_id' => $company->id,
    ]);

    $supplier = Partner::create([
        'company_id' => $company->id,
        'is_supplier' => true,
        'is_customer' => false,
        'is_member' => false,
        'document_type' => 'RUC',
        'document_number' => (string) random_int(10000000000, 99999999999),
        'business_name' => 'Proveedor ' . Str::uuid()->toString(),
        'status' => 'active',
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

    $index = actingAs($user)->getJson('/api/purchases');
    $index->assertOk()->assertJsonStructure(['data', 'meta']);

    $options = actingAs($user)->getJson('/api/purchases/form-options');
    $options->assertOk()->assertJsonStructure(['data' => ['suppliers', 'warehouses', 'taxes']]);

    $create = actingAs($user)->postJson('/api/purchases', [
        'partner_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'vendor_bill_number' => 'F001-1',
        'vendor_bill_date' => now()->toDateString(),
        'observation' => 'Obs',
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
    $purchaseId = (int) data_get($create->json(), 'data.id');
    expect($purchaseId)->toBeGreaterThan(0);

    $show = actingAs($user)->getJson("/api/purchases/{$purchaseId}");
    $show->assertOk()->assertJsonPath('data.id', $purchaseId);
    $show->assertJsonStructure(['data' => ['productables'], 'meta' => ['activities']]);

    $update = actingAs($user)->putJson("/api/purchases/{$purchaseId}", [
        'partner_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'vendor_bill_number' => null,
        'vendor_bill_date' => null,
        'observation' => 'Obs 2',
        'products' => [
            [
                'product_product_id' => $variant->id,
                'quantity' => 3,
                'price' => 10,
                'tax_id' => $tax->id,
            ],
        ],
    ]);
    $update->assertOk()->assertJsonPath('data.observation', 'Obs 2');

    $post = actingAs($user)->postJson("/api/purchases/{$purchaseId}/post");
    $post->assertOk()->assertJsonPath('data.status', 'posted');

    $cannotDeletePosted = actingAs($user)->deleteJson("/api/purchases/{$purchaseId}");
    $cannotDeletePosted->assertStatus(422);

    $cancel = actingAs($user)->postJson("/api/purchases/{$purchaseId}/cancel");
    $cancel->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect(Inventory::query()->where('inventoryable_type', Purchase::class)->where('inventoryable_id', $purchaseId)->count())
        ->toBeGreaterThan(0);

    $draft = actingAs($user)->postJson('/api/purchases', [
        'partner_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'vendor_bill_number' => null,
        'vendor_bill_date' => null,
        'observation' => null,
        'products' => [
            [
                'product_product_id' => $variant->id,
                'quantity' => 1,
                'price' => 10,
                'tax_id' => null,
            ],
        ],
    ]);
    $draft->assertCreated();
    $draftId = (int) data_get($draft->json(), 'data.id');

    $delete = actingAs($user)->deleteJson("/api/purchases/{$draftId}");
    $delete->assertOk()->assertJsonPath('ok', true);
    expect(Purchase::find($draftId))->toBeNull();
});
