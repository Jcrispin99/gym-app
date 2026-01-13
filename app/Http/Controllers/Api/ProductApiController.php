<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductProduct;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    /**
     * Search products by name or SKU
     *
     * IMPORTANTE:
     * - Por defecto (require_stock=false): Muestra TODOS los productos sin filtrar por stock ni warehouse
     *   Esto es para COMPRAS, donde estamos AGREGANDO inventario
     * - Con require_stock=true: Filtra por warehouse y stock > 0
     *   Esto es para VENTAS/POS, donde necesitamos productos disponibles
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $limit = (int) $request->input('limit', 20);
        $warehouseId = $request->input('warehouse_id');
        $requireStock = $request->input('require_stock', false);
        $browse = filter_var($request->input('browse', false), FILTER_VALIDATE_BOOLEAN);
        $categoryId = $request->input('category_id');
        $onlyActive = filter_var($request->input('only_active', true), FILTER_VALIDATE_BOOLEAN);

        $productsQuery = ProductProduct::with(['template', 'attributeValues.attribute']);

        if ($onlyActive) {
            $productsQuery->whereHas('template', function ($q) {
                $q->where('is_active', true);
            });
        }

        if (!empty($categoryId)) {
            $productsQuery->whereHas('template', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        // Si no hay búsqueda, retornar los 10 más usados (basado en productables)
        if (empty($query) && !$browse) {
            $productsQuery->withCount('productables')
                ->orderBy('productables_count', 'desc')
                ->limit(10);
        } elseif (empty($query) && $browse) {
            $productsQuery
                ->orderByDesc('is_principal')
                ->orderBy(
                    \App\Models\ProductTemplate::select('name')
                        ->whereColumn('product_templates.id', 'product_products.product_template_id')
                        ->limit(1),
                )
                ->orderBy('id')
                ->limit($limit);
        } else {
            // Búsqueda por nombre, SKU, código de barras o atributos de variante
            $productsQuery->where(function ($q) use ($query) {
                $q->where('sku', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%")
                    ->orWhereHas('template', function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('attributeValues', function ($q) use ($query) {
                        $q->where('value', 'like', "%{$query}%");
                    });
            })->limit($limit);
        }

        // SOLO aplicar filtro de stock si se requiere (para VENTAS/POS)
        // Para COMPRAS, mostramos TODOS los productos sin importar stock
        if ($requireStock && $warehouseId) {
            $productsQuery->whereHas('inventories', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                    ->where('quantity_balance', '>', 0);
            });
        }

        $products = $productsQuery->get()
            ->map(function ($product) use ($warehouseId) {
                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => $product->template->name,
                    'display_name' => $product->display_name,
                    'price' => $product->price,
                    'cost_price' => $product->cost_price,
                    'category_id' => $product->template->category_id,
                    'stock' => $warehouseId
                        ? $product->getStockInWarehouse($warehouseId)
                        : $product->stock,
                    'attributes' => $product->attribute_string,
                ];
            });

        return response()->json($products);
    }

    /**
     * Get product details by ID
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $product = ProductProduct::with(['template', 'attributeValues.attribute'])
            ->findOrFail($id);

        return response()->json([
            'id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->template->name,
            'display_name' => $product->display_name,
            'price' => $product->price,
            'cost_price' => $product->cost_price,
            'stock' => $product->stock,
            'attributes' => $product->attribute_string,
        ]);
    }
}
