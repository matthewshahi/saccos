<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanPaymentController extends Controller
{
    public function index(Request $request)
{
    $search = $request->input('search');

    // Fetch payment accounts (static for all members)
    $paymentAccounts = DB::table('sacco_sub_account')
        ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
        ->where('main_account_type', 'LIKE', 'ASSETS%') // Only asset accounts
        ->select(
            'sacco_sub_account.sub_account_id',
            'sacco_sub_account.sub_account_name',
            'sacco_sub_account.sub_account_code',
            'sacco_main_account.main_account_code'
        )
        ->orderBy('sacco_sub_account.sub_account_name', 'asc')
        ->get();

    // Fetch loans
    $loans = DB::table('sacco_loans')
        ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
        ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
        ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
        ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
        ->where('sacco_members.member_active', 'Y') // Check if member is active
        ->where('sacco_members.member_deleted', '!=', 'Y') // Check if member is not deleted
        ->select(
            'sacco_loans.loan_id',
            'sacco_loans.loan_member',
            'sacco_loans.loan_amount',
            'sacco_loans.loan_taken_period',
            'sacco_loans.loan_payment_period',
            'sacco_loans.loan_interest_payable',
            'sacco_loans.loan_monthly_repayment_amount',
            'sacco_loans.loan_loan_paid',
            'sacco_loans.loan_on',
            'sacco_members.*',
            'sacco_department.department_name',
            'sacco_company.company_name',
            'sacco_loan_types.loan_type_name'
        )
        ->when($search, function ($query, $search) {
            return $query->where(function ($q) use ($search) {
                $q->where('sacco_company.company_name', 'like', "%{$search}%")
                  ->orWhere('sacco_department.department_name', 'like', "%{$search}%")
                  ->orWhere('sacco_members.member_name', 'like', "%{$search}%")
                  ->orWhere('sacco_members.member_kra_pin', 'like', "%{$search}%")
                  ->orWhere('sacco_members.member_phone_no', 'like', "%{$search}%")
                  ->orWhere('sacco_loans.loan_id', 'like', "%{$search}%");
            });
        })
        ->orderBy('sacco_loans.loan_taken_period', 'desc')
        ->orderBy('sacco_loans.loan_on', 'desc')
        ->paginate(50);

    // Pass data to the Blade view
    return view('loans.single_loan_payment', compact('loans', 'search', 'paymentAccounts'));
}

   
    // public function updateLoanPayment(Request $request)
    // {
    //     // Validate the request
    //     $validated = $request->validate([
    //         'loan_id' => 'required|integer|exists:sacco_loans,loan_id',
    //         'action' => 'required|in:increase,reduce',
    //         'amount_paid' => 'required|numeric|min:0',
    //         'document_no' => 'required|string|max:100',
    //         'description' => 'required|string|max:255',
    //         'payment_account' => 'required|integer|exists:sacco_sub_account,sub_account_id',
    //         'calculate_interest' => 'required|in:Y,N',
    //     ]);

    //     // Fetch loan details
    //     $loan = DB::table('sacco_loans')
    //         ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
    //         ->where('sacco_loans.loan_id', $validated['loan_id'])
    //         ->where('sacco_members.member_active', 'Y') // Ensure member is active
    //         ->where('sacco_members.member_deleted', '!=', 'Y') // Ensure member is not deleted
    //         ->select('sacco_loans.*', 'sacco_members.member_name')
    //         ->first();

    //     if (!$loan) {
    //         return back()->withErrors(['loan_id' => 'Loan not found or member is inactive/deleted.']);
    //     }

    //     // Perform loan action (increase/reduce)
    //     DB::transaction(function () use ($loan, $validated) {
    //         $interestCalculated = 0;

    //         // If reducing loan and interest needs to be calculated
    //         if ($validated['action'] === 'reduce' && $validated['calculate_interest'] === 'Y') {
    //             $interestCalculated = $this->calculateInterest($loan, $validated['amount_paid']);
    //         }

    //         // Update loan balance
    //         $this->updateLoanBalance($loan, $validated['action'], $validated['amount_paid'], $interestCalculated);

    //         // Save payment record
    //         $this->saveLoanPayment($loan, $validated, $interestCalculated);

    //         // Update ledger entries
    //         $this->updateLedgerEntries($loan, $validated, $interestCalculated);
    //     });

    //     // Redirect with success message
    //     return redirect()->route('modify.member.loans')
    //         ->with('success', 'Loan transaction processed successfully.');
    // }
    public function updateLoanPayment(Request $request)
{
    // Validate the request
    $validated = $request->validate([
        'loan_id' => 'required|integer|exists:sacco_loans,loan_id',
        'action' => 'required|in:increase,reduce',
        'amount_paid' => 'required|numeric|min:0',
        'document_no' => 'required|string|max:100',
        'description' => 'required|string|max:255',
        'payment_account' => 'required|integer|exists:sacco_sub_account,sub_account_id',
        'calculate_interest' => 'required|in:Y,N',
        'transaction_date' => 'required|date',
    ]);

    // Fetch loan details
    $loan = DB::table('sacco_loans')
        ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
        ->where('sacco_loans.loan_id', $validated['loan_id'])
        ->where('sacco_members.member_active', 'Y') // Ensure member is active
        ->where('sacco_members.member_deleted', '!=', 'Y') // Ensure member is not deleted
        ->select('sacco_loans.*', 'sacco_members.*') // Include member_name
        ->first();

    if (!$loan) {
        return back()->withErrors(['loan_id' => 'Loan not found or member is inactive/deleted.']);
    }

    DB::transaction(function () use ($loan, $validated) {
        $interestCalculated = 0;

        // If reducing loan and interest needs to be calculated
        if ($validated['action'] === 'reduce' && $validated['calculate_interest'] === 'Y') {
            $interestCalculated = $this->calculateInterest($loan, $validated['amount_paid']);
        }

        $principalAmount = $validated['amount_paid'] - $interestCalculated;

        // Update loan balance
        $this->updateLoanBalance($loan, $validated['action'], $principalAmount, $interestCalculated);

        // Update member loan balance and tied shares
        $this->updateMemberData($loan, $validated['action'], $principalAmount);

        // Update loan guarantors
        $this->updateGuarantors($loan, $validated['action'], $principalAmount);

        // Save payment record
        $this->saveLoanPayment($loan, $validated, $interestCalculated);

        // Update ledger entries
        $this->updateLedgerEntries($loan, $validated, $interestCalculated);
    });

    return redirect()->route('modify.member.loans')
        ->with('success', 'Loan transaction processed successfully.');
}

protected function updateMemberData($loan, $action, $principalAmount)
{
    $memberUpdateData = [];

    // Update member_total_loan
    $memberUpdateData['member_total_loan'] = $action === 'reduce'
        ? $loan->member_total_loan - $principalAmount
        : $loan->member_total_loan + $principalAmount;

    // Update the member table
    DB::table('sacco_members')
        ->where('member_id', $loan->loan_member)
        ->update($memberUpdateData);
}
protected function updateGuarantors($loan, $action, $principalAmount)
{
    // Fetch all guarantors for the loan
    $guarantors = DB::table('sacco_loan_guarantors')
        ->where('loan_guar_loan_id', $loan->loan_id)
        ->get();

    // Ensure the total guaranteed amount is valid
    $totalGuaranteedAmount = $loan->loan_amount_guaranteed;

    if ($totalGuaranteedAmount == 0 || $guarantors->isEmpty()) {
        // Skip guarantor updates if no amount is guaranteed or no guarantors exist
        return;
    }

    foreach ($guarantors as $guarantor) {
        // Calculate the prorated amount to adjust for this guarantor
        $proratedAmount = ($guarantor->loan_guar_amount_guaranteed / $totalGuaranteedAmount) * $principalAmount;

        // Update tied shares for the guarantor
        if ($guarantor->loan_guar_guarantor_id === $loan->loan_member) {
            // Self-guaranteed
            DB::table('sacco_members')
                ->where('member_id', $guarantor->loan_guar_guarantor_id)
                ->update([
                    'member_tied_shares_self' => $action === 'reduce'
                        ? DB::raw('member_tied_shares_self - ' . $proratedAmount)
                        : DB::raw('member_tied_shares_self + ' . $proratedAmount),
                ]);
        } else {
            // Guaranteed by others
            DB::table('sacco_members')
                ->where('member_id', $guarantor->loan_guar_guarantor_id)
                ->update([
                    'member_tied_shares' => $action === 'reduce'
                        ? DB::raw('member_tied_shares - ' . $proratedAmount)
                        : DB::raw('member_tied_shares + ' . $proratedAmount),
                ]);
        }

        // Update the freed amount in the guarantors table
        DB::table('sacco_loan_guarantors')
            ->where('loan_guar_id', $guarantor->loan_guar_id)
            ->update([
                'loan_guar_amount_freed' => $action === 'reduce'
                    ? $guarantor->loan_guar_amount_freed + $proratedAmount
                    : $guarantor->loan_guar_amount_freed - $proratedAmount,
            ]);
    }
}

    /**
     * Calculate interest based on loan type and amount.
     */
    protected function calculateInterest($loan, $amount)
{
    // Fetch the current active period
    $currentPeriod = DB::table('sacco_period')
        ->where('period_active', 'Y')
        ->value('period_name');

    if (!$currentPeriod) {
        session()->flash('error', 'Active period not found.');
        return 0;
    }

    // Fetch the loan type
    $loanType = DB::table('sacco_loan_types')
        ->where('loan_type_id', $loan->loan_loan_type)
        ->first();

    if (!$loanType) {
        session()->flash('error', 'Loan type not found.');
        return 0;
    }

    $interest = 0;

    if (strtolower(trim($loanType->loan_type_interest_type)) === 'reducing balance') {
        // Check if a payment with interest > 0 exists for this loan in the current period
        $existingPaymentWithInterest = DB::table('sacco_loan_payments')
            ->where('loan_payments_loan_id', $loan->loan_id)
            ->where('loan_payments_period', $currentPeriod)
            ->where('loan_payments_interest', '>', 0)
            ->exists();

        if (!$existingPaymentWithInterest) {
            // Calculate reducing balance interest if no prior payment with interest exists
            $interest = ($loan->loan_amount - $loan->loan_loan_paid) * ($loanType->loan_type_interest / 100) / 12;
        }
    } elseif (strtolower(trim($loanType->loan_type_interest_type)) === 'fixed interest') {
        // Always calculate interest for fixed interest loans
        $totalInterest = $loan->loan_amount * ($loanType->loan_type_interest / 100);
        $interest = $totalInterest / $loan->loan_payment_period;
    } else {
        // Unsupported interest type
        session()->flash('error', 'Unsupported interest type.');
        return 0;
    }

    return $interest;
}



    /**
     * Update loan balance in the database.
     */
    protected function updateLoanBalance($loan, $action, $principalAmount, $interest)
    {
        $loanPaid = $action === 'reduce'
            ? $loan->loan_loan_paid + $principalAmount // Reducing the loan by paying principal
            : $loan->loan_loan_paid - $principalAmount; // Increasing the loan by adding principal
    
        DB::table('sacco_loans')
            ->where('loan_id', $loan->loan_id)
            ->update(['loan_loan_paid' => $loanPaid]);
    }
    

    /**
     * Save loan payment record.
     */
    protected function saveLoanPayment($loan, $data, $interest)
{
    // Fetch the current active period name from the period table
    $currentPeriod = DB::table('sacco_period')
        ->where('period_active', 'Y') // Active period
        ->value('period_name'); // Fetch the 'period_name' column

    if (!$currentPeriod) {
        session()->flash('error', 'Current active period not found. Loan payment cannot be saved.');
        return false; // Stop execution
    }

    // Determine the action description and adjust the amount for increase or reduction
    $actionDescription = $data['action'] === 'reduce' ? 'Loan Reduction' : 'Loan Increase';
    
    // Calculate the principal portion of the payment
    $principalAmount = $data['amount_paid'] - $interest;
    
    // Adjust the principal amount for "increase" action (make it negative)
    $adjustedAmount = $data['action'] === 'reduce' ? $principalAmount : -1 * $principalAmount;

    // Insert loan payment record
    DB::table('sacco_loan_payments')->insert([
        'loan_payments_loan_id' => $loan->loan_id,
        'loan_payments_amount' => $adjustedAmount, // Amount after interest adjustment
        'loan_payments_description' => "{$data['description']} ({$actionDescription})",
        'loan_payments_docno' => $data['document_no'],
        'loan_payments_paid_in_by' => auth()->user()->id,
        'loan_payments_period' => $currentPeriod, // Use the current active period
        'loan_payments_paid_on' => $data['transaction_date'],
        'loan_payments_interest' => $interest,
        'loan_payments_by' => auth()->user()->id,
        'loan_payments_ip' => request()->ip(),
    ]);
}
    
    protected function updateLedgerEntries($loan, $data, $interest)
{
    $currentPeriod = DB::table('sacco_period')
        ->where('period_active', 'Y')
        ->value('period_name');

    if (!$currentPeriod) {
        session()->flash('error', 'Current active period not found. Ledger entries cannot be updated.');
        return false;
    }

    $loanType = DB::table('sacco_loan_types')
        ->where('loan_type_id', $loan->loan_loan_type)
        ->first();

    if (!$loanType) {
        session()->flash('error', 'Loan type not found. Ledger entries cannot be updated.');
        return false;
    }

    $paymentAccount = $data['payment_account'];
    $loanAccount = $loanType->loan_type_acount;
    $interestAccount = $loanType->loan_type_int_account;
    $principalAmount = $data['amount_paid'] - $interest;
    $transactionDate = $data['transaction_date'];

    $description = $data['description'] . " (Member: {$loan->member_name}, Loan: {$loan->loan_id})";

    if ($data['action'] === 'reduce') {
        // **Loan Repayment Logic**
        // Debit: Payment account (total payment)
        $this->recordTransaction($paymentAccount, 'DEBIT', $data['amount_paid'], $currentPeriod, $transactionDate, $description, $data['document_no']);

        // Credit: Loan account (principal repayment)
        $this->recordTransaction($loanAccount, 'CREDIT', $principalAmount, $currentPeriod, $transactionDate, $description, $data['document_no']);

        // Credit: Interest account (if applicable)
        if ($interest > 0) {
            $this->recordTransaction($interestAccount, 'CREDIT', $interest, $currentPeriod, $transactionDate, $description, $data['document_no']);
        }
    } elseif ($data['action'] === 'increase') {
        // **Loan Disbursement Logic**
        // Credit: Payment account (loan disbursement amount)
        $this->recordTransaction($paymentAccount, 'CREDIT', $data['amount_paid'], $currentPeriod, $transactionDate, $description, $data['document_no']);

        // Debit: Loan account (principal disbursement)
        $this->recordTransaction($loanAccount, 'DEBIT', $principalAmount, $currentPeriod, $transactionDate, $description, $data['document_no']);
    }
}
    
    protected function recordTransaction($accountId, $type, $amount, $currentPeriod, $transactionDate, $description,$document_no)
    {

        

        DB::table('sacco_accounts_trans')->insert([
            'accounts_trans_sub_account' => $accountId,
            'accounts_trans_period' => $currentPeriod,
            'accounts_trans_debit' => $type === 'DEBIT' ? $amount : 0,
            'accounts_trans_credit' => $type === 'CREDIT' ? $amount : 0,
            'accounts_trans_doc_no' => $document_no,
            'accounts_trans_decription' => $description,
            'accounts_trans_source' => 'Loan Payment',
            'accounts_trans_dat_date' => $transactionDate,
            'accounts_trans_transdate' => now(),
            'accounts_trans_user_id' => auth()->id(),
            'accounts_trans_ip' => request()->ip(),
            'accounts_trans_member_id' => null, // Optional: Add member ID if necessary
            'accounts_trans_app_name' => 'SaccoApp',
            'accounts_trans_cash_in_already' => 'N',
            'accounts_trans_reconsiled' => 'N',
            'accounts_trans_payment_type' => $type,
        ]);
    
        $columnToUpdate = ($type === 'DEBIT') ? 'sub_account_debit' : 'sub_account_credit';
    
        DB::table('sacco_sub_account')
            ->where('sub_account_id', $accountId)
            ->increment($columnToUpdate, $amount);
    
        $mainAccountId = DB::table('sacco_sub_account')
            ->where('sub_account_id', $accountId)
            ->value('sub_account_main_account');
    
        $mainColumnToUpdate = ($type === 'DEBIT') ? 'main_account_debit' : 'main_account_credit';
    
        DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccountId)
            ->increment($mainColumnToUpdate, $amount);
    }
}
