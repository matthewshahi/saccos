<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add insurance effect column if it does not exist
        if (!Schema::hasColumn('sacco_loan_types', 'loan_type_insurance_effect')) {
            Schema::table('sacco_loan_types', function (Blueprint $table) {
                $table->string('loan_type_insurance_effect', 30)
                    ->nullable()
                    ->after('loan_type_insurable');
            });
        }

        // 2. Clean existing null / empty values first
        DB::table('sacco_loan_types')
            ->whereNull('loan_type_commission_effect')
            ->orWhere('loan_type_commission_effect', '')
            ->update([
                'loan_type_commission_effect' => 'ADD_TO_LOAN'
            ]);

        DB::table('sacco_loan_types')
            ->whereNull('loan_type_insurance_effect')
            ->orWhere('loan_type_insurance_effect', '')
            ->update([
                'loan_type_insurance_effect' => 'ADD_TO_LOAN'
            ]);

        // 3. Now enforce NOT NULL + defaults
        DB::statement("
            ALTER TABLE sacco_loan_types
            MODIFY loan_type_commission_effect VARCHAR(30) NOT NULL DEFAULT 'ADD_TO_LOAN'
        ");

        DB::statement("
            ALTER TABLE sacco_loan_types
            MODIFY loan_type_insurance_effect VARCHAR(30) NOT NULL DEFAULT 'ADD_TO_LOAN'
        ");
    }

    public function down(): void
    {
        // Revert commission effect to nullable
        DB::statement("
            ALTER TABLE sacco_loan_types
            MODIFY loan_type_commission_effect VARCHAR(30) NULL DEFAULT NULL
        ");

        // Drop insurance effect if it exists
        if (Schema::hasColumn('sacco_loan_types', 'loan_type_insurance_effect')) {
            Schema::table('sacco_loan_types', function (Blueprint $table) {
                $table->dropColumn('loan_type_insurance_effect');
            });
        }
    }
};