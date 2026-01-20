<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('sunat_status')
                ->default('pending')
                ->after('payment_status');
            $table->json('sunat_response')
                ->nullable()
                ->after('sunat_status');
            $table->timestamp('sunat_sent_at')
                ->nullable()
                ->after('sunat_response');

            $table->index('sunat_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['sunat_status']);
            $table->dropColumn(['sunat_status', 'sunat_response', 'sunat_sent_at']);
        });
    }
};
