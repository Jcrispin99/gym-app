<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\ProductProduct;

class KardexService
{
    /**
     * Obtiene el último registro de inventario para una variante en un almacén específico
     */
    public function getLastRecord($productProductId, $warehouseId)
    {
        $lastRecord = Inventory::where('product_product_id', $productProductId)
            ->where('warehouse_id', $warehouseId)
            ->latest()
            ->first();

        return [
            'quantity' => $lastRecord?->quantity_balance ?? 0,
            'cost' => $lastRecord?->cost_balance ?? 0,
            'total' => $lastRecord?->total_balance ?? 0,
            'date' => $lastRecord?->created_at ?? null,
        ];
    }

    /**
     * Registra una entrada al inventario (compras, transferencias entrantes, etc.)
     * 
     * @param mixed $model Modelo polimórfico (Purchase, Transfer, etc.)
     * @param array $variant Datos de la variante ['id', 'quantity', 'price', 'subtotal']
     * @param int $warehouseId ID del almacén
     * @param string $detail Descripción del movimiento
     */
    public function registerEntry($model, array $variant, $warehouseId, $detail)
    {
        $lastRecord = $this->getLastRecord($variant['id'], $warehouseId);

        $qty = (float) ($variant['quantity'] ?? 0);
        
        // Normalizar costo unitario: si viene "subtotal" (monto base sin IGV) y es > 0, usarlo.
        $baseSubtotal = $variant['subtotal'] ?? null;
        $unitCost = ($baseSubtotal !== null && (float) $baseSubtotal > 0 && $qty > 0)
            ? ((float) $baseSubtotal / $qty)
            : (float) ($variant['price'] ?? 0);

        // Calcular nuevo balance usando promedio ponderado
        $newQuantityBalance = $lastRecord['quantity'] + $qty;
        $newTotalBalance = $lastRecord['total'] + ($qty * $unitCost);
        $newCostBalance = $newTotalBalance / ($newQuantityBalance ?: 1);

        // Crear registro de inventario
        $model->inventories()->create([
            'detail' => $detail,
            'quantity_in' => $qty,
            'cost_in' => $unitCost,
            'total_in' => $qty * $unitCost,
            'quantity_balance' => $newQuantityBalance,
            'cost_balance' => $newCostBalance,
            'total_balance' => $newTotalBalance,
            'product_product_id' => $variant['id'],
            'warehouse_id' => $warehouseId,
        ]);

        // Actualizar stock en la variante (este campo será calculado de inventories)
        // ProductProduct::where('id', $variant['id'])->increment('stock', $qty);
    }

    /**
     * Registra una salida del inventario (ventas, transferencias salientes, etc.)
     * 
     * @param mixed $model Modelo polimórfico (Sale, Transfer, etc.)
     * @param array $variant Datos de la variante ['id', 'quantity']
     * @param int $warehouseId ID del almacén
     * @param string $detail Descripción del movimiento
     */
    public function registerExit($model, array $variant, $warehouseId, $detail)
    {
        $lastRecord = $this->getLastRecord($variant['id'], $warehouseId);
        
        $qty = (float) ($variant['quantity'] ?? 0);
        
        // Calcular nuevo balance
        $newQuantityBalance = $lastRecord['quantity'] - $qty;
        $newTotalBalance = $lastRecord['total'] - ($qty * $lastRecord['cost']);
        $newCostBalance = $newTotalBalance / ($newQuantityBalance ?: 1);

        // Crear registro de inventario
        $model->inventories()->create([
            'detail' => $detail,
            'quantity_out' => $qty,
            'cost_out' => $lastRecord['cost'],
            'total_out' => $qty * $lastRecord['cost'],
            'quantity_balance' => $newQuantityBalance,
            'cost_balance' => $newCostBalance,
            'total_balance' => $newTotalBalance,
            'product_product_id' => $variant['id'],
            'warehouse_id' => $warehouseId,
        ]);

        // Actualizar stock en la variante (este campo será calculado de inventories)
        // ProductProduct::where('id', $variant['id'])->decrement('stock', $qty);
    }

    /**
     * Obtiene el kardex completo de una variante en un almacén
     * 
     * @param int $productProductId ID de la variante
     * @param int $warehouseId ID del almacén
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getKardex($productProductId, $warehouseId)
    {
        return Inventory::where('product_product_id', $productProductId)
            ->where('warehouse_id', $warehouseId)
            ->with(['inventoryable', 'warehouse'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Obtiene el stock actual de una variante en un almacén específico
     * 
     * @param int $productProductId ID de la variante
     * @param int $warehouseId ID del almacén
     * @return int
     */
    public function getCurrentStock($productProductId, $warehouseId): int
    {
        $lastRecord = $this->getLastRecord($productProductId, $warehouseId);
        return (int) $lastRecord['quantity'];
    }

    /**
     * Validar si hay suficiente stock para una salida
     * 
     * @param int $productProductId ID de la variante
     * @param int $warehouseId ID del almacén
     * @param float $requiredQuantity Cantidad requerida
     * @return bool
     */
    public function hasEnoughStock($productProductId, $warehouseId, $requiredQuantity): bool
    {
        $currentStock = $this->getCurrentStock($productProductId, $warehouseId);
        return $currentStock >= $requiredQuantity;
    }

    /**
     * Registra un ajuste de inventario (corrección de stock)
     * 
     * @param mixed $model Modelo polimórfico (Adjustment, etc.)
     * @param array $variant Datos de la variante ['id', 'quantity', 'reason']
     * @param int $warehouseId ID del almacén
     * @param string $detail Descripción del ajuste
     */
    public function registerAdjustment($model, array $variant, $warehouseId, $detail)
    {
        $lastRecord = $this->getLastRecord($variant['id'], $warehouseId);
        $qty = (float) ($variant['quantity'] ?? 0);

        // Determinar si es ajuste positivo o negativo
        if ($qty >= 0) {
            // Ajuste positivo (entrada)
            $this->registerEntry($model, $variant, $warehouseId, $detail);
        } else {
            // Ajuste negativo (salida)
            $variant['quantity'] = abs($qty);
            $this->registerExit($model, $variant, $warehouseId, $detail);
        }
    }
}
