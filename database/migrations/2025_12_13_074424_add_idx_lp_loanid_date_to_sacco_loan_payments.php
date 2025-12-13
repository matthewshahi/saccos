<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexName = 'idx_lp_loanid_date';

        $exists = DB::selectOne("
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'sacco_loan_payments'
              AND index_name = ?
            LIMIT 1
        ", [$indexName]);

        if (!$exists) {
            DB::statement("
                CREATE INDEX {$indexName}
                ON sacco_loan_payments (loan_payments_loan_id, loan_payments_on)
            ");
        }
    }

    public function down(): void
    {
        $indexName = 'idx_lp_loanid_date';

        $exists = DB::selectOne("
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'sacco_loan_payments'
              AND index_name = ?
            LIMIT 1
        ", [$indexName]);

        if ($exists) {
            DB::statement("
                DROP INDEX {$indexName}
                ON sacco_loan_payments
            ");
        }
    }
};
