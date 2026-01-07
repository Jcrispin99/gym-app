<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\ProductTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request)
    {
        $query = ProductTemplate::with(['productProducts', 'mainImage', 'category']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
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

        $products = $query->latest()->paginate(20);
        $products->getCollection()->each->append(['image', 'sku', 'barcode']);

        return Inertia::render('Products/Index', compact('products'));
    }

    /**
     * Show the form for creating a new product
     */
    public function create()
    {
        $categories = Category::with('parent')->get();
        $attributes = Attribute::with('attributeValues')->get();

        // Debug
        \Log::info('ProductController@create - Categories count: '.$categories->count());
        \Log::info('ProductController@create - Attributes count: '.$attributes->count());
        \Log::info('ProductController@create - Attributes: '.json_encode($attributes));

        return Inertia::render('Products/CreateEdit', compact('categories', 'attributes'));
    }

    /**
     * Store a newly created product with its variants
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'boolean',
            'sku' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:10240',
            'attributeLines' => 'nullable|array',
            'generatedVariants' => 'nullable|array',
            'additionalImages.*' => 'nullable|image|max:10240',
        ], [], [
            'category_id' => 'categoría',
        ]);

        DB::transaction(function () use ($request, $data) {
            // 1. Create product template
            $product = ProductTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'category_id' => $data['category_id'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            // 2. Save main image
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/products', 'public');
                $product->images()->create([
                    'path' => $path,
                ]);
            }

            // 3. Save additional images
            if ($request->hasFile('additionalImages')) {
                foreach ($request->file('additionalImages') as $imageFile) {
                    $path = $imageFile->store('images/products', 'public');
                    $product->images()->create([
                        'path' => $path,
                        'size' => $imageFile->getSize(),
                    ]);
                }
            }

            // 4. Create AttributeValues if they don't exist (firstOrCreate)
            if (! empty($data['attributeLines'])) {
                foreach ($data['attributeLines'] as $line) {
                    if (empty($line['attribute_id']) || empty($line['values'])) {
                        continue;
                    }

                    $attribute = Attribute::find($line['attribute_id']);
                    if (! $attribute) {
                        continue;
                    }

                    foreach ($line['values'] as $valueName) {
                        AttributeValue::firstOrCreate([
                            'attribute_id' => $attribute->id,
                            'value' => $valueName,
                        ]);
                    }
                }
            }

            // 5A. If there are generated variants:
            if (! empty($data['generatedVariants'])) {
                foreach ($data['generatedVariants'] as $index => $variantData) {
                    $productProduct = $product->productProducts()->create([
                        'sku' => $variantData['sku'] ?? null,
                        'barcode' => $variantData['barcode'] ?? null,
                        'price' => $variantData['price'] ?? $product->price,
                        'stock' => $variantData['stock'] ?? 0,
                        'is_principal' => $index === 0,
                    ]);

                    if (! empty($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attributeId => $valueName) {
                            $attributeValue = AttributeValue::where('attribute_id', $attributeId)
                                ->where('value', $valueName)
                                ->first();

                            if ($attributeValue) {
                                $productProduct->attributeValues()->attach($attributeValue->id);
                            }
                        }
                    }
                }
            } else {
                // 5B. If NO variants (simple product):
                $product->productProducts()->create([
                    'sku' => $data['sku'] ?? null,
                    'barcode' => $data['barcode'] ?? null,
                    'price' => $data['price'],
                    'stock' => 0,
                    'is_principal' => true,
                ]);
            }
        });

        return redirect()->route('products.index')
            ->with('success', 'Producto creado exitosamente');
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        $product->load([
            'productProducts' => function ($query) {
                $query->orderBy('is_principal', 'desc');
            },
            'productProducts.attributeValues',
            'images',
        ]);

        return Inertia::render('Products/Show', [
            'product' => $product,
        ]);
    }

    /**
     * Show the form for editing the specified product
     */
    public function edit(ProductTemplate $product)
    {
        $product->load([
            'productProducts' => function ($query) {
                $query->orderBy('is_principal', 'desc');
            },
            'productProducts.attributeValues',
            'images',
        ]);

        // Ensure there's a principal variant
        if ($product->productProducts->isNotEmpty()) {
            $hasPrincipal = $product->productProducts->where('is_principal', true)->isNotEmpty();
            if (! $hasPrincipal) {
                $firstVariant = $product->productProducts->first();
                $firstVariant->update(['is_principal' => true]);
                $product->load([
                    'productProducts' => function ($query) {
                        $query->orderBy('is_principal', 'desc');
                    },
                    'productProducts.attributeValues',
                ]);
            }
        }

        // Get activity log
        $activities = Activity::forSubject($product)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        $categories = Category::with('parent')->get();
        $attributes = Attribute::with('attributeValues')->get();
        $product->append(['image', 'sku', 'barcode']);

        return Inertia::render('Products/CreateEdit', compact('product', 'categories', 'attributes', 'activities'));
    }

    /**
     * Update the specified product and its variants
     */
    public function update(Request $request, ProductTemplate $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'boolean',
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

        DB::transaction(function () use ($request, $product, $data) {
            // 1. Update product template
            $product->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'category_id' => $data['category_id'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $mainImageId = null;

            // 2. Handle main image (replace or create)
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/products', 'public');
                $mainImage = $product->images()->first();

                if ($mainImage) {
                    Storage::disk('public')->delete($mainImage->path);
                    $mainImage->update(['path' => $path]);
                    $mainImageId = $mainImage->id;
                } else {
                    $newImage = $product->images()->create(['path' => $path]);
                    $mainImageId = $newImage->id;
                }
            } else {
                $mainImage = $product->images()->first();
                if ($mainImage) {
                    $mainImageId = $mainImage->id;
                }
            }

            // 3. Delete additional images not sent (existingImageIds)
            if ($request->has('existingImageIds')) {
                $existingIds = $request->input('existingImageIds', []);
                $product->images()
                    ->whereNotIn('id', $existingIds)
                    ->when($mainImageId, function ($query) use ($mainImageId) {
                        return $query->where('id', '!=', $mainImageId);
                    })
                    ->each(function ($image) {
                        Storage::disk('public')->delete($image->path);
                        $image->delete();
                    });
            }

            // 4. Add new additional images
            if ($request->hasFile('additionalImages')) {
                foreach ($request->file('additionalImages') as $imageFile) {
                    $path = $imageFile->store('images/products', 'public');
                    $product->images()->create([
                        'path' => $path,
                        'size' => $imageFile->getSize(),
                    ]);
                }
            }

            // 5. Create new AttributeValues if they don't exist
            if (! empty($data['attributeLines'])) {
                foreach ($data['attributeLines'] as $line) {
                    if (empty($line['attribute_id']) || empty($line['values'])) {
                        continue;
                    }

                    $attribute = Attribute::find($line['attribute_id']);
                    if (! $attribute) {
                        continue;
                    }

                    foreach ($line['values'] as $valueName) {
                        AttributeValue::firstOrCreate([
                            'attribute_id' => $attribute->id,
                            'value' => $valueName,
                        ]);
                    }
                }
            }

            // 6A. If there are generated variants:
            if (! empty($data['generatedVariants'])) {
                $existingVariants = $product->productProducts()->with('attributeValues')->get();
                $processedIds = [];

                foreach ($data['generatedVariants'] as $variantData) {
                    // Create "signature" of the variant (JSON of sorted attributes)
                    $attributeKey = '';
                    if (! empty($variantData['attributes'])) {
                        ksort($variantData['attributes']);
                        $attributeKey = json_encode($variantData['attributes']);
                    }

                    // Search for existing variant with that signature
                    $existingVariant = null;
                    foreach ($existingVariants as $ev) {
                        $evAttributes = [];
                        if ($ev->attributeValues) {
                            foreach ($ev->attributeValues as $av) {
                                $evAttributes[$av->attribute_id] = $av->value;
                            }
                            ksort($evAttributes);
                        }
                        $evKey = json_encode($evAttributes);

                        if ($evKey === $attributeKey) {
                            $existingVariant = $ev;
                            break;
                        }
                    }

                    if ($existingVariant) {
                        // UPDATE existing variant
                        $existingVariant->update([
                            'sku' => $variantData['sku'] ?? null,
                            'barcode' => $variantData['barcode'] ?? null,
                            'price' => $variantData['price'] ?? $product->price,
                        ]);
                        $processedIds[] = $existingVariant->id;
                    } else {
                        // CREATE new variant
                        $hasPrincipalExisting = $existingVariants->where('is_principal', true)->isNotEmpty();
                        $shouldBePrincipal = count($processedIds) === 0 && ! $hasPrincipalExisting;

                        $productProduct = $product->productProducts()->create([
                            'sku' => $variantData['sku'] ?? null,
                            'barcode' => $variantData['barcode'] ?? null,
                            'price' => $variantData['price'] ?? $product->price,
                            'stock' => $variantData['stock'] ?? 0,
                            'is_principal' => $shouldBePrincipal,
                        ]);

                        if (! empty($variantData['attributes'])) {
                            foreach ($variantData['attributes'] as $attributeId => $valueName) {
                                $attributeValue = AttributeValue::where('attribute_id', $attributeId)
                                    ->where('value', $valueName)
                                    ->first();

                                if ($attributeValue) {
                                    $productProduct->attributeValues()->attach($attributeValue->id);
                                }
                            }
                        }

                        $processedIds[] = $productProduct->id;
                    }
                }

                // DELETE variants not processed (they no longer exist)
                $product->productProducts()->whereNotIn('id', $processedIds)->delete();

            } else {
                // 6B. If NO variants (simple product):
                $existingVariant = $product->productProducts()->first();
                if ($existingVariant) {
                    $existingVariant->update([
                        'sku' => $data['sku'] ?? null,
                        'barcode' => $data['barcode'] ?? null,
                        'price' => $data['price'],
                        'is_principal' => true,
                    ]);
                } else {
                    $product->productProducts()->create([
                        'sku' => $data['sku'] ?? null,
                        'barcode' => $data['barcode'] ?? null,
                        'price' => $data['price'],
                        'stock' => 0,
                        'is_principal' => true,
                    ]);
                }
            }
        });

        return redirect()->route('products.edit', $product)
            ->with('success', 'Producto actualizado exitosamente');
    }

    /**
     * Remove the specified product and its variants
     */
    public function destroy(ProductTemplate $product)
    {
        $product->productProducts()->delete();
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Producto eliminado exitosamente');
    }

    /**
     * Toggle product active status
     */
    public function toggleStatus(ProductTemplate $product)
    {
        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        return back()->with('success', 'Estado actualizado exitosamente');
    }
}
