<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
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
        // Process members in chunks to avoid memory issues
        DB::table('sacco_members')
            ->select('id')
            ->orderBy('id')
            ->chunk(100, function ($members) {
                foreach ($members as $member) {
                    $totalBalance = DB::table('sacco_loans')
                        ->where('loan_member_id', $member->id)
                        ->sum(DB::raw('loan_loan_amount - loan_loan_paid'));

                    DB::table('sacco_members')
                        ->where('id', $member->id)
                        ->update(['member_total_loans' => $totalBalance]);
                }
            });
    }
}