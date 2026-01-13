<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberFinancialPositionController extends Controller
{
    /**
     * Show report filter UI
     */
    public function index(Request $request)
    {
        // Load available accounting periods (latest first)
        $periods = DB::table('sacco_periods')
            ->orderBy('period_end', 'desc')
            ->get();

        return view('reports.members.financial_position.index', compact('periods'));
    }

    /**
     * Fetch report data (AJAX / DataTables)
     */
    public function data(Request $request)
    {
        $period = $request->input('period');

        if (!$period) {
            return response()->json([
                'error' => 'Accounting period is required'
            ], 422);
        }

        // Load active loan types (dynamic columns)
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();

        // Load active members with org context
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
            // SAVINGS (Shares)
            // ======================
            $savings = DB::table('sacco_shares')
                ->where('share_member_id', $member->member_id)
                ->where('share_transdate', '<=', $period)
                ->sum('share_amount_paying');

            // ======================
            // FOSA
            // ======================
            $fosa = DB::table('sacco_fosas')
                ->where('fosa_member_id', $member->member_id)
                ->where('fosa_transdate', '<=', $period)
                ->sum('fosa_amount');

            // ======================
            // CAPITAL SHARES
            // ======================
            $capital = DB::table('sacco_capital_shares')
                ->where('share_capitalmember_id', $member->member_id)
                ->where('share_capitaltransdate', '<=', $period)
                ->sum('share_capitalamount_paying');

            // ======================
            // LOANS (per loan type)
            // ======================
            $loans = [];

            foreach ($loanTypes as $type) {

                // Total loan principal issued
                $taken = DB::table('sacco_loans')
                    ->where('loan_member_id', $member->member_id)
                    ->where('loan_type_id', $type->loan_type_id)
                    ->where('loan_date', '<=', $period)
                    ->where('loan_deleted', '<>', 'Y')
                    ->sum('loan_amount');

                // Total repayments made
                $repaid = DB::table('sacco_loan_payments')
                    ->where('loan_payments_member_id', $member->member_id)
                    ->where('loan_payments_loan_type_id', $type->loan_type_id)
                    ->where('loan_payments_transdate', '<=', $period)
                    ->sum(DB::raw('loan_payments_principal'));

                $loans[] = [
                    'loan_type_id'   => $type->loan_type_id,
                    'loan_type_name' => $type->loan_type_name,
                    'taken'          => round($taken, 2),
                    'balance'        => round($taken - $repaid, 2),
                ];
            }

            $results[] = [
                'member_id'          => $member->member_id,
                'member_name'        => $member->member_name,
                'member_sacco_id'    => $member->member_sacco_id,
                'member_national_id' => $member->member_national_id,
                'member_gender'      => $member->member_gender,
                'company'            => $member->company_name,
                'department'         => $member->department_name,
                'position'           => $member->position_name,

                'savings' => round($savings, 2),
                'fosa'    => round($fosa, 2),
                'capital' => round($capital, 2),

                'loans'   => $loans,
            ];
        }

        return response()->json([
            'period' => $period,
            'data'   => $results,
        ]);
    }

    /**
     * Export report (CSV)
     */
    public function export(Request $request)
    {
        $period = $request->input('period');

        if (!$period) {
            return redirect()->back()->with('error', 'Accounting period is required');
        }

        $filename = 'member_financial_position_' . date('Ymd_His') . '.csv';

        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();

        $members = $this->data($request)->getData(true)['data'];

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($members, $loanTypes) {

            $handle = fopen('php://output', 'w');

            // CSV Header
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

            foreach ($loanTypes as $type) {
                $header[] = $type->loan_type_name . ' Taken';
                $header[] = $type->loan_type_name . ' Balance';
            }

            fputcsv($handle, $header);

            // Rows
            foreach ($members as $row) {

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

                fputcsv($handle, $line);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
