<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'only_active' => 'nullable|boolean',
        ]);

        $onlyActive = filter_var($validated['only_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Category::query()->with('parent')->orderBy('name');

        if (!empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($qBuilder) use ($q) {
                $qBuilder->where('name', 'like', '%' . $q . '%')
                    ->orWhere('slug', 'like', '%' . $q . '%')
                    ->orWhere('full_name', 'like', '%' . $q . '%');
            });
        }

        if (array_key_exists('parent_id', $validated)) {
            $query->where('parent_id', $validated['parent_id']);
        }

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(Category $category)
    {
        $category->load(['parent', 'children']);

        return response()->json([
            'data' => $category,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'slug' => 'required|string|max:255|unique:categories,slug',
            'full_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'full_name' => $validated['full_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ])->load('parent');

        return response()->json([
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'slug' => 'required|string|max:255|unique:categories,slug,' . $category->id,
            'full_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($validated['parent_id']) && (int) $validated['parent_id'] === (int) $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Una categoría no puede ser su propio padre.',
            ]);
        }

        $category->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'full_name' => $validated['full_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? $category->is_active,
        ]);

        return response()->json([
            'data' => $category->fresh()->load('parent'),
        ]);
    }

    public function destroy(Category $category)
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'No se puede eliminar una categoría con subcategorías.',
            ]);
        }

        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'No se puede eliminar una categoría con productos asociados.',
            ]);
        }

        $category->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function toggleStatus(Category $category)
    {
        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        return response()->json([
            'data' => $category->fresh()->load('parent'),
        ]);
    }
}

