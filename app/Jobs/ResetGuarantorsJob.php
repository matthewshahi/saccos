<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ResetGuarantorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        

        // Step 1: Update freed guarantor amounts based on % paid
        $loans = DB::table('sacco_loans')
            ->whereColumn('loan_loan_paid', '<', 'loan_amount')
            ->get();

        foreach ($loans as $loan) {
            if ($loan->loan_amount == 0) {
                continue;
            }

            $percentPaid = $loan->loan_loan_paid / $loan->loan_amount;

            DB::table('sacco_loan_guarantors')
                ->where('loan_guar_loan_id', $loan->loan_id)
                ->where('loan_guar_deleted', '<>', 'Y')
                ->update([
                    'loan_guar_amount_freed' => DB::raw("loan_guar_amount_guaranteed * $percentPaid")
                ]);
        }

        // Step 2: Reset all tied shares to zero
        DB::table('sacco_members')->update([
            'member_tied_shares' => 0,
            'member_tied_shares_self' => 0,
        ]);

        // Step 3: Recalculate member tied shares based on remaining guaranteed amounts
        $guarantors = DB::table('sacco_loan_guarantors')
            ->where('loan_guar_deleted', '<>', 'Y')
            ->get();

        foreach ($guarantors as $g) {
            $diff = $g->loan_guar_amount_guaranteed - $g->loan_guar_amount_freed;
            if ($diff > 0) {
                $loan = DB::table('sacco_loans')
                    ->where('loan_id', $g->loan_guar_loan_id)
                    ->first();

                if (!$loan) continue;

                if ($loan->loan_member != $g->loan_guar_guarantor_id) {
                    DB::table('sacco_members')
                        ->where('member_id', $g->loan_guar_guarantor_id)
                        ->increment('member_tied_shares', $diff);
                } else {
                    DB::table('sacco_members')
                        ->where('member_id', $g->loan_guar_guarantor_id)
                        ->increment('member_tied_shares_self', $diff);
                }
            }
        }

        logger("mugera_Finished ................. all guarantors have been reset.");
    }
}