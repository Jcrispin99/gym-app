<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductTemplateApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = ProductTemplate::with(['productProducts', 'mainImage', 'category']);

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('productProducts', function ($variantQuery) use ($search) {
                        $variantQuery->where('sku', 'like', "%{$search}%");
                    })
                    ->orWhereHas('productProducts', function ($variantQuery) use ($search) {
                        $variantQuery->where('barcode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $products = $query->latest()->paginate($perPage);
        $products->getCollection()->each->append(['image', 'sku', 'barcode']);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(ProductTemplate $productTemplate)
    {
        $productTemplate->load([
            'category',
            'images',
            'mainImage',
            'productProducts.attributeValues.attribute',
        ]);

        $productTemplate->append(['image', 'sku', 'barcode']);

        return response()->json([
            'data' => $productTemplate,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'nullable|boolean',
            'is_pos_visible' => 'nullable|boolean',
            'tracks_inventory' => 'nullable|boolean',
            'sku' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:10240',
            'attributeLines' => 'nullable|array',
            'generatedVariants' => 'nullable|array',
            'additionalImages.*' => 'nullable|image|max:10240',
        ], [], [
            'category_id' => 'categoría',
        ]);

        /** @var ProductTemplate $productTemplate */
        $productTemplate = DB::transaction(function () use ($request, $data) {
            $productTemplate = ProductTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'category_id' => $data['category_id'],
                'is_active' => $data['is_active'] ?? true,
                'is_pos_visible' => $data['is_pos_visible'] ?? true,
                'tracks_inventory' => $data['tracks_inventory'] ?? true,
            ]);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/products', 'public');
                $productTemplate->images()->create([
                    'path' => $path,
                ]);
            }

            if ($request->hasFile('additionalImages')) {
                foreach ($request->file('additionalImages') as $imageFile) {
                    $path = $imageFile->store('images/products', 'public');
                    $productTemplate->images()->create([
                        'path' => $path,
                        'size' => $imageFile->getSize(),
                    ]);
                }
            }

            if (!empty($data['attributeLines'])) {
                foreach ($data['attributeLines'] as $line) {
                    if (empty($line['attribute_id']) || empty($line['values']) || !is_array($line['values'])) {
                        continue;
                    }

                    $attribute = Attribute::find($line['attribute_id']);
                    if (!$attribute) {
                        continue;
                    }

                    foreach ($line['values'] as $valueName) {
                        $valueName = trim((string) $valueName);
                        if ($valueName === '') {
                            continue;
                        }

                        AttributeValue::firstOrCreate([
                            'attribute_id' => $attribute->id,
                            'value' => $valueName,
                        ]);
                    }
                }
            }

            $createdVariantIds = [];

            if (!empty($data['generatedVariants'])) {
                foreach ($data['generatedVariants'] as $index => $variantData) {
                    $productProduct = $productTemplate->productProducts()->create([
                        'sku' => $variantData['sku'] ?? null,
                        'barcode' => $variantData['barcode'] ?? null,
                        'price' => $variantData['price'] ?? $productTemplate->price,
                        'cost_price' => $variantData['cost_price'] ?? 0,
                        'is_principal' => $index === 0,
                    ]);

                    $createdVariantIds[] = $productProduct->id;

                    if (!empty($variantData['attributes']) && is_array($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attributeId => $valueName) {
                            $attributeValue = AttributeValue::where('attribute_id', $attributeId)
                                ->where('value', $valueName)
                                ->first();

                            if ($attributeValue) {
                                $productProduct->attributeValues()->syncWithoutDetaching([$attributeValue->id]);
                            }
                        }
                    }
                }
            }

            if (empty($createdVariantIds)) {
                $productTemplate->productProducts()->create([
                    'sku' => $data['sku'] ?? null,
                    'barcode' => $data['barcode'] ?? null,
                    'price' => $data['price'],
                    'cost_price' => 0,
                    'is_principal' => true,
                ]);
            }
            return $productTemplate;
        });

        $productTemplate->load([
            'category',
            'mainImage',
            'images',
            'productProducts.attributeValues.attribute',
        ]);
        $productTemplate->append(['image', 'sku', 'barcode']);

        return response()->json([
            'data' => $productTemplate,
        ], 201);
    }

    public function update(Request $request, ProductTemplate $productTemplate)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'nullable|boolean',
            'is_pos_visible' => 'nullable|boolean',
            'tracks_inventory' => 'nullable|boolean',
            'sku' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:10240',
            'attributeLines' => 'nullable|array',
            'generatedVariants' => 'nullable|array',
            'additionalImages.*' => 'nullable|image|max:10240',
            'existingImageIds' => 'nullable|array',
        ], [], [
            'category_id' => 'categoría',
        ]);

        DB::transaction(function () use ($request, $productTemplate, $data) {
            $productTemplate->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'category_id' => $data['category_id'],
                'is_active' => $data['is_active'] ?? $productTemplate->is_active,
                'is_pos_visible' => $data['is_pos_visible'] ?? $productTemplate->is_pos_visible,
                'tracks_inventory' => $data['tracks_inventory'] ?? $productTemplate->tracks_inventory,
            ]);

            $mainImageId = null;

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/products', 'public');
                $mainImage = $productTemplate->images()->oldest()->first();

                if ($mainImage) {
                    Storage::disk('public')->delete($mainImage->path);
                    $mainImage->update(['path' => $path]);
                    $mainImageId = $mainImage->id;
                } else {
                    $newImage = $productTemplate->images()->create(['path' => $path]);
                    $mainImageId = $newImage->id;
                }
            } else {
                $mainImage = $productTemplate->images()->oldest()->first();
                if ($mainImage) {
                    $mainImageId = $mainImage->id;
                }
            }

            if ($request->has('existingImageIds')) {
                $existingIds = $request->input('existingImageIds', []);
                $productTemplate->images()
                    ->whereNotIn('id', $existingIds)
                    ->when($mainImageId, function ($query) use ($mainImageId) {
                        return $query->where('id', '!=', $mainImageId);
                    })
                    ->each(function ($image) {
                        Storage::disk('public')->delete($image->path);
                        $image->delete();
                    });
            }

            if ($request->hasFile('additionalImages')) {
                foreach ($request->file('additionalImages') as $imageFile) {
                    $path = $imageFile->store('images/products', 'public');
                    $productTemplate->images()->create([
                        'path' => $path,
                        'size' => $imageFile->getSize(),
                    ]);
                }
            }

            if (!empty($data['attributeLines'])) {
                foreach ($data['attributeLines'] as $line) {
                    if (empty($line['attribute_id']) || empty($line['values']) || !is_array($line['values'])) {
                        continue;
                    }

                    $attribute = Attribute::find($line['attribute_id']);
                    if (!$attribute) {
                        continue;
                    }

                    foreach ($line['values'] as $valueName) {
                        $valueName = trim((string) $valueName);
                        if ($valueName === '') {
                            continue;
                        }

                        AttributeValue::firstOrCreate([
                            'attribute_id' => $attribute->id,
                            'value' => $valueName,
                        ]);
                    }
                }
            }

            if (!empty($data['generatedVariants'])) {
                $existingVariants = $productTemplate->productProducts()->with('attributeValues')->get();
                $processedIds = [];
                $signatureMap = [];

                foreach ($existingVariants as $variant) {
                    $attributes = [];
                    foreach ($variant->attributeValues as $av) {
                        $attributes[$av->attribute_id] = $av->value;
                    }
                    ksort($attributes);
                    $signatureMap[json_encode($attributes)] = $variant;
                }

                foreach ($data['generatedVariants'] as $variantData) {
                    $attributes = [];
                    if (!empty($variantData['attributes']) && is_array($variantData['attributes'])) {
                        $attributes = $variantData['attributes'];
                        ksort($attributes);
                    }
                    $signature = json_encode($attributes);

                    $existing = $signatureMap[$signature] ?? null;

                    if ($existing) {
                        $existing->update([
                            'sku' => $variantData['sku'] ?? null,
                            'barcode' => $variantData['barcode'] ?? null,
                            'price' => $variantData['price'] ?? $productTemplate->price,
                            'cost_price' => $variantData['cost_price'] ?? $existing->cost_price,
                        ]);
                        $processedIds[] = $existing->id;
                    } else {
                        $productProduct = $productTemplate->productProducts()->create([
                            'sku' => $variantData['sku'] ?? null,
                            'barcode' => $variantData['barcode'] ?? null,
                            'price' => $variantData['price'] ?? $productTemplate->price,
                            'cost_price' => $variantData['cost_price'] ?? 0,
                            'is_principal' => false,
                        ]);

                        if (!empty($variantData['attributes']) && is_array($variantData['attributes'])) {
                            foreach ($variantData['attributes'] as $attributeId => $valueName) {
                                $attributeValue = AttributeValue::where('attribute_id', $attributeId)
                                    ->where('value', $valueName)
                                    ->first();

                                if ($attributeValue) {
                                    $productProduct->attributeValues()->syncWithoutDetaching([$attributeValue->id]);
                                }
                            }
                        }

                        $processedIds[] = $productProduct->id;
                    }
                }

                $productTemplate->productProducts()->whereNotIn('id', $processedIds)->delete();

                $remaining = $productTemplate->productProducts()->get();
                if ($remaining->isNotEmpty() && $remaining->where('is_principal', true)->isEmpty()) {
                    $remaining->first()->update(['is_principal' => true]);
                }
            } else {
                $existingVariant = $productTemplate->productProducts()->first();
                if ($existingVariant) {
                    $existingVariant->update([
                        'sku' => $data['sku'] ?? null,
                        'barcode' => $data['barcode'] ?? null,
                        'price' => $data['price'],
                        'is_principal' => true,
                    ]);
                } else {
                    $productTemplate->productProducts()->create([
                        'sku' => $data['sku'] ?? null,
                        'barcode' => $data['barcode'] ?? null,
                        'price' => $data['price'],
                        'cost_price' => 0,
                        'is_principal' => true,
                    ]);
                }
            }
        });

        $productTemplate->load([
            'category',
            'mainImage',
            'images',
            'productProducts.attributeValues.attribute',
        ]);
        $productTemplate->append(['image', 'sku', 'barcode']);

        return response()->json([
            'data' => $productTemplate,
        ]);
    }

    public function destroy(ProductTemplate $productTemplate)
    {
        if ($productTemplate->productProducts()->exists()) {
            $productTemplate->productProducts()->delete();
        }

        $productTemplate->images()->each(function ($image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        });

        $productTemplate->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function toggleStatus(ProductTemplate $productTemplate)
    {
        $productTemplate->update([
            'is_active' => ! $productTemplate->is_active,
        ]);

        return response()->json([
            'data' => $productTemplate->fresh()->load('category'),
        ]);
    }
}
