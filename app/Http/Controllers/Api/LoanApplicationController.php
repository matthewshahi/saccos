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
    | 2. Fetch authoritative member record
    |------------------------------------------------------------------
    */
    $memberRow = DB::table('sacco_members')
        ->where('member_id', (int) $member->member_id)
        ->where('member_active', 'Y')
        ->where('member_deleted', '<>', 'Y')
        ->first();

    if (!$memberRow) {
        return response()->json([
            'message' => 'Member not found or inactive.',
        ], 404);
    }

    $monthsInSacco = $this->getMemberMonthsInSacco($memberRow);

    /*
    |------------------------------------------------------------------
    | 3. Fetch active loan types and enrich with member-specific feedback
    |------------------------------------------------------------------
    */
    $loanTypes = DB::table('sacco_loan_types')
        ->where('loan_type_deleted', 'N')
        ->orderBy('loan_type_name')
        ->get()
        ->map(function ($t) use ($memberRow, $monthsInSacco) {
            $qualification = $this->buildLoanQualificationFeedback(
                $memberRow,
                $t,
                $monthsInSacco
            );

            return [
                // 🔑 Canonical identifier (never trust name)
                'loan_type_id' => (int) $t->loan_type_id,

                // 🏷 UI-facing fields
                'loan_name'    => $t->loan_type_name,
                'loan_code'    => $t->loan_type_code,

                // 💰 Financial constraints (authoritative)
                'interest_rate'   => (float) $t->loan_type_interest,
                'interest_type'   => $t->loan_type_interest_type,
                'max_amount'      => (float) $t->loan_type_max_amount,
                'duration_months' => (int) $t->loan_type_duration,

                // 📋 Qualification hints
                'qualification_period'  => (int) $t->loan_type_qualification_period,
                'instant_qualification' => $this->isInstantLoanType($t),

                // 🔒 Flags / existing fields
                'insurable' => strtoupper((string) ($t->loan_type_insurable ?? 'N')) === 'Y',
                'share_factor' => (int) $t->loan_type_share_factor,
                'loan_type_guaranteable_percent' => (float) ($t->loan_type_guaranteable_percent ?? 0),

                // ✅ New member-specific feedback
                'qualifies' => $qualification['qualifies'],
                'qualified_amount' => $qualification['qualified_amount'],
                'qualification_text' => $qualification['qualification_text'],
                'qualification_reason' => $qualification['qualification_reason'],
                'qualification_shortfall' => $qualification['qualification_shortfall'],
                'months_in_sacco' => $monthsInSacco,
                'current_total_loans' => $qualification['current_total_loans'],
            ];
        })
        ->values();

    /*
    |------------------------------------------------------------------
    | 4. Final response (Loan Application – Products Contract)
    |------------------------------------------------------------------
    */
    return response()->json([
        'loan_products' => $loanTypes,
    ]);
}

private function getMemberMonthsInSacco($member): int
{
    if (empty($member->member_date_joined)) {
        return 0;
    }

    try {
        return Carbon::parse($member->member_date_joined)->diffInMonths(now());
    } catch (\Throwable $e) {
        return 0;
    }
}
    private function buildLoanQualificationFeedback($member, $loanType, int $monthsInSacco): array
{
    $productMaxAmount = round((float) ($loanType->loan_type_max_amount ?? 0), 2);
    $qualificationPeriod = max(0, (int) ($loanType->loan_type_qualification_period ?? 0));
    $isInstant = $this->isInstantLoanType($loanType);

    $memberSavings = round((float) ($member->member_total_share ?? 0), 2);
    $memberTotalLoans = round((float) ($member->member_total_loan ?? 0), 2);

    /*
    |--------------------------------------------------------------------------
    | Membership age gate
    |--------------------------------------------------------------------------
    | Instant loans bypass this gate.
    |--------------------------------------------------------------------------
    */
    if (!$isInstant && $monthsInSacco < $qualificationPeriod) {
        $remainingMonths = max(0, $qualificationPeriod - $monthsInSacco);

        return [
            'qualifies' => false,
            'qualified_amount' => 0.00,
            'qualification_text' => 'You do not qualify for this loan yet.',
            'qualification_reason' => 'You need ' . $remainingMonths . ' more month' . ($remainingMonths === 1 ? '' : 's') . ' in the SACCO.',
            'qualification_shortfall' => 0.00,
            'current_total_loans' => $memberTotalLoans,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Amount rule
    |--------------------------------------------------------------------------
    | Normal loans:
    |   (member_total_share * loan_factor_or_shares) - member_total_loan
    |
    | Instant loans:
    |   use product max amount directly
    |--------------------------------------------------------------------------
    */
    if ($isInstant) {
        $qualifiedAmount = $productMaxAmount;
    } else {
        $loanFactor = $this->getLoanFactorOrSharesDefault();

        $rawQualifiedAmount = round(
            ($memberSavings * $loanFactor) - $memberTotalLoans,
            2
        );

        $rawQualifiedAmount = max(0, $rawQualifiedAmount);
        $qualifiedAmount = min($productMaxAmount, $rawQualifiedAmount);
    }

    $qualifiedAmount = round(max(0, $qualifiedAmount), 2);

    if ($qualifiedAmount < 1) {
        return [
            'qualifies' => false,
            'qualified_amount' => 0.00,
            'qualification_text' => 'You do not qualify for this loan at the moment.',
            'qualification_reason' => 'Your savings/deposits and current loans do not support this loan yet.',
            'qualification_shortfall' => 0.00,
            'current_total_loans' => $memberTotalLoans,
        ];
    }

    return [
        'qualifies' => true,
        'qualified_amount' => $qualifiedAmount,
        'qualification_text' => 'You qualify to apply for up to KES ' . number_format($qualifiedAmount, 2) . '.',
        'qualification_reason' => '',
        'qualification_shortfall' => 0.00,
        'current_total_loans' => $memberTotalLoans,
    ];
}

private function getLoanFactorOrSharesDefault(): float
{
    $exists = DB::table('sacco_defaults')
        ->where('default_name', 'loan_factor_or_shares')
        ->exists();

    if (!$exists) {
        DB::table('sacco_defaults')->insert([
            'default_name' => 'loan_factor_or_shares',
            'default_value' => '3',
            'default_userid' => null,
            'default_ip' => 'AUTO-API',
            'default_transdate' => now(),
        ]);
    }

    $value = DB::table('sacco_defaults')
        ->where('default_name', 'loan_factor_or_shares')
        ->value('default_value');

    $factor = (float) $value;

    return $factor > 0 ? $factor : 3.0;
}

private function isInstantLoanType($loanType): bool
{
    $raw = $loanType->loan_type_instant_qualification ?? 0;

    if (is_bool($raw)) {
        return $raw;
    }

    if (is_numeric($raw) && (int) $raw === 1) {
        return true;
    }

    $name = strtoupper(trim((string) ($loanType->loan_type_name ?? '')));
    $code = strtoupper(trim((string) ($loanType->loan_type_code ?? '')));
    $normalized = strtoupper(trim((string) $raw));

    return in_array($normalized, ['1', 'Y', 'YES', 'TRUE'], true)
        || str_contains($name, 'INSTANT')
        || str_contains($code, 'INSTANT');
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

    // Helpers (do NOT reveal names)
    $maskKeepLast3 = function ($v) {
        $s = trim((string)$v);
        if ($s === '') return $s;
        if (mb_strlen($s) <= 3) return '***';
        return str_repeat('*', mb_strlen($s) - 3) . mb_substr($s, -3);
    };

    $safeGuarantorLabel = function (string $saccoId, string $nationalId) use ($maskKeepLast3) {
        $sid = strtoupper(trim($saccoId));
        $nid = trim($nationalId);
        return "SACCO ID {$sid}, National ID " . $maskKeepLast3($nid);
    };

    // 2) Force JSON validation (no redirects)
    $validator = Validator::make($request->all(), [
        'loan_type_id'      => 'required|integer',
        'loan_category_id'  => 'nullable|integer',
        'amount'            => 'required|numeric|min:1',
        'duration_months'   => 'required|integer|min:1',
        'reason'            => 'nullable|string|max:255',

        'topup_loan_id'     => 'nullable|integer',
        'payroll_number'    => 'nullable|string|max:50',
        'designation'       => 'nullable|string|max:100',
        'employment_terms'  => 'nullable|string|max:100',

        // guarantors array from mobile app (deep validation below)
        'guarantors'        => 'nullable|array',

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

    // ------------------------------------------------------------
    // Validate + normalize guarantors BEFORE throttling/saving
    // ------------------------------------------------------------
    $loanType = DB::table('sacco_loan_types')
        ->select('loan_type_id', 'loan_type_guaranteable_percent')
        ->where('loan_type_id', (int) $validated['loan_type_id'])
        ->first();

    if (!$loanType) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid loan type.',
        ], 422);
    }

    $guarantePercent = (int) ($loanType->loan_type_guaranteable_percent ?? 0);

    $rawGuarantors = $request->input('guarantors', []);
    if (!is_array($rawGuarantors)) $rawGuarantors = [];

    // Optional cap (keeps you aligned with legacy max if set)
    $maxGuarantors = DB::table('sacco_defaults')
        ->where('default_name', 'maximum_no_of_guarantors')
        ->value('default_value');
    $maxGuarantors = is_numeric($maxGuarantors) ? (int) $maxGuarantors : 0;

    if ($maxGuarantors > 0 && count($rawGuarantors) > $maxGuarantors) {
        return response()->json([
            'success' => false,
            'message' => 'Guarantor validation failed.',
            'errors'  => [
                'guarantors' => "Too many guarantors submitted. Maximum allowed is {$maxGuarantors}.",
            ],
        ], 422);
    }

    // Legacy-required structures
    $legacyGuarantorNames   = [];  // ["NAME - (SACCO_ID)", ...] (internal only)
    $legacyGuarantorAmounts = [];  // [1000, 5000, ...]
    $legacyToSafeLabel      = [];  // map legacy label => safe label (no names)
    $hashGuarantors         = [];  // for payload hash (no names)

    if ($guarantePercent > 0) {
        $gErrors = [];
        $totalGuaranteed = 0.0;

        foreach (array_values($rawGuarantors) as $idx => $g) {
            if (!is_array($g)) {
                $gErrors["guarantors.$idx"] = "Guarantor row " . ($idx + 1) . " is invalid.";
                continue;
            }

            // support both keys: member_no OR member_sacco_id
            $saccoId    = strtoupper(trim((string)($g['member_sacco_id'] ?? $g['member_no'] ?? '')));
            $nationalId = trim((string)($g['national_id'] ?? $g['member_national_id'] ?? ''));
            $amountRaw  = $g['amount'] ?? null;

            // allow blank rows
            $allBlank = ($saccoId === '' && $nationalId === '' && ($amountRaw === null || $amountRaw === ''));
            if ($allBlank) continue;

            if ($saccoId === '') {
                $gErrors["guarantors.$idx.member_no"] =
                    "Guarantor row " . ($idx + 1) . ": Member Number (SACCO ID) is required.";
                continue;
            }

            if ($nationalId === '') {
                $gErrors["guarantors.$idx.national_id"] =
                    "Guarantor row " . ($idx + 1) . ": National ID is required.";
                continue;
            }

            $amount = is_numeric($amountRaw)
                ? (float) $amountRaw
                : (float) str_replace(',', '', (string) $amountRaw);

            if (!is_numeric($amount) || $amount <= 0) {
                $gErrors["guarantors.$idx.amount"] =
                    "Guarantor row " . ($idx + 1) . ": Amount must be greater than zero.";
                continue;
            }

            // validate member exists by sacco_id + national_id
            $gMember = DB::table('sacco_members')
                ->select('member_id', 'member_name', 'member_sacco_id', 'member_national_id')
                ->where('member_sacco_id', $saccoId)
                ->where('member_national_id', $nationalId)
                ->where('member_active', 'Y')
                ->where('member_deleted', '<>', 'Y')
                ->first();

            if (!$gMember) {
                $gErrors["guarantors.$idx"] =
                    "Guarantor row " . ($idx + 1) . ": No active member found for " .
                    $safeGuarantorLabel($saccoId, $nationalId) . ".";
                continue;
            }

            // Legacy internal label (do not expose to user)
            $legacyLabel = $gMember->member_name . ' - (' . $gMember->member_sacco_id . ')';
            $safeLabel   = $safeGuarantorLabel($gMember->member_sacco_id, $gMember->member_national_id);

            $legacyGuarantorNames[]   = $legacyLabel;
            $legacyGuarantorAmounts[] = $amount;
            $legacyToSafeLabel[$legacyLabel] = $safeLabel;

            // Include guarantors in hash WITHOUT names
            $hashGuarantors[] = [
                'member_sacco_id'    => strtoupper((string)$gMember->member_sacco_id),
                'member_national_id' => (string)$gMember->member_national_id,
                'amount'             => (string) number_format($amount, 2, '.', ''),
            ];

            $totalGuaranteed += $amount;
        }

        if (count($legacyGuarantorNames) === 0) {
            $gErrors["guarantors"] = "Guarantors are required for this loan type.";
        }

        $loanAmount = (float) $validated['amount'];
        $requiredGuaranteed = $loanAmount * $guarantePercent / 100;

        if ($requiredGuaranteed > 0 && $totalGuaranteed + 0.000001 < $requiredGuaranteed) {
            $gErrors["guarantors_total"] =
                "Total guaranteed amount is insufficient. Required: {$requiredGuaranteed}, provided: {$totalGuaranteed}.";
        }

        if (!empty($gErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Guarantor validation failed.',
                'errors'  => $gErrors,
            ], 422);
        }
    }

    // 3) Payload hash (include guarantors so duplicates are correct)
    $hashSource = [
        'member_id'         => $memberId,
        'loan_type_id'      => (int) $validated['loan_type_id'],
        'loan_category_id'  => (int) ($validated['loan_category_id'] ?? 1),
        'amount'            => (string) number_format((float) $validated['amount'], 2, '.', ''),
        'duration_months'   => (int) $validated['duration_months'],
        'reason'            => trim((string) ($validated['reason'] ?? '')),
        'topup_loan_id'     => $validated['topup_loan_id'] ?? null,
        'payroll_number'    => trim((string) ($validated['payroll_number'] ?? '')),
        'designation'       => trim((string) ($validated['designation'] ?? '')),
        'employment_terms'  => trim((string) ($validated['employment_terms'] ?? '')),
        'guarantors'        => $hashGuarantors,
        'agree'             => 1,
    ];

    $payloadHash = hash('sha256', json_encode($hashSource));

    // 4) Duplicates / cooldown (only applies to SUCCESSFUL submissions we saved)
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

    // 5) Forward to legacy FIRST (do NOT save mobile_app table unless legacy succeeds)
    $legacyBase = [
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
        'context'                          => 'api',
    ];

    if (!empty($legacyGuarantorNames)) {
        $legacyBase['guarantors_guarantor_name']   = $legacyGuarantorNames;
        $legacyBase['guarantors_amount_guaranteed'] = $legacyGuarantorAmounts;
    }

    $legacyRequest = Request::create('/api/legacy/submit-loan', 'POST', $legacyBase);
    $legacyRequest->setUserResolver(fn () => $request->user());
    $legacyRequest->headers->set('Accept', 'application/json');
    $legacyRequest->headers->set('X-Requested-With', 'XMLHttpRequest');

    $legacyResponse = app(\App\Http\Controllers\HomeController::class)
        ->submitLoanApplication($legacyRequest);

    // Normalize legacy response (expected to be JSON)
    if ($legacyResponse instanceof \Illuminate\Http\JsonResponse) {
        $status = $legacyResponse->status();
        $data   = $legacyResponse->getData(true);

        // sanitize any legacy strings that include names ("NAME - (SACCO_ID)")
        if (!empty($legacyToSafeLabel)) {
            if (isset($data['message']) && is_string($data['message'])) {
                foreach ($legacyToSafeLabel as $legacyLabel => $safeLabel) {
                    $data['message'] = str_replace($legacyLabel, $safeLabel, $data['message']);
                }
            }

            if (isset($data['errors']) && is_array($data['errors'])) {
                array_walk_recursive($data['errors'], function (&$val) use ($legacyToSafeLabel) {
                    if (!is_string($val)) return;
                    foreach ($legacyToSafeLabel as $legacyLabel => $safeLabel) {
                        $val = str_replace($legacyLabel, $safeLabel, $val);
                    }
                });
            }
        }

        // If legacy FAILED, do NOT save into sacco_mobile_app_loan_applications
        if ($status < 200 || $status >= 300) {
            return response()->json($data, $status);
        }

        // Legacy SUCCESS => NOW save to sacco_mobile_app_loan_applications (this is the only “logging” left)
        try {
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
        } catch (\Throwable $e) {
            // Do not break a successful loan submission; just record internally.
            Log::error('MOBILE LOAN APPLY: failed to insert audit row after legacy success', [
                'member_id' => $memberId,
                'error'     => $e->getMessage(),
            ]);
        }

        return response()->json($data, $status);
    }

    // Fallback (should not happen if Accept JSON is respected)
    return response()->json([
        'success' => false,
        'message' => 'Unexpected legacy response. Please try again or contact support.',
    ], 500);
}
}
