<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('is_member')->default(false)->after('user_id');
            $table->index('is_member');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropIndex(['is_provider']);
            $table->dropColumn('is_provider');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('is_provider')->default(false)->after('is_customer');
            $table->index('is_provider');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropIndex(['is_member']);
            $table->dropColumn('is_member');
        });
    }
};

