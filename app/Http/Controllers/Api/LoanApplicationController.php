<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanApplicationController extends Controller
{
    /**
     * GET /api/auth/loan-applications/products
     *
     * Returns loan types available for application.
     * Read-only, authenticated, integrity-first.
     */
    public function products(Request $request)
    {
        /*
        |------------------------------------------------------------------
        | 1. Authenticate member (authoritative, never client-provided)
        |------------------------------------------------------------------
        */
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |------------------------------------------------------------------
        | 2. Fetch active loan types only
        |------------------------------------------------------------------
        | loan_type_deleted = 'N' → authoritative active filter
        |------------------------------------------------------------------
        */
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', 'N')
            ->orderBy('loan_type_name')
            ->get()
            ->map(function ($t) {
                return [
                    // 🔑 Canonical identifier (never trust name)
                    'loan_type_id' => (int) $t->loan_type_id,

                    // 🏷 UI-facing fields
                    'loan_name'    => $t->loan_type_name,
                    'loan_code'    => $t->loan_type_code,

                    // 💰 Financial constraints (authoritative)
                    'interest_rate'        => (float) $t->loan_type_interest,
                    'interest_type'        => $t->loan_type_interest_type,
                    'max_amount'           => (float) $t->loan_type_max_amount,
                    'duration_months'      => (int) $t->loan_type_duration,

                    // 📋 Qualification hints (non-enforcing, UI guidance only)
                    'qualification_period' => (int) $t->loan_type_qualification_period,
                    'instant_qualification' => $t->loan_type_instant_qualification === 'Y',

                    // 🔒 Flags (future use)
                    'insurable'            => $t->loan_type_insurable === 'Y',
                    'share_factor'         => (int) $t->loan_type_share_factor,
                ];
            })
            ->values();

        /*
        |------------------------------------------------------------------
        | 3. Final response (Loan Application – Products Contract)
        |------------------------------------------------------------------
        */
        return response()->json([
            'loan_products' => $loanTypes,
        ]);
    }
    /**
     * GET /api/auth/loan-applications/context
     *
     * Returns authenticated member context for loan application UI.
     */
    public function context(Request $request)
    {
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Calculate membership duration (months)
        $monthsInSacco = null;
        if (!empty($member->member_date_joined)) {
            $joined = \Carbon\Carbon::parse($member->member_date_joined);
            $monthsInSacco = $joined->diffInMonths(now());
        }

        return response()->json([
            'member' => [
                'member_id'        => (int) $member->member_id,
                'member_name'      => $member->member_name,
                'member_sacco_id'  => $member->member_sacco_id,
                'phone'            => $member->member_phone_no,
                'email'            => $member->member_email,
                'gender'           => $member->member_gender,

                'date_joined'      => $member->member_date_joined,
                'months_in_sacco'  => $monthsInSacco,

                // Financial posture (display only)
                'total_shares'         => (float) ($member->member_total_share ?? 0),
                'total_loans'          => (float) ($member->member_total_loan ?? 0),
                'share_capital'        => (float) ($member->member_total_share_capital ?? 0),

                // Special flags
                'is_junior'        => $member->member_is_junior === 'Y',
                'guardian_id'      => $member->member_guardian_id,

                // Bank details (read-only)
                'bank' => [
                    'name'    => $member->bank_name,
                    'branch' => $member->bank_branch,
                    'account' => $member->bank_account_number,
                ],
            ],
        ]);
    }
    /**
     * GET /api/auth/loan-applications/topup-loans
     *
     * Returns member's outstanding loans eligible for top-up.
     */
    public function topupLoans(Request $request)
    {
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $loans = DB::table('sacco_loans as l')
            ->join('sacco_loan_types as t', 't.loan_type_id', '=', 'l.loan_loan_type')
            ->where('l.loan_member', $member->member_id)
            ->where('l.loan_stoped', 'N')
            ->where('t.loan_type_deleted', 'N')
            ->get()
            ->map(function ($loan) {
                $amountTaken = (float) ($loan->loan_amount ?? 0);
                $amountPaid  = (float) ($loan->loan_loan_paid ?? 0);

                $balance = $amountTaken - $amountPaid;

                return [
                    'loan_id'        => (int) $loan->loan_id,
                    'loan_type_id'   => (int) $loan->loan_loan_type,
                    'loan_type_name' => $loan->loan_type_name,

                    // Display format required by UI
                    'label' => sprintf(
                        '%s (%d)',
                        $loan->loan_type_name,
                        $loan->loan_id
                    ),

                    'amount_taken' => $amountTaken,
                    'amount_paid'  => $amountPaid,
                    'balance'      => round($balance, 2),
                ];
            })
            ->filter(function ($loan) {
                // Eligible only if meaningful outstanding balance
                return $loan['balance'] > 1;
            })
            ->values();

        return response()->json([
            'topup_loans' => $loans,
        ]);
    }
}
