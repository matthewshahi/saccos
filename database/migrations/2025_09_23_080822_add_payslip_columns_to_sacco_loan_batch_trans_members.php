<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('sacco_loan_batch_trans_members', function (Blueprint $table) {
        if (!Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payslip1')) {
            $table->string('batch_trans_payslip1')->nullable()->after('batch_trans_ip');
        }
        if (!Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payslip2')) {
            $table->string('batch_trans_payslip2')->nullable()->after('batch_trans_payslip1');
        }
    });
}

public function down()
{
    Schema::table('sacco_loan_batch_trans_members', function (Blueprint $table) {
        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payslip1')) {
            $table->dropColumn('batch_trans_payslip1');
        }
        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payslip2')) {
            $table->dropColumn('batch_trans_payslip2');
        }
    });
}
};
