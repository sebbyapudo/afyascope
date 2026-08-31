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
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_catalog_item_id')->constrained()->restrictOnDelete();
            $table->string('description', 150);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->unique(['bill_id', 'service_catalog_item_id']);
        });

        DB::statement('ALTER TABLE bill_items ADD CONSTRAINT bill_items_amount_positive_check CHECK (`amount_minor` > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};
