<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttributeController extends Controller
{
    /**
     * Display a listing of attributes
     */
    public function index()
    {
        $attributes = Attribute::withValues()
            ->orderBy('name')
            ->get();

        return Inertia::render('Attributes/Index', [
            'attributes' => $attributes,
        ]);
    }

    /**
     * Show the form for creating a new attribute
     */
    public function create()
    {
        return Inertia::render('Attributes/Create');
    }

    /**
     * Store a newly created attribute with its values
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:attributes',
            'is_active' => 'boolean',
            'values' => 'required|array|min:1',
            'values.*' => 'required|string|max:255',
        ]);

        // Create attribute
        $attribute = Attribute::create([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Create attribute values
        foreach ($validated['values'] as $value) {
            if (!empty(trim($value))) {
                $attribute->attributeValues()->create([
                    'value' => trim($value),
                ]);
            }
        }

        return redirect()->route('attributes.index')
            ->with('success', 'Atributo creado exitosamente');
    }

    /**
     * Display the specified attribute
     */
    public function show(Attribute $attribute)
    {
        $attribute->load('attributeValues');

        return Inertia::render('Attributes/Show', [
            'attribute' => $attribute,
        ]);
    }

    /**
     * Show the form for editing the specified attribute
     */
    public function edit(Attribute $attribute)
    {
        $attribute->load('attributeValues');

        return Inertia::render('Attributes/Edit', [
            'attribute' => $attribute,
        ]);
    }

    /**
     * Update the specified attribute and its values
     */
    public function update(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name,' . $attribute->id,
            'is_active' => 'boolean',
            'values' => 'required|array|min:1',
            'values.*' => 'required|string|max:255',
        ]);

        // Update attribute
        $attribute->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Delete all existing values and create new ones
        $attribute->attributeValues()->delete();

        foreach ($validated['values'] as $value) {
            if (!empty(trim($value))) {
                $attribute->attributeValues()->create([
                    'value' => trim($value),
                ]);
            }
        }

        return back()->with('success', 'Atributo actualizado exitosamente');
    }

    /**
     * Remove the specified attribute and its values
     */
    public function destroy(Attribute $attribute)
    {
        // Values will be deleted automatically due to cascade
        $attribute->delete();

        return redirect()->route('attributes.index')
            ->with('success', 'Atributo eliminado exitosamente');
    }

    /**
     * Toggle attribute active status
     */
    public function toggleStatus(Attribute $attribute)
    {
        $attribute->update([
            'is_active' => !$attribute->is_active,
        ]);

        return back()->with('success', 'Estado actualizado exitosamente');
    }
}
