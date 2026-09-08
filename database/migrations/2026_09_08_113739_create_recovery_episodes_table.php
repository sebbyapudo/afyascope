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
        Schema::create('recovery_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('procedure_record_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('nurse_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recovery_number', 30)->unique();
            $table->string('status', 30)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['nurse_user_id', 'status', 'started_at', 'id']);
        });

        DB::statement("ALTER TABLE recovery_episodes ADD CONSTRAINT recovery_episodes_status_check CHECK (`status` IN ('in_progress', 'completed'))");
        DB::statement("ALTER TABLE recovery_episodes ADD CONSTRAINT recovery_episodes_completion_check CHECK ((`status` = 'in_progress' AND `completed_at` IS NULL) OR (`status` = 'completed' AND `completed_at` IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovery_episodes');
    }
};
