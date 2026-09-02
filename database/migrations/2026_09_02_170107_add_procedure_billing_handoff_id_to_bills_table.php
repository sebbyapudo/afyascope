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
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('procedure_billing_handoff_id')
                ->nullable()
                ->unique()
                ->after('visit_id')
                ->constrained()
                ->restrictOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropForeign(['procedure_billing_handoff_id']);
            $table->dropUnique(['procedure_billing_handoff_id']);
            $table->dropColumn('procedure_billing_handoff_id');
        });
    }
};
