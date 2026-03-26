<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LoanApplicationSelfServiceController extends Controller
{
    public function listLoansPendingApproval(Request $request)
{
    $pendingLoansOnly = $request->has('pending') && $request->input('pending') == '1' ? 'Y' : null;

    $query = DB::table('sacco_loan_batch_trans_members AS trans')
        ->join('sacco_members AS members', 'trans.batch_trans_member_id', '=', 'members.member_id')
        ->join('sacco_loan_types AS types', 'trans.batch_trans_loan_type', '=', 'types.loan_type_id')
        ->join('sacco_loan_category AS category', 'trans.batch_trans_loan_category', '=', 'category.loan_category_id')
        ->leftJoin('sacco_loan_batch_guarantors_members AS guarantors', function ($join) {
            $join->on('trans.batch_trans_id', '=', 'guarantors.guarantors_loan_batch_trans_id')
                ->where('guarantors.guarantors_deleted', 'N');
        })
        ->leftJoin('sacco_members AS g_members', 'guarantors.guarantors_guarantor_id', '=', 'g_members.member_id')
        ->select(
            'trans.*',
            'members.*',
            'types.loan_type_name',
            'category.loan_category_name',
            DB::raw('GROUP_CONCAT(g_members.member_name ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_names'),
            DB::raw('GROUP_CONCAT(guarantors.guarantors_amount_guaranteed ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_amounts'),
            DB::raw('GROUP_CONCAT(guarantors.guarantors_approved ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_approval_status')
        )
        ->groupBy('trans.batch_trans_id');

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
            ->where('batch_trans_deleted', '!=', 'Y');
    }

    $loans = $query->orderBy('trans.batch_trans_on', 'desc')
        ->limit(300)
        ->paginate(20);

    $deductionTypes = DB::table('sacco_loan_deductions_types')
        ->where('deduction_type_deleted', 'N')
        ->where('deduction_type_active', 1)
        ->orderBy('deduction_type_name', 'asc')
        ->get();

    

    $loanIds = collect($loans->items())
    ->pluck('batch_trans_id')
    ->filter()
    ->values();

    $applicationCharges = collect();

    if ($loanIds->isNotEmpty()) {
        $applicationCharges = DB::table('sacco_loan_batch_trans_members_deductions')
            ->whereIn('batch_trans_deduction_batch_trans_id', $loanIds)
            ->where('batch_trans_deduction_deleted', 'N')
            ->orderBy('batch_trans_deduction_batch_trans_id', 'asc')
            ->orderBy('batch_trans_deduction_id', 'asc')
            ->get()
            ->groupBy('batch_trans_deduction_batch_trans_id');
    }

    return view('loans.selfservice.pending_approval', compact('loans', 'deductionTypes', 'applicationCharges'));
}


    public function listLoansPendingApprovalSelf(Request $request)
{
    $pendingLoansOnly = $request->has('pending') && $request->input('pending') == '1' ? 'Y' : null;

    $loggedInMemberId = Auth::user()->member_id;

    $query = DB::table('sacco_loan_batch_trans_members AS trans')
        ->join('sacco_members AS members', 'trans.batch_trans_member_id', '=', 'members.member_id')
        ->join('sacco_loan_types AS types', 'trans.batch_trans_loan_type', '=', 'types.loan_type_id')
        ->join('sacco_loan_category AS categories', 'trans.batch_trans_loan_category', '=', 'categories.loan_category_id')
        ->leftJoin('sacco_loan_batch_guarantors_members AS guarantors', function ($join) {
            $join->on('trans.batch_trans_id', '=', 'guarantors.guarantors_loan_batch_trans_id')
                ->where('guarantors.guarantors_deleted', 'N');
        })
        ->leftJoin('sacco_members AS g_members', 'guarantors.guarantors_guarantor_id', '=', 'g_members.member_id')
        ->select(
            'trans.*',
            'members.*',
            'types.loan_type_name',
            'categories.loan_category_name',
            DB::raw('GROUP_CONCAT(g_members.member_name ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_names'),
            DB::raw('GROUP_CONCAT(guarantors.guarantors_amount_guaranteed ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_amounts'),
            DB::raw('GROUP_CONCAT(guarantors.guarantors_approved ORDER BY guarantors.guarantors_id ASC SEPARATOR "|") AS guarantors_approval_status')
        )
        ->where('members.member_id', $loggedInMemberId)
        ->groupBy('trans.batch_trans_id');

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
            ->where('batch_trans_deleted', '!=', 'Y');
    }

    $loans = $query->orderBy('trans.batch_trans_on', 'desc')
        ->paginate(20);

    $deductionTypes = DB::table('sacco_loan_deductions_types')
        ->where('deduction_type_deleted', 'N')
        ->where('deduction_type_active', 1)
        ->orderBy('deduction_type_name', 'asc')
        ->get();

    $loanIds = $loans->getCollection()
        ->pluck('batch_trans_id')
        ->filter()
        ->values();

    $applicationCharges = collect();

    if ($loanIds->isNotEmpty()) {
        $applicationCharges = DB::table('sacco_loan_batch_trans_members_deductions')
            ->whereIn('batch_trans_deduction_batch_trans_id', $loanIds)
            ->where('batch_trans_deduction_deleted', 'N')
            ->orderBy('batch_trans_deduction_batch_trans_id', 'asc')
            ->orderBy('batch_trans_deduction_id', 'asc')
            ->get()
            ->groupBy('batch_trans_deduction_batch_trans_id');
    }

    return view('loans.selfservice.pending_approval', compact('loans', 'deductionTypes', 'applicationCharges'));
}

public function approveLoan($id)
{
    $logged_in_user = auth()->id();
    $transdate = now();
    $myIP = request()->ip();

    DB::beginTransaction();

    try {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->value('period_name');

        if (!$currentPeriod) {
            throw new \Exception('Active period not found.');
        }

        $loan = DB::table('sacco_loan_batch_trans_members')
            ->join('sacco_loan_category', 'sacco_loan_batch_trans_members.batch_trans_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->join('sacco_loan_types', 'sacco_loan_batch_trans_members.batch_trans_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_members', 'sacco_loan_batch_trans_members.batch_trans_member_id', '=', 'sacco_members.member_id')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_updated', '<>', 'Y')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->select(
                'sacco_loan_batch_trans_members.*',
                'sacco_loan_types.loan_type_name',
                'sacco_loan_types.loan_type_acount',
                'sacco_loan_types.loan_type_guaranteable_percent',
                'sacco_loan_types.loan_type_commission_effect',
                'sacco_loan_types.loan_type_insurance_effect',
                'sacco_loan_category.loan_category_name',
                'sacco_members.member_name'
            )
            ->first();

        if (!$loan) {
            throw new \Exception('Loan not found or already processed.');
        }

        $default_bank_account = $this->getDefaultAccount('default_bank_account');
        $default_insurance_account = $this->getDefaultAccount('default_insurance_account');
        $default_loan_commission_account = $this->getDefaultAccount('default_loan_commission_account');

        if (!$default_bank_account || !$default_insurance_account || !$default_loan_commission_account) {
            throw new \Exception('Missing default bank, insurance, or commission accounts.');
        }

        if ((float) ($loan->loan_type_guaranteable_percent ?? 0) > 0) {
            $guaranteeCheck = $this->isSufficientlyGuaranteed($loan, $loan->batch_trans_loan_amount);

            if (!$guaranteeCheck['is_fully_guaranteed']) {
                $errorMessage = "Error: This loan application by <strong>{$loan->member_name}</strong> "
                    . "is under-guaranteed.<br>"
                    . "Total Guaranteed: <strong>" . number_format($guaranteeCheck['total_guaranteed'], 2) . "</strong><br>"
                    . "Required Guarantee: <strong>" . number_format($guaranteeCheck['required_guarantee'], 2) . "</strong><br>"
                    . "Deficit: <strong>" . number_format($guaranteeCheck['difference'], 2) . "</strong>";

                DB::rollBack();
                return redirect()->back()->withErrors(['error' => $errorMessage]);
            }
        }

        $chargeSummary = $this->getSelfServiceChargeSummary($loan->batch_trans_id);

        $requestedAmount = round((float) $loan->batch_trans_loan_amount, 2);
        $insurance = round((float) ($loan->batch_trans_insurance ?? 0), 2);
        $commission = round((float) ($loan->batch_trans_commission ?? 0), 2);

        $totalAddToLoan = round((float) ($chargeSummary['add_to_loan'] ?? 0), 2);
        $totalDeductFromDisbursement = round((float) ($chargeSummary['deduct_from_disbursement'] ?? 0), 2);

        $commissionEffect = strtoupper(trim((string) ($loan->loan_type_commission_effect ?? 'ADD_TO_LOAN')));
        if (!in_array($commissionEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
            $commissionEffect = 'ADD_TO_LOAN';
        }

        $insuranceEffect = strtoupper(trim((string) ($loan->loan_type_insurance_effect ?? 'ADD_TO_LOAN')));
        if (!in_array($insuranceEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
            $insuranceEffect = 'ADD_TO_LOAN';
        }

        $commissionAddedToLoan = $commissionEffect === 'ADD_TO_LOAN' ? $commission : 0.00;
        $commissionDeductedFromDisbursement = $commissionEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $commission : 0.00;

        $insuranceAddedToLoan = $insuranceEffect === 'ADD_TO_LOAN' ? $insurance : 0.00;
        $insuranceDeductedFromDisbursement = $insuranceEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $insurance : 0.00;

        $total_loan = round(
            $requestedAmount
            + $totalAddToLoan
            + $commissionAddedToLoan
            + $insuranceAddedToLoan,
            2
        );

        $netDisbursement = round(
            $requestedAmount
            - $commissionDeductedFromDisbursement
            - $insuranceDeductedFromDisbursement
            - $totalDeductFromDisbursement,
            2
        );

        if ($netDisbursement <= 0) {
            throw new \Exception(
                'This approval would make net disbursement zero or negative. '
                . 'Requested: ' . number_format($requestedAmount, 2)
                . ', Commission Deducted: ' . number_format($commissionDeductedFromDisbursement, 2)
                . ', Insurance Deducted: ' . number_format($insuranceDeductedFromDisbursement, 2)
                . ', Other Deductions: ' . number_format($totalDeductFromDisbursement, 2)
                . ', Net: ' . number_format($netDisbursement, 2)
            );
        }

        $chargeAccountErrors = $this->validateSelfServiceChargeAccounts($chargeSummary['rows']);
        if (!empty($chargeAccountErrors)) {
            throw new \Exception(implode(' ', $chargeAccountErrors));
        }

        $new_batch_no = "Self Applied Loan-" . $loan->batch_trans_id . "-" . $loan->member_name;

        $loanInsert = [
            'loan_member' => $loan->batch_trans_member_id,
            'loan_loan_type' => $loan->batch_trans_loan_type,
            'loan_loan_category' => $loan->batch_trans_loan_category,
            'loan_amount' => $total_loan,
            'loan_insurance' => $insurance,
            'loan_commision' => $commission,
            'loan_taken_period' => $currentPeriod,
            'loan_payment_period' => $loan->batch_trans_loan_duration,
            'loan_interest_payable' => $loan->batch_trans_expected_interest,
            'loan_monthly_repayment_amount' => $loan->batch_trans_monthly_payment,
            'loan_amount_guaranteed' => $loan->batch_trans_loan_guaranteed,
            'loan_loan_paid' => 0,
            'loan_doc_no' => $loan->batch_trans_doc_no,
            'loan_description' => $loan->batch_trans_description,
            'loan_batch_no' => $new_batch_no,
            'loan_start_deduction_period' => $currentPeriod,
            'loan_account_credited' => $default_bank_account,
            'loan_account_debited' => $loan->loan_type_acount,
            'loan_on' => $transdate,
            'loan_by' => $logged_in_user,
            'loan_ip' => $myIP,
            'loan_stoped' => 'N',
            'loan_taken_start_period' => $currentPeriod,
        ];

        if (Schema::hasColumn('sacco_loans', 'loan_requested_amount')) {
            $loanInsert['loan_requested_amount'] = $requestedAmount;
        }
        if (Schema::hasColumn('sacco_loans', 'loan_other_additions')) {
            $loanInsert['loan_other_additions'] = $totalAddToLoan;
        }
        if (Schema::hasColumn('sacco_loans', 'loan_other_deductions')) {
            $loanInsert['loan_other_deductions'] = $totalDeductFromDisbursement;
        }
        if (Schema::hasColumn('sacco_loans', 'loan_net_disbursement')) {
            $loanInsert['loan_net_disbursement'] = $netDisbursement;
        }
        if (Schema::hasColumn('sacco_loans', 'loan_charges_snapshot')) {
            $loanInsert['loan_charges_snapshot'] = $chargeSummary['charges_snapshot'];
        }
        if (Schema::hasColumn('sacco_loans', 'loan_batch_trans_id')) {
            $loanInsert['loan_batch_trans_id'] = $loan->batch_trans_id;
        }

        $loan_id = DB::table('sacco_loans')->insertGetId($loanInsert);

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

        $this->transferSelfServiceChargesToLoan($loan_id, $loan->batch_trans_id);

        DB::table('sacco_members')
            ->where('member_id', $loan->batch_trans_member_id)
            ->increment('member_total_loan', $total_loan);

        DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->update(['batch_trans_updated' => 'Y']);

        $this->postSelfServiceLoanLedgerEntries(
            $loan,
            $chargeSummary,
            $default_bank_account,
            $default_insurance_account,
            $default_loan_commission_account,
            $currentPeriod
        );

        DB::commit();

        return redirect()->back()->with('success', 'Loan successfully approved.');
    } catch (\Exception $e) {
        DB::rollBack();
        return redirect()->back()->withErrors(['error' => 'Error: ' . $e->getMessage()]);
    }
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

        //   dd($loan);

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
    $isApiRequest = $request->expectsJson()
        || $request->wantsJson()
        || $request->input('context') === 'api'
        || $request->is('api/*');

    $respondError = function (string $message, int $status = 400, array $errors = []) use ($request, $isApiRequest) {
        if ($isApiRequest) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], $status);
        }

        return redirect()->back()
            ->withErrors(!empty($errors) ? $errors : ['error' => $message])
            ->withInput();
    };

    $respondSuccess = function (string $message, array $payload = []) use ($id, $isApiRequest) {
        if ($isApiRequest) {
            return response()->json(array_merge([
                'success' => true,
                'message' => $message,
                'batch_trans_id' => (int) $id,
            ], $payload), 200);
        }

        return redirect()
            ->route('loans.pending.approval.selfedit', ['id' => $id])
            ->with('success', $message);
    };

    /*
    |--------------------------------------------------------------------------
    | 1. Resolve trusted member principal
    |--------------------------------------------------------------------------
    | API/mobile: $request->user() is authoritative
    | Web: fall back to session user if it carries member_id
    |--------------------------------------------------------------------------
    */
    $principal = $request->user();

    if (!$principal || !isset($principal->member_id)) {
        $principal = Auth::user();
    }

    $trustedMemberId = isset($principal->member_id) ? (int) $principal->member_id : null;
    $actorUserId = Auth::id();

    if (!$trustedMemberId) {
        return $respondError('Unauthenticated.', 401);
    }

    DB::beginTransaction();

    try {
        /*
        |--------------------------------------------------------------------------
        | 2. Load only the member’s own pending, non-deleted application
        |--------------------------------------------------------------------------
        */
        $loan = DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_member_id', $trustedMemberId)
            ->where('batch_trans_deleted', '<>', 'Y')
            ->where('batch_trans_updated', 'N')
            ->first();

        if (!$loan) {
            DB::rollBack();
            return $respondError('Loan not found, already processed, or unauthorized.', 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Normalize both web and API payload names
        |--------------------------------------------------------------------------
        */
        $normalized = [
            'batch_trans_loan_amount' => $request->input(
                'batch_trans_loan_amount',
                $request->input('amount', $loan->batch_trans_loan_amount)
            ),
            'batch_trans_loan_type' => $request->input(
                'batch_trans_loan_type',
                $request->input('loan_type_id', $loan->batch_trans_loan_type)
            ),
            'batch_trans_loan_category' => $request->input(
                'batch_trans_loan_category',
                $request->input('loan_category_id', $loan->batch_trans_loan_category)
            ),
            'batch_trans_loan_duration' => $request->input(
                'batch_trans_loan_duration',
                $request->input('duration_months', $loan->batch_trans_loan_duration)
            ),
            'batch_trans_description' => $request->input(
                'batch_trans_description',
                $request->input('reason', $loan->batch_trans_description)
            ),
        ];

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_loan_to_top_up')) {
            $normalized['batch_trans_loan_to_top_up'] = $request->input(
                'batch_trans_loan_to_top_up',
                $request->input('topup_loan_id', $loan->batch_trans_loan_to_top_up ?? null)
            );
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payroll_number')) {
            $normalized['batch_trans_payroll_number'] = $request->input(
                'batch_trans_payroll_number',
                $request->input('payroll_number', $loan->batch_trans_payroll_number ?? null)
            );
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_present_designation')) {
            $normalized['batch_trans_present_designation'] = $request->input(
                'batch_trans_present_designation',
                $request->input('designation', $loan->batch_trans_present_designation ?? null)
            );
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_terms_of_employment')) {
            $normalized['batch_trans_terms_of_employment'] = $request->input(
                'batch_trans_terms_of_employment',
                $request->input('employment_terms', $loan->batch_trans_terms_of_employment ?? null)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Validate without redirect-only behaviour
        |--------------------------------------------------------------------------
        */
        $validationPayload = array_merge($request->all(), $normalized);

        $validator = \Illuminate\Support\Facades\Validator::make($validationPayload, [
            'batch_trans_loan_amount' => 'required|numeric|min:1',
            'batch_trans_loan_type' => 'required|integer|exists:sacco_loan_types,loan_type_id',
            'batch_trans_loan_category' => 'required|integer|exists:sacco_loan_category,loan_category_id',
            'batch_trans_loan_duration' => 'required|integer|min:1|max:100',
            'batch_trans_description' => 'required|string|max:50',
            'batch_trans_loan_to_top_up' => 'nullable|integer|exists:sacco_loans,loan_id',
            'batch_trans_pay1' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
            'batch_trans_pay2' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
            'batch_trans_payroll_number' => 'nullable|string|max:50',
            'batch_trans_present_designation' => 'nullable|string|max:100',
            'batch_trans_terms_of_employment' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            DB::rollBack();
            return $respondError('Validation failed.', 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        /*
        |--------------------------------------------------------------------------
        | 5. Fetch authoritative loan type / category / member
        |--------------------------------------------------------------------------
        */
        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $validated['batch_trans_loan_type'])
            ->where('loan_type_deleted', '<>', 'Y')
            ->first();

        if (!$loanType) {
            DB::rollBack();
            return $respondError('Invalid loan type.', 422, [
                'batch_trans_loan_type' => ['Invalid loan type.'],
            ]);
        }

        $loanCategory = DB::table('sacco_loan_category')
            ->where('loan_category_id', $validated['batch_trans_loan_category'])
            ->where('loan_category_deleted', '<>', 'Y')
            ->first();

        if (!$loanCategory) {
            DB::rollBack();
            return $respondError('Invalid loan category.', 422, [
                'batch_trans_loan_category' => ['Invalid loan category.'],
            ]);
        }

        $member = DB::table('sacco_members')
            ->where('member_id', $trustedMemberId)
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->first();

        if (!$member) {
            DB::rollBack();
            return $respondError('Invalid member.', 422, [
                'member' => ['Invalid member.'],
            ]);
        }

        $loanAmount = round((float) $validated['batch_trans_loan_amount'], 2);
        $loanDuration = (int) $validated['batch_trans_loan_duration'];

        /*
        |--------------------------------------------------------------------------
        | 6. Validate optional top-up loan ownership
        |--------------------------------------------------------------------------
        */
        $topUpLoan = null;
        $topUpLoanId = $validated['batch_trans_loan_to_top_up'] ?? null;

        if (!empty($topUpLoanId)) {
            $topUpLoan = DB::table('sacco_loans')
                ->where('loan_id', $topUpLoanId)
                ->where('loan_member', $trustedMemberId)
                ->first();

            if (!$topUpLoan) {
                DB::rollBack();
                return $respondError('Invalid top-up loan.', 422, [
                    'batch_trans_loan_to_top_up' => ['Invalid top-up loan.'],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Re-run business validations
        |--------------------------------------------------------------------------
        */
        $nmsg = '';
        $nmsg .= $this->validateLoanParameters($loanAmount, $loanType, $loanDuration, $topUpLoan);
        $nmsg .= $this->validateMemberEligibility($member, $loanType);

        if (!empty($topUpLoan)) {
            $topUpOutstanding = (float) (($topUpLoan->loan_amount ?? 0) - ($topUpLoan->loan_loan_paid ?? 0));
            if ($loanAmount <= $topUpOutstanding) {
                $nmsg .= 'Invalid top-up loan. ';
            }
        }

        if (!empty($nmsg)) {
            DB::rollBack();
            return $respondError(trim($nmsg), 422, [
                'loan' => [trim($nmsg)],
            ]);
        }
/*
|--------------------------------------------------------------------------
| 8. Recalculate self-service financials using existing saved charges
|--------------------------------------------------------------------------
| ADD_TO_LOAN charges must affect EMI/interest.
| DEDUCT_FROM_DISBURSEMENT charges affect net cash, not EMI base.
|--------------------------------------------------------------------------
*/
$chargeSummary = $this->getSelfServiceChargeSummary($loan->batch_trans_id);
$addToLoanTotal = round((float) ($chargeSummary['add_to_loan'] ?? 0), 2);
$existingCommission = round((float) ($loan->batch_trans_commission ?? 0), 2);

$amountForEmi = round($loanAmount + $addToLoanTotal, 2);

$financials = $this->calculateSaccoLoanFinancials(
    $loanAmount,
    $amountForEmi,
    $loanDuration,
    $loanType,
    $existingCommission
);
        /*
        |--------------------------------------------------------------------------
        | 9. Handle optional payslip uploads
        |--------------------------------------------------------------------------
        */
        $payslip1Path = $loan->batch_trans_payslip1 ?? null;
        if ($request->hasFile('batch_trans_pay1')) {
            $payslip1Path = $request->file('batch_trans_pay1')->store('uploads/payslips');
        }

        $payslip2Path = $loan->batch_trans_payslip2 ?? null;
        if ($request->hasFile('batch_trans_pay2')) {
            $payslip2Path = $request->file('batch_trans_pay2')->store('uploads/payslips');
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Update only this member’s own pending application
        |--------------------------------------------------------------------------
        */
        $updateData = [
            'batch_trans_loan_amount' => $loanAmount,
            'batch_trans_loan_type' => (int) $validated['batch_trans_loan_type'],
            'batch_trans_loan_category' => (int) $validated['batch_trans_loan_category'],
            'batch_trans_loan_duration' => $loanDuration,
            'batch_trans_description' => $validated['batch_trans_description'],
            'batch_trans_insurance' => round((float) ($financials['insurance'] ?? 0), 2),
            'batch_trans_monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
            'batch_trans_monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
            'batch_trans_expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
            'batch_trans_payslip1' => $payslip1Path,
            'batch_trans_payslip2' => $payslip2Path,
            'batch_trans_ip' => $request->ip(),
        ];

        if ($actorUserId) {
            $updateData['batch_trans_by'] = $actorUserId;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_loan_to_top_up')) {
            $updateData['batch_trans_loan_to_top_up'] = !empty($topUpLoanId) ? (int) $topUpLoanId : null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payroll_number')) {
            $updateData['batch_trans_payroll_number'] = $validated['batch_trans_payroll_number'] ?? null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_present_designation')) {
            $updateData['batch_trans_present_designation'] = $validated['batch_trans_present_designation'] ?? null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_terms_of_employment')) {
            $updateData['batch_trans_terms_of_employment'] = $validated['batch_trans_terms_of_employment'] ?? null;
        }

        DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_member_id', $trustedMemberId)
            ->where('batch_trans_deleted', '<>', 'Y')
            ->where('batch_trans_updated', 'N')
            ->update($updateData);

        DB::commit();

        return $respondSuccess('Loan updated successfully.', [
            'insurance' => round((float) ($financials['insurance'] ?? 0), 2),
            'monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
            'monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
            'expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Failed to update self-service loan application', [
            'batch_trans_id' => $id,
            'member_id' => $trustedMemberId,
            'user_id' => $actorUserId,
            'message' => $e->getMessage(),
        ]);

        return $respondError('Failed to update loan application. ' . $e->getMessage(), 500);
    }
}
    public function deleteGuarantor(Request $request, $id)
{
    $isApiRequest = $request->expectsJson()
        || $request->wantsJson()
        || $request->input('context') === 'api'
        || $request->is('api/*');

    $respondError = function (string $message, int $status = 400, array $errors = []) use ($request, $isApiRequest) {
        if ($isApiRequest) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], $status);
        }

        return redirect()->back()
            ->withErrors(!empty($errors) ? $errors : ['error' => $message]);
    };

    $respondSuccess = function (string $message, array $payload = []) use ($isApiRequest) {
        if ($isApiRequest) {
            return response()->json(array_merge([
                'success' => true,
                'message' => $message,
            ], $payload), 200);
        }

        return redirect()->back()->with('success', $message);
    };

    $principal = $request->user();

    if (!$principal || !isset($principal->member_id)) {
        $principal = Auth::user();
    }

    $trustedMemberId = isset($principal->member_id) ? (int) $principal->member_id : null;
    $actorUserId = Auth::id();

    if (!$trustedMemberId) {
        return $respondError('Unauthenticated.', 401);
    }

    DB::beginTransaction();

    try {
        $guarantor = DB::table('sacco_loan_batch_guarantors_members as g')
            ->join('sacco_loan_batch_trans_members as t', 'g.guarantors_loan_batch_trans_id', '=', 't.batch_trans_id')
            ->select(
                'g.*',
                't.batch_trans_id',
                't.batch_trans_member_id',
                't.batch_trans_updated',
                't.batch_trans_deleted'
            )
            ->where('g.guarantors_id', $id)
            ->where('g.guarantors_deleted', '<>', 'Y')
            ->where('t.batch_trans_member_id', $trustedMemberId)
            ->where('t.batch_trans_deleted', '<>', 'Y')
            ->where('t.batch_trans_updated', 'N')
            ->first();

        if (!$guarantor) {
            DB::rollBack();
            return $respondError('Guarantor not found, already deleted, or unauthorized.', 404);
        }

        $updateData = [
            'guarantors_deleted' => 'Y',
            'guarantors_deleted_on' => now(),
            'guarantors_deleted_ip' => $request->ip(),
        ];

        if ($actorUserId) {
            $updateData['guarantors_deleted_by'] = $actorUserId;
        }

        DB::table('sacco_loan_batch_guarantors_members')
            ->where('guarantors_id', $id)
            ->where('guarantors_deleted', '<>', 'Y')
            ->update($updateData);

        DB::commit();

        return $respondSuccess('Guarantor marked as deleted successfully.', [
            'guarantors_id' => (int) $id,
            'batch_trans_id' => (int) $guarantor->batch_trans_id,
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Failed to delete self-service guarantor', [
            'guarantors_id' => $id,
            'member_id' => $trustedMemberId,
            'user_id' => $actorUserId,
            'message' => $e->getMessage(),
        ]);

        return $respondError('Failed to delete guarantor. ' . $e->getMessage(), 500);
    }
}

public function processLoanApplication(Request $request)
{
    $isApiRequest = $request->expectsJson()
        || $request->wantsJson()
        || $request->input('context') === 'api'
        || $request->is('api/*');

    $respondError = function (string $message, int $status = 400, array $errors = []) use ($request, $isApiRequest) {
        if ($isApiRequest) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], $status);
        }

        return redirect()->back()
            ->withErrors(!empty($errors) ? $errors : ['error' => $message])
            ->withInput();
    };

    $respondSuccess = function (string $message, array $payload = []) use ($isApiRequest) {
        if ($isApiRequest) {
            return response()->json(array_merge([
                'success' => true,
                'message' => $message,
            ], $payload), 200);
        }

        return redirect()->back()->with('success', $message);
    };

    /*
    |--------------------------------------------------------------------------
    | 1. Resolve trusted member principal
    |--------------------------------------------------------------------------
    | API/mobile: $request->user() is authoritative
    | Web: fall back to session user if it carries member_id
    |--------------------------------------------------------------------------
    */
    $principal = $request->user();

    if (!$principal || !isset($principal->member_id)) {
        $principal = Auth::user();
    }

    $trustedMemberId = isset($principal->member_id) ? (int) $principal->member_id : null;
    $actorUserId = Auth::id();

    if (!$trustedMemberId) {
        return $respondError('Unauthenticated.', 401);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Validate batch_trans_id first so we can load the member's own draft
    |--------------------------------------------------------------------------
    */
    $batchTransId = (int) ($request->input('batch_trans_id', $request->input('id', 0)));

    if ($batchTransId <= 0) {
        return $respondError('Invalid loan application reference.', 422, [
            'batch_trans_id' => ['Invalid loan application reference.'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Reject client/member spoofing if batch_trans_member_id is posted
    |--------------------------------------------------------------------------
    */
    $postedMemberId = $request->input('batch_trans_member_id');
    if ($postedMemberId !== null && $postedMemberId !== '' && (int) $postedMemberId !== $trustedMemberId) {
        return $respondError('Unauthorized member reference.', 403, [
            'batch_trans_member_id' => ['Unauthorized member reference.'],
        ]);
    }

    DB::beginTransaction();

    try {
        /*
        |--------------------------------------------------------------------------
        | 4. Load only this member's own pending, non-deleted application
        |--------------------------------------------------------------------------
        */
        $loan = DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $batchTransId)
            ->where('batch_trans_member_id', $trustedMemberId)
            ->where('batch_trans_deleted', '<>', 'Y')
            ->where('batch_trans_updated', 'N')
            ->first();

        if (!$loan) {
            DB::rollBack();
            return $respondError('Loan application not found, already processed, or unauthorized.', 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Normalize both web and API payload names, with fallback to draft
        |--------------------------------------------------------------------------
        */
        $normalized = [
            'batch_trans_id' => $batchTransId,
            'batch_trans_loan_amount' => $request->input(
                'batch_trans_loan_amount',
                $request->input('amount', $loan->batch_trans_loan_amount)
            ),
            'batch_trans_loan_type' => $request->input(
                'batch_trans_loan_type',
                $request->input('loan_type_id', $loan->batch_trans_loan_type)
            ),
            'batch_trans_loan_category' => $request->input(
                'batch_trans_loan_category',
                $request->input('loan_category_id', $loan->batch_trans_loan_category)
            ),
            'batch_trans_loan_duration' => $request->input(
                'batch_trans_loan_duration',
                $request->input('duration_months', $loan->batch_trans_loan_duration)
            ),
            'batch_trans_description' => $request->input(
                'batch_trans_description',
                $request->input('reason', $loan->batch_trans_description)
            ),
            'batch_trans_loan_to_top_up' => $request->input(
                'batch_trans_loan_to_top_up',
                $request->input('topup_loan_id', $loan->batch_trans_loan_to_top_up ?? null)
            ),
        ];

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payroll_number')) {
            $normalized['batch_trans_payroll_number'] = $request->input(
                'batch_trans_payroll_number',
                $request->input('payroll_number', $loan->batch_trans_payroll_number ?? null)
            );
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_present_designation')) {
            $normalized['batch_trans_present_designation'] = $request->input(
                'batch_trans_present_designation',
                $request->input('designation', $loan->batch_trans_present_designation ?? null)
            );
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_terms_of_employment')) {
            $normalized['batch_trans_terms_of_employment'] = $request->input(
                'batch_trans_terms_of_employment',
                $request->input('employment_terms', $loan->batch_trans_terms_of_employment ?? null)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Validate normalized payload without redirect-only behaviour
        |--------------------------------------------------------------------------
        */
        $validator = \Illuminate\Support\Facades\Validator::make(
            array_merge($request->all(), $normalized),
            [
                'batch_trans_id' => 'required|integer|min:1',
                'batch_trans_loan_amount' => 'required|numeric|min:1',
                'batch_trans_loan_type' => 'required|integer|exists:sacco_loan_types,loan_type_id',
                'batch_trans_loan_category' => 'required|integer|exists:sacco_loan_category,loan_category_id',
                'batch_trans_loan_duration' => 'required|integer|min:1|max:100',
                'batch_trans_description' => 'nullable|string|max:50',
                'batch_trans_loan_to_top_up' => 'nullable|integer|exists:sacco_loans,loan_id',
                'batch_trans_pay1' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
                'batch_trans_pay2' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
                'batch_trans_payroll_number' => 'nullable|string|max:50',
                'batch_trans_present_designation' => 'nullable|string|max:100',
                'batch_trans_terms_of_employment' => 'nullable|string|max:100',
            ]
        );

        if ($validator->fails()) {
            DB::rollBack();
            return $respondError('Validation failed.', 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        /*
        |--------------------------------------------------------------------------
        | 7. Fetch authoritative loan type / category / member
        |--------------------------------------------------------------------------
        */
        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $validated['batch_trans_loan_type'])
            ->where('loan_type_deleted', '<>', 'Y')
            ->first();

        if (!$loanType) {
            DB::rollBack();
            return $respondError('Invalid loan type.', 422, [
                'batch_trans_loan_type' => ['Invalid loan type.'],
            ]);
        }

        $loanCategory = DB::table('sacco_loan_category')
            ->where('loan_category_id', $validated['batch_trans_loan_category'])
            ->where('loan_category_deleted', '<>', 'Y')
            ->first();

        if (!$loanCategory) {
            DB::rollBack();
            return $respondError('Invalid loan category.', 422, [
                'batch_trans_loan_category' => ['Invalid loan category.'],
            ]);
        }

        $member = DB::table('sacco_members')
            ->where('member_id', $trustedMemberId)
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->first();

        if (!$member) {
            DB::rollBack();
            return $respondError('Invalid member.', 422, [
                'member' => ['Invalid member.'],
            ]);
        }

        $loanAmount = round((float) $validated['batch_trans_loan_amount'], 2);
        $loanDuration = (int) $validated['batch_trans_loan_duration'];

        /*
        |--------------------------------------------------------------------------
        | 8. Validate optional top-up loan ownership
        |--------------------------------------------------------------------------
        */
        $topUpLoan = null;
        $topUpLoanId = $validated['batch_trans_loan_to_top_up'] ?? null;

        if (!empty($topUpLoanId)) {
            $topUpLoan = DB::table('sacco_loans')
                ->where('loan_id', $topUpLoanId)
                ->where('loan_member', $trustedMemberId)
                ->first();

            if (!$topUpLoan) {
                DB::rollBack();
                return $respondError('Invalid top-up loan.', 422, [
                    'batch_trans_loan_to_top_up' => ['Invalid top-up loan.'],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Business validations
        |--------------------------------------------------------------------------
        */
        $nmsg = '';
        $nmsg .= $this->validateLoanParameters($loanAmount, $loanType, $loanDuration, $topUpLoan);
        $nmsg .= $this->validateMemberEligibility($member, $loanType);

        if (!empty($topUpLoan)) {
            $topUpOutstanding = (float) (($topUpLoan->loan_amount ?? 0) - ($topUpLoan->loan_loan_paid ?? 0));
            if ($loanAmount <= $topUpOutstanding) {
                $nmsg .= 'Invalid top-up loan. ';
            }
        }

        if (!empty($nmsg)) {
            DB::rollBack();
            return $respondError(trim($nmsg), 422, [
                'loan' => [trim($nmsg)],
            ]);
        }
/*
|--------------------------------------------------------------------------
| 10. SACCO-aware financial recalculation using existing saved charges
|--------------------------------------------------------------------------
| ADD_TO_LOAN charges must affect EMI/interest.
| DEDUCT_FROM_DISBURSEMENT charges affect net cash, not EMI base.
|--------------------------------------------------------------------------
*/
$chargeSummary = $this->getSelfServiceChargeSummary($loan->batch_trans_id);
$addToLoanTotal = round((float) ($chargeSummary['add_to_loan'] ?? 0), 2);
$existingCommission = round((float) ($loan->batch_trans_commission ?? 0), 2);

$amountForEmi = round($loanAmount + $addToLoanTotal, 2);

$financials = $this->calculateSaccoLoanFinancials(
    $loanAmount,
    $amountForEmi,
    $loanDuration,
    $loanType,
    $existingCommission
);

        /*
        |--------------------------------------------------------------------------
        | 11. Optional payslip uploads
        |--------------------------------------------------------------------------
        */
        $payslip1Path = $loan->batch_trans_payslip1 ?? null;
        if ($request->hasFile('batch_trans_pay1')) {
            $payslip1Path = $request->file('batch_trans_pay1')->store('uploads/payslips');
        }

        $payslip2Path = $loan->batch_trans_payslip2 ?? null;
        if ($request->hasFile('batch_trans_pay2')) {
            $payslip2Path = $request->file('batch_trans_pay2')->store('uploads/payslips');
        }

        /*
        |--------------------------------------------------------------------------
        | 12. Update only this member's own pending application
        |--------------------------------------------------------------------------
        */
        $updateData = [
            'batch_trans_loan_amount' => $loanAmount,
            'batch_trans_loan_type' => (int) $validated['batch_trans_loan_type'],
            'batch_trans_loan_category' => (int) $validated['batch_trans_loan_category'],
            'batch_trans_loan_duration' => $loanDuration,
            'batch_trans_description' => $validated['batch_trans_description'] ?? $loan->batch_trans_description,
            'batch_trans_insurance' => round((float) ($financials['insurance'] ?? 0), 2),
            'batch_trans_monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
            'batch_trans_monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
            'batch_trans_expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
            'batch_trans_payslip1' => $payslip1Path,
            'batch_trans_payslip2' => $payslip2Path,
            'batch_trans_ip' => $request->ip(),
        ];

        if ($actorUserId) {
            $updateData['batch_trans_by'] = $actorUserId;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_loan_to_top_up')) {
            $updateData['batch_trans_loan_to_top_up'] = !empty($topUpLoanId) ? (int) $topUpLoanId : null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payroll_number')) {
            $updateData['batch_trans_payroll_number'] = $validated['batch_trans_payroll_number'] ?? null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_present_designation')) {
            $updateData['batch_trans_present_designation'] = $validated['batch_trans_present_designation'] ?? null;
        }

        if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_terms_of_employment')) {
            $updateData['batch_trans_terms_of_employment'] = $validated['batch_trans_terms_of_employment'] ?? null;
        }

        DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $batchTransId)
            ->where('batch_trans_member_id', $trustedMemberId)
            ->where('batch_trans_deleted', '<>', 'Y')
            ->where('batch_trans_updated', 'N')
            ->update($updateData);

        DB::commit();

        return $respondSuccess('Loan application processed successfully.', [
            'batch_trans_id' => $batchTransId,
            'insurance' => round((float) ($financials['insurance'] ?? 0), 2),
            'monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
            'monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
            'expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Failed to process self-service loan application', [
            'batch_trans_id' => $batchTransId,
            'member_id' => $trustedMemberId,
            'user_id' => $actorUserId,
            'message' => $e->getMessage(),
        ]);

        return $respondError('Failed to process loan application. ' . $e->getMessage(), 500);
    }
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
                    'guarantors_email_sent' => 'N', // Set all as 'N'
                ]
            );
        }

        return ['success' => true, 'message' => 'Guarantors validated and updated successfully.'];
    }

    private function validateLoanParameters($loanAmount, $loanType, $loanDuration, $topUpLoan = null)
    {
        $errors = "";

        // Validate loan amount
        if ($loanAmount < 1 || $loanAmount > $loanType->loan_type_max_amount) {
            $errors .= "Error: Loan amount must be between 1 and {$loanType->loan_type_max_amount}. ";
        }

        // Validate repayment period
        if ($loanDuration > $loanType->loan_type_duration) {
            $errors .= "Error: Loan repayment period cannot exceed {$loanType->loan_type_duration} months. ";
        }

        // Validate top-up loan if provided
        if ($topUpLoan) {
            if (!$topUpLoan) {
                $errors .= "Error: Invalid top-up loan. ";
            } elseif ($loanAmount <= ($topUpLoan->loan_amount - $topUpLoan->loan_loan_paid)) {
                $errors .= "Error: New loan amount must exceed the balance of the top-up loan. ";
            }
        }

        return $errors;
    }

    private function validateMemberEligibility($member, $loanType)
    {
        if (!$member) {
            return "Error: Member not found in the database.";
        }

        $membershipDurationRequired = $loanType->loan_type_qualification_period;

        if (strtotime($member->member_date_joined) > strtotime("-{$membershipDurationRequired} months")) {
            return "Error: Member must be {$membershipDurationRequired} months old in the SACCO to take this loan.";
        }

        return null; // No errors
    }



    private function calculateInsuranceAndInterest($loanTypeId, $loanAmount, $repaymentPeriod)
    {
        $insu = $loanAmount * 1 / 100; // Default insurance calculation

        $loanType = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->first();

        // If the loan is not insurable, set insurance to 0
        if ($loanType->loan_type_insurable != "Y") {
            $insu = 0;
        }

        // Fixed interest calculation
        if ($loanType->loan_type_interest_type == "FIXED INTEREST") {
            $interestAmountPayable = round(($loanAmount + $insu) * $loanType->loan_type_interest / 100, 0);
            $emi = ceil(($loanAmount + $interestAmountPayable + $insu) / $repaymentPeriod);
            $monthlyRepaymentPrincipal = ($loanAmount + $insu) / $repaymentPeriod;
        } else {
            // Reducing balance interest calculation
            $loanAmountWithInsurance = $loanAmount + $insu;
            $interestRate = $loanType->loan_type_interest / 12 / 100;

            $emi = ($loanAmountWithInsurance * $interestRate) * pow(1 + $interestRate, $repaymentPeriod) / (pow(1 + $interestRate, $repaymentPeriod) - 1);
            $interestAmountPayable = ($emi * $repaymentPeriod) - $loanAmountWithInsurance;
            $monthlyRepaymentPrincipal = $emi - ($loanAmountWithInsurance * $interestRate);
            $emi = ceil($emi);
            $monthlyRepaymentPrincipal = ceil($monthlyRepaymentPrincipal);
        }

        return [
            'insurance' => $insu,
            'emi' => $emi,
            'interestAmountPayable' => $interestAmountPayable,
            'monthlyRepaymentPrincipal' => $monthlyRepaymentPrincipal,
        ];
    }

   public function saveAdjustedCharges(Request $request, $id)
{
    $loggedInUser = auth()->id();
    $transdate = now();
    $myIP = $request->ip();

    $loan = DB::table('sacco_loan_batch_trans_members as trans')
        ->join('sacco_members as members', 'trans.batch_trans_member_id', '=', 'members.member_id')
        ->join('sacco_loan_types as types', 'trans.batch_trans_loan_type', '=', 'types.loan_type_id')
        ->select(
            'trans.*',
            'members.member_name',
            'members.member_sacco_id',
            'types.loan_type_name',
            'types.loan_type_commission_effect',
            'types.loan_type_insurance_effect'
        )
        ->where('trans.batch_trans_id', $id)
        ->where('trans.batch_trans_updated', 'N')
        ->where('trans.batch_trans_deleted', '<>', 'Y')
        ->first();

    if (!$loan) {
        return $this->respondAdjustedCharges(
            $request,
            false,
            'Loan application not found, already approved, or deleted.',
            [],
            404
        );
    }

    $loanType = DB::table('sacco_loan_types')
        ->where('loan_type_id', $loan->batch_trans_loan_type)
        ->where('loan_type_deleted', '<>', 'Y')
        ->first();

    if (!$loanType) {
        return $this->respondAdjustedCharges(
            $request,
            false,
            'Loan type not found or inactive.',
            [],
            422
        );
    }

    $submittedCharges = collect($request->input('charges', []))
        ->filter(function ($row) {
            return !empty($row['deduction_type_id'] ?? null);
        })
        ->values();

    DB::beginTransaction();

    try {
        DB::table('sacco_loan_batch_trans_members_deductions')
            ->where('batch_trans_deduction_batch_trans_id', $id)
            ->where('batch_trans_deduction_deleted', 'N')
            ->update([
                'batch_trans_deduction_deleted' => 'Y',
                'batch_trans_deduction_deleted_by' => $loggedInUser,
                'batch_trans_deduction_deleted_on' => $transdate,
                'batch_trans_deduction_deleted_ip' => $myIP,
            ]);

        $baseLoanAmount = round((float) $loan->batch_trans_loan_amount, 2);
        $existingCommission = round((float) ($loan->batch_trans_commission ?? 0), 2);
        $loanDuration = (int) ($loan->batch_trans_loan_duration ?? 0);

        if ($loanDuration < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'batch_trans_loan_duration' => 'Invalid loan duration on this application.',
            ]);
        }

        $rowsToInsert = [];
        $seenTypeIds = [];
        $addToLoanTotal = 0;
        $deductFromDisbursementTotal = 0;

        foreach ($submittedCharges as $index => $row) {
            $typeId = (int) ($row['deduction_type_id'] ?? 0);

            if ($typeId <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "charges.$index.deduction_type_id" => 'Please select a valid charge type.',
                ]);
            }

            if (in_array($typeId, $seenTypeIds, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "charges.$index.deduction_type_id" => 'The same charge type cannot be added more than once to one application.',
                ]);
            }

            $seenTypeIds[] = $typeId;

            $type = DB::table('sacco_loan_deductions_types')
                ->where('deduction_type_id', $typeId)
                ->where('deduction_type_deleted', 'N')
                ->where('deduction_type_active', 1)
                ->first();

            if (!$type) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "charges.$index.deduction_type_id" => 'The selected charge type is invalid or inactive.',
                ]);
            }

            $typeName = $this->readDeductionTypeField($type, ['deduction_type_name', 'loan_deduction_name']);
            $typeCode = $this->readDeductionTypeField($type, ['deduction_type_code', 'loan_deduction_code']);
            $valueType = strtoupper((string) $this->readDeductionTypeField($type, ['deduction_type_value_type', 'loan_deduction_type_value_type']));
            $effect = strtoupper((string) $this->readDeductionTypeField($type, ['deduction_type_effect', 'loan_deduction_type_effect']));
            $defaultValue = (float) $this->readDeductionTypeField($type, ['deduction_type_default_value', 'loan_deduction_type_default_value'], 0);
            $accountId = (int) $this->readDeductionTypeField($type, ['deduction_type_account', 'loan_deduction_type_account'], 0);

            if (!in_array($valueType, ['FIXED', 'PERCENT'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "charges.$index.deduction_type_id" => "Charge type {$typeName} has an invalid value type.",
                ]);
            }

            if (!in_array($effect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "charges.$index.deduction_type_id" => "Charge type {$typeName} has an invalid effect.",
                ]);
            }

            if ($accountId <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "charges.$index.deduction_type_id" => "Charge type {$typeName} does not have a valid account mapping.",
                ]);
            }

            $userEnteredValue = $row['value'] ?? $row['amount'] ?? null;
            $userNote = trim((string) ($row['comment'] ?? $row['description'] ?? ''));

            if ($defaultValue > 0) {
                $appliedValue = $defaultValue;
                $locked = true;
            } else {
                if ($userEnteredValue === null || $userEnteredValue === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "charges.$index.value" => "Please enter a value for {$typeName}.",
                    ]);
                }

                $appliedValue = (float) $userEnteredValue;
                $locked = false;

                if ($appliedValue <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "charges.$index.value" => "The entered value for {$typeName} must be greater than zero.",
                    ]);
                }
            }

            if ($valueType === 'PERCENT') {
                if ($appliedValue > 100) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "charges.$index.value" => "{$typeName} percentage cannot exceed 100%.",
                    ]);
                }

                $amount = round(($baseLoanAmount * $appliedValue) / 100, 2);
            } else {
                $amount = round($appliedValue, 2);
            }

            if ($amount <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "charges.$index.value" => "The computed amount for {$typeName} must be greater than zero.",
                ]);
            }

            if ($effect === 'ADD_TO_LOAN') {
                $addToLoanTotal += $amount;
            } else {
                $deductFromDisbursementTotal += $amount;
            }

            $rowsToInsert[] = [
                'batch_trans_deduction_batch_id' => null,
                'batch_trans_deduction_batch_trans_id' => $id,
                'batch_trans_deduction_deduction_type_id' => $typeId,
                'batch_trans_deduction_name' => $typeName,
                'batch_trans_deduction_code' => $typeCode,
                'batch_trans_deduction_value_type' => $valueType,
                'batch_trans_deduction_effect' => $effect,
                'batch_trans_deduction_account' => $accountId,
                'batch_trans_deduction_amount' => $amount,
                'batch_trans_deduction_description' => $this->buildSelfServiceChargeDescription(
                    $typeName,
                    $valueType,
                    $effect,
                    $appliedValue,
                    $amount,
                    $locked,
                    $userNote
                ),
                'batch_trans_deduction_updated' => 'N',
                'batch_trans_deduction_by' => $loggedInUser,
                'batch_trans_deduction_on' => $transdate,
                'batch_trans_deduction_ip' => $myIP,
                'batch_trans_deduction_deleted' => 'N',
                'batch_trans_deduction_deleted_by' => null,
                'batch_trans_deduction_deleted_on' => null,
                'batch_trans_deduction_deleted_ip' => null,
            ];
        }

        if (!empty($rowsToInsert)) {
            DB::table('sacco_loan_batch_trans_members_deductions')->insert($rowsToInsert);
        }

        /*
        |--------------------------------------------------------------------------
        | Recalculate application commitment after saving charges
        |--------------------------------------------------------------------------
        | ADD_TO_LOAN charges affect amountForEmi.
        | DEDUCT_FROM_DISBURSEMENT charges affect net cash only, not EMI base.
        |--------------------------------------------------------------------------
        */
        $amountForEmi = round($baseLoanAmount + $addToLoanTotal, 2);

        $financials = $this->calculateSaccoLoanFinancials(
            $baseLoanAmount,
            $amountForEmi,
            $loanDuration,
            $loanType,
            $existingCommission
        );

        $recalculatedInsurance = round((float) ($financials['insurance'] ?? 0), 2);
        $commissionAddedToLoan = round((float) ($financials['commission_added_to_loan'] ?? 0), 2);
        $commissionDeductedFromDisbursement = round((float) ($financials['commission_deducted_from_disbursement'] ?? 0), 2);
        $insuranceAddedToLoan = round((float) ($financials['insurance_added_to_loan'] ?? 0), 2);
        $insuranceDeductedFromDisbursement = round((float) ($financials['insurance_deducted_from_disbursement'] ?? 0), 2);

        $totalLoanPreview = round(
            $baseLoanAmount
            + $addToLoanTotal
            + $commissionAddedToLoan
            + $insuranceAddedToLoan,
            2
        );

        $netDisbursementPreview = round(
            $baseLoanAmount
            - $commissionDeductedFromDisbursement
            - $insuranceDeductedFromDisbursement
            - $deductFromDisbursementTotal,
            2
        );

        if ($netDisbursementPreview <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'charges' => "These charges make net disbursement zero or negative. "
                    . "Requested amount: " . number_format($baseLoanAmount, 2)
                    . ", Commission deducted: " . number_format($commissionDeductedFromDisbursement, 2)
                    . ", Insurance deducted: " . number_format($insuranceDeductedFromDisbursement, 2)
                    . ", Other disbursement deductions: " . number_format($deductFromDisbursementTotal, 2)
                    . ", Net disbursement: " . number_format($netDisbursementPreview, 2) . ".",
            ]);
        }

        $batchUpdate = [
            'batch_trans_insurance' => $recalculatedInsurance,
            'batch_trans_monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
            'batch_trans_monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
            'batch_trans_expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
            'batch_trans_ip' => $myIP,
        ];

        if ($loggedInUser) {
            $batchUpdate['batch_trans_by'] = $loggedInUser;
        }

        DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_updated', 'N')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->update($batchUpdate);

        DB::commit();

        $successMessage = $submittedCharges->isEmpty()
            ? 'Adjusted charges saved successfully. No active charges remain on this application.'
            : 'Adjusted charges saved successfully. Loan commitment recalculated.';

        return $this->respondAdjustedCharges(
            $request,
            true,
            $successMessage,
            [
                'batch_trans_id' => (int) $id,
                'charges_count' => count($rowsToInsert),
                'add_to_loan_total' => round($addToLoanTotal, 2),
                'deduct_from_disbursement_total' => round($deductFromDisbursementTotal, 2),
                'commission_deducted' => $commissionDeductedFromDisbursement,
                'insurance_deducted' => $insuranceDeductedFromDisbursement,
                'commission_added_to_loan' => $commissionAddedToLoan,
                'insurance_added_to_loan' => $insuranceAddedToLoan,
                'insurance' => $recalculatedInsurance,
                'monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
                'monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
                'expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
                'total_loan_preview' => $totalLoanPreview,
                'net_disbursement_preview' => $netDisbursementPreview,
            ]
        );
    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();

        return $this->respondAdjustedCharges(
            $request,
            false,
            'Validation failed while saving adjusted charges.',
            $e->errors(),
            422
        );
    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Failed to save self-service adjusted charges', [
            'batch_trans_id' => $id,
            'user_id' => $loggedInUser,
            'message' => $e->getMessage(),
        ]);

        return $this->respondAdjustedCharges(
            $request,
            false,
            'Failed to save adjusted charges. ' . $e->getMessage(),
            [],
            500
        );
    }
}
    private function respondAdjustedCharges(Request $request, bool $success, string $message, array $dataOrErrors = [], int $status = 200)
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->isJson()) {
            return response()->json([
                'status' => $success ? 'success' : 'error',
                'message' => $message,
                'data' => $success ? $dataOrErrors : null,
                'errors' => $success ? [] : $dataOrErrors,
            ], $status);
        }

        if ($success) {
            return redirect()->back()->with('success', $message);
        }

        return redirect()->back()->withErrors($dataOrErrors ?: ['error' => $message])->withInput();
    }

    private function readDeductionTypeField($row, array $fields, $default = null)
    {
        foreach ($fields as $field) {
            if (is_object($row) && property_exists($row, $field)) {
                return $row->{$field};
            }

            if (is_array($row) && array_key_exists($field, $row)) {
                return $row[$field];
            }
        }

        return $default;
    }

    private function buildSelfServiceChargeDescription(
        string $typeName,
        string $valueType,
        string $effect,
        float $appliedValue,
        float $amount,
        bool $locked,
        string $userNote = ''
    ): string {
        $effectText = $effect === 'ADD_TO_LOAN'
            ? 'added to loan'
            : 'deducted from disbursement';

        if ($valueType === 'PERCENT') {
            $desc = "{$typeName} {$effectText} at {$appliedValue}% (KES " . number_format($amount, 2) . ")";
        } else {
            $desc = "{$typeName} {$effectText} at KES " . number_format($amount, 2);
        }

        if ($locked) {
            $desc .= ' [locked from setup]';
        } else {
            $desc .= ' [entered during self-service adjustment]';
        }
        if ($userNote !== '') {
            $desc .= '. Note: ' . $userNote;
        }

        return $desc . '.';
    }
private function postSelfServiceLoanLedgerEntries(
    $loan,
    array $chargeSummary,
    $defaultBankAccount,
    $defaultInsuranceAccount,
    $defaultCommissionAccount,
    $currentPeriod
): void {
    $requestedAmount = round((float) ($loan->batch_trans_loan_amount ?? 0), 2);
    $insurance = round((float) ($loan->batch_trans_insurance ?? 0), 2);
    $commission = round((float) ($loan->batch_trans_commission ?? 0), 2);

    $totalAddToLoan = round((float) ($chargeSummary['add_to_loan'] ?? 0), 2);
    $totalDeductFromDisbursement = round((float) ($chargeSummary['deduct_from_disbursement'] ?? 0), 2);

    $loanAccount = $loan->loan_type_acount;
    $bankAccount = $defaultBankAccount;
    $insuranceAccount = $defaultInsuranceAccount;
    $commissionAccount = $defaultCommissionAccount;

    if (empty($loanAccount)) {
        throw new \Exception('Loan account missing for this loan type.');
    }

    $commissionEffect = strtoupper(trim((string) ($loan->loan_type_commission_effect ?? 'ADD_TO_LOAN')));
    if (!in_array($commissionEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
        $commissionEffect = 'ADD_TO_LOAN';
    }

    $insuranceEffect = strtoupper(trim((string) ($loan->loan_type_insurance_effect ?? 'ADD_TO_LOAN')));
    if (!in_array($insuranceEffect, ['ADD_TO_LOAN', 'DEDUCT_FROM_DISBURSEMENT'], true)) {
        $insuranceEffect = 'ADD_TO_LOAN';
    }

    $commissionAddedToLoan = $commissionEffect === 'ADD_TO_LOAN' ? $commission : 0.00;
    $commissionDeductedFromDisbursement = $commissionEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $commission : 0.00;

    $insuranceAddedToLoan = $insuranceEffect === 'ADD_TO_LOAN' ? $insurance : 0.00;
    $insuranceDeductedFromDisbursement = $insuranceEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $insurance : 0.00;

    $loanDebit = round(
        $requestedAmount
        + $totalAddToLoan
        + $commissionAddedToLoan
        + $insuranceAddedToLoan,
        2
    );

    $bankCredit = round(
        $requestedAmount
        - $commissionDeductedFromDisbursement
        - $insuranceDeductedFromDisbursement
        - $totalDeductFromDisbursement,
        2
    );

    if ($loanDebit <= 0) {
        throw new \Exception('Computed loan debit is invalid.');
    }

    if ($bankCredit <= 0) {
        throw new \Exception('Computed bank disbursement is zero or negative.');
    }

    $memberNumber = isset($loan->member_sacco_id) && !empty($loan->member_sacco_id)
        ? $loan->member_sacco_id
        : ($loan->batch_trans_member_id ?? null);

    $memberLabel = $memberNumber
        ? ($memberNumber . ' - ' . $loan->member_name)
        : $loan->member_name;

    $chargeRows = collect($chargeSummary['rows'] ?? [])
        ->filter(function ($row) {
            return (float) ($row->batch_trans_deduction_amount ?? 0) > 0;
        });

    $chargesSnapshot = $chargeRows
        ->map(function ($row) {
            return trim((string) ($row->batch_trans_deduction_description ?? ''));
        })
        ->filter()
        ->implode(' | ');

    $loanDescription = 'Loan principal plus financed charges for member ' . $memberLabel;
    if ($chargesSnapshot !== '') {
        $loanDescription .= ' | ' . $chargesSnapshot;
    }

    $bankDescription = 'Net loan disbursement to member ' . $memberLabel;
    if ($chargesSnapshot !== '') {
        $bankDescription .= ' | ' . $chargesSnapshot;
    }

    $postings = [];

    $postings[] = [
        'account' => $loanAccount,
        'debit' => $loanDebit,
        'credit' => 0,
        'doc_no' => $loan->batch_trans_doc_no,
        'desc' => $loanDescription,
        'source' => 'Loan Account',
    ];

    $postings[] = [
        'account' => $bankAccount,
        'debit' => 0,
        'credit' => $bankCredit,
        'doc_no' => $loan->batch_trans_doc_no,
        'desc' => $bankDescription,
        'source' => 'Loan Approved',
    ];

    if ($insurance > 0) {
        $postings[] = [
            'account' => $insuranceAccount,
            'debit' => 0,
            'credit' => $insurance,
            'doc_no' => $loan->batch_trans_doc_no,
            'desc' => 'Loan insurance for member ' . $memberLabel,
            'source' => 'Loan Insurance',
        ];
    }

    if ($commission > 0) {
        $postings[] = [
            'account' => $commissionAccount,
            'debit' => 0,
            'credit' => $commission,
            'doc_no' => $loan->batch_trans_doc_no,
            'desc' => 'Loan commission for member ' . $memberLabel,
            'source' => 'Loan Commission',
        ];
    }

    foreach (($chargeSummary['rows'] ?? []) as $row) {
        $amount = round((float) ($row->batch_trans_deduction_amount ?? 0), 2);

        if ($amount <= 0) {
            continue;
        }

        if (empty($row->batch_trans_deduction_account)) {
            throw new \Exception('Missing ledger account for charge "' . ($row->batch_trans_deduction_name ?? 'Unknown') . '".');
        }

        $chargeDesc = !empty($row->batch_trans_deduction_description)
            ? $row->batch_trans_deduction_description
            : (($row->batch_trans_deduction_name ?? 'Loan Charge') . ' for member ' . $memberLabel);

        if (stripos($chargeDesc, 'member') === false) {
            $chargeDesc .= ' | Member: ' . $memberLabel;
        }

        $postings[] = [
            'account' => $row->batch_trans_deduction_account,
            'debit' => 0,
            'credit' => $amount,
            'doc_no' => $loan->batch_trans_doc_no,
            'desc' => $chargeDesc,
            'source' => 'Loan Charge',
        ];
    }

    $totalDebitPosted = round(array_sum(array_column($postings, 'debit')), 2);
    $totalCreditPosted = round(array_sum(array_column($postings, 'credit')), 2);

    if ($totalDebitPosted !== $totalCreditPosted) {
        throw new \Exception(
            'Self-service loan ledger is not balanced. Debits: '
            . number_format($totalDebitPosted, 2)
            . ', Credits: '
            . number_format($totalCreditPosted, 2)
        );
    }

    foreach ($postings as $entry) {
        $this->updateSaccoAccountsTrans(
            $entry['account'],
            $entry['debit'],
            $entry['credit'],
            $entry['doc_no'],
            $entry['desc'],
            now(),
            $currentPeriod,
            $entry['source']
        );
    }
}
private function transferSelfServiceChargesToLoan($loanId, $transactionId): void
{
    $rows = DB::table('sacco_loan_batch_trans_members_deductions')
        ->where('batch_trans_deduction_batch_trans_id', $transactionId)
        ->where('batch_trans_deduction_deleted', 'N')
        ->get();

    foreach ($rows as $row) {
        DB::table('sacco_loan_deductions')->insert([
            'loan_deduction_loan_id' => $loanId,
            'loan_deduction_batch_id' => null,
            'loan_deduction_batch_trans_id' => $transactionId,
            'loan_deduction_batch_deduction_id' => $row->batch_trans_deduction_id ?? null,
            'loan_deduction_deduction_type_id' => $row->batch_trans_deduction_deduction_type_id,
            'loan_deduction_name' => $row->batch_trans_deduction_name,
            'loan_deduction_code' => $row->batch_trans_deduction_code,
            'loan_deduction_value_type' => $row->batch_trans_deduction_value_type,
            'loan_deduction_effect' => $row->batch_trans_deduction_effect,
            'loan_deduction_account' => $row->batch_trans_deduction_account,
            'loan_deduction_amount' => (float) $row->batch_trans_deduction_amount,
            'loan_deduction_description' => $row->batch_trans_deduction_description,
            'loan_deduction_updated' => 'N',
            'loan_deduction_by' => auth()->id(),
            'loan_deduction_ip' => request()->ip(),
            'loan_deduction_deleted' => 'N',
        ]);
    }

    DB::table('sacco_loan_batch_trans_members_deductions')
        ->where('batch_trans_deduction_batch_trans_id', $transactionId)
        ->where('batch_trans_deduction_deleted', 'N')
        ->update([
            'batch_trans_deduction_updated' => 'Y',
        ]);
}
private function validateSelfServiceChargeAccounts($rows): array
{
    $errors = [];

    foreach ($rows as $row) {
        $amount = round((float) ($row->batch_trans_deduction_amount ?? 0), 2);

        if ($amount <= 0) {
            continue;
        }

        if (empty($row->batch_trans_deduction_account)) {
            $errors[] = 'Missing ledger account for charge "' . ($row->batch_trans_deduction_name ?? 'Unknown Charge') . '".';
        }
    }

    return array_values(array_unique($errors));
}
private function getSelfServiceChargeSummary($transactionId): array
{
    $rows = DB::table('sacco_loan_batch_trans_members_deductions')
        ->where('batch_trans_deduction_batch_trans_id', $transactionId)
        ->where('batch_trans_deduction_deleted', 'N')
        ->orderBy('batch_trans_deduction_id', 'asc')
        ->get();

    $addToLoan = 0;
    $deductFromDisbursement = 0;
    $snapshotParts = [];

    foreach ($rows as $row) {
        $amount = round((float) ($row->batch_trans_deduction_amount ?? 0), 2);
        $effect = strtoupper(trim((string) ($row->batch_trans_deduction_effect ?? '')));

        if ($effect === 'ADD_TO_LOAN') {
            $addToLoan += $amount;
        } elseif ($effect === 'DEDUCT_FROM_DISBURSEMENT') {
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
private function calculateSaccoLoanFinancials(
    float $requestedLoanAmount,
    float $amountForEmi,
    int $durationMonths,
    $loanType,
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

    $isInsurable = strtoupper(trim((string) ($loanType->loan_type_insurable ?? 'N'))) === 'Y';

    $calcMethod = DB::table('sacco_defaults')
        ->where('default_name', 'loan_interest_insurance')
        ->value('default_value');

    $allowedMethods = [
        'calc_loan_interest_insurance_adom',
        'calc_loan_interest_insurance_yes',
        'calc_loan_interest_insurance_dhl',
        'default_calc_loan_interest_insurance',
    ];

    if (!$calcMethod || !in_array($calcMethod, $allowedMethods, true)) {
        $calcMethod = 'default_calc_loan_interest_insurance';
    }

    if (!$isInsurable) {
        $insurance = 0.00;
    } elseif ($calcMethod === 'calc_loan_interest_insurance_adom') {
        $baseInsu = ((5.03 * $durationMonths + 3.03) * $requestedLoanAmount) / 6000;
        $baseInsu = max($baseInsu, 100);
        $phcf = $baseInsu * 0.0025;
        $insurance = round($baseInsu + $phcf, 2);
    } else {
        $insurance = round($requestedLoanAmount * 0.01, 2);
    }

    $commissionAddedToLoan = $commissionEffect === 'ADD_TO_LOAN' ? $commission : 0.00;
    $commissionDeductedFromDisbursement = $commissionEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $commission : 0.00;

    $insuranceAddedToLoan = $insuranceEffect === 'ADD_TO_LOAN' ? $insurance : 0.00;
    $insuranceDeductedFromDisbursement = $insuranceEffect === 'DEDUCT_FROM_DISBURSEMENT' ? $insurance : 0.00;

    $loanPlusExtras = round(
        $amountForEmi + $commissionAddedToLoan + $insuranceAddedToLoan,
        2
    );

    $interestType = strtoupper(trim((string) ($loanType->loan_type_interest_type ?? 'REDUCING BALANCE')));
    $annualRate = (float) ($loanType->loan_type_interest ?? 0);
    $monthlyRate = $annualRate / 12 / 100;

    $monthlyPayment = 0.00;
    $monthlyPrincipal = 0.00;
    $expectedInterest = 0.00;

    switch ($calcMethod) {
        case 'calc_loan_interest_insurance_adom':
            if ($interestType === 'FIXED INTEREST') {
                $expectedInterest = round($loanPlusExtras * ($annualRate / 100), 0);
                $monthlyPayment = ceil(($loanPlusExtras + $expectedInterest) / $durationMonths);
                $monthlyPrincipal = $loanPlusExtras / $durationMonths;
            } else {
                if ($monthlyRate > 0) {
                    $monthlyPayment = ($loanPlusExtras * $monthlyRate) * pow(1 + $monthlyRate, $durationMonths)
                        / (pow(1 + $monthlyRate, $durationMonths) - 1);
                } else {
                    $monthlyPayment = $loanPlusExtras / $durationMonths;
                }

                $expectedInterest = ($monthlyPayment * $durationMonths) - $loanPlusExtras;
                $monthlyPrincipal = $monthlyPayment - ($loanPlusExtras * $monthlyRate);

                $monthlyPayment = ceil($monthlyPayment);
                $monthlyPrincipal = ceil($monthlyPrincipal);
            }
            break;

        case 'calc_loan_interest_insurance_yes':
            if ($monthlyRate > 0) {
                $monthlyPayment = $loanPlusExtras * $monthlyRate * pow(1 + $monthlyRate, $durationMonths)
                    / (pow(1 + $monthlyRate, $durationMonths) - 1);
            } else {
                $monthlyPayment = $loanPlusExtras / $durationMonths;
            }

            $expectedInterest = ($monthlyPayment * $durationMonths) - $loanPlusExtras;
            $monthlyPrincipal = round($amountForEmi / $durationMonths, 2);
            break;

        case 'calc_loan_interest_insurance_dhl':
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
            break;

        case 'default_calc_loan_interest_insurance':
        default:
            if ($interestType === 'FIXED INTEREST') {
                $expectedInterest = round($loanPlusExtras * $annualRate / 100, 0);
                $monthlyPayment = ceil(($loanPlusExtras + $expectedInterest) / $durationMonths);
                $monthlyPrincipal = $loanPlusExtras / $durationMonths;
            } else {
                if ($monthlyRate > 0) {
                    $monthlyPayment = ($loanPlusExtras * $monthlyRate) * pow(1 + $monthlyRate, $durationMonths)
                        / (pow(1 + $monthlyRate, $durationMonths) - 1);
                } else {
                    $monthlyPayment = $loanPlusExtras / $durationMonths;
                }

                $expectedInterest = ($monthlyPayment * $durationMonths) - $loanPlusExtras;
                $monthlyPrincipal = $monthlyPayment - ($loanPlusExtras * $monthlyRate);

                $monthlyPayment = ceil($monthlyPayment);
                $monthlyPrincipal = ceil($monthlyPrincipal);
            }
            break;
    }

    return [
        'calc_method' => $calcMethod,
        'requested_amount' => $requestedLoanAmount,
        'amount_for_emi' => $amountForEmi,
        'loan_plus_extras' => round($loanPlusExtras, 2),

        'commission_amount' => round($commission, 2),
        'commission_effect' => $commissionEffect,
        'commission_added_to_loan' => round($commissionAddedToLoan, 2),
        'commission_deducted_from_disbursement' => round($commissionDeductedFromDisbursement, 2),

        'insurance' => round($insurance, 2),
        'insurance_effect' => $insuranceEffect,
        'insurance_added_to_loan' => round($insuranceAddedToLoan, 2),
        'insurance_deducted_from_disbursement' => round($insuranceDeductedFromDisbursement, 2),

        'monthly_payment' => round($monthlyPayment, 2),
        'monthly_payment_principal' => round($monthlyPrincipal, 2),
        'expected_interest' => round($expectedInterest, 2),
    ];
}
public function updatePendingLoanDocNo(Request $request, $id)
{
    $isJson = $request->expectsJson()
        || $request->wantsJson()
        || $request->ajax();

    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
        'batch_trans_doc_no' => 'nullable|string|max:100',
    ]);

    if ($validator->fails()) {
        if ($isJson) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return redirect()->back()
            ->withErrors($validator->errors())
            ->withInput();
    }

    $docNo = trim((string) $request->input('batch_trans_doc_no', ''));

    try {
        $loan = DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_updated', 'N')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->first();

        if (!$loan) {
            if ($isJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pending loan application not found.',
                ], 404);
            }

            return redirect()->back()->withErrors([
                'error' => 'Pending loan application not found.',
            ]);
        }

        $updateData = [
            'batch_trans_doc_no' => $docNo !== '' ? $docNo : null,
            'batch_trans_ip' => $request->ip(),
        ];

        if (Auth::check()) {
            $updateData['batch_trans_by'] = Auth::id();
        }

        DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_updated', 'N')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->update($updateData);

        if ($isJson) {
            return response()->json([
                'success' => true,
                'message' => 'Document number saved successfully.',
                'batch_trans_id' => (int) $id,
                'batch_trans_doc_no' => $docNo !== '' ? $docNo : null,
            ]);
        }

        return redirect()->back()->with('success', 'Document number saved successfully.');
    } catch (\Throwable $e) {
        Log::error('Failed to update self-service loan doc number', [
            'batch_trans_id' => $id,
            'user_id' => Auth::id(),
            'message' => $e->getMessage(),
        ]);

        if ($isJson) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save document number.',
            ], 500);
        }

        return redirect()->back()->withErrors([
            'error' => 'Failed to save document number.',
        ]);
    }
}

}
