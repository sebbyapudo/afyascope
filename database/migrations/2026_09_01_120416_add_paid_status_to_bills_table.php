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
        DB::statement('ALTER TABLE bills DROP CHECK bills_status_check');
        DB::statement("ALTER TABLE bills ADD CONSTRAINT bills_status_check CHECK (`status` IN ('open', 'paid'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE bills DROP CHECK bills_status_check');
        DB::statement("ALTER TABLE bills ADD CONSTRAINT bills_status_check CHECK (`status` IN ('open'))");
    }
};
