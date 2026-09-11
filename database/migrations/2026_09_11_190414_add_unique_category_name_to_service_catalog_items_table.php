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
            $table->unique(['category', 'name'], 'service_catalog_items_category_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->dropUnique('service_catalog_items_category_name_unique');
        });
    }
};
