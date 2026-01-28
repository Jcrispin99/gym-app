<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

test('warehouses api supports CRUD', function () {
    $user = User::query()->create([
        'name' => 'API User',
        'email' => 'api-warehouse@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $company = Company::create([
        'business_name' => 'Kraken Gym SAC',
        'trade_name' => 'Kraken Gym',
        'ruc' => '20123456999',
        'active' => true,
    ]);

    $response = actingAs($user)->getJson('/api/warehouses');
    $response->assertOk()->assertJsonStructure(['data']);

    $create = actingAs($user)->postJson('/api/warehouses', [
        'name' => 'Almacén API',
        'location' => 'Lima',
        'company_id' => $company->id,
    ]);

    $create->assertCreated();
    $warehouseId = (int) data_get($create->json(), 'data.id');
    expect($warehouseId)->toBeGreaterThan(0);

    $show = actingAs($user)->getJson("/api/warehouses/{$warehouseId}");
    $show->assertOk()
        ->assertJsonPath('data.id', $warehouseId)
        ->assertJsonPath('data.name', 'Almacén API');

    $update = actingAs($user)->putJson("/api/warehouses/{$warehouseId}", [
        'name' => 'Almacén API 2',
        'location' => null,
        'company_id' => $company->id,
    ]);
    $update->assertOk()->assertJsonPath('data.name', 'Almacén API 2');

    $filter = actingAs($user)->getJson('/api/warehouses?company_id=' . $company->id);
    $filter->assertOk();
    expect(collect(data_get($filter->json(), 'data'))->pluck('company_id')->unique()->values()->all())
        ->toEqual([$company->id]);

    $delete = actingAs($user)->deleteJson("/api/warehouses/{$warehouseId}");
    $delete->assertOk()->assertJsonPath('ok', true);
    expect(Warehouse::find($warehouseId))->toBeNull();
});

