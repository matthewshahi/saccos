<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;



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
    

    public function listLoansPendingApprovalSelf(Request $request)
        {
            $pendingLoansOnly = $request->has('pending') && $request->input('pending') == '1' ? 'Y' : null;

            $loggedInMemberId = Auth::user()->member_id; // Get the logged-in user's member_id

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
                ->where('members.member_id', $loggedInMemberId) // Filter by logged-in user's member_id
                ->groupBy('trans.batch_trans_id');

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

            // Filter pending loans
            if ($pendingLoansOnly) {
                $query->where('batch_trans_updated', 'N')
                    ->where('batch_trans_deleted', '!=', 'Y'); // Exclude rejected loans
            }

            // Apply ordering and paginate
            $loans = $query->orderBy('trans.batch_trans_on', 'desc') // Ensure orderBy is before paginate
                        ->paginate(20); // Fetch paginated results

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

public function loansApply()
{
    $loanTypes = DB::table('sacco_loan_types')
        ->where('loan_type_deleted', '<>', 'Y')
        ->orderBy('loan_type_name')
        ->get();

    $loanCategories = DB::table('sacco_loan_category')
        ->where('loan_category_deleted', '<>', 'Y')
        ->orderBy('loan_category_name')
        ->get();

    $maximumNoOfGuarantors = DB::table('sacco_defaults')
        ->where('default_name', 'maximum_no_of_guarantors')
        ->value('default_value');
 
    $memberLoans = DB::table('sacco_loans')
        ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
        ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', DB::raw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) as loan_balance'))
        ->where('sacco_loans.loan_member', auth()->user()->id)
        ->where('sacco_loans.loan_amount', '>', DB::raw('sacco_loans.loan_loan_paid'))
        ->where('sacco_loans.loan_stoped', 'N')
        ->get();

    return view('loans.apply', compact('loanTypes', 'loanCategories', 'maximumNoOfGuarantors', 'memberLoans'));
}
// public function listLoansPendingApprovalSelfedit($id)
// {
//     // Get the logged-in user's member ID
//     $loggedInMemberId = auth()->user()->member_id;

//     // Fetch the loan application details with joins
//     $loan = DB::table('sacco_loan_batch_trans_members AS trans')
//         ->join('sacco_members AS members', 'trans.batch_trans_member_id', '=', 'members.member_id')
//         ->join('sacco_loan_types AS types', 'trans.batch_trans_loan_type', '=', 'types.loan_type_id')
//         ->leftJoin('sacco_loan_category AS categories', 'trans.batch_trans_loan_category', '=', 'categories.loan_category_id')
//         ->select(
//             'trans.*',
//             'members.member_name',
//             'members.member_sacco_id',
//             'types.loan_type_name',
//             'categories.loan_category_name'
//         )
//         ->where('trans.batch_trans_id', $id)
//         ->where('trans.batch_trans_member_id', $loggedInMemberId) // Restrict to the logged-in user's loan
//         ->first();

//     // Check if loan exists and belongs to the user
//     if (!$loan) {
//         return redirect()->route('loans.pending.approval')->with('error', 'Loan not found or unauthorized access.');
//     }

//     // Fetch loan types, categories, guarantor limits, and member loans
//     $loanTypes = DB::table('sacco_loan_types')
//         ->where('loan_type_deleted', '<>', 'Y')
//         ->orderBy('loan_type_name')
//         ->get();

//     $loanCategories = DB::table('sacco_loan_category')
//         ->where('loan_category_deleted', '<>', 'Y')
//         ->orderBy('loan_category_name')
//         ->get();

//     $maximumNoOfGuarantors = DB::table('sacco_defaults')
//         ->where('default_name', 'maximum_no_of_guarantors')
//         ->value('default_value');

//     $memberLoans = DB::table('sacco_loans')
//         ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
//         ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', DB::raw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) as loan_balance'))
//         ->where('sacco_loans.loan_member', $loggedInMemberId)
//         ->where('sacco_loans.loan_amount', '>', DB::raw('sacco_loans.loan_loan_paid'))
//         ->where('sacco_loans.loan_stoped', 'N')
//         ->get();

//     // Fetch guarantors for the loan
//     $guarantors = DB::table('sacco_loan_batch_guarantors_members AS guarantors')
//         ->join('sacco_members AS members', 'guarantors.guarantors_guarantor_id', '=', 'members.member_id')
//         ->select(
//             'guarantors.guarantors_id',
//             'members.member_name',
//             'guarantors.guarantors_amount_guaranteed',
//             'guarantors.guarantors_approved'
//         )
//         ->where('guarantors.guarantors_loan_batch_trans_id', $id)
//         ->where('guarantors.guarantors_deleted', '<>', 'Y') // Exclude deleted guarantors
//         ->get();

//     return view('loans.selfedit', compact('loan', 'loanTypes', 'loanCategories', 'maximumNoOfGuarantors', 'memberLoans', 'guarantors'));
// }
public function listLoansPendingApprovalSelfedit($id)
{
    // Get the logged-in user's member ID
    $loggedInMemberId = auth()->user()->member_id;

    // Fetch the loan application details with join

    $loan = DB::table('sacco_loan_batch_trans_members AS trans')
        ->join('sacco_members AS members', 'trans.batch_trans_member_id', '=', 'members.member_id')
        ->join('sacco_loan_types AS types', 'trans.batch_trans_loan_type', '=', 'types.loan_type_id')
        ->leftJoin('sacco_loan_category AS categories', 'trans.batch_trans_loan_category', '=', 'categories.loan_category_id')
        ->select(
            'trans.*',
            'members.member_name',
            'members.member_sacco_id',
            'types.loan_type_name',
            'categories.loan_category_name'
        )
        ->where('trans.batch_trans_id', $id)
        ->where('trans.batch_trans_member_id', $loggedInMemberId) // Restrict to the logged-in user's loan
        ->first();

      
    // Check if loan exists and belongs to the user
    if (!$loan) {
        return redirect()->route('loans.pending.approval')->with('error', 'Loan not found or unauthorized access.');
    }

    // Fetch loan types
    $loanTypes = DB::table('sacco_loan_types')
        ->where('loan_type_deleted', '<>', 'Y')
        ->orderBy('loan_type_name')
        ->get();

    // Fetch loan categories
    $loanCategories = DB::table('sacco_loan_category')
        ->where('loan_category_deleted', '<>', 'Y')
        ->orderBy('loan_category_name')
        ->get();

    // Fetch the maximum number of guarantors
    $maximumNoOfGuarantors = DB::table('sacco_defaults')
        ->where('default_name', 'maximum_no_of_guarantors')
        ->value('default_value') ?? 3; // Default to 3 if not set

    // Fetch member loans
    $memberLoans = DB::table('sacco_loans')
        ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
        ->select(
            'sacco_loans.loan_id',
            'sacco_loans.loan_amount',
            'sacco_loans.loan_loan_paid',
            'sacco_loan_types.loan_type_name',
            DB::raw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) as loan_balance')
        )
        ->where('sacco_loans.loan_member', $loggedInMemberId)
        ->whereRaw('sacco_loans.loan_amount > sacco_loans.loan_loan_paid') // Ensure there is a balance
        ->where('sacco_loans.loan_stoped', 'N') // Ensure loan is active
        ->get();

    // Fetch guarantors for the loan
    $guarantors = DB::table('sacco_loan_batch_guarantors_members AS guarantors')
        ->join('sacco_members AS members', 'guarantors.guarantors_guarantor_id', '=', 'members.member_id')
        ->select(
            'guarantors.guarantors_id',
            'members.member_name',
            'guarantors.guarantors_amount_guaranteed',
            'guarantors.guarantors_approved'
        )
        ->where('guarantors.guarantors_loan_batch_trans_id', $id)
        ->where('guarantors.guarantors_deleted', '<>', 'Y') // Exclude deleted guarantors
        ->get();


    // Return the self-edit view with the fetched data
    
    return view('loans.selfedit', compact('loan', 'loanTypes', 'loanCategories', 'maximumNoOfGuarantors', 'memberLoans', 'guarantors'));
}
public function updateLoanApplication(Request $request, $id)
{
    // Validate input
    $request->validate([
        'batch_trans_loan_amount' => 'required|numeric|min:1',
        'batch_trans_loan_type' => 'required|exists:sacco_loan_types,loan_type_id',
        'batch_trans_loan_category' => 'required|exists:sacco_loan_category,loan_category_id',
        'batch_trans_description' => 'required|string|max:50',
        'batch_trans_pay1' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
        'batch_trans_pay2' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
    ]);

    // Fetch the loan
    $loan = DB::table('sacco_loan_batch_trans_members')->where('batch_trans_id', $id)->first();
    if (!$loan) {
        return redirect()->back()->withErrors('Loan not found.');
    }

    // Handle file uploads
    $payslip1Path = $loan->batch_trans_payslip1;
    if ($request->hasFile('batch_trans_pay1')) {
        $payslip1Path = $request->file('batch_trans_pay1')->store('uploads/payslips');
    }

    $payslip2Path = $loan->batch_trans_payslip2;
    if ($request->hasFile('batch_trans_pay2')) {
        $payslip2Path = $request->file('batch_trans_pay2')->store('uploads/payslips');
    }

    // Update loan
    DB::table('sacco_loan_batch_trans_members')
        ->where('batch_trans_id', $id)
        ->update([
            'batch_trans_loan_amount' => $request->batch_trans_loan_amount,
            'batch_trans_loan_type' => $request->batch_trans_loan_type,
            'batch_trans_loan_category' => $request->batch_trans_loan_category,
            'batch_trans_description' => $request->batch_trans_description,
            'batch_trans_payslip1' => $payslip1Path,
            'batch_trans_payslip2' => $payslip2Path,
        ]);

    return redirect()->route('loans.pending.approval.selfedit', ['id' => $id])
        ->with('success', 'Loan updated successfully.');
}
public function deleteGuarantor($id)
{
    // Retrieve the guarantor record
    $guarantor = DB::table('sacco_loan_batch_guarantors_members')
        ->join('sacco_loan_batch_trans_members', 'sacco_loan_batch_guarantors_members.guarantors_loan_batch_trans_id', '=', 'sacco_loan_batch_trans_members.batch_trans_id')
        ->where('sacco_loan_batch_guarantors_members.guarantors_id', $id)
        ->select('sacco_loan_batch_guarantors_members.*', 'sacco_loan_batch_trans_members.batch_trans_member_id')
        ->first();

    // Check if the guarantor exists
    if (!$guarantor) {
        return redirect()->back()->with('error', 'Guarantor not found.');
    }

    // Check if the logged-in user owns the loan to which this guarantor is attached
    if ($guarantor->batch_trans_member_id != auth()->id()) {
        return redirect()->back()->with('error', 'You cannot delete a guarantor for someone else\'s loan.');
    }

    // Perform the soft delete by updating the necessary fields
    DB::table('sacco_loan_batch_guarantors_members')
        ->where('guarantors_id', $id)
        ->update([
            'guarantors_deleted' => 'Y',
            'guarantors_deleted_by' => auth()->id(),
            'guarantors_deleted_on' => now(),
            'guarantors_deleted_ip' => request()->ip(),
        ]);

    return redirect()->back()->with('success', 'Guarantor marked as deleted successfully.');
}

public function processLoanApplication(Request $request)
{
    $logged_in_user = auth()->id();
    $nmsg = '';

    // Validation rules
    $request->validate([
        'batch_trans_member_id' => 'required|exists:sacco_members,member_id',
        'batch_trans_member_name' => 'required|string',
        'batch_trans_loan_amount' => 'required|numeric|min:1',
        'batch_trans_id' => 'required|numeric|min:1',
        'batch_trans_loan_type' => 'required|exists:sacco_loan_types,loan_type_id',
        'batch_trans_loan_category' => 'required|exists:sacco_loan_category,loan_category_id',
        'batch_trans_loan_duration' => 'required|integer|min:1|max:100',
        'batch_trans_description' => 'required|string|max:50',
        'batch_trans_loan_to_top_up' => 'nullable|integer|exists:sacco_loans,loan_id',
        'batch_trans_pay1' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
        'batch_trans_pay2' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
    ]);


    $data = $request->all();
    
    $batch_trans_id = $data['batch_trans_id']; // Extract the batch_trans_id
    $loanAmount = floatval($data['batch_trans_loan_amount']);
    

    // Check if the member ID matches the logged-in user
    if ($data['batch_trans_member_id'] != $logged_in_user) {
        return redirect()->back()->withErrors(['error' => 'Unauthorized access.']);
    }

    // Validate loan category and type
    $loanCategory = DB::table('sacco_loan_category')->where('loan_category_id', $data['batch_trans_loan_category'])->first();
    $loanType = DB::table('sacco_loan_types')->where('loan_type_id', $data['batch_trans_loan_type'])->first();

    if (!$loanCategory) {
        $nmsg .= "Invalid loan category. ";
    }
    if (!$loanType) {
        $nmsg .= "Invalid loan type. ";
    }

    // Validate the member
    $member = DB::table('sacco_members')
        ->where('member_id', $data['batch_trans_member_id'])
        ->where('member_active', 'Y')
        ->where('member_deleted', '<>', 'Y')
        ->first();

    if (!$member) {
        $nmsg .= "Invalid member. ";
    } elseif (strtotime($member->member_date_joined) > strtotime("-{$loanType->loan_type_qualification_period} months")) {
        $nmsg .= "Member must be {$loanType->loan_type_qualification_period} months old to take this loan. ";
    }


    // Check for deleted or updated loans
    $existingLoan = DB::table('sacco_loan_batch_trans_members')
        ->where('batch_trans_id', $data['batch_trans_id'])
        ->where('batch_trans_deleted', '<>', 'Y')
        ->where('batch_trans_updated', '<>', 'Y')
        ->first();

    if (!$existingLoan) {
        return redirect()->back()->withErrors(['error' => 'Loan record not found, already updated, or deleted.']);
    }
    
    // Validate loan amount
    $loanAmount = floatval($data['batch_trans_loan_amount']);
    if ($loanAmount < 1 || $loanAmount > $loanType->loan_type_max_amount) {
        $nmsg .= "Invalid loan amount. ";
    }

    // Check for loan top-up logic
    if (!empty($data['batch_trans_loan_to_top_up'])) {
        $topupLoan = DB::table('sacco_loans')
            ->where('loan_id', $data['batch_trans_loan_to_top_up'])
            ->where('loan_member', $data['batch_trans_member_id'])
            ->first();

        if (!$topupLoan || $loanAmount <= ($topupLoan->loan_amount - $topupLoan->loan_loan_paid)) {
            $nmsg .= "Invalid top-up loan. ";
        }
    }

    
    
 


    // Check for errors
    if (!empty($nmsg)) {
        return redirect()->back()->withErrors($nmsg)->withInput();
    }

   // Handle file uploads
$payslip1Path = null;
$payslip2Path = null;

if ($request->hasFile('batch_trans_pay1')) {
    $payslip1 = $request->file('batch_trans_pay1');
    $payslip1Path = $payslip1->storeAs(
        'payslips',
        'payslip1_' . auth()->user()->id . '_' . time() . '.' . $payslip1->getClientOriginalExtension()
    );
}

if ($request->hasFile('batch_trans_pay2')) {
    $payslip2 = $request->file('batch_trans_pay2');
    $payslip2Path = $payslip2->storeAs(
        'payslips',
        'payslip2_' . auth()->user()->id . '_' . time() . '.' . $payslip2->getClientOriginalExtension()
    );
}
if (empty($nmsg)) {
    $guarantorValidation = $this->validateAndProcessGuarantors($data, $loanType, $batch_trans_id, $loanAmount);

    if (!$guarantorValidation['success']) {
        return redirect()->back()->withErrors($guarantorValidation['message'])->withInput();
    }
}
// Update loan record
$loanUpdateData = [
    'batch_trans_loan_type' => $data['batch_trans_loan_type'],
    'batch_trans_loan_category' => $data['batch_trans_loan_category'],
    'batch_trans_loan_amount' => $data['batch_trans_loan_amount'],
    'batch_trans_loan_duration' => $data['batch_trans_loan_duration'],
    'batch_trans_description' => $data['batch_trans_description'],
    'batch_trans_updated' => 'N',
    'batch_trans_by' => $logged_in_user,
    'batch_trans_ip' => $request->ip(),
];

// Add file paths to the update data if files were uploaded
if ($payslip1Path) {
    $loanUpdateData['batch_trans_payslip1'] = $payslip1Path;
}
if ($payslip2Path) {
    $loanUpdateData['batch_trans_payslip2'] = $payslip2Path;
}

// Update the loan record in the database
DB::table('sacco_loan_batch_trans_members')
    ->where('batch_trans_id', $data['batch_trans_id'])
    ->update($loanUpdateData);

// Update guarantors




    return redirect()->route('loans.apply')->with('success', 'Loan application submitted successfully.');
}
private function validateAndProcessGuarantors($data, $loanType, $batch_trans_id, $loanAmount)
{
    $nmsg = "";
    $guarantors = [];
    $totalGuaranteed = 0;

    // Fetch defaults for max guarantors and guarantor factor
    $maximumNoOfGuarantors = DB::table('sacco_defaults')->where('default_name', 'maximum_no_of_guarantors')->value('default_value') ?? 3;
    $max_guarantor_factor = DB::table('sacco_defaults')->where('default_name', 'max_guarantor_factor')->value('default_value') ?? 1;

    // Get existing guarantors for the loan
    $existingGuarantors = DB::table('sacco_loan_batch_guarantors_members')
        ->where('guarantors_loan_batch_trans_id', $batch_trans_id)
        ->where('guarantors_deleted', '<>', 'Y')
        ->get();

    $existingGuarantorIds = $existingGuarantors->pluck('guarantors_guarantor_id')->toArray();

    // Add amounts from existing guarantors to the total guarantee
    foreach ($existingGuarantors as $existingGuarantor) {
        $totalGuaranteed += $existingGuarantor->guarantors_amount_guaranteed;
    }

    // Loop through new guarantors provided in the form
    for ($i = 0; $i < $maximumNoOfGuarantors; $i++) {
        $guarantorName = $data['guarantors'][$i]['name'] ?? null;
        $guarantorAmount = !empty($data['guarantors'][$i]['amount']) ? floatval($data['guarantors'][$i]['amount']) : 0;

        if (!empty($guarantorName)) {
            // Extract and validate guarantor details
            $guarantor = DB::table('sacco_members')
                ->where('member_name', explode(" - (", $guarantorName)[0])
                ->where('member_sacco_id', trim(explode(" - (", $guarantorName)[1], ")"))
                ->where('member_active', 'Y')
                ->where('member_deleted', '<>', 'Y')
                ->first();

            if (!$guarantor) {
                $nmsg .= "Error: Guarantor {$guarantorName} not found or inactive. ";
                continue;
            }

            // Ensure no duplicate guarantors for the same loan
            if (in_array($guarantor->member_id, $existingGuarantorIds)) {
                $nmsg .= "Error: Guarantor {$guarantorName} has already guaranteed this loan. ";
                continue;
            }

            // Check if guarantor is guaranteeing another member
            if ($data['batch_trans_member_id'] != $guarantor->member_id) {
                if (($guarantor->member_total_share * $max_guarantor_factor - $guarantor->member_tied_shares) < $guarantorAmount) {
                    $nmsg .= "Error: Guarantor {$guarantorName} does not have enough free shares to guarantee this amount. ";
                    continue;
                }
            } else {
                // Check if guarantor is self-guaranteeing
                if (($guarantor->member_total_share - $guarantor->member_tied_shares_self) < $guarantorAmount) {
                    $nmsg .= "Error: Guarantor {$guarantorName} does not have enough free shares to self-guarantee this amount. ";
                    continue;
                }
            }

            // Add guarantor details to the array
            $guarantors[] = [
                'id' => $guarantor->member_id,
                'amount' => $guarantorAmount,
            ];
            $totalGuaranteed += $guarantorAmount;
        }
    }

    // Under-guarantee check
    $batch_trans_loan_guaranteed = $loanAmount * $loanType->loan_type_guaranteable_percent / 100;

  

    // Under-guarantee check
    $batch_trans_loan_guaranteed = $loanAmount * $loanType->loan_type_guaranteable_percent / 100;

    if ($loanType->loan_type_guaranteable_percent > 0) {
        if ($batch_trans_loan_guaranteed > $totalGuaranteed) {
            $nmsg .= "Error: This loan application is under-guaranteed. Total guaranteed must be at least {$batch_trans_loan_guaranteed}. ";
        } elseif ($totalGuaranteed > $batch_trans_loan_guaranteed) {
            // Prorate all guarantors (existing + new)
            $prorationFactor = $batch_trans_loan_guaranteed / $totalGuaranteed;
    
            // Adjust amounts for existing guarantors
            $proratedGuarantors = [];
            foreach ($existingGuarantors as $existingGuarantor) {
                $proratedGuarantors[] = [
                    'id' => $existingGuarantor->guarantors_guarantor_id,
                    'amount' => round($existingGuarantor->guarantors_amount_guaranteed * $prorationFactor, 2),
                ];
            }
    
            // Adjust amounts for new guarantors
            foreach ($guarantors as $guarantor) {
                $proratedGuarantors[] = [
                    'id' => $guarantor['id'],
                    'amount' => round($guarantor['amount'] * $prorationFactor, 2),
                ];
            }
    
            // Recalculate the total after rounding
            $proratedTotal = array_sum(array_column($proratedGuarantors, 'amount'));
    
            // Adjust the last guarantor to fix any rounding errors
            if ($proratedTotal !== $batch_trans_loan_guaranteed) {
                $difference = $batch_trans_loan_guaranteed - $proratedTotal;
                $proratedGuarantors[count($proratedGuarantors) - 1]['amount'] += $difference;
            }
    
            // Replace original guarantors with prorated ones
            $guarantors = $proratedGuarantors;
        }
    }


    // Return validation errors if any
    if (!empty($nmsg)) {
        return ['success' => false, 'message' => $nmsg];
    }

    // Update or insert new guarantors into the database
    foreach ($guarantors as $guarantor) {
        DB::table('sacco_loan_batch_guarantors_members')->updateOrInsert(
            [
                'guarantors_loan_batch_trans_id' => $batch_trans_id,
                'guarantors_guarantor_id' => $guarantor['id'],
            ],
            [
                'guarantors_amount_guaranteed' => $guarantor['amount'],
                'guarantors_by' => auth()->id(),
                'guarantors_ip' => request()->ip(),
                'guarantors_deleted' => 'N',
                'guarantors_approved' => 'N', // Set all as 'N'
                'guarantors_email_sent'=> 'N', // Set all as 'N'
            ]
        );
    }

    return ['success' => true, 'message' => 'Guarantors validated and updated successfully.'];
}
}

