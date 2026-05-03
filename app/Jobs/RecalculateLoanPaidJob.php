<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

class RecalculateLoanPaidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * Allow the job to run without queue timeout interruption.
     */
    public $timeout = 0;

    public function handle(): void
    {
        Log::info("mugera_Job started: RecalculateLoanPaidJob is now running.");

        try {
            /*
             * Step 1:
             * Reset all loan paid amounts to zero.
             */
            Log::info("mugera_RecalculateLoanPaid Step 1: Resetting all sacco_loans.loan_loan_paid to 0.");

            $resetCount = DB::table('sacco_loans')->update([
                'loan_loan_paid' => 0,
            ]);

            Log::info("mugera_RecalculateLoanPaid Step 1: Reset completed.", [
                'loans_reset' => $resetCount,
            ]);

            /*
             * Step 2:
             * Recalculate loan_loan_paid from sacco_loan_payments.
             *
             * IMPORTANT:
             * - loan_payments_amount is treated as principal paid.
             * - loan_payments_interest is NOT added.
             * - Negative principal values are preserved because they affect loan balance.
             */
            Log::info("mugera_RecalculateLoanPaid Step 2: Updating loan_loan_paid from loan repayments.");

            $updatedCount = DB::update("
                UPDATE sacco_loans l
                INNER JOIN (
                    SELECT
                        loan_payments_loan_id,
                        SUM(COALESCE(loan_payments_amount, 0)) AS total_principal_paid
                    FROM sacco_loan_payments
                    WHERE loan_payments_loan_id IS NOT NULL
                    GROUP BY loan_payments_loan_id
                ) p
                    ON p.loan_payments_loan_id = l.loan_id
                SET l.loan_loan_paid = COALESCE(p.total_principal_paid, 0)
            ");

            Log::info("mugera_RecalculateLoanPaid Step 2: Loan paid totals updated.", [
                'loans_updated_from_payments' => $updatedCount,
            ]);

            Log::info("mugera_Finished: RecalculateLoanPaidJob completed successfully.");
        } catch (Throwable $e) {
            Log::error("mugera_RecalculateLoanPaidJob failed.", [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            throw $e;
        }
    }
}