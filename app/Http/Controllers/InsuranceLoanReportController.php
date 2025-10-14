<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;

class InsuranceLoanReportController extends Controller
{
    private $currentPeriod;

    public function __construct()
    {
        // Get active accounting period
        $this->currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();
    }

    public function index(Request $request)
    {
        // ✅ Default numeric current period (YYYYMM)
        $defaultPeriod = $this->currentPeriod ? $this->currentPeriod->period_name : date('Ym');
        $period        = $request->input('period', $defaultPeriod);
        $pms_srch      = '%' . ($request->input('pms_srch') ?? '') . '%';

        // ✅ Build base query
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

        // ✅ Optional search filter
        if ($request->filled('pms_srch')) {
            $query->where(function ($q) use ($pms_srch) {
                $q->where('sacco_members.member_name', 'like', $pms_srch)
                    ->orWhere('sacco_members.member_sacco_id', 'like', $pms_srch)
                    ->orWhere('sacco_members.member_national_id', 'like', $pms_srch);
            });
        }

        $loans = $query->orderBy('sacco_members.member_name', 'asc')->get();

        // ✅ Compute balances
        $records = [];
        foreach ($loans as $loan) {
            $loanBalance = $this->getLoanBalance($loan->loan_id, $loan->loan_amount, $period);
            if ($loanBalance <= 1) continue;

            $endDate = date('Y-m-d', strtotime($loan->loan_on . ' + ' . $loan->loan_payment_period . ' months'));
            $interval = (new DateTime(date('Y-m-d')))->diff(new DateTime($endDate));
            $monthsRemaining = ceil(($interval->days) / (365 / 12));

            if (date('Y-m-d') > $endDate) {
                $monthsRemaining = "<span class='text-danger'>{$monthsRemaining}</span>";
            }

            $records[] = [
    'member_sacco_id'     => $loan->member_sacco_id,
    'member_name'          => $loan->member_name,
    'member_phone_no'      => $loan->member_phone_no,
    'member_national_id'   => $loan->member_national_id,
    'member_kra_pin'       => $loan->member_kra_pin,
    'member_gender'        => $loan->member_gender,
    'loan_amount'          => round($loan->loan_amount, 2),
    'loan_on'              => substr($loan->loan_on, 0, 10),
    'loan_payment_period'  => $loan->loan_payment_period,
    'loan_balance'         => round($loanBalance, 2),
    'months_remaining'     => $monthsRemaining,
    'loan_type_interest'   => $loan->loan_type_interest,
    'loan_type_name'       => $loan->loan_type_name,
    'loan_id'              => $loan->loan_id,
];
        }

        return view('reports.insurance_loan', compact('records', 'period', 'pms_srch'));
    }

    /**
     * ✅ Compute loan balance as at the given accounting period
     * Uses numeric period (YYYYMM) cutoff — not date.
     */
    private function getLoanBalance($loanId, $loanAmount, $period)
    {
        // Sum all payments up to and including the selected period
        $payments = DB::table('sacco_loan_payments')
            ->where('loan_payments_loan_id', $loanId)
            ->where('loan_payments_period', '<=', $period)
            ->sum('loan_payments_amount');

        $remaining = $loanAmount - $payments;
        return max($remaining, 0);
    }

    public function export(Request $request)
{
    $defaultPeriod = $this->currentPeriod ? $this->currentPeriod->period_name : date('Ym');
    $period        = $request->input('period', $defaultPeriod);
    $pms_srch      = '%' . ($request->input('pms_srch') ?? '') . '%';

    // Base query (same as index)
    $query = DB::table('sacco_loans')
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
            'sacco_loans.loan_payment_period',
            'sacco_loans.loan_id'
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

    // Build CSV headers
    $filename = "insurance_loans_report_{$period}.csv";
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
    ];

    // Stream CSV output
    $callback = function () use ($loans, $period) {
        $handle = fopen('php://output', 'w');

        // Header row
        fputcsv($handle, [
            'Member Name', 'Phone', 'National ID', 'KRA PIN',
            'Loan Type', 'Interest (%)', 'Loan Amount (KES)', 
            'Loan Date', 'Payment Period (Months)', 'Outstanding Balance (KES)'
        ]);

        foreach ($loans as $loan) {
            $balance = $this->getLoanBalance($loan->loan_id, $loan->loan_amount, $period);
            fputcsv($handle, [
                strtoupper($loan->member_name),
                $loan->member_phone_no,
                $loan->member_national_id,
                $loan->member_kra_pin,
                $loan->loan_type_name,
                $loan->loan_type_interest,
                number_format($loan->loan_amount, 2),
                date('d-M-Y', strtotime($loan->loan_on)),
                $loan->loan_payment_period,
                number_format($balance, 2),
            ]);
        }

        fclose($handle);
    };

    return response()->stream($callback, 200, $headers);
}
}