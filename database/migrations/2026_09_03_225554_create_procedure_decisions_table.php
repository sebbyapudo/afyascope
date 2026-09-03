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
        Schema::create('procedure_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('decision_number', 30)->unique();
            $table->string('outcome', 30);
            $table->text('clinical_rationale')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['decided_at', 'id']);
        });

        Schema::table('procedure_billing_handoffs', function (Blueprint $table) {
            $table->foreignId('procedure_decision_id')
                ->nullable()
                ->after('id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procedure_billing_handoffs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procedure_decision_id');
        });

        Schema::dropIfExists('procedure_decisions');
    }
};
