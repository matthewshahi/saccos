<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberFinancialPositionController extends Controller
{
    /**
     * Show report UI
     * No period table, no lookup.
     */
    public function index()
    {
        // Default period = current YYYYMM
        $currentPeriod = date('Ym');

        return view('reports.members.financial_position.index', compact('currentPeriod'));
    }

    /**
     * Fetch report data (AS AT period)
     */
    public function data(Request $request)
    {
        // Default to current YYYYMM if not provided
        $period = $request->input('period', date('Ym'));

        // Safety: numeric YYYYMM only
        if (!preg_match('/^\d{6}$/', $period)) {
            return response()->json([
                'error' => 'Invalid period format. Expected YYYYMM.'
            ], 422);
        }

        // ======================
        // Loan types
        // ======================
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();

        // ======================
        // Active members
        // ======================
        $members = DB::table('sacco_members')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id')
            ->where('member_deleted', '<>', 'Y')
            ->where('member_active', '=', 'Y')
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

        $results = [];

        foreach ($members as $member) {

            // ======================
            // SAVINGS
            // ======================
            $savings = DB::table('sacco_shares')
                ->where('share_member_id', $member->member_id)
                ->where('share_period', '<=', $period)
                ->sum('share_amount_paying');

            // ======================
            // FOSA
            // ======================
            $fosa = DB::table('sacco_fosas')
                ->where('fosa_member_id', $member->member_id)
                ->where('fosa_period', '<=', $period)
                ->sum('fosa_amount');

            // ======================
            // CAPITAL
            // ======================
            $capital = DB::table('sacco_capital_shares')
                ->where('share_capitalmember_id', $member->member_id)
                ->where('share_capitalperiod', '<=', $period)
                ->sum('share_capitalamount_paying');

            // ======================
            // LOANS
            // ======================
            $loans = [];

            foreach ($loanTypes as $type) {

                // Loan principal issued
                $taken = DB::table('sacco_loans')
                    ->where('loan_member_id', $member->member_id)
                    ->where('loan_type_id', $type->loan_type_id)
                    ->where('loan_period', '<=', $period)
                    ->where('loan_deleted', '<>', 'Y')
                    ->sum('loan_amount');

                // Principal repaid
                $repaid = DB::table('sacco_loan_payments')
                    ->where('loan_payments_member_id', $member->member_id)
                    ->where('loan_payments_loan_type_id', $type->loan_type_id)
                    ->where('loan_payments_period', '<=', $period)
                    ->sum('loan_payments_principal');

                $loans[] = [
                    'loan_type_name' => $type->loan_type_name,
                    'taken'          => round($taken, 2),
                    'balance'        => round($taken - $repaid, 2),
                ];
            }

            $results[] = [
                'member_name'        => $member->member_name,
                'member_sacco_id'    => $member->member_sacco_id,
                'member_national_id' => $member->member_national_id,
                'member_gender'      => $member->member_gender,
                'company'            => $member->company_name,
                'department'         => $member->department_name,
                'position'           => $member->position_name,
                'savings'            => round($savings, 2),
                'fosa'               => round($fosa, 2),
                'capital'            => round($capital, 2),
                'loans'              => $loans,
            ];
        }

        return response()->json([
            'period' => $period,
            'data'   => $results,
        ]);
    }

    /**
     * Export CSV
     */
    public function export(Request $request)
    {
        $period = $request->input('period', date('Ym'));

        if (!preg_match('/^\d{6}$/', $period)) {
            return redirect()->back()->with('error', 'Invalid period format');
        }

        $request->merge(['period' => $period]);
        $data = $this->data($request)->getData(true)['data'];

        $filename = "member_financial_position_{$period}.csv";

        return response()->stream(function () use ($data) {

            $out = fopen('php://output', 'w');

            // Header
            $header = [
                'Member Name',
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
                foreach ($data[0]['loans'] as $loan) {
                    $header[] = $loan['loan_type_name'] . ' Taken';
                    $header[] = $loan['loan_type_name'] . ' Balance';
                }
            }

            fputcsv($out, $header);

            foreach ($data as $row) {
                $line = [
                    $row['member_name'],
                    $row['member_sacco_id'],
                    $row['member_national_id'],
                    $row['member_gender'],
                    $row['company'],
                    $row['department'],
                    $row['savings'],
                    $row['fosa'],
                    $row['capital'],
                ];

                foreach ($row['loans'] as $loan) {
                    $line[] = $loan['taken'];
                    $line[] = $loan['balance'];
                }

                fputcsv($out, $line);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
}
