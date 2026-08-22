<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberFinancialPositionController extends Controller
{
    /**
     * Report UI
     */
    public function index()
    {
        $currentPeriod = date('Ym');
        return view('reports.members.financial_position.index', compact('currentPeriod'));
    }

    /**
     * Base members query (re-used by data + export)
     * NOTE: LEFT joins + no deleted filters => no record "escapes"
     */
    protected function membersBaseQuery(string $pms_srch = '')
    {
        $q = DB::table('sacco_members')
            ->leftJoin('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->leftJoin('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->leftJoin('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id');

        if ($pms_srch !== '') {
            $q->where(function ($qq) use ($pms_srch) {
                $qq->where('sacco_members.member_name', 'like', "%{$pms_srch}%")
                    ->orWhere('sacco_members.member_sacco_id', 'like', "%{$pms_srch}%")
                    ->orWhere('sacco_members.member_national_id', 'like', "%{$pms_srch}%")
                    ->orWhere('sacco_members.member_email', 'like', "%{$pms_srch}%")
                    ->orWhere('sacco_members.member_phone_no', 'like', "%{$pms_srch}%")
                    ->orWhere('sacco_company.company_name', 'like', "%{$pms_srch}%")
                    ->orWhere('sacco_department.department_name', 'like', "%{$pms_srch}%")
                    ->orWhere('sacco_position.position_name', 'like', "%{$pms_srch}%");
            });
        }

        return $q;
    }

    /**
     * Human-readable audit label for the authenticated user generating/viewing
     * this financial position report.
     */
    protected function generatedByLabel(): string
    {
        $user = auth()->user();

        if (!$user) {
            return 'System';
        }

        foreach (['member_name', 'name', 'email', 'member_email'] as $field) {
            $value = trim((string) ($user->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return auth()->id() ? ('User #' . auth()->id()) : 'Authenticated user';
    }
    /**
     * Loan types (include even "deleted" types => no record escapes)
     */
    protected function loanTypes()
    {
        return DB::table('sacco_loan_types')
            ->orderBy('loan_type_name')
            ->get();
    }


    /**
     * All configured Other Savings (legacy FOSA) types shown as separate report columns.
     *
     * IMPORTANT:
     * A type is reported whether active or inactive. Inactive only means it is no
     * longer open for new activity; historical balances must still remain visible
     * so the financial position reconciles.
     *
     * Any FOSA type whose name/prefix matches a live Special Savings product code
     * is excluded here so products such as FEDHA are not counted twice.
     */
    protected function otherSavingsConfiguration(): array
    {
        $specialCodes = DB::table('sacco_special_saving_products')
            ->where('special_saving_product_deleted', 'N')
            ->pluck('special_saving_product_code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $specialCodeLookup = array_fill_keys($specialCodes, true);

        $allTypes = DB::table('sacco_fosa_types')
            ->orderBy('type_name')
            ->orderBy('type_id')
            ->get();

        $specialTypeIds = [];

        foreach ($allTypes as $type) {
            $name   = strtoupper(trim((string) ($type->type_name ?? '')));
            $prefix = strtoupper(trim((string) ($type->type_prefix ?? '')));

            if (isset($specialCodeLookup[$name]) || isset($specialCodeLookup[$prefix])) {
                $specialTypeIds[] = (int) $type->type_id;
            }
        }

        $types = $allTypes
            ->filter(function ($type) use ($specialCodeLookup) {
                $name   = strtoupper(trim((string) ($type->type_name ?? '')));
                $prefix = strtoupper(trim((string) ($type->type_prefix ?? '')));

                return !isset($specialCodeLookup[$name])
                    && !isset($specialCodeLookup[$prefix]);
            })
            ->values();

        return [
            'types'            => $types,
            'report_type_ids'  => $types->pluck('type_id')->map(fn ($id) => (int) $id)->all(),
            'special_codes'    => $specialCodes,
            'special_type_ids' => array_values(array_unique($specialTypeIds)),
        ];
    }

    /**
     * Whether the selected report population has a non-zero uncategorised
     * Other Savings balance. Used to show the General Other Savings column
     * only when it is actually needed.
     */
    protected function hasGeneralOtherSavings(string $period, string $pms_srch, array $config): bool
    {
        $reportTypeIds  = $config['report_type_ids'] ?? [];
        $specialTypeIds = $config['special_type_ids'] ?? [];

        $memberIds = $this->membersBaseQuery($pms_srch)
            ->select('sacco_members.member_id')
            ->distinct();

        $q = DB::table('sacco_fosas as f')
            ->whereIn('f.fosa_member_id', $memberIds)
            ->where('f.fosa_period', '<=', (int) $period);

        // General = no type, invalid type, or any type that no longer exists in
        // the configured FOSA type table. Inactive configured types are NOT
        // generalised; they retain their own report column.
        if (!empty($reportTypeIds)) {
            $q->where(function ($w) use ($reportTypeIds) {
                $w->whereNull('f.fosa_type_id')
                    ->orWhereNotIn('f.fosa_type_id', $reportTypeIds);
            });
        }

        // But never move legacy Special Savings types into General.
        if (!empty($specialTypeIds)) {
            $q->where(function ($w) use ($specialTypeIds) {
                $w->whereNull('f.fosa_type_id')
                    ->orWhereNotIn('f.fosa_type_id', $specialTypeIds);
            });
        }

        return $q
            ->select('f.fosa_member_id', DB::raw('SUM(COALESCE(f.fosa_amount_paying,0)) as general_balance'))
            ->groupBy('f.fosa_member_id')
            ->havingRaw('ABS(SUM(COALESCE(f.fosa_amount_paying,0))) > 0.000001')
            ->limit(1)
            ->get()
            ->isNotEmpty();
    }

    /**
     * Bulk aggregates for a set of member IDs (FAST)
     *
     * IMPORTANT:
     * Loans are computed EXACTLY like your proven audit SQL:
     *  - join payments to loans by loan_id
     *  - payments filtered by <= period
     *  - group FIRST by loan_id to get per-loan paid_upto
     *  - compute per-loan balance_upto
     *  - then roll up to member + loan_type
     *
     * This prevents double counting and matches your phpMyAdmin audit output.
     */
    protected function aggregatesForMembers(array $memberIds, string $period, array $otherSavingsConfig)
    {
        if (empty($memberIds)) {
            return [
                'savings'        => [],
                'fosa'                 => [],
                'fosaByType'           => [],
                'fosaGeneral'          => [],
                'capital'              => [],
                'specialSavings' => [],
                'loanTaken'      => [], // [member_id][loan_type_id] => taken_total
                'loanPaid'    => [], // [member_id][loan_type_id] => paid_total
                'loanBalance' => [], // [member_id][loan_type_id] => balance_total (sum of per-loan balances)
            ];
        }

        $periodInt = (int) $period;

        // -------------------------
        // Savings
        // -------------------------
        $savings = DB::table('sacco_shares')
            ->select('share_member_id', DB::raw('SUM(COALESCE(share_amount_paying,0)) as total'))
            ->whereIn('share_member_id', $memberIds)
            ->where('share_period', '<=', $periodInt)
            ->groupBy('share_member_id')
            ->pluck('total', 'share_member_id')
            ->toArray();

        // -------------------------
        // Other Savings (legacy FOSA), grouped by configured type.
        // -------------------------
        // Every configured type, active or inactive, gets its own report column.
        // Only null/invalid type IDs fall back to General Other Savings.
        // Legacy FOSA rows matching Special Savings products (e.g. FEDHA)
        // are excluded to prevent double counting.
        $reportTypeLookup = array_fill_keys($otherSavingsConfig['report_type_ids'] ?? [], true);
        $specialCodeLookup = array_fill_keys($otherSavingsConfig['special_codes'] ?? [], true);

        $fosaRows = DB::table('sacco_fosas as f')
            ->leftJoin('sacco_fosa_types as t', 'f.fosa_type_id', '=', 't.type_id')
            ->select(
                'f.fosa_member_id',
                'f.fosa_type_id',
                't.type_name',
                't.type_prefix',
                DB::raw('SUM(COALESCE(f.fosa_amount_paying,0)) as total')
            )
            ->whereIn('f.fosa_member_id', $memberIds)
            ->where('f.fosa_period', '<=', $periodInt)
            ->groupBy('f.fosa_member_id', 'f.fosa_type_id', 't.type_name', 't.type_prefix')
            ->get();

        $fosa        = [];
        $fosaByType  = [];
        $fosaGeneral = [];

        foreach ($fosaRows as $row) {
            $mid    = (int) $row->fosa_member_id;
            $amount = (float) ($row->total ?? 0);
            $tid    = is_numeric($row->fosa_type_id ?? null) ? (int) $row->fosa_type_id : null;

            $typeName   = strtoupper(trim((string) ($row->type_name ?? '')));
            $typePrefix = strtoupper(trim((string) ($row->type_prefix ?? '')));

            // Special Savings products no longer belong in Other Savings.
            if (isset($specialCodeLookup[$typeName]) || isset($specialCodeLookup[$typePrefix])) {
                continue;
            }

            $fosa[$mid] = ($fosa[$mid] ?? 0) + $amount;

            if ($tid !== null && isset($reportTypeLookup[$tid])) {
                $fosaByType[$mid][$tid] = ($fosaByType[$mid][$tid] ?? 0) + $amount;
            } else {
                $fosaGeneral[$mid] = ($fosaGeneral[$mid] ?? 0) + $amount;
            }
        }

        // -------------------------
        // Capital
        // -------------------------
        $capital = DB::table('sacco_capital_shares')
            ->select('share_capitalmember_id', DB::raw('SUM(COALESCE(share_capitalamount_paying,0)) as total'))
            ->whereIn('share_capitalmember_id', $memberIds)
            ->where('share_capitalperiod', '<=', $periodInt)
            ->groupBy('share_capitalmember_id')
            ->pluck('total', 'share_capitalmember_id')
            ->toArray();

        // -------------------------
        // Special Savings — historical balance AS AT selected period
        // -------------------------
        // Reconstruct the member balance from transaction movements instead
        // of using today's account balance. This preserves historical reporting.
        //
        // CREDIT  => increases Special Savings
        // DEBIT   => reduces Special Savings
        // INTEREST_VESTING only moves interest from accrued to available,
        // so it does not change the member's total Special Savings balance.
        //
        // If a transaction was reversed AFTER the requested period, it still
        // belongs to that historical period. If it had already been reversed
        // by period-end, its original financial effect is excluded.
        $periodStart = substr($period, 0, 4) . '-' . substr($period, 4, 2) . '-01';
        $periodEndDate = date('Y-m-t', strtotime($periodStart));

        $specialSavings = DB::table('sacco_special_saving_transactions as sst')
            ->select(
                'sst.special_saving_transaction_member_id',
                DB::raw("
                    ROUND(SUM(
                        CASE
                            WHEN UPPER(COALESCE(sst.special_saving_transaction_type, '')) = 'INTEREST_VESTING'
                                THEN 0
                            WHEN UPPER(COALESCE(sst.special_saving_transaction_direction, '')) = 'CREDIT'
                                THEN COALESCE(sst.special_saving_transaction_amount, 0)
                            WHEN UPPER(COALESCE(sst.special_saving_transaction_direction, '')) = 'DEBIT'
                                THEN -COALESCE(sst.special_saving_transaction_amount, 0)
                            ELSE 0
                        END
                    ), 2) as total
                ")
            )
            ->whereIn('sst.special_saving_transaction_member_id', $memberIds)
            ->where('sst.special_saving_transaction_period', '<=', $periodInt)
            ->where('sst.special_saving_transaction_deleted', 'N')
            ->where(function ($q) use ($periodEndDate) {
                $q->whereNull('sst.special_saving_transaction_reversed')
                    ->orWhere('sst.special_saving_transaction_reversed', '!=', 'Y')
                    ->orWhere(function ($rq) use ($periodEndDate) {
                        $rq->where('sst.special_saving_transaction_reversed', 'Y')
                            ->whereNotNull('sst.special_saving_transaction_reversed_on')
                            ->whereDate('sst.special_saving_transaction_reversed_on', '>', $periodEndDate);
                    });
            })
            ->groupBy('sst.special_saving_transaction_member_id')
            ->pluck('total', 'sst.special_saving_transaction_member_id')
            ->toArray();

        // =====================================================
        // LOANS — per-loan first (NO double counting)
        // Mirrors the audit query logic.
        // =====================================================
        $perLoanRows = DB::table('sacco_loans as l')
            ->leftJoin('sacco_loan_payments as p', function ($join) use ($periodInt) {
                $join->on('p.loan_payments_loan_id', '=', 'l.loan_id')
                    ->where('p.loan_payments_period', '<=', $periodInt);
            })
            ->select([
                'l.loan_member',
                'l.loan_loan_type',
                'l.loan_id',
                DB::raw('ROUND(COALESCE(l.loan_amount,0),2) as taken'),
                DB::raw('ROUND(COALESCE(SUM(COALESCE(p.loan_payments_amount,0)),0),2) as paid_upto'),
                DB::raw('ROUND(ROUND(COALESCE(l.loan_amount,0),2) - ROUND(COALESCE(SUM(COALESCE(p.loan_payments_amount,0)),0),2),2) as balance_upto'),
            ])
            ->whereIn('l.loan_member', $memberIds)
            ->whereNotNull('l.loan_loan_type')
            ->where('l.loan_taken_period', '<=', $periodInt)
            ->groupBy('l.loan_id', 'l.loan_member', 'l.loan_loan_type', 'l.loan_amount')
            ->get();

        $loanTaken   = [];
        $loanPaid    = [];
        $loanBalance = [];

        foreach ($perLoanRows as $r) {
            $mid = (int) $r->loan_member;
            $tid = (int) $r->loan_loan_type;

            $taken = (float) ($r->taken ?? 0);
            $paid  = (float) ($r->paid_upto ?? 0);
            $bal   = (float) ($r->balance_upto ?? 0);

            $loanTaken[$mid][$tid]   = ($loanTaken[$mid][$tid] ?? 0) + $taken;
            $loanPaid[$mid][$tid]    = ($loanPaid[$mid][$tid] ?? 0) + $paid;
            $loanBalance[$mid][$tid] = ($loanBalance[$mid][$tid] ?? 0) + $bal;
        }

        return compact('savings', 'fosa', 'fosaByType', 'fosaGeneral', 'capital', 'specialSavings', 'loanTaken', 'loanPaid', 'loanBalance');
    }

    /**
     * Report data (AJAX)
     * GET /.../data?period=YYYYMM&pms_srch=&page=1&per_page=5000
     */
    public function data(Request $request)
    {
        $period   = $request->get('period', date('Ym'));
        $pms_srch = trim((string) $request->get('pms_srch', ''));

        $page    = max(1, (int) $request->get('page', 1));
        $perPage = (int) $request->get('per_page', 5000);
        if ($perPage < 1) $perPage = 5000;
        if ($perPage > 5000) $perPage = 5000;

        if (!preg_match('/^\d{6}$/', $period)) {
            return response()->json(['error' => 'Invalid period'], 422);
        }

        $loanTypes          = $this->loanTypes();
        $otherSavingsConfig = $this->otherSavingsConfiguration();
        $otherSavingsTypes  = $otherSavingsConfig['types'];
        $base               = $this->membersBaseQuery($pms_srch);

        $total = (clone $base)
            ->distinct()
            ->count('sacco_members.member_id');

        $members = (clone $base)
            ->select([
                DB::raw('sacco_members.member_id as member_id'),
                'sacco_members.member_name',
                'sacco_members.member_sacco_id',
                'sacco_members.member_national_id',
                'sacco_members.member_email',
                'sacco_members.member_phone_no',
                'sacco_members.member_gender',
                DB::raw('sacco_members.member_active as member_active'),
                'sacco_department.department_name',
                'sacco_company.company_name',
                'sacco_position.position_name',
            ])
            ->orderBy('sacco_members.member_name')
            ->orderBy('sacco_members.member_id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $memberIds = $members->pluck('member_id')->map(fn ($v) => (int) $v)->all();
        $agg       = $this->aggregatesForMembers($memberIds, $period, $otherSavingsConfig);

        $rows = [];
        foreach ($members as $m) {
            $mid = (int) $m->member_id;

            $active  = (strtoupper(trim((string) ($m->member_active ?? ''))) === 'Y') ? 'Yes' : 'No';
            $savings        = (float) ($agg['savings'][$mid] ?? 0);
            $fosa           = (float) ($agg['fosa'][$mid] ?? 0); // retained for API compatibility
            $capital        = (float) ($agg['capital'][$mid] ?? 0);
            $specialSavings = (float) ($agg['specialSavings'][$mid] ?? 0);

            $otherSavingsData = [];
            foreach ($otherSavingsTypes as $type) {
                $tid = (int) $type->type_id;
                $otherSavingsData[] = [
                    'type_id'     => $tid,
                    'type_name'   => $type->type_name,
                    'type_prefix' => $type->type_prefix,
                    'amount'      => round((float) ($agg['fosaByType'][$mid][$tid] ?? 0), 2),
                ];
            }

            $generalOtherSavings = (float) ($agg['fosaGeneral'][$mid] ?? 0);

            $loanData        = [];
            $overallExposure = 0.0;

            foreach ($loanTypes as $lt) {
                $tid = (int) $lt->loan_type_id;

                $taken   = (float) ($agg['loanTaken'][$mid][$tid] ?? 0);
                $balance = (float) ($agg['loanBalance'][$mid][$tid] ?? 0);

                // Negative balances are intentionally preserved.
                $overallExposure += $balance;

                $loanData[] = [
                    'loan_type_name' => $lt->loan_type_name,
                    'taken'          => round($taken, 2),
                    'balance'        => round($balance, 2),
                ];
            }

            $rows[] = [
                'member_name'        => $m->member_name,
                'member_sacco_id'    => $m->member_sacco_id,
                'member_national_id' => $m->member_national_id,
                'member_email'       => $m->member_email,
                'member_phone_no'    => $m->member_phone_no,
                'member_gender'      => $m->member_gender,
                'member_active'      => $active,
                'company'            => $m->company_name,
                'department'         => $m->department_name,
                'position'           => $m->position_name,
                'capital'               => round($capital, 2),
                'savings'               => round($savings, 2),
                'special_savings'       => round($specialSavings, 2),
                'other_savings'         => $otherSavingsData,
                'general_other_savings' => round($generalOtherSavings, 2),
                'fosa'                  => round($fosa, 2), // consolidated compatibility value; not displayed
                'overall_exposure'      => round($overallExposure, 2),
                'loans'              => $loanData,
            ];
        }

        return response()->json([
            'period' => $period,
            'data'   => $rows,
            'meta'   => [
                'page'         => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'has_more'     => (($page * $perPage) < $total),
                'generated_at' => now()->format('Y-m-d H:i:s T'),
                'generated_by' => $this->generatedByLabel(),
                'search'       => $pms_srch,
                'other_savings_types' => $otherSavingsTypes->map(fn ($type) => [
                    'type_id'     => (int) $type->type_id,
                    'type_name'   => $type->type_name,
                    'type_prefix' => $type->type_prefix,
                    'type_active' => $type->type_active ?? null,
                ])->values()->all(),
            ],
        ]);
    }
    /**
     * CSV EXPORT - STREAM + CHUNK (handles 100k+ safely)
     */
    public function export(Request $request)
    {
        $period   = $request->get('period', date('Ym'));
        $pms_srch = trim((string) $request->get('pms_srch', ''));

        if (!preg_match('/^\d{6}$/', $period)) {
            abort(400, 'Invalid period');
        }

        $loanTypes          = $this->loanTypes();
        $otherSavingsConfig = $this->otherSavingsConfiguration();
        $otherSavingsTypes  = $otherSavingsConfig['types'];
        $filename           = "member_financial_position_{$period}.csv";
        $generatedAt = now()->format('Y-m-d H:i:s T');
        $generatedBy = $this->generatedByLabel();

        $totalMembers = (clone $this->membersBaseQuery($pms_srch))
            ->distinct()
            ->count('sacco_members.member_id');

        $showGeneralOtherSavings = $this->hasGeneralOtherSavings(
            $period,
            $pms_srch,
            $otherSavingsConfig
        );

        return response()->stream(function () use (
            $period,
            $pms_srch,
            $loanTypes,
            $otherSavingsConfig,
            $otherSavingsTypes,
            $showGeneralOtherSavings,
            $generatedAt,
            $generatedBy,
            $totalMembers
        ) {
            $out = fopen('php://output', 'w');

            // Audit/report context.
            fputcsv($out, ['Report', 'Member Financial Position']);
            fputcsv($out, ['As At Period', $period]);
            fputcsv($out, ['Generated At', $generatedAt]);
            fputcsv($out, ['Generated By', $generatedBy]);
            fputcsv($out, ['Search', $pms_srch !== '' ? $pms_srch : 'All members']);
            fputcsv($out, ['Total Members', $totalMembers]);
            fputcsv($out, []);

            $header = [
                'Name',
                'Sacco ID',
                'National ID',
                'Email',
                'Phone',
                'Gender',
                'Active',
                'Company',
                'Department',
                'Position',
                'Capital',
                'Savings',
                'Special Savings',
            ];

            foreach ($otherSavingsTypes as $type) {
                $header[] = 'Other Savings - ' . $type->type_name;
            }

            // Safety/fallback bucket appears only when truly uncategorised/invalid balances exist.
            if ($showGeneralOtherSavings) {
                $header[] = 'General Other Savings';
            }

            $header[] = 'Overall Loan Exposure';

            foreach ($loanTypes as $l) {
                $header[] = $l->loan_type_name . ' Taken';
                $header[] = $l->loan_type_name . ' Balance';
            }

            fputcsv($out, $header);

            $chunkSize = 5000;

            $membersQuery = $this->membersBaseQuery($pms_srch)
                ->select([
                    DB::raw('sacco_members.member_id as member_id'),
                    'sacco_members.member_name',
                    'sacco_members.member_sacco_id',
                    'sacco_members.member_national_id',
                    'sacco_members.member_email',
                    'sacco_members.member_phone_no',
                    'sacco_members.member_gender',
                    DB::raw('sacco_members.member_active as member_active'),
                    'sacco_department.department_name',
                    'sacco_company.company_name',
                    'sacco_position.position_name',
                ])
                ->orderBy('sacco_members.member_id');

            $membersQuery->chunkById($chunkSize, function ($chunk) use ($out, $period, $loanTypes, $otherSavingsConfig, $otherSavingsTypes, $showGeneralOtherSavings) {
                $memberIds = $chunk->pluck('member_id')->map(fn ($v) => (int) $v)->all();
                $agg = $this->aggregatesForMembers($memberIds, $period, $otherSavingsConfig);

                foreach ($chunk as $m) {
                    $mid = (int) $m->member_id;

                    $active  = (strtoupper(trim((string) ($m->member_active ?? ''))) === 'Y') ? 'Yes' : 'No';
                    $capital        = round((float) ($agg['capital'][$mid] ?? 0), 2);
                    $savings        = round((float) ($agg['savings'][$mid] ?? 0), 2);
                    $specialSavings = round((float) ($agg['specialSavings'][$mid] ?? 0), 2);

                    $overallExposure = 0.0;
                    foreach ($loanTypes as $lt) {
                        $tid = (int) $lt->loan_type_id;
                        $overallExposure += (float) ($agg['loanBalance'][$mid][$tid] ?? 0);
                    }

                    $line = [
                        $m->member_name,
                        $m->member_sacco_id,
                        $m->member_national_id,
                        $m->member_email,
                        $m->member_phone_no,
                        $m->member_gender,
                        $active,
                        $m->company_name,
                        $m->department_name,
                        $m->position_name,
                        $capital,
                        $savings,
                        $specialSavings,
                    ];

                    foreach ($otherSavingsTypes as $type) {
                        $tid = (int) $type->type_id;
                        $line[] = round((float) ($agg['fosaByType'][$mid][$tid] ?? 0), 2);
                    }

                    if ($showGeneralOtherSavings) {
                        $line[] = round((float) ($agg['fosaGeneral'][$mid] ?? 0), 2);
                    }

                    $line[] = round($overallExposure, 2);

                    foreach ($loanTypes as $lt) {
                        $tid = (int) $lt->loan_type_id;

                        $taken   = (float) ($agg['loanTaken'][$mid][$tid] ?? 0);
                        $balance = (float) ($agg['loanBalance'][$mid][$tid] ?? 0);

                        $line[] = round($taken, 2);
                        $line[] = round($balance, 2);
                    }

                    fputcsv($out, $line);
                }

                if (function_exists('flush')) flush();
            }, 'sacco_members.member_id', 'member_id');

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
}