<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->modifyToMediumText('sacco_accounts_trans', 'accounts_trans_decription', true);

        $this->modifyToMediumText('sacco_loan_batch_trans', 'batch_trans_description', true);
        $this->modifyToMediumText('sacco_loan_batch_trans_deductions', 'batch_trans_deduction_description', true);
        $this->modifyToMediumText('sacco_loan_deductions', 'loan_deduction_description', true);

        $this->modifyToMediumText('sacco_loans', 'loan_description', true);
        $this->modifyToMediumText('sacco_loans', 'loan_charges_snapshot', true);

        // Only if these columns exist in your DB
        $this->modifyToMediumText('sacco_loan_batch_guarantors', 'guarantors_description', true);
        $this->modifyToMediumText('sacco_loan_guarantors', 'guarantors_description', true);
    }

    public function down(): void
    {
        // Intentionally left blank.
        // Shrinking live text columns is risky and can truncate real data.
    }

    private function modifyToMediumText(string $table, string $column, bool $nullable = true): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $nullSql = $nullable ? 'NULL' : 'NOT NULL';

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` MEDIUMTEXT %s',
            $table,
            $column,
            $nullSql
        ));
    }
};