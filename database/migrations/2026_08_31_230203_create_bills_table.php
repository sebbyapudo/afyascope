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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->string('bill_number', 30)->unique();
            $table->string('type', 20);
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->unique(['visit_id', 'type']);
        });

        DB::statement("ALTER TABLE bills ADD CONSTRAINT bills_type_check CHECK (`type` IN ('consultation', 'procedure'))");
        DB::statement("ALTER TABLE bills ADD CONSTRAINT bills_status_check CHECK (`status` IN ('open'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
