<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * =========================================================
         * KITUSURU SACCO - MEMBER MASTER DEFAULTS
         *
         * Gender:
         *   Default = M
         *
         * Position:
         *   Default = 1 (MEMBER)
         *
         * Department:
         *   Default = 1 (KITUSURU)
         * =========================================================
         */

        // ---------------------------------------------------------
        // 1. Clean existing data before enforcing NOT NULL
        // ---------------------------------------------------------

        DB::table('sacco_members')
            ->where(function ($query) {
                $query->whereNull('member_gender')
                    ->orWhereRaw("TRIM(member_gender) = ''")
                    ->orWhereRaw("UPPER(TRIM(member_gender)) NOT IN ('M', 'F')");
            })
            ->update([
                'member_gender' => 'M',
            ]);

        // Normalise valid lowercase gender values as well.
        DB::statement("
            UPDATE sacco_members
            SET member_gender = UPPER(TRIM(member_gender))
            WHERE UPPER(TRIM(member_gender)) IN ('M', 'F')
        ");

        DB::table('sacco_members')
            ->where(function ($query) {
                $query->whereNull('member_position')
                    ->orWhereRaw("TRIM(member_position) = ''")
                    ->orWhereNotIn('member_position', ['1', '2']);
            })
            ->update([
                'member_position' => '1',
            ]);

        DB::table('sacco_members')
            ->whereNull('member_dept')
            ->update([
                'member_dept' => 1,
            ]);

        // ---------------------------------------------------------
        // 2. Enforce database-level defaults
        // ---------------------------------------------------------

        DB::statement("
            ALTER TABLE sacco_members
            MODIFY member_gender VARCHAR(1) NOT NULL DEFAULT 'M'
        ");

        DB::statement("
            ALTER TABLE sacco_members
            MODIFY member_position VARCHAR(100) NOT NULL DEFAULT '1'
        ");

        DB::statement("
            ALTER TABLE sacco_members
            MODIFY member_dept INT NOT NULL DEFAULT 1
        ");
    }

    public function down(): void
    {
        /*
         * Restore the original nullable definitions.
         */

        DB::statement("
            ALTER TABLE sacco_members
            MODIFY member_gender VARCHAR(1) NULL DEFAULT NULL
        ");

        DB::statement("
            ALTER TABLE sacco_members
            MODIFY member_position VARCHAR(100) NULL DEFAULT NULL
        ");

        DB::statement("
            ALTER TABLE sacco_members
            MODIFY member_dept INT NULL DEFAULT NULL
        ");
    }
};