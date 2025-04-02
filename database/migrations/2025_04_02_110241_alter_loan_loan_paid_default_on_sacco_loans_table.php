<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterLoanLoanPaidDefaultOnSaccoLoansTable extends Migration
{
    public function up()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->decimal('loan_loan_paid', 15, 2)
                  ->default(0)
                  ->nullable(false)
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->decimal('loan_loan_paid', 15, 2)
                  ->nullable()
                  ->default(null)
                  ->change();
        });
    }
}