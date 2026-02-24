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
     */
    protected function membersBaseQuery(string $pms_srch = '')
    {
        $q = DB::table('sacco_members')
            ->leftJoin('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->leftJoin('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->leftJoin('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id');
        // ✅ removed: ->where('sacco_members.member_deleted', '<>', 'Y');

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
     * Loan types (cached per request)
     */
    protected function loanTypes()
    {
        return DB::table('sacco_loan_types')
            // ✅ removed: ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();
    }

    /**
     * Bulk aggregates for a set of member IDs (FAST)
     */
    protected function aggregatesForMembers(array $memberIds, string $period)
    {
        if (empty($memberIds)) {
            return [
                'savings'   => [],
                'fosa'      => [],
                'capital'   => [],
                'loanTaken' => [], // [member_id][loan_type_id] => amount
                'loanPaid'  => [], // [member_id][loan_type_id] => amount
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
        // LOANS (NO DOUBLE COUNTING)
        // 1) Build per-loan rows: taken + paid_upto (as-at period)
        // 2) Roll up per member + loan_type
        // =====================================================

        // Step 1: per-loan paid (exactly like your audit SQL)
        $perLoan = DB::table('sacco_loans as l')
            ->leftJoin('sacco_loan_payments as p', function ($join) use ($periodInt) {
                $join->on('p.loan_payments_loan_id', '=', 'l.loan_id')
                    ->where('p.loan_payments_period', '<=', $periodInt);
            })
            ->select([
                'l.loan_id',
                'l.loan_member',
                'l.loan_loan_type',
                DB::raw('COALESCE(l.loan_amount,0) as taken'),
                DB::raw('COALESCE(SUM(COALESCE(p.loan_payments_amount,0)),0) as paid_upto'),
            ])
            ->whereIn('l.loan_member', $memberIds)
            ->whereNotNull('l.loan_loan_type')
            ->where('l.loan_taken_period', '<=', $periodInt)
            ->groupBy('l.loan_id', 'l.loan_member', 'l.loan_loan_type', 'l.loan_amount');

        // Step 2: roll up per member + loan type (still no inflation)
        $loanAggRows = DB::query()
            ->fromSub($perLoan, 'x')
            ->select([
                'x.loan_member',
                'x.loan_loan_type',
                DB::raw('SUM(x.taken) as taken_total'),
                DB::raw('SUM(x.paid_upto) as paid_total'),
            ])
            ->groupBy('x.loan_member', 'x.loan_loan_type')
            ->get();

        $loanTaken = [];
        $loanPaid  = [];

        foreach ($loanAggRows as $r) {
            $mid = (int) $r->loan_member;
            $tid = (int) $r->loan_loan_type;

            $loanTaken[$mid][$tid] = (float) ($r->taken_total ?? 0);
            $loanPaid[$mid][$tid]  = (float) ($r->paid_total ?? 0);
        }

        return compact('savings', 'fosa', 'capital', 'loanTaken', 'loanPaid');
    }

    /**
     * Report data (AJAX)
     * ✅ Updated: allow up to 5000 per request (no 1000 cap)
     * GET /.../data?period=YYYYMM&pms_srch=&page=1&per_page=5000
     */
    public function data(Request $request)
    {
        $period   = $request->get('period', date('Ym'));
        $pms_srch = trim((string) $request->get('pms_srch', ''));

        // paging (UI should request per_page=5000 if you want “all” in fewer calls)
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = (int) $request->get('per_page', 5000);
        if ($perPage < 1) $perPage = 5000;

        // ✅ cap at 5000 (safe). If you truly want uncapped, remove the next line.
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

        $memberIds = $members->pluck('member_id')->map(fn($v) => (int) $v)->all();
        $agg       = $this->aggregatesForMembers($memberIds, $period);

        $rows = [];
        foreach ($members as $m) {
            $mid = (int) $m->member_id;

            $active = (strtoupper(trim((string)($m->member_active ?? ''))) === 'Y') ? 'Yes' : 'No';

            $savings = (float) ($agg['savings'][$mid] ?? 0);
            $fosa    = (float) ($agg['fosa'][$mid] ?? 0);
            $capital = (float) ($agg['capital'][$mid] ?? 0);

            $loanData = [];
            foreach ($loanTypes as $lt) {
                $tid = (int) $lt->loan_type_id;

                $taken = (float) ($agg['loanTaken'][$mid][$tid] ?? 0);
                $paid  = (float) ($agg['loanPaid'][$mid][$tid] ?? 0);

                $loanData[] = [
                    'loan_type_name' => $lt->loan_type_name,
                    'taken'          => round($taken, 2),
                    'balance'        => round($taken - $paid, 2),
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
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'has_more'  => (($page * $perPage) < $total),
            ],
        ]);
    }

    /**
     * CSV EXPORT - STREAM + CHUNK (handles 100k+ safely)
     * ✅ Updated chunk size to 5000 (optional)
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

            // ✅ chunk size set to 5000
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

                $memberIds = $chunk->pluck('member_id')->map(fn($v) => (int) $v)->all();
                $agg = $this->aggregatesForMembers($memberIds, $period);

                foreach ($chunk as $m) {
                    $mid = (int) $m->member_id;

                    $active = (strtoupper(trim((string)($m->member_active ?? ''))) === 'Y') ? 'Yes' : 'No';

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

                        $taken = (float) ($agg['loanTaken'][$mid][$tid] ?? 0);
                        $paid  = (float) ($agg['loanPaid'][$mid][$tid] ?? 0);

                        $line[] = round($taken, 2);
                        $line[] = round($taken - $paid, 2);
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
