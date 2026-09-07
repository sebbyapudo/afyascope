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
        Schema::create('pre_procedure_readinesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('procedure_decision_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('nurse_user_id')->constrained('users')->restrictOnDelete();
            $table->string('readiness_number', 30)->unique();
            $table->string('status', 30)->default('in_preparation');
            $table->boolean('consent_verified')->default(false);
            $table->boolean('patient_identity_verified')->default(false);
            $table->boolean('procedure_verified')->default(false);
            $table->boolean('allergies_reviewed')->default(false);
            $table->boolean('medications_reviewed')->default(false);
            $table->text('observations')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at', 'id']);
        });

        DB::statement("ALTER TABLE pre_procedure_readinesses ADD CONSTRAINT pre_procedure_readinesses_status_check CHECK (`status` IN ('in_preparation', 'ready'))");
        DB::statement("ALTER TABLE pre_procedure_readinesses ADD CONSTRAINT pre_procedure_readinesses_completion_check CHECK ((`status` = 'in_preparation' AND `completed_at` IS NULL) OR (`status` = 'ready' AND `completed_at` IS NOT NULL AND `consent_verified` = 1 AND `patient_identity_verified` = 1 AND `procedure_verified` = 1 AND `allergies_reviewed` = 1 AND `medications_reviewed` = 1))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_procedure_readinesses');
    }
};
