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

    /**
 * POST /api/auth/loan-applications/apply
 *
 * Receives a loan application request.
 * This step only validates structure and authentication.
 * NO eligibility logic, NO DB writes yet.
 */
/**
 * POST /api/auth/loan-applications/apply
 *
 * Submits a loan application request.
 * Fully server-authoritative. No client trust.
 */
public function apply(Request $request)
{
    /*
    |--------------------------------------------------
    | 1. Authenticate member
    |--------------------------------------------------
    */
    $member = $request->user();

    if (!$member || !isset($member->member_id)) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    $memberId = (int) $member->member_id;

    /*
    |--------------------------------------------------
    | 2. Validate request shape (NOT business rules)
    |--------------------------------------------------
    */
    $data = $request->validate([
        'loan_type_id'  => 'required|integer',
        'amount'        => 'required|numeric|min:1',
        'topup_loan_id' => 'nullable|integer',
        'employment'    => 'nullable|array',
        'employment.*'  => 'nullable',
        'declaration'   => 'required|boolean',
    ]);

    if ($data['declaration'] !== true) {
        return response()->json([
            'message' => 'Loan declaration must be accepted.',
        ], 422);
    }

    /*
    |--------------------------------------------------
    | 3. Fetch loan product (authoritative)
    |--------------------------------------------------
    */
    $loanType = DB::table('sacco_loan_types')
        ->where('loan_type_id', $data['loan_type_id'])
        ->where('loan_type_deleted', 'N')
        ->first();

    if (!$loanType) {
        return response()->json([
            'message' => 'Invalid or inactive loan product.',
        ], 422);
    }

    /*
    |--------------------------------------------------
    | 4. Fetch member financial posture
    |--------------------------------------------------
    */
    $memberRow = DB::table('sacco_members')
        ->where('member_id', $memberId)
        ->where('member_active', 'Y')
        ->first();

    if (!$memberRow) {
        return response()->json([
            'message' => 'Member not found or inactive.',
        ], 422);
    }

    $totalShares  = (float) ($memberRow->member_total_share ?? 0);
    $monthsInSacco = 0;

    if (!empty($memberRow->member_date_joined)) {
        $monthsInSacco = \Carbon\Carbon::parse($memberRow->member_date_joined)
            ->diffInMonths(now());
    }

    /*
    |--------------------------------------------------
    | 5. Qualification checks
    |--------------------------------------------------
    */
    $qualificationPeriod = (int) ($loanType->loan_type_qualification_period ?? 0);
    $instantQualification = $loanType->loan_type_instant_qualification === 'Y';

    if (!$instantQualification && $monthsInSacco < $qualificationPeriod) {
        return response()->json([
            'message' => 'Member has not met the qualification period.',
        ], 422);
    }

    if ($memberRow->member_is_junior === 'Y' && empty($memberRow->member_guardian_id)) {
        return response()->json([
            'message' => 'Junior member requires a guardian.',
        ], 422);
    }

    /*
    |--------------------------------------------------
    | 6. Validate top-up loan (if any) and recompute balance
    |--------------------------------------------------
    */
    $topupBalance = 0;
    $topupLoanId  = null;

    if (!empty($data['topup_loan_id'])) {
        $topupLoan = DB::table('sacco_loans')
            ->where('loan_id', $data['topup_loan_id'])
            ->where('loan_member', $memberId)
            ->where('loan_stoped', 'N')
            ->first();

        if (!$topupLoan) {
            return response()->json([
                'message' => 'Invalid top-up loan selected.',
            ], 422);
        }

        $principal = (float) ($topupLoan->loan_amount ?? 0);
        $paid      = (float) ($topupLoan->loan_loan_paid ?? 0);

        $topupBalance = max(0, $principal - $paid);
        $topupLoanId  = (int) $topupLoan->loan_id;
    }

    /*
    |--------------------------------------------------
    | 7. Compute maximum allowable amount
    |--------------------------------------------------
    */
    $shareFactor = (int) ($loanType->loan_type_share_factor ?? 1);
    $maxByShares = $totalShares * $shareFactor;
    $maxByProduct = (float) ($loanType->loan_type_max_amount ?? 0);

    $maximumAllowed = min($maxByShares, $maxByProduct);

    $requestedAmount = (float) $data['amount'];

    if ($requestedAmount > $maximumAllowed) {
        return response()->json([
            'message' => 'Requested amount exceeds allowable limit.',
            'limits' => [
                'maximum_allowed' => $maximumAllowed,
            ],
        ], 422);
    }

    /*
    |--------------------------------------------------
    | 8. Persist loan application (pending)
    |--------------------------------------------------
    */
    $applicationId = DB::table('sacco_loan_applications')->insertGetId([
        'application_member_id'   => $memberId,
        'application_loan_type'   => $loanType->loan_type_id,
        'application_amount'      => $requestedAmount,
        'application_topup_loan'  => $topupLoanId,
        'application_status'      => 'PENDING',
        'application_employment'  => isset($data['employment'])
            ? json_encode($data['employment'])
            : null,
        'application_created_at'  => now(),
        'application_created_by'  => $memberId,
        'application_ip'          => $request->ip(),
    ]);

    /*
    |--------------------------------------------------
    | 9. Final response
    |--------------------------------------------------
    */
    return response()->json([
        'status'        => 'submitted',
        'message'       => 'Loan application submitted successfully.',
        'application_id'=> $applicationId,
    ], 201);
}


}
