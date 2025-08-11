<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesForLoanPerformanceReport extends Migration
{
    public function up()
    {
        // Index for WHERE and JOIN on loan_member
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->index('loan_member', 'idx_loans_loan_member');
            $table->index('loan_loan_type', 'idx_loans_loan_type');
            $table->index(['loan_amount', 'loan_loan_paid'], 'idx_loans_amount_paid');
            $table->index('loan_stoped', 'idx_loans_stoped');
        });

        // Index for ordering and filtering on members
        Schema::table('sacco_members', function (Blueprint $table) {
            $table->index('member_id', 'idx_members_member_id');
            $table->index('member_name', 'idx_members_name');
            $table->index('member_position', 'idx_members_position');
            $table->index('member_phone_no', 'idx_members_phone');
            $table->index('member_national_id', 'idx_members_national_id');
        });

        // Index for loan types
        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->index('loan_type_id', 'idx_loan_types_id');
        });

        // Index for loan payments max period subquery
        Schema::table('sacco_loan_payments', function (Blueprint $table) {
            $table->index('loan_payments_loan_id', 'idx_payments_loan_id');
            $table->index('loan_payments_period', 'idx_payments_period');
        });
    }

    public function down()
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->dropIndex('idx_loans_loan_member');
            $table->dropIndex('idx_loans_loan_type');
            $table->dropIndex('idx_loans_amount_paid');
            $table->dropIndex('idx_loans_stoped');
        });

        Schema::table('sacco_members', function (Blueprint $table) {
            $table->dropIndex('idx_members_member_id');
            $table->dropIndex('idx_members_name');
            $table->dropIndex('idx_members_position');
            $table->dropIndex('idx_members_phone');
            $table->dropIndex('idx_members_national_id');
        });

        Schema::table('sacco_loan_types', function (Blueprint $table) {
            $table->dropIndex('idx_loan_types_id');
        });

        Schema::table('sacco_loan_payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_loan_id');
            $table->dropIndex('idx_payments_period');
        });
    }
}