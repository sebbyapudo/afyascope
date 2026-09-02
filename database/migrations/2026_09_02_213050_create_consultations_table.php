<?php

use App\ConsultationStatus;
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
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('consultation_number', 30)->unique();
            $table->string('status', 30)->default(ConsultationStatus::InProgress->value);
            $table->timestamp('started_at');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['doctor_user_id', 'status', 'started_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
