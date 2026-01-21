<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InsuranceLoanReportController extends Controller
{
    private $currentPeriod;

    public function __construct()
    {
        $this->currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();
    }

    public function index(Request $request)
    {
        $defaultPeriod = $this->currentPeriod ? $this->currentPeriod->period_name : date('Ym');
        $period   = $request->input('period', $defaultPeriod);
        $pms_srch = '%' . ($request->input('pms_srch') ?? '') . '%';

        $query = DB::table('sacco_loans')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_loan_category', 'sacco_loans.loan_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->select(
                'sacco_loans.*',
                'sacco_members.member_name',
                'sacco_members.member_phone_no',
                'sacco_members.member_kra_pin',
                'sacco_members.member_national_id',
                'sacco_members.member_gender',
                'sacco_members.member_sacco_id',
                'sacco_loan_types.loan_type_name',
                'sacco_loan_types.loan_type_interest'
            )
            ->where('sacco_members.member_active', 'Y')
            ->where('sacco_loans.loan_taken_period', '<=', $period);

        if ($request->filled('pms_srch')) {
            $query->where(function ($q) use ($pms_srch) {
                $q->where('sacco_members.member_name', 'like', $pms_srch)
                  ->orWhere('sacco_members.member_sacco_id', 'like', $pms_srch)
                  ->orWhere('sacco_members.member_national_id', 'like', $pms_srch);
            });
        }

        $loans = $query->orderBy('sacco_members.member_name', 'asc')->get();

        $records = [];

        foreach ($loans as $loan) {

            $loanBalance = $this->getLoanBalance($loan->loan_id, $loan->loan_amount, $period);
            if ($loanBalance <= 1) continue;

            [$monthsRemaining, $loanEnd] =
                $this->calculateRemainingMonths(
                    $loan->loan_taken_period,
                    $loan->loan_payment_period,
                    $period
                );

            if ($monthsRemaining == 0 && $loanEnd < (int)$period) {
                $monthsRemaining = "<span class='text-danger'>Overdue</span>";
            }

            /** 🔑 FIX: NORMALISED DISPLAY DATE **/
            $approvalYm = date('Ym', strtotime($loan->loan_on));
            $periodYm   = (string) $loan->loan_taken_period;

            if ($approvalYm !== $periodYm) {
                // loan period is truth → mid-month of period
                $displayLoanDate = sprintf(
                    '%04d-%02d-15',
                    floor((int)$periodYm / 100),
                    ((int)$periodYm % 100)
                );
            } else {
                $displayLoanDate = substr($loan->loan_on, 0, 10);
            }

            $records[] = [
                'member_sacco_id'    => $loan->member_sacco_id,
                'member_name'        => $loan->member_name,
                'member_phone_no'    => $loan->member_phone_no,
                'member_national_id' => $loan->member_national_id,
                'member_kra_pin'     => $loan->member_kra_pin,
                'member_gender'      => $loan->member_gender,

                'loan_amount'        => round($loan->loan_amount, 2),
                'loan_on'            => $displayLoanDate,
                'loan_taken_period'  => $loan->loan_taken_period,
                'loan_payment_period'=> $loan->loan_payment_period,

                'loan_balance'       => round($loanBalance, 2),
                'months_remaining'   => $monthsRemaining,

                'loan_type_interest' => $loan->loan_type_interest,
                'loan_type_name'     => $loan->loan_type_name,
                'loan_id'            => $loan->loan_id,
            ];
        }

        return view('reports.insurance_loan', compact('records', 'period', 'pms_srch'));
    }

    private function getLoanBalance($loanId, $loanAmount, $period)
    {
        $payments = DB::table('sacco_loan_payments')
            ->where('loan_payments_loan_id', $loanId)
            ->where('loan_payments_period', '<=', $period)
            ->sum('loan_payments_amount');

        return max($loanAmount - $payments, 0);
    }

    private function calculateRemainingMonths($loanStart, $loanTerm, $current)
    {
        $loanStart = (int)$loanStart;
        $loanTerm  = (int)$loanTerm;
        $current   = (int)$current;

        $year  = floor($loanStart / 100);
        $month = ($loanStart % 100) + $loanTerm;

        while ($month > 12) {
            $month -= 12;
            $year++;
        }

        $loanEnd = (int)sprintf('%04d%02d', $year, $month);

        $monthsRemaining =
            (($loanEnd / 100) * 12 + ($loanEnd % 100)) -
            (($current / 100) * 12 + ($current % 100));

        return [max(0, (int)$monthsRemaining), $loanEnd];
    }

    public function export(Request $request)
    {
        $defaultPeriod = $this->currentPeriod ? $this->currentPeriod->period_name : date('Ym');
        $period   = $request->input('period', $defaultPeriod);
        $pms_srch = '%' . ($request->input('pms_srch') ?? '') . '%';

        $loans = DB::table('sacco_loans')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_members.member_name',
                'sacco_members.member_phone_no',
                'sacco_members.member_national_id',
                'sacco_members.member_kra_pin',
                'sacco_loan_types.loan_type_name',
                'sacco_loan_types.loan_type_interest',
                'sacco_loans.loan_amount',
                'sacco_loans.loan_on',
                'sacco_loans.loan_taken_period',
                'sacco_loans.loan_payment_period',
                'sacco_loans.loan_id'
            )
            ->where('sacco_members.member_active', 'Y')
            ->where('sacco_loans.loan_taken_period', '<=', $period)
            ->orderBy('sacco_members.member_name', 'asc')
            ->get();

        $filename = "insurance_loans_report_{$period}.csv";

        return response()->stream(function () use ($loans, $period) {

            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Member Name','Phone','National ID','KRA PIN',
                'Loan Type','Interest (%)','Loan Amount (KES)',
                'Loan Date','Loan Period','Tenure (Months)',
                'Outstanding (KES)','Remaining (Months)'
            ]);

            foreach ($loans as $loan) {

                $balance = $this->getLoanBalance($loan->loan_id, $loan->loan_amount, $period);
                [$monthsRemaining, $loanEnd] =
                    $this->calculateRemainingMonths(
                        $loan->loan_taken_period,
                        $loan->loan_payment_period,
                        $period
                    );

                if ($monthsRemaining == 0 && $loanEnd < (int)$period) {
                    $monthsRemaining = 'OVERDUE';
                }

                // SAME NORMALISATION LOGIC
                $approvalYm = date('Ym', strtotime($loan->loan_on));
                $periodYm   = (string)$loan->loan_taken_period;

                if ($approvalYm !== $periodYm) {
                    $displayLoanDate = sprintf(
                        '%04d-%02d-15',
                        floor((int)$periodYm / 100),
                        ((int)$periodYm % 100)
                    );
                } else {
                    $displayLoanDate = date('Y-m-d', strtotime($loan->loan_on));
                }

                fputcsv($out, [
                    strtoupper($loan->member_name),
                    $loan->member_phone_no,
                    $loan->member_national_id,
                    $loan->member_kra_pin,
                    $loan->loan_type_name,
                    $loan->loan_type_interest,
                    number_format($loan->loan_amount, 2),
                    date('d-M-Y', strtotime($displayLoanDate)),
                    $loan->loan_taken_period,
                    $loan->loan_payment_period,
                    number_format($balance, 2),
                    $monthsRemaining,
                ]);
            }

            fclose($out);

        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
