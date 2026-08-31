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
        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('appointment_id')
                ->nullable()
                ->after('patient_id')
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
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropUnique(['appointment_id']);
            $table->dropColumn('appointment_id');
        });
    }
};
