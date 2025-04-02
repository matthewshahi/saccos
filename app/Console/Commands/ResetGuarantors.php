<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetGuarantors extends Command
{
    protected $signature = 'sacco:reset-guarantors';
    protected $description = 'Reset all guarantors and tied shares based on paid loans';

    public function handle()
    {
        $this->info("mugera_Waiting .................");
        $this->info("mugera_Processing .................");

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

        DB::table('sacco_members')->update([
            'member_tied_shares' => 0,
            'member_tied_shares_self' => 0,
        ]);

        $guarantors = DB::table('sacco_loan_guarantors')
            ->where('loan_guar_deleted', '<>', 'Y')
            ->get();

        foreach ($guarantors as $guarantor) {
            $diff = $guarantor->loan_guar_amount_guaranteed - $guarantor->loan_guar_amount_freed;

            if ($diff > 0) {
                $loan = DB::table('sacco_loans')->where('loan_id', $guarantor->loan_guar_loan_id)->first();

                if (!$loan) {
                    continue;
                }

                if ($loan->loan_member != $guarantor->loan_guar_guarantor_id) {
                    DB::table('sacco_members')
                        ->where('member_id', $guarantor->loan_guar_guarantor_id)
                        ->increment('member_tied_shares', $diff);
                } else {
                    DB::table('sacco_members')
                        ->where('member_id', $guarantor->loan_guar_guarantor_id)
                        ->increment('member_tied_shares_self', $diff);
                }
            }
        }

        $this->info("mugera_Finished ................. all guarantors have been reset.");
    }
}