<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add automatic default-interest configuration
     * to SACCO loan products.
     */
    public function up(): void
    {
        Schema::table('sacco_loan_types', function (Blueprint $table) {

            /*
             * Number of calendar days after the applicable contractual
             * due date before the loan is considered to be in default.
             *
             * 0  = default immediately after the due date.
             * 45 = default SACCO grace period.
             */
            $table->unsignedInteger('loan_type_default_after_days')
                ->default(45)
                ->after('loan_type_auto_interest_on_period_change')
                ->comment(
                    'Calendar days after contractual due date before loan is considered defaulted'
                );

            /*
             * Interest rate to use once the loan has defaulted.
             *
             * NULL means use the normal loan_type_interest rate.
             */
            $table->double('loan_type_default_interest')
                ->nullable()
                ->after('loan_type_default_after_days')
                ->comment(
                    'Interest rate applied after default; NULL uses loan_type_interest'
                );
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->dropColumn([
                'loan_type_default_after_days',
                'loan_type_default_interest',
            ]);
        });
    }
};