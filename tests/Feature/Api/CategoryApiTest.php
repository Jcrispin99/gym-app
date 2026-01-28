<?php

use App\Models\Category;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('categories api supports CRUD, toggle and delete constraints', function () {
    $user = User::query()->create([
        'name' => 'API User',
        'email' => 'api-category@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'staff',
    ]);

    $index = actingAs($user)->getJson('/api/categories');
    $index->assertOk()->assertJsonStructure(['data']);

    $name = 'Cat ' . Str::uuid()->toString();
    $slug = 'cat-' . Str::uuid()->toString();

    $create = actingAs($user)->postJson('/api/categories', [
        'name' => $name,
        'slug' => $slug,
        'full_name' => 'Full ' . $name,
        'description' => 'Desc',
        'is_active' => true,
        'parent_id' => null,
    ]);

    $create->assertCreated()->assertJsonPath('data.name', $name);
    $categoryId = (int) data_get($create->json(), 'data.id');
    expect($categoryId)->toBeGreaterThan(0);

    $show = actingAs($user)->getJson("/api/categories/{$categoryId}");
    $show->assertOk()->assertJsonPath('data.id', $categoryId);

    $newName = 'Cat ' . Str::uuid()->toString();
    $newSlug = 'cat-' . Str::uuid()->toString();
    $update = actingAs($user)->putJson("/api/categories/{$categoryId}", [
        'name' => $newName,
        'slug' => $newSlug,
        'full_name' => null,
        'description' => null,
        'parent_id' => null,
        'is_active' => false,
    ]);
    $update->assertOk()->assertJsonPath('data.name', $newName);

    $toggle = actingAs($user)->postJson("/api/categories/{$categoryId}/toggle-status");
    $toggle->assertOk()->assertJsonPath('data.is_active', true);

    $child = Category::create([
        'name' => 'Child ' . Str::uuid()->toString(),
        'slug' => 'child-' . Str::uuid()->toString(),
        'parent_id' => $categoryId,
        'is_active' => true,
    ]);

    $deleteWithChild = actingAs($user)->deleteJson("/api/categories/{$categoryId}");
    $deleteWithChild->assertStatus(422);

    $child->delete();

    ProductTemplate::create([
        'name' => 'Prod ' . Str::uuid()->toString(),
        'category_id' => $categoryId,
    ]);

    $deleteWithProduct = actingAs($user)->deleteJson("/api/categories/{$categoryId}");
    $deleteWithProduct->assertStatus(422);

    ProductTemplate::query()->where('category_id', $categoryId)->delete();

    $delete = actingAs($user)->deleteJson("/api/categories/{$categoryId}");
    $delete->assertOk()->assertJsonPath('ok', true);
    expect(Category::find($categoryId))->toBeNull();
});

