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
                    ->orWhere('sacco_company.company_name', 'like', "%{$pms_srch}%")
                    ->orWhere('sacco_department.department_name', 'like', "%{$pms_srch}%");
            });
        }

        return $q;
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
    protected function aggregatesForMembers(array $memberIds, string $period)
    {
        if (empty($memberIds)) {
            return [
                'savings'     => [],
                'fosa'        => [],
                'capital'     => [],
                'loanTaken'   => [], // [member_id][loan_type_id] => taken_total
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
        // FOSA
        // -------------------------
        $fosa = DB::table('sacco_fosas')
            ->select('fosa_member_id', DB::raw('SUM(COALESCE(fosa_amount_paying,0)) as total'))
            ->whereIn('fosa_member_id', $memberIds)
            ->where('fosa_period', '<=', $periodInt)
            ->groupBy('fosa_member_id')
            ->pluck('total', 'fosa_member_id')
            ->toArray();

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

        return compact('savings', 'fosa', 'capital', 'loanTaken', 'loanPaid', 'loanBalance');
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

        $loanTypes = $this->loanTypes();
        $base      = $this->membersBaseQuery($pms_srch);

        $total = (clone $base)->distinct('sacco_members.member_id')->count('sacco_members.member_id');

        $members = (clone $base)
            ->select([
                DB::raw('sacco_members.member_id as member_id'),
                'sacco_members.member_name',
                'sacco_members.member_sacco_id',
                'sacco_members.member_national_id',
                'sacco_members.member_gender',
                DB::raw('sacco_members.member_active as member_active'),
                'sacco_department.department_name',
                'sacco_company.company_name',
                'sacco_position.position_name',
            ])
            ->orderBy('sacco_members.member_name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $memberIds = $members->pluck('member_id')->map(fn ($v) => (int) $v)->all();
        $agg       = $this->aggregatesForMembers($memberIds, $period);

        $rows = [];
        foreach ($members as $m) {
            $mid = (int) $m->member_id;

            $active  = (strtoupper(trim((string)($m->member_active ?? ''))) === 'Y') ? 'Yes' : 'No';
            $savings = (float) ($agg['savings'][$mid] ?? 0);
            $fosa    = (float) ($agg['fosa'][$mid] ?? 0);
            $capital = (float) ($agg['capital'][$mid] ?? 0);

            $loanData = [];
            foreach ($loanTypes as $lt) {
                $tid = (int) $lt->loan_type_id;

                $taken   = (float) ($agg['loanTaken'][$mid][$tid] ?? 0);
                $balance = (float) ($agg['loanBalance'][$mid][$tid] ?? 0); // ✅ aligned to audit SQL

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
                'member_gender'      => $m->member_gender,
                'member_active'      => $active,
                'company'            => $m->company_name,
                'department'         => $m->department_name,
                'position'           => $m->position_name,
                'savings'            => round($savings, 2),
                'fosa'               => round($fosa, 2),
                'capital'            => round($capital, 2),
                'loans'              => $loanData,
            ];
        }

        return response()->json([
            'period' => $period,
            'data'   => $rows,
            'meta'   => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'has_more' => (($page * $perPage) < $total),
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

        $loanTypes = $this->loanTypes();
        $filename  = "member_financial_position_{$period}.csv";

        return response()->stream(function () use ($period, $pms_srch, $loanTypes) {

            $out = fopen('php://output', 'w');

            $header = [
                'Name',
                'Sacco ID',
                'National ID',
                'Gender',
                'Active',
                'Company',
                'Department',
                'Position',
                'Savings',
                'FOSA',
                'Capital',
            ];

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
                    'sacco_members.member_gender',
                    DB::raw('sacco_members.member_active as member_active'),
                    'sacco_department.department_name',
                    'sacco_company.company_name',
                    'sacco_position.position_name',
                ])
                ->orderBy('sacco_members.member_id');

            $membersQuery->chunkById($chunkSize, function ($chunk) use ($out, $period, $loanTypes) {

                $memberIds = $chunk->pluck('member_id')->map(fn ($v) => (int) $v)->all();
                $agg = $this->aggregatesForMembers($memberIds, $period);

                foreach ($chunk as $m) {
                    $mid = (int) $m->member_id;

                    $active  = (strtoupper(trim((string)($m->member_active ?? ''))) === 'Y') ? 'Yes' : 'No';
                    $savings = round((float) ($agg['savings'][$mid] ?? 0), 2);
                    $fosa    = round((float) ($agg['fosa'][$mid] ?? 0), 2);
                    $capital = round((float) ($agg['capital'][$mid] ?? 0), 2);

                    $line = [
                        $m->member_name,
                        $m->member_sacco_id,
                        $m->member_national_id,
                        $m->member_gender,
                        $active,
                        $m->company_name,
                        $m->department_name,
                        $m->position_name,
                        $savings,
                        $fosa,
                        $capital,
                    ];

                    foreach ($loanTypes as $lt) {
                        $tid = (int) $lt->loan_type_id;

                        $taken   = (float) ($agg['loanTaken'][$mid][$tid] ?? 0);
                        $balance = (float) ($agg['loanBalance'][$mid][$tid] ?? 0); // ✅ aligned to audit SQL

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