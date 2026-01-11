<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            // Información del documento
            $table->string('serie');
            $table->string('correlative');
            $table->foreignId('journal_id')->constrained('journals')->onDelete('cascade');
            $table->timestamp('date')->useCurrent();

            // Relaciones
            $table->foreignId('partner_id')
                ->nullable()
                ->constrained('partners')
                ->onDelete('set null'); // Cliente (opcional)
            
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('pos_session_id')
                ->nullable()
                ->constrained('pos_sessions')
                ->onDelete('set null'); // Opcional si es venta manual

            $table->foreignId('user_id')->constrained('users'); // Vendedor

            // Montos
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);

            // Estados
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('posted');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('paid');

            // Notas adicionales
            $table->text('notes')->nullable();

            $table->timestamps();

            // Índices
            $table->index(['status']);
            $table->index(['payment_status']);
            $table->index(['date']);
            $table->index(['pos_session_id']);
            
            // Unicidad: serie+correlativo por compañía
            $table->unique(
                ['company_id', 'serie', 'correlative'], 
                'sale_unique_company_serie_corr'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
