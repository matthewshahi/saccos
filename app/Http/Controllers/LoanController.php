<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoanController extends Controller
{
    private $currentPeriod;

    public function __construct()
    {
        $this->middleware('auth');
        // Fetch the current active period
        $this->currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();
    }

    // Display the list of loan batches
    public function loans_batches(Request $request)
{
    // Get the period from the request or default to the current active period
    $period = $request->input('period', $this->currentPeriod->period_name);

    // Query the loan batches with ordering by the latest additions at the top
    $batches = DB::table('sacco_loan_batch')
        ->join('sacco_sub_account', 'sacco_loan_batch.batch_credit_account', '=', 'sacco_sub_account.sub_account_id')
        ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
        ->where('batch_period', $period)
        ->where('batch_deleted', 'N')
        ->orderByDesc('sacco_loan_batch.batch_id')  // Order by batch_id to get latest additions on top
        ->orderBy('sacco_main_account.main_account_code')
        ->orderBy('sacco_sub_account.sub_account_code')
        ->select(
            'sacco_loan_batch.*',
            'sacco_sub_account.sub_account_name',
            'sacco_sub_account.sub_account_code',
            'sacco_main_account.main_account_code'
        )
        ->get();

    // Return the view with batches, period, and the current period for reference
    return view('loans.batch_list', [
        'batches' => $batches,
        'period' => $period,
        'currentPeriod' => $this->currentPeriod
    ]);
}
    // public function loans_batches(Request $request)
    // {
    //     $period = $request->input('period', $this->currentPeriod->period_name);
    //     $batches = DB::table('sacco_loan_batch')
    //         ->join('sacco_sub_account', 'sacco_loan_batch.batch_credit_account', '=', 'sacco_sub_account.sub_account_id')
    //         ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
    //         ->where('batch_period', $period)
    //         ->where('batch_deleted', 'N')
    //         ->orderBy('sacco_main_account.main_account_code')
    //         ->orderBy('sacco_sub_account.sub_account_code')
    //         ->select('sacco_loan_batch.*', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code')
    //         ->get();
        
    //     return view('loans.batch_list', [
    //         'batches' => $batches,
    //         'period' => $period,
    //         'currentPeriod' => $this->currentPeriod
    //     ]);
    // }

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

    public function loans_batch_edit($batch_id)
{
    $batch = DB::table('sacco_loan_batch')
        ->where('batch_id', $batch_id)
        ->where('batch_deleted', 'N')
        ->first();

    if (!$batch) {
        return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
    }

    // Retrieve total amount and count of transactions for this batch
    $transactionSummary = DB::table('sacco_loan_batch_trans')
        ->where('batch_trans_batch_id', $batch_id)
        ->where('batch_trans_deleted', 'N')
        ->select(
            DB::raw('SUM(batch_trans_loan_amount) as total_transaction_amount'),
            DB::raw('COUNT(*) as transaction_count')
        )
        ->first();

    // Check if approval conditions are met
    $canApprove = (
        $transactionSummary->transaction_count == $batch->batch_total_trans &&
        $transactionSummary->total_transaction_amount == $batch->batch_amount
    );

    // Retrieve accounts and current period as in the existing logic
    $accounts = DB::table('sacco_sub_account')
        ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
        ->where('sacco_main_account.main_account_code', 'like', 'A%')
        ->where(function ($query) {
            $query->where('sacco_sub_account.sub_account_name', 'LIKE', '%Bank%')
                ->orWhere('sacco_sub_account.sub_account_name', 'LIKE', '%MPESA%');
        })
        ->select('sacco_sub_account.sub_account_id', DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code, ' - ', sacco_sub_account.sub_account_name) AS account_name"))
        ->get();

    return view('loans.batch_form', [
        'batch' => $batch,
        'accounts' => $accounts,
        'currentPeriod' => $this->currentPeriod,
        'canApprove' => $canApprove // Pass the approval status to the view
    ]);
}


    // Show the form for editing the specified batch
    // public function loans_batch_edit($batch_id)
    // {
    //     $batch = DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->where('batch_deleted', 'N')->first();

    //     if (!$batch) {
    //         return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
    //     }

    //     $accounts = DB::table('sacco_sub_account')
    //         ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
    //         ->where('sacco_main_account.main_account_code', 'like', 'A%')
    //         ->where(function ($query) {
    //             $query->where('sacco_sub_account.sub_account_name', 'LIKE', '%Bank%')
    //                 ->orWhere('sacco_sub_account.sub_account_name', 'LIKE', '%MPESA%');
    //         })
    //         ->select('sacco_sub_account.sub_account_id', DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code, ' - ', sacco_sub_account.sub_account_name) AS account_name"))
    //         ->get();

    //     return view('loans.batch_form', ['batch' => $batch, 'accounts' => $accounts, 'currentPeriod' => $this->currentPeriod]);
    // }

    // Update the specified batch
   
    public function loans_batch_update(Request $request, $batch_id)
{
    // Retrieve the batch
    $batch = DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->first();

    if (!$batch || $batch->batch_deleted == 'Y') {
        return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
    }

    if ($batch->batch_updated == 'Y') {
        return redirect()->route('loans.batches')->with('error', 'This batch has been finalized and cannot be edited.');
    }

    // Normalize `batch_updated` to 'Y' for consistency if it's set to 'on'
    $batchUpdated = $request->input('batch_updated') === 'on' ? 'Y' : $request->input('batch_updated');
    $batchApproved = $request->input('batch_approved') === 'on' ? 'Y' : $request->input('batch_approved');

    // Validate incoming data
    $data = $request->validate([
        'batch_reference' => 'required|string|max:100',
        'batch_amount' => 'required|numeric|min:1.01',
        'batch_total_trans' => 'required|integer|min:1',
        'batch_credit_account' => 'required|integer',
    ]);

    // Remove batch_updated from data to ensure it does not get updated here
    unset($data['batch_updated']);

    // Add batch_approved to the data if it was submitted in the request
    $data['batch_approved'] = $batchApproved;
    
    // Set additional fields
    $data['batch_by'] = auth()->id();
    $data['batch_ip'] = $request->ip();
    $data['batch_period'] = $this->currentPeriod->period_name;
    $data['batch_amount'] = str_replace(',', '', $data['batch_amount']); // Clean batch_amount

    try {
        // Attempt to update the batch
        DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->update($data);

        // Proceed to finalize if batch_approved in DB is Y and normalized batch_updated is 'Y'
        if ($data['batch_approved'] === 'Y' && $batchUpdated === 'Y') {
            return $this->finalizeBatch($batch_id);
        }

        // Redirect with success message if no finalization is needed
        return redirect()->route('loans.batches')->with('success', 'Loan batch updated successfully.');
    } catch (\Exception $e) {
        // Catch and display the SQL error if any
        return redirect()->route('loans.batches')->with('error', 'SQL Error: ' . $e->getMessage());
    }
}
  
    // public function loans_batch_update(Request $request, $batch_id)
    // {
    //     // Retrieve the batch
    //     $batch = DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->first();
    
    //     if (!$batch || $batch->batch_deleted == 'Y') {
    //         return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
    //     }
    
    //     if ($batch->batch_updated == 'Y') {
    //         return redirect()->route('loans.batches')->with('error', 'This batch has been finalized and cannot be edited.');
    //     }
    
    //     // Normalize `batch_updated` to 'Y' for consistency if it's set to 'on'
    //     $batchUpdated = $request->input('batch_updated') === 'on' ? 'Y' : $request->input('batch_updated');
    
    //     // Validate incoming data
    //     $data = $request->validate([
    //         'batch_reference' => 'required|string|max:100',
    //         'batch_amount' => 'required|numeric|min:1.01',
    //         'batch_total_trans' => 'required|integer|min:1',
    //         'batch_credit_account' => 'required|integer',
    //     ]);
    
    //     // Remove batch_updated from data to ensure it does not get updated here
    //     unset($data['batch_updated']);
    
    //     // Set additional fields
    //     $data['batch_by'] = auth()->id();
    //     $data['batch_ip'] = $request->ip();
    //     $data['batch_period'] = $this->currentPeriod->period_name;
    //     $data['batch_amount'] = str_replace(',', '', $data['batch_amount']); // Clean batch_amount
    
    //     try {
    //         // Attempt to update the batch
    //         DB::table('sacco_loan_batch')->where('batch_id', $batch_id)->update($data);
            
    //         // Proceed to finalize if batch_approved in DB is Y and normalized batch_updated is 'Y'
    //         if ($batch->batch_approved === 'Y' && $batchUpdated === 'Y') {
              
    //             return $this->finalizeBatch($batch_id);
    //         }
    
    //         // Redirect with success message if no finalization is needed
    //         return redirect()->route('loans.batches')->with('success', 'Loan batch updated successfully.');
    //     } catch (\Exception $e) {
    //         // Catch and display the SQL error if any
    //         return redirect()->route('loans.batches')->with('error', 'SQL Error: ' . $e->getMessage());
    //     }
    // }

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
            ->select('sacco_loan_batch_trans.*', 'sacco_loan_types.loan_type_name', 'sacco_loan_category.loan_category_name', 'sacco_members.member_name','sacco_members.member_id')
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

    // Initialize $loansToTopUp as an empty collection
    $loansToTopUp = collect();

    // Extract member_sacco_id from the loan_member_id input
    $loanMemberStr = request()->input('loan_member_id'); // Assuming this is passed in the request
    $loanMemberId = null;

    if ($loanMemberStr) {
        // Extract member_sacco_id (e.g., ISA228) from loanMemberStr
        preg_match('/\(([^)]+)\)$/', $loanMemberStr, $matches);
        $loanMemberSaccoId = $matches[1] ?? null;

        if ($loanMemberSaccoId) {
            // Fetch the loan member ID using the member_sacco_id
            $loanMember = DB::table('sacco_members')->where('member_sacco_id', $loanMemberSaccoId)->first();
            $loanMemberId = $loanMember ? $loanMember->member_id : null;
        }
    }

    // Check if a valid member ID was found
    if ($loanMemberId) {
        // Fetch outstanding loans for the selected member eligible for top-up
        $loansToTopUp = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select('sacco_loans.loan_id', 'sacco_loan_types.loan_type_name', DB::raw('(loan_amount - loan_loan_paid) as loan_balance'))
            ->where('loan_member', $loanMemberId) // Filter by member ID
            ->where('loan_stoped', 'N')
            ->where(DB::raw('(loan_amount - loan_loan_paid)'), '>', 0)
            ->get();
    }

    return view('loans.transaction_add', [
        'batch' => $batch,
        'loanTypes' => $loanTypes,
        'loanCategories' => $loanCategories,
        'members' => $members,
        'loansToTopUp' => $loansToTopUp, // Pass loansToTopUp to the view
        'currentPeriod' => $this->currentPeriod,
    ]);
}


    // public function loans_batch_transactions_add(Request $request, $batch_id)
    // {
    //     $batch = DB::table('sacco_loan_batch')
    //         ->where('batch_id', $batch_id)
    //         ->where('batch_deleted', 'N')
    //         ->first();
    
    //     if (!$batch) {
    //         return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
    //     }
    
    //     // Extract the sacco ID from the member string
    //     $memberStr = $request->input('batch_trans_member_id');
    //     preg_match('/\(([^)]+)\)$/', $memberStr, $matches);
    //     $saccoId = $matches[1] ?? null;
    
    //     if (!$saccoId) {
    //         return redirect()->back()->withErrors(['batch_trans_member_id' => 'Invalid member selection.']);
    //     }
    
    //     // Fetch member details using sacco ID
    //     $member = DB::table('sacco_members')
    //         ->where('member_sacco_id', $saccoId)
    //         ->where('member_active', 'Y')
    //         ->where('member_total_share', '>', 0)
    //         ->first();
    
    //     if (!$member) {
    //         return redirect()->back()->withErrors(['batch_trans_member_id' => 'Member not found, inactive, or has no shares.']);
    //     }
    
    //     // Validate request data
    //     $data = $request->validate([
    //         'batch_trans_loan_type' => 'required|integer',
    //         'batch_trans_loan_category' => 'required|integer',
    //         'batch_trans_loan_amount' => 'required|numeric|min:1.01',
    //         'batch_trans_loan_duration' => 'required|integer|min:1',
    //         'batch_trans_doc_no' => 'required|string|max:100',
    //         'batch_trans_description' => 'nullable|string|max:100',
    //         'batch_trans_commission' => 'nullable|numeric|min:0', // Correct field name
    //         'batch_trans_loan_to_top_up' => 'nullable|integer',
    //     ]);
    
    //     if ($request->filled('batch_trans_commission') && $request->input('batch_trans_commission') <= 0) {
    //         return redirect()->back()->withErrors(['batch_trans_commission' => 'Commission amount must be greater than zero if provided.']);
    //     }
    
    //     // Set member_id from the fetched member
    //     $data['batch_trans_member_id'] = $member->member_id;
    //     $data['batch_trans_batch_id'] = $batch_id;
    //     $data['batch_trans_by'] = auth()->id();
    //     $data['batch_trans_ip'] = $request->ip();
    
    //     DB::table('sacco_loan_batch_trans')->insert($data);
    
    //     return redirect()->route('loans.batch.transactions', $batch_id)->with('success', 'Transaction added successfully.');
    // }
    


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
                ->where('member_total_share', '>', 0)
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

  
    public function LoanGetFreeShares(Request $request)
        {
            $memberId = $request->input('member_id');
            $loanMemberStr = $request->input('loan_member_id'); // Assuming this is passed in the request
        
            // Initialize loanMemberId as null
            $loanMemberId = null;
        
            if ($loanMemberStr) {
                // Extract member_sacco_id (e.g., ISA228) from loanMemberStr
                preg_match('/\(([^)]+)\)$/', $loanMemberStr, $matches);
                $loanMemberSaccoId = $matches[1] ?? null;
        
                // Fetch the loan member ID using the member_sacco_id
                $loanMember = DB::table('sacco_members')->where('member_sacco_id', $loanMemberSaccoId)->first();
                $loanMemberId = $loanMember ? $loanMember->member_id : null;
            }
        
            // Fetch member details
            $member = DB::table('sacco_members')->where('member_id', $memberId)->first();
        
            if (!$member) {
                return response()->json(['error' => 'Member not found'], 404);
            }
        
            // Fetch max_guarantor_factor
            $maxGuarantorFactor = DB::table('sacco_defaults')
                ->where('default_name', 'max_guarantor_factor')
                ->value('default_value');
        
            // Default maxGuarantorFactor to 1 if not set
            if (!$maxGuarantorFactor) {
                $maxGuarantorFactor = 1;
            }
        
            // Calculate free shares
            $totalShares = $member->member_total_share;
            $tiedShares = $member->member_tied_shares;
            $tiedSharesSelf = $member->member_tied_shares_self;
        
            // Determine if the member is self-guaranteeing
            if ($memberId == $loanMemberId && $loanMemberId !== null) {
                $freeShares = $totalShares - $tiedSharesSelf;
            } else {
                // For guaranteeing others, multiply by max_guarantor_factor
                $freeShares = ($totalShares * $maxGuarantorFactor) - $tiedShares;
            }
        
            // Ensure freeShares is not negative
            $freeShares = max($freeShares, 0);
        
            return response()->json(['free_shares' => $freeShares]);
        }
    
        public function loans_batch_transactions_edit($batch_id, $transaction_id)
        {
            $batch = DB::table('sacco_loan_batch')
                ->where('batch_id', $batch_id)
                ->where('batch_deleted', 'N')
                ->first();
        
            $transaction = DB::table('sacco_loan_batch_trans')
                ->join('sacco_members', 'sacco_loan_batch_trans.batch_trans_member_id', '=', 'sacco_members.member_id')
                ->where('sacco_loan_batch_trans.batch_trans_id', $transaction_id)
                ->select('sacco_loan_batch_trans.*', 'sacco_members.member_name', 'sacco_members.member_sacco_id')
                ->first();
        
            $loanTypes = DB::table('sacco_loan_types')->get();
            $loanCategories = DB::table('sacco_loan_category')->get();
        
            $guarantors = DB::table('sacco_loan_batch_guarantors')
                ->join('sacco_members', 'sacco_loan_batch_guarantors.guarantors_guarantor_id', '=', 'sacco_members.member_id')
                ->where('guarantors_loan_batch_trans_id', $transaction_id)
                ->where('guarantors_deleted', 'N')
                ->select('sacco_loan_batch_guarantors.*', 'sacco_members.member_name', 'sacco_members.member_sacco_id')
                ->get();
        
            $loansToTopUp = collect(); // Initialize as an empty collection
        
            // Fetch outstanding loans for the selected member eligible for top-up
            if ($transaction) {
                $loansToTopUp = DB::table('sacco_loans')
                    ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
                    ->select('sacco_loans.loan_id', 'sacco_loan_types.loan_type_name', DB::raw('(loan_amount - loan_loan_paid) as loan_balance'))
                    ->where('loan_member', $transaction->batch_trans_member_id)
                    ->where('loan_stoped', 'N')
                    ->where(DB::raw('(loan_amount - loan_loan_paid)'), '>', 0)
                    ->get();
            }
        
            if (!$batch || !$transaction) {
                return redirect()->route('loans.batch.transactions', $batch_id)->with('error', 'Transaction not found.');
            }
        
            return view('loans.transaction_edit', compact('batch', 'transaction', 'loanTypes', 'loanCategories', 'guarantors', 'loansToTopUp'));
        }
        





        public function loans_batch_transactions_add(Request $request, $batch_id)
    {
        $batch = DB::table('sacco_loan_batch')
            ->where('batch_id', $batch_id)
            ->where('batch_deleted', 'N')
            ->first();

        if (!$batch) {
            return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
        }

        $member = $this->getMemberFromRequest($request->input('batch_trans_member_id'));
        if (!$member) {
            return redirect()->back()->withErrors(['batch_trans_member_id' => 'Invalid member selection or member has no shares.'])->withInput();
        }

        $data = $request->validate([
            'batch_trans_loan_type' => 'required|integer',
            'batch_trans_loan_category' => 'required|integer',
            'batch_trans_loan_amount' => 'required|numeric|min:1.01',
            'batch_trans_loan_duration' => 'required|integer|min:1',
            'batch_trans_doc_no' => 'required|string|max:100',
            'batch_trans_description' => 'nullable|string|max:100',
            'batch_trans_commission_amount' => 'nullable|numeric|min:0',
            'batch_trans_loan_to_top_up' => 'nullable|integer',
        ]);


       


        $loanType = DB::table('sacco_loan_types')->where('loan_type_id', $data['batch_trans_loan_type'])->first();
        if (!$loanType) {
            return redirect()->back()->withErrors(['batch_trans_loan_type' => 'Invalid loan type selected.'])->withInput();
        }

        $errors = [];
        if (!$this->validateLoanDuration($data['batch_trans_loan_duration'], $loanType->loan_type_duration)) {
            $errors['batch_trans_loan_duration'] = 'Loan duration exceeds the maximum allowed for this loan type.';
        }

        if (!$this->validateLoanAmount($data['batch_trans_loan_amount'], $loanType->loan_type_max_amount)) {
            $errors['batch_trans_loan_amount'] = 'Loan amount exceeds the maximum allowed for this loan type.';
        }

        if (!$this->validateMemberEligibility($member, $loanType->loan_type_qualification_period)) {
            $errors['batch_trans_member_id'] = 'Member does not meet the qualification period for this loan type.';
        }

        // New check for share and loan constraint
        $shareAndLoanValidation = $this->validateShareAndLoanConstraint($member, $loanType, $data['batch_trans_loan_amount']);
        if (!$shareAndLoanValidation['is_valid']) {
            $maxLoanAmount = $shareAndLoanValidation['max_amount'];
            $errors['batch_trans_loan_amount'] = "Insufficient shares or high existing loans. The maximum loan you can take is KES " . number_format($maxLoanAmount, 2) . ".";
        }

        $guarantorsResult = $this->validateAndCalculateGuarantors($member, $data['batch_trans_loan_amount'], $loanType, $request->input('guarantors', []));
        if (is_array($guarantorsResult) && isset($guarantorsResult['errors'])) {
            $errors = array_merge($errors, $guarantorsResult['errors']);
        }
        // Validate loan top-up logic
        $loanToTopUpValidation = $this->validateLoanToTopUp($member->member_id, $data['batch_trans_loan_to_top_up'], $data['batch_trans_loan_amount']);
        if (!$loanToTopUpValidation['is_valid']) {
            $errors['batch_trans_loan_to_top_up'] = $loanToTopUpValidation['message'];
        }


        if (!empty($errors)) {
            return redirect()->back()->withErrors($errors)->withInput();
        }

        $guaranteedAmount = $guarantorsResult;
        if (!$this->validateGuarantorsRequirement($loanType->loan_type_guaranteable_percent, $data['batch_trans_loan_amount'], $guaranteedAmount)) {
            $errors['guarantors'] = 'Insufficient guarantee amount.';
            return redirect()->back()->withErrors($errors)->withInput();
        }

          
          
        $data['batch_trans_member_id'] = $member->member_id;
        $data['batch_trans_batch_id'] = $batch_id;
        $data['batch_trans_by'] = auth()->id();
        $data['batch_trans_ip'] = $request->ip();
        $data['batch_trans_commission'] = $data['batch_trans_commission_amount'] ?? null;

        
        unset($data['batch_trans_commission_amount']);

        DB::table('sacco_loan_batch_trans')->insert($data);
        $transactionId = DB::getPdo()->lastInsertId();

        $this->saveGuarantors($request->input('guarantors', []), $transactionId, $data['batch_trans_loan_amount'], $loanType);

        return redirect()->route('loans.batch.transactions', $batch_id)->with('success', 'Transaction added successfully.');
    }

    private function getMemberFromRequest($memberStr)
    {
        preg_match('/\(([^)]+)\)$/', $memberStr, $matches);
        $saccoId = $matches[1] ?? null;
        if (!$saccoId) return null;

        return DB::table('sacco_members')
            ->where('member_sacco_id', $saccoId)
            ->where('member_active', 'Y')
            ->where('member_total_share', '>', 0)
            ->first();
    }

    
    private function validateLoanDuration($duration, $maxDuration)
    {
        return $duration <= $maxDuration;
    }

    private function validateLoanAmount($amount, $maxAmount)
    {
        return $amount <= $maxAmount;
    }

    private function validateMemberEligibility($member, $qualificationPeriod)
    {
        $joinedDate = new \DateTime($member->member_date_joined);
        $currentDate = new \DateTime();
        $diff = $currentDate->diff($joinedDate);
        $months = $diff->y * 12 + $diff->m;

        return $months >= $qualificationPeriod;
    }

    private function validateAndCalculateGuarantors($member, $loanAmount, $loanType, $guarantors)
    {
        $totalGuaranteed = 0;
        $errors = [];
        $maxGuarantorFactor = $this->getMaxGuarantorFactor();
        $memberFreeSharesSelf = max($member->member_total_share - $member->member_tied_shares_self, 0);
        $memberFreeSharesOthers = max($member->member_total_share - $member->member_tied_shares, 0);

        foreach ($guarantors as $index => $guarantor) {
            $guarantorMember = $this->getMemberFromRequest($guarantor['member']);

            if (!$guarantorMember) {
                $errors["guarantors.{$index}.member"] = 'Invalid guarantor selected.';
                continue;
            }

            $guarantorName = $guarantorMember->member_name;

            if ($guarantorMember->member_id === $member->member_id) {
                $guaranteedAmount = min($guarantor['amount'], $memberFreeSharesSelf);
                $totalGuaranteed += $guaranteedAmount;

                if ($guarantor['amount'] > $memberFreeSharesSelf) {
                    $errors["guarantors.{$index}.amount"] = "Insufficient free shares for self-guarantee by {$guarantorName}. Available: " . number_format($memberFreeSharesSelf, 2);
                }
            } else {
                $guaranteeLimit = $this->calculateGuaranteeLimit($guarantorMember, $maxGuarantorFactor);
                $guaranteedAmount = min($guarantor['amount'], $guaranteeLimit);
                $totalGuaranteed += $guaranteedAmount;

                if ($guarantor['amount'] > $guaranteeLimit) {
                    $errors["guarantors.{$index}.amount"] = "Guarantor {$guarantorName}'s available free shares do not cover the guaranteed amount. Available: " . number_format($guaranteeLimit, 2);
                }
            }
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        return $totalGuaranteed;
    }

    private function validateGuarantorsRequirement($guaranteablePercent, $loanAmount, $guaranteedAmount)
    {
        if ($guaranteablePercent > 0) {
            $requiredGuarantee = $loanAmount * ($guaranteablePercent / 100);
            return $guaranteedAmount >= $requiredGuarantee;
        }

        return true;
    }

    private function getMaxGuarantorFactor()
    {
        return DB::table('sacco_defaults')
            ->where('default_name', 'max_guarantor_factor')
            ->value('default_value') ?? 1;
    }

    private function calculateGuaranteeLimit($guarantor, $maxGuarantorFactor)
    {
        $totalShares = $guarantor->member_total_share;
        $tiedShares = $guarantor->member_tied_shares;
        return ($totalShares * $maxGuarantorFactor) - $tiedShares;
    }

    private function saveGuarantors($guarantors, $transactionId, $loanAmount, $loanType)
    {
        $guaranteablePercent = $loanType->loan_type_guaranteable_percent;
        $requiredGuarantee = $loanAmount * ($guaranteablePercent / 100);
        $totalGuarantorsAmount = array_sum(array_column($guarantors, 'amount'));

        foreach ($guarantors as $guarantor) {
            $guarantorMember = $this->getMemberFromRequest($guarantor['member']);
            if ($guarantorMember) {
                $guaranteedAmount = $guarantor['amount'];
                if ($totalGuarantorsAmount > $requiredGuarantee) {
                    $guaranteedAmount = $this->prorateGuarantee($guarantor['amount'], $totalGuarantorsAmount, $requiredGuarantee);
                }

                DB::table('sacco_loan_batch_guarantors')->insert([
                    'guarantors_loan_batch_trans_id' => $transactionId,
                    'guarantors_guarantor_id' => $guarantorMember->member_id,
                    'guarantors_amount_guaranteed' => $guaranteedAmount,
                    'guarantors_description' => $guarantor['description'] ?? '',
                    'guarantors_by' => auth()->id(),
                    'guarantors_ip' => request()->ip(),
                ]);
            }
        }
    }

    private function prorateGuarantee($amount, $totalAmount, $requiredAmount)
    {
        return ($amount / $totalAmount) * $requiredAmount;
    }

    private function validateShareAndLoanConstraint($member, $loanType, $requestedLoanAmount)
    {
        $shareFactor = is_numeric($loanType->loan_type_share_factor) ? $loanType->loan_type_share_factor : 1;
        $availableShares = ($member->member_total_share * $shareFactor) - $member->member_total_loan;

        return [
            'is_valid' => $availableShares >= $requestedLoanAmount,
            'max_amount' => $availableShares
        ];
    }

    public function loans_batch_transactions_update(Request $request, $batch_id, $transaction_id)
{
    // Retrieve the batch
    $batch = DB::table('sacco_loan_batch')
        ->where('batch_id', $batch_id)
        ->where('batch_deleted', 'N')
        ->first();

        // dd($request->post());

    if (!$batch) {
        return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
    }

    // Retrieve the transaction
    $transaction = DB::table('sacco_loan_batch_trans')->where('batch_trans_id', $transaction_id)->first();
    if (!$transaction) {
        return redirect()->route('loans.batches')->with('error', 'Transaction not found.');
    }

    // Validate the member
    $member = $this->getMemberFromRequest($request->input('batch_trans_member_id'));
    if (!$member) {
        return redirect()->back()->withErrors(['batch_trans_member_id' => 'Invalid member selection or member has no shares.'])->withInput();
    }

    // Validate request data
    $data = $request->validate([
        'batch_trans_loan_type' => 'required|integer',
        'batch_trans_loan_category' => 'required|integer',
        'batch_trans_loan_amount' => 'required|numeric|min:1.01',
        'batch_trans_loan_duration' => 'required|integer|min:1',
        'batch_trans_doc_no' => 'required|string|max:100',
        'batch_trans_description' => 'nullable|string|max:100',
        'batch_trans_commission_amount' => 'nullable|numeric|min:0',
        'batch_trans_loan_to_top_up' => 'nullable|integer',
    ]);

    // Validate loan top-up logic
 
    



    // Validate loan type
    $loanType = DB::table('sacco_loan_types')->where('loan_type_id', $data['batch_trans_loan_type'])->first();
    if (!$loanType) {
        return redirect()->back()->withErrors(['batch_trans_loan_type' => 'Invalid loan type selected.'])->withInput();
    }

    // Perform additional validations
    $errors = [];
    $loanToTopUpValidation = $this->validateLoanToTopUp($member->member_id, $data['batch_trans_loan_to_top_up'], $data['batch_trans_loan_amount']);
    if (!$loanToTopUpValidation['is_valid']) {
        $errors['batch_trans_loan_to_top_up'] = $loanToTopUpValidation['message'];
    }

    if (!$this->validateLoanDuration($data['batch_trans_loan_duration'], $loanType->loan_type_duration)) {
        $errors['batch_trans_loan_duration'] = 'Loan duration exceeds the maximum allowed for this loan type.';
    }

    if (!$this->validateLoanAmount($data['batch_trans_loan_amount'], $loanType->loan_type_max_amount)) {
        $errors['batch_trans_loan_amount'] = 'Loan amount exceeds the maximum allowed for this loan type.';
    }

    if (!$this->validateMemberEligibility($member, $loanType->loan_type_qualification_period)) {
        $errors['batch_trans_member_id'] = 'Member does not meet the qualification period for this loan type.';
    }

    // Check for share and loan constraints
    $shareAndLoanValidation = $this->validateShareAndLoanConstraint($member, $loanType, $data['batch_trans_loan_amount']);
    if (!$shareAndLoanValidation['is_valid']) {
        $maxLoanAmount = $shareAndLoanValidation['max_amount'];
        $errors['batch_trans_loan_amount'] = "Insufficient shares or high existing loans. The maximum loan you can take is KES " . number_format($maxLoanAmount, 2) . ".";
    }

    // Validate and calculate guarantors
    $guarantorsResult = $this->validateAndCalculateGuarantors($member, $data['batch_trans_loan_amount'], $loanType, $request->input('guarantors', []));
    if (is_array($guarantorsResult) && isset($guarantorsResult['errors'])) {
        $errors = array_merge($errors, $guarantorsResult['errors']);
    }

    if (!empty($errors)) {
        return redirect()->back()->withErrors($errors)->withInput();
    }

    $guaranteedAmount = $guarantorsResult;
    $requiredGuarantee = $data['batch_trans_loan_amount'] * ($loanType->loan_type_guaranteable_percent / 100);
    if (!$this->validateGuarantorsRequirement($loanType->loan_type_guaranteable_percent, $data['batch_trans_loan_amount'], $guaranteedAmount)) {
        $missingGuaranteeAmount = number_format($requiredGuarantee - $guaranteedAmount, 2);
        $errors['guarantors'] = "Insufficient guarantee amount. Additional guarantee of KES $missingGuaranteeAmount is needed.";
        return redirect()->back()->withErrors($errors)->withInput();
    }

    // Prepare data for update
    $data['batch_trans_member_id'] = $member->member_id;
    $data['batch_trans_batch_id'] = $batch_id;
    $data['batch_trans_by'] = auth()->id();
    $data['batch_trans_ip'] = $request->ip();
    $data['batch_trans_commission'] = $data['batch_trans_commission_amount'] ?? null;
    unset($data['batch_trans_commission_amount']);

    // Update transaction
    DB::table('sacco_loan_batch_trans')
        ->where('batch_trans_id', $transaction_id)
        ->update($data);

    // Delete old guarantors
    DB::table('sacco_loan_batch_guarantors')
        ->where('guarantors_loan_batch_trans_id', $transaction_id)
        ->delete();

    // Save new guarantors
    $this->saveGuarantors($request->input('guarantors', []), $transaction_id, $data['batch_trans_loan_amount'], $loanType);

    return redirect()->route('loans.batch.transactions', $batch_id)->with('success', 'Transaction updated successfully.');
}

private function validateLoanToTopUp($memberId, $loanToTopUpId, $newLoanAmount)
{
    if (!$loanToTopUpId) {
        return ['is_valid' => true]; // No top-up requested
    }

    

    // Fetch the loan details for the selected top-up loan
    $loan = DB::table('sacco_loans')
        ->where('loan_id', $loanToTopUpId)
        ->where('loan_member', $memberId)
        ->where('loan_stoped', 'N')
        ->where(DB::raw('(loan_amount - loan_loan_paid)'), '>', 0)
        ->first();

    if (!$loan) {
        return [
            'is_valid' => false,
            'message' => 'Selected loan is not eligible for top-up.'
        ];
    }

   

    // Calculate the outstanding balance
    $outstandingBalance = $loan->loan_amount - $loan->loan_loan_paid;

    

    // Check if the outstanding balance is greater than the new loan amount
    if ($outstandingBalance > $newLoanAmount) {
        
        return [
            'is_valid' => false,
            'message' => 'The outstanding balance of the selected loan for top-up exceeds the amount of the new loan.'
        ];
    }

    return ['is_valid' => true];
}
private function updateSaccoAccountsTrans($subAccountId, $debit, $credit, $docNo, $description, $date, $period, $sourceDescription)
    {
        DB::table('sacco_accounts_trans')->insert([
            'accounts_trans_sub_account' => $subAccountId,
            'accounts_trans_period' => $period,
            'accounts_trans_debit' => $debit,
            'accounts_trans_credit' => $credit,
            'accounts_trans_doc_no' => $docNo,
            'accounts_trans_decription' => $description,
            'accounts_trans_dat_date' => $date,
            'accounts_trans_user_id' => Auth::id(),
            'accounts_trans_ip' => request()->ip(),
            'accounts_trans_source' => $sourceDescription,
            'accounts_trans_app_name' => 'iSacco',
        ]);

        DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->increment('sub_account_debit', $debit);
        DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->increment('sub_account_credit', $credit);

        $mainAccountId = DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->value('sub_account_main_account');

        DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccountId)
            ->increment('main_account_debit', $debit);
        DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccountId)
            ->increment('main_account_credit', $credit);
    }

    public function finalizeBatch($batchId)
{
    $errors = [];

    // Fetch the batch details and check if it's eligible for processing
    $batch = DB::table('sacco_loan_batch')
        ->where('batch_id', $batchId)
        ->where('batch_deleted', 'N')
        ->where('batch_updated', 'N')
        ->first();

    if (!$batch) {
        $errors[] = 'Batch cannot be processed because it has been deleted or already finalized.';
    } elseif ($batch->batch_approved != 'Y') {
        $errors[] = 'Batch cannot be processed because it is not approved.';
    }

    if (!empty($errors)) {
        return redirect()->route('loans.batches')->withErrors($errors);
    }

    // Initialize transaction and default accounts if needed
    DB::beginTransaction();

    try {
        $transactions = DB::table('sacco_loan_batch_trans')
            ->where('batch_trans_batch_id', $batchId)
            ->where('batch_trans_deleted', 'N')
            ->get();
        
        // Retrieve default accounts
        $default_bank_account = $this->getDefaultAccount('default_bank_account');
        $default_insurance_account = $this->getDefaultAccount('default_insurance_account');
        $default_commission_account = $this->getDefaultAccount('default_loan_commission_account');
        $default_loan_account = $this->getDefaultAccount('default_loan_account');

        foreach ($transactions as $transaction) {
            // Retrieve specific accounts from sacco_loan_types
            $loanType = DB::table('sacco_loan_types')
                ->where('loan_type_id', $transaction->batch_trans_loan_type)
                ->first();

            $loan_account = $loanType->loan_type_acount ?? $default_loan_account;
            $commission_account = $loanType->loan_type_comm_account ?? $default_commission_account;
            $insurance_account = $loanType->loan_type_int_account ?? $default_insurance_account;

            // Check if bank account exists, fallback to default if necessary
            $bank_account = $batch->batch_credit_account ?? $default_bank_account;

            // Validate that all accounts are set
            if (!$loan_account || !$commission_account || !$insurance_account || !$bank_account) {
                DB::rollBack();
                return redirect()->route('loans.batches')->withErrors([
                    'error' => 'Missing required accounts for processing the batch.'
                ]);
            }
            try {
                $loanId = DB::table('sacco_loans')->insertGetId([
                    'loan_member' => $transaction->batch_trans_member_id,
                    'loan_loan_type' => $transaction->batch_trans_loan_type,
                    'loan_loan_category'=>$transaction->batch_trans_loan_category,
                    'loan_amount' => $transaction->batch_trans_loan_amount + $transaction->batch_trans_insurance,
                    'loan_insurance' => $transaction->batch_trans_insurance,
                    'loan_commision' => $transaction->batch_trans_commission,
                    'loan_payment_period' => $transaction->batch_trans_loan_duration,
                    'loan_doc_no' => $transaction->batch_trans_doc_no,
                    'loan_description' => $transaction->batch_trans_description,
                    'loan_batch_no' => "{$batch->batch_id}-{$transaction->batch_trans_id}",
                    'loan_account_credited' => $bank_account,
                    'loan_account_debited' => $loan_account,
                    'loan_by' => auth()->id(),
                    'loan_ip' => request()->ip(),
                    'loan_taken_period' => $this->currentPeriod->period_name,
                ]);
                Log::info('Loan ID created successfully: ' . $loanId);  // Log or display for debugging
            } catch (\Exception $e) {
                Log::error('Loan insertion failed: ' . $e->getMessage());
                DB::rollBack();
                return redirect()->route('loans.batches')->with('error', 'Batch finalization failed: ' . $e->getMessage());
            }
          //  dd($transaction->batch_trans_loan_to_top_up_amount);

            if ($transaction->batch_trans_loan_to_top_up>0) {
                 
                $this->processLoanTopUp($transaction);
            }

            // Update member's total loan
            DB::table('sacco_members')
                ->where('member_id', $transaction->batch_trans_member_id)
                ->increment('member_total_loan', $transaction->batch_trans_loan_amount + $transaction->batch_trans_insurance);

            // Ledger updates based on accounts retrieved

            // Credit the loan account
            $this->updateSaccoAccountsTrans(
                $loan_account,
                0,
                $transaction->batch_trans_loan_amount,
                $transaction->batch_trans_doc_no,
                'Loan Disbursement for ' . $transaction->batch_trans_member_id,
                now(),
                $this->currentPeriod->period_name,
                'Loan Disbursement'
            );

            // Debit the bank account, excluding commission and insurance
            $bank_credit = $transaction->batch_trans_loan_amount - $transaction->batch_trans_commission - $transaction->batch_trans_insurance;
            $this->updateSaccoAccountsTrans(
                $bank_account,
                $bank_credit,
                0,
                $transaction->batch_trans_doc_no,
                'Loan Payout for ' . $transaction->batch_trans_member_id,
                now(),
                $this->currentPeriod->period_name,
                'Loan Disbursement'
            );

            // Insurance account entry
            if ($transaction->batch_trans_insurance > 0) {
                $this->updateSaccoAccountsTrans(
                    $insurance_account,
                    0,
                    $transaction->batch_trans_insurance,
                    $transaction->batch_trans_doc_no,
                    'Insurance Fee for ' . $transaction->batch_trans_member_id,
                    now(),
                    $this->currentPeriod->period_name,
                    'Loan Insurance Fee'
                );
            }

            // Commission account entry
            if ($transaction->batch_trans_commission > 0) {
                $this->updateSaccoAccountsTrans(
                    $commission_account,
                    0,
                    $transaction->batch_trans_commission,
                    $transaction->batch_trans_doc_no,
                    'Commission Fee for ' . $transaction->batch_trans_member_id,
                    now(),
                    $this->currentPeriod->period_name,
                    'Loan Commission Fee'
                );
            }
        }

        DB::table('sacco_loan_batch')->where('batch_id', $batchId)->update(['batch_updated' => 'Y']);
        DB::commit();

        return redirect()->route('loans.batches')->with('success', 'Batch finalized and transferred successfully.');
    } catch (\Exception $e) {
        DB::rollBack();
        return redirect()->route('loans.batches')->with('error', 'Batch finalization failed: ' . $e->getMessage());
    }
}
    // public function finalizeBatch($batchId)
    // {
  
    //     $errors = [];

    //     $batch = DB::table('sacco_loan_batch')
    //         ->where('batch_id', $batchId)
    //         ->where('batch_deleted', 'N')
    //         ->where('batch_updated', 'N')
    //         ->first();

    //     if (!$batch) {
    //         $errors[] = 'Batch cannot be processed because it has been deleted or already finalized.';
    //     } elseif ($batch->batch_approved != 'Y') {
    //         $errors[] = 'Batch cannot be processed because it is not approved.';
    //     }

    //     if (empty($errors)) {
    //         $transactionSummary = DB::table('sacco_loan_batch_trans')
    //             ->where('batch_trans_batch_id', $batchId)
    //             ->where('batch_trans_deleted', 'N')
    //             ->select(DB::raw('SUM(batch_trans_loan_amount) as total_transaction_amount'), DB::raw('COUNT(*) as transaction_count'))
    //             ->first();

    //         if ($transactionSummary->transaction_count != $batch->batch_total_trans) {
    //             $errors[] = 'The total number of transactions does not match the batch total transaction count.';
    //         }
    //         if ($transactionSummary->total_transaction_amount != $batch->batch_amount) {
    //             $errors[] = 'The total transaction amount does not match the batch amount.';
    //         }
    //     }

       
       
    //     if (!empty($errors)) {
    //         return redirect()->route('loans.batches')->withErrors($errors);
    //     }
        

    //     DB::beginTransaction();

    //     try {
    //         $transactions = DB::table('sacco_loan_batch_trans')
    //             ->where('batch_trans_batch_id', $batchId)
    //             ->where('batch_trans_deleted', 'N')
    //             ->get();

    //         foreach ($transactions as $transaction) {
    //             $loanId = DB::table('sacco_loans')->insertGetId([
    //                 'loan_member' => $transaction->batch_trans_member_id,
    //                 'loan_loan_type' => $transaction->batch_trans_loan_type,
    //                 'loan_loan_category' => $transaction->batch_trans_loan_category,
    //                 'loan_amount' => $transaction->batch_trans_loan_amount + $transaction->batch_trans_insurance,
    //                 'loan_insurance' => $transaction->batch_trans_insurance,
    //                 'loan_commision' => $transaction->batch_trans_commission,
    //                 'loan_payment_period' => $transaction->batch_trans_loan_duration,
    //                 'loan_doc_no' => $transaction->batch_trans_doc_no,
    //                 'loan_description' => $transaction->batch_trans_description,
    //                 'loan_batch_no' => "{$batch->batch_id}-{$transaction->batch_trans_id}",
    //                 'loan_account_credited' => $batch->batch_credit_account,
    //                 'loan_account_debited' => $transaction->batch_trans_loan_type,
    //                 'loan_by' => auth()->id(),
    //                 'loan_ip' => request()->ip(),
    //                 'loan_taken_period' => $this->currentPeriod->period_name,
    //             ]);

    //             DB::table('sacco_members')
    //                 ->where('member_id', $transaction->batch_trans_member_id)
    //                 ->increment('member_total_loan', $transaction->batch_trans_loan_amount + $transaction->batch_trans_insurance);

    //             $this->processGuarantors($transaction, $loanId);

    //             if ($transaction->batch_trans_loan_to_top_up_amount > 0 && $transaction->batch_trans_loan_to_top_up) {
    //                 $this->processLoanTopUp($transaction);
    //             }

    //             $this->updateSaccoAccountsTrans(
    //                 $transaction->batch_trans_loan_type,
    //                 0,
    //                 $transaction->batch_trans_loan_amount,
    //                 $transaction->batch_trans_doc_no,
    //                 $transaction->batch_trans_description,
    //                 now(),
    //                 $this->currentPeriod->period_name,
    //                 'Loan Disbursement'
    //             );

    //             $this->updateSaccoAccountsTrans(
    //                 $batch->batch_credit_account,
    //                 $transaction->batch_trans_loan_amount,
    //                 0,
    //                 $transaction->batch_trans_doc_no,
    //                 $transaction->batch_trans_description,
    //                 now(),
    //                 $this->currentPeriod->period_name,
    //                 'Loan Disbursement'
    //             );
    //         }

    //         DB::table('sacco_loan_batch')->where('batch_id', $batchId)->update(['batch_updated' => 'Y']);

    //         DB::commit();

    //         return redirect()->route('loans.batches')->with('success', 'Batch finalized and transferred successfully.');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return redirect()->route('loans.batches')->with('error', 'Batch finalization failed: ' . $e->getMessage());
    //     }
    // }
    private function processGuarantors($transaction, $loanId)
        {
            // Retrieve the guarantors for the specific transaction
            $guarantors = DB::table('sacco_loan_batch_guarantors')
                ->where('guarantors_loan_batch_trans_id', $transaction->batch_trans_id)
                ->where('guarantors_deleted', 'N')
                ->get();

            foreach ($guarantors as $guarantor) {
                // Insert each guarantor record linked to the new loan record
                DB::table('sacco_loan_guarantors')->insert([
                    'loan_guar_loan_id' => $loanId,
                    'loan_guar_guarantor_id' => $guarantor->guarantors_guarantor_id,
                    'loan_guar_amount_guaranteed' => $guarantor->guarantors_amount_guaranteed,
                    'loan_guar_deleted' => 'N',
                ]);

                // Update the tied shares of the guarantor to reflect the guarantee
                DB::table('sacco_members')
                    ->where('member_id', $guarantor->guarantors_guarantor_id)
                    ->increment('member_tied_shares', $guarantor->guarantors_amount_guaranteed);
            }
        }

    private function getDefaultAccount($account_name)
        {
            return DB::table('sacco_defaults')
                ->where('default_name', $account_name)
                ->value('default_value');
        }
        private function processLoanTopUp($transaction)
{
    // Fetch the full remaining balance on the existing loan
    $existingLoan = DB::table('sacco_loans')
        ->where('loan_id', $transaction->batch_trans_loan_to_top_up)
        ->value('loan_amount');

    // Reduce the outstanding loan balance by the entire existing loan amount
    DB::table('sacco_loans')
        ->where('loan_id', $transaction->batch_trans_loan_to_top_up)
        ->decrement('loan_loan_paid', $existingLoan);

    // Prepare top-up payment details
    $paidBy = "LOAN CLEARANCE - LNo." . $transaction->batch_trans_loan_to_top_up;

    // Insert top-up payment record as a reduction in the outstanding balance
    DB::table('sacco_loan_payments')->insert([
        'loan_payments_amount' => $existingLoan,
        'loan_payments_description' => $transaction->batch_trans_description,
        'loan_payments_docno' => $transaction->batch_trans_doc_no,
        'loan_payments_paid_in_by' => $paidBy,
        'loan_payments_period' => $this->currentPeriod->period_name,
        'loan_payments_paid_on' => now(),
        'loan_payments_loan_id' => $transaction->batch_trans_loan_to_top_up,
        'loan_payments_interest' => 0,
        'loan_payments_by' => auth()->id(),
        'loan_payments_ip' => request()->ip()
    ]);

    // Adjust member's total loan balance
    DB::table('sacco_members')
        ->where('member_id', $transaction->batch_trans_member_id)
        ->decrement('member_total_loan', $existingLoan);

    // Adjust guarantor obligations if any
    $guarantors = DB::table('sacco_loan_guarantors')
        ->where('loan_guar_loan_id', $transaction->batch_trans_loan_to_top_up)
        ->where('loan_guar_deleted', 'N')
        ->get();

    foreach ($guarantors as $guarantor) {
        $guaranteedAmount = $guarantor->loan_guar_amount_guaranteed - $guarantor->loan_guar_amount_freed;

        DB::table('sacco_members')
            ->where('member_id', $guarantor->loan_guar_guarantor_id)
            ->decrement('member_tied_shares', $guaranteedAmount);

        if ($transaction->batch_trans_member_id == $guarantor->loan_guar_guarantor_id) {
            DB::table('sacco_members')
                ->where('member_id', $guarantor->loan_guar_guarantor_id)
                ->decrement('member_tied_shares_self', $guaranteedAmount);

            DB::table('sacco_loan_guarantors')
                ->where('loan_guar_loan_id', $transaction->batch_trans_loan_to_top_up)
                ->where('loan_guar_guarantor_id', $guarantor->loan_guar_guarantor_id)
                ->update(['loan_guar_deleted' => 'Y']);
        }
    }

    DB::table('sacco_loan_guarantors')
        ->where('loan_guar_loan_id', $transaction->batch_trans_loan_to_top_up)
        ->update(['loan_guar_amount_freed' => DB::raw('loan_guar_amount_guaranteed')]);

    // Ledger Entries for Loan Clearance

    // Credit the loan account to reflect the cleared balance
    $this->updateSaccoAccountsTrans(
        $transaction->batch_trans_loan_type,
        0,
        $existingLoan,
        $transaction->batch_trans_doc_no,
        'Loan Clearance for Member ID: ' . $transaction->batch_trans_member_id,
        now(),
        $this->currentPeriod->period_name,
        'Loan Clearance'
    );

    // Debit the bank account to reflect the outflow of the clearance amount
    $this->updateSaccoAccountsTrans(
        $transaction->batch_credit_account ?? $this->getDefaultAccount('default_bank_account'),
        $existingLoan,
        0,
        $transaction->batch_trans_doc_no,
        'Loan Clearance for Member ID: ' . $transaction->batch_trans_member_id,
        now(),
        $this->currentPeriod->period_name,
        'Loan Clearance'
    );
}
}