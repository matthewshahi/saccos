<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the minimum number of Credit Committee approvals
     * required before a loan can proceed through the approval process.
     *
     * Default: 1
     */
    public function up(): void
    {
        $exists = DB::table('sacco_defaults')
            ->where(
                'default_name',
                'CREDIT_COMMITTEE_REQUIRED_APPROVALS'
            )
            ->exists();

        if (!$exists) {
            DB::table('sacco_defaults')->insert([
                'default_name' =>
                    'CREDIT_COMMITTEE_REQUIRED_APPROVALS',

                'default_value' =>
                    '1',

                'default_transdate' =>
                    now(),

                'default_userid' =>
                    null,

                'default_ip' =>
                    'SYSTEM_MIGRATION',
            ]);
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('sacco_defaults')
            ->where(
                'default_name',
                'CREDIT_COMMITTEE_REQUIRED_APPROVALS'
            )
            ->delete();
    }
};