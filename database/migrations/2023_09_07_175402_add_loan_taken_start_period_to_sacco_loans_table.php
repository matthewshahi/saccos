<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLoanTakenStartPeriodToSaccoLoansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->unsignedInteger('loan_taken_start_period')->nullable(); // YYYYMM format, like 202309
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
            //
        });
    }
}
