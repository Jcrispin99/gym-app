<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_templates', function (Blueprint $table) {
            $table->boolean('is_pos_visible')->default(true)->after('is_active');
            $table->boolean('tracks_inventory')->default(true)->after('is_pos_visible');
            $table->index(['is_pos_visible', 'tracks_inventory']);
        });
    }

    public function down(): void
    {
        Schema::table('product_templates', function (Blueprint $table) {
            $table->dropIndex(['is_pos_visible', 'tracks_inventory']);
            $table->dropColumn(['is_pos_visible', 'tracks_inventory']);
        });
    }
};

