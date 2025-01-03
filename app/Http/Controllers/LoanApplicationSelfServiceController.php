<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanApplicationSelfServiceController extends Controller
{
    public function listLoansPendingApproval(Request $request)
    {

        $pendingLoansOnly = $request->has('pending') && $request->input('pending') == '1' ? 'Y' : null;

        $query = DB::table('sacco_loan_batch_trans_members AS trans')
    ->join('sacco_members AS members', 'trans.batch_trans_member_id', '=', 'members.member_id') // Loan applicant
    ->join('sacco_loan_types AS types', 'trans.batch_trans_loan_type', '=', 'types.loan_type_id') // Loan type
    ->leftJoin('sacco_loan_batch_guarantors_members AS guarantors', function ($join) {
        $join->on('trans.batch_trans_id', '=', 'guarantors.guarantors_loan_batch_trans_id')
             ->where('guarantors.guarantors_deleted', 'N'); // Exclude deleted guarantors
    })
 
    ->leftJoin('sacco_members AS g_members', 'guarantors.guarantors_guarantor_id', '=', 'g_members.member_id') // Guarantors' details
    ->select(
        'trans.*',
        'members.member_name', 
        'members.member_phone_no', 
        'members.member_national_id',
        'types.loan_type_name',
        DB::raw('GROUP_CONCAT(g_members.member_name ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_names'), // Ordered by guarantors_id
        DB::raw('GROUP_CONCAT(guarantors.guarantors_amount_guaranteed ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_amounts'), // Ordered by guarantors_id
        DB::raw('GROUP_CONCAT(guarantors.guarantors_approved ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_approval_status') // Ordered by guarantors_id
    )
     
    ->groupBy('trans.batch_trans_id');
     


    //     $query = DB::table('sacco_loan_batch_trans_members AS trans')
    // ->join('sacco_members AS members', 'trans.batch_trans_member_id', '=', 'members.member_id') // Loan applicant
    // ->join('sacco_loan_types AS types', 'trans.batch_trans_loan_type', '=', 'types.loan_type_id') // Loan type
    // ->leftJoin('sacco_loan_batch_guarantors_members AS guarantors', function ($join) {
    //     $join->on('trans.batch_trans_id', '=', 'guarantors.guarantors_loan_batch_trans_id')
    //          ->where('guarantors.guarantors_deleted', 'N'); // Exclude deleted guarantors
    // })
    // ->leftJoin('sacco_members AS g_members', 'guarantors.guarantors_guarantor_id', '=', 'g_members.member_id') // Guarantors' details
    // ->select(
    //     'trans.*',
    //     'members.member_name', 
    //     'members.member_phone_no', 
    //     'members.member_national_id',
    //     'types.loan_type_name',
    //     DB::raw('GROUP_CONCAT(DISTINCT g_members.member_name SEPARATOR "|") AS guarantors_names'), // Use "|" as separator
    //     DB::raw('GROUP_CONCAT(DISTINCT guarantors.guarantors_amount_guaranteed SEPARATOR "|") AS guarantors_amounts'), // Use "|" as separator
    //     DB::raw('GROUP_CONCAT(DISTINCT guarantors.guarantors_approved SEPARATOR "|") AS guarantors_approved') // Use "|" as separator
    // )
    // // ->where('trans.batch_trans_deleted', 'N') // Exclude deleted loan applications
    // ->groupBy('trans.batch_trans_id');

     
    
        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('members.member_name', 'LIKE', "%$search%")
                  ->orWhere('members.member_phone_no', 'LIKE', "%$search%")
                  ->orWhere('members.member_national_id', 'LIKE', "%$search%")
                  ->orWhere('types.loan_type_name', 'LIKE', "%$search%");
            });
        }

        if ($pendingLoansOnly) {
            $query->where('batch_trans_updated', 'N')
                       ->where('batch_trans_deleted', '!=', 'Y'); // Exclude rejected loans
        }
        

    
        // Fetch paginated loans (max 300 records, 20 per page)
        $loans = $query->orderBy('trans.batch_trans_on', 'desc')
                       ->limit(300)
                       ->paginate(20);
        
                       
        // Return the view with loans
        return view('loans.selfservice.pending_approval', compact('loans'));
    }
    
    public function approveLoan($id)
    {
        $logged_in_user = auth()->id();
        $transdate = now();
        $myIP = request()->ip();
    
        DB::beginTransaction();
        try {
            // Fetch current period
            $currentPeriod = DB::table('sacco_period')
                ->where('period_active', 'Y')
                ->where('period_deleted', '<>', 'Y')
                ->value('period_name');
    
            if (!$currentPeriod) {
                throw new \Exception('Active period not found.');
            }
    
            // Fetch the loan details
            $loan = DB::table('sacco_loan_batch_trans_members')
                ->join('sacco_loan_category', 'sacco_loan_batch_trans_members.batch_trans_loan_category', '=', 'sacco_loan_category.loan_category_id')
                ->join('sacco_loan_types', 'sacco_loan_batch_trans_members.batch_trans_loan_type', '=', 'sacco_loan_types.loan_type_id')
                ->join('sacco_members', 'sacco_loan_batch_trans_members.batch_trans_member_id', '=', 'sacco_members.member_id')
                ->where('batch_trans_id', $id)
                ->where('batch_trans_updated', '<>', 'Y')
                ->where('batch_trans_deleted', '<>', 'Y')
                ->first();
    
            if (!$loan) {
                throw new \Exception('Loan not found or already processed.');
            }
    
            // Check for default accounts
            $default_bank_account = $this->getDefaultAccount('default_bank_account');
            $default_insurance_account = $this->getDefaultAccount('default_insurance_account');
            $default_loan_commission_account = $this->getDefaultAccount('default_loan_commission_account');
    
            if (!$default_bank_account || !$default_insurance_account || !$default_loan_commission_account) {
                throw new \Exception('Missing default bank, insurance, or commission accounts.');
            }


            if ($loan->loan_type_guaranteable_percent > 0) {
                $guaranteeCheck = $this->isSufficientlyGuaranteed($loan, $loan->batch_trans_loan_amount);
            
                
                if (!$guaranteeCheck['is_fully_guaranteed']) {
                    $errorMessage = "Error: This loan application by <strong>{$loan->member_name}</strong> "
                                  . "is under-guaranteed.<br>"
                                  . "Total Guaranteed: <strong>" . number_format($guaranteeCheck['total_guaranteed'], 2) . "</strong><br>"
                                  . "Required Guarantee: <strong>" . number_format($guaranteeCheck['required_guarantee'], 2) . "</strong><br>"
                                  . "Deficit: <strong>" . number_format($guaranteeCheck['difference'], 2) . "</strong>";
            
                    return redirect()->back()->withErrors(['error' => $errorMessage]);
                }
            }
    if ($loan->loan_type_guaranteable_percent > 0) {
    $guaranteeCheck = $this->isSufficientlyGuaranteed($loan, $loan->batch_trans_loan_amount);

    if (!$guaranteeCheck['is_fully_guaranteed']) {
        $errorMessage = "Error: This loan application by <strong>{$loan->member_name}</strong> "
                      . "is under-guaranteed.<br>"
                      . "Total Guaranteed: <strong>" . number_format($guaranteeCheck['total_guaranteed'], 2) . "</strong><br>"
                      . "Required Guarantee: <strong>" . number_format($guaranteeCheck['required_guarantee'], 2) . "</strong><br>"
                      . "Deficit: <strong>" . number_format($guaranteeCheck['difference'], 2) . "</strong>";

        return redirect()->back()->withErrors(['error' => $errorMessage]);
    }
}

            // Check for sufficient guarantors
if ($loan->loan_type_guaranteable_percent > 0) {
    $guaranteeCheck = $this->isSufficientlyGuaranteed($loan, $loan->batch_trans_loan_amount);

    if (!$guaranteeCheck['is_fully_guaranteed']) {
        $errorMessage = "Error: This loan application by <strong>{$loan->member_name}</strong> "
                      . "is under-guaranteed.<br>"
                      . "Total Guaranteed: <strong>" . number_format($guaranteeCheck['total_guaranteed'], 2) . "</strong><br>"
                      . "Required Guarantee: <strong>" . number_format($guaranteeCheck['required_guarantee'], 2) . "</strong><br>"
                      . "Deficit: <strong>" . number_format($guaranteeCheck['difference'], 2) . "</strong>";
                      

        return redirect()->back()->withErrors(['error' => $errorMessage]);
    }
}
    
            // Prepare loan details
            $total_loan = $loan->batch_trans_loan_amount + $loan->batch_trans_insurance;
            $new_batch_no = "Self Applied Loan-" . $loan->batch_trans_id . "-" . $loan->member_name;
    
            // Insert loan record into sacco_loans
            $loan_id = DB::table('sacco_loans')->insertGetId([
                'loan_member' => $loan->batch_trans_member_id,
                'loan_loan_type' => $loan->batch_trans_loan_type,
                'loan_loan_category' => $loan->batch_trans_loan_category,
                'loan_amount' => $total_loan,
                'loan_insurance' => $loan->batch_trans_insurance,
                'loan_commision' => $loan->batch_trans_commission,
                'loan_taken_period' => $currentPeriod,
                'loan_payment_period' => $loan->batch_trans_loan_duration,
                'loan_interest_payable' => $loan->batch_trans_expected_interest,
                'loan_monthly_repayment_amount' => $loan->batch_trans_monthly_payment,
                'loan_amount_guaranteed' => $loan->batch_trans_loan_guaranteed,
                'loan_loan_paid' => 0,
                'loan_doc_no' => $loan->batch_trans_doc_no,
                'loan_description' => "Self Application - Batch No: " . $loan->batch_trans_id,
                'loan_batch_no' => $new_batch_no,
                'loan_start_deduction_period' => $currentPeriod,
                'loan_account_credited' => $default_bank_account,
                'loan_account_debited' => $loan->loan_type_acount,
                'loan_on' => now(),
                'loan_by' => $logged_in_user,
                'loan_ip' => $myIP,
                'loan_stoped' => 'N',
                'loan_taken_start_period' => $currentPeriod
            ]);
    
            // Update guarantors
            $guarantors = DB::table('sacco_loan_batch_guarantors_members')
                ->where('guarantors_loan_batch_trans_id', $loan->batch_trans_id)
                ->where('guarantors_deleted', 'N')
                ->get();
    
            foreach ($guarantors as $guarantor) {
                DB::table('sacco_loan_guarantors')->insert([
                    'loan_guar_loan_id' => $loan_id,
                    'loan_guar_guarantor_id' => $guarantor->guarantors_guarantor_id,
                    'loan_guar_amount_guaranteed' => $guarantor->guarantors_amount_guaranteed,
                    'loan_guar_description' => "Guarantor for loan approval via self-serve - {$loan->member_name}",
                    'loan_guar_by' => $logged_in_user,
                    'loan_guar_on' => $transdate,
                    'loan_guar_ip' => $myIP,
                    'loan_guar_deleted' => 'N'
                ]);
    
                // Update tied shares
                if ($guarantor->guarantors_guarantor_id == $loan->batch_trans_member_id) {
                    DB::table('sacco_members')
                        ->where('member_id', $guarantor->guarantors_guarantor_id)
                        ->increment('member_tied_shares_self', $guarantor->guarantors_amount_guaranteed);
                } else {
                    DB::table('sacco_members')
                        ->where('member_id', $guarantor->guarantors_guarantor_id)
                        ->increment('member_tied_shares', $guarantor->guarantors_amount_guaranteed);
                }
            }
    
            // Update member's total loan
            DB::table('sacco_members')
                ->where('member_id', $loan->batch_trans_member_id)
                ->increment('member_total_loan', $total_loan);
    
            // Update loan status
            DB::table('sacco_loan_batch_trans_members')
                ->where('batch_trans_id', $id)
                ->update(['batch_trans_updated' => 'Y']);
    
            DB::commit();
    
            session()->flash('success', 'Loan successfully approved.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error: ' . $e->getMessage()]);
        }
    
        return redirect()->back();
    }

    public function rejectLoan($id)
    {
        $logged_in_user = auth()->id();
        $transdate = now();
        $myIP = request()->ip();
    
        // Start transaction
        DB::beginTransaction();
        try {
            // Fetch loan details
            $loan = DB::table('sacco_loan_batch_trans_members')
                ->where('batch_trans_id', $id)
                ->where('batch_trans_deleted', '<>', 'Y')
                ->where('batch_trans_updated', '<>', 'Y')
                ->first();
    
            if (!$loan) {
                throw new \Exception('Loan not found or already processed.');
            }
    
            // Mark loan as deleted
            DB::table('sacco_loan_batch_trans_members')
                ->where('batch_trans_id', $id)
                ->update([
                    'batch_trans_deleted' => 'Y',
                    'batch_trans_deleted_by' => $logged_in_user,
                    'batch_trans_deleted_on' => $transdate,
                    'batch_trans_deleted_ip' => $myIP
                ]);
    
            // Commit transaction
            DB::commit();
    
            session()->flash('success', 'Loan successfully rejected.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    
        return redirect()->back();
    }
private function getDefaultAccount($account_name)
{
    return DB::table('sacco_defaults')
        ->where('default_name', $account_name)
        ->value('default_value');
}
private function isSufficientlyGuaranteed($loan, $loan_amount)
{
    // Fetch total amount guaranteed by all approved guarantors
    $total_guaranteed = DB::table('sacco_loan_batch_guarantors_members')
        ->where('guarantors_loan_batch_trans_id', $loan->batch_trans_id)
        ->where('guarantors_deleted', 'N') // Active guarantors
        ->where('guarantors_approved', 'Y') // Approved guarantors
        ->sum('guarantors_amount_guaranteed');

    // Calculate the required guaranteed amount
    $required_guarantee = ($loan->loan_type_guaranteable_percent / 100) * $loan_amount;

    // Check if the variance is within the range of ±1
    $is_fully_guaranteed = abs($total_guaranteed - $required_guarantee) <= 1;

    return [
        'is_fully_guaranteed' => $is_fully_guaranteed,
        'total_guaranteed' => $total_guaranteed,
        'required_guarantee' => $required_guarantee,
        'difference' => max(0, $required_guarantee - $total_guaranteed),
    ];
}
// private function isSufficientlyGuaranteed($loan, $loan_amount)
// {
//     // Fetch total amount guaranteed by all approved guarantors
//     $total_guaranteed = DB::table('sacco_loan_batch_guarantors_members')
//         ->where('guarantors_loan_batch_trans_id', $loan->batch_trans_id)
//         ->where('guarantors_deleted', 'N') // Active guarantors
//         ->where('guarantors_approved', 'Y') // Approved guarantors
//         ->sum('guarantors_amount_guaranteed');

//     // Calculate the required guaranteed amount
//     $required_guarantee = ($loan->loan_type_guaranteable_percent / 100) * $loan_amount;

//     return [
//         'is_fully_guaranteed' => $total_guaranteed >= $required_guarantee,
//         'total_guaranteed' => $total_guaranteed,
//         'required_guarantee' => $required_guarantee,
//         'difference' => max(0, $required_guarantee - $total_guaranteed),
//     ];
// }
private function updateLedgerEntries($loan, $default_bank_account, $default_insurance_account, $default_loan_commission_account, $new_batch_no, $total_loan, $logged_in_user, $myIP, $transdate, $currentPeriod)
{
    $this->updateSaccoAccountsTrans($default_bank_account, 0, $loan->batch_trans_loan_amount - $loan->batch_trans_commission, $new_batch_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Approved");

    $this->updateSaccoAccountsTrans($default_insurance_account, 0, $loan->batch_trans_insurance, $new_batch_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Insurance");

    $this->updateSaccoAccountsTrans($default_loan_commission_account, 0, $loan->batch_trans_commission, $new_batch_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Commission");

    $this->updateSaccoAccountsTrans($loan->loan_type_acount, $total_loan, 0, $new_batch_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Account");
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
        'accounts_trans_user_id' => auth()->id(),
        'accounts_trans_ip' => request()->ip(),
        'accounts_trans_source' => $sourceDescription,
        'accounts_trans_app_name' => 'iSacco',
    ]);

    // Update cumulative totals
    DB::table('sacco_sub_account')
        ->where('sub_account_id', $subAccountId)
        ->increment('sub_account_debit', $debit);
    DB::table('sacco_sub_account')
        ->where('sub_account_id', $subAccountId)
        ->increment('sub_account_credit', $credit);
}

}
