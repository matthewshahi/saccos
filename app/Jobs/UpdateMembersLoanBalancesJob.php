<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateMembersLoanBalancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function handle()
    {
        // Step 1: Reset all totals to zero
        DB::table('sacco_members')->update(['member_total_loan' => 0]);

        // Step 2: Recalculate and update
        DB::table('sacco_members')
            ->select('member_id')
            ->orderBy('member_id')
            ->chunk(100, function ($members) {
                foreach ($members as $member) {
                    // Use COALESCE to handle NULLs in DB-side computation
                    $totalBalance = DB::table('sacco_loans')
                        ->where('loan_member', $member->member_id)
                        ->selectRaw('COALESCE(SUM(COALESCE(loan_amount, 0) - COALESCE(loan_loan_paid, 0)), 0) as balance')
                        ->value('balance');

                    DB::table('sacco_members')
                        ->where('member_id', $member->member_id)
                        ->update(['member_total_loan' => $totalBalance]);

                    Log::info("mugera_Updated member_total_loan for member {$member->member_id}: {$totalBalance}");
                }
            });
    }
}