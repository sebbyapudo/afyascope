<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 20);
            $table->unsignedBigInteger('unit_price_minor');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE service_catalog_items ADD CONSTRAINT service_catalog_items_category_check CHECK (`category` IN ('consultation', 'procedure'))");
        DB::statement('ALTER TABLE service_catalog_items ADD CONSTRAINT service_catalog_items_price_positive_check CHECK (`unit_price_minor` > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_catalog_items');
    }
};
