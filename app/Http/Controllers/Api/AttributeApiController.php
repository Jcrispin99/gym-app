<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttributeApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'only_active' => 'nullable|boolean',
            'with_values' => 'nullable|boolean',
        ]);

        $withValues = filter_var($validated['with_values'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $onlyActive = filter_var($validated['only_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Attribute::query()->orderBy('name');

        if (!empty($validated['q'])) {
            $query->search($validated['q']);
        }

        if ($onlyActive) {
            $query->active();
        }

        if ($withValues) {
            $query->withValues();
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(Attribute $attribute)
    {
        $attribute->load('attributeValues');

        return response()->json([
            'data' => $attribute,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name',
            'is_active' => 'nullable|boolean',
            'values' => 'required|array|min:1',
            'values.*' => 'required|string|max:255',
        ]);

        $values = collect($validated['values'])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values();

        if ($values->isEmpty()) {
            throw ValidationException::withMessages([
                'values' => 'Debes agregar al menos un valor.',
            ]);
        }

        $attribute = Attribute::create([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        foreach ($values as $value) {
            $attribute->attributeValues()->create([
                'value' => $value,
            ]);
        }

        return response()->json([
            'data' => $attribute->fresh()->load('attributeValues'),
        ], 201);
    }

    public function update(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name,' . $attribute->id,
            'is_active' => 'nullable|boolean',
            'values' => 'required|array|min:1',
            'values.*' => 'required|string|max:255',
        ]);

        $values = collect($validated['values'])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values();

        if ($values->isEmpty()) {
            throw ValidationException::withMessages([
                'values' => 'Debes agregar al menos un valor.',
            ]);
        }

        $attribute->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $attribute->attributeValues()->delete();

        foreach ($values as $value) {
            $attribute->attributeValues()->create([
                'value' => $value,
            ]);
        }

        return response()->json([
            'data' => $attribute->fresh()->load('attributeValues'),
        ]);
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function toggleStatus(Attribute $attribute)
    {
        $attribute->update([
            'is_active' => ! $attribute->is_active,
        ]);

        return response()->json([
            'data' => $attribute->fresh()->load('attributeValues'),
        ]);
    }
}

