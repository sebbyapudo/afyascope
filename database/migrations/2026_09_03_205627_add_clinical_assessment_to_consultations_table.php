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
        Schema::table('consultations', function (Blueprint $table) {
            $table->text('presenting_complaint')->nullable();
            $table->text('relevant_history')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('allergies')->nullable();
            $table->text('examination_findings')->nullable();
            $table->string('asa_classification', 10)->nullable();
            $table->text('assessment_impression')->nullable();
            $table->text('plan_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn([
                'presenting_complaint',
                'relevant_history',
                'current_medications',
                'allergies',
                'examination_findings',
                'asa_classification',
                'assessment_impression',
                'plan_notes',
            ]);
        });
    }
};
