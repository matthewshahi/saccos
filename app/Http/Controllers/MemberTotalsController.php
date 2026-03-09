<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberTotalsController extends Controller
{
    public function recalculateAll()
    {
        $summary = [];

        DB::transaction(function () use (&$summary) {

            /* =========================================================
             * STEP 0: ✅ Correct loan paid in sacco_loans FIRST
             * - Reset loan_loan_paid to 0
             * - Update each loan based on payments (by loan_id relationship)
             * - Negative balances allowed => NO capping
             * ========================================================= */

            // 0.1 Reset loan paid to zero (NOT NULL)
            $summary['loans_paid_reset_rows'] = DB::table('sacco_loans')->update([
                'loan_loan_paid' => 0
            ]);

            // 0.2 Update per-loan paid from transactional table
            $summary['loans_paid_updated_rows'] = DB::affectingStatement("
                UPDATE sacco_loans l
                JOIN (
                    SELECT
                        loan_payments_loan_id AS loan_id,
                        SUM(COALESCE(loan_payments_amount, 0)) AS paid
                    FROM sacco_loan_payments
                    WHERE loan_payments_loan_id IS NOT NULL
                    GROUP BY loan_payments_loan_id
                ) p ON p.loan_id = l.loan_id
                SET l.loan_loan_paid = p.paid
            ");

            /* =========================================================
             * STEP 1: Reset member totals to zero (NOW includes loans)
             * ========================================================= */
            $summary['members_reset_rows'] = DB::table('sacco_members')->update([
                'member_total_share'         => 0,
                'member_total_fosa'          => 0,
                'member_total_share_capital' => 0,
                'member_total_loan'          => 0,
            ]);

            /* =========================================================
             * STEP 2: member_total_share from sacco_shares
             * ========================================================= */
            $shares = DB::table('sacco_shares')
                ->select('share_member_id', DB::raw('SUM(COALESCE(share_amount_paying,0)) as total'))
                ->whereNotNull('share_member_id')
                ->groupBy('share_member_id')
                ->get();

            foreach ($shares as $record) {
                DB::table('sacco_members')
                    ->where('member_id', $record->share_member_id)
                    ->update(['member_total_share' => $record->total]);
            }

            /* =========================================================
             * STEP 3: member_total_fosa from sacco_fosas
             * ========================================================= */
            $fosas = DB::table('sacco_fosas')
                ->select('fosa_member_id', DB::raw('SUM(COALESCE(fosa_amount_paying,0)) as total'))
                ->whereNotNull('fosa_member_id')
                ->groupBy('fosa_member_id')
                ->get();

            foreach ($fosas as $record) {
                DB::table('sacco_members')
                    ->where('member_id', $record->fosa_member_id)
                    ->update(['member_total_fosa' => $record->total]);
            }

            /* =========================================================
             * STEP 4: member_total_share_capital from sacco_capital_shares
             * ========================================================= */
            $capitalShares = DB::table('sacco_capital_shares')
                ->select('share_capitalmember_id', DB::raw('SUM(COALESCE(share_capitalamount_paying,0)) as total'))
                ->whereNotNull('share_capitalmember_id')
                ->groupBy('share_capitalmember_id')
                ->get();

            foreach ($capitalShares as $record) {
                DB::table('sacco_members')
                    ->where('member_id', $record->share_capitalmember_id)
                    ->update(['member_total_share_capital' => $record->total]);
            }

            /* =========================================================
             * STEP 5: ✅ member_total_loan from sacco_loans
             * (after loan_loan_paid is correct; negative balances allowed)
             * ========================================================= */
            $summary['members_loan_totals_updated_rows'] = DB::affectingStatement("
                UPDATE sacco_members m
                LEFT JOIN (
                    SELECT
                        loan_member,
                        SUM(COALESCE(loan_amount,0) - COALESCE(loan_loan_paid,0)) AS balance
                    FROM sacco_loans
                    WHERE loan_member IS NOT NULL
                    GROUP BY loan_member
                ) x ON x.loan_member = m.member_id
                SET m.member_total_loan = COALESCE(x.balance, 0)
            ");

            $summary['shares_updated']  = $shares->count();
            $summary['fosas_updated']   = $fosas->count();
            $summary['capital_updated'] = $capitalShares->count();
        });

        dd($summary);
    }
}