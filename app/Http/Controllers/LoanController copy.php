<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    private $currentPeriod;

    public function __construct()
    {
        // Fetch the current active period
        $this->currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();
    }

    // Display the list of loan batches
    public function loans_batches(Request $request)
    {
        $period = $request->input('period', $this->currentPeriod->period_name);
        $batches = DB::table('sacco_loan_batch')
            ->join('sacco_sub_account', 'sacco_loan_batch.batch_credit_account', '=', 'sacco_sub_account.sub_account_id')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('batch_period', $period)
            ->where('batch_deleted', 'N')
            ->orderBy('sacco_main_account.main_account_code')
            ->orderBy('sacco_sub_account.sub_account_code')
            ->select('sacco_loan_batch.*', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code')
            ->get();

        return view('loans.batch_list', [
            'batches' => $batches,
            'period' => $period,
            'currentPeriod' => $this->currentPeriod
        ]);
    }

    // Show the form for creating a new batch
    public function loans_batch()
    {
        $accounts = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_main_account.main_account_code', 'like', 'A%')
            ->where(function ($query) {
                $query->where('sacco_sub_account.sub_account_name', 'LIKE', '%Bank%')
                    ->orWhere('sacco_sub_account.sub_account_name', 'LIKE', '%MPESA%');
            })
            ->select('sacco_sub_account.sub_account_id', DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code, ' - ', sacco_sub_account.sub_account_name) AS account_name"))
            ->get();

        return view('loans.batch_form', ['accounts' => $accounts, 'currentPeriod' => $this->currentPeriod]);
    }

    // Store a newly created batch
    public function loans_batch_store(Request $request)
    {
        $data = $request->validate([
            'batch_reference' => 'required|string|max:100',
            'batch_amount' => 'required|numeric|min:1.01',
            'batch_total_trans' => 'required|integer|min:1',
            'batch_credit_account' => 'required|integer',
        ]);

        $data['batch_period'] = $this->currentPeriod->period_name;
        $data['batch_by'] = auth()->id();
        $data['batch_ip'] = $request->ip();
        $data['batch_approved'] = 'N';
        $data['batch_updated'] = 'N';
        $data['batch_deleted'] = 'N';

        // Remove commas from batch_amount if entered
        $data['batch_amount'] = str_replace(',', '', $data['batch_amount']);

        DB::table('sacco_loan_batch')->insert($data);

        return redirect()->route('loans.batches')->with('success', 'Loan batch added successfully.');
    }

    // Show the form for editing the specified batch
    public function loans_batch_edit($batch_id)
    {
        $batch = DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->where('batch_deleted', 'N')->first();

        if (!$batch) {
            return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
        }

        $accounts = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_main_account.main_account_code', 'like', 'A%')
            ->where(function ($query) {
                $query->where('sacco_sub_account.sub_account_name', 'LIKE', '%Bank%')
                    ->orWhere('sacco_sub_account.sub_account_name', 'LIKE', '%MPESA%');
            })
            ->select('sacco_sub_account.sub_account_id', DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code, ' - ', sacco_sub_account.sub_account_name) AS account_name"))
            ->get();

        return view('loans.batch_form', ['batch' => $batch, 'accounts' => $accounts, 'currentPeriod' => $this->currentPeriod]);
    }

    // Update the specified batch
    public function loans_batch_update(Request $request, $batch_id)
    {
        $batch = DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->first();

        if (!$batch || $batch->batch_deleted == 'Y') {
            return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
        }

        if ($batch->batch_updated == 'Y') {
            return redirect()->route('loans.batches')->with('error', 'This batch has been finalized and cannot be edited.');
        }

        $data = $request->validate([
            'batch_reference' => 'required|string|max:100',
            'batch_amount' => 'required|numeric|min:1.01',
            'batch_total_trans' => 'required|integer|min:1',
            'batch_credit_account' => 'required|integer',
        ]);

        // Check the latest approval status from the database
        $isApproved = $request->has('batch_approved') || $batch->batch_approved == 'Y';
        $data['batch_approved'] = $isApproved ? 'Y' : 'N';

        // Finalize only if approved
        if ($isApproved) {
            $data['batch_updated'] = $request->has('batch_updated') ? 'Y' : 'N';
        } else {
            $data['batch_updated'] = 'N';
        }

        $data['batch_by'] = auth()->id();
        $data['batch_ip'] = $request->ip();
        $data['batch_period'] = $this->currentPeriod->period_name;

        // Remove commas from batch_amount if entered
        $data['batch_amount'] = str_replace(',', '', $data['batch_amount']);

        $updated = DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->update($data);

        if ($updated) {
            return redirect()->route('loans.batches')->with('success', 'Loan batch updated successfully.');
        } else {
            return redirect()->route('loans.batches')->with('error', 'Failed to update loan batch.');
        }
    }

    // Delete the specified batch
    public function loans_batch_delete($batch_id)
    {
        // Fetch the batch from the database
        $batch = DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->first();

        // Check if the batch can be deleted
        if (!$batch || $batch->batch_updated == 'Y' || $batch->batch_approved == 'Y' || $batch->batch_deleted == 'Y') {
            return redirect()->route('loans.batches')->with('error', 'This batch cannot be deleted.');
        }

        // Mark the batch as deleted
        $deleted = DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->update([
            'batch_deleted' => 'Y',
            'batch_deleted_by' => auth()->id(),
            'batch_deleted_on' => now(),
            'batch_deleted_ip' => request()->ip()
        ]);

        if ($deleted) {
            return redirect()->route('loans.batches')->with('success', 'Loan batch deleted successfully.');
        } else {
            return redirect()->route('loans.batches')->with('error', 'Failed to delete loan batch.');
        }
    }

    // Display the transactions for a batch
    public function loans_batch_transactions($batch_id)
    {
        $batch = DB::table('sacco_loan_batch')
            ->where('batch_id', $batch_id)
            ->where('batch_deleted', 'N')
            ->first();

        if (!$batch) {
            return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
        }

        $transactions = DB::table('sacco_loan_batch_trans')
            ->join('sacco_loan_types', 'sacco_loan_batch_trans.batch_trans_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_loan_category', 'sacco_loan_batch_trans.batch_trans_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->join('sacco_members', 'sacco_loan_batch_trans.batch_trans_member_id', '=', 'sacco_members.member_id')
            ->where('batch_trans_batch_id', $batch_id)
            ->where('batch_trans_deleted', 'N')
            ->select('sacco_loan_batch_trans.*', 'sacco_loan_types.loan_type_name', 'sacco_loan_category.loan_category_name', 'sacco_members.member_name')
            ->get();

        return view('loans.transactions_list', [
            'batch' => $batch,
            'transactions' => $transactions,
            'currentPeriod' => $this->currentPeriod,
        ]);
    }

    // Show the form for adding a new transaction to a batch
   
    public function loans_batch_transactions_add_view($batch_id)
        {
            $batch = DB::table('sacco_loan_batch')
                ->where('batch_id', $batch_id)
                ->where('batch_deleted', 'N')
                ->first();

            if (!$batch) {
                return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
            }

            $loanTypes = DB::table('sacco_loan_types')
                ->where('loan_type_deleted', 'N')
                ->orderBy('loan_type_name')
                ->get();

            $loanCategories = DB::table('sacco_loan_category')
                ->where('loan_category_deleted', 'N')
                ->get();

            $members = DB::table('sacco_members')
                ->where('member_deleted', 'N')
                ->where('member_active', 'Y')
                ->get();

            $outstandingLoans = DB::table('sacco_loans')
                ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
                ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', DB::raw('(loan_amount - loan_loan_paid) as loan_balance'))
                ->where('loan_stoped', 'N')
                ->where(DB::raw('(loan_amount - loan_loan_paid)'), '>', 0)
                ->get();

            return view('loans.transaction_add', [
                'batch' => $batch,
                'loanTypes' => $loanTypes,
                'loanCategories' => $loanCategories,
                'members' => $members,
                'outstandingLoans' => $outstandingLoans, // Pass the variable to the view
                'currentPeriod' => $this->currentPeriod,
            ]);
        }


    // Add a new transaction to a batch
   
    // Delete a transaction
    public function loans_batch_transactions_delete($transaction_id)
    {
        $transaction = DB::table('sacco_loan_batch_trans')->where('batch_trans_id', $transaction_id)->first();

        if (!$transaction) {
            return redirect()->route('loans.batches')->with('error', 'Transaction not found.');
        }

        DB::table('sacco_loan_batch_trans')->where('batch_trans_id', $transaction_id)->update([
            'batch_trans_deleted' => 'Y',
            'batch_trans_deleted_by' => auth()->id(),
            'batch_trans_deleted_on' => now(),
            'batch_trans_deleted_ip' => request()->ip()
        ]);

        return redirect()->route('loans.batch.transactions', $transaction->batch_trans_batch_id)->with('success', 'Transaction deleted successfully.');
    }

    public function searchMembers(Request $request)
    {
        $query = $request->input('query');
        $members = DB::table('sacco_members')
            ->select('member_id', 'member_name', 'member_sacco_id')
            ->where(function($q) use ($query) {
                $q->where('member_name', 'LIKE', '%' . $query . '%')
                    ->orWhere('member_phone_no', 'LIKE', '%' . $query . '%')
                    ->orWhere('member_sacco_id', 'LIKE', '%' . $query . '%');
            })
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->orderBy('member_name')
            ->limit(5)
            ->get();
    
        $results = [];
        foreach ($members as $member) {
            $results[] = [
                'label' => $member->member_name . ' - (' . $member->member_sacco_id . ')',
                'value' => $member->member_name . ' - (' . $member->member_sacco_id . ')',
                'member_id' => $member->member_id, // Make sure this is included
            ];
        }
    
        return response()->json($results);
    }
    

    public function searchMemberLoans(Request $request)
        {
            $memberId = $request->input('member_id');

            $outstandingLoans = DB::table('sacco_loans')
                ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
                ->select('sacco_loans.loan_id', 'sacco_loan_types.loan_type_name', DB::raw('(loan_amount - loan_loan_paid) as loan_balance'))
                ->where('loan_member', $memberId)
                ->where('loan_stoped', 'N')
                ->where(DB::raw('(loan_amount - loan_loan_paid)'), '>', 0)
                ->get();

            return response()->json($outstandingLoans);
        }

        public function loans_batch_transactions_add(Request $request, $batch_id)
        {
            $batch = DB::table('sacco_loan_batch')
                ->where('batch_id', $batch_id)
                ->where('batch_deleted', 'N')
                ->first();
        
            if (!$batch) {
                return redirect()->route('loans.batches')->with('error', ['Batch not found or has been deleted.']);
            }
        
            // Initial validation of fields based on form
            $validated = $request->validate([
                'batch_trans_loan_type' => 'required|integer',
                'batch_trans_loan_category' => 'required|integer',
                'batch_trans_loan_amount' => 'required|numeric|min:1.01',
                'batch_trans_member_id' => 'required|integer',
                'batch_trans_loan_duration' => 'required|integer|min:1',
                'batch_trans_doc_no' => 'required|string|max:100',
                'batch_trans_description' => 'nullable|string|max:100',
                'batch_trans_commission_amount' => 'nullable|numeric',
                'batch_trans_loan_to_top_up' => 'nullable|integer',
                'guarantors' => 'nullable|array',
                'guarantors.*.member' => 'nullable|string',
                'guarantors.*.amount' => 'nullable|numeric|min:0',
                'guarantors.*.free_shares' => 'nullable|numeric|min:0'
            ]);
        
            $errors = $this->validateLoanTransactionInput($batch_id, $batch, $validated);
        
            if (!empty($errors)) {
                return redirect()->back()->withInput()->with('error', $errors);
            }
        
            return redirect()->back()->withInput()->with('success', 'Initial validation passed. Proceed to calculations and saving.');
        }
        
        private function validateLoanTransactionInput($batch_id, $batch, $validated)
        {
            $errors = [];
        
            $member = DB::table('sacco_members')
                ->where('member_id', $validated['batch_trans_member_id'])
                ->where('member_active', 'Y')
                ->where('member_deleted', '<>', 'Y')
                ->first();
        
            if (!$member) {
                $errors[] = 'Invalid member selected.';
            }
        
            $loanType = DB::table('sacco_loan_types')
                ->where('loan_type_id', $validated['batch_trans_loan_type'])
                ->first();
        
            if (!$loanType) {
                $errors[] = 'Invalid loan type selected.';
            }
        
            $loanCategory = DB::table('sacco_loan_category')
                ->where('loan_category_id', $validated['batch_trans_loan_category'])
                ->first();
        
            if (!$loanCategory) {
                $errors[] = 'Invalid loan category selected.';
            }
        
            // Skip further validations if core entities are missing
            if (!$member || !$loanType || !$loanCategory) {
                return $errors;
            }
        
            // 1. Check for duplicate loan in batch
            $existingLoan = DB::table('sacco_loan_batch_trans')
                ->where('batch_trans_batch_id', $batch_id)
                ->where('batch_trans_loan_type', $validated['batch_trans_loan_type'])
                ->where('batch_trans_member_id', $validated['batch_trans_member_id'])
                ->where('batch_trans_deleted', '<>', 'Y')
                ->exists();
        
            if ($existingLoan) {
                $errors[] = 'This member already has a similar loan in this batch.';
            }
        
            // 2. Check batch limits
            $currentTransactions = DB::table('sacco_loan_batch_trans')
                ->where('batch_trans_batch_id', $batch_id)
                ->where('batch_trans_deleted', 'N')
                ->count();
        
            $currentAmount = DB::table('sacco_loan_batch_trans')
                ->where('batch_trans_batch_id', $batch_id)
                ->where('batch_trans_deleted', 'N')
                ->sum('batch_trans_loan_amount');
        
            if ($currentTransactions >= $batch->batch_total_trans) {
                $errors[] = 'Transaction limit exceeded for this batch.';
            }
        
            if (($currentAmount + $validated['batch_trans_loan_amount']) > $batch->batch_amount) {
                $errors[] = 'Total transaction amount exceeded for this batch.';
            }
        
            // 3. Loan duration
            if ($validated['batch_trans_loan_duration'] > $loanType->loan_type_duration) {
                $errors[] = 'Loan duration exceeds the allowed maximum of ' . $loanType->loan_type_duration . ' months.';
            }
        
            // 4. Max loan amount
            if ($validated['batch_trans_loan_amount'] > $loanType->loan_type_max_amount) {
                $errors[] = 'Loan amount exceeds the maximum allowed for this loan type (' . number_format($loanType->loan_type_max_amount, 2) . ').';
            }
        
            // 5. Qualification period
            $minJoinDate = now()->subMonths($loanType->loan_type_qualification_period);
            if (strtotime($member->member_date_joined) > strtotime($minJoinDate)) {
                $errors[] = 'Member must have been in the SACCO for at least ' . $loanType->loan_type_qualification_period . ' months to qualify for this loan.';
            }
        
            // 6. Share factor check
            $totalShares = $member->member_total_share + $member->member_total_share_capital;
            $maxLoanByShares = $totalShares * $loanType->loan_type_share_factor;
            if ($validated['batch_trans_loan_amount'] > $maxLoanByShares) {
                $errors[] = 'Loan amount exceeds allowable limit based on share factor. Max allowed is ' . number_format($maxLoanByShares, 2);
            }
        
            return $errors;
        }
             

}
