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
        Schema::table('membership_freezes', function (Blueprint $table) {
            $table->integer('planned_days')->after('days_frozen')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_freezes', function (Blueprint $table) {
            $table->dropColumn('planned_days');
        });
    }
};
