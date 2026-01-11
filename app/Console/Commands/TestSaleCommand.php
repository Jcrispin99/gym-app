<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\ProductProduct;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\Warehouse;
use App\Services\SequenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestSaleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sale {--setup : Crear inventarios de prueba}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testea la creación de ventas y verifica inventarios';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== TEST DE VENTAS ===');
        $this->newLine();

        // Si se pasa --setup, crear inventarios de prueba
        if ($this->option('setup')) {
            $this->setupInventories();
            return;
        }

        // 1. Verificar datos necesarios
        $this->info('1. Verificando datos necesarios...');
        
        $warehouse = Warehouse::first();
        $customer = Partner::customers()->first();
        $products = ProductProduct::limit(3)->get();
        $tax = Tax::active()->first();
        $journal = Journal::where('type', 'sale')->first();

        if (!$warehouse) {
            $this->error('❌ No hay almacenes. Crea uno primero.');
            return;
        }

        if (!$journal) {
            $this->error('❌ No hay journal de tipo "sale". Crea uno primero.');
            return;
        }

        $this->info("✅ Warehouse: {$warehouse->name}");
        $this->info("✅ Journal: {$journal->name}");
        $this->info("✅ Tax: " . ($tax ? "{$tax->name} ({$tax->rate_percent}%)" : 'Sin impuesto'));
        $this->info("✅ Customer: " . ($customer ? $customer->display_name : 'Sin cliente (venta anónima)'));
        $this->info("✅ Productos disponibles: {$products->count()}");
        $this->newLine();

        // 2. Verificar inventarios (kardex)
        $this->info('2. Verificando stock disponible...');
        
        // Calcular stock actual desde kardex
        $stockQuery = DB::table('inventories')
            ->select([
                'product_product_id',
                DB::raw('SUM(quantity_in - quantity_out) as stock_actual')
            ])
            ->where('warehouse_id', $warehouse->id)
            ->groupBy('product_product_id')
            ->having('stock_actual', '>', 0);
        
        $stockCount = $stockQuery->count();
        
        if ($stockCount === 0) {
            $this->warn("⚠️  No hay stock disponible. Ejecuta: php artisan test:sale --setup");
            $this->newLine();
            return;
        }

        $this->info("✅ Productos con stock: {$stockCount}");
        
        // Mostrar algunos productos con stock
        $stockItems = $stockQuery->limit(5)->get();

        $this->table(
            ['Product ID', 'Stock Actual'],
            $stockItems->map(fn($item) => [
                $item->product_product_id,
                $item->stock_actual
            ])
        );
        $this->newLine();

        // 3. Crear venta de prueba
        $this->info('3. Creando venta de prueba...');

        try {
            // Obtener productos con stock
            $productsWithStock = DB::table('inventories')
                ->select([
                    'product_product_id',
                    DB::raw('SUM(quantity_in - quantity_out) as stock_actual')
                ])
                ->where('warehouse_id', $warehouse->id)
                ->groupBy('product_product_id')
                ->having('stock_actual', '>', 0)
                ->limit(2)
                ->get();
            
            if ($productsWithStock->isEmpty()) {
                $this->error('❌ No hay productos con stock disponible.');
                return;
            }

            DB::beginTransaction();

            // Generar número de documento
            $numberParts = SequenceService::getNextParts($journal->id);

            // Crear venta
            $sale = Sale::create([
                'serie' => $numberParts['serie'],
                'correlative' => $numberParts['correlative'],
                'journal_id' => $journal->id,
                'partner_id' => $customer?->id,
                'warehouse_id' => $warehouse->id,
                'company_id' => $warehouse->company_id,
                'user_id' => 1, // Usuario admin
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]);

            // Agregar productos
            $subtotal = 0;
            $totalTax = 0;

            foreach ($productsWithStock as $stockItem) {
                $quantity = min(2, $stockItem->stock_actual); // Vender máximo 2 unidades
                $price = 50.00; // Precio de ejemplo
                $lineSubtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;

                $sale->products()->create([
                    'product_product_id' => $stockItem->product_product_id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'tax_id' => $tax?->id,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $totalTax += $taxAmount;
            }

            // Actualizar totales
            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);

            DB::commit();

            $this->newLine();
            $this->info("✅ Venta creada exitosamente!");
            $this->info("📄 Documento: {$sale->document_number}");
            $this->info("💰 Subtotal: S/ {$sale->subtotal}");
            $this->info("📊 Impuesto: S/ {$sale->tax_amount}");
            $this->info("💵 Total: S/ {$sale->total}");
            $this->info("📦 Líneas: {$sale->products->count()}");
            $this->info("🏪 Estado: {$sale->status}");
            $this->newLine();

            // Mostrar líneas
            $this->table(
                ['Producto', 'Cantidad', 'Precio', 'Subtotal'],
                $sale->products->map(fn($line) => [
                    $line->productProduct->sku ?? 'N/A',
                    $line->quantity,
                    'S/ ' . number_format($line->price, 2),
                    'S/ ' . number_format($line->subtotal, 2),
                ])
            );

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error al crear venta: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }

    /**
     * Crear inventarios de prueba (movimientos de kardex)
     */
    protected function setupInventories()
    {
        $this->info('=== SETUP: Creando stock inicial (kardex) ===');
        $this->newLine();

        $warehouse = Warehouse::first();
        if (!$warehouse) {
            $this->error('❌ No hay almacenes. Crea uno primero.');
            return;
        }

        $products = ProductProduct::limit(10)->get();
        if ($products->isEmpty()) {
            $this->error('❌ No hay productos. Ejecuta los seeders primero.');
            return;
        }

        $created = 0;
        foreach ($products as $product) {
            // Verificar si ya tiene movimientos
            $hasMovements = Inventory::where('warehouse_id', $warehouse->id)
                ->where('product_product_id', $product->id)
                ->exists();

            if (!$hasMovements) {
                $quantity = rand(10, 100);
                $cost = $product->cost_price ?? rand(10, 50);

                // Crear movimiento de entrada inicial (ajuste de inventario)
                Inventory::create([
                    'warehouse_id' => $warehouse->id,
                    'product_product_id' => $product->id,
                    'inventoryable_type' => 'App\Models\Warehouse', // Ajuste inicial
                    'inventoryable_id' => $warehouse->id,
                    'detail' => 'Stock inicial de prueba',
                    'quantity_in' => $quantity,
                    'cost_in' => $cost,
                    'total_in' => $quantity * $cost,
                    'quantity_out' => 0,
                    'cost_out' => 0,
                    'total_out' => 0,
                    'quantity_balance' => $quantity,
                    'cost_balance' => $cost,
                    'total_balance' => $quantity * $cost,
                ]);
                $created++;
            }
        }

        $this->info("✅ Creados {$created} movimientos de stock inicial en {$warehouse->name}");
        $this->newLine();
        
        // Mostrar resumen de stock actual
        $this->info('📦 Stock disponible:');
        $stock = DB::table('inventories')
            ->select([
                'product_product_id',
                DB::raw('SUM(quantity_in - quantity_out) as stock_actual')
            ])
            ->where('warehouse_id', $warehouse->id)
            ->groupBy('product_product_id')
            ->having('stock_actual', '>', 0)
            ->limit(5)
            ->get();

        if ($stock->isNotEmpty()) {
            $this->table(
                ['Product ID', 'Stock'],
                $stock->map(fn($s) => [$s->product_product_id, $s->stock_actual])
            );
        }

        $this->newLine();
        $this->info("💡 Ahora ejecuta: php artisan test:sale");
    }
}
