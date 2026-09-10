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
        Schema::create('recovery_readiness_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_episode_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('assessed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('criteria_met');
            $table->boolean('clinical_concern_requires_escalation');
            $table->text('assessment_note')->nullable();
            $table->timestamp('assessed_at');
            $table->timestamps();

            $table->index(['assessed_by_user_id', 'assessed_at'], 'recovery_assessor_assessed_at_idx');
        });

        DB::statement('ALTER TABLE recovery_readiness_assessments ADD CONSTRAINT recovery_readiness_assessments_consistency_check CHECK (NOT (`criteria_met` = 1 AND `clinical_concern_requires_escalation` = 1))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovery_readiness_assessments');
    }
};
