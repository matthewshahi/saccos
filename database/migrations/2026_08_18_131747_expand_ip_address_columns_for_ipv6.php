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
        DB::statement("
            ALTER TABLE sacco_defaults
            MODIFY default_ip VARCHAR(45) NULL
        ");

        DB::statement("
            ALTER TABLE sacco_userrights
            MODIFY rights_ip VARCHAR(45) NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE sacco_defaults
            MODIFY default_ip VARCHAR(32) NULL
        ");

        DB::statement("
            ALTER TABLE sacco_userrights
            MODIFY rights_ip VARCHAR(32) NULL
        ");
    }
};