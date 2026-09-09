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
        Schema::create('recovery_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_episode_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('general_recovery_status', 255);
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->boolean('nausea')->default(false);
            $table->boolean('vomiting')->default(false);
            $table->unsignedSmallInteger('systolic_blood_pressure')->nullable();
            $table->unsignedSmallInteger('diastolic_blood_pressure')->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedTinyInteger('oxygen_saturation')->nullable();
            $table->boolean('supplemental_oxygen')->default(false);
            $table->text('nursing_note')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['recovery_episode_id', 'recorded_at', 'id']);
        });

        DB::statement('ALTER TABLE recovery_observations ADD CONSTRAINT recovery_observations_general_status_check CHECK (CHAR_LENGTH(TRIM(`general_recovery_status`)) BETWEEN 1 AND 255)');
        DB::statement('ALTER TABLE recovery_observations ADD CONSTRAINT recovery_observations_pain_score_check CHECK (`pain_score` IS NULL OR `pain_score` BETWEEN 0 AND 10)');
        DB::statement('ALTER TABLE recovery_observations ADD CONSTRAINT recovery_observations_blood_pressure_check CHECK ((`systolic_blood_pressure` IS NULL AND `diastolic_blood_pressure` IS NULL) OR (`systolic_blood_pressure` BETWEEN 30 AND 300 AND `diastolic_blood_pressure` BETWEEN 20 AND 200))');
        DB::statement('ALTER TABLE recovery_observations ADD CONSTRAINT recovery_observations_pulse_check CHECK (`pulse_rate` IS NULL OR `pulse_rate` BETWEEN 20 AND 300)');
        DB::statement('ALTER TABLE recovery_observations ADD CONSTRAINT recovery_observations_respiratory_check CHECK (`respiratory_rate` IS NULL OR `respiratory_rate` BETWEEN 4 AND 100)');
        DB::statement('ALTER TABLE recovery_observations ADD CONSTRAINT recovery_observations_oxygen_saturation_check CHECK (`oxygen_saturation` IS NULL OR `oxygen_saturation` BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE recovery_observations ADD CONSTRAINT recovery_observations_boolean_check CHECK (`nausea` IN (0, 1) AND `vomiting` IN (0, 1) AND `supplemental_oxygen` IN (0, 1))');
        DB::statement('ALTER TABLE recovery_observations ADD CONSTRAINT recovery_observations_nursing_note_check CHECK (`nursing_note` IS NULL OR CHAR_LENGTH(`nursing_note`) <= 5000)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovery_observations');
    }
};
