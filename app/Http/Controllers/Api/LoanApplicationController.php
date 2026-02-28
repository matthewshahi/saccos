<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

    use Illuminate\Support\Facades\Validator;
 
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
            // ->where('loan_type_guaranteable_percent', 0)
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
    // 1) Auth
    $member = $request->user();
    if (!$member || !isset($member->member_id)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.'
        ], 401);
    }

    $memberId = (int) $member->member_id;

    // 2) Force JSON validation (no redirects)
    $validator = Validator::make($request->all(), [
        'loan_type_id'      => 'required|integer',
        'loan_category_id'  => 'nullable|integer',   // you reference it later
        'amount'            => 'required|numeric|min:1',
        'duration_months'   => 'required|integer|min:1',
        'reason'            => 'nullable|string|max:255',

        'topup_loan_id'     => 'nullable|integer',
        'payroll_number'    => 'nullable|string|max:50',
        'designation'       => 'nullable|string|max:100',
        'employment_terms'  => 'nullable|string|max:100',

        // IMPORTANT: do this instead of manual FILTER_VALIDATE_BOOLEAN
        'agree'             => 'required|accepted',
    ], [
        'agree.accepted' => 'You must agree to the terms and conditions before submitting a loan application.',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $validator->errors(),
        ], 422);
    }

    $validated = $validator->validated();

    // 3) Stable payload hash (avoid float weirdness by normalizing)
    $hashSource = [
        'member_id'        => $memberId,
        'loan_type_id'      => (int) $validated['loan_type_id'],
        'loan_category_id'  => (int) ($validated['loan_category_id'] ?? 1),
        'amount'            => (string) number_format((float) $validated['amount'], 2, '.', ''),
        'duration_months'   => (int) $validated['duration_months'],
        'reason'            => trim((string) ($validated['reason'] ?? '')),
        'topup_loan_id'     => $validated['topup_loan_id'] ?? null,
        'payroll_number'    => trim((string) ($validated['payroll_number'] ?? '')),
        'designation'       => trim((string) ($validated['designation'] ?? '')),
        'employment_terms'  => trim((string) ($validated['employment_terms'] ?? '')),
        'agree'             => 1,
    ];

    $payloadHash = hash('sha256', json_encode($hashSource));

    // 4) Duplicates
    $existsExact = DB::table('sacco_mobile_app_loan_applications')
        ->where('mobile_app_payload_hash', $payloadHash)
        ->exists();

    if ($existsExact) {
        return response()->json([
            'success' => false,
            'message' => 'A similar loan application has already been submitted. Please wait before trying again.'
        ], 409);
    }

    $recentCutoff = Carbon::now()->subMinutes(2);

    $existsRecent = DB::table('sacco_mobile_app_loan_applications')
        ->where('mobile_app_member_id', $memberId)
        ->where('mobile_app_submitted_at', '>=', $recentCutoff)
        ->exists();

    if ($existsRecent) {
        return response()->json([
            'success' => false,
            'message' => 'You recently submitted a loan application. Please wait about 2 minutes before trying again.'
        ], 429);
    }

    // 5) Save + forward safely
    return DB::transaction(function () use ($request, $member, $memberId, $validated, $payloadHash) {

        DB::table('sacco_mobile_app_loan_applications')->insert([
            'mobile_app_member_id'         => $memberId,
            'mobile_app_loan_type_id'      => (int) $validated['loan_type_id'],
            'mobile_app_amount'            => (float) $validated['amount'],
            'mobile_app_duration_months'   => (int) $validated['duration_months'],
            'mobile_app_reason'            => $validated['reason'] ?? null,
            'mobile_app_topup_loan_id'     => $validated['topup_loan_id'] ?? null,

            'mobile_app_payroll_number'    => $validated['payroll_number'] ?? null,
            'mobile_app_designation'       => $validated['designation'] ?? null,
            'mobile_app_employment_terms'  => $validated['employment_terms'] ?? null,

            'mobile_app_agree'             => 1,
            'mobile_app_payload_hash'      => $payloadHash,
            'mobile_app_submitted_at'      => now(),
            'mobile_app_status'            => 'pending',

            'mobile_app_submitted_ip'      => $request->ip(),
            'mobile_app_submitted_by'      => 'mobile_app',
        ]);

        // Forward to legacy (make it look like an AJAX/JSON request)
        $legacyRequest = Request::create('/legacy/submit-loan', 'POST', [
            'batch_trans_member_id'            => $memberId,
            'batch_trans_member_name'          => $member->member_name,
            'batch_trans_loan_type'            => (int) $validated['loan_type_id'],
            'batch_trans_loan_category'        => (int) ($validated['loan_category_id'] ?? 1),
            'batch_trans_loan_amount'          => (float) $validated['amount'],
            'batch_trans_loan_duration'        => (int) $validated['duration_months'],
            'batch_trans_description'          => $validated['reason'] ?? null,
            'batch_trans_loan_to_top_up'       => $validated['topup_loan_id'] ?? null,
            'batch_trans_payroll_number'       => $validated['payroll_number'] ?? null,
            'batch_trans_present_designation'  => $validated['designation'] ?? null,
            'batch_trans_terms_of_employment'  => $validated['employment_terms'] ?? null,
        ]);

        // --------------------------------------------------
// Force API context for legacy handler
// --------------------------------------------------
$legacyRequest = Request::create(
    '/api/legacy/submit-loan', // MUST match api/*
    'POST',
    array_merge(
        $legacyRequest->request->all() ?? [],
        [
            'context' => 'api', // explicit override (belt + braces)
        ]
    )
);

// Bind authenticated user
$legacyRequest->setUserResolver(fn () => $request->user());

// Force API headers
$legacyRequest->headers->set('Accept', 'application/json');
$legacyRequest->headers->set('X-Requested-With', 'XMLHttpRequest');

// --------------------------------------------------
// 🔎 Diagnostics — REMOVE after confirmation
// --------------------------------------------------
Log::info('Forwarding loan application to legacy handler', [
    'legacy_path'   => $legacyRequest->path(),
    'is_api_path'   => $legacyRequest->is('api/*'),
    'context_param' => $legacyRequest->get('context'),
    'expects_json'  => $legacyRequest->expectsJson(),
    'member_id'     => optional($request->user())->member_id,
]);

// Execute legacy handler
$legacyResponse = app(\App\Http\Controllers\HomeController::class)
    ->submitLoanApplication($legacyRequest);

// --------------------------------------------------
// 🔎 Capture legacy response shape
// --------------------------------------------------
Log::info('Legacy loan submission response', [
    'response_type' => is_object($legacyResponse)
        ? get_class($legacyResponse)
        : gettype($legacyResponse),
    'status'        => method_exists($legacyResponse, 'status')
        ? $legacyResponse->status()
        : null,
]);

// --------------------------------------------------
// Normalize response for API
// --------------------------------------------------
if ($legacyResponse instanceof \Illuminate\Http\JsonResponse) {
    return $legacyResponse;
}


        // Otherwise normalize to API response
        return response()->json([
            'success' => true,
            'message' => 'Your loan application has been received and saved for further processing.',
        ], 201);
    });
}

}
