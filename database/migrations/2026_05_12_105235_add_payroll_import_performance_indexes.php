<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            'sacco_members',
            'idx_payroll_member_national_id_deleted',
            'ALTER TABLE sacco_members
             ADD INDEX idx_payroll_member_national_id_deleted (
                member_national_id,
                member_deleted,
                member_id
             )'
        );

        $this->addIndexIfMissing(
            'sacco_loans',
            'idx_payroll_loan_lookup',
            'ALTER TABLE sacco_loans
             ADD INDEX idx_payroll_loan_lookup (
                loan_member,
                loan_loan_type,
                loan_stoped,
                loan_taken_period,
                loan_start_deduction_period,
                loan_id
             )'
        );

        $this->addIndexIfMissing(
            'sacco_loan_payments',
            'idx_payroll_loan_payments_doc_period',
            'ALTER TABLE sacco_loan_payments
             ADD INDEX idx_payroll_loan_payments_doc_period (
                loan_payments_period,
                loan_payments_docno,
                loan_end_month_proc
             )'
        );

        $this->addIndexIfMissing(
            'sacco_shares',
            'idx_payroll_shares_doc_period',
            'ALTER TABLE sacco_shares
             ADD INDEX idx_payroll_shares_doc_period (
                share_period,
                share_doc_no,
                share_end_month_proc
             )'
        );

        $this->addIndexIfMissing(
            'sacco_capital_shares',
            'idx_payroll_capital_doc_period',
            'ALTER TABLE sacco_capital_shares
             ADD INDEX idx_payroll_capital_doc_period (
                share_capitalperiod,
                share_capitaldoc_no,
                share_capitalend_month_proc
             )'
        );

        $this->addIndexIfMissing(
            'sacco_fosas',
            'idx_payroll_fosa_doc_period',
            'ALTER TABLE sacco_fosas
             ADD INDEX idx_payroll_fosa_doc_period (
                fosa_period,
                fosa_doc_no,
                fosa_end_month_proc
             )'
        );

        $this->addIndexIfMissing(
            'sacco_loan_guarantors',
            'idx_payroll_guarantors_loan_deleted',
            'ALTER TABLE sacco_loan_guarantors
             ADD INDEX idx_payroll_guarantors_loan_deleted (
                loan_guar_loan_id,
                loan_guar_deleted
             )'
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('sacco_loan_guarantors', 'idx_payroll_guarantors_loan_deleted');
        $this->dropIndexIfExists('sacco_fosas', 'idx_payroll_fosa_doc_period');
        $this->dropIndexIfExists('sacco_capital_shares', 'idx_payroll_capital_doc_period');
        $this->dropIndexIfExists('sacco_shares', 'idx_payroll_shares_doc_period');
        $this->dropIndexIfExists('sacco_loan_payments', 'idx_payroll_loan_payments_doc_period');
        $this->dropIndexIfExists('sacco_loans', 'idx_payroll_loan_lookup');
        $this->dropIndexIfExists('sacco_members', 'idx_payroll_member_national_id_deleted');
    }

    private function addIndexIfMissing(string $table, string $indexName, string $sql): void
    {
        if (!$this->indexExists($table, $indexName)) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$indexName}");
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        return $exists;
    }
};