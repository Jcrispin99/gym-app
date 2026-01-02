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
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->onDelete('cascade')
                ->comment('Compañía matriz (null = matriz, valor = sucursal)');
            
            $table->string('branch_code', 10)
                ->nullable()
                ->unique()
                ->after('parent_id')
                ->comment('Código de sucursal (ej: SUC-001)');
            
            $table->boolean('is_main_office')
                ->default(false)
                ->after('branch_code')
                ->comment('¿Es casa matriz?');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'branch_code', 'is_main_office']);
        });
    }
};
