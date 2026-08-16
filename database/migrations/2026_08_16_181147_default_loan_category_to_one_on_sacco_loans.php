<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * ---------------------------------------------------------
         * LOAN CATEGORY DEFAULT
         *
         * - Default loan category = 1
         * - Keep column NULLABLE
         * - Do NOT make it mandatory
         * - Preserve every existing valid integer value
         * - Only repair existing NULL values
         * ---------------------------------------------------------
         */

        // Set the default without changing NULL/NOT NULL status.
        DB::statement("
            ALTER TABLE sacco_loans
            ALTER COLUMN loan_loan_category SET DEFAULT 1
        ");

        /*
         * Existing data:
         * loan_loan_category is an integer column, therefore a
         * genuinely non-integer value cannot normally be stored.
         * We only need to repair NULL values.
         *
         * Chunking avoids one unnecessarily large UPDATE on
         * installations with very large loan tables.
         */
        DB::table('sacco_loans')
            ->whereNull('loan_loan_category')
            ->orderBy('loan_id')
            ->chunkById(5000, function ($loans) {
                $ids = $loans->pluck('loan_id')->all();

                DB::table('sacco_loans')
                    ->whereIn('loan_id', $ids)
                    ->whereNull('loan_loan_category')
                    ->update([
                        'loan_loan_category' => 1,
                    ]);
            }, 'loan_id');
    }

    public function down(): void
    {
        /*
         * Remove only the database default.
         *
         * We deliberately DO NOT change rows that were backfilled
         * to category 1 because after migration there is no safe
         * way to distinguish an originally-NULL row from a genuine
         * category-1 loan.
         */
        DB::statement("
            ALTER TABLE sacco_loans
            ALTER COLUMN loan_loan_category DROP DEFAULT
        ");
    }
};