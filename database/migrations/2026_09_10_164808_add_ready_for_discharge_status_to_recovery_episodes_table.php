<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE recovery_episodes DROP CHECK recovery_episodes_status_check');
        DB::statement('ALTER TABLE recovery_episodes DROP CHECK recovery_episodes_completion_check');
        DB::statement("ALTER TABLE recovery_episodes ADD CONSTRAINT recovery_episodes_status_check CHECK (`status` IN ('in_progress', 'ready_for_discharge', 'completed'))");
        DB::statement("ALTER TABLE recovery_episodes ADD CONSTRAINT recovery_episodes_completion_check CHECK ((`status` IN ('in_progress', 'ready_for_discharge') AND `completed_at` IS NULL) OR (`status` = 'completed' AND `completed_at` IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE recovery_episodes DROP CHECK recovery_episodes_status_check');
        DB::statement('ALTER TABLE recovery_episodes DROP CHECK recovery_episodes_completion_check');
        DB::statement("ALTER TABLE recovery_episodes ADD CONSTRAINT recovery_episodes_status_check CHECK (`status` IN ('in_progress', 'completed'))");
        DB::statement("ALTER TABLE recovery_episodes ADD CONSTRAINT recovery_episodes_completion_check CHECK ((`status` = 'in_progress' AND `completed_at` IS NULL) OR (`status` = 'completed' AND `completed_at` IS NOT NULL))");
    }
};
