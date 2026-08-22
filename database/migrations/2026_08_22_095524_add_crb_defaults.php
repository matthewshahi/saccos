<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MIGRATION_MARKER = 'CRB_DEFAULTS_20260822';

    public function up(): void
    {
        $defaults = [
            'CRB_REGISTERED_NAME'     => null,
            'CRB_TRADING_NAME'        => null,
            'CRB_INSTITUTION_CODE'    => null,
            'CRB_BRANCH_NAME'         => null,
            'CRB_BRANCH_CODE'         => null,
            'CRB_DEFAULT_CURRENCY'    => 'KES',
            'CRB_DEFAULT_NATIONALITY' => 'KE',
        ];

        foreach ($defaults as $name => $value) {
            $exists = DB::table('sacco_defaults')
                ->where('default_name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('sacco_defaults')->insert([
                'default_name'      => $name,
                'default_value'     => $value,
                'default_transdate' => now(),
                'default_userid'    => null,
                'default_ip'        => self::MIGRATION_MARKER,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sacco_defaults')
            ->whereIn('default_name', [
                'CRB_REGISTERED_NAME',
                'CRB_TRADING_NAME',
                'CRB_INSTITUTION_CODE',
                'CRB_BRANCH_NAME',
                'CRB_BRANCH_CODE',
                'CRB_DEFAULT_CURRENCY',
                'CRB_DEFAULT_NATIONALITY',
            ])
            ->where('default_ip', self::MIGRATION_MARKER)
            ->delete();
    }
};
