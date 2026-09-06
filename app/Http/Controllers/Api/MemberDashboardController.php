<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MemberDashboardController extends Controller
{
    /**
     * GET /api/auth/dashboard
     *
     * Returns dashboard data for the authenticated member.
     * Uses DB query builder only (no Eloquent models).
     */
    public function index(Request $request)
{
    // 🔐 Authenticated member
    $member = $request->user();

    if (!$member || !isset($member->member_id)) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    $memberId  = $member->member_id;
    // $nowPeriod = (int) date('Ym');
    $nowPeriod = date('Ym');

    /*
|--------------------------------------------------------------------------
| 1. Core member (authoritative)
|--------------------------------------------------------------------------
*/
    $row = DB::table('sacco_members')
        ->where('member_id', $memberId)
        ->where('member_active', 'Y')
        ->first();

    if (!$row) {
        return response()->json(['message' => 'Member not found or inactive.'], 404);
    }

    /*
|--------------------------------------------------------------------------
| 2. Financial snapshot (home dashboard)
|--------------------------------------------------------------------------
*/

    $fosaBreakdown = $this->getFosaBreakdown($memberId);

    $snapshot = [
        'shares'        => (float) ($row->member_total_share ?? 0),
        'capital'       => (float) ($row->member_total_share_capital ?? 0),
        'other_savings' => $fosaBreakdown,
        'loans'         => (float) ($row->member_total_loan ?? 0),
    ];


    /*
|--------------------------------------------------------------------------
| 3. Loan attention signals (non-judgemental)
|--------------------------------------------------------------------------
| Flags loans whose last repayment is ≥ 2 periods behind.
| Uses YYYYMM arithmetic (consistent with SACCO data model).
|--------------------------------------------------------------------------
*/
    $rawLoans = DB::table('sacco_loans as l')
        ->leftJoin(
            'sacco_loan_payments as p',
            'p.loan_payments_loan_id',
            '=',
            'l.loan_id'
        )
        ->join(
            'sacco_loan_types as t',
            't.loan_type_id',
            '=',
            'l.loan_loan_type'
        )
        ->where('l.loan_member', $memberId)
        ->where('l.loan_stoped', 'N')
        ->groupBy(
            'l.loan_id',
            'l.loan_amount',
            'l.loan_loan_paid',
            'l.loan_taken_period',
            't.loan_type_name'
        )
        ->selectRaw('
        l.loan_id,
        t.loan_type_name,
        l.loan_amount,
        COALESCE(l.loan_loan_paid, 0) as paid,
        COALESCE(MAX(p.loan_payments_period), l.loan_taken_period) as last_period
    ')
        ->get();

    $attention = [];

    foreach ($rawLoans as $loan) {
        $principal = (float) ($loan->loan_amount ?? 0);
        $paid      = (float) ($loan->paid ?? 0);
        $balance   = $principal - $paid;

        // Skip cleared or near-zero balances
        if ($balance <= 1) {
            continue;
        }

        // $monthsBehind = $nowPeriod - (int) $loan->last_period;

        $monthsBehind = $this->diffPeriodsInMonths($loan->last_period, $nowPeriod);

        if ($monthsBehind >= 2) {
            $attention[] = [
                'loan_id'   => $loan->loan_id,
                'label'     => strtoupper($loan->loan_type_name)
                    . ' (' . $loan->loan_id . ')',
                'balance'   => $balance,
                'last_paid' => (int) $loan->last_period,
                'months'    => $monthsBehind,
                'message'   => 'Repayment review recommended',
            ];
        }
    }


    /*
|--------------------------------------------------------------------------
| 4. Final response (Home Dashboard Contract)
|--------------------------------------------------------------------------
*/

    $quickPayments = $this->getQuickPayments($memberId);

    // $duesItems = $this->getExpectedFosaDues($row);
    $duesItems = $this->getExpectedContributionDues($row);
    $asOf = $this->getContributionAsOfDate($row);

    $duesTotal = 0.0;
    foreach ($duesItems as $it) {
        $duesTotal += (float) ($it['balance'] ?? 0);
    }

    return response()->json([
        'member' => [
            'name'          => $row->member_name,
            'member_number' => 'SACCO / ' . (string) $row->member_sacco_id,
            'status' => $row->member_active === 'Y'
                ? 'Active'
                : 'Inactive',
        ],

        'snapshot' => $snapshot,

        // ✅ ADD THIS
        'quick_payments' => $quickPayments,

        'attention' => [
            'count' => count($attention),
            'items' => $attention,
        ],
        'dues' => [
            'count'         => count($duesItems),
            'total_balance' => round($duesTotal, 2),
            'as_of'         => $asOf->toDateString(),
            'items'         => $duesItems,
        ],
    ]);
}
    /**
     * --------------------------------------------------------------------------
     * Quick Payments (M-PESA Tap-to-Pay)
     * Builds all payable payment codes for the member
     * --------------------------------------------------------------------------
     */

    private function periodToYearMonth(?string $period): ?array
{
    $period = trim((string) $period);

    if (!preg_match('/^\d{6}$/', $period)) {
        return null;
    }

    $year  = (int) substr($period, 0, 4);
    $month = (int) substr($period, 4, 2);

    if ($month < 1 || $month > 12) {
        return null;
    }

    return [$year, $month];
}

private function diffPeriodsInMonths($fromPeriod, $toPeriod): int
{
    $from = $this->periodToYearMonth((string) $fromPeriod);
    $to   = $this->periodToYearMonth((string) $toPeriod);

    if (!$from || !$to) {
        return 0;
    }

    [$fromYear, $fromMonth] = $from;
    [$toYear, $toMonth]     = $to;

    return max(0, (($toYear - $fromYear) * 12) + ($toMonth - $fromMonth));
}

    private function getExpectedFosaDues($memberRow): array
{
    $memberId = (int) $memberRow->member_id;

    // ✅ Calendar dates only
    $memberJoinedAt = !empty($memberRow->member_date_joined)
        ? Carbon::parse($memberRow->member_date_joined)->startOfDay()
        : Carbon::now()->startOfDay();

    // ✅ As-of date (end of day), optionally stop at deactivation
    $asOf = Carbon::now()->endOfDay();
    if (!empty($memberRow->member_date_dactivated)) {
        $deact = Carbon::parse($memberRow->member_date_dactivated)->endOfDay();
        if ($deact->lessThan($asOf)) {
            $asOf = $deact;
        }
    }

    // ✅ Friendly labels for app
    $periodLabels = [
        'one_time' => 'One-time',
        'daily'    => 'Daily',
        'weekly'   => 'Weekly',
        'monthly'  => 'Monthly',
        'yearly'   => 'Yearly',
    ];

    $unitLabels = [
        'one_time' => 'time',
        'daily'    => 'days',
        'weekly'   => 'weeks',
        'monthly'  => 'months',
        'yearly'   => 'years',
    ];

    // ✅ Only expected rules
    $types = DB::table('sacco_fosa_types')
        ->where('type_active', 'Y')
        ->whereNotNull('expected_amount')
        ->whereNotNull('expected_period')
        ->select('type_id', 'type_name', 'type_prefix', 'expected_amount', 'expected_period', 'created_at')
        ->orderBy('type_id')
        ->get();

    if ($types->isEmpty()) return [];

    $typeIds = $types->pluck('type_id')->map(fn($v) => (int) $v)->all();

    // ✅ baseStart = max(memberJoinedAt, earliest type created_at)
    $earliestTypeCreatedAt = null;
    foreach ($types as $t) {
        if (!empty($t->created_at)) {
            $d = Carbon::parse($t->created_at)->startOfDay();
            if ($earliestTypeCreatedAt === null || $d->lessThan($earliestTypeCreatedAt)) {
                $earliestTypeCreatedAt = $d;
            }
        }
    }
    if ($earliestTypeCreatedAt === null) $earliestTypeCreatedAt = $memberJoinedAt;

    $baseStart = $memberJoinedAt->copy();
    if ($earliestTypeCreatedAt->greaterThan($baseStart)) $baseStart = $earliestTypeCreatedAt;

    // ✅ Pull txns once (calendar date filter)
    $txRows = DB::table('sacco_fosas')
        ->where('fosa_member_id', $memberId)
        ->whereIn('fosa_type_id', $typeIds)
        ->whereRaw('COALESCE(fosa_date_paid, fosa_transdate) >= ?', [$baseStart->toDateTimeString()])
        ->whereRaw('COALESCE(fosa_date_paid, fosa_transdate) <= ?', [$asOf->toDateTimeString()])
        ->selectRaw('
            fosa_type_id,
            COALESCE(fosa_amount_paying,0) as amount,
            COALESCE(fosa_date_paid, fosa_transdate) as paid_at
        ')
        ->get();

    $txByType = [];
    foreach ($txRows as $r) {
        $tid = (int) $r->fosa_type_id;
        $txByType[$tid][] = [
            'amount'  => (float) $r->amount,
            'paid_at' => $r->paid_at ? Carbon::parse($r->paid_at) : null,
        ];
    }

    $items = [];

    foreach ($types as $t) {
        $typeId = (int) $t->type_id;
        $amount = (float) $t->expected_amount;
        $period = (string) $t->expected_period;

        // ✅ Ignore zero/negative expected config (keeps output clean)
        if ($amount <= 0) continue;

        // ✅ Effective start = max(member join, type created)
        $typeCreatedAt = !empty($t->created_at)
            ? Carbon::parse($t->created_at)->startOfDay()
            : $memberJoinedAt;

        $effectiveStart = $memberJoinedAt->copy();
        if ($typeCreatedAt->greaterThan($effectiveStart)) $effectiveStart = $typeCreatedAt;

        if ($effectiveStart->greaterThan($asOf)) continue;

        // ✅ Expected total (calendar based)
        $expectedTotal = 0.0;
        $units = null;

        if ($period === 'one_time') {
            $units = 1;
            $expectedTotal = $amount;
        } elseif ($period === 'daily') {
            $days = $effectiveStart->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay()) + 1;
            $units = $days;
            $expectedTotal = $amount * $days;
        } elseif ($period === 'weekly') {
            $days = $effectiveStart->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay()) + 1;
            $weeks = (int) ceil($days / 7);
            $units = $weeks;
            $expectedTotal = $amount * $weeks;
        } elseif ($period === 'monthly') {
            $startM = $effectiveStart->copy()->startOfMonth();
            $endM   = $asOf->copy()->startOfMonth();
            $months = $startM->diffInMonths($endM) + 1; // inclusive
            $units = $months;
            $expectedTotal = $amount * $months;
        } elseif ($period === 'yearly') {
            $startY = $effectiveStart->copy()->startOfYear();
            $endY   = $asOf->copy()->startOfYear();
            $years = $startY->diffInYears($endY) + 1; // inclusive
            $units = $years;
            $expectedTotal = $amount * $years;
        } else {
            continue;
        }

        // ✅ Paid total AFTER effectiveStart (prevents backdating)
        $paid = 0.0;
        $lastPaidAt = null;

        foreach (($txByType[$typeId] ?? []) as $tx) {
            if (!$tx['paid_at']) continue;

            if ($tx['paid_at']->greaterThanOrEqualTo($effectiveStart) && $tx['paid_at']->lessThanOrEqualTo($asOf)) {
                $paid += (float) $tx['amount'];
                if ($lastPaidAt === null || $tx['paid_at']->greaterThan($lastPaidAt)) {
                    $lastPaidAt = $tx['paid_at'];
                }
            }
        }

        $balance = $expectedTotal - $paid;

        // ✅ Only "red" items
        if ($balance > 0.01) {
            $items[] = [
                'type_id'         => $typeId,
                'type_name'       => (string) $t->type_name,
                'type_prefix'     => (string) $t->type_prefix,

                'expected_period' => $period,
                'period_label'    => $periodLabels[$period] ?? strtoupper($period),

                'expected_amount' => round($amount, 2),
                'expected_total'  => round($expectedTotal, 2),
                'paid_total'      => round($paid, 2),
                'balance'         => round($balance, 2),

                'units'           => $units,
                'unit_label'      => $unitLabels[$period] ?? 'units',

                'effective_start' => $effectiveStart->toDateString(),
                'as_of'           => $asOf->toDateString(),
                'last_paid_at'    => $lastPaidAt ? $lastPaidAt->toDateTimeString() : null,
            ];
        }
    }

    return $items;
} 
private function getQuickPayments(int $memberId): array
    {
        // Savings & Capital (static)
        $payments = [
            'savings' => [
                'label' => 'Savings',
                'code'  => 'SH' . $memberId,
            ],
            'capital' => [
                'label' => 'Capital Shares',
                'code'  => 'CA' . $memberId,
            ],
            'loans' => [],
            'fosa'  => [],
        ];

        // Outstanding loans
        $loans = DB::table('sacco_loans as l')
            ->join('sacco_loan_types as t', 'l.loan_loan_type', '=', 't.loan_type_id')
            ->where('l.loan_member', $memberId)
            ->whereRaw('l.loan_amount - COALESCE(l.loan_loan_paid,0) > 1')
            ->select(
                'l.loan_id',
                't.loan_type_name'
            )
            ->orderByDesc('l.loan_taken_period')
            ->get();

        foreach ($loans as $loan) {
            $payments['loans'][] = [
                'loan_id' => $loan->loan_id,
                'label'   => strtoupper($loan->loan_type_name),
                'code'    => 'LN' . $loan->loan_id,
            ];
        }

        // Active FOSA types
        $fosaTypes = DB::table('sacco_fosa_types')
            ->where('type_active', 'Y')
            ->orderBy('type_prefix')
            ->get();

        foreach ($fosaTypes as $type) {
            $payments['fosa'][] = [
                'type'   => strtoupper($type->type_name),
                'prefix' => $type->type_prefix,
                'code'  => $type->type_prefix . $memberId,
            ];
        }

        return $payments;
    }

    private function getFosaBreakdown(int $memberId): array
    {
        $rows = DB::table('sacco_fosas')
            ->leftJoin(
                'sacco_fosa_types',
                'sacco_fosas.fosa_type_id',
                '=',
                'sacco_fosa_types.type_id'
            )
            ->where('sacco_fosas.fosa_member_id', $memberId)
            ->select(
                'sacco_fosa_types.type_name',
                'sacco_fosas.fosa_amount_paying'
            )
            ->get();

        $grouped = $rows->groupBy(function ($r) {
            return $r->type_name ?: 'UNSPECIFIED';
        });

        $breakdown = [];

        foreach ($grouped as $type => $items) {
            $breakdown[$type] = $items
                ->sum(fn($i) => (float) $i->fosa_amount_paying);
        }

        return $breakdown;
    }



    public function savings(Request $request)
    {
        // 🔐 Authenticated member (same pattern as dashboard)
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = $member->member_id;

        /*
    |----------------------------------------------------------------------
    | 1. Total savings (authoritative from sacco_members)
    |----------------------------------------------------------------------
    */
        $row = DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->where('member_active', 'Y')
            ->first();

        if (!$row) {
            return response()->json([
                'message' => 'Member not found or inactive.',
            ], 401);
        }

        $totalSavings = (float) ($row->member_total_share ?? 0);

        /*
    |----------------------------------------------------------------------
    | 2. Last 10 savings (shares only)
    |----------------------------------------------------------------------
    */
        $transactions = DB::table('sacco_shares')
            ->where('share_member_id', $memberId)
            ->orderByDesc('share_period')
            ->orderByDesc('share_date_paid')
            ->orderByDesc('share_id')
            ->limit(10)
            ->get()
            ->map(function ($s) {
                $amount = (float) $s->share_amount_paying;

                $description = $s->share_description ?? 'Share Transaction';
                $description = preg_replace('/^(CR|DR)\s*-\s*/i', '', $description);

                return [
                    'date'        => Carbon::parse($s->share_date_paid)->format('d M Y'),
                    'description' => $description,
                    'amount'      => abs($amount),
                    'is_credit'   => $amount > 0,
                ];
            });


        /*
    |----------------------------------------------------------------------
    | 3. Response (Savings screen contract)
    |----------------------------------------------------------------------
    */
        return response()->json([
            'total_savings' => $totalSavings,
            'transactions'  => $transactions,
        ]);
    }


    public function fosaSavings(Request $request)
    {
        // 🔐 Authenticated member
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = $member->member_id;

        /*
    |----------------------------------------------------------------------
    | 1. Total FOSA balance (authoritative)
    |----------------------------------------------------------------------
    | Use sacco_members if you track total there,
    | otherwise compute from sacco_fosas.
    */
        $row = DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->where('member_active', 'Y')
            ->first();

        if (!$row) {
            return response()->json([
                'message' => 'Member not found or inactive.',
            ], 401);
        }

        // If you already maintain this total (recommended)
        $totalFosa = (float) ($row->member_total_fosa ?? 0);

        /*
    |----------------------------------------------------------------------
    | 2. Last 10 FOSA transactions (authoritative ordering)
    |----------------------------------------------------------------------
    */
        $transactions = DB::table('sacco_fosas')
            ->where('fosa_member_id', $memberId)
            ->orderByDesc('fosa_period')
            ->orderByDesc('fosa_date_paid')
            ->orderByDesc('fosa_id')
            ->limit(10)
            ->get()
            ->map(function ($f) {
                $amount = (float) $f->fosa_amount_paying;

                $description = $f->fosa_description ?? 'FOSA Transaction';
                $description = preg_replace('/^(CR|DR)\s*-\s*/i', '', $description);

                return [
                    'date'        => Carbon::parse($f->fosa_date_paid)->format('d M Y'),
                    'description' => $description,
                    'amount'      => abs($amount),
                    'is_credit'   => $amount > 0,
                ];
            });

        /*
    |----------------------------------------------------------------------
    | 3. Response (same contract shape as Shares)
    |----------------------------------------------------------------------
    */
        return response()->json([
            'total_savings' => $totalFosa,
            'transactions'  => $transactions,
        ]);
    }

    
    public function loans(Request $request)
{
    /*
    |--------------------------------------------------------------------------
    | Authenticated Member
    |--------------------------------------------------------------------------
    */

    $member = $request->user();

    if (!$member || !isset($member->member_id)) {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    $memberId = (int) $member->member_id;

    /*
    |--------------------------------------------------------------------------
    | Current Period
    |--------------------------------------------------------------------------
    |
    | SACCO periods are YYYYMM.
    |
    | Example:
    | September 2026 = 202609
    |
    */

    $currentPeriod = date('Ym');


    /*
    |--------------------------------------------------------------------------
    | Outstanding Loan Threshold
    |--------------------------------------------------------------------------
    |
    | Keep this consistent with the desktop member-status logic.
    |
    */

    $threshold = DB::table('sacco_defaults')
        ->where('default_name', 'threshold_amount')
        ->value('default_value');

    $thresholdAmount = is_numeric($threshold)
        ? (float) $threshold
        : 1.0;


    /*
    |--------------------------------------------------------------------------
    | Fetch Outstanding Loans
    |--------------------------------------------------------------------------
    |
    | Principal Balance:
    |
    |     loan_amount - loan_loan_paid
    |
    | Do NOT calculate interest in Flutter.
    |
    */

    $loans = DB::table('sacco_loans as l')
        ->join(
            'sacco_loan_types as t',
            't.loan_type_id',
            '=',
            'l.loan_loan_type'
        )
        ->leftJoin(
            'sacco_loan_category as c',
            'c.loan_category_id',
            '=',
            'l.loan_loan_category'
        )
        ->where(
            'l.loan_member',
            $memberId
        )
        ->where(
            'l.loan_stoped',
            'N'
        )
        ->whereRaw(
            '
                COALESCE(l.loan_amount, 0)
                -
                COALESCE(l.loan_loan_paid, 0)
                > ?
            ',
            [$thresholdAmount]
        )
        ->select(
            'l.loan_id',
            'l.loan_amount',
            'l.loan_loan_paid',
            'l.loan_payment_period',
            'l.loan_taken_period',
            'l.loan_on',
            'l.loan_doc_no',
            'l.loan_description',

            't.loan_type_id',
            't.loan_type_name',
            't.loan_type_interest',
            't.loan_type_interest_type',

            'c.loan_category_name'
        )
        ->orderByDesc('l.loan_taken_period')
        ->orderByDesc('l.loan_on')
        ->orderByDesc('l.loan_id')
        ->get();


    /*
    |--------------------------------------------------------------------------
    | Find REDUCING BALANCE Loans
    |--------------------------------------------------------------------------
    |
    | For reducing-balance loans only:
    |
    | If interest has already been serviced during the current YYYYMM period,
    | current interest must be ZERO.
    |
    | FIXED INTEREST does NOT use this suppression.
    |
    */

    $reducingLoanIds = $loans
        ->filter(function ($loan) {

            return strtoupper(
                trim(
                    (string) (
                        $loan->loan_type_interest_type ?? ''
                    )
                )
            ) === 'REDUCING BALANCE';
        })
        ->pluck('loan_id')
        ->map(
            fn($loanId) => (int) $loanId
        )
        ->values();


    /*
    |--------------------------------------------------------------------------
    | Reducing Loans With Interest Already Paid This Period
    |--------------------------------------------------------------------------
    |
    | One bulk query.
    |
    | Avoid querying sacco_loan_payments once per loan.
    |
    */

    $loansWithInterestPaidThisPeriod = collect();

    if ($reducingLoanIds->isNotEmpty()) {

        $loansWithInterestPaidThisPeriod =
            DB::table('sacco_loan_payments')
                ->whereIn(
                    'loan_payments_loan_id',
                    $reducingLoanIds
                )
                ->where(
                    'loan_payments_period',
                    $currentPeriod
                )
                ->where(
                    'loan_payments_interest',
                    '>',
                    0
                )
                ->pluck(
                    'loan_payments_loan_id'
                )
                ->map(
                    fn($loanId) => (int) $loanId
                )
                ->unique()
                ->values();
    }


    /*
    |--------------------------------------------------------------------------
    | Build Mobile Loan Listing
    |--------------------------------------------------------------------------
    */

    $loanData = $loans
        ->map(function ($loan) use (
            $loansWithInterestPaidThisPeriod
        ) {

            $interestType = strtoupper(
                trim(
                    (string) (
                        $loan->loan_type_interest_type ?? ''
                    )
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Has reducing-balance interest already been paid this period?
            |--------------------------------------------------------------------------
            */

            $interestPaidThisPeriod =
                $interestType === 'REDUCING BALANCE'
                &&
                $loansWithInterestPaidThisPeriod
                    ->contains(
                        (int) $loan->loan_id
                    );


            /*
            |--------------------------------------------------------------------------
            | Authoritative Current Position
            |--------------------------------------------------------------------------
            */

            $position = $this->calculateCurrentLoanPosition(
                $loan,
                $interestPaidThisPeriod
            );


            /*
            |--------------------------------------------------------------------------
            | Temporary Legacy Payments
            |--------------------------------------------------------------------------
            |
            | The CURRENT released Flutter app expects a "payments" array.
            |
            | Keep the old latest-five structure temporarily so deploying this API
            | does not break/change the currently installed app before we publish
            | the new Flutter loan-statement screen.
            |
            | The NEW app will ignore this and use:
            |
            | /api/auth/loans/{loanId}/statement
            |
            */

            $legacyPayments = DB::table('sacco_loan_payments')
                ->where(
                    'loan_payments_loan_id',
                    $loan->loan_id
                )
                ->orderByDesc('loan_payments_period')
                ->orderByDesc('loan_payments_paid_on')
                ->orderByDesc('loan_payments_id')
                ->limit(5)
                ->get()
                ->map(function ($payment) {

                    return [
                        'date' =>
                            $payment->loan_payments_paid_on
                            ? Carbon::parse(
                                $payment->loan_payments_paid_on
                            )->format('d M Y')
                            : '',

                        'description' =>
                            $payment->loan_payments_description
                            ?? 'Loan Repayment',

                        /*
                         * Keep legacy behaviour until Flutter is replaced.
                         */
                        'amount' =>
                            abs(
                                (float) (
                                    $payment->loan_payments_amount ?? 0
                                )
                            ),

                        'is_credit' => false,
                    ];
                });


            return [

                /*
                |--------------------------------------------------------------------------
                | Loan Identity
                |--------------------------------------------------------------------------
                */

                'loan_id' =>
                    (int) $loan->loan_id,

                /*
                 * Existing Flutter currently expects loan_type.
                 * Preserve its existing format for backward compatibility.
                 */
                'loan_type' =>
                    sprintf(
                        '%s (%d)',
                        strtoupper(
                            trim(
                                (string) (
                                    $loan->loan_type_name ?? 'LOAN'
                                )
                            )
                        ),
                        (int) $loan->loan_id
                    ),

                /*
                 * New Flutter should use this clean name.
                 */
                'loan_type_name' =>
                    strtoupper(
                        trim(
                            (string) (
                                $loan->loan_type_name ?? 'LOAN'
                            )
                        )
                    ),

                'loan_number' =>
                    (int) $loan->loan_id,

                'loan_category' =>
                    $loan->loan_category_name !== null
                    ? (string) $loan->loan_category_name
                    : null,


                /*
                |--------------------------------------------------------------------------
                | Financial Position
                |--------------------------------------------------------------------------
                |
                | THIS is what the new Flutter Loans screen will use.
                |--------------------------------------------------------------------------
                */

                'loan_amount' =>
                    round(
                        (float) (
                            $loan->loan_amount ?? 0
                        ),
                        2
                    ),

                'principal_balance' =>
                    $position['principal_balance'],

                'current_interest' =>
                    $position['current_interest'],

                'amount_to_pay' =>
                    $position['amount_to_pay'],


                /*
                |--------------------------------------------------------------------------
                | Interest Information
                |--------------------------------------------------------------------------
                */

                'interest_rate' =>
                    round(
                        (float) (
                            $loan->loan_type_interest ?? 0
                        ),
                        6
                    ),

                'interest_type' =>
                    $interestType,

                'interest_serviced_this_period' =>
                    $interestPaidThisPeriod,


                /*
                |--------------------------------------------------------------------------
                | Loan Details
                |--------------------------------------------------------------------------
                */

                'repayment_term' =>
                    (int) (
                        $loan->loan_payment_period ?? 0
                    ),

                'taken_period' =>
                    $loan->loan_taken_period !== null
                    ? (int) $loan->loan_taken_period
                    : null,

                'loan_date' =>
                    $loan->loan_on
                    ? Carbon::parse(
                        $loan->loan_on
                    )->format('d M Y')
                    : null,

                'document_no' =>
                    $loan->loan_doc_no,

                'description' =>
                    $loan->loan_description,


                /*
                |--------------------------------------------------------------------------
                | Backward Compatibility
                |--------------------------------------------------------------------------
                |
                | Existing Flutter currently reads:
                |
                | principal
                | balance
                | payments
                |
                | Keep these until the new app has been released.
                |
                */

                'principal' =>
                    round(
                        (float) (
                            $loan->loan_amount ?? 0
                        ),
                        2
                    ),

                'balance' =>
                    $position['principal_balance'],

                'payments' =>
                    $legacyPayments,
            ];
        })
        ->values();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    return response()->json([
        'period' => (int) $currentPeriod,
        'loans'  => $loanData,
    ]);
}

public function loanStatement(
    Request $request,
    int $loanId
) {
    /*
    |--------------------------------------------------------------------------
    | Authenticated Member
    |--------------------------------------------------------------------------
    */

    $member = $request->user();

    if (!$member || !isset($member->member_id)) {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    $memberId = (int) $member->member_id;

    $currentPeriod = date('Ym');


    /*
    |--------------------------------------------------------------------------
    | Fetch Loan
    |--------------------------------------------------------------------------
    |
    | SECURITY:
    |
    | We filter BOTH loan_id and loan_member.
    |
    | Therefore a member cannot change the URL from:
    |
    | /loans/16994/statement
    |
    | to another member's loan ID and obtain their statement.
    |
    */

    $loan = DB::table('sacco_loans as l')
        ->join(
            'sacco_loan_types as t',
            't.loan_type_id',
            '=',
            'l.loan_loan_type'
        )
        ->leftJoin(
            'sacco_loan_category as c',
            'c.loan_category_id',
            '=',
            'l.loan_loan_category'
        )
        ->where(
            'l.loan_id',
            $loanId
        )
        ->where(
            'l.loan_member',
            $memberId
        )
        ->select(
            'l.*',

            't.loan_type_name',
            't.loan_type_interest',
            't.loan_type_interest_type',

            'c.loan_category_name'
        )
        ->first();


    if (!$loan) {
        return response()->json([
            'message' => 'Loan not found.',
        ], 404);
    }


    /*
    |--------------------------------------------------------------------------
    | Interest Type
    |--------------------------------------------------------------------------
    */

    $interestType = strtoupper(
        trim(
            (string) (
                $loan->loan_type_interest_type ?? ''
            )
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Current Period Interest Check
    |--------------------------------------------------------------------------
    |
    | Only required for REDUCING BALANCE.
    |
    */

    $interestPaidThisPeriod = false;

    if ($interestType === 'REDUCING BALANCE') {

        $interestPaidThisPeriod =
            DB::table('sacco_loan_payments')
                ->where(
                    'loan_payments_loan_id',
                    $loanId
                )
                ->where(
                    'loan_payments_period',
                    $currentPeriod
                )
                ->where(
                    'loan_payments_interest',
                    '>',
                    0
                )
                ->exists();
    }


    /*
    |--------------------------------------------------------------------------
    | Current Loan Position
    |--------------------------------------------------------------------------
    */

    $position = $this->calculateCurrentLoanPosition(
        $loan,
        $interestPaidThisPeriod
    );


    /*
    |--------------------------------------------------------------------------
    | COMPLETE Loan Statement
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | NO limit().
    |
    | We return every loan-payment record.
    |
    | Ordering:
    |
    | 1. YYYYMM period
    | 2. Date paid
    | 3. Payment ID
    |
    | Therefore even two transactions on the same date/period have a stable,
    | deterministic order.
    |
    */

    $payments = DB::table('sacco_loan_payments')
        ->where(
            'loan_payments_loan_id',
            $loanId
        )
        ->orderBy(
            'loan_payments_period',
            'asc'
        )
        ->orderBy(
            'loan_payments_paid_on',
            'asc'
        )
        ->orderBy(
            'loan_payments_id',
            'asc'
        )
        ->get();


    /*
    |--------------------------------------------------------------------------
    | Running Principal Balance
    |--------------------------------------------------------------------------
    |
    | loan_payments_amount = PRINCIPAL movement.
    |
    | loan_payments_interest = INTEREST paid.
    |
    | Do NOT use abs() here.
    |
    | The database contains legitimate negative principal adjustments.
    | Their sign must be preserved.
    |
    */

    $runningPrincipal =
        (float) (
            $loan->loan_amount ?? 0
        );


    $transactions = $payments
        ->values()
        ->map(function (
            $payment,
            $index
        ) use (
            &$runningPrincipal
        ) {

            $principalMovement =
                (float) (
                    $payment->loan_payments_amount ?? 0
                );

            $interest =
                (float) (
                    $payment->loan_payments_interest ?? 0
                );


            /*
            |--------------------------------------------------------------------------
            | Total Transaction Amount
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | Principal = 65,435.05
            | Interest  = 26,150.95
            |
            | Total     = 91,586.00
            |
            */

            $totalPaid =
                $principalMovement
                +
                $interest;


            /*
            |--------------------------------------------------------------------------
            | Running Principal
            |--------------------------------------------------------------------------
            */

            $runningPrincipal =
                $runningPrincipal
                -
                $principalMovement;


            return [

                'sequence' =>
                    $index + 1,

                'payment_id' =>
                    (int) $payment->loan_payments_id,

                'period' =>
                    $payment->loan_payments_period !== null
                    ? (int) $payment->loan_payments_period
                    : null,

                'date' =>
                    $payment->loan_payments_paid_on
                    ? Carbon::parse(
                        $payment->loan_payments_paid_on
                    )->format('d M Y')
                    : null,

                'document_no' =>
                    $payment->loan_payments_docno,

                'description' =>
                    $payment->loan_payments_description
                    ?? 'Loan Repayment',

                'paid_in_by' =>
                    $payment->loan_payments_paid_in_by,


                /*
                |--------------------------------------------------------------------------
                | Transaction Financial Breakdown
                |--------------------------------------------------------------------------
                */

                'principal' =>
                    round(
                        $principalMovement,
                        2
                    ),

                'interest' =>
                    round(
                        $interest,
                        2
                    ),

                'total_paid' =>
                    round(
                        $totalPaid,
                        2
                    ),

                'running_principal_balance' =>
                    round(
                        $runningPrincipal,
                        2
                    ),
            ];
        });


    /*
    |--------------------------------------------------------------------------
    | Final Response
    |--------------------------------------------------------------------------
    */

    return response()->json([

        'period' =>
            (int) $currentPeriod,

        'loan' => [

            'loan_id' =>
                (int) $loan->loan_id,

            'loan_type_name' =>
                strtoupper(
                    trim(
                        (string) (
                            $loan->loan_type_name ?? 'LOAN'
                        )
                    )
                ),

            'loan_number' =>
                (int) $loan->loan_id,

            'loan_category' =>
                $loan->loan_category_name !== null
                ? (string) $loan->loan_category_name
                : null,


            /*
            |--------------------------------------------------------------------------
            | Current Position
            |--------------------------------------------------------------------------
            */

            'loan_amount' =>
                round(
                    (float) (
                        $loan->loan_amount ?? 0
                    ),
                    2
                ),

            'principal_paid' =>
                round(
                    (float) (
                        $loan->loan_loan_paid ?? 0
                    ),
                    2
                ),

            'principal_balance' =>
                $position['principal_balance'],

            'current_interest' =>
                $position['current_interest'],

            'amount_to_pay' =>
                $position['amount_to_pay'],


            /*
            |--------------------------------------------------------------------------
            | Interest Rules
            |--------------------------------------------------------------------------
            */

            'interest_rate' =>
                round(
                    (float) (
                        $loan->loan_type_interest ?? 0
                    ),
                    6
                ),

            'interest_type' =>
                $interestType,

            'interest_serviced_this_period' =>
                $interestPaidThisPeriod,


            /*
            |--------------------------------------------------------------------------
            | Loan Information
            |--------------------------------------------------------------------------
            */

            'repayment_term' =>
                (int) (
                    $loan->loan_payment_period ?? 0
                ),

            'taken_period' =>
                $loan->loan_taken_period !== null
                ? (int) $loan->loan_taken_period
                : null,

            'loan_date' =>
                $loan->loan_on
                ? Carbon::parse(
                    $loan->loan_on
                )->format('d M Y')
                : null,

            'document_no' =>
                $loan->loan_doc_no,

            'description' =>
                $loan->loan_description,
        ],


        /*
        |--------------------------------------------------------------------------
        | Full Statement
        |--------------------------------------------------------------------------
        */

        'statement' => [

            'transaction_count' =>
                $transactions->count(),

            'transactions' =>
                $transactions,
        ],
    ]);
}


    public function profile(Request $request)
    {
        // 🔐 Authenticated member
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = $member->member_id;

        /*
    |----------------------------------------------------------------------
    | 1. Core member record
    |----------------------------------------------------------------------
    */
        $row = DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->where('member_active', 'Y')
            ->first();

        if (!$row) {
            return response()->json([
                'message' => 'Member not found or inactive.',
            ], 404);
        }

        /*
    |----------------------------------------------------------------------
    | 2. Next of Kin (joined to relationship type)
    |----------------------------------------------------------------------
    */
        $nextOfKin = DB::table('sacco_next_of_kin as k')
            ->leftJoin(
                'sacco_kin_type as t',
                'k.kin_relationship',
                '=',
                't.kin_type_id'
            )
            ->where('k.kin_member_id', $memberId)
            ->where('k.kin_deleted', 'N')
            ->orderByDesc('k.kin_percent')
            ->get()
            ->map(function ($k) {
                return [
                    'name'         => $k->kin_names,
                    'relationship' => $k->kin_type_name ?? 'Other',
                    'phone'        => $k->kin_address, // stored as phone in your data
                    'percent'      => (int) $k->kin_percent,
                ];
            });

        /*
    |----------------------------------------------------------------------
    | 3. Response (UI contract)
    |----------------------------------------------------------------------
    */
        return response()->json([
            'member' => [
                'name'           => $row->member_name,
                'member_number'  => 'SACCO / ' . str_pad(
                    (string) $row->member_sacco_id,
                    5,
                    '0',
                    STR_PAD_LEFT
                ),
                'status'         => $row->member_active === 'Y'
                    ? 'Active'
                    : 'Inactive',

                'date_joined'    => $row->member_date_joined,
                'department'     => $row->member_dept,
                'position'       => $row->member_position,

                'contact' => [
                    'phone'   => $row->member_phone_no,
                    'email'   => $row->member_email,
                    'address' => $row->member_postal_address,
                ],

                'bank' => [
                    'name'    => $row->bank_name,
                    'branch'  => $row->bank_branch,
                    'account' => $row->bank_account_number,
                ],
            ],

            'next_of_kin' => $nextOfKin,
        ]);
    }
private function getExpectedContributionDues($memberRow): array
{
    $defaults = $this->getContributionDefaults();

    $items = array_merge(
        $this->getExpectedCapitalDues($memberRow, $defaults),
        $this->getExpectedShareDues($memberRow, $defaults),
        $this->getExpectedFosaDues($memberRow)
    );

    return array_values($items);
}

private function getContributionDefaults(): array
{
    $this->ensureContributionDefaultExists('min_share_contribution', '1000');
    $this->ensureContributionDefaultExists('min_capital_contribution', '1000');
    $this->ensureContributionDefaultExists('min_share_daily_contribution', '0');

    $defaults = DB::table('sacco_defaults')
        ->whereIn('default_name', [
            'min_share_contribution',
            'min_capital_contribution',
            'min_share_daily_contribution',
        ])
        ->pluck('default_value', 'default_name');

    return [
        'min_share_contribution' => (float) ($defaults['min_share_contribution'] ?? 1000),
        'min_capital_contribution' => (float) ($defaults['min_capital_contribution'] ?? 1000),
        'min_share_daily_contribution' => (float) ($defaults['min_share_daily_contribution'] ?? 0),
    ];
}

private function ensureContributionDefaultExists(string $name, string $value): void
{
    $exists = DB::table('sacco_defaults')
        ->where('default_name', $name)
        ->exists();

    if ($exists) {
        return;
    }

    DB::table('sacco_defaults')->insert([
        'default_name' => $name,
        'default_value' => $value,
        'default_userid' => null,
        'default_ip' => 'AUTO-API',
        'default_transdate' => now(),
    ]);
}

private function getExpectedShareDues($memberRow, array $defaults): array
{
    $memberId = (int) $memberRow->member_id;
    $asOf = $this->getContributionAsOfDate($memberRow);

    $dailyMin = (float) ($defaults['min_share_daily_contribution'] ?? 0);
    $monthlyMin = (float) ($defaults['min_share_contribution'] ?? 0);

    $expectedAmount = 0.0;
    $expectedTotal = 0.0;
    $units = 0;
    $expectedPeriod = 'monthly';
    $periodLabel = 'Monthly';
    $unitLabel = 'months';
    $effectiveStart = null;

    /*
    |--------------------------------------------------------------------------
    | Daily logic takes priority only when min_share_daily_contribution > 0
    |--------------------------------------------------------------------------
    */
    if ($dailyMin > 0) {
        $effectiveStart = $this->getShareDailyWindowStartDate($memberRow, $asOf);

        if ($effectiveStart->greaterThan($asOf)) {
            return [];
        }

        $units = $effectiveStart->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay()) + 1;
        $expectedAmount = $dailyMin;
        $expectedTotal = round($dailyMin * $units, 2);
        $expectedPeriod = 'daily';
        $periodLabel = 'Daily';
        $unitLabel = 'days';
    } else {
        /*
        |--------------------------------------------------------------------------
        | Monthly logic only when daily default is zero
        | Window = last 12 months max, but never before join date
        |--------------------------------------------------------------------------
        */
        if ($monthlyMin <= 0) {
            return [];
        }

        $effectiveStart = $this->getShareMonthlyWindowStartDate($memberRow, $asOf);

        if ($effectiveStart->greaterThan($asOf)) {
            return [];
        }

        $startMonth = $effectiveStart->copy()->startOfMonth();
        $endMonth = $asOf->copy()->startOfMonth();

        $units = $startMonth->diffInMonths($endMonth) + 1;
        $expectedAmount = $monthlyMin;
        $expectedTotal = round($monthlyMin * $units, 2);
        $expectedPeriod = 'monthly';
        $periodLabel = 'Monthly';
        $unitLabel = 'months';
    }

    $paid = (float) DB::table('sacco_shares')
        ->where('share_member_id', $memberId)
        ->whereRaw('COALESCE(share_date_paid, share_transdate) >= ?', [$effectiveStart->toDateTimeString()])
        ->whereRaw('COALESCE(share_date_paid, share_transdate) <= ?', [$asOf->toDateTimeString()])
        ->sum('share_amount_paying');

    $lastPaidAt = DB::table('sacco_shares')
        ->where('share_member_id', $memberId)
        ->whereRaw('COALESCE(share_date_paid, share_transdate) >= ?', [$effectiveStart->toDateTimeString()])
        ->whereRaw('COALESCE(share_date_paid, share_transdate) <= ?', [$asOf->toDateTimeString()])
        ->orderByRaw('COALESCE(share_date_paid, share_transdate) DESC')
        ->value(DB::raw('COALESCE(share_date_paid, share_transdate)'));

    $balance = round($expectedTotal - $paid, 2);

    if ($balance <= 0.01) {
        return [];
    }

    return [[
        'type_id'         => 1000001,
        'type_name'       => 'SAVINGS/DEPOSITS',
        'type_prefix'     => 'SH',
        'expected_period' => $expectedPeriod,
        'period_label'    => $periodLabel,
        'expected_amount' => round($expectedAmount, 2),
        'expected_total'  => round($expectedTotal, 2),
        'paid_total'      => round($paid, 2),
        'balance'         => $balance,
        'units'           => $units,
        'unit_label'      => $unitLabel,
        'effective_start' => $effectiveStart->toDateString(),
        'as_of'           => $asOf->toDateString(),
        'last_paid_at'    => $lastPaidAt ? Carbon::parse($lastPaidAt)->toDateTimeString() : null,
    ]];
}

private function getExpectedCapitalDues($memberRow, array $defaults): array
{
    $expectedCapital = (float) ($defaults['min_capital_contribution'] ?? 0);

    if ($expectedCapital <= 0) {
        return [];
    }

    $paidCapital = (float) ($memberRow->member_total_share_capital ?? 0);
    $balance = round($expectedCapital - $paidCapital, 2);

    if ($balance <= 0.01) {
        return [];
    }

    $asOf = $this->getContributionAsOfDate($memberRow);
    $joinedAt = !empty($memberRow->member_date_joined)
        ? Carbon::parse($memberRow->member_date_joined)->startOfDay()
        : $asOf->copy()->startOfDay();

    return [[
        'type_id'         => 1000002,
        'type_name'       => 'CAPITAL SHARES',
        'type_prefix'     => 'CA',
        'expected_period' => 'one_time',
        'period_label'    => 'One-time',
        'expected_amount' => round($expectedCapital, 2),
        'expected_total'  => round($expectedCapital, 2),
        'paid_total'      => round($paidCapital, 2),
        'balance'         => $balance,
        'units'           => 1,
        'unit_label'      => 'time',
        'effective_start' => $joinedAt->toDateString(),
        'as_of'           => $asOf->toDateString(),
        'last_paid_at'    => null,
    ]];
}

private function getContributionAsOfDate($memberRow): Carbon
{
    $asOf = Carbon::now()->endOfDay();

    if (!empty($memberRow->member_date_dactivated)) {
        $deactivatedAt = Carbon::parse($memberRow->member_date_dactivated)->endOfDay();
        if ($deactivatedAt->lessThan($asOf)) {
            $asOf = $deactivatedAt;
        }
    }

    return $asOf;
}

private function getShareDailyWindowStartDate($memberRow, Carbon $asOf): Carbon
{
    $joinedAt = !empty($memberRow->member_date_joined)
        ? Carbon::parse($memberRow->member_date_joined)->startOfDay()
        : $asOf->copy()->startOfDay();

    $maxLookbackStart = $asOf->copy()->subDays(364)->startOfDay();

    return $joinedAt->greaterThan($maxLookbackStart)
        ? $joinedAt
        : $maxLookbackStart;
}

private function getShareMonthlyWindowStartDate($memberRow, Carbon $asOf): Carbon
{
    $joinedAt = !empty($memberRow->member_date_joined)
        ? Carbon::parse($memberRow->member_date_joined)->startOfDay()
        : $asOf->copy()->startOfDay();

    $maxLookbackStart = $asOf->copy()->startOfMonth()->subMonths(11)->startOfDay();

    return $joinedAt->greaterThan($maxLookbackStart)
        ? $joinedAt
        : $maxLookbackStart;
}
}
