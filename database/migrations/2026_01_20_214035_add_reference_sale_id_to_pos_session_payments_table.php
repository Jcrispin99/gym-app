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
        Schema::table('pos_session_payments', function (Blueprint $table) {
            $table->foreignId('reference_sale_id')->nullable()->constrained('sales')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_session_payments', function (Blueprint $table) {
            $table->dropForeign(['reference_sale_id']);
            $table->dropColumn('reference_sale_id');
        });
    }
};
