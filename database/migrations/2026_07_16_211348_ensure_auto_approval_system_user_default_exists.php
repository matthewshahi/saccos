<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    private const DEFAULT_NAME = 'auto_approval_system_user_id';
    private const SYSTEM_USER_ID = 999999;

    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sacco_defaults')) {
            throw new RuntimeException(
                'The sacco_defaults table does not exist.'
            );
        }

        $default = DB::table('sacco_defaults')
            ->where('default_name', self::DEFAULT_NAME)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Create the system-user default
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
        | Repair an empty or invalid existing value
        |--------------------------------------------------------------------------
        | A valid configured value is preserved.
        |--------------------------------------------------------------------------
        */
        $currentValue = trim(
            (string) ($default->default_value ?? '')
        );

        if (
            $currentValue === ''
            || !is_numeric($currentValue)
            || (int) $currentValue <= 0
        ) {
            DB::table('sacco_defaults')
                ->where('default_name', self::DEFAULT_NAME)
                ->update([
                    'default_value' =>
                        (string) self::SYSTEM_USER_ID,

                    'default_transdate' => now(),

                    'default_userid' =>
                        self::SYSTEM_USER_ID,

                    'default_ip' => '127.0.0.1',
                ]);
        }
    }

    /**
     * Reverse the migration.
     *
     * The row is intentionally retained because another process may already
     * be using it for financial audit records.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};