<?php

namespace App\Observers;

use App\Models\ProductProduct;
use App\Models\Purchase;

class PurchaseObserver
{
    /**
     * Handle the Purchase "updated" event.
     * 
     * Cuando una compra se publica (status -> 'posted'), actualiza el cost_price
     * de cada variante de producto con el precio de compra registrado.
     */
    public function updated(Purchase $purchase): void
    {
        // Solo actuar si el status cambió a 'posted'
        if ($purchase->isDirty('status') && $purchase->status === 'posted') {
            // Actualizar cost_price de cada producto comprado
            foreach ($purchase->productables as $productable) {
                ProductProduct::where('id', $productable->product_product_id)
                    ->update(['cost_price' => $productable->price]);
            }
        }
    }
}
