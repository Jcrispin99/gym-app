<?php

use App\Models\Attribute;
use App\Models\Category;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('product templates api supports CRUD with variants', function () {
    $user = User::query()->create([
        'name' => 'API User',
        'email' => 'api-product-template@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $category = Category::create([
        'name' => 'Cat ' . Str::uuid()->toString(),
        'slug' => 'cat-' . Str::uuid()->toString(),
        'is_active' => true,
    ]);

    $attribute = Attribute::create([
        'name' => 'Color ' . Str::uuid()->toString(),
        'is_active' => true,
    ]);

    $index = actingAs($user)->getJson('/api/product-templates');
    $index->assertOk()->assertJsonStructure(['data', 'meta']);

    $name = 'Prod ' . Str::uuid()->toString();
    $create = actingAs($user)->postJson('/api/product-templates', [
        'name' => $name,
        'description' => 'Desc',
        'price' => 10,
        'category_id' => $category->id,
        'is_active' => true,
        'attributeLines' => [
            [
                'attribute_id' => $attribute->id,
                'values' => ['Rojo', 'Azul'],
            ],
        ],
        'generatedVariants' => [
            [
                'sku' => 'SKU-RED',
                'barcode' => '1234567890123',
                'price' => 10,
                'attributes' => [
                    (string) $attribute->id => 'Rojo',
                ],
            ],
            [
                'sku' => 'SKU-BLUE',
                'barcode' => '1234567890124',
                'price' => 10,
                'attributes' => [
                    (string) $attribute->id => 'Azul',
                ],
            ],
        ],
    ]);

    $create->assertCreated()->assertJsonPath('data.name', $name);
    $templateId = (int) data_get($create->json(), 'data.id');
    expect($templateId)->toBeGreaterThan(0);
    expect(data_get($create->json(), 'data.product_products'))->toBeArray();
    expect(count(data_get($create->json(), 'data.product_products')))->toBe(2);

    $show = actingAs($user)->getJson("/api/product-templates/{$templateId}");
    $show->assertOk()->assertJsonPath('data.id', $templateId);

    $newName = 'Prod ' . Str::uuid()->toString();
    $update = actingAs($user)->putJson("/api/product-templates/{$templateId}", [
        'name' => $newName,
        'description' => null,
        'price' => 12,
        'category_id' => $category->id,
        'is_active' => false,
        'generatedVariants' => [
            [
                'sku' => 'SKU-RED',
                'barcode' => '1234567890123',
                'price' => 12,
                'attributes' => [
                    (string) $attribute->id => 'Rojo',
                ],
            ],
        ],
    ]);
    $update->assertOk()->assertJsonPath('data.name', $newName);
    expect(count(data_get($update->json(), 'data.product_products')))->toBe(1);

    $toggle = actingAs($user)->postJson("/api/product-templates/{$templateId}/toggle-status");
    $toggle->assertOk()->assertJsonPath('data.is_active', true);

    $delete = actingAs($user)->deleteJson("/api/product-templates/{$templateId}");
    $delete->assertOk()->assertJsonPath('ok', true);
    expect(ProductTemplate::find($templateId))->toBeNull();
});

