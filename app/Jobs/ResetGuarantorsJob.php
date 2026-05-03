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

class ResetGuarantorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 0;

    public function handle(): void
    {
        Log::info("mugera_Job started: ResetGuarantorsJob is now running.");

        try {
            /*
             * IMPORTANT:
             * This job assumes sacco_loans.loan_loan_paid has already been recalculated
             * by RecalculateLoanPaidJob.
             *
             * Logic:
             * loan_balance = loan_amount - loan_loan_paid
             *
             * If loan_balance <= 0:
             *   - fully release guarantors
             *
             * If loan_balance > loan_amount:
             *   - cap guarantor exposure at loan_amount
             *
             * For each non-deleted guarantor:
             *   loan_guar_amount_freed = loan_guar_amount_guaranteed * freed_rate
             */

            Log::info("mugera_Step 1: Recalculating freed guarantor amounts using capped loan balance.");

            $loans = DB::table('sacco_loans')
                ->select(
                    'loan_id',
                    'loan_member',
                    'loan_amount',
                    'loan_loan_paid'
                )
                ->orderBy('loan_id')
                ->get();

            Log::info("mugera_Step 1: Found " . $loans->count() . " loans to process.");

            foreach ($loans as $loan) {
                $loanAmount = (float) ($loan->loan_amount ?? 0);
                $loanPaid   = (float) ($loan->loan_loan_paid ?? 0);

                /*
                 * If loan amount is invalid, fully release any guarantors
                 * attached to this loan to avoid stale tied shares.
                 */
                if ($loanAmount <= 0) {
                    DB::table('sacco_loan_guarantors')
                        ->where('loan_guar_loan_id', $loan->loan_id)
                        ->where('loan_guar_deleted', '<>', 'Y')
                        ->update([
                            'loan_guar_amount_freed' => DB::raw('loan_guar_amount_guaranteed'),
                        ]);

                    Log::info("mugera_Step 1: Loan {$loan->loan_id} has zero/invalid amount. Guarantors fully released.");

                    continue;
                }

                $loanBalance = $loanAmount - $loanPaid;

                /*
                 * If balance is zero or negative, fully release guarantors.
                 */
                if ($loanBalance <= 0) {
                    $remainingRate = 0;
                } else {
                    /*
                     * If balance is higher than amount taken, cap it at amount taken.
                     * Guarantors should not be tied beyond the original amount taken.
                     */
                    $cappedBalance = min($loanBalance, $loanAmount);
                    $remainingRate = $cappedBalance / $loanAmount;
                }

                if ($remainingRate < 0) {
                    $remainingRate = 0;
                }

                if ($remainingRate > 1) {
                    $remainingRate = 1;
                }

                $freedRate = 1 - $remainingRate;

                DB::table('sacco_loan_guarantors')
                    ->where('loan_guar_loan_id', $loan->loan_id)
                    ->where('loan_guar_deleted', '<>', 'Y')
                    ->update([
                        'loan_guar_amount_freed' => DB::raw('ROUND(loan_guar_amount_guaranteed * ' . $freedRate . ', 2)'),
                    ]);

                Log::info("mugera_Step 1: Loan {$loan->loan_id} guarantors refreshed.", [
                    'loan_amount'    => $loanAmount,
                    'loan_paid'      => $loanPaid,
                    'loan_balance'   => $loanBalance,
                    'remaining_rate' => $remainingRate,
                    'freed_rate'     => $freedRate,
                ]);
            }

            /*
             * Step 2:
             * Reset member tied shares before rebuilding from guarantor records.
             */
            Log::info("mugera_Step 2: Resetting member_tied_shares and member_tied_shares_self to 0.");

            DB::table('sacco_members')->update([
                'member_tied_shares'      => 0,
                'member_tied_shares_self' => 0,
            ]);

            /*
             * Step 3:
             * Rebuild member tied shares from non-deleted guarantor records.
             *
             * This is where we decide self guarantee:
             *
             * if loan_member == loan_guar_guarantor_id:
             *     member_tied_shares_self
             * else:
             *     member_tied_shares
             */
            Log::info("mugera_Step 3: Rebuilding member tied shares from guarantor records.");

            $guarantors = DB::table('sacco_loan_guarantors as g')
                ->join('sacco_loans as l', 'l.loan_id', '=', 'g.loan_guar_loan_id')
                ->where('g.loan_guar_deleted', '<>', 'Y')
                ->select(
                    'g.loan_guar_id',
                    'g.loan_guar_loan_id',
                    'g.loan_guar_guarantor_id',
                    'g.loan_guar_amount_guaranteed',
                    'g.loan_guar_amount_freed',
                    'l.loan_member',
                    'l.loan_amount',
                    'l.loan_loan_paid'
                )
                ->orderBy('g.loan_guar_id')
                ->get();

            Log::info("mugera_Step 3: Found " . $guarantors->count() . " guarantor records to evaluate.");

            foreach ($guarantors as $g) {
                $loanAmount = (float) ($g->loan_amount ?? 0);
                $loanPaid   = (float) ($g->loan_loan_paid ?? 0);

                if ($loanAmount <= 0) {
                    continue;
                }

                $loanBalance = $loanAmount - $loanPaid;

                /*
                 * If loan balance is zero or negative, guarantors are fully released.
                 * Do not update member tied shares.
                 */
                if ($loanBalance <= 0) {
                    continue;
                }

                $guaranteed = (float) ($g->loan_guar_amount_guaranteed ?? 0);
                $freed      = (float) ($g->loan_guar_amount_freed ?? 0);
                $remaining  = round($guaranteed - $freed, 2);

                if ($remaining <= 0) {
                    continue;
                }

                if ((int) $g->loan_member === (int) $g->loan_guar_guarantor_id) {
                    DB::table('sacco_members')
                        ->where('member_id', $g->loan_guar_guarantor_id)
                        ->increment('member_tied_shares_self', $remaining);

                    Log::info("mugera_Step 3: SELF guarantee updated.", [
                        'loan_id'      => $g->loan_guar_loan_id,
                        'member_id'    => $g->loan_guar_guarantor_id,
                        'amount_tied'  => $remaining,
                    ]);
                } else {
                    DB::table('sacco_members')
                        ->where('member_id', $g->loan_guar_guarantor_id)
                        ->increment('member_tied_shares', $remaining);

                    Log::info("mugera_Step 3: NORMAL guarantee updated.", [
                        'loan_id'      => $g->loan_guar_loan_id,
                        'member_id'    => $g->loan_guar_guarantor_id,
                        'amount_tied'  => $remaining,
                    ]);
                }
            }

            Log::info("mugera_Finished: ResetGuarantorsJob completed successfully.");
        } catch (Throwable $e) {
            Log::error("mugera_ResetGuarantorsJob failed.", [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            throw $e;
        }
    }
}