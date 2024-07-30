<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeLoanTakenStartPeriodInSaccoLoansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            // Modify the loan_taken_start_period column to be unsigned big integer and nullable
            $table->unsignedBigInteger('loan_taken_start_period')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            // Revert the loan_taken_start_period column changes
            // Adjust the column type and nullability as necessary to revert changes
            // Example: if it was previously an integer and non-nullable
            $table->integer('loan_taken_start_period')->nullable(false)->change();
        });
    }
}
