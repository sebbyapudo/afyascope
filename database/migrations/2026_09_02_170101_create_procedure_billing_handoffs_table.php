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
        Schema::create('procedure_billing_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('service_catalog_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('handoff_number', 30)->unique();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['decided_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedure_billing_handoffs');
    }
};
