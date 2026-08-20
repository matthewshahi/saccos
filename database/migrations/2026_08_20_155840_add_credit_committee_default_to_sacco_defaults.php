<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the configured classification category used to identify
     * Credit Committee officials.
     *
     * Later usage:
     *
     * CREDIT_COMMITTEE => CREDIT_COMMITTEE
     *
     * The value corresponds to:
     * sacco_member_classifications.classification_category
     */
    public function up(): void
    {
        $exists = DB::table('sacco_defaults')
            ->where('default_name', 'CREDIT_COMMITTEE')
            ->exists();

        if (!$exists) {
            DB::table('sacco_defaults')->insert([
                'default_name'      => 'CREDIT_COMMITTEE',
                'default_value'     => 'CREDIT_COMMITTEE',
                'default_transdate' => now(),
                'default_userid'    => null,
                'default_ip'        => 'SYSTEM_MIGRATION',
            ]);
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('sacco_defaults')
            ->where('default_name', 'CREDIT_COMMITTEE')
            ->where('default_value', 'CREDIT_COMMITTEE')
            ->delete();
    }
};