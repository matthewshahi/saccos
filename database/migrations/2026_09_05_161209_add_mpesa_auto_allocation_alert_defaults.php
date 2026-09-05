<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sacco_defaults')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Existing SACCO email
        |--------------------------------------------------------------------------
        |
        | Use the existing sacco_mail as the initial management alert email.
        | SACCO management may later replace this with multiple comma-separated
        | email addresses.
        |
        */

        $existingSaccoMail = DB::table('sacco_defaults')
            ->where('default_name', 'sacco_mail')
            ->value('default_value');

        $defaults = [

            /*
             * One or more comma-separated management phone numbers.
             *
             * Example:
             *
             * 0722400737,0712345678,0798765432
             */
            'SACCO_MANAGER_ALERT_PHONE_NUMBERS' => '0722400737',

            /*
             * One or more comma-separated management email addresses.
             *
             * Start with the SACCO's existing sacco_mail where available.
             */
            'SACCO_MANAGER_ALERT_EMAILS' => trim(
                (string) ($existingSaccoMail ?? '')
            ),

            /*
             * Secondary management SMS alerts are optional because they
             * consume the SACCO's SMS budget.
             *
             * Y = queue management SMS alerts
             * N = do not queue management SMS alerts
             *
             * Email alerts are not controlled by this setting.
             */
            'SEND_SECONDARY_AUTO_ALERTS_SMS' => 'N',
        ];

        foreach ($defaults as $name => $value) {

            $exists = DB::table('sacco_defaults')
                ->where('default_name', $name)
                ->exists();

            /*
             * Existing SACCO configuration must never be overwritten.
             */
            if ($exists) {
                continue;
            }

            DB::table('sacco_defaults')->insert([
                'default_name' => $name,
                'default_value' => $value,
                'default_transdate' => now(),
                'default_userid' => 999,
                'default_ip' => '127.0.0.1',
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sacco_defaults')) {
            return;
        }

        DB::table('sacco_defaults')
            ->whereIn('default_name', [
                'SACCO_MANAGER_ALERT_PHONE_NUMBERS',
                'SACCO_MANAGER_ALERT_EMAILS',
                'SEND_SECONDARY_AUTO_ALERTS_SMS',
            ])
            ->delete();
    }
};