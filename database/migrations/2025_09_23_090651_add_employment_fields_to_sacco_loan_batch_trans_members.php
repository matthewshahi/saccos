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
        if (!Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payroll_number')) {
            $table->string('batch_trans_payroll_number', 50)->nullable()->after('batch_trans_payslip2');
        }
        if (!Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_present_designation')) {
            $table->string('batch_trans_present_designation', 100)->nullable()->after('batch_trans_payroll_number');
        }
        if (!Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_terms_of_employment')) {
            $table->string('batch_trans_terms_of_employment', 100)->nullable()->after('batch_trans_present_designation');
        }
    });
}

public function down()
{
    Schema::table('sacco_loan_batch_trans_members', function (Blueprint $table) {
        $table->dropColumn([
            'batch_trans_payroll_number',
            'batch_trans_present_designation',
            'batch_trans_terms_of_employment',
        ]);
    });
}
};
