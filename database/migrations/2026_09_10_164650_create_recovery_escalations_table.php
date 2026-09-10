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
        Schema::create('recovery_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_episode_id')->constrained()->restrictOnDelete();
            $table->foreignId('escalated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('open');
            $table->boolean('open_marker')->nullable()->default(true);
            $table->timestamp('escalated_at');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('resolution', 40)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['recovery_episode_id', 'open_marker']);
            $table->index(['status', 'escalated_at', 'id']);
            $table->index(['resolved_by_user_id', 'resolved_at']);
        });

        DB::statement("ALTER TABLE recovery_escalations ADD CONSTRAINT recovery_escalations_status_check CHECK (`status` IN ('open', 'resolved'))");
        DB::statement("ALTER TABLE recovery_escalations ADD CONSTRAINT recovery_escalations_resolution_check CHECK ((`status` = 'open' AND `open_marker` = 1 AND `resolved_by_user_id` IS NULL AND `resolution` IS NULL AND `resolution_note` IS NULL AND `resolved_at` IS NULL) OR (`status` = 'resolved' AND `open_marker` IS NULL AND `resolved_by_user_id` IS NOT NULL AND `resolution` IN ('continue_monitoring', 'clinically_cleared') AND `resolved_at` IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovery_escalations');
    }
};
