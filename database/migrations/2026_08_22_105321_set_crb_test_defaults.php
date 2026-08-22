<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Set temporary CRB configuration values for testing.
     *
     * IMPORTANT:
     * These are development/test values only.
     * The institution/branch/specification codes must later be replaced
     * with the official values assigned/confirmed by the CRB.
     */
    public function up(): void
    {
        $defaults = [
            /*
            |--------------------------------------------------------------------------
            | Institution Details
            |--------------------------------------------------------------------------
            */
            'CRB_REGISTERED_NAME'      => 'Kass Sacco',
            'CRB_TRADING_NAME'         => 'Kass Sacco',

            /*
            |--------------------------------------------------------------------------
            | TEST CRB Codes
            |--------------------------------------------------------------------------
            |
            | Institution 1 becomes 001 when formatted for CRB output.
            | Branch 1 becomes 001.
            | SACCO branch identifier therefore resolves to S001001.
            |
            | B is being used as the temporary DST specification code.
            |--------------------------------------------------------------------------
            */
            'CRB_SPECIFICATION_CODE'   => 'B',
            'CRB_INSTITUTION_CODE'     => '1',
            'CRB_BRANCH_NAME'          => 'Main Branch',
            'CRB_BRANCH_CODE'          => '1',

            /*
            |--------------------------------------------------------------------------
            | Reporting Defaults
            |--------------------------------------------------------------------------
            */
            'CRB_DEFAULT_CURRENCY'     => 'KES',
            'CRB_DEFAULT_NATIONALITY'  => 'KE',
        ];

        foreach ($defaults as $name => $value) {

            $existing = DB::table('sacco_defaults')
                ->where('default_name', $name)
                ->first();

            if ($existing) {
                /*
                 * The CRB defaults already exist from the earlier migration,
                 * so update them with the temporary test values.
                 */
                DB::table('sacco_defaults')
                    ->where('default_name', $name)
                    ->update([
                        'default_value'     => $value,
                        'default_transdate' => now(),
                        'default_ip'        => 'CRB_TEST_DEFAULTS',
                    ]);

                continue;
            }

            /*
             * Insert values that do not yet exist.
             *
             * CRB_SPECIFICATION_CODE will normally enter through here.
             */
            DB::table('sacco_defaults')->insert([
                'default_name'      => $name,
                'default_value'     => $value,
                'default_transdate' => now(),
                'default_userid'    => null,
                'default_ip'        => 'CRB_TEST_DEFAULTS',
            ]);
        }
    }

    /**
     * Deliberately do not delete or blank CRB settings on rollback.
     *
     * These settings may have been replaced with official CRB values after
     * this migration was originally run. A rollback must not destroy those
     * later administrative changes.
     */
    public function down(): void
    {
        //
    }
};