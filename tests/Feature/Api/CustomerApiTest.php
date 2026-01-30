<?php

use App\Models\Company;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('customers api supports CRUD and form-options', function () {
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
        'email' => 'api-customer@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
        'company_id' => $company->id,
    ]);

    $index = actingAs($user)->getJson('/api/customers');
    $index->assertOk()->assertJsonStructure(['data']);

    $options = actingAs($user)->getJson('/api/customers/form-options');
    $options->assertOk()->assertJsonStructure(['data' => ['companies']]);

    $doc = (string) random_int(10000000, 99999999);
    $create = actingAs($user)->postJson('/api/customers', [
        'company_id' => $company->id,
        'document_type' => 'DNI',
        'document_number' => $doc,
        'first_name' => 'Cliente',
        'last_name' => 'Test',
        'email' => 'cliente@example.com',
        'phone' => '123456',
        'mobile' => '999999999',
        'address' => 'Av. Test 123',
        'district' => 'Lima',
        'province' => 'Lima',
        'department' => 'Lima',
        'birth_date' => '1990-01-01',
        'gender' => 'M',
        'emergency_contact_name' => 'Contacto',
        'emergency_contact_phone' => '999999998',
        'photo_url' => 'https://example.com/photo.jpg',
        'notes' => 'Nota',
    ]);

    $create->assertCreated()->assertJsonPath('data.is_customer', true);
    $customerId = (int) data_get($create->json(), 'data.id');
    expect($customerId)->toBeGreaterThan(0);

    $show = actingAs($user)->getJson("/api/customers/{$customerId}");
    $show->assertOk()->assertJsonPath('data.id', $customerId);
    $show->assertJsonStructure(['meta' => ['activities']]);

    $update = actingAs($user)->putJson("/api/customers/{$customerId}", [
        'company_id' => $company->id,
        'document_type' => 'DNI',
        'document_number' => $doc,
        'first_name' => 'Cliente2',
        'last_name' => 'Test2',
        'email' => 'cliente2@example.com',
        'phone' => '1234567',
        'mobile' => '999999997',
        'address' => 'Av. Test 124',
        'district' => 'Lima',
        'province' => 'Lima',
        'department' => 'Lima',
        'birth_date' => '1991-01-01',
        'gender' => 'F',
        'emergency_contact_name' => 'Contacto2',
        'emergency_contact_phone' => '999999996',
        'photo_url' => 'https://example.com/photo2.jpg',
        'notes' => 'Nota 2',
        'status' => 'active',
    ]);
    $update->assertOk()->assertJsonPath('data.first_name', 'Cliente2');

    $delete = actingAs($user)->deleteJson("/api/customers/{$customerId}");
    $delete->assertOk()->assertJsonPath('ok', true);

    expect(Partner::query()->whereKey($customerId)->exists())->toBeFalse();
});

