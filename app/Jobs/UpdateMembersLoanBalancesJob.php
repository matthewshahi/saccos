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

class UpdateMembersLoanBalancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 0;

    public function handle(): void
    {
        Log::info("mugera_Job started: UpdateMembersLoanBalancesJob is now running.");

        try {
            /*
             * Step 1:
             * Reset all member loan totals to zero.
             */
            Log::info("mugera_UpdateMembersLoanBalances Step 1: Resetting all member_total_loan to 0.");

            $resetCount = DB::table('sacco_members')->update([
                'member_total_loan' => 0,
            ]);

            Log::info("mugera_UpdateMembersLoanBalances Step 1: Reset completed.", [
                'members_reset' => $resetCount,
            ]);

            /*
             * Step 2:
             * Update member_total_loan from active outstanding loan balances.
             *
             * Balance = loan_amount - loan_loan_paid
             *
             * If balance <= 0:
             *   count as 0
             *
             * This avoids overpaid loans reducing the member's total loan balance.
             */
            Log::info("mugera_UpdateMembersLoanBalances Step 2: Updating member_total_loan from loan balances.");

            $updatedCount = DB::update("
                UPDATE sacco_members m
                INNER JOIN (
                    SELECT
                        loan_member,
                        SUM(
                            CASE
                                WHEN (COALESCE(loan_amount, 0) - COALESCE(loan_loan_paid, 0)) > 0
                                THEN (COALESCE(loan_amount, 0) - COALESCE(loan_loan_paid, 0))
                                ELSE 0
                            END
                        ) AS total_loan_balance
                    FROM sacco_loans
                    WHERE loan_member IS NOT NULL
                    GROUP BY loan_member
                ) x
                    ON x.loan_member = m.member_id
                SET m.member_total_loan = COALESCE(x.total_loan_balance, 0)
            ");

            Log::info("mugera_UpdateMembersLoanBalances Step 2: Member loan totals updated.", [
                'members_updated' => $updatedCount,
            ]);

            Log::info("mugera_Finished: UpdateMembersLoanBalancesJob completed successfully.");
        } catch (Throwable $e) {
            Log::error("mugera_UpdateMembersLoanBalancesJob failed.", [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            throw $e;
        }
    }
}