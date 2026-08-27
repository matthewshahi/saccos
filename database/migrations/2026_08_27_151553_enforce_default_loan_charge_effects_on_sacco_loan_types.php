<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sacco_loan_types')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Insurance Effect
        |--------------------------------------------------------------------------
        |
        | Historical SACCO convention:
        | ADD_TO_LOAN is the safe/default treatment.
        |
        | Clean legacy NULL/blank values first, then enforce the database default.
        |--------------------------------------------------------------------------
        */
        if (Schema::hasColumn(
            'sacco_loan_types',
            'loan_type_insurance_effect'
        )) {
            DB::table('sacco_loan_types')
                ->where(function ($query) {
                    $query
                        ->whereNull('loan_type_insurance_effect')
                        ->orWhereRaw(
                            "TRIM(COALESCE(loan_type_insurance_effect, '')) = ''"
                        );
                })
                ->update([
                    'loan_type_insurance_effect' => 'ADD_TO_LOAN',
                ]);

            DB::statement("
                ALTER TABLE sacco_loan_types
                MODIFY loan_type_insurance_effect
                    VARCHAR(30)
                    NOT NULL
                    DEFAULT 'ADD_TO_LOAN'
            ");
        }

        /*
        |--------------------------------------------------------------------------
        | Commission Effect
        |--------------------------------------------------------------------------
        |
        | Apply the same rule because this field follows the same historical
        | schema and must not be allowed to fail when commission is disabled.
        |--------------------------------------------------------------------------
        */
        if (Schema::hasColumn(
            'sacco_loan_types',
            'loan_type_commission_effect'
        )) {
            DB::table('sacco_loan_types')
                ->where(function ($query) {
                    $query
                        ->whereNull('loan_type_commission_effect')
                        ->orWhereRaw(
                            "TRIM(COALESCE(loan_type_commission_effect, '')) = ''"
                        );
                })
                ->update([
                    'loan_type_commission_effect' => 'ADD_TO_LOAN',
                ]);

            DB::statement("
                ALTER TABLE sacco_loan_types
                MODIFY loan_type_commission_effect
                    VARCHAR(30)
                    NOT NULL
                    DEFAULT 'ADD_TO_LOAN'
            ");
        }
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Rollback
        |--------------------------------------------------------------------------
        |
        | We deliberately do not turn existing ADD_TO_LOAN values back into NULL.
        | We only remove the enforced DEFAULT while retaining NOT NULL integrity.
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('sacco_loan_types')) {
            return;
        }

        if (Schema::hasColumn(
            'sacco_loan_types',
            'loan_type_insurance_effect'
        )) {
            DB::statement("
                ALTER TABLE sacco_loan_types
                MODIFY loan_type_insurance_effect
                    VARCHAR(30)
                    NOT NULL
            ");
        }

        if (Schema::hasColumn(
            'sacco_loan_types',
            'loan_type_commission_effect'
        )) {
            DB::statement("
                ALTER TABLE sacco_loan_types
                MODIFY loan_type_commission_effect
                    VARCHAR(30)
                    NOT NULL
            ");
        }
    }
};