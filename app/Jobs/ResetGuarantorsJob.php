<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ResetGuarantorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info("mugera_Job started: ResetGuarantorsJob is now running.");
        
        // Step 1: Update freed guarantor amounts based on % paid
        Log::info("mugera_Step 1: Calculating freed amounts for active loans.");
        $loans = DB::table('sacco_loans')
            ->whereColumn('loan_loan_paid', '<', 'loan_amount')
            ->get();

        Log::info("mugera_Step 1: Found " . $loans->count() . " active loans to process.");

        foreach ($loans as $loan) {
            if ($loan->loan_amount == 0) {
                Log::info("mugera_Step 1: Skipping loan ID {$loan->loan_id} with zero loan amount.");
                continue;
            }

            $percentPaid = $loan->loan_loan_paid / $loan->loan_amount;

            DB::table('sacco_loan_guarantors')
                ->where('loan_guar_loan_id', $loan->loan_id)
                ->where('loan_guar_deleted', '<>', 'Y')
                ->update([
                    'loan_guar_amount_freed' => DB::raw("loan_guar_amount_guaranteed * $percentPaid")
                ]);

            Log::info("mugera_Step 1: Updated guarantors for loan ID {$loan->loan_id} (Paid %: " . round($percentPaid * 100, 2) . "%).");
        }

        // Step 2: Reset all tied shares to zero
        Log::info("mugera_Step 2: Resetting all tied shares to 0.");
        DB::table('sacco_members')->update([
            'member_tied_shares' => 0,
            'member_tied_shares_self' => 0,
        ]);

        // Step 3: Recalculate member tied shares
        Log::info("mugera_Step 3: Recalculating tied shares based on remaining guarantees.");
        $guarantors = DB::table('sacco_loan_guarantors')
            ->where('loan_guar_deleted', '<>', 'Y')
            ->get();

        Log::info("mugera_Step 3: Found " . $guarantors->count() . " active guarantor records to evaluate.");

        foreach ($guarantors as $g) {
            $diff = $g->loan_guar_amount_guaranteed - $g->loan_guar_amount_freed;
            if ($diff > 0) {
                $loan = DB::table('sacco_loans')
                    ->where('loan_id', $g->loan_guar_loan_id)
                    ->first();

                if (!$loan) {
                    Log::warning("mugera_Step 3: Skipped - Loan ID {$g->loan_guar_loan_id} not found.");
                    continue;
                }

                if ($loan->loan_member != $g->loan_guar_guarantor_id) {
                    DB::table('sacco_members')
                        ->where('member_id', $g->loan_guar_guarantor_id)
                        ->increment('member_tied_shares', $diff);

                    Log::info("mugera_Step 3: Added KES {$diff} to member_id {$g->loan_guar_guarantor_id}'s tied_shares.");
                } else {
                    DB::table('sacco_members')
                        ->where('member_id', $g->loan_guar_guarantor_id)
                        ->increment('member_tied_shares_self', $diff);

                    Log::info("mugera_Step 3: Added KES {$diff} to member_id {$g->loan_guar_guarantor_id}'s tied_shares_self (self-guarantee).");
                }
            }
        }

        Log::info("mugera_Finished: All guarantors have been reset.");
    }
}