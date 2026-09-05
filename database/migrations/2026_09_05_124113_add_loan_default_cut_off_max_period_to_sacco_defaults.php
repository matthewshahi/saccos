<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Loan Default Interest Maximum Automatic Period
        |--------------------------------------------------------------------------
        |
        | Global SACCO control for automatic default-interest processing.
        |
        | Values:
        |
        | 0 = Automatic default interest DISABLED.
        | 1 = Allow maximum 1 automatic default-interest cycle.
        | 2 = Allow maximum 2 automatic default-interest cycles.
        | 3 = Allow maximum 3 automatic default-interest cycles.
        | etc.
        |
        | This works together with the per-loan-type setting:
        |
        | loan_type_auto_interest_on_period_change
        |
        | BOTH controls must permit automatic default interest before any
        | default-interest posting can occur.
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('sacco_defaults')) {
            return;
        }

        $exists = DB::table('sacco_defaults')
            ->where(
                'default_name',
                'loan_default_cut_off_max_period'
            )
            ->exists();

        /*
         * Do NOT overwrite an existing SACCO configuration.
         *
         * This is important for installations where the setting may already
         * have been manually created/configured.
         */
        if ($exists) {
            return;
        }

        DB::table('sacco_defaults')->insert([
            'default_name' =>
                'loan_default_cut_off_max_period',

            /*
             * Safe default:
             *
             * Automatic default interest remains OFF until the SACCO
             * deliberately enables it by setting a value greater than zero.
             */
            'default_value' => 0,

            'default_transdate' => now(),

            /*
             * System-created configuration.
             */
            'default_userid' => 0,
            'default_ip' => '127.0.0.1',
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('sacco_defaults')) {
            return;
        }

        DB::table('sacco_defaults')
            ->where(
                'default_name',
                'loan_default_cut_off_max_period'
            )
            ->delete();
    }
};