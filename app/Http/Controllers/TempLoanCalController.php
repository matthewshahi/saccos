<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TempLoanCalController extends Controller
{
    public function recalcAllLoansAndMembers()
    {
        // Prevent script timeout and memory limits for heavy recalculation
        ini_set('max_execution_time', 0); // ⏳ no timeout
        ini_set('memory_limit', '-1');   // 🧠 unlimited memory (use cautiously)

        Log::info('⚙️ Starting full loan + member recalculation process...');

        // 1️⃣ Reset all loan and member totals
        DB::table('sacco_loans')->update(['loan_loan_paid' => 0]);
        DB::table('sacco_members')->update(['member_total_loan' => 0]);

        Log::info('✅ Step 1 complete: loan_loan_paid and member_total_loan reset to 0.');

        // 2️⃣ Process loan payments in chunks and update loan_loan_paid per loan
        $totalLoansUpdated = 0;

        DB::table('sacco_loan_payments')
            ->select('loan_payments_loan_id', DB::raw('COALESCE(SUM(COALESCE(loan_payments_amount, 0)), 0) as total_paid'))
            ->whereNotNull('loan_payments_loan_id')
            ->groupBy('loan_payments_loan_id')
            ->orderBy('loan_payments_loan_id')
            ->chunk(1000, function ($chunk) use (&$totalLoansUpdated) {
                foreach ($chunk as $payment) {
                    DB::table('sacco_loans')
                        ->where('loan_id', $payment->loan_payments_loan_id)
                        ->update(['loan_loan_paid' => $payment->total_paid]);

                    $totalLoansUpdated++;
                }
            });

        Log::info("✅ Step 2 complete: Recalculated loan_loan_paid for {$totalLoansUpdated} loans.");

        // 3️⃣ Process members in chunks and update member_total_loan
        $totalMembersUpdated = 0;

        DB::table('sacco_loans')
            ->select('loan_member', DB::raw('COALESCE(SUM(COALESCE(loan_amount, 0) - COALESCE(loan_loan_paid, 0)), 0) as total_balance'))
            ->whereNotNull('loan_member')
            ->groupBy('loan_member')
            ->orderBy('loan_member')
            ->chunk(1000, function ($chunk) use (&$totalMembersUpdated) {
                foreach ($chunk as $rec) {
                    DB::table('sacco_members')
                        ->where('member_id', $rec->loan_member)
                        ->update(['member_total_loan' => $rec->total_balance]);

                    $totalMembersUpdated++;
                }
            });

        Log::info("✅ Step 3 complete: Updated member_total_loan for {$totalMembersUpdated} members.");

        return response()->json([
            'status'  => 'success',
            'message' => 'All loan and member balances recalculated successfully.',
            'loans_updated'   => $totalLoansUpdated,
            'members_updated' => $totalMembersUpdated,
        ]);
    }
}