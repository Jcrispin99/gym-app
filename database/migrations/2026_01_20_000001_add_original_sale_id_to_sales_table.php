<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('original_sale_id')
                ->nullable()
                ->after('company_id')
                ->constrained('sales')
                ->nullOnDelete();

            $table->index('original_sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['original_sale_id']);
            $table->dropIndex(['original_sale_id']);
            $table->dropColumn('original_sale_id');
        });
    }
};
