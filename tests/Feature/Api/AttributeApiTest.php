<?php

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('attributes api supports CRUD and toggle status', function () {
    $user = User::query()->create([
        'name' => 'API User',
        'email' => 'api-attribute@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $index = actingAs($user)->getJson('/api/attributes');
    $index->assertOk()->assertJsonStructure(['data']);

    $name = 'Color ' . Str::uuid()->toString();
    $create = actingAs($user)->postJson('/api/attributes', [
        'name' => $name,
        'is_active' => true,
        'values' => ['Rojo', 'Azul'],
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.name', $name);

    $attributeId = (int) data_get($create->json(), 'data.id');
    expect($attributeId)->toBeGreaterThan(0);

    $show = actingAs($user)->getJson("/api/attributes/{$attributeId}");
    $show->assertOk()
        ->assertJsonPath('data.id', $attributeId)
        ->assertJsonPath('data.name', $name);

    $newName = 'Talla ' . Str::uuid()->toString();
    $update = actingAs($user)->putJson("/api/attributes/{$attributeId}", [
        'name' => $newName,
        'is_active' => false,
        'values' => ['S', 'M', 'L'],
    ]);
    $update->assertOk()->assertJsonPath('data.name', $newName);

    $toggle = actingAs($user)->postJson("/api/attributes/{$attributeId}/toggle-status");
    $toggle->assertOk()->assertJsonPath('data.is_active', true);

    $delete = actingAs($user)->deleteJson("/api/attributes/{$attributeId}");
    $delete->assertOk()->assertJsonPath('ok', true);
    expect(Attribute::find($attributeId))->toBeNull();
});

