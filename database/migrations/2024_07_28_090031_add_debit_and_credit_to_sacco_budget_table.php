<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDebitAndCreditToSaccoBudgetTable extends Migration
{
    public function up()
    {
        Schema::table('sacco_budget', function (Blueprint $table) {
            $table->double('budget_amount_debit')->default(0)->after('budget_amount');
            $table->double('budget_amount_credit')->default(0)->after('budget_amount_debit');
        });
    }

    public function down()
    {
        Schema::table('sacco_budget', function (Blueprint $table) {
            $table->dropColumn('budget_amount_debit');
            $table->dropColumn('budget_amount_credit');
        });
    }
};
