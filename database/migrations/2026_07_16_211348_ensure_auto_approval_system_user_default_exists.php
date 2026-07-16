<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_NAME = 'auto_approval_system_user_id';
    private const SYSTEM_USER_ID = 999999;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sacco_defaults')) {
            throw new \RuntimeException(
                'The sacco_defaults table does not exist.'
            );
        }

        $default = DB::table('sacco_defaults')
            ->where('default_name', self::DEFAULT_NAME)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Create the default when it does not exist
        |--------------------------------------------------------------------------
        */
        if ($default === null) {
            DB::table('sacco_defaults')->insert([
                'default_name' => self::DEFAULT_NAME,
                'default_value' => (string) self::SYSTEM_USER_ID,
                'default_transdate' => now(),
                'default_userid' => self::SYSTEM_USER_ID,
                'default_ip' => '127.0.0.1',
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Repair an existing empty or invalid value
        |--------------------------------------------------------------------------
        | A valid configured value is preserved.
        |--------------------------------------------------------------------------
        */
        $currentValue = trim(
            (string) ($default->default_value ?? '')
        );

        if (
            $currentValue === ''
            || !ctype_digit($currentValue)
            || (int) $currentValue <= 0
        ) {
            DB::table('sacco_defaults')
                ->where('default_name', self::DEFAULT_NAME)
                ->update([
                    'default_value' => (string) self::SYSTEM_USER_ID,
                    'default_transdate' => now(),
                    'default_userid' => self::SYSTEM_USER_ID,
                    'default_ip' => '127.0.0.1',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * Intentionally retained because financial records may reference
         * the system audit identifier.
         */
    }
};