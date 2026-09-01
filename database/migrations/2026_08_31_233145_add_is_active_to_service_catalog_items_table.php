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
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('unit_price_minor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
