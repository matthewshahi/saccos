<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicLoansController extends Controller
{
    public function loansTypesList()
    {
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', 'N')
            ->orderBy('loan_type_name', 'asc')
            ->paginate(10);

        return view('public.loans_types_list', compact('loanTypes'));
    }

    public function calculator()
    {
        return view('public.loan_calculator');
    }

    public function loanDetails($id)
    {
        $loan = DB::table('sacco_loan_types')
            ->where('loan_type_id', $id)
            ->where('loan_type_deleted', 'N')
            ->first();

        if (!$loan) {
            return redirect()->route('loans.types.list')->with('error', 'Loan type not found.');
        }

        return view('public.loan_details', compact('loan'));
    }

    public function showLoanCalculator($id)
    {
        $loan = DB::table('sacco_loan_types')
            ->where('loan_type_id', $id)
            ->where('loan_type_deleted', 'N')
            ->first();

        if (!$loan) {
            return redirect()->route('loans.types.list')->with('error', 'Loan type not found.');
        }

        return view('public.loan_calculator', compact('loan'));
    }

    
    public function calculateLoan(Request $request, $id)
    {
        $loan = DB::table('sacco_loan_types')
            ->where('loan_type_id', $id)
            ->where('loan_type_deleted', 'N')
            ->first();

        if (!$loan) {
            return redirect()->route('loans.types.list')->with('error', 'Loan type not found.');
        }

        // Validate input with custom redirection on failure
        $validator = \Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1|max:' . $loan->loan_type_max_amount,
            'duration' => 'required|integer|min:1|max:' . $loan->loan_type_duration,
        ]);

        if ($validator->fails()) {
            // Redirect back to the calculator view with errors
            return redirect()->route('loan.calculator', ['id' => $id])
                ->withErrors($validator)
                ->withInput();
        }

        $loanAmount = $request->input('amount');
        $duration = $request->input('duration');
        $interestRate = $loan->loan_type_interest;
        $interestType = strtolower($loan->loan_type_interest_type);
        
        if ($interestType === 'reducing balance') {
            $emi = $this->calculateReducingBalanceEMI($loanAmount, $interestRate, $duration);
            $repaymentSchedule = $this->generateReducingBalanceSchedule($loanAmount, $interestRate, $duration, $emi);
        } else {
            $emi = $this->calculateFixedEMI($loanAmount, $interestRate, $duration);
            $repaymentSchedule = $this->generateFixedSchedule($loanAmount, $interestRate, $duration, $emi);
        }

        return view('public.loan_calculator', compact('loan', 'emi', 'repaymentSchedule'));
    }

    private function calculateReducingBalanceEMI($principal, $rate, $months)
    {
        $monthlyRate = $rate / 12 / 100;
        return $principal * $monthlyRate * pow(1 + $monthlyRate, $months) / (pow(1 + $monthlyRate, $months) - 1);
    }

    private function calculateFixedEMI($principal, $rate, $months)
    {
        $totalInterest = ($principal * $rate * $months) / (100 * 12);
        return ($principal + $totalInterest) / $months;
    }

    private function generateReducingBalanceSchedule($principal, $rate, $months, $emi)
    {
        $schedule = [];
        $monthlyRate = $rate / 12 / 100;

        for ($i = 1; $i <= $months; $i++) {
            $interest = $principal * $monthlyRate;
            $principalPayment = $emi - $interest;
            $principal -= $principalPayment;

            $schedule[] = [
                'month' => $i,
                'principal' => round($principalPayment, 2),
                'interest' => round($interest, 2),
                'emi' => round($emi, 2),
                'balance' => max(round($principal, 2), 0),
            ];
        }

        return $schedule;
    }

    private function generateFixedSchedule($principal, $rate, $months, $emi)
    {
        $schedule = [];
        $monthlyInterest = ($principal * $rate) / (100 * 12);

        for ($i = 1; $i <= $months; $i++) {
            $principalPayment = $emi - $monthlyInterest;
            $principal -= $principalPayment;

            $schedule[] = [
                'month' => $i,
                'principal' => round($principalPayment, 2),
                'interest' => round($monthlyInterest, 2),
                'emi' => round($emi, 2),
                'balance' => max(round($principal, 2), 0),
            ];
        }

        return $schedule;
    }
}