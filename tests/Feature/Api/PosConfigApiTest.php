<?php

use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\PosConfig;
use App\Models\Sequence;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('pos configs api supports CRUD, form-options and toggle status', function () {
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
        'email' => 'api-pos-config@example.com',
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
        'is_member' => false,
        'document_type' => 'DNI',
        'document_number' => (string) random_int(10000000, 99999999),
        'first_name' => 'Cliente',
        'last_name' => 'POS',
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

    $sequence = Sequence::create([
        'sequence_size' => 8,
        'step' => 1,
        'next_number' => 1,
    ]);

    $journal = Journal::create([
        'company_id' => $company->id,
        'name' => 'Boletas POS',
        'code' => 'BP' . random_int(100, 999),
        'type' => 'sale',
        'is_fiscal' => true,
        'document_type_code' => '03',
        'sequence_id' => $sequence->id,
    ]);

    $index = actingAs($user)->getJson('/api/pos-configs');
    $index->assertOk()->assertJsonStructure(['data', 'meta']);

    $options = actingAs($user)->getJson('/api/pos-configs/form-options');
    $options->assertOk()->assertJsonStructure(['data' => ['warehouses', 'customers', 'taxes', 'journals']]);

    $create = actingAs($user)->postJson('/api/pos-configs', [
        'name' => 'POS Principal',
        'warehouse_id' => $warehouse->id,
        'default_customer_id' => $customer->id,
        'tax_id' => $tax->id,
        'apply_tax' => true,
        'prices_include_tax' => false,
        'is_active' => true,
        'journals' => [
            [
                'journal_id' => $journal->id,
                'document_type' => 'receipt',
                'is_default' => true,
            ],
        ],
    ]);
    $create->assertCreated()->assertJsonPath('data.name', 'POS Principal');
    $posConfigId = (int) data_get($create->json(), 'data.id');
    expect($posConfigId)->toBeGreaterThan(0);

    $show = actingAs($user)->getJson("/api/pos-configs/{$posConfigId}");
    $show->assertOk()->assertJsonPath('data.id', $posConfigId);
    $show->assertJsonStructure(['meta' => ['activities']]);

    $toggle = actingAs($user)->postJson("/api/pos-configs/{$posConfigId}/toggle-status");
    $toggle->assertOk()->assertJsonPath('data.is_active', false);

    $update = actingAs($user)->putJson("/api/pos-configs/{$posConfigId}", [
        'name' => 'POS Editado',
        'warehouse_id' => $warehouse->id,
        'default_customer_id' => $customer->id,
        'tax_id' => $tax->id,
        'apply_tax' => true,
        'prices_include_tax' => true,
        'is_active' => true,
        'journals' => [
            [
                'journal_id' => $journal->id,
                'document_type' => 'receipt',
                'is_default' => false,
            ],
        ],
    ]);
    $update->assertOk()->assertJsonPath('data.name', 'POS Editado');

    expect(PosConfig::query()->findOrFail($posConfigId)->journals()->count())->toBe(1);

    $delete = actingAs($user)->deleteJson("/api/pos-configs/{$posConfigId}");
    $delete->assertOk()->assertJsonPath('ok', true);
});
