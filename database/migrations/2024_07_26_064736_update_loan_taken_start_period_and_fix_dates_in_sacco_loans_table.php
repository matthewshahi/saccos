<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateLoanTakenStartPeriodAndFixDatesInSaccoLoansTable extends Migration
{
    public function up()
    {
        // Temporarily disable strict mode
        DB::statement("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

        // Correct invalid datetime values before altering the table
        DB::statement("UPDATE sacco_loans SET loan_stopped_on = NULL WHERE loan_stopped_on = '0000-00-00 00:00:00'");

        Schema::table('sacco_loans', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_loans', 'loan_taken_start_period')) {
                $table->string('loan_taken_start_period', 6)->nullable();
            } else {
                // Temporarily set the column to nullable to avoid issues during change
                $table->string('loan_taken_start_period', 6)->nullable()->change();
            }
        });

        // Ensure existing data in loan_taken_start_period is 6 characters long and numeric
        DB::statement("UPDATE sacco_loans SET loan_taken_start_period = LPAD(loan_taken_start_period, 6, '0') WHERE loan_taken_start_period IS NOT NULL AND CHAR_LENGTH(loan_taken_start_period) < 6;");
        DB::statement("UPDATE sacco_loans SET loan_taken_start_period = '000000' WHERE loan_taken_start_period IS NULL;");

        // Finally, set the column to be non-nullable
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->string('loan_taken_start_period', 6)->nullable(false)->change();
        });

        // Re-enable strict mode
        DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }

    public function down()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_loans', 'loan_taken_start_period')) {
                $table->dropColumn('loan_taken_start_period');
            }
        });
    }
}
