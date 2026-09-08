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
        Schema::create('procedure_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('procedure_decision_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('pre_procedure_readiness_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('service_catalog_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('procedure_number', 30)->unique();
            $table->string('status', 30)->default('in_progress');
            $table->text('findings')->nullable();
            $table->text('diagnosis_impression')->nullable();
            $table->boolean('specimens_taken')->default(false);
            $table->text('specimen_notes')->nullable();
            $table->text('complications')->nullable();
            $table->text('outcome')->nullable();
            $table->text('procedure_notes')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['doctor_user_id', 'status', 'started_at', 'id']);
        });

        DB::statement("ALTER TABLE procedure_records ADD CONSTRAINT procedure_records_status_check CHECK (`status` IN ('in_progress', 'completed'))");
        DB::statement('ALTER TABLE procedure_records ADD CONSTRAINT procedure_records_lock_version_check CHECK (`lock_version` >= 1)');
        DB::statement("ALTER TABLE procedure_records ADD CONSTRAINT procedure_records_completion_check CHECK ((`status` = 'in_progress' AND `completed_at` IS NULL) OR (`status` = 'completed' AND `completed_at` IS NOT NULL AND `findings` IS NOT NULL AND CHAR_LENGTH(TRIM(`findings`)) > 0 AND `outcome` IS NOT NULL AND CHAR_LENGTH(TRIM(`outcome`)) > 0 AND (`specimens_taken` = 0 OR (`specimen_notes` IS NOT NULL AND CHAR_LENGTH(TRIM(`specimen_notes`)) > 0))))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedure_records');
    }
};
