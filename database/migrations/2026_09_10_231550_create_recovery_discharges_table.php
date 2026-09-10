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
        Schema::create('recovery_discharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_episode_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('discharged_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('discharge_number', 30)->unique();
            $table->string('condition_summary');
            $table->string('accompaniment_status', 30);
            $table->string('disposition', 30);
            $table->text('nursing_note')->nullable();
            $table->text('general_care_instructions');
            $table->text('activity_driving_instructions');
            $table->text('diet_fluids_instructions');
            $table->text('medication_instructions')->nullable();
            $table->text('warning_signs_instructions');
            $table->text('follow_up_instructions')->nullable();
            $table->timestamp('discharged_at');
            $table->timestamps();

            $table->index(['discharged_by_user_id', 'discharged_at']);
        });

        DB::statement("ALTER TABLE recovery_discharges ADD CONSTRAINT recovery_discharges_accompaniment_check CHECK (`accompaniment_status` IN ('accompanied', 'not_accompanied', 'not_applicable'))");
        DB::statement("ALTER TABLE recovery_discharges ADD CONSTRAINT recovery_discharges_disposition_check CHECK (`disposition` IN ('home', 'other_facility', 'other'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovery_discharges');
    }
};
