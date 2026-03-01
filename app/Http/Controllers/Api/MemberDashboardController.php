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
        $nowPeriod = (int) date('Ym');

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

            $monthsBehind = $nowPeriod - (int) $loan->last_period;

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

        $duesItems = $this->getExpectedFosaDues($row);

$duesTotal = 0.0;
foreach ($duesItems as $it) {
    $duesTotal += (float) ($it['balance'] ?? 0);
}

        return response()->json([
            'member' => [
                'name'          => $row->member_name,
                'member_number' => 'SACCO / ' . str_pad(
                    (string) $row->member_sacco_id,
                    5,
                    '0',
                    STR_PAD_LEFT
                ),
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
        'as_of'         => Carbon::now()->toDateString(),
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
        // 🔐 Authenticated member
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $memberId = $member->member_id;

        /*
    |------------------------------------------------------------
    | 1. Fetch outstanding loans only
    |------------------------------------------------------------
    | loan_amount_guaranteed = original principal
    | loan_loan_paid        = total repaid (can be NULL)
    | balance               = principal - paid
    */

        $loans = DB::table('sacco_loans as l')
            ->join('sacco_loan_types as t', 't.loan_type_id', '=', 'l.loan_loan_type')
            ->where('l.loan_member', $memberId)
            ->where('l.loan_stoped', 'N')
            ->get()
            ->map(function ($loan) {

                $principal = (float) ($loan->loan_amount ?? 0);
                $paid      = (float) ($loan->loan_loan_paid ?? 0);
                $balance   = $principal - $paid;

                return [
                    'loan_id'        => $loan->loan_id,
                    'loan_type'      => sprintf(
                        '%s (%d)',
                        strtoupper($loan->loan_type_name),
                        $loan->loan_id
                    ),
                    'principal'      => $principal,
                    'balance'        => $balance,
                    'repayment_term' => (int) $loan->loan_payment_period,
                    'taken_period'   => $loan->loan_taken_period,
                ];
            })
            // ✅ ONLY outstanding loans
            ->filter(fn($l) => $l['balance'] > 1)
            ->values();

        /*
    |------------------------------------------------------------
    | 2. Attach latest 5 repayments per loan
    |------------------------------------------------------------
    */

        $loans = $loans->map(function ($loan) {

            $payments = DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loan['loan_id'])
                ->orderByDesc('loan_payments_period')
                ->orderByDesc('loan_payments_paid_on')
                ->orderByDesc('loan_payments_id')
                ->limit(5)
                ->get()
                ->map(function ($p) {
                    return [
                        'date'        => Carbon::parse($p->loan_payments_paid_on)->format('d M Y'),
                        'description' => $p->loan_payments_description ?? 'Loan Repayment',
                        'amount'      => abs((float) ($p->loan_payments_amount ?? 0)),
                        // repayments reduce balance → debit to member
                        'is_credit'   => false,
                    ];
                });

            $loan['payments'] = $payments;

            return $loan;
        });

        /*
    |------------------------------------------------------------
    | 3. Final response (Loans screen contract)
    |------------------------------------------------------------
    */

        return response()->json([
            'loans' => $loans,
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
}
