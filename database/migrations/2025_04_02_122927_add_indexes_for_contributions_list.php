<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIndexesForContributionsList extends Migration
{
    public function up()
    {
        // Add indexes to sacco_members
        Schema::table('sacco_members', function ($table) {
            $table->index(['member_dept', 'member_active', 'member_deleted', 'member_date_joined'], 'idx_members_status_joined');
            $table->index(['member_name'], 'idx_member_name');
            $table->index(['member_sacco_id'], 'idx_member_sacco_id');
            $table->index(['member_national_id'], 'idx_member_national_id');
            $table->index(['member_email'], 'idx_member_email');
        });

        // Add indexes to sacco_department
        Schema::table('sacco_department', function ($table) {
            $table->index(['department_id', 'department_name'], 'idx_department_id_name');
        });

        // Add indexes to sacco_company
        Schema::table('sacco_company', function ($table) {
            $table->index(['company_id', 'company_name'], 'idx_company_id_name');
        });

        // Add indexes to sacco_position
        Schema::table('sacco_position', function ($table) {
            $table->index(['position_id', 'position_name'], 'idx_position_id_name');
        });

        // Add indexes to sacco_loan_types
        Schema::table('sacco_loan_types', function ($table) {
            $table->index(['loan_type_deleted', 'loan_type_name'], 'idx_loan_type_status_name');
        });

        // Add indexes to sacco_period
        Schema::table('sacco_period', function ($table) {
            $table->index(['period_active', 'period_deleted'], 'idx_period_active_deleted');
        });

        // Add index to sacco_defaults
        Schema::table('sacco_defaults', function ($table) {
            $table->index(['default_name'], 'idx_defaults_name');
        });
    }

    public function down()
    {
        // Drop indexes in reverse
        Schema::table('sacco_members', function ($table) {
            $table->dropIndex('idx_members_status_joined');
            $table->dropIndex('idx_member_name');
            $table->dropIndex('idx_member_sacco_id');
            $table->dropIndex('idx_member_national_id');
            $table->dropIndex('idx_member_email');
        });

        Schema::table('sacco_department', function ($table) {
            $table->dropIndex('idx_department_id_name');
        });

        Schema::table('sacco_company', function ($table) {
            $table->dropIndex('idx_company_id_name');
        });

        Schema::table('sacco_position', function ($table) {
            $table->dropIndex('idx_position_id_name');
        });

        Schema::table('sacco_loan_types', function ($table) {
            $table->dropIndex('idx_loan_type_status_name');
        });

        Schema::table('sacco_period', function ($table) {
            $table->dropIndex('idx_period_active_deleted');
        });

        Schema::table('sacco_defaults', function ($table) {
            $table->dropIndex('idx_defaults_name');
        });
    }
}