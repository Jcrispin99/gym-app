<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WarehouseApiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
        ]);

        $query = Warehouse::query()->with('company')->latest();

        if (! empty($validated['company_id'])) {
            $query->where('company_id', (int) $validated['company_id']);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(Warehouse $warehouse)
    {
        $warehouse->load('company');

        return response()->json([
            'data' => $warehouse,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $warehouse = Warehouse::create($validated)->load('company');

        return response()->json([
            'data' => $warehouse,
        ], 201);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'data' => $warehouse->fresh()->load('company'),
        ]);
    }

    public function destroy(Warehouse $warehouse)
    {
        try {
            $warehouse->delete();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'warehouse' => 'No se pudo eliminar el almacén. Puede tener registros relacionados.',
            ]);
        }

        return response()->json([
            'ok' => true,
        ]);
    }
}
