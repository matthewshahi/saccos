<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum needs a MODIFY
        DB::statement("
            ALTER TABLE sacco_fosa_types
            MODIFY expected_period ENUM('one_time','daily','weekly','monthly','yearly') NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE sacco_fosa_types
            MODIFY expected_period ENUM('daily','weekly','monthly','yearly') NULL
        ");
    }
};