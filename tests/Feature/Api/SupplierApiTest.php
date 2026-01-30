<?php

use App\Models\Company;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('suppliers api supports CRUD and upsert', function () {
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
        'email' => 'api-supplier@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
        'company_id' => $company->id,
    ]);

    $index = actingAs($user)->getJson('/api/suppliers');
    $index->assertOk()->assertJsonStructure(['data']);

    $options = actingAs($user)->getJson('/api/suppliers/form-options');
    $options->assertOk()->assertJsonStructure(['data' => ['companies']]);

    $doc = (string) random_int(10000000, 99999999);

    $create = actingAs($user)->postJson('/api/suppliers', [
        'company_id' => $company->id,
        'document_type' => 'DNI',
        'document_number' => $doc,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'email' => 'juan.' . Str::uuid()->toString() . '@example.com',
        'supplier_category' => 'services',
        'notes' => 'Nota',
    ]);

    $create->assertCreated()->assertJsonPath('data.is_supplier', true);
    $supplierId = (int) data_get($create->json(), 'data.id');
    expect($supplierId)->toBeGreaterThan(0);

    $show = actingAs($user)->getJson("/api/suppliers/{$supplierId}");
    $show->assertOk()->assertJsonPath('data.id', $supplierId);
    $show->assertJsonStructure(['meta' => ['activities']]);

    $update = actingAs($user)->putJson("/api/suppliers/{$supplierId}", [
        'company_id' => $company->id,
        'document_type' => 'DNI',
        'document_number' => $doc,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'email' => 'updated.' . Str::uuid()->toString() . '@example.com',
        'provider_category' => 'equipment',
        'notes' => 'Nota 2',
        'status' => 'active',
    ]);
    $update->assertOk()->assertJsonPath('data.provider_category', 'equipment');

    $upsert = actingAs($user)->postJson('/api/suppliers', [
        'company_id' => $company->id,
        'document_type' => 'DNI',
        'document_number' => $doc,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'notes' => 'Upsert',
    ]);
    $upsert->assertOk()->assertJsonPath('data.id', $supplierId);

    $delete = actingAs($user)->deleteJson("/api/suppliers/{$supplierId}");
    $delete->assertOk()->assertJsonPath('ok', true);
    expect(Partner::find($supplierId))->toBeNull();
});
