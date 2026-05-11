<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MigrationStatementCleanupController extends Controller
{
    private function ensureMigrationMode()
    {
        abort_unless(
            config('app.migration_mode', 'N') === 'Y',
            403,
            'Migration mode is not enabled.'
        );
    }

    public function destroyLoan(Request $request, $loan)
    {
        $this->ensureMigrationMode();

        DB::transaction(function () use ($loan) {
            $loanRecord = DB::table('sacco_loans')
                ->where('loan_id', $loan)
                ->first();

            abort_if(!$loanRecord, 404, 'Loan not found.');

            DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loan)
                ->delete();

            DB::table('sacco_loan_guarantors')
                ->where('loan_guar_loan_id', $loan)
                ->delete();

            DB::table('sacco_loans')
                ->where('loan_id', $loan)
                ->delete();
        });

        return back()->with(
            'success',
            'Loan, related repayments, and related guarantors deleted. Please run the normal recalculation/reprocessing functions to regularise member balances, loan balances, and tied shares.'
        );
    }

    public function destroyPayment(Request $request, $payment)
    {
        $this->ensureMigrationMode();

        DB::transaction(function () use ($payment) {
            $loanPayment = DB::table('sacco_loan_payments')
                ->where('loan_payments_id', $payment)
                ->first();

            abort_if(!$loanPayment, 404, 'Loan repayment not found.');

            DB::table('sacco_loan_payments')
                ->where('loan_payments_id', $payment)
                ->delete();
        });

        return back()->with(
            'success',
            'Loan repayment deleted. Please manually run the loan recalculation method in the system to regularise loan_paid and member loan balances.'
        );
    }
}