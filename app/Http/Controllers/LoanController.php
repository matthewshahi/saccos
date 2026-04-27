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
        ->where('sacco_main_account.main_account_deleted', 'N')
        ->where('sacco_sub_account.sub_account_deleted', 'N')
        ->where(function ($query) {
            $query->where('sacco_sub_account.sub_account_name', 'LIKE', '%BANK%')
                  ->orWhere('sacco_sub_account.sub_account_name', 'LIKE', '%MPESA%');
        })
        ->orderBy('sacco_sub_account.sub_account_name', 'asc')
        ->orderBy('sacco_main_account.main_account_code', 'asc')
        ->orderBy('sacco_sub_account.sub_account_code', 'asc')
        ->select(
            'sacco_sub_account.sub_account_id',
            DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code, ' - ', sacco_sub_account.sub_account_name) AS account_name")
        )
        ->get();

    return view('loans.batch_form', [
        'accounts' => $accounts,
        'currentPeriod' => $this->currentPeriod
    ]);
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

    $transactionSummary = DB::table('sacco_loan_batch_trans')
        ->where('batch_trans_batch_id', $batch_id)
        ->where('batch_trans_deleted', 'N')
        ->select(
            DB::raw('COALESCE(SUM(batch_trans_loan_amount), 0) as total_transaction_amount'),
            DB::raw('COUNT(*) as transaction_count')
        )
        ->first();

    $canApprove = (
        (int) $transactionSummary->transaction_count === (int) $batch->batch_total_trans &&
        (float) $transactionSummary->total_transaction_amount == (float) $batch->batch_amount
    );

    $accounts = DB::table('sacco_sub_account')
        ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
        ->where('sacco_main_account.main_account_code', 'like', 'A%')
        ->where('sacco_main_account.main_account_deleted', 'N')
        ->where('sacco_sub_account.sub_account_deleted', 'N')
        ->where(function ($query) {
            $query->where('sacco_sub_account.sub_account_name', 'LIKE', '%Bank%')
                  ->orWhere('sacco_sub_account.sub_account_name', 'LIKE', '%MPESA%');
        })
        ->orderBy('sacco_sub_account.sub_account_name', 'asc')
        ->orderBy('sacco_main_account.main_account_code', 'asc')
        ->orderBy('sacco_sub_account.sub_account_code', 'asc')
        ->select(
            'sacco_sub_account.sub_account_id',
            DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code, ' - ', sacco_sub_account.sub_account_name) AS account_name")
        )
        ->get();

    return view('loans.batch_form', [
        'batch' => $batch,
        'accounts' => $accounts,
        'currentPeriod' => $this->currentPeriod,
        'canApprove' => $canApprove,
        'transactionSummary' => $transactionSummary,
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
    $batch = DB::table('sacco_loan_batch')
        ->where('batch_id', $batch_id)
        ->first();

    if (!$batch || $batch->batch_deleted == 'Y') {
        return redirect()->route('loans.batches')
            ->with('error', 'Batch not found or has been deleted.');
    }

    if ($batch->batch_updated == 'Y') {
        return redirect()->route('loans.batches')
            ->with('error', 'This batch has already been finalised and cannot be edited.');
    }

    $batchUpdated = ($request->input('batch_updated') === 'on' || $request->input('batch_updated') === 'Y') ? 'Y' : 'N';
    $batchApproved = ($request->input('batch_approved') === 'on' || $request->input('batch_approved') === 'Y') ? 'Y' : 'N';

    /*
    |---------------------------------------------------------
    | If already approved, do not allow any more edits.
    | Only allow finalisation.
    |---------------------------------------------------------
    */
    if ($batch->batch_approved == 'Y') {
        if ($batchUpdated === 'Y') {
            return $this->finalizeBatch($batch_id);
        }

        return redirect()->route('loans.batches')
            ->with('error', 'This batch has already been approved. No further edits are allowed. You can only finalise it.');
    }

    $data = $request->validate([
        'batch_reference' => 'required|string|max:100',
        'batch_amount' => 'required|numeric|min:1.01',
        'batch_total_trans' => 'required|integer|min:1',
        'batch_credit_account' => 'required|integer',
    ]);

    $data['batch_approved'] = $batchApproved;
    $data['batch_by'] = auth()->id();
    $data['batch_ip'] = $request->ip();
    $data['batch_period'] = $this->currentPeriod->period_name;
    $data['batch_amount'] = str_replace(',', '', $data['batch_amount']);

    try {
        DB::table('sacco_loan_batch')
            ->where('batch_id', $batch_id)
            ->update($data);

        // approve + finalise in one action
        if ($data['batch_approved'] === 'Y' && $batchUpdated === 'Y') {
            return $this->finalizeBatch($batch_id);
        }

        if ($data['batch_approved'] === 'Y') {
            return redirect()->route('loans.batches')
                ->with('success', 'Loan batch approved successfully. No further edits are allowed except finalisation.');
        }

        return redirect()->route('loans.batches')
            ->with('success', 'Loan batch updated successfully.');
    } catch (\Exception $e) {
        return redirect()->route('loans.batches')
            ->with('error', 'SQL Error: ' . $e->getMessage());
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
        return redirect()->route('loans.batches')
            ->with('error', 'Batch not found or has been deleted.');
    }

    $deductionsSub = DB::table('sacco_loan_batch_trans_deductions')
        ->select(
            'batch_trans_deduction_batch_trans_id',
            DB::raw("
                SUM(
                    CASE
                        WHEN batch_trans_deduction_deleted = 'N'
                         AND batch_trans_deduction_effect = 'ADD_TO_LOAN'
                        THEN batch_trans_deduction_amount
                        ELSE 0
                    END
                ) as total_add_to_loan
            "),
            DB::raw("
                SUM(
                    CASE
                        WHEN batch_trans_deduction_deleted = 'N'
                         AND batch_trans_deduction_effect = 'DEDUCT_FROM_DISBURSEMENT'
                        THEN batch_trans_deduction_amount
                        ELSE 0
                    END
                ) as total_deduct_from_disbursement
            "),
            DB::raw("
                GROUP_CONCAT(
                    CASE
                        WHEN batch_trans_deduction_deleted = 'N'
                        THEN batch_trans_deduction_description
                        ELSE NULL
                    END
                    SEPARATOR ' | '
                ) as deduction_descriptions
            ")
        )
        ->groupBy('batch_trans_deduction_batch_trans_id');

    $transactions = DB::table('sacco_loan_batch_trans as t')
        ->join('sacco_loan_types as lt', 't.batch_trans_loan_type', '=', 'lt.loan_type_id')
        ->join('sacco_loan_category as lc', 't.batch_trans_loan_category', '=', 'lc.loan_category_id')
        ->join('sacco_members as m', 't.batch_trans_member_id', '=', 'm.member_id')
        ->leftJoinSub($deductionsSub, 'd', function ($join) {
            $join->on('t.batch_trans_id', '=', 'd.batch_trans_deduction_batch_trans_id');
        })
        ->leftJoin('users as u', 't.batch_trans_by', '=', 'u.id')
        ->where('t.batch_trans_batch_id', $batch_id)
        ->where('t.batch_trans_deleted', 'N')
        ->orderBy('t.batch_trans_id', 'asc')
        ->select(
            't.*',
            'lt.loan_type_name',
            'lt.loan_type_interest',
            'lc.loan_category_name',
            'm.member_name',
            'm.member_sacco_id',
            'm.member_phone_no',
            'm.member_email',
            'm.member_national_id',
            'm.bank_name',
            'm.bank_branch',
            'm.bank_account_number',
            DB::raw('COALESCE(u.name, "System") as user_name'),
            DB::raw('COALESCE(d.total_add_to_loan, 0) as batch_trans_total_add_to_loan'),
            DB::raw('COALESCE(d.total_deduct_from_disbursement, 0) as batch_trans_total_deduct_from_disbursement'),
            DB::raw('COALESCE(d.deduction_descriptions, "") as batch_trans_deduction_descriptions'),
            DB::raw('COALESCE(t.batch_trans_expected_interest, 0) as batch_trans_interest_amount'),
            DB::raw('(COALESCE(t.batch_trans_loan_amount, 0) + COALESCE(d.total_add_to_loan, 0)) as batch_trans_amount_for_emi'),
           DB::raw("
    (
        COALESCE(t.batch_trans_loan_amount, 0)

        - COALESCE(d.total_deduct_from_disbursement, 0)

        - CASE
            WHEN UPPER(TRIM(COALESCE(lt.loan_type_commission_effect, 'ADD_TO_LOAN'))) = 'DEDUCT_FROM_DISBURSEMENT'
            THEN COALESCE(t.batch_trans_commission, 0)
            ELSE 0
          END

        - CASE
            WHEN UPPER(TRIM(COALESCE(lt.loan_type_insurance_effect, 'ADD_TO_LOAN'))) = 'DEDUCT_FROM_DISBURSEMENT'
            THEN COALESCE(t.batch_trans_insurance, 0)
            ELSE 0
          END

        - COALESCE(t.batch_trans_loan_to_top_up_amount, 0)

    ) as batch_trans_net_disbursement
"),
            DB::raw('
                (
                    COALESCE(t.batch_trans_monthly_payment, 0)
                    * COALESCE(t.batch_trans_loan_duration, 0)
                ) as batch_trans_total_payable
            ')
        )
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

if ($batch->batch_approved === 'Y' || $batch->batch_updated === 'Y') {
    $message = $batch->batch_updated === 'Y'
        ? 'This batch has already been finalised and cannot accept new transactions.'
        : 'This batch has already been approved and cannot accept new transactions.';

    return redirect()->back()->with('error', $message);
}


    $loanTypes = DB::table('sacco_loan_types')
        ->where('loan_type_deleted', 'N')
        ->where('loan_type_active', 1)
        ->orderBy('loan_type_name', 'asc')
        ->get();

    $loanCategories = DB::table('sacco_loan_category')
        ->where('loan_category_deleted', 'N')
        ->orderBy('loan_category_name', 'asc')
        ->get();

    $members = DB::table('sacco_members')
        ->where('member_deleted', 'N')
        ->where('member_active', 'Y')
        ->orderBy('member_name', 'asc')
        ->get();

    $additionalDeductionTypes = DB::table('sacco_loan_deductions_types')
        ->where('deduction_type_deleted', 'N')
        ->where('deduction_type_active', 1)
        ->orderBy('deduction_type_name', 'asc')
        ->get();

    $loansToTopUp = collect();

    $loanMemberStr = request()->input('loan_member_id');
    $loanMemberId = null;

    if ($loanMemberStr) {
        preg_match('/\(([^)]+)\)$/', $loanMemberStr, $matches);
        $loanMemberSaccoId = $matches[1] ?? null;

        if ($loanMemberSaccoId) {
            $loanMember = DB::table('sacco_members')
                ->where('member_sacco_id', $loanMemberSaccoId)
                ->first();

            $loanMemberId = $loanMember ? $loanMember->member_id : null;
        }
    }

    if ($loanMemberId) {
        $loansToTopUp = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_loans.loan_id',
                'sacco_loan_types.loan_type_name',
                DB::raw('(loan_amount - loan_loan_paid) as loan_balance')
            )
            ->where('loan_member', $loanMemberId)
            ->where('loan_stoped', 'N')
            ->where(DB::raw('(loan_amount - loan_loan_paid)'), '>', 0)
            ->orderBy('sacco_loans.loan_id', 'desc')
            ->get();
    }

    return view('loans.transaction_add', [
        'batch' => $batch,
        'loanTypes' => $loanTypes,
        'loanCategories' => $loanCategories,
        'members' => $members,
        'loansToTopUp' => $loansToTopUp,
        'additionalDeductionTypes' => $additionalDeductionTypes,
        'currentPeriod' => $this->currentPeriod,
    ]);
}


    public function loans_batch_transactions_delete($transaction_id)
{
    $transaction = DB::table('sacco_loan_batch_trans')
        ->where('batch_trans_id', $transaction_id)
        ->first();

    if (!$transaction || $transaction->batch_trans_deleted === 'Y') {
        return redirect()
            ->route('loans.batches')
            ->with('error', 'Transaction not found or has already been deleted.');
    }

    $batch = DB::table('sacco_loan_batch')
        ->where('batch_id', $transaction->batch_trans_batch_id)
        ->first();

    if (!$batch || $batch->batch_deleted === 'Y') {
        return redirect()
            ->route('loans.batches')
            ->with('error', 'Parent batch not found or has been deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Do not allow deleting transactions from approved or finalised batches.
    |--------------------------------------------------------------------------
    */
    if ($batch->batch_approved === 'Y' || $batch->batch_updated === 'Y') {
        $message = $batch->batch_updated === 'Y'
            ? 'This batch has already been finalised. Transactions cannot be deleted.'
            : 'This batch has already been approved. Transactions cannot be deleted.';

        return redirect()
            ->route('loans.batch.transactions', $transaction->batch_trans_batch_id)
            ->with('error', $message);
    }

    DB::beginTransaction();

    try {
        DB::table('sacco_loan_batch_trans')
            ->where('batch_trans_id', $transaction_id)
            ->update([
                'batch_trans_deleted'    => 'Y',
                'batch_trans_deleted_by' => auth()->id(),
                'batch_trans_deleted_on' => now(),
                'batch_trans_deleted_ip' => request()->ip(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Soft-delete related batch deduction rows.
        |--------------------------------------------------------------------------
        */
        DB::table('sacco_loan_batch_trans_deductions')
            ->where('batch_trans_deduction_batch_trans_id', $transaction_id)
            ->where('batch_trans_deduction_deleted', 'N')
            ->update([
                'batch_trans_deduction_deleted'    => 'Y',
                'batch_trans_deduction_deleted_by' => auth()->id(),
                'batch_trans_deduction_deleted_on' => now(),
                'batch_trans_deduction_deleted_ip' => request()->ip(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Soft-delete related batch guarantors.
        |--------------------------------------------------------------------------
        */
        DB::table('sacco_loan_batch_guarantors')
            ->where('guarantors_loan_batch_trans_id', $transaction_id)
            ->where('guarantors_deleted', 'N')
            ->update([
                'guarantors_deleted'    => 'Y',
                'guarantors_deleted_by' => auth()->id(),
                'guarantors_deleted_on' => now(),
                'guarantors_deleted_ip' => request()->ip(),
            ]);

        DB::commit();

        return redirect()
            ->route('loans.batch.transactions', $transaction->batch_trans_batch_id)
            ->with('success', 'Transaction deleted successfully.');
    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Loan batch transaction delete failed: ' . $e->getMessage());

        return redirect()
            ->route('loans.batch.transactions', $transaction->batch_trans_batch_id)
            ->with('error', 'Failed to delete transaction. Please try again.');
    }
}

    public function searchMembers(Request $request)
    {
        $query = $request->input('query');
        $members = DB::table('sacco_members')
            ->select('member_id', 'member_name', 'member_sacco_id')
            ->where(function ($q) use ($query) {
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

    if (!$batch) {
        return redirect()->route('loans.batches')->with('error', 'Batch not found or has been deleted.');
    }

    $transaction = DB::table('sacco_loan_batch_trans')
        ->join('sacco_members', 'sacco_loan_batch_trans.batch_trans_member_id', '=', 'sacco_members.member_id')
        ->where('sacco_loan_batch_trans.batch_trans_id', $transaction_id)
        ->where('sacco_loan_batch_trans.batch_trans_batch_id', $batch_id)
        ->where('sacco_loan_batch_trans.batch_trans_deleted', 'N')
        ->select(
            'sacco_loan_batch_trans.*',
            'sacco_members.member_id as member_id',
            'sacco_members.member_name',
            'sacco_members.member_sacco_id'
        )
        ->first();

    if (!$transaction) {
        return redirect()->route('loans.batch.transactions', $batch_id)->with('error', 'Transaction not found.');
    }

    $loanTypes = DB::table('sacco_loan_types')
        ->where('loan_type_deleted', 'N')
        ->where('loan_type_active', 1)
        ->orderBy('loan_type_name', 'asc')
        ->get();

    $loanCategories = DB::table('sacco_loan_category')
        ->where('loan_category_deleted', 'N')
        ->orderBy('loan_category_name', 'asc')
        ->get();

    $additionalDeductionTypes = DB::table('sacco_loan_deductions_types')
        ->where('deduction_type_deleted', 'N')
        ->where('deduction_type_active', 1)
        ->orderBy('deduction_type_name', 'asc')
        ->get();

    $guarantors = DB::table('sacco_loan_batch_guarantors')
        ->join('sacco_members', 'sacco_loan_batch_guarantors.guarantors_guarantor_id', '=', 'sacco_members.member_id')
        ->where('sacco_loan_batch_guarantors.guarantors_loan_batch_trans_id', $transaction_id)
        ->where('sacco_loan_batch_guarantors.guarantors_deleted', 'N')
        ->select(
            'sacco_loan_batch_guarantors.*',
            'sacco_members.member_id as guarantor_member_id',
            'sacco_members.member_name',
            'sacco_members.member_sacco_id'
        )
        ->get();

    $existingOtherCharges = DB::table('sacco_loan_batch_trans_deductions')
        ->where('batch_trans_deduction_batch_trans_id', $transaction_id)
        ->where('batch_trans_deduction_deleted', 'N')
        ->orderBy('batch_trans_deduction_batch_id', 'asc')
        ->get();

    $loansToTopUp = DB::table('sacco_loans')
        ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
        ->select(
            'sacco_loans.loan_id',
            'sacco_loan_types.loan_type_name',
            DB::raw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) as loan_balance')
        )
        ->where('sacco_loans.loan_member', $transaction->batch_trans_member_id)
        ->where('sacco_loans.loan_stoped', 'N')
        ->where(DB::raw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid)'), '>', 0)
        ->orderBy('sacco_loans.loan_id', 'desc')
        ->get();

    return view('loans.transaction_edit', [
        'batch' => $batch,
        'transaction' => $transaction,
        'loanTypes' => $loanTypes,
        'loanCategories' => $loanCategories,
        'guarantors' => $guarantors,
        'loansToTopUp' => $loansToTopUp,
        'additionalDeductionTypes' => $additionalDeductionTypes,
        'existingOtherCharges' => $existingOtherCharges,
        'currentPeriod' => $this->currentPeriod,
    ]);
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
    ->where('member_deleted', '<>', 'Y')
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
        // $memberFreeSharesSelf = max($member->member_total_share - $member->member_tied_shares_self, 0);

        $maxGuarantorFactorSelf = $this->getMaxGuarantorFactorSelf();
        $memberFreeSharesSelf = max(0, ($member->member_total_share - $member->member_tied_shares_self)) * $maxGuarantorFactorSelf;

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
    $guaranteablePercent = (float) ($loanType->loan_type_guaranteable_percent ?? 0);
    $requiredGuarantee = round($loanAmount * ($guaranteablePercent / 100), 2);

    $cleanGuarantors = [];
    $totalEntered = 0;

    foreach ($guarantors as $guarantor) {
        if (empty($guarantor['member']) || !isset($guarantor['amount']) || !is_numeric($guarantor['amount'])) {
            continue;
        }

        $amount = round((float) $guarantor['amount'], 2);
        if ($amount <= 0) {
            continue;
        }

        $guarantorMember = $this->getMemberFromRequest($guarantor['member']);
        if (!$guarantorMember) {
            continue;
        }

        $cleanGuarantors[] = [
            'member_id' => $guarantorMember->member_id,
            'amount' => $amount,
            'description' => $guarantor['description'] ?? '',
        ];

        $totalEntered += $amount;
    }

    $totalEntered = round($totalEntered, 2);

    if ($totalEntered <= 0) {
        return 0;
    }

    $totalSaved = 0;
    $lastIndex = count($cleanGuarantors) - 1;

    foreach ($cleanGuarantors as $index => $guarantor) {
        if ($totalEntered > $requiredGuarantee && $requiredGuarantee > 0) {
            if ($index === $lastIndex) {
                $guaranteedAmount = round($requiredGuarantee - $totalSaved, 2);
            } else {
                $guaranteedAmount = round(
                    ($guarantor['amount'] / $totalEntered) * $requiredGuarantee,
                    2
                );
            }
        } else {
            $guaranteedAmount = round($guarantor['amount'], 2);
        }

        if ($guaranteedAmount <= 0) {
            continue;
        }

        DB::table('sacco_loan_batch_guarantors')->insert([
            'guarantors_loan_batch_trans_id' => $transactionId,
            'guarantors_guarantor_id'        => $guarantor['member_id'],
            'guarantors_amount_guaranteed'   => $guaranteedAmount,
            'guarantors_description'         => $guarantor['description'],
            'guarantors_by'                  => auth()->id(),
            'guarantors_ip'                  => request()->ip(),
            'guarantors_deleted'             => 'N',
        ]);

        $totalSaved += $guaranteedAmount;
    }

    return round($totalSaved, 2);
}

    private function prorateGuarantee($amount, $totalAmount, $requiredAmount)
    {
        return ($amount / $totalAmount) * $requiredAmount;
    }

     private function validateShareAndLoanConstraint($member, $loanType, $requestedLoanAmount)
{
    $policy = $this->getEffectiveLoanShareFactorPolicy($loanType);

    /*
    |--------------------------------------------------------------------------
    | If factor is 0, share-factor limit is disabled for this loan product.
    |--------------------------------------------------------------------------
    */
    if (!$policy['limited']) {
        return [
            'is_valid' => true,
            'max_amount' => null,
            'share_factor_limited' => false,
            'share_factor_source' => $policy['source'],
            'share_factor' => 0,
        ];
    }

    $shareFactor = $policy['factor'];

    $availableShares = (
        ((float) ($member->member_total_share ?? 0) * $shareFactor)
        - (float) ($member->member_total_loan ?? 0)
    );

    return [
        'is_valid' => $availableShares >= (float) $requestedLoanAmount,
        'max_amount' => max($availableShares, 0),
        'share_factor_limited' => true,
        'share_factor_source' => $policy['source'],
        'share_factor' => $shareFactor,
    ];
}
private function getEffectiveLoanShareFactorPolicy($loanType): array
{
    $rawLoanTypeFactor = $loanType->loan_type_share_factor ?? null;

    /*
    |--------------------------------------------------------------------------
    | Loan-type factor has first priority.
    | 0 is valid and means "no share-factor limit".
    |--------------------------------------------------------------------------
    */
    if ($rawLoanTypeFactor !== null && $rawLoanTypeFactor !== '' && is_numeric($rawLoanTypeFactor)) {
        $factor = (float) $rawLoanTypeFactor;

        if ($factor == 0.0) {
            return [
                'limited' => false,
                'factor'  => 0,
                'source'  => 'loan_type_share_factor',
            ];
        }

        return [
            'limited' => true,
            'factor'  => $factor,
            'source'  => 'loan_type_share_factor',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Only fall back to global SACCO default if loan-type factor is unavailable.
    |--------------------------------------------------------------------------
    */
    $defaultRow = DB::table('sacco_defaults')
    ->where('default_name', 'loan_factor_or_shares')
    ->first();

if (!$defaultRow) {
    DB::table('sacco_defaults')->insert([
        'default_name'   => 'loan_factor_or_shares',
        'default_value'  => 3,
        'default_userid' => auth()->id(),
        'default_ip'     => request()->ip(),
    ]);

    $defaultFactor = 3;
} else {
    $defaultFactor = $defaultRow->default_value;

    if (!is_numeric($defaultFactor) || (float) $defaultFactor <= 0) {
        DB::table('sacco_defaults')
            ->where('default_name', 'loan_factor_or_shares')
            ->update([
                'default_value'  => 3,
                'default_userid' => auth()->id(),
                'default_ip'     => request()->ip(),
            ]);

        $defaultFactor = 3;
    }
}

$defaultFactor = (float) $defaultFactor;

    

    return [
        'limited' => true,
        'factor'  => $defaultFactor,
        'source'  => 'loan_factor_or_shares',
    ];
}

    public function loans_batch_transactions_update(Request $request, $batch_id, $transaction_id)
{
    $batch = DB::table('sacco_loan_batch')
        ->where('batch_id', $batch_id)
        ->where('batch_deleted', 'N')
        ->first();

    if (!$batch) {
        return redirect()->route('loans.batches')
            ->with('error', 'Batch not found or has been deleted.');
    }

    if ($batch->batch_updated === 'Y') {
        return redirect()->route('loans.batch.transactions', $batch_id)
            ->with('error', 'This batch has already been finalized and cannot be edited.');
    }

    $transaction = DB::table('sacco_loan_batch_trans')
        ->where('batch_trans_id', $transaction_id)
        ->where('batch_trans_batch_id', $batch_id)
        ->where('batch_trans_deleted', 'N')
        ->first();

    if (!$transaction) {
        return redirect()->route('loans.batch.transactions', $batch_id)
            ->with('error', 'Transaction not found.');
    }

    $data = $request->validate([
        'batch_trans_member_id'             => 'required|string',
        'batch_trans_loan_type'             => 'required|integer',
        'batch_trans_loan_category'         => 'required|integer',
        'batch_trans_loan_amount'           => 'required|numeric|min:1.01',
        'batch_trans_loan_duration'         => 'required|integer|min:1',
        'batch_trans_doc_no'                => 'required|string|max:100',
        'batch_trans_description'           => 'nullable|string|max:100',
        'batch_trans_commission_amount'     => 'nullable|numeric|min:0',
        'batch_trans_loan_to_top_up'        => 'nullable|integer',

        'guarantors'                        => 'nullable|array',
        'guarantors.*.member'              => 'nullable|string',
        'guarantors.*.amount'              => 'nullable|numeric|min:0',
        'guarantors.*.free_shares'         => 'nullable|numeric|min:0',

        'other_charges'                     => 'nullable|array',
        'other_charges.*.deduction_type_id' => 'nullable|integer',
        'other_charges.*.amount'            => 'nullable|numeric|min:0',
    ]);

    $member = $this->getMemberFromRequest($data['batch_trans_member_id']);
    if (!$member) {
        return redirect()->back()
            ->withErrors(['batch_trans_member_id' => 'Invalid member selection or member has no shares.'])
            ->withInput();
    }

    $loanType = DB::table('sacco_loan_types')
        ->where('loan_type_id', $data['batch_trans_loan_type'])
        ->first();

    if (!$loanType) {
        return redirect()->back()
            ->withErrors(['batch_trans_loan_type' => 'Invalid loan type selected.'])
            ->withInput();
    }

    $errors = [];

    // Duplicate check, excluding current transaction
    $duplicateLoan = DB::table('sacco_loan_batch_trans')
        ->where('batch_trans_batch_id', $batch_id)
        ->where('batch_trans_loan_type', $data['batch_trans_loan_type'])
        ->where('batch_trans_member_id', $member->member_id)
        ->where('batch_trans_id', '<>', $transaction_id)
        ->where('batch_trans_deleted', '<>', 'Y')
        ->exists();

    if ($duplicateLoan) {
        $errors[] = 'This member already has a similar loan in this batch.';
    }

    // Batch limits, excluding current transaction
    $currentTransactions = DB::table('sacco_loan_batch_trans')
        ->where('batch_trans_batch_id', $batch_id)
        ->where('batch_trans_id', '<>', $transaction_id)
        ->where('batch_trans_deleted', 'N')
        ->count();

    $currentAmount = DB::table('sacco_loan_batch_trans')
        ->where('batch_trans_batch_id', $batch_id)
        ->where('batch_trans_id', '<>', $transaction_id)
        ->where('batch_trans_deleted', 'N')
        ->sum('batch_trans_loan_amount');

    if ($currentTransactions >= $batch->batch_total_trans) {
        $errors[] = 'Transaction limit exceeded for this batch.';
    }

    if (($currentAmount + (float) $data['batch_trans_loan_amount']) > (float) $batch->batch_amount) {
        $errors[] = 'Total transaction amount exceeded for this batch.';
    }

    // Top-up validation
    $loanToTopUpValidation = $this->validateLoanToTopUp(
        $member->member_id,
        $data['batch_trans_loan_to_top_up'] ?? null,
        $data['batch_trans_loan_amount']
    );

    if (!$loanToTopUpValidation['is_valid']) {
        $errors['batch_trans_loan_to_top_up'] = $loanToTopUpValidation['message'];
    }

    // Duration validation
    if (!$this->validateLoanDuration($data['batch_trans_loan_duration'], $loanType->loan_type_duration)) {
        $errors['batch_trans_loan_duration'] = 'Loan duration exceeds the maximum allowed for this loan type.';
    }

    // Max amount validation
    if (!$this->validateLoanAmount($data['batch_trans_loan_amount'], $loanType->loan_type_max_amount)) {
        $errors['batch_trans_loan_amount'] = 'Loan amount exceeds the maximum allowed for this loan type.';
    }

    // Qualification validation
    if (!$this->validateMemberEligibility($member, $loanType->loan_type_qualification_period)) {
        $errors['batch_trans_member_id'] = 'Member does not meet the qualification period for this loan type.';
    }

    // Share / exposure validation
    $shareAndLoanValidation = $this->validateShareAndLoanConstraint(
        $member,
        $loanType,
        $data['batch_trans_loan_amount']
    );

    if (!$shareAndLoanValidation['is_valid']) {
        $maxLoanAmount = $shareAndLoanValidation['max_amount'];
        $errors['batch_trans_loan_amount'] =
            'Insufficient shares or high existing loans. The maximum loan you can take is KES '
            . number_format($maxLoanAmount, 2) . '.';
    }

    // Guarantor validation
    $validatedForGuarantors = $data;
    $validatedForGuarantors['guarantors'] = $request->input('guarantors', []);

    $guarantorErrors = $this->validateGuarantors($validatedForGuarantors, $loanType, $member);
    $errors = array_merge($errors, $guarantorErrors);

    $guarantorsResult = $this->validateAndCalculateGuarantors(
        $member,
        $data['batch_trans_loan_amount'],
        $loanType,
        $request->input('guarantors', [])
    );

    if (is_array($guarantorsResult) && isset($guarantorsResult['errors'])) {
        $errors = array_merge($errors, $guarantorsResult['errors']);
    }

    // Other charges / deductions
    $otherCharges = $this->resolveLoanTransactionCharges(
        (float) $data['batch_trans_loan_amount'],
        $request->input('other_charges', [])
    );

    if (!empty($otherCharges['errors'])) {
        $errors = array_merge($errors, $otherCharges['errors']);
    }

    if (!empty($errors)) {
        return redirect()->back()->withErrors($errors)->withInput();
    }

    $guaranteedAmount = is_array($guarantorsResult) ? 0 : (float) $guarantorsResult;
    $requiredGuarantee = (float) $data['batch_trans_loan_amount'] * ((float) $loanType->loan_type_guaranteable_percent / 100);

    if (!$this->validateGuarantorsRequirement(
        $loanType->loan_type_guaranteable_percent,
        $data['batch_trans_loan_amount'],
        $guaranteedAmount
    )) {
        $missingGuaranteeAmount = number_format($requiredGuarantee - $guaranteedAmount, 2);

        return redirect()->back()
            ->withErrors([
                'guarantors' => "Insufficient guarantee amount. Additional guarantee of KES {$missingGuaranteeAmount} is needed."
            ])
            ->withInput();
    }

    $commission = (float) ($data['batch_trans_commission_amount'] ?? 0);

    // IMPORTANT:
    // requested loan amount = insurance base
    // amount_for_emi       = EMI / interest base
    $calc = $this->calculateLoanFinancialsForTransaction(
        (float) $data['batch_trans_loan_amount'],
        (float) $otherCharges['amount_for_emi'],
        (int) $data['batch_trans_loan_duration'],
        $loanType,
        $member,
        $commission
    );

    $topUpOutstanding = 0;
    if (!empty($data['batch_trans_loan_to_top_up'])) {
        $topupLoan = DB::table('sacco_loans')
            ->where('loan_id', $data['batch_trans_loan_to_top_up'])
            ->first();

        if ($topupLoan) {
            $topUpOutstanding = max(
                0,
                ((float) $topupLoan->loan_amount - (float) $topupLoan->loan_loan_paid)
            );
        }
    }

    
    DB::beginTransaction();

    try {
        DB::table('sacco_loan_batch_trans')
            ->where('batch_trans_id', $transaction_id)
            ->update([
                'batch_trans_loan_type'                 => $data['batch_trans_loan_type'],
                'batch_trans_loan_category'             => $data['batch_trans_loan_category'],
                'batch_trans_loan_amount'               => (float) $data['batch_trans_loan_amount'],
                'batch_trans_member_id'                 => $member->member_id,
                'batch_trans_loan_duration'             => (int) $data['batch_trans_loan_duration'],
                'batch_trans_monthly_payment'           => $calc['monthly_payment'],
                'batch_trans_monthly_payment_principal' => $calc['monthly_payment_principal'],
                'batch_trans_expected_interest'         => $calc['expected_interest'],
                'batch_trans_insurance'                 => $calc['insurance'],
                'batch_trans_other_deductions' => (float) ($otherCharges['total_other_deductions'] ?? 0),
                'batch_trans_doc_no'                    => $data['batch_trans_doc_no'],
                'batch_trans_description'               => $data['batch_trans_description'] ?? '',
                'batch_trans_commission'                => $commission,
                'batch_trans_loan_to_top_up'            => $data['batch_trans_loan_to_top_up'] ?? 0,
                'batch_trans_loan_to_top_up_amount'     => $topUpOutstanding,
                'batch_trans_loan_guaranteed'           => 0,
                'batch_trans_by'                        => auth()->id(),
                'batch_trans_ip'                        => $request->ip(),
            ]);

        // Soft-delete previous deduction rows
        DB::table('sacco_loan_batch_trans_deductions')
            ->where('batch_trans_deduction_batch_trans_id', $transaction_id)
            ->where('batch_trans_deduction_deleted', 'N')
            ->update([
                'batch_trans_deduction_deleted'    => 'Y',
                'batch_trans_deduction_deleted_by' => auth()->id(),
                'batch_trans_deduction_deleted_on' => now(),
                'batch_trans_deduction_deleted_ip' => $request->ip(),
            ]);

        // Insert fresh deduction rows
        foreach (($otherCharges['rows'] ?? []) as $row) {
            DB::table('sacco_loan_batch_trans_deductions')->insert([
                'batch_trans_deduction_batch_id'           => $batch_id,
                'batch_trans_deduction_batch_trans_id'     => $transaction_id,
                'batch_trans_deduction_deduction_type_id'  => $row['batch_trans_deduction_deduction_type_id'],
                'batch_trans_deduction_name'               => $row['batch_trans_deduction_name'],
                'batch_trans_deduction_code'               => $row['batch_trans_deduction_code'],
                'batch_trans_deduction_value_type'         => $row['batch_trans_deduction_value_type'],
                'batch_trans_deduction_effect'             => $row['batch_trans_deduction_effect'],
                'batch_trans_deduction_account'            => $row['batch_trans_deduction_account'],
                'batch_trans_deduction_amount'             => (float) $row['batch_trans_deduction_amount'],
                'batch_trans_deduction_description'        => $row['batch_trans_deduction_description'],
                'batch_trans_deduction_updated'            => 'N',
                'batch_trans_deduction_by'                 => auth()->id(),
                'batch_trans_deduction_ip'                 => $request->ip(),
                'batch_trans_deduction_deleted'            => 'N',
            ]);
        }

        DB::table('sacco_loan_batch_guarantors')
            ->where('guarantors_loan_batch_trans_id', $transaction_id)
            ->delete();

        $savedGuaranteed = $this->saveGuarantors(
    $request->input('guarantors', []),
    $transaction_id,
    (float) $data['batch_trans_loan_amount'],
    $loanType
);

DB::table('sacco_loan_batch_trans')
    ->where('batch_trans_id', $transaction_id)
    ->update([
        'batch_trans_loan_guaranteed' => $savedGuaranteed,
    ]);


        DB::commit();

        return redirect()
            ->route('loans.batch.transactions', $batch_id)
            ->with('success', 'Transaction updated successfully.');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Loan transaction update failed: ' . $e->getMessage());

        return redirect()->back()
            ->withInput()
            ->with('error', 'Something went wrong while updating. Please try again.');
    }
}
// private function calculateLoanFinancialsForTransaction(
//     float $requestedLoanAmount,
//     float $amountForEmi,
//     int $durationMonths,
//     $loanType,
//     $member,
//     float $commission = 0
// ): array {
//     $requestedLoanAmount = round($requestedLoanAmount, 2);
//     $amountForEmi = round($amountForEmi, 2);
//     $commission = round($commission, 2);

//     $commissionEffect = strtoupper(trim((string) ($loanType->loan_type_commission_effect ?? 'ADD_TO_LOAN')));
//     if (!in_array($commissionEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
//         $commissionEffect = 'ADD_TO_LOAN';
//     }

//     $insuranceEffect = strtoupper(trim((string) ($loanType->loan_type_insurance_effect ?? 'ADD_TO_LOAN')));
//     if (!in_array($insuranceEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
//         $insuranceEffect = 'ADD_TO_LOAN';
//     }

//     $isInsurable = strtoupper(trim((string) ($loanType->loan_type_insurable ?? 'N'))) === 'Y';

//     $calcFunction = DB::table('sacco_defaults')
//         ->where('default_name', 'loan_interest_insurance')
//         ->value('default_value');

//     if (!$calcFunction || !method_exists($this, $calcFunction)) {
//         $calcFunction = 'default_calc_loan_interest_insurance';
//     }

//     switch ($calcFunction) {
//         case 'calc_loan_interest_insurance_adom':
//             $baseInsu = ((5.03 * $durationMonths + 3.03) * $requestedLoanAmount) / 6000;
//             $baseInsu = max($baseInsu, 100);

//             $phcf = $baseInsu * 0.0025;
//             $insurance = $baseInsu + $phcf;

//             if (!$isInsurable) {
//                 $insurance = 0;
//             }

//             $commissionForEmi = ($commissionEffect === 'ADD_TO_LOAN') ? $commission : 0;
//             $insuranceForEmi = ($insuranceEffect === 'ADD_TO_LOAN') ? $insurance : 0;
//             $loanPlusExtras = $amountForEmi + $commissionForEmi + $insuranceForEmi;

//             if (strtoupper($loanType->loan_type_interest_type ?? '') === 'FIXED INTEREST') {
//                 $expectedInterest = round($loanPlusExtras * ((float) $loanType->loan_type_interest / 100), 0);
//                 $monthlyPayment = ceil(($loanPlusExtras + $expectedInterest) / $durationMonths);
//                 $monthlyPrincipal = $loanPlusExtras / $durationMonths;
//             } else {
//                 $rate = ((float) $loanType->loan_type_interest) / 12 / 100;

//                 if ($rate > 0) {
//                     $monthlyPayment = ($loanPlusExtras * $rate) * pow(1 + $rate, $durationMonths) / (pow(1 + $rate, $durationMonths) - 1);
//                 } else {
//                     $monthlyPayment = $loanPlusExtras / $durationMonths;
//                 }

//                 $expectedInterest = ($monthlyPayment * $durationMonths) - $loanPlusExtras;
//                 $monthlyPrincipal = $monthlyPayment - ($loanPlusExtras * $rate);

//                 $monthlyPayment = ceil($monthlyPayment);
//                 $monthlyPrincipal = ceil($monthlyPrincipal);
//             }

//             return [
//                 'monthly_payment'           => round($monthlyPayment, 2),
//                 'monthly_payment_principal' => round($monthlyPrincipal, 2),
//                 'expected_interest'         => round($expectedInterest, 2),
//                 'insurance'                 => round($insurance, 2),
//             ];

//         case 'calc_loan_interest_insurance_yes':
//             $annualRate = (float) $loanType->loan_type_interest;
//             $monthlyRate = $annualRate / 12 / 100;

//             $insurance = $isInsurable
//                 ? round($requestedLoanAmount * 0.01, 2)
//                 : 0.0;

//             $commissionForEmi = ($commissionEffect === 'ADD_TO_LOAN') ? $commission : 0;
//             $insuranceForEmi = ($insuranceEffect === 'ADD_TO_LOAN') ? $insurance : 0;
//             $loanPlusExtras = $amountForEmi + $commissionForEmi + $insuranceForEmi;

//             if ($monthlyRate > 0) {
//                 $monthlyPayment = $loanPlusExtras * $monthlyRate * pow(1 + $monthlyRate, $durationMonths)
//                     / (pow(1 + $monthlyRate, $durationMonths) - 1);
//             } else {
//                 $monthlyPayment = $loanPlusExtras / $durationMonths;
//             }

//             $expectedInterest = ($monthlyPayment * $durationMonths) - $loanPlusExtras;

//             return [
//                 'monthly_payment'           => round($monthlyPayment, 2),
//                 'monthly_payment_principal' => round($amountForEmi / $durationMonths, 2),
//                 'expected_interest'         => round($expectedInterest, 2),
//                 'insurance'                 => round($insurance, 2),
//             ];

//         case 'calc_loan_interest_insurance_dhl':
//             $interestType = strtoupper(trim($loanType->loan_type_interest_type ?? 'FIXED INTEREST'));
//             $annualRate   = (float) ($loanType->loan_type_interest ?? 0);

//             $insurance = $isInsurable
//                 ? round($requestedLoanAmount * 0.01, 2)
//                 : 0.0;

//             $commissionForEmi = ($commissionEffect === 'ADD_TO_LOAN') ? $commission : 0;
//             $insuranceForEmi = ($insuranceEffect === 'ADD_TO_LOAN') ? $insurance : 0;
//             $loanAmountWithInsu = $amountForEmi + $commissionForEmi + $insuranceForEmi;

//             if ($interestType === 'FIXED INTEREST') {
//                 $expectedInterest = round($loanAmountWithInsu * $annualRate / 100, 2);
//                 $monthlyPayment = ceil(($loanAmountWithInsu + $expectedInterest) / $durationMonths);
//                 $monthlyPrincipal = ceil($loanAmountWithInsu / $durationMonths);
//             } else {
//                 $interestPercent = $annualRate / 12 / 100;
//                 $monthlyPayment = ($loanAmountWithInsu / $durationMonths) + ($loanAmountWithInsu * $interestPercent);
//                 $monthlyPayment = ceil($monthlyPayment);

//                 $expectedInterest = $loanAmountWithInsu * $interestPercent;
//                 $monthlyPrincipal = $monthlyPayment - ($loanAmountWithInsu * $interestPercent);
//                 $monthlyPrincipal = ceil($monthlyPrincipal);
//             }

//             return [
//                 'monthly_payment'           => round($monthlyPayment, 2),
//                 'monthly_payment_principal' => round($monthlyPrincipal, 2),
//                 'expected_interest'         => round($expectedInterest, 2),
//                 'insurance'                 => round($insurance, 2),
//             ];

//         case 'default_calc_loan_interest_insurance':
//         default:
//             $interestType = strtoupper(trim($loanType->loan_type_interest_type ?? 'REDUCING BALANCE'));
//             $annualRate = (float) $loanType->loan_type_interest;
//             $monthlyRate = $annualRate / 12 / 100;

//             $insurance = $isInsurable
//                 ? round($requestedLoanAmount * 0.01, 2)
//                 : 0.0;

//             $commissionForEmi = ($commissionEffect === 'ADD_TO_LOAN') ? $commission : 0;
//             $insuranceForEmi = ($insuranceEffect === 'ADD_TO_LOAN') ? $insurance : 0;
//             $loanPlusExtras = $amountForEmi + $commissionForEmi + $insuranceForEmi;

//             $monthlyPayment = 0;
//             $expectedInterest = 0;

//             switch ($interestType) {
//                 case 'FIXED INTEREST':
//                     $expectedInterest = ($amountForEmi * $annualRate * ($durationMonths / 12)) / 100;
//                     $totalPayable = $loanPlusExtras + $expectedInterest;
//                     $monthlyPayment = $totalPayable / $durationMonths;
//                     break;

//                 case 'COMPOUND INTEREST':
//                     $totalPayable = $loanPlusExtras * pow(1 + $monthlyRate, $durationMonths);
//                     $expectedInterest = $totalPayable - $loanPlusExtras;
//                     $monthlyPayment = $totalPayable / $durationMonths;
//                     break;

//                 case 'REDUCING BALANCE':
//                 default:
//                     if ($monthlyRate > 0) {
//                         $monthlyPayment = $loanPlusExtras * $monthlyRate * pow(1 + $monthlyRate, $durationMonths)
//                             / (pow(1 + $monthlyRate, $durationMonths) - 1);
//                     } else {
//                         $monthlyPayment = $loanPlusExtras / $durationMonths;
//                     }
//                     $expectedInterest = ($monthlyPayment * $durationMonths) - $loanPlusExtras;
//                     break;
//             }

//             return [
//                 'monthly_payment'           => round($monthlyPayment, 2),
//                 'monthly_payment_principal' => round($amountForEmi / $durationMonths, 2),
//                 'expected_interest'         => round($expectedInterest, 2),
//                 'insurance'                 => round($insurance, 2),
//             ];
//     }
// }
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

   

$ledgerErrors = $this->validateBatchLedgerAccounts($batchId);

if (!empty($ledgerErrors)) {
    return redirect()->route('loans.batches')->withErrors($ledgerErrors);
}

    DB::beginTransaction();

    try {
        $transactions = DB::table('sacco_loan_batch_trans')
            ->where('batch_trans_batch_id', $batchId)
            ->where('batch_trans_deleted', 'N')
            ->get();

        // Retrieve default accounts
        $default_bank_account       = $this->getDefaultAccount('default_bank_account');
        $default_insurance_account  = $this->getDefaultAccount('default_insurance_account');
        $default_commission_account = $this->getDefaultAccount('default_loan_commission_account');
        $default_loan_account       = $this->getDefaultAccount('default_loan_account');

        if (!$default_bank_account || !$default_insurance_account || !$default_commission_account || !$default_loan_account) {
            DB::rollBack();
            return back()->withErrors([
                'defaults' => 'One or more required default accounts are missing. Please configure all default accounts before proceeding.',
            ]);
        }

        foreach ($transactions as $transaction) {
            // Retrieve specific accounts from sacco_loan_types
            $loanType = DB::table('sacco_loan_types')
                ->where('loan_type_id', $transaction->batch_trans_loan_type)
                ->first();

            if (!$loanType) {
                DB::rollBack();
                return redirect()->route('loans.batches')->withErrors([
                    'error' => 'Loan type missing for one of the transactions.'
                ]);
            }

            $loan_account       = $loanType->loan_type_acount ?? $default_loan_account;
            $commission_account = $loanType->loan_type_comm_account ?? $default_commission_account;
            $insurance_account  = $default_insurance_account;
            $bank_account       = $batch->batch_credit_account ?? $default_bank_account;

            if (!$loan_account || !$commission_account || !$insurance_account || !$bank_account) {
                DB::rollBack();
                return redirect()->route('loans.batches')->withErrors([
                    'error' => 'Missing required accounts for processing the batch.'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Pull batch deductions / additions snapshot
            |--------------------------------------------------------------------------
            */
            $batchDeductions = DB::table('sacco_loan_batch_trans_deductions')
                ->where('batch_trans_deduction_batch_trans_id', $transaction->batch_trans_id)
                ->where('batch_trans_deduction_deleted', 'N')
                ->orderBy('batch_trans_deduction_id', 'asc')
                ->get();

            $otherAdditions = round(
                (float) $batchDeductions
                    ->where('batch_trans_deduction_effect', 'ADD_TO_LOAN')
                    ->sum('batch_trans_deduction_amount'),
                2
            );

            $otherDeductions = round(
                (float) $batchDeductions
                    ->where('batch_trans_deduction_effect', 'DEDUCT_FROM_DISBURSEMENT')
                    ->sum('batch_trans_deduction_amount'),
                2
            );

            $chargesSnapshot = $batchDeductions
                ->pluck('batch_trans_deduction_description')
                ->filter()
                ->implode(' | ');
            

                $member = DB::table('sacco_members')
    ->where('member_id', $transaction->batch_trans_member_id)
    ->select('member_id', 'member_name')
    ->first();

$memberLabel = $member
    ? ($member->member_id . ' - ' . trim((string) $member->member_name))
    : ('member ' . $transaction->batch_trans_member_id);

$loanDescription = trim((string) $transaction->batch_trans_description);

if ($chargesSnapshot !== '') {
    $loanDescription = $loanDescription !== ''
        ? $loanDescription . ' | ' . $chargesSnapshot
        : $chargesSnapshot;
}


            /*
            |--------------------------------------------------------------------------
            | Determine what becomes part of live loan amount
            |--------------------------------------------------------------------------
            | Backward compatibility rule:
            | loan_amount in live loans table should now carry the financed loan burden:
            | requested loan + insurance + all other additions that were added to loan
            |
            | Deductions at disbursement do NOT increase loan amount.
            | Guarantors still guarantee only the requested loan amount as captured in batch rows.
            */
            $requestedAmount = round((float) $transaction->batch_trans_loan_amount, 2);
            $insurance       = round((float) ($transaction->batch_trans_insurance ?? 0), 2);
            $commission      = round((float) ($transaction->batch_trans_commission ?? 0), 2);
            $topUpAmount     = round((float) ($transaction->batch_trans_loan_to_top_up_amount ?? 0), 2);

            $commissionEffect = strtoupper(trim((string) ($loanType->loan_type_commission_effect ?? 'ADD_TO_LOAN')));
            if (!in_array($commissionEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'])) {
                $commissionEffect = 'ADD_TO_LOAN';
            }

            $insuranceEffect = strtoupper(trim((string) ($loanType->loan_type_insurance_effect ?? 'ADD_TO_LOAN')));
            if (!in_array($insuranceEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'])) {
                $insuranceEffect = 'ADD_TO_LOAN';
            }

            $commissionAddedToLoan = $commissionEffect === 'ADD_TO_LOAN' ? $commission : 0;
            $commissionDeducted    = $commissionEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $commission : 0;

            $insuranceAddedToLoan = $insuranceEffect === 'ADD_TO_LOAN' ? $insurance : 0;
            $insuranceDeducted    = $insuranceEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $insurance : 0;

            $liveLoanAmount = round(
                $requestedAmount
                + $otherAdditions
                + $commissionAddedToLoan
                + $insuranceAddedToLoan,
                2
            );

            $netDisbursement = round(
                $requestedAmount
                - $otherDeductions
                - $commissionDeducted
                - $insuranceDeducted
                - $topUpAmount,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Guarantors from batch
            |--------------------------------------------------------------------------
            */
            $batchGuarantors = DB::table('sacco_loan_batch_guarantors')
                ->where('guarantors_loan_batch_trans_id', $transaction->batch_trans_id)
                ->where('guarantors_deleted', 'N')
                ->get();

            $loanAmountGuaranteed = round(
                (float) $batchGuarantors->sum('guarantors_amount_guaranteed'),
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Insert live loan
            |--------------------------------------------------------------------------
            */
            try {
                $loanId = DB::table('sacco_loans')->insertGetId([
                    'loan_member'                      => $transaction->batch_trans_member_id,
                    'loan_loan_type'                   => $transaction->batch_trans_loan_type,
                    'loan_loan_category'               => $transaction->batch_trans_loan_category,

                    'loan_requested_amount'            => $requestedAmount,
                    'loan_amount'                      => $liveLoanAmount,
                    'loan_insurance'                   => $insurance,
                    'loan_commision'                   => $commission,
                    'loan_other_additions'             => $otherAdditions,
                    'loan_other_deductions'            => $otherDeductions,
                    'loan_net_disbursement'            => $netDisbursement,

                    'loan_taken_period'                => $this->currentPeriod->period_name,
                    'loan_payment_period'              => $transaction->batch_trans_loan_duration,
                    'loan_interest_payable'            => $transaction->batch_trans_expected_interest,
                    'loan_monthly_repayment_amount'    => $transaction->batch_trans_monthly_payment,
                    'loan_monthly_repayment_principal' => $transaction->batch_trans_monthly_payment_principal,
                    'loan_amount_guaranteed'           => $loanAmountGuaranteed,
                    'loan_loan_paid'                   => 0,

                    'loan_doc_no'                      => $transaction->batch_trans_doc_no,
                    'loan_description'                 => $loanDescription,
                    'loan_charges_snapshot'            => $chargesSnapshot,

                    'loan_batch_no'                    => (string) $batch->batch_id,
                    'loan_batch_trans_id'              => $transaction->batch_trans_id,
                    'loan_start_deduction_period'      => $this->currentPeriod->period_name,
                    'loan_old_loan_id'                 => !empty($transaction->batch_trans_loan_to_top_up)
                        ? $transaction->batch_trans_loan_to_top_up
                        : null,

                    // kept for backward compatibility
                    'loan_account_credited'            => $bank_account,
                    'loan_account_debited'             => $loan_account,

                    'loan_by'                          => auth()->id(),
                    'loan_ip'                          => request()->ip(),
                    'loan_taken_start_period'          => $this->currentPeriod->period_name,
                ]);

                    $this->transferBatchDeductionsToLoan($loanId, $batchId, $transaction->batch_trans_id);

                Log::info('Loan ID created successfully: ' . $loanId);
            } catch (\Exception $e) {
                Log::error('Loan insertion failed: ' . $e->getMessage());
                DB::rollBack();
                return redirect()->route('loans.batches')->with('error', 'Batch finalization failed: ' . $e->getMessage());
            }

            /*
            |--------------------------------------------------------------------------
            | Transfer guarantors exactly as captured in batch
            |--------------------------------------------------------------------------
            | No re-proration here. No additions/deductions effect here.
            */
            foreach ($batchGuarantors as $guarantor) {
                DB::table('sacco_loan_guarantors')->insert([
                    'loan_guar_loan_id'            => $loanId,
                    'loan_guar_guarantor_id'       => $guarantor->guarantors_guarantor_id,
                    'loan_guar_amount_guaranteed'  => $guarantor->guarantors_amount_guaranteed,
                    'loan_guar_description'        => $guarantor->guarantors_description ?? 'Imported from batch ID ' . $batchId,
                    'loan_guar_transfered'         => 'Y',
                    'loan_guar_by'                 => auth()->id(),
                    'loan_guar_on'                 => now(),
                    'loan_guar_ip'                 => request()->ip(),
                    'loan_guar_deleted'            => 'N',
                ]);

                if ($guarantor->guarantors_guarantor_id == $transaction->batch_trans_member_id) {
                    DB::table('sacco_members')
                        ->where('member_id', $guarantor->guarantors_guarantor_id)
                        ->increment('member_tied_shares_self', $guarantor->guarantors_amount_guaranteed);
                } else {
                    DB::table('sacco_members')
                        ->where('member_id', $guarantor->guarantors_guarantor_id)
                        ->increment('member_tied_shares', $guarantor->guarantors_amount_guaranteed);
                }
            }

            // mark batch guarantors as transferred
            DB::table('sacco_loan_batch_guarantors')
                ->where('guarantors_loan_batch_trans_id', $transaction->batch_trans_id)
                ->where('guarantors_deleted', 'N')
                ->update([
                    'guarantors_transfered' => 'Y',
                ]);

            /*
            |--------------------------------------------------------------------------
            | Existing top-up logic kept as is
            |--------------------------------------------------------------------------
            */
            if ($transaction->batch_trans_loan_to_top_up > 0) {
                $this->processLoanTopUp($transaction);
            }

            /*
            |--------------------------------------------------------------------------
            | Update member total loan using live loan amount
            |--------------------------------------------------------------------------
            */
            DB::table('sacco_members')
                ->where('member_id', $transaction->batch_trans_member_id)
                ->increment('member_total_loan', $liveLoanAmount);

            /*
            |--------------------------------------------------------------------------
            | Ledger updates kept as they are now
            |--------------------------------------------------------------------------
            */
            // ----------------- LEDGER UPDATES -----------------
// IMPORTANT:
// - Do NOT subtract top-up here.
//   Top-up is handled separately by processLoanTopUp(), which already posts its own entries.
// - Only post non-zero amounts.
// - If a non-zero item has no account, throw an error before posting anything.

$principal  = round((float) $transaction->batch_trans_loan_amount, 2);
$commission = round((float) ($transaction->batch_trans_commission ?? 0), 2);
$insurance  = round((float) ($transaction->batch_trans_insurance ?? 0), 2);

// Pull transaction deductions/additions from batch rows
$chargeRows = DB::table('sacco_loan_batch_trans_deductions')
    ->where('batch_trans_deduction_batch_trans_id', $transaction->batch_trans_id)
    ->where('batch_trans_deduction_deleted', 'N')
    ->orderBy('batch_trans_deduction_id', 'asc')
    ->get();

$totalAddToLoan = round(
    (float) $chargeRows
        ->where('batch_trans_deduction_effect', 'ADD_TO_LOAN')
        ->sum('batch_trans_deduction_amount'),
    2
);

$totalDeductFromDisbursement = round(
    (float) $chargeRows
        ->where('batch_trans_deduction_effect', 'DEDUCT_FROM_DISBURSEMENT')
        ->sum('batch_trans_deduction_amount'),
    2
);

// Respect configured effects for commission and insurance
$commissionEffect = strtoupper(trim((string) ($loanType->loan_type_commission_effect ?? 'ADD_TO_LOAN')));
if (!in_array($commissionEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
    $commissionEffect = 'ADD_TO_LOAN';
}

$insuranceEffect = strtoupper(trim((string) ($loanType->loan_type_insurance_effect ?? 'ADD_TO_LOAN')));
if (!in_array($insuranceEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
    $insuranceEffect = 'ADD_TO_LOAN';
}

$commissionAddedToLoan = $commissionEffect === 'ADD_TO_LOAN' ? $commission : 0;
$commissionDeducted    = $commissionEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $commission : 0;

$insuranceAddedToLoan = $insuranceEffect === 'ADD_TO_LOAN' ? $insurance : 0;
$insuranceDeducted    = $insuranceEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $insurance : 0;

// 1. Loan receivable debit = everything financed onto the loan
$loanDebit = round(
    $principal
    + $totalAddToLoan
    + $commissionAddedToLoan
    + $insuranceAddedToLoan,
    2
);

// 2. Bank credit = actual cash disbursed out now
//    NOTE: top-up is NOT deducted here because processLoanTopUp() handles that separately.
$bankCredit = round(
    $principal
    - $totalDeductFromDisbursement
    - $commissionDeducted
    - $insuranceDeducted,
    2
);

$postings = [];

// --- LOAN ACCOUNT (DR)
if ($loanDebit > 0) {
    if (empty($loan_account)) {
        throw new \Exception('Loan account missing for transaction #' . $transaction->batch_trans_id);
    }

    $postings[] = [
        'account' => $loan_account,
        'debit'   => $loanDebit,
        'credit'  => 0,
        'doc_no'  => $transaction->batch_trans_doc_no,
        'desc'    => 'Loan principal plus financed charges for member ' . $memberLabel
    . ($chargesSnapshot !== '' ? ' | ' . $chargesSnapshot : ''),

        'source'  => 'Loan Disbursement',
    ];
}

// --- BANK ACCOUNT (CR)
if ($bankCredit > 0) {
    if (empty($bank_account)) {
        throw new \Exception('Bank account missing for transaction #' . $transaction->batch_trans_id);
    }

    $postings[] = [
        'account' => $bank_account,
        'debit'   => 0,
        'credit'  => $bankCredit,
        'doc_no'  => $transaction->batch_trans_doc_no,
        'desc'    => 'Net loan disbursement to member ' . $memberLabel
    . ($chargesSnapshot !== '' ? ' | ' . $chargesSnapshot : ''),
        'source'  => 'Loan Disbursement',
    ];
}

// --- COMMISSION ACCOUNT (CR)
if ($commission > 0) {
    if (empty($commission_account)) {
        throw new \Exception('Commission account missing for transaction #' . $transaction->batch_trans_id);
    }

    $postings[] = [
        'account' => $commission_account,
        'debit'   => 0,
        'credit'  => $commission,
        'doc_no'  => $transaction->batch_trans_doc_no,
        'desc'    => 'Loan commission for member ' . $memberLabel,
        'source'  => 'Loan Commission Fee',
    ];
}

// --- INSURANCE ACCOUNT (CR)
if ($insurance > 0) {
    if (empty($insurance_account)) {
        throw new \Exception('Insurance account missing for transaction #' . $transaction->batch_trans_id);
    }

    $postings[] = [
        'account' => $insurance_account,
        'debit'   => 0,
        'credit'  => $insurance,
        'doc_no'  => $transaction->batch_trans_doc_no,
        'desc'    => 'Loan insurance for member ' . $memberLabel,
        'source'  => 'Loan Insurance',
    ];
}

// --- OTHER CHARGES / DEDUCTIONS ACCOUNTS (CR)
foreach ($chargeRows as $charge) {
    $chargeAmount = round((float) ($charge->batch_trans_deduction_amount ?? 0), 2);

    if ($chargeAmount <= 0) {
        continue;
    }

    $chargeAccount = $charge->batch_trans_deduction_account;
    $chargeName    = trim((string) ($charge->batch_trans_deduction_name ?? 'Loan Charge'));
    $chargeEffect  = strtoupper(trim((string) ($charge->batch_trans_deduction_effect ?? '')));

    if (empty($chargeAccount)) {
        throw new \Exception(
            'Ledger account missing for charge "' . $chargeName . '" in transaction #' . $transaction->batch_trans_id
        );
    }

   $effectLabel = $chargeEffect === 'ADD_TO_LOAN'
    ? 'financed on loan'
    : 'deducted at disbursement';

$chargeDescription = trim((string) ($charge->batch_trans_deduction_description ?? ''));

if ($chargeDescription === '') {
    $chargeDescription = $chargeName . ' (' . $effectLabel . ')';
}

$postings[] = [
    'account' => $chargeAccount,
    'debit'   => 0,
    'credit'  => $chargeAmount,
    'doc_no'  => $transaction->batch_trans_doc_no,
    'desc'    => $chargeDescription . ' | Member: ' . $memberLabel,
    'source'  => 'Loan Charge',
];
}

// Final balance check before posting
$totalDebitPosted = round(array_sum(array_column($postings, 'debit')), 2);
$totalCreditPosted = round(array_sum(array_column($postings, 'credit')), 2);

if ($totalDebitPosted !== $totalCreditPosted) {
    throw new \Exception(
        'Loan ledger not balanced for transaction #' . $transaction->batch_trans_id .
        '. Debits: ' . number_format($totalDebitPosted, 2) .
        ', Credits: ' . number_format($totalCreditPosted, 2)
    );
}

// Post entries
foreach ($postings as $entry) {
    $this->updateSaccoAccountsTrans(
        $entry['account'],
        $entry['debit'],
        $entry['credit'],
        $entry['doc_no'],
        $entry['desc'],
        now(),
        $this->currentPeriod->period_name,
        $entry['source']
    );
}
        }

        DB::table('sacco_loan_batch')
            ->where('batch_id', $batchId)
            ->update(['batch_updated' => 'Y']);

        DB::commit();

        return redirect()->route('loans.batches')->with('success', 'Batch finalized and transferred successfully.');
    } catch (\Exception $e) {
        DB::rollBack();
        return redirect()->route('loans.batches')->with('error', 'Batch finalization failed: ' . $e->getMessage());
    }
}

    private function validateBatchLedgerAccounts($batchId): array
{
    $errors = [];

    $batch = DB::table('sacco_loan_batch')
        ->where('batch_id', $batchId)
        ->where('batch_deleted', 'N')
        ->first();

    if (!$batch) {
        return ['Loan batch not found or has been deleted.'];
    }

    if (empty($batch->batch_credit_account)) {
        $errors[] = "Batch {$batch->batch_reference} has no bank / credit account selected.";
    }

    $defaultLoanAccount       = $this->getDefaultAccount('default_loan_account');
    $defaultCommissionAccount = $this->getDefaultAccount('default_loan_commission_account');
    $defaultInsuranceAccount  = $this->getDefaultAccount('default_insurance_account');

    $transactions = DB::table('sacco_loan_batch_trans as t')
        ->join('sacco_members as m', 't.batch_trans_member_id', '=', 'm.member_id')
        ->leftJoin('sacco_loan_types as lt', 't.batch_trans_loan_type', '=', 'lt.loan_type_id')
        ->where('t.batch_trans_batch_id', $batchId)
        ->where('t.batch_trans_deleted', 'N')
        ->select(
            't.*',
            'm.member_name',
            'm.member_sacco_id',
            'lt.loan_type_name',
            'lt.loan_type_acount',
            'lt.loan_type_comm_account',
            'lt.loan_type_int_account'
        )
        ->get();

    foreach ($transactions as $transaction) {
        $memberLabel = $transaction->member_name . ' (' . $transaction->member_sacco_id . ')';

        $loanAccount = $transaction->loan_type_acount ?: $defaultLoanAccount;
        if (empty($loanAccount)) {
            $errors[] = "Missing loan ledger account for {$memberLabel} under loan type {$transaction->loan_type_name}.";
        }

        if ((float) $transaction->batch_trans_commission > 0) {
            $commissionAccount = $transaction->loan_type_comm_account ?: $defaultCommissionAccount;

            if (empty($commissionAccount)) {
                $errors[] = "Missing commission ledger account for {$memberLabel} under loan type {$transaction->loan_type_name}.";
            }
        }

        if ((float) $transaction->batch_trans_insurance > 0) {
            $insuranceAccount = $defaultInsuranceAccount;

            if (empty($insuranceAccount)) {
                $errors[] = "Missing insurance ledger account for {$memberLabel} under loan type {$transaction->loan_type_name}.";
            }
        }

        $deductions = DB::table('sacco_loan_batch_trans_deductions')
            ->where('batch_trans_deduction_batch_trans_id', $transaction->batch_trans_id)
            ->where('batch_trans_deduction_deleted', 'N')
            ->where('batch_trans_deduction_amount', '>', 0)
            ->get();

        foreach ($deductions as $deduction) {
            if (empty($deduction->batch_trans_deduction_account)) {
                $errors[] = "Missing deduction ledger account for {$deduction->batch_trans_deduction_name} on {$memberLabel}, Doc No: {$transaction->batch_trans_doc_no}.";
            }
        }
    }

    return array_values(array_unique($errors));
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
    // Fetch the existing loan record
    $old = DB::table('sacco_loans')
        ->select('loan_id','loan_amount','loan_loan_paid','loan_loan_type','loan_member')
        ->where('loan_id', $transaction->batch_trans_loan_to_top_up)
        ->first();

    if (!$old) {
        // No old loan found
        return;
    }

    // Normalize nulls
    $loanAmount = (float) ($old->loan_amount ?? 0);
    $loanPaid   = (float) ($old->loan_loan_paid ?? 0);
    $balance    = max(0, $loanAmount - $loanPaid);

    if ($balance <= 0) {
        // Already cleared or invalid
        return;
    }

    // Mark the old loan as paid
    DB::table('sacco_loans')
        ->where('loan_id', $old->loan_id)
        ->increment('loan_loan_paid', $balance);

    // Optional: close the old loan
    DB::table('sacco_loans')
        ->where('loan_id', $old->loan_id)
        ->update([
            'loan_stoped'    => 'Y',
            'loan_stoped_on' => now(),
        ]);

    // Record the internal payment for audit
    DB::table('sacco_loan_payments')->insert([
        'loan_payments_amount'      => $balance,
        'loan_payments_description' => 'Top-up clearance for Loan #' . $old->loan_id,
        'loan_payments_docno'       => $transaction->batch_trans_doc_no,
        'loan_payments_paid_in_by'  => 'TOP-UP ' . $transaction->batch_trans_doc_no,
        'loan_payments_period'      => $this->currentPeriod->period_name,
        'loan_payments_paid_on'     => now(),
        'loan_payments_loan_id'     => $old->loan_id,
        'loan_payments_interest'    => 0,
        'loan_payments_by'          => auth()->id(),
        'loan_payments_ip'          => request()->ip(),
    ]);

    // Reduce member’s total loan balance
    DB::table('sacco_members')
        ->where('member_id', $transaction->batch_trans_member_id)
        ->decrement('member_total_loan', $balance);

    // Release guarantors’ tied shares
    $guarantors = DB::table('sacco_loan_guarantors')
        ->where('loan_guar_loan_id', $old->loan_id)
        ->where('loan_guar_deleted', 'N')
        ->get();

    foreach ($guarantors as $g) {
        $toFree = max(0, $g->loan_guar_amount_guaranteed - $g->loan_guar_amount_freed);

        if ($toFree > 0) {
            DB::table('sacco_members')
                ->where('member_id', $g->loan_guar_guarantor_id)
                ->decrement(
                    ($transaction->batch_trans_member_id == $g->loan_guar_guarantor_id)
                        ? 'member_tied_shares_self'
                        : 'member_tied_shares',
                    $toFree
                );

            DB::table('sacco_loan_guarantors')
                ->where('loan_guar_loan_id', $old->loan_id)
                ->where('loan_guar_guarantor_id', $g->loan_guar_guarantor_id)
                ->update(['loan_guar_deleted' => 'Y']);
        }
    }

    // Mark all amounts as freed for audit
    DB::table('sacco_loan_guarantors')
        ->where('loan_guar_loan_id', $old->loan_id)
        ->update(['loan_guar_amount_freed' => DB::raw('loan_guar_amount_guaranteed')]);

    // Ledger Entries (internal settlement: DR Bank, CR Loan)
    $loan_account = DB::table('sacco_loan_types')
        ->where('loan_type_id', $old->loan_loan_type)
        ->value('loan_type_acount') ?? $this->getDefaultAccount('default_loan_account');

    $bank_account = $transaction->batch_credit_account ?? $this->getDefaultAccount('default_bank_account');

    // Debit Bank (clearing)
    $this->updateSaccoAccountsTrans(
        $bank_account,
        $balance, // DR
        0,
        $transaction->batch_trans_doc_no,
        'Top-up clearance (DR Bank) for Loan #'.$old->loan_id,
        now(),
        $this->currentPeriod->period_name,
        'Loan Top-up Clearance'
    );

    // Credit Loan Receivable
    $this->updateSaccoAccountsTrans(
        $loan_account,
        0,
        $balance, // CR
        $transaction->batch_trans_doc_no,
        'Old Loan cleared by Top-up (CR Loan) #'.$old->loan_id,
        now(),
        $this->currentPeriod->period_name,
        'Loan Top-up Clearance'
    );
}
    // private function processLoanTopUp($transaction)
    // {
    //     // Fetch the full remaining balance on the existing loan
    //     $existingLoan = DB::table('sacco_loans')
    //         ->where('loan_id', $transaction->batch_trans_loan_to_top_up)
    //         ->value('loan_amount');

    //     // Reduce the outstanding loan balance by the entire existing loan amount
    //     DB::table('sacco_loans')
    //         ->where('loan_id', $transaction->batch_trans_loan_to_top_up)
    //         ->decrement('loan_loan_paid', $existingLoan);

    //     // Prepare top-up payment details
    //     $paidBy = "LOAN CLEARANCE - LNo." . $transaction->batch_trans_loan_to_top_up;

    //     // Insert top-up payment record as a reduction in the outstanding balance
    //     DB::table('sacco_loan_payments')->insert([
    //         'loan_payments_amount' => $existingLoan,
    //         'loan_payments_description' => $transaction->batch_trans_description,
    //         'loan_payments_docno' => $transaction->batch_trans_doc_no,
    //         'loan_payments_paid_in_by' => $paidBy,
    //         'loan_payments_period' => $this->currentPeriod->period_name,
    //         'loan_payments_paid_on' => now(),
    //         'loan_payments_loan_id' => $transaction->batch_trans_loan_to_top_up,
    //         'loan_payments_interest' => 0,
    //         'loan_payments_by' => auth()->id(),
    //         'loan_payments_ip' => request()->ip()
    //     ]);

    //     // Adjust member's total loan balance
    //     DB::table('sacco_members')
    //         ->where('member_id', $transaction->batch_trans_member_id)
    //         ->decrement('member_total_loan', $existingLoan);

    //     // Adjust guarantor obligations if any
    //     $guarantors = DB::table('sacco_loan_guarantors')
    //         ->where('loan_guar_loan_id', $transaction->batch_trans_loan_to_top_up)
    //         ->where('loan_guar_deleted', 'N')
    //         ->get();

    //     foreach ($guarantors as $guarantor) {
    //         $guaranteedAmount = $guarantor->loan_guar_amount_guaranteed - $guarantor->loan_guar_amount_freed;

    //         if ($transaction->batch_trans_member_id == $guarantor->loan_guar_guarantor_id) {

    //             DB::table('sacco_members')
    //             ->where('member_id', $guarantor->loan_guar_guarantor_id)
    //             ->decrement('member_tied_shares_self', $guaranteedAmount);


    //         }

    //        else  {
    //         DB::table('sacco_members')
    //         ->where('member_id', $guarantor->loan_guar_guarantor_id)
    //         ->decrement('member_tied_shares', $guaranteedAmount);


    //         }

    //         DB::table('sacco_loan_guarantors')
    //         ->where('loan_guar_loan_id', $transaction->batch_trans_loan_to_top_up)
    //         ->where('loan_guar_guarantor_id', $guarantor->loan_guar_guarantor_id)
    //         ->update(['loan_guar_deleted' => 'Y']);
    //     }


    //     // Ledger Entries for Loan Clearance

    //     // Credit the loan account to reflect the cleared balance
    //     $this->updateSaccoAccountsTrans(
    //         $transaction->batch_trans_loan_type,
    //         0,
    //         $existingLoan,
    //         $transaction->batch_trans_doc_no,
    //         'Loan Clearance for Member ID: ' . $transaction->batch_trans_member_id,
    //         now(),
    //         $this->currentPeriod->period_name,
    //         'Loan Clearance'
    //     );

    //     // Debit the bank account to reflect the outflow of the clearance amount
    //     $this->updateSaccoAccountsTrans(
    //         $transaction->batch_credit_account ?? $this->getDefaultAccount('default_bank_account'),
    //         $existingLoan,
    //         0,
    //         $transaction->batch_trans_doc_no,
    //         'Loan Clearance for Member ID: ' . $transaction->batch_trans_member_id,
    //         now(),
    //         $this->currentPeriod->period_name,
    //         'Loan Clearance'
    //     );
    // }
    public function loans_batch_transactions_add(Request $request, $batch_id)
{
    $batch = DB::table('sacco_loan_batch')
        ->where('batch_id', $batch_id)
        ->where('batch_deleted', 'N')
        ->first();

    if (!$batch) {
        return redirect()->route('loans.batches')
            ->with('error', ['Batch not found or has been deleted.']);
    }

    $validated = $request->validate([
        'batch_trans_loan_type'            => 'required|integer',
        'batch_trans_loan_category'        => 'required|integer',
        'batch_trans_loan_amount'          => 'required|numeric|min:1.01',
        'batch_trans_member_id'            => 'required|string',
        'batch_trans_loan_duration'        => 'required|integer|min:1',
        'batch_trans_doc_no'               => 'required|string|max:100',
        'batch_trans_description'          => 'nullable|string|max:100',
        'batch_trans_commission_amount'    => 'nullable|numeric|min:0',
        'batch_trans_loan_to_top_up'       => 'nullable|integer',

        'guarantors'                       => 'nullable|array',
        'guarantors.*.member'             => 'nullable|string',
        'guarantors.*.amount'             => 'nullable|numeric|min:0',
        'guarantors.*.free_shares'        => 'nullable|numeric|min:0',

        'other_charges'                    => 'nullable|array',
        'other_charges.*.deduction_type_id'=> 'nullable|integer',
        'other_charges.*.amount'           => 'nullable|numeric|min:0',
    ]);

    $rawInput = $validated['batch_trans_member_id'];
    $matches = [];
    preg_match('/\((.*?)\)/', $rawInput, $matches);
    $memberSaccoId = trim($matches[1] ?? '');

    if ($memberSaccoId === '') {
        return redirect()->back()->withInput()->with('error', ['Invalid member selected.']);
    }

    $member = DB::table('sacco_members')
        ->whereRaw('LOWER(member_sacco_id) = ?', [strtolower($memberSaccoId)])
        ->where('member_active', 'Y')
        ->where('member_deleted', '<>', 'Y')
        ->first();

    if (!$member) {
        return redirect()->back()->withInput()->with('error', ['Invalid member selected.']);
    }

    $loanType = DB::table('sacco_loan_types')
        ->where('loan_type_id', $validated['batch_trans_loan_type'])
        ->first();

    if (!$loanType) {
        return redirect()->back()->withInput()->with('error', ['Invalid loan type selected.']);
    }

    $errors = $this->validateLoanTransactionInput($batch_id, $batch, $validated);

    $guarantorErrors = $this->validateGuarantors($validated, $loanType, $member);
    $errors = array_merge($errors, $guarantorErrors);

    $topUpErrors = $this->validateLoanToTopUp(
        $member->member_id,
        $validated['batch_trans_loan_to_top_up'] ?? null,
        $validated['batch_trans_loan_amount']
    );

    if (!$topUpErrors['is_valid']) {
        $errors[] = $topUpErrors['message'];
    }

    $otherCharges = $this->resolveLoanTransactionCharges(
        (float) $validated['batch_trans_loan_amount'],
        $request->input('other_charges', [])
    );

    if (!empty($otherCharges['errors'])) {
        $errors = array_merge($errors, $otherCharges['errors']);
    }

    if (!empty($errors)) {
        return redirect()->back()->withInput()->with('error', $errors);
    }

    $commission = (float) ($validated['batch_trans_commission_amount'] ?? 0);
$calc = $this->calculateLoanFinancialsForTransaction(
    (float) $validated['batch_trans_loan_amount'],
    (float) $otherCharges['amount_for_emi'],
    (int) $validated['batch_trans_loan_duration'],
    $loanType,
    $member,
    $commission
);

    $topUpOutstanding = 0;
    if (!empty($validated['batch_trans_loan_to_top_up'])) {
        $topupLoan = DB::table('sacco_loans')
            ->where('loan_id', $validated['batch_trans_loan_to_top_up'])
            ->first();

        if ($topupLoan) {
            $topUpOutstanding = max(
                0,
                ((float) $topupLoan->loan_amount - (float) $topupLoan->loan_loan_paid)
            );
        }
    }

    try {
        DB::beginTransaction();

        $data = [
            'batch_trans_batch_id'               => $batch_id,
            'batch_trans_loan_type'             => $validated['batch_trans_loan_type'],
            'batch_trans_loan_category'         => $validated['batch_trans_loan_category'],
            'batch_trans_loan_amount'           => (float) $validated['batch_trans_loan_amount'],
            'batch_trans_member_id'             => $member->member_id,
            'batch_trans_loan_duration'         => (int) $validated['batch_trans_loan_duration'],
            'batch_trans_monthly_payment'       => $calc['monthly_payment'],
            'batch_trans_monthly_payment_principal' => $calc['monthly_payment_principal'],
            'batch_trans_expected_interest'     => $calc['expected_interest'],
            'batch_trans_insurance'             => $calc['insurance'],
            'batch_trans_other_deductions'      => (float) ($otherCharges['total_other_deductions'] ?? 0),
            'batch_trans_doc_no'                => $validated['batch_trans_doc_no'],
            'batch_trans_description'           => $validated['batch_trans_description'] ?? '',
            'batch_trans_commission'            => $commission,
            'batch_trans_loan_to_top_up'        => $validated['batch_trans_loan_to_top_up'] ?? 0,
            'batch_trans_loan_to_top_up_amount' => $topUpOutstanding,
            'batch_trans_loan_guaranteed'       => 0,
            'batch_trans_updated'               => 'N',
            'batch_trans_by'                    => auth()->id(),
            'batch_trans_ip'                    => $request->ip(),
        ];

        $transactionId = DB::table('sacco_loan_batch_trans')->insertGetId($data);

        if (!$transactionId) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Loan transaction could not be saved.');
        }

        foreach (($otherCharges['rows'] ?? []) as $row) {
            DB::table('sacco_loan_batch_trans_deductions')->insert([
                'batch_trans_deduction_batch_id'           => $batch_id,
                'batch_trans_deduction_batch_trans_id'     => $transactionId,
                'batch_trans_deduction_deduction_type_id'  => $row['batch_trans_deduction_deduction_type_id'],
                'batch_trans_deduction_name'               => $row['batch_trans_deduction_name'],
                'batch_trans_deduction_code'               => $row['batch_trans_deduction_code'],
                'batch_trans_deduction_value_type'         => $row['batch_trans_deduction_value_type'],
                'batch_trans_deduction_effect'             => $row['batch_trans_deduction_effect'],
                'batch_trans_deduction_account'            => $row['batch_trans_deduction_account'],
                'batch_trans_deduction_amount'             => (float) $row['batch_trans_deduction_amount'],
                'batch_trans_deduction_description'        => $row['batch_trans_deduction_description'],
                'batch_trans_deduction_updated'            => 'N',
                'batch_trans_deduction_by'                 => auth()->id(),
                'batch_trans_deduction_ip'                 => $request->ip(),
                'batch_trans_deduction_deleted'            => 'N',
            ]);
        }

        $totalGuaranteed = 0;
        $originalTotal = 0;
        $validGuarantors = [];

        foreach (($validated['guarantors'] ?? []) as $guarantor) {
            preg_match('/\((.*?)\)/', $guarantor['member'] ?? '', $match);
            $guarantorSaccoId = trim($match[1] ?? '');

            if ($guarantorSaccoId !== '' && !empty($guarantor['amount']) && is_numeric($guarantor['amount'])) {
                $guarantorMember = DB::table('sacco_members')
                    ->where('member_sacco_id', $guarantorSaccoId)
                    ->where('member_active', 'Y')
                    ->where('member_deleted', '<>', 'Y')
                    ->first();

                if ($guarantorMember) {
                    $guarantor['parsed_id'] = $guarantorMember->member_id;
                    $validGuarantors[] = $guarantor;
                    $originalTotal += (float) $guarantor['amount'];
                }
            }
        }

        if ($originalTotal > 0) {
            $loanAmount = (float) $validated['batch_trans_loan_amount'];

            foreach ($validGuarantors as $guarantor) {
                $originalAmount = (float) $guarantor['amount'];
                $proratedAmount = round(($originalAmount / $originalTotal) * $loanAmount, 2);

                DB::table('sacco_loan_batch_guarantors')->insert([
                    'guarantors_loan_batch_trans_id' => $transactionId,
                    'guarantors_guarantor_id'        => $guarantor['parsed_id'],
                    'guarantors_amount_guaranteed'   => $proratedAmount,
                    'guarantors_description'         => $guarantor['description'] ?? '',
                    'guarantors_by'                  => auth()->id(),
                    'guarantors_ip'                  => $request->ip(),
                ]);

                $totalGuaranteed += $proratedAmount;
            }
        }

        DB::table('sacco_loan_batch_trans')
            ->where('batch_trans_id', $transactionId)
            ->update([
                'batch_trans_loan_guaranteed' => $totalGuaranteed
            ]);

        DB::commit();

        return redirect()
            ->route('loans.batch.transactions', $batch_id)
            ->with('success', 'Loan transaction, deductions and guarantors saved successfully.');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Loan transaction save failed: ' . $e->getMessage());

        return redirect()->back()
            ->withInput()
            ->with('error', 'Something went wrong while saving. Please try again.');
    }
}

private function resolveLoanTransactionCharges(float $requestedLoanAmount, array $submittedOtherCharges = []): array
{
    $requestedLoanAmount = round((float) $requestedLoanAmount, 2);

    $errors = [];
    $rows = [];

    $totalAddToLoan = 0;
    $totalDeductFromDisbursement = 0;

    $submittedRows = collect($submittedOtherCharges)
        ->filter(function ($row) {
            return is_array($row) && !empty($row['deduction_type_id']);
        })
        ->values();

    if ($submittedRows->isEmpty()) {
        return [
            'errors'                         => [],
            'rows'                           => [],
            'total_other_additions'          => 0,
            'total_other_deductions'         => 0,
            'total_add_to_loan'              => 0,
            'total_deduct_from_disbursement' => 0,
            'amount_for_emi'                 => $requestedLoanAmount,
            'net_amount_to_member'           => $requestedLoanAmount,
        ];
    }

    $typeIds = $submittedRows
        ->pluck('deduction_type_id')
        ->map(function ($id) {
            return (int) $id;
        })
        ->filter()
        ->unique()
        ->values()
        ->all();

    $deductionTypes = DB::table('sacco_loan_deductions_types')
        ->whereIn('deduction_type_id', $typeIds)
        ->where('deduction_type_deleted', 'N')
        ->where('deduction_type_active', 1)
        ->get()
        ->keyBy('deduction_type_id');

    $alreadyUsed = [];

    foreach ($submittedRows as $index => $row) {
        $rowNo  = $index + 1;
        $typeId = (int) ($row['deduction_type_id'] ?? 0);

        if ($typeId <= 0) {
            $errors[] = "Row {$rowNo}: Invalid deduction type selected.";
            continue;
        }

        if (isset($alreadyUsed[$typeId])) {
            $errors[] = "Row {$rowNo}: The same deduction type cannot be added more than once.";
            continue;
        }

        $alreadyUsed[$typeId] = true;

        $type = $deductionTypes->get($typeId);

        if (!$type) {
            $errors[] = "Row {$rowNo}: Selected deduction type is invalid, inactive, or deleted.";
            continue;
        }

        $valueType = strtoupper(trim((string) ($type->deduction_type_value_type ?? 'FIXED')));
        $effect    = strtoupper(trim((string) ($type->deduction_type_effect ?? 'DEDUCT_FROM_DISBURSEMENT')));
        $name      = trim((string) ($type->deduction_type_name ?? 'Charge'));
        $code      = trim((string) ($type->deduction_type_code ?? ''));
        $account   = $type->deduction_type_account ?: null;

        if (!in_array($valueType, ['FIXED', 'PERCENT'], true)) {
            $errors[] = "Row {$rowNo}: {$name} has an invalid value type.";
            continue;
        }

        if (!in_array($effect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
            $errors[] = "Row {$rowNo}: {$name} has an invalid effect.";
            continue;
        }

        $configuredDefault = (float) ($type->deduction_type_default_value ?? 0);
        $hasLockedValue    = $configuredDefault > 0;

        $submittedValueRaw = $row['amount'] ?? null;
        $submittedValue    = is_numeric($submittedValueRaw) ? (float) $submittedValueRaw : null;

        if ($hasLockedValue) {
            $valueToUse = $configuredDefault;
        } else {
            if ($submittedValue === null || $submittedValue <= 0) {
                $errors[] = "Row {$rowNo}: {$name} requires a value greater than zero.";
                continue;
            }

            $valueToUse = $submittedValue;
        }

        $resolvedAmount = 0;
        $description = '';

        if ($valueType === 'PERCENT') {
            $percent = round($valueToUse, 4);

            if ($percent <= 0) {
                $errors[] = "Row {$rowNo}: {$name} percentage must be greater than zero.";
                continue;
            }

            $resolvedAmount = round(($requestedLoanAmount * $percent) / 100, 2);

            if ($resolvedAmount <= 0) {
                $errors[] = "Row {$rowNo}: {$name} resolved to zero. Please check the percentage.";
                continue;
            }

            if ($effect === 'ADD_TO_LOAN') {
                $description = sprintf(
                    '%s added to loan at %s%%, resulting in KES %s based on requested loan amount of KES %s.',
                    $name,
                    rtrim(rtrim(number_format($percent, 4, '.', ''), '0'), '.'),
                    number_format($resolvedAmount, 2),
                    number_format($requestedLoanAmount, 2)
                );
            } else {
                $description = sprintf(
                    '%s deducted from disbursement at %s%%, resulting in KES %s based on requested loan amount of KES %s.',
                    $name,
                    rtrim(rtrim(number_format($percent, 4, '.', ''), '0'), '.'),
                    number_format($resolvedAmount, 2),
                    number_format($requestedLoanAmount, 2)
                );
            }
        } else {
            $resolvedAmount = round($valueToUse, 2);

            if ($resolvedAmount <= 0) {
                $errors[] = "Row {$rowNo}: {$name} amount must be greater than zero.";
                continue;
            }

            if ($effect === 'ADD_TO_LOAN') {
                $description = sprintf(
                    '%s added to loan at KES %s.',
                    $name,
                    number_format($resolvedAmount, 2)
                );
            } else {
                $description = sprintf(
                    '%s deducted from disbursement at KES %s.',
                    $name,
                    number_format($resolvedAmount, 2)
                );
            }
        }

        $rows[] = [
            'batch_trans_deduction_deduction_type_id' => $typeId,
            'batch_trans_deduction_name'              => $name,
            'batch_trans_deduction_code'              => $code !== '' ? $code : null,
            'batch_trans_deduction_value_type'        => $valueType,
            'batch_trans_deduction_effect'            => $effect,
            'batch_trans_deduction_account'           => $account,
            'batch_trans_deduction_amount'            => $resolvedAmount,
            'batch_trans_deduction_description'       => $description,
        ];

        if ($effect === 'ADD_TO_LOAN') {
            $totalAddToLoan += $resolvedAmount;
        } else {
            $totalDeductFromDisbursement += $resolvedAmount;
        }
    }

    $totalAddToLoan = round($totalAddToLoan, 2);
    $totalDeductFromDisbursement = round($totalDeductFromDisbursement, 2);

    return [
        'errors'                         => $errors,
        'rows'                           => $rows,
        'total_other_additions'          => $totalAddToLoan,
        'total_other_deductions'         => $totalDeductFromDisbursement,
        'total_add_to_loan'              => $totalAddToLoan,
        'total_deduct_from_disbursement' => $totalDeductFromDisbursement,
        'amount_for_emi'                 => round($requestedLoanAmount + $totalAddToLoan, 2),
        'net_amount_to_member'           => round($requestedLoanAmount - $totalDeductFromDisbursement, 2),
    ];
}

    private function validateLoanTransactionInput($batch_id, $batch, $validated)
    {
        $errors = [];

        // Step 1: Extract SACCO ID from input like "ABDALLAH MOHAMMED - (SP69)"
        $rawMemberInput = $validated['batch_trans_member_id'];
        $matches = [];
        preg_match('/\((.*?)\)/', $rawMemberInput, $matches);
        $memberSaccoId = $matches[1] ?? null;

        // Step 2: Run the actual query using the extracted SACCO ID
        $member = null;
        if ($memberSaccoId) {
            $member = DB::table('sacco_members')
                ->whereRaw('LOWER(member_sacco_id) = ?', [strtolower($memberSaccoId)])
                ->where('member_active', 'Y')
                ->where('member_deleted', '<>', 'Y')
                ->first();
        }

        if (!$member) {
            $errors[] = 'Invalid member selected.';
        }

        // Fetch Loan Type
        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $validated['batch_trans_loan_type'])
            ->first();

        if (!$loanType) {
            $errors[] = 'Invalid loan type selected.';
        }

        // Fetch Loan Category
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
            ->where('batch_trans_member_id', $member->member_id)
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

        // 6. Share factor check using loan-type rule first
$sharePolicy = $this->getEffectiveLoanShareFactorPolicy($loanType);

if ($sharePolicy['limited']) {
    $loanFactor = $sharePolicy['factor'];

    $totalShares = (float) ($member->member_total_share ?? 0);
    $maxLoanLimit = $totalShares * $loanFactor;

    $existingLoanBalance = DB::table('sacco_loans')
        ->where('loan_member', $member->member_id)
        ->where('loan_stoped', 'N')
        ->whereRaw('loan_amount > COALESCE(loan_loan_paid, 0)')
        ->selectRaw('SUM(loan_amount - COALESCE(loan_loan_paid, 0)) AS balance')
        ->value('balance') ?? 0;

    $totalExposure = (float) $existingLoanBalance + (float) $validated['batch_trans_loan_amount'];

    if ($totalExposure > $maxLoanLimit) {
        $errors[] = 'Loan amount exceeds allowable limit based on SACCO share policy. '
            . 'Maximum allowed is KES ' . number_format($maxLoanLimit, 2)
            . ' based on shares of KES ' . number_format($totalShares, 2)
            . ' x factor of ' . $loanFactor . '. '
            . 'Current exposure plus new loan would be KES ' . number_format($totalExposure, 2) . '.';
    }
}

        return $errors;
    }
   private function validateGuarantors($validated, $loanType, $member, $excludeTransactionId = null)
{
    $errors = [];
    $guarantors = $validated['guarantors'] ?? [];

    $guaranteablePercent = (float) ($loanType->loan_type_guaranteable_percent ?? 0);
    if ($guaranteablePercent <= 0) {
        return $errors;
    }

    $requiredGuarantee = round(
        (float) $validated['batch_trans_loan_amount'] * ($guaranteablePercent / 100),
        2
    );

    $guaranteedTotal = 0.00;

    $maxGuarantorFactor = (float) $this->getMaxGuarantorFactor();
    $maxGuarantorFactorSelf = (float) $this->getMaxGuarantorFactorSelf();

    foreach ($guarantors as $index => $g) {
        if (empty($g['member']) || !isset($g['amount']) || !is_numeric($g['amount'])) {
            continue;
        }

        $guaranteeAmount = round((float) $g['amount'], 2);
        if ($guaranteeAmount <= 0) {
            continue;
        }

        preg_match('/\(([^)]+)\)$/', $g['member'], $matches);
        $memberSaccoId = trim($matches[1] ?? '');

        if ($memberSaccoId === '') {
            $errors[] = 'Row ' . ($index + 1) . ': Invalid guarantor selected.';
            continue;
        }

        $guarantor = DB::table('sacco_members')
            ->whereRaw('LOWER(TRIM(member_sacco_id)) = ?', [strtolower($memberSaccoId)])
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->first();

        if (!$guarantor) {
            $errors[] = 'Row ' . ($index + 1) . ': Guarantor not found for ID: ' . $memberSaccoId;
            continue;
        }

        $isSelf = ((int) $guarantor->member_id === (int) $member->member_id);

        $pendingQuery = DB::table('sacco_loan_batch_guarantors as g')
            ->join('sacco_loan_batch_trans as t', 't.batch_trans_id', '=', 'g.guarantors_loan_batch_trans_id')
            ->join('sacco_loan_batch as b', 'b.batch_id', '=', 't.batch_trans_batch_id')
            ->where('g.guarantors_guarantor_id', $guarantor->member_id)
            ->where('g.guarantors_deleted', '<>', 'Y')
            ->where(function ($q) {
                $q->whereNull('g.guarantors_transfered')
                  ->orWhere('g.guarantors_transfered', '<>', 'Y');
            })
            ->where('t.batch_trans_deleted', '<>', 'Y')
            ->where('b.batch_deleted', '<>', 'Y')
            ->where('b.batch_updated', '<>', 'Y');

        if (!empty($excludeTransactionId)) {
            $pendingQuery->where('t.batch_trans_id', '<>', $excludeTransactionId);
        }

        if ($isSelf) {
            $pendingQuery->where('t.batch_trans_member_id', $guarantor->member_id);

            $currentTied = (float) ($guarantor->member_tied_shares_self ?? 0);
            $maxAllowedGuarantee = ((float) ($guarantor->member_total_share ?? 0) * $maxGuarantorFactorSelf);
        } else {
            $pendingQuery->where('t.batch_trans_member_id', '<>', $guarantor->member_id);

            $currentTied = (float) ($guarantor->member_tied_shares ?? 0);
            $maxAllowedGuarantee = ((float) ($guarantor->member_total_share ?? 0) * $maxGuarantorFactor);
        }

        $pendingGuarantees = round((float) $pendingQuery->sum('g.guarantors_amount_guaranteed'), 2);

        $available = round($maxAllowedGuarantee - $currentTied - $pendingGuarantees, 2);
        $available = max(0, $available);

        if ($guaranteeAmount > $available) {
            if ($isSelf) {
                $errors[] = 'Row ' . ($index + 1) . ': Insufficient free shares for self-guarantee. Available: ' . number_format($available, 2);
            } else {
                $errors[] = 'Row ' . ($index + 1) . ': ' . $g['member'] . ' has insufficient free shares to guarantee this amount. Available: ' . number_format($available, 2);
            }
            continue;
        }

        $guaranteedTotal += $guaranteeAmount;
    }

    if ($guaranteedTotal < $requiredGuarantee) {
        $errors[] = 'Total guarantees provided (' . number_format($guaranteedTotal, 2) . ') are less than required (' . number_format($requiredGuarantee, 2) . ').';
    }

    return $errors;
}

    private function validateLoanToTopUp($memberId, $loanToTopUpId, $newLoanAmount)
    {
        if (!$loanToTopUpId || !$memberId) {
            return ['is_valid' => true]; // No top-up selected
        }

        // Fetch loan for validation
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

        // Calculate balance
        $outstandingBalance = $loan->loan_amount - $loan->loan_loan_paid;

        if ($outstandingBalance > $newLoanAmount) {
            return [
                'is_valid' => false,
                'message' => 'The outstanding balance of the selected loan for top-up exceeds the amount of the new loan.'
            ];
        }

        return ['is_valid' => true];
    }



    private function calculateLoanFinancials($loanAmount, $durationMonths, $loanType, $member, $commission = 0)
    {
        $calcFunction = DB::table('sacco_defaults')
            ->where('default_name', 'loan_interest_insurance')
            ->value('default_value');

        if (!$calcFunction || !method_exists($this, $calcFunction)) {
            $calcFunction = 'default_calc_loan_interest_insurance'; // Use new default
        }

        return $this->$calcFunction($loanAmount, $durationMonths, $loanType, $member, $commission);
    }

   private function calc_loan_interest_insurance_adom()
{
    $args = func_get_args();
    $argc = func_num_args();

    if ($argc === 3) {
        // LEGACY MODE
        [$loanTypeId, $loanAmount, $months] = $args;

        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $loanTypeId)
            ->first();

    } elseif ($argc === 5) {
        // NEW MODE
        [$loanAmount, $months, $loanType, $member, $commission] = $args;

    } else {
        throw new \InvalidArgumentException(
            'Invalid arguments for calc_loan_interest_insurance_adom'
        );
    }

    /* ===============================
       ADOM / MEPIP INSURANCE
       =============================== */
    $base_insu = ((5.03 * $months + 3.03) * $loanAmount) / 6000;
    $base_insu = max($base_insu, 100);

    $phcf = $base_insu * 0.0025;
    $insu = $base_insu + $phcf;

    if ($loanType->loan_type_insurable !== 'Y') {
        $insu = 0;
    }

    /* ===============================
       INTEREST + EMI
       =============================== */
    if (strtoupper($loanType->loan_type_interest_type) === 'FIXED INTEREST') {

        $interest =
            round(($loanAmount + $insu) * $loanType->loan_type_interest / 100, 0);

        $emi =
            ceil(($loanAmount + $interest + $insu) / $months);

        $principal =
            ($loanAmount + $insu) / $months;

    } else {

        $loanPlus = $loanAmount + $insu;
        $rate = $loanType->loan_type_interest / 12 / 100;

        if ($rate > 0) {
            $emi =
                ($loanPlus * $rate)
                * pow(1 + $rate, $months)
                / (pow(1 + $rate, $months) - 1);
        } else {
            $emi = $loanPlus / $months;
        }

        $interest = ($emi * $months) - $loanPlus;
        $principal = $emi - ($loanPlus * $rate);

        $emi = ceil($emi);
        $principal = ceil($principal);
    }

    /* ===============================
       DUAL RETURN (CRITICAL)
       =============================== */
    return [
        // LEGACY (numeric)
        "", 
        $emi,
        $interest,
        $principal,
        $insu,

        // NEW (associative)
        'monthly_payment' => $emi,
        'monthly_payment_principal' => $principal,
        'expected_interest' => $interest,
        'insurance' => $insu,
    ];
}


    private function calc_loan_interest_insurance_yes($loanAmount, $durationMonths, $loanType, $member, $commission = 0)
    {
        $annualRate = (float) $loanType->loan_type_interest;
        $monthlyRate = $annualRate / 12 / 100;

        // Insurance = 1% of loan
        $insurance = round($loanAmount * 0.01, 2);

        // Total burden
        $loanPlusExtras = $loanAmount + $commission + $insurance;

        // EMI calculation
        if ($monthlyRate > 0) {
            $emi = $loanPlusExtras * $monthlyRate * pow(1 + $monthlyRate, $durationMonths) / (pow(1 + $monthlyRate, $durationMonths) - 1);
        } else {
            $emi = $loanPlusExtras / $durationMonths;
        }

        $expectedInterest = ($emi * $durationMonths) - $loanPlusExtras;

        return [
            'monthly_payment' => round($emi, 2),
            'monthly_payment_principal' => round($loanAmount / $durationMonths, 2),
            'expected_interest' => round($expectedInterest, 2),
            'insurance' => $insurance,
        ];
    }
   

    private function getMaxGuarantorFactorSelf()
    {
        $value = DB::table('sacco_defaults')
            ->where('default_name', 'max_guarantor_factor_self')
            ->value('default_value');

        if ($value === null) {
            // Insert default
            DB::table('sacco_defaults')->insert([
                'default_name' => 'max_guarantor_factor_self',
                'default_value' => 1,
            ]);

            return 1.0;
        }

        return (float) $value;
    }

    private function calc_loan_interest_insurance_dhl($loanAmount, $durationMonths, $loanType, $member = null, $commission = 0)
    {
        $interestType = strtoupper(trim($loanType->loan_type_interest_type ?? 'FIXED INTEREST'));
        $annualRate   = (float) ($loanType->loan_type_interest ?? 0);
        $monthlyRate  = $annualRate / 12 / 100;

        // ✅ Insurance — only if insurable, 1% of loan amount
        $insurance = ($loanType->loan_type_insurable == 'Y')
            ? round($loanAmount * 0.01, 2)
            : 0.0;

        // ✅ Include insurance and commission in total loan cost base
        $loanAmountWithInsu = $loanAmount + $commission + $insurance;

        $emi = 0;
        $expectedInterest = 0;
        $monthlyPrincipal = 0;

        if ($interestType === 'FIXED INTEREST') {
            // ✅ Fixed interest calculation
            $expectedInterest = round(($loanAmountWithInsu) * $annualRate / 100, 2);
            $emi = ceil(($loanAmountWithInsu + $expectedInterest) / $durationMonths);
            $monthlyPrincipal = ceil(($loanAmountWithInsu) / $durationMonths);
        } else {
            // ✅ Flat interest monthly (legacy SACCO method)
            $interestPercent = $annualRate / 12 / 100;

            $emi = ($loanAmountWithInsu / $durationMonths) + ($loanAmountWithInsu * $interestPercent);
            $EMI = ceil($emi);

            $expectedInterest = $loanAmountWithInsu * $interestPercent;
            $monthlyPrincipal = $EMI - ($loanAmountWithInsu * $interestPercent);

            // enforce consistent rounding
            $emi = $EMI;
            $monthlyPrincipal = ceil($monthlyPrincipal);
        }

        return [
            'monthly_payment'            => round($emi, 2),
            'monthly_payment_principal'  => round($monthlyPrincipal, 2),
            'expected_interest'          => round($expectedInterest, 2),
            'insurance'                  => round($insurance, 2),
        ];
    }
private function getBatchTransactionChargeSummary($transactionId): array
{
    $rows = DB::table('sacco_loan_batch_trans_deductions')
        ->where('batch_trans_deduction_batch_trans_id', $transactionId)
        ->where('batch_trans_deduction_deleted', 'N')
        ->orderBy('batch_trans_deduction_id', 'asc')
        ->get();

    $addToLoan = 0;
    $deductFromDisbursement = 0;
    $snapshotParts = [];

    foreach ($rows as $row) {
        $amount = (float) ($row->batch_trans_deduction_amount ?? 0);

        if (($row->batch_trans_deduction_effect ?? '') === 'ADD_TO_LOAN') {
            $addToLoan += $amount;
        }

        if (($row->batch_trans_deduction_effect ?? '') === 'DEDUCT_FROM_DISBURSEMENT') {
            $deductFromDisbursement += $amount;
        }

        if (!empty($row->batch_trans_deduction_description)) {
            $snapshotParts[] = trim($row->batch_trans_deduction_description);
        } else {
            $snapshotParts[] = trim(($row->batch_trans_deduction_name ?? 'Charge') . ' - ' . number_format($amount, 2));
        }
    }

    return [
        'rows' => $rows,
        'add_to_loan' => round($addToLoan, 2),
        'deduct_from_disbursement' => round($deductFromDisbursement, 2),
        'charges_snapshot' => implode(' | ', $snapshotParts),
    ];
}

private function transferBatchDeductionsToLoan($loanId, $batchId, $transactionId): void
{
    $rows = DB::table('sacco_loan_batch_trans_deductions')
        ->where('batch_trans_deduction_batch_trans_id', $transactionId)
        ->where('batch_trans_deduction_deleted', 'N')
        ->get();

    foreach ($rows as $row) {
        DB::table('sacco_loan_deductions')->insert([
            'loan_deduction_loan_id'              => $loanId,
            'loan_deduction_batch_id'             => $batchId,
            'loan_deduction_batch_trans_id'       => $transactionId,
            'loan_deduction_batch_deduction_id'   => $row->batch_trans_deduction_id ?? null,
            'loan_deduction_deduction_type_id'    => $row->batch_trans_deduction_deduction_type_id,
            'loan_deduction_name'                 => $row->batch_trans_deduction_name,
            'loan_deduction_code'                 => $row->batch_trans_deduction_code,
            'loan_deduction_value_type'           => $row->batch_trans_deduction_value_type,
            'loan_deduction_effect'               => $row->batch_trans_deduction_effect,
            'loan_deduction_account'              => $row->batch_trans_deduction_account,
            'loan_deduction_amount'               => (float) $row->batch_trans_deduction_amount,
            'loan_deduction_description'          => $row->batch_trans_deduction_description,
            'loan_deduction_updated'              => 'N',
            'loan_deduction_by'                   => auth()->id(),
            'loan_deduction_ip'                   => request()->ip(),
            'loan_deduction_deleted'              => 'N',
        ]);
    }
}

private function calculateLoanFinancialsForTransaction(
    float $requestedLoanAmount,
    float $amountForEmi,
    int $durationMonths,
    $loanType,
    $member,
    float $commission = 0
): array {
    $requestedLoanAmount = round($requestedLoanAmount, 2);
    $amountForEmi = round($amountForEmi, 2);
    $commission = round($commission, 2);
    $durationMonths = max(1, $durationMonths);

    $commissionEffect = strtoupper(trim((string) ($loanType->loan_type_commission_effect ?? 'ADD_TO_LOAN')));
    if (!in_array($commissionEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
        $commissionEffect = 'ADD_TO_LOAN';
    }

    $insuranceEffect = strtoupper(trim((string) ($loanType->loan_type_insurance_effect ?? 'ADD_TO_LOAN')));
    if (!in_array($insuranceEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
        $insuranceEffect = 'ADD_TO_LOAN';
    }

    $calcFunction = $this->resolveCalcFunction();
    $insurance = $this->resolveInsuranceAmount(
        $requestedLoanAmount,
        $durationMonths,
        $loanType,
        $calcFunction
    );

    $loanPlusExtras = $this->resolveLoanPlusExtras(
        $amountForEmi,
        $commission,
        $commissionEffect,
        $insurance,
        $insuranceEffect
    );

    switch ($calcFunction) {
        case 'calc_loan_interest_insurance_adom':
            if (strtoupper(trim((string) ($loanType->loan_type_interest_type ?? ''))) === 'FIXED INTEREST') {
                $expectedInterest = round($loanPlusExtras * ((float) $loanType->loan_type_interest / 100), 0);
                $monthlyPayment = ceil(($loanPlusExtras + $expectedInterest) / $durationMonths);
                $monthlyPrincipal = $loanPlusExtras / $durationMonths;
            } else {
                $rate = ((float) $loanType->loan_type_interest) / 12 / 100;

                if ($rate > 0) {
                    $monthlyPayment = ($loanPlusExtras * $rate) * pow(1 + $rate, $durationMonths)
                        / (pow(1 + $rate, $durationMonths) - 1);
                } else {
                    $monthlyPayment = $loanPlusExtras / $durationMonths;
                }

                $expectedInterest = ($monthlyPayment * $durationMonths) - $loanPlusExtras;
                $monthlyPrincipal = $monthlyPayment - ($loanPlusExtras * $rate);

                $monthlyPayment = ceil($monthlyPayment);
                $monthlyPrincipal = ceil($monthlyPrincipal);
            }

            return [
                'monthly_payment' => round($monthlyPayment, 2),
                'monthly_payment_principal' => round($monthlyPrincipal, 2),
                'expected_interest' => round($expectedInterest, 2),
                'insurance' => round($insurance, 2),
            ];

        case 'calc_loan_interest_insurance_yes':
            $annualRate = (float) $loanType->loan_type_interest;
            $monthlyRate = $annualRate / 12 / 100;

            if ($monthlyRate > 0) {
                $monthlyPayment = $loanPlusExtras * $monthlyRate * pow(1 + $monthlyRate, $durationMonths)
                    / (pow(1 + $monthlyRate, $durationMonths) - 1);
            } else {
                $monthlyPayment = $loanPlusExtras / $durationMonths;
            }

            $expectedInterest = ($monthlyPayment * $durationMonths) - $loanPlusExtras;

            return [
                'monthly_payment' => round($monthlyPayment, 2),
                'monthly_payment_principal' => round($amountForEmi / $durationMonths, 2),
                'expected_interest' => round($expectedInterest, 2),
                'insurance' => round($insurance, 2),
            ];

        case 'calc_loan_interest_insurance_dhl':
            $interestType = strtoupper(trim((string) ($loanType->loan_type_interest_type ?? 'FIXED INTEREST')));
            $annualRate = (float) ($loanType->loan_type_interest ?? 0);

            if ($interestType === 'FIXED INTEREST') {
                $expectedInterest = round($loanPlusExtras * $annualRate / 100, 2);
                $monthlyPayment = ceil(($loanPlusExtras + $expectedInterest) / $durationMonths);
                $monthlyPrincipal = ceil($loanPlusExtras / $durationMonths);
            } else {
                $interestPercent = $annualRate / 12 / 100;
                $monthlyPayment = ($loanPlusExtras / $durationMonths) + ($loanPlusExtras * $interestPercent);
                $monthlyPayment = ceil($monthlyPayment);

                $expectedInterest = $loanPlusExtras * $interestPercent;
                $monthlyPrincipal = $monthlyPayment - ($loanPlusExtras * $interestPercent);
                $monthlyPrincipal = ceil($monthlyPrincipal);
            }

            return [
                'monthly_payment' => round($monthlyPayment, 2),
                'monthly_payment_principal' => round($monthlyPrincipal, 2),
                'expected_interest' => round($expectedInterest, 2),
                'insurance' => round($insurance, 2),
            ];

        case 'default_calc_loan_interest_insurance':
        default:
            return $this->default_calc_loan_interest_insurance(
                $loanType,
                $amountForEmi,
                $loanPlusExtras,
                $insurance,
                $durationMonths
            );
    }
}
private function resolveCalcFunction(): string
{
    $calcFunction = DB::table('sacco_defaults')
        ->where('default_name', 'loan_interest_insurance')
        ->value('default_value');

    $allowed = [
        'calc_loan_interest_insurance_adom',
        'calc_loan_interest_insurance_yes',
        'calc_loan_interest_insurance_dhl',
        'calc_loan_interest_insurance_kass',
        'default_calc_loan_interest_insurance',
    ];

    if (!$calcFunction || !in_array($calcFunction, $allowed, true)) {
        return 'default_calc_loan_interest_insurance';
    }

    return $calcFunction;
}

private function resolveInsuranceAmount(
    float $requestedLoanAmount,
    int $durationMonths,
    $loanType,
    string $calcFunction
): float {
    $requestedLoanAmount = round($requestedLoanAmount, 2);
    $durationMonths = max(1, $durationMonths);

    $isInsurable = strtoupper(trim((string) ($loanType->loan_type_insurable ?? 'N'))) === 'Y';

    if (!$isInsurable) {
        return 0.0;
    }


if ($calcFunction === 'calc_loan_interest_insurance_kass') {
    return round(($requestedLoanAmount * 10.4) / 1000, 2);
}

    if ($calcFunction === 'calc_loan_interest_insurance_adom') {
        $baseInsu = ((5.03 * $durationMonths + 3.03) * $requestedLoanAmount) / 6000;
        $baseInsu = max($baseInsu, 100);
        $phcf = $baseInsu * 0.0025;

        return round($baseInsu + $phcf, 2);
    }

    // Your default SACCO behaviour
    return round($requestedLoanAmount * 0.01, 2);
}

private function resolveLoanPlusExtras(
    float $amountForEmi,
    float $commission,
    string $commissionEffect,
    float $insurance,
    string $insuranceEffect
): float {
    $amountForEmi = round($amountForEmi, 2);
    $commission = round($commission, 2);
    $insurance = round($insurance, 2);

    $commissionForEmi = strtoupper($commissionEffect) === 'ADD_TO_LOAN' ? $commission : 0.0;
    $insuranceForEmi = strtoupper($insuranceEffect) === 'ADD_TO_LOAN' ? $insurance : 0.0;

    return round($amountForEmi + $commissionForEmi + $insuranceForEmi, 2);
}

private function default_calc_loan_interest_insurance(
    $loanType,
    float $amountForEmi,
    float $loanPlusExtras,
    float $insurance,
    int $repaymentPeriod
): array {
    $amountForEmi = round($amountForEmi, 2);
    $loanPlusExtras = round($loanPlusExtras, 2);
    $insurance = round($insurance, 2);
    $repaymentPeriod = max(1, $repaymentPeriod);

    $interestType = strtoupper(trim((string) ($loanType->loan_type_interest_type ?? 'REDUCING BALANCE')));
    $annualRate = (float) ($loanType->loan_type_interest ?? 0);

    // Use your old default SACCO logic exactly:
    // FIXED INTEREST => straight formula
    // anything else => reducing-balance style
    if ($interestType === 'FIXED INTEREST') {
        $interestAmountPayable = round($loanPlusExtras * $annualRate / 100, 0);
        $emi = ceil(($loanPlusExtras + $interestAmountPayable) / $repaymentPeriod);
        $monthlyRepaymentPrincipal = $loanPlusExtras / $repaymentPeriod;
    } else {
        $interestRate = $annualRate / 12 / 100;

        if ($interestRate > 0) {
            $emi = ($loanPlusExtras * $interestRate) * pow(1 + $interestRate, $repaymentPeriod)
                / (pow(1 + $interestRate, $repaymentPeriod) - 1);
        } else {
            $emi = $loanPlusExtras / $repaymentPeriod;
        }

        $interestAmountPayable = ($emi * $repaymentPeriod) - $loanPlusExtras;
        $monthlyRepaymentPrincipal = $emi - ($loanPlusExtras * $interestRate);

        $emi = ceil($emi);
        $monthlyRepaymentPrincipal = ceil($monthlyRepaymentPrincipal);
    }

    return [
        'monthly_payment' => round($emi, 2),
        'monthly_payment_principal' => round($monthlyRepaymentPrincipal, 2),
        'expected_interest' => round($interestAmountPayable, 2),
        'insurance' => round($insurance, 2),
    ];
}

}
