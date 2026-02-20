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
        // Default to current YYYYMM
        $currentPeriod = date('Ym');

        return view(
            'reports.members.financial_position.index',
            compact('currentPeriod')
        );
    }

    /**
     * Report data (AJAX)
     */
    public function data(Request $request)
    {
        $period    = $request->get('period', date('Ym'));
        $pms_srch  = trim($request->get('pms_srch'));

        if (!preg_match('/^\d{6}$/', $period)) {
            return response()->json(['error' => 'Invalid period'], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | LOAN TYPES
        |--------------------------------------------------------------------------
        */
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | MEMBERS + DEPARTMENT + COMPANY + POSITION
        |--------------------------------------------------------------------------
        */
        $membersQuery = DB::table('sacco_members')
            ->join(
                'sacco_department',
                'sacco_members.member_dept',
                '=',
                'sacco_department.department_id'
            )
            ->join(
                'sacco_company',
                'sacco_department.department_company_id',
                '=',
                'sacco_company.company_id'
            )
            ->join(
                'sacco_position',
                'sacco_members.member_position',
                '=',
                'sacco_position.position_id'
            )
            ->where('member_deleted', '<>', 'Y')
            ->where('member_active', '=', 'Y');

        /*
        |--------------------------------------------------------------------------
        | SEARCH FILTER (NOW ACTUALLY WORKING)
        |--------------------------------------------------------------------------
        */
        if ($pms_srch !== '') {
            $membersQuery->where(function ($q) use ($pms_srch) {
                $q->where('member_name', 'like', "%{$pms_srch}%")
                  ->orWhere('member_sacco_id', 'like', "%{$pms_srch}%")
                  ->orWhere('member_national_id', 'like', "%{$pms_srch}%")
                  ->orWhere('company_name', 'like', "%{$pms_srch}%")
                  ->orWhere('department_name', 'like', "%{$pms_srch}%");
            });
        }

        $members = $membersQuery
            ->orderBy('member_name')
            ->select([
                'member_id',
                'member_name',
                'member_sacco_id',
                'member_national_id',
                'member_gender',
                'department_name',
                'company_name',
                'position_name'
            ])
            ->get();

        /*
        |--------------------------------------------------------------------------
        | BUILD REPORT ROWS
        |--------------------------------------------------------------------------
        */
        $rows = [];

        foreach ($members as $m) {

            // SAVINGS
            $savings = DB::table('sacco_shares')
                ->where('share_member_id', $m->member_id)
                ->where('share_period', '<=', $period)
                ->sum('share_amount_paying');

            // FOSA
            $fosa = DB::table('sacco_fosas')
                ->where('fosa_member_id', $m->member_id)
                ->where('fosa_period', '<=', $period)
                ->sum('fosa_amount_paying');

            // CAPITAL SHARES
            $capital = DB::table('sacco_capital_shares')
                ->where('share_capitalmember_id', $m->member_id)
                ->where('share_capitalperiod', '<=', $period)
                ->sum('share_capitalamount_paying');

            // LOANS
            $loanData = [];

            foreach ($loanTypes as $lt) {

                $totalLoan = DB::table('sacco_loans')
                    ->where('loan_member', $m->member_id)
                    ->where('loan_loan_type', $lt->loan_type_id)
                    ->where('loan_taken_period', '<=', $period)
                    ->sum('loan_amount');

                $totalPaid = DB::table('sacco_loan_payments')
                    ->join(
                        'sacco_loans',
                        'sacco_loan_payments.loan_payments_loan_id',
                        '=',
                        'sacco_loans.loan_id'
                    )
                    ->where('loan_member', $m->member_id)
                    ->where('loan_loan_type', $lt->loan_type_id)
                    ->where('loan_payments_period', '<=', $period)
                    ->sum('loan_payments_amount');

                $loanData[] = [
                    'loan_type_name' => $lt->loan_type_name,
                    'taken'          => round($totalLoan, 2),
                    'balance'        => round($totalLoan - $totalPaid, 2),
                ];
            }

            $rows[] = [
                'member_name'        => $m->member_name,
                'member_sacco_id'    => $m->member_sacco_id,
                'member_national_id' => $m->member_national_id,
                'member_gender'      => $m->member_gender,
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
            'data'   => $rows
        ]);
    }

    /**
     * CSV EXPORT
     */
    public function export(Request $request)
    {
        $period    = $request->get('period', date('Ym'));
        $pms_srch  = trim($request->get('pms_srch'));

        if (!preg_match('/^\d{6}$/', $period)) {
            abort(400, 'Invalid period');
        }

        $request->merge([
            'period'   => $period,
            'pms_srch' => $pms_srch
        ]);

        $data = $this->data($request)->getData(true)['data'];

        $filename = "member_financial_position_{$period}.csv";

        return response()->stream(function () use ($data) {

            $out = fopen('php://output', 'w');

            $header = [
                'Name',
                'Sacco ID',
                'National ID',
                'Gender',
                'Company',
                'Department',
                'Savings',
                'FOSA',
                'Capital',
            ];

            if (!empty($data)) {
                foreach ($data[0]['loans'] as $l) {
                    $header[] = $l['loan_type_name'] . ' Taken';
                    $header[] = $l['loan_type_name'] . ' Balance';
                }
            }

            fputcsv($out, $header);

            foreach ($data as $r) {

                $line = [
                    $r['member_name'],
                    $r['member_sacco_id'],
                    $r['member_national_id'],
                    $r['member_gender'],
                    $r['company'],
                    $r['department'],
                    $r['savings'],
                    $r['fosa'],
                    $r['capital'],
                ];

                foreach ($r['loans'] as $l) {
                    $line[] = $l['taken'];
                    $line[] = $l['balance'];
                }

                fputcsv($out, $line);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}"
        ]);
    }
}
