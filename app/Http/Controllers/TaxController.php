<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Tax::orderBy('is_default', 'desc')
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $taxes = $query->get();

        return Inertia::render('Taxes/Index', [
            'taxes' => $taxes,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Taxes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:taxes,name',
            'description' => 'nullable|string',
            'invoice_label' => 'nullable|string|max:255',
            'tax_type' => 'required|string',
            'affectation_type_code' => 'nullable|string|max:10',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'is_price_inclusive' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Si se marca como default, desmarcar otros defaults del mismo tipo
        if ($validated['is_default'] ?? false) {
            Tax::where('tax_type', $validated['tax_type'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        Tax::create($validated);

        return redirect()->route('taxes.index')
            ->with('success', 'Impuesto creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Tax $tax)
    {
        return Inertia::render('Taxes/Show', [
            'tax' => $tax,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tax $tax)
    {
        return Inertia::render('Taxes/Edit', [
            'tax' => $tax,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tax $tax)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:taxes,name,' . $tax->id,
            'description' => 'nullable|string',
            'invoice_label' => 'nullable|string|max:255',
            'tax_type' => 'required|string',
            'affectation_type_code' => 'nullable|string|max:10',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'is_price_inclusive' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Si se marca como default, desmarcar otros defaults del mismo tipo
        if ($validated['is_default'] ?? false) {
            Tax::where('tax_type', $validated['tax_type'])
                ->where('is_default', true)
                ->where('id', '!=', $tax->id)
                ->update(['is_default' => false]);
        }

        $tax->update($validated);

        return redirect()->route('taxes.index')
            ->with('success', 'Impuesto actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tax $tax)
    {
        // Verificar si el tax está siendo usado
        if ($tax->productables()->count() > 0) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar el impuesto porque está siendo usado en productos.');
        }

        $tax->delete();

        return redirect()->route('taxes.index')
            ->with('success', 'Impuesto eliminado exitosamente.');
    }

    /**
     * Toggle tax status
     */
    public function toggleStatus(Tax $tax)
    {
        $tax->update([
            'is_active' => !$tax->is_active
        ]);

        return redirect()->back()
            ->with('success', 'Estado actualizado exitosamente.');
    }
}
