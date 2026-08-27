<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\MemberLoanLimitService;

class LoanApplicationSelfServiceController extends Controller
{
    public function listLoansPendingApproval(Request $request)
    {
        $pendingLoansOnly =
            $request->has('pending')
            && $request->input('pending') == '1'
            ? 'Y'
            : null;

        $query = DB::table('sacco_loan_batch_trans_members AS trans')
            ->join(
                'sacco_members AS members',
                'trans.batch_trans_member_id',
                '=',
                'members.member_id'
            )
            ->join(
                'sacco_loan_types AS types',
                'trans.batch_trans_loan_type',
                '=',
                'types.loan_type_id'
            )
            ->join(
                'sacco_loan_category AS category',
                'trans.batch_trans_loan_category',
                '=',
                'category.loan_category_id'
            )
            ->leftJoin(
                'sacco_loan_batch_guarantors_members AS guarantors',
                function ($join) {
                    $join->on(
                        'trans.batch_trans_id',
                        '=',
                        'guarantors.guarantors_loan_batch_trans_id'
                    )
                        ->where(
                            'guarantors.guarantors_deleted',
                            'N'
                        );
                }
            )
            ->leftJoin(
                'sacco_members AS g_members',
                'guarantors.guarantors_guarantor_id',
                '=',
                'g_members.member_id'
            )
            ->select(
                'trans.*',
                'members.*',
                'types.loan_type_name',
                'types.loan_type_guaranteable_percent',
                'category.loan_category_name',

                DB::raw(
                    'GROUP_CONCAT(
                    g_members.member_name
                    ORDER BY guarantors.guarantors_id ASC
                    SEPARATOR "|"
                ) AS guarantors_names'
                ),

                DB::raw(
                    'GROUP_CONCAT(
                    guarantors.guarantors_amount_guaranteed
                    ORDER BY guarantors.guarantors_id ASC
                    SEPARATOR "|"
                ) AS guarantors_amounts'
                ),

                DB::raw(
                    'GROUP_CONCAT(
                    guarantors.guarantors_approved
                    ORDER BY guarantors.guarantors_id ASC
                    SEPARATOR "|"
                ) AS guarantors_approval_status'
                )
            )
            ->groupBy('trans.batch_trans_id');

        if ($request->has('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(function ($q) use ($search) {
                $q->where(
                    'members.member_name',
                    'LIKE',
                    "%{$search}%"
                )
                    ->orWhere(
                        'members.member_phone_no',
                        'LIKE',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'members.member_national_id',
                        'LIKE',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'types.loan_type_name',
                        'LIKE',
                        "%{$search}%"
                    );
            });
        }

        if ($pendingLoansOnly) {
            $query
                ->where(
                    'trans.batch_trans_updated',
                    'N'
                )
                ->where(
                    'trans.batch_trans_deleted',
                    '!=',
                    'Y'
                );
        }

        $loans = $query
            ->orderBy(
                'trans.batch_trans_on',
                'desc'
            )
            ->limit(300)
            ->paginate(20);

        /*
    |--------------------------------------------------------------------------
    | Existing deduction / charge configuration
    |--------------------------------------------------------------------------
    */

        $deductionTypes = DB::table(
            'sacco_loan_deductions_types'
        )
            ->where(
                'deduction_type_deleted',
                'N'
            )
            ->where(
                'deduction_type_active',
                1
            )
            ->orderBy(
                'deduction_type_name',
                'asc'
            )
            ->get();

        $loanIds = $loans
            ->getCollection()
            ->pluck('batch_trans_id')
            ->filter()
            ->values();

        $applicationCharges = collect();

        if ($loanIds->isNotEmpty()) {
            $applicationCharges = DB::table(
                'sacco_loan_batch_trans_members_deductions'
            )
                ->whereIn(
                    'batch_trans_deduction_batch_trans_id',
                    $loanIds
                )
                ->where(
                    'batch_trans_deduction_deleted',
                    'N'
                )
                ->orderBy(
                    'batch_trans_deduction_batch_trans_id',
                    'asc'
                )
                ->orderBy(
                    'batch_trans_deduction_id',
                    'asc'
                )
                ->get()
                ->groupBy(
                    'batch_trans_deduction_batch_trans_id'
                );
        }

        /*
    |--------------------------------------------------------------------------
    | Credit Committee configuration
    |--------------------------------------------------------------------------
    */

        $creditCommitteeConfig =
            $this->getCreditCommitteeConfig();

        $creditCommitteeMembers =
            $this->getCreditCommitteeMembers(
                $creditCommitteeConfig['category']
            );

        $loggedInMemberId = (int) (
            Auth::user()->member_id ?? 0
        );

        /*
    |--------------------------------------------------------------------------
    | Is the logged-in user currently in the Credit Committee?
    |--------------------------------------------------------------------------
    */

        $authenticatedCreditCommitteeMember =
            $creditCommitteeMembers->first(
                function ($member) use ($loggedInMemberId) {
                    return (int) $member->member_id
                        === $loggedInMemberId;
                }
            );

        /*
    |--------------------------------------------------------------------------
    | Attach committee state to every loan
    |--------------------------------------------------------------------------
    */

        $loans->getCollection()->transform(
            function ($loan) use (
                $creditCommitteeConfig,
                $creditCommitteeMembers,
                $loggedInMemberId
            ) {
                $loan->credit_committee =
                    $this->buildCreditCommitteeApprovalState(
                        $loan->batch_trans_credit_committee_decisions
                            ?? null,

                        $creditCommitteeMembers,

                        $creditCommitteeConfig['required_approvals'],

                        $creditCommitteeConfig['category'],

                        $loggedInMemberId
                    );

                $requiresGuarantors =
                    (float) ($loan->loan_type_guaranteable_percent ?? 0) > 0;

                $guarantorStatuses = array_values(
                    array_filter(
                        explode(
                            '|',
                            (string) ($loan->guarantors_approval_status ?? '')
                        ),
                        fn($status) => trim($status) !== ''
                    )
                );

                $guarantorsReady =
                    !$requiresGuarantors
                    ||
                    (
                        !empty($guarantorStatuses)
                        && collect($guarantorStatuses)->every(
                            fn($status) =>
                            strtoupper(trim($status)) === 'Y'
                        )
                    );

                $loan->credit_committee['voting_open'] =
                    $guarantorsReady;

                return $loan;
            }
        );

        return view(
            'loans.selfservice.pending_approval',
            compact(
                'loans',
                'deductionTypes',
                'applicationCharges',
                'creditCommitteeConfig',
                'creditCommitteeMembers',
                'authenticatedCreditCommitteeMember'
            )
        );
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
                    'sacco_loan_types.loan_type_id as loan_type_id',
                    'sacco_loan_types.loan_type_name',
                    'sacco_loan_types.loan_type_max_amount as loan_type_max_amount',
                    'sacco_loan_types.loan_type_acount',
                    'sacco_loan_types.loan_type_guaranteable_percent',
                    'sacco_loan_types.loan_type_commission_effect',
                    'sacco_loan_types.loan_type_insurance_effect',
                    'sacco_loan_category.loan_category_name',
                    'sacco_members.member_name'
                )
                ->lockForUpdate()
                ->first();

            if (!$loan) {
                throw new \Exception('Loan not found or already processed.');
            }

            /*
|--------------------------------------------------------------------------
| Credit Committee final approval gate
|--------------------------------------------------------------------------
|
| Do not trust the Blade disabled button.
| Recalculate the condition immediately before final loan approval.
|--------------------------------------------------------------------------
*/

            $creditCommitteeConfig =
                $this->getCreditCommitteeConfig();

            $creditCommitteeMembers =
                $this->getCreditCommitteeMembers(
                    $creditCommitteeConfig['category']
                );

            $creditCommitteeState =
                $this->buildCreditCommitteeApprovalState(
                    $loan->batch_trans_credit_committee_decisions
                        ?? null,

                    $creditCommitteeMembers,

                    $creditCommitteeConfig['required_approvals'],

                    $creditCommitteeConfig['category'],

                    (int) (
                        Auth::user()->member_id
                        ?? 0
                    )
                );

            if (
                !$creditCommitteeState['can_final_approve']
            ) {
                throw new \Exception(
                    $creditCommitteeState['blocking_message']
                        ?? 'Credit Committee approval requirements '
                        . 'have not been satisfied.'
                );
            }
            /*
|--------------------------------------------------------------------------
| Final product and individual member-limit recheck
|--------------------------------------------------------------------------
| The applicable limit may have changed after the member submitted or
| edited the application.
|--------------------------------------------------------------------------
*/
            $memberLimitValidation = app(MemberLoanLimitService::class)
                ->validateRequestedAmount(
                    (int) $loan->batch_trans_member_id,
                    $loan,
                    (float) $loan->batch_trans_loan_amount
                );

            if (!$memberLimitValidation['is_valid']) {
                throw new \Exception(
                    $memberLimitValidation['message']
                );
            }

            $default_bank_account = $this->getDefaultAccount('default_bank_account');
            $default_insurance_account = $this->getDefaultAccount('default_insurance_account');
            $default_loan_commission_account = $this->getDefaultAccount('default_loan_commission_account');

            if (!$default_bank_account || !$default_insurance_account || !$default_loan_commission_account) {
                throw new \Exception('Missing default bank, insurance, or commission accounts.');
            }

            if ((float) ($loan->loan_type_guaranteable_percent ?? 0) > 0) {

                /*
    |--------------------------------------------------------------------------
    | Final guarantor capacity recheck
    |--------------------------------------------------------------------------
    |
    | Reconfirm the current share capacity of every guarantor immediately
    | before approval.
    |
    | Guaranteeing another member:
    |   member_total_share × max_guarantor_factor
    |   - member_tied_shares
    |   - other pending guarantees
    |
    | Self-guaranteeing:
    |   member_total_share × max_guarantor_factor_self
    |   - member_tied_shares_self
    |   - other pending self-guarantees
    |--------------------------------------------------------------------------
    */
                $finalCapacityCheck =
                    $this->validateGuarantorCapacityForFinalApproval(
                        $loan
                    );

                if (!$finalCapacityCheck['success']) {
                    throw new \Exception(
                        $finalCapacityCheck['message']
                    );
                }

                /*
    |--------------------------------------------------------------------------
    | Final total guarantee check
    |--------------------------------------------------------------------------
    */
                $guaranteeCheck = $this->isSufficientlyGuaranteed(
                    $loan,
                    $loan->batch_trans_loan_amount
                );

                if (!$guaranteeCheck['is_fully_guaranteed']) {
                    throw new \Exception(
                        'This loan application by '
                            . $loan->member_name
                            . ' is under-guaranteed. '
                            . 'Total Guaranteed: '
                            . number_format(
                                $guaranteeCheck['total_guaranteed'],
                                2
                            )
                            . '. Required Guarantee: '
                            . number_format(
                                $guaranteeCheck['required_guarantee'],
                                2
                            )
                            . '. Deficit: '
                            . number_format(
                                $guaranteeCheck['difference'],
                                2
                            )
                            . '.'
                    );
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
                'loan_credit_committee_decisions' =>
                $loan->batch_trans_credit_committee_decisions,
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
                ->whereRaw("COALESCE(guarantors_deleted, 'N') <> 'Y'")
                ->where('guarantors_approved', 'Y')
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
        $trustedMemberId = (int) auth()->user()->member_id;

        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();

        $loanCategories = DB::table('sacco_loan_category')
            ->where('loan_category_deleted', '<>', 'Y')
            ->orderBy('loan_category_name')
            ->get();

        $maximumNoOfGuarantors = (int) (
            DB::table('sacco_defaults')
            ->where('default_name', 'maximum_no_of_guarantors')
            ->value('default_value') ?? 3
        );

        $memberLoans = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_loans.*',
                'sacco_loan_types.loan_type_name',
                DB::raw('(COALESCE(sacco_loans.loan_amount,0) - COALESCE(sacco_loans.loan_loan_paid,0)) as loan_balance')
            )
            ->where('sacco_loans.loan_member', $trustedMemberId)
            ->whereRaw('COALESCE(sacco_loans.loan_amount,0) > COALESCE(sacco_loans.loan_loan_paid,0)')
            ->where('sacco_loans.loan_stoped', 'N')
            ->get();

        $maxGuarantorFactorSelf = (float) (
            DB::table('sacco_defaults')
            ->where('default_name', 'max_guarantor_factor_self')
            ->value('default_value') ?? 1
        );

        $member = DB::table('sacco_members')
            ->where('member_id', $trustedMemberId)
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->first();

        $selfGuaranteeAvailable = 0;



        if ($member) {
            $pendingSelfGuaranteeAmount = DB::table('sacco_loan_batch_guarantors_members as g')
                ->join('sacco_loan_batch_trans_members as t', 'g.guarantors_loan_batch_trans_id', '=', 't.batch_trans_id')
                ->where('g.guarantors_guarantor_id', $trustedMemberId)
                ->where('t.batch_trans_member_id', $trustedMemberId)
                ->whereRaw("COALESCE(g.guarantors_deleted, 'N') <> 'Y'")
                ->whereRaw("COALESCE(t.batch_trans_deleted, 'N') <> 'Y'")
                ->whereRaw("COALESCE(t.batch_trans_updated, 'N') = 'N'")
                ->sum('g.guarantors_amount_guaranteed');

            $selfGuaranteeAvailable = max(
                0,
                ((float) $member->member_total_share * $maxGuarantorFactorSelf)
                    - (float) $member->member_tied_shares_self
                    - (float) $pendingSelfGuaranteeAmount
            );
        }

        return view('loans.apply', compact(
            'loanTypes',
            'loanCategories',
            'maximumNoOfGuarantors',
            'memberLoans',
            'selfGuaranteeAvailable'
        ));
    }

    public function submitLoanApplication(Request $request)
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
                ], $payload), 201);
            }

            return redirect()
                ->route('loans.pending.approval.self')
                ->with('success', $message);
        };

        $principal = $request->user();

        if (!$principal || !isset($principal->member_id)) {
            $principal = Auth::user();
        }

        $trustedMemberId = isset($principal->member_id)
            ? (int) $principal->member_id
            : null;

        /*
|--------------------------------------------------------------------------
| Resolve the authoritative audit actor
|--------------------------------------------------------------------------
| Web requests use the standard authenticated guard.
| Mobile API requests use the member resolved by auth.api.
|--------------------------------------------------------------------------
*/
        $actorUserId = Auth::id()
            ?? $trustedMemberId;

        if (!$trustedMemberId) {
            return $respondError('Unauthenticated.', 401);
        }

        $postedMemberId = $request->input('batch_trans_member_id');

        if ($postedMemberId !== null && $postedMemberId !== '' && (int) $postedMemberId !== $trustedMemberId) {
            return $respondError('Unauthorized member reference.', 403, [
                'batch_trans_member_id' => ['Unauthorized member reference.'],
            ]);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'batch_trans_loan_amount' => 'required|numeric|min:1',
            'batch_trans_loan_type' => 'required|integer|exists:sacco_loan_types,loan_type_id',
            'batch_trans_loan_category' => 'required|integer|exists:sacco_loan_category,loan_category_id',
            'batch_trans_loan_duration' => 'required|integer|min:1|max:100',
            'batch_trans_description' => 'required|string|max:50',
            'batch_trans_commission' => 'nullable|numeric|min:0',
            'batch_trans_loan_to_top_up' => 'nullable|integer|exists:sacco_loans,loan_id',
            'batch_trans_pay1' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
            'batch_trans_pay2' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
            'batch_trans_payroll_number' => 'nullable|string|max:50',
            'batch_trans_present_designation' => 'nullable|string|max:100',
            'batch_trans_terms_of_employment' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $respondError('Validation failed.', 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            $loanType = DB::table('sacco_loan_types')
                ->where('loan_type_id', $validated['batch_trans_loan_type'])
                ->where('loan_type_deleted', '<>', 'Y')
                ->first();

            if (!$loanType) {
                DB::rollBack();
                return $respondError('Invalid loan type.', 422);
            }

            $loanCategory = DB::table('sacco_loan_category')
                ->where('loan_category_id', $validated['batch_trans_loan_category'])
                ->where('loan_category_deleted', '<>', 'Y')
                ->first();

            if (!$loanCategory) {
                DB::rollBack();
                return $respondError('Invalid loan category.', 422);
            }

            $member = DB::table('sacco_members')
                ->where('member_id', $trustedMemberId)
                ->where('member_active', 'Y')
                ->where('member_deleted', '<>', 'Y')
                ->first();

            if (!$member) {
                DB::rollBack();
                return $respondError('Invalid member.', 422);
            }

            $loanAmount = round((float) $validated['batch_trans_loan_amount'], 2);
            $loanDuration = (int) $validated['batch_trans_loan_duration'];
            $commission = round((float) ($validated['batch_trans_commission'] ?? 0), 2);

            $topUpLoan = null;
            $topUpLoanId = $validated['batch_trans_loan_to_top_up'] ?? null;
            $topUpOutstanding = 0.00;

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

                $topUpOutstanding = round(
                    (float) (($topUpLoan->loan_amount ?? 0) - ($topUpLoan->loan_loan_paid ?? 0)),
                    2
                );

                if ($loanAmount <= $topUpOutstanding) {
                    DB::rollBack();
                    return $respondError('Invalid top-up loan. New loan amount must exceed the current outstanding balance.', 422);
                }
            }

            /*
|--------------------------------------------------------------------------
| Product maximum and individual member limit
|--------------------------------------------------------------------------
*/
            $memberLimitValidation = app(MemberLoanLimitService::class)
                ->validateRequestedAmount(
                    (int) $trustedMemberId,
                    $loanType,
                    (float) $loanAmount
                );

            if (!$memberLimitValidation['is_valid']) {
                DB::rollBack();

                return $respondError(
                    $memberLimitValidation['message'],
                    422,
                    [
                        'batch_trans_loan_amount' => [
                            $memberLimitValidation['message'],
                        ],
                    ]
                );
            }

            $nmsg = '';
            $nmsg .= $this->validateLoanParameters(
                $loanAmount,
                $loanType,
                $loanDuration,
                $topUpLoan
            );
            $nmsg .= $this->validateMemberEligibility($member, $loanType);



            if (!empty($nmsg)) {
                DB::rollBack();
                return $respondError(trim($nmsg), 422, [
                    'loan' => [trim($nmsg)],
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | New application has no adjusted charges yet.
        |--------------------------------------------------------------------------
        */
            $amountForEmi = $loanAmount;

            $financials = $this->calculateSaccoLoanFinancials(
                $loanAmount,
                $amountForEmi,
                $loanDuration,
                $loanType,
                $commission
            );

            $payslip1Path = null;
            if ($request->hasFile('batch_trans_pay1')) {
                $payslip1Path = $request->file('batch_trans_pay1')->store('uploads/payslips');
            }

            $payslip2Path = null;
            if ($request->hasFile('batch_trans_pay2')) {
                $payslip2Path = $request->file('batch_trans_pay2')->store('uploads/payslips');
            }

            $insertData = [
                'batch_trans_batch_id' => $trustedMemberId,
                'batch_trans_member_id' => $trustedMemberId,
                'batch_trans_loan_type' => (int) $validated['batch_trans_loan_type'],
                'batch_trans_loan_category' => (int) $validated['batch_trans_loan_category'],
                'batch_trans_loan_amount' => $loanAmount,
                'batch_trans_loan_duration' => $loanDuration,
                'batch_trans_monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
                'batch_trans_monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
                'batch_trans_doc_no' => 'N/A',
                'batch_trans_description' => $validated['batch_trans_description'],
                'batch_trans_commission' => $commission,
                'batch_trans_loan_to_top_up_amount' => $topUpOutstanding,
                'batch_trans_loan_to_top_up' => !empty($topUpLoanId) ? (int) $topUpLoanId : 0,
                'batch_trans_insurance' => round((float) ($financials['insurance'] ?? 0), 2),
                'batch_trans_loan_guaranteed' => 0,
                'batch_trans_expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
                'batch_trans_updated' => 'N',
                'batch_trans_payslip1' => $payslip1Path,
                'batch_trans_payslip2' => $payslip2Path,
                'batch_trans_by' => $actorUserId,
                'batch_trans_on' => now(),
                'batch_trans_ip' => $request->ip(),
                'batch_trans_deleted' => 'N',
            ];

            if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_payroll_number')) {
                $insertData['batch_trans_payroll_number'] = $validated['batch_trans_payroll_number'] ?? null;
            }

            if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_present_designation')) {
                $insertData['batch_trans_present_designation'] = $validated['batch_trans_present_designation'] ?? null;
            }

            if (Schema::hasColumn('sacco_loan_batch_trans_members', 'batch_trans_terms_of_employment')) {
                $insertData['batch_trans_terms_of_employment'] = $validated['batch_trans_terms_of_employment'] ?? null;
            }

            $batchTransId = DB::table('sacco_loan_batch_trans_members')->insertGetId($insertData);

            $guarantorCheck = $this->validateAndProcessGuarantors(
                array_merge($request->all(), [
                    'batch_trans_member_id' => $trustedMemberId,
                ]),
                $loanType,
                $batchTransId,
                $loanAmount
            );

            if (!$guarantorCheck['success']) {
                DB::rollBack();

                return $respondError(trim($guarantorCheck['message']), 422, [
                    'guarantors' => [trim($guarantorCheck['message'])],
                ]);
            }

            DB::table('sacco_loan_batch_trans_members')
                ->where('batch_trans_id', $batchTransId)
                ->where('batch_trans_member_id', $trustedMemberId)
                ->where('batch_trans_deleted', '<>', 'Y')
                ->where('batch_trans_updated', 'N')
                ->update([
                    'batch_trans_loan_guaranteed' => round((float) ($guarantorCheck['total_guaranteed'] ?? 0), 2),
                    'batch_trans_ip' => $request->ip(),
                    'batch_trans_by' => $actorUserId,
                ]);

            DB::commit();

            return $respondSuccess('Loan application submitted successfully.', [
                'batch_trans_id' => (int) $batchTransId,
                'insurance' => round((float) ($financials['insurance'] ?? 0), 2),
                'monthly_payment' => round((float) ($financials['monthly_payment'] ?? 0), 2),
                'monthly_payment_principal' => round((float) ($financials['monthly_payment_principal'] ?? 0), 2),
                'expected_interest' => round((float) ($financials['expected_interest'] ?? 0), 2),
                'total_guaranteed' => round((float) ($guarantorCheck['total_guaranteed'] ?? 0), 2),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to submit self-service loan application', [
                'member_id' => $trustedMemberId,
                'user_id' => $actorUserId,
                'message' => $e->getMessage(),
            ]);

            return $respondError(
                'Failed to submit the loan application. Please try again or contact support.',
                500
            );
        }
    }

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
        $actorUserId = Auth::id()
            ?? $trustedMemberId;

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
            /*
|--------------------------------------------------------------------------
| Product maximum and individual member limit
|--------------------------------------------------------------------------
*/
            $memberLimitValidation = app(MemberLoanLimitService::class)
                ->validateRequestedAmount(
                    (int) $trustedMemberId,
                    $loanType,
                    (float) $loanAmount
                );

            if (!$memberLimitValidation['is_valid']) {
                DB::rollBack();

                return $respondError(
                    $memberLimitValidation['message'],
                    422,
                    [
                        'batch_trans_loan_amount' => [
                            $memberLimitValidation['message'],
                        ],
                    ]
                );
            }

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
        | 8. Validate fresh guarantors before updating loan
        |--------------------------------------------------------------------------
        */



            $guarantorCheck = $this->validateAndProcessGuarantors(
                array_merge($request->all(), [
                    'batch_trans_member_id' => $trustedMemberId,
                ]),
                $loanType,
                (int) $id,
                $loanAmount,
                false // validation only; do not save here because replaceLoanGuarantorsForEdit() saves later
            );




            if (!$guarantorCheck['success']) {
                DB::rollBack();
                return $respondError(trim($guarantorCheck['message']), 422, [
                    'guarantors' => [trim($guarantorCheck['message'])],
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
                'batch_trans_loan_guaranteed' => round((float) ($guarantorCheck['total_guaranteed'] ?? 0), 2),
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

            /*
|--------------------------------------------------------------------------
| 11. Replace old guarantors with fresh validated guarantors
|--------------------------------------------------------------------------
*/
            $this->replaceLoanGuarantorsForEdit(
                (int) $id,
                $guarantorCheck['guarantors'],
                $actorUserId,
                $request->ip(),
                now(),
                (string) ($member->member_name ?? '')
            );

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

            return $respondError(
                'Failed to update the loan application. Please try again or contact support.',
                500
            );
        }
    }

    private function replaceLoanGuarantorsForEdit(
        int $batchTransId,
        array $guarantors,
        ?int $actorUserId,
        string $ip,
        $transdate,
        string $borrowerName = ''
    ): void {
        $existingRows = DB::table('sacco_loan_batch_guarantors_members')
            ->where('guarantors_loan_batch_trans_id', $batchTransId)
            ->whereRaw("COALESCE(guarantors_deleted, 'N') <> 'Y'")
            ->get()
            ->keyBy('guarantors_guarantor_id');

        $submittedGuarantorIds = collect($guarantors)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();

        /*
    |--------------------------------------------------------------------------
    | Soft-delete guarantors removed during edit
    |--------------------------------------------------------------------------
    */
        if (!empty($submittedGuarantorIds)) {
            DB::table('sacco_loan_batch_guarantors_members')
                ->where('guarantors_loan_batch_trans_id', $batchTransId)
                ->whereRaw("COALESCE(guarantors_deleted, 'N') <> 'Y'")
                ->whereNotIn('guarantors_guarantor_id', $submittedGuarantorIds)
                ->update([
                    'guarantors_deleted' => 'Y',
                    'guarantors_deleted_by' => $actorUserId,
                    'guarantors_deleted_on' => $transdate,
                    'guarantors_deleted_ip' => $ip,
                ]);
        } else {
            DB::table('sacco_loan_batch_guarantors_members')
                ->where('guarantors_loan_batch_trans_id', $batchTransId)
                ->whereRaw("COALESCE(guarantors_deleted, 'N') <> 'Y'")
                ->update([
                    'guarantors_deleted' => 'Y',
                    'guarantors_deleted_by' => $actorUserId,
                    'guarantors_deleted_on' => $transdate,
                    'guarantors_deleted_ip' => $ip,
                ]);

            return;
        }

        $description = 'Guarantor processed after loan edit'
            . (!empty($borrowerName) ? ' - ' . $borrowerName : '');

        foreach ($guarantors as $guarantor) {
            $guarantorId = (int) $guarantor['id'];
            $amount = round((float) $guarantor['amount'], 2);

            if (isset($existingRows[$guarantorId])) {
                DB::table('sacco_loan_batch_guarantors_members')
                    ->where('guarantors_id', $existingRows[$guarantorId]->guarantors_id)
                    ->update([
                        'guarantors_amount_guaranteed' => $amount,
                        'guarantors_description' => $description,
                        'guarantors_approved' => 'N',
                        'guarantors_approved_on' => null,
                        'guarantors_email_sent' => 'N',
                        'guarantors_by' => $actorUserId,
                        'guarantors_on' => $transdate,
                        'guarantors_ip' => $ip,
                        'guarantors_deleted' => 'N',
                        'guarantors_deleted_by' => null,
                        'guarantors_deleted_on' => null,
                        'guarantors_deleted_ip' => null,
                    ]);
            } else {
                DB::table('sacco_loan_batch_guarantors_members')->insert([
                    'guarantors_loan_batch_trans_id' => $batchTransId,
                    'guarantors_guarantor_id' => $guarantorId,
                    'guarantors_amount_guaranteed' => $amount,
                    'guarantors_description' => $description,
                    'guarantors_transfered' => null,
                    'guarantors_approved' => 'N',
                    'guarantors_approved_on' => null,
                    'guarantors_email_sent' => 'N',
                    'guarantors_by' => $actorUserId,
                    'guarantors_on' => $transdate,
                    'guarantors_ip' => $ip,
                    'guarantors_deleted' => 'N',
                    'guarantors_deleted_by' => null,
                    'guarantors_deleted_on' => null,
                    'guarantors_deleted_ip' => null,
                ]);
            }
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
        $actorUserId = Auth::id()
            ?? $trustedMemberId;

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

            return $respondError(
                'Failed to delete the guarantor. Please try again or contact support.',
                500
            );
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
        $actorUserId = Auth::id()
            ?? $trustedMemberId;

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
            /*
|--------------------------------------------------------------------------
| Product maximum and individual member limit
|--------------------------------------------------------------------------
*/
            $memberLimitValidation = app(MemberLoanLimitService::class)
                ->validateRequestedAmount(
                    (int) $trustedMemberId,
                    $loanType,
                    (float) $loanAmount
                );

            if (!$memberLimitValidation['is_valid']) {
                DB::rollBack();

                return $respondError(
                    $memberLimitValidation['message'],
                    422,
                    [
                        'batch_trans_loan_amount' => [
                            $memberLimitValidation['message'],
                        ],
                    ]
                );
            }

            $nmsg = '';
            $nmsg .= $this->validateLoanParameters(
                $loanAmount,
                $loanType,
                $loanDuration,
                $topUpLoan
            );
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

            $guarantorCheck = $this->validateAndProcessGuarantors(
                array_merge($request->all(), [
                    'batch_trans_member_id' => $trustedMemberId,
                ]),
                $loanType,
                $batchTransId,
                $loanAmount
            );

            if (!$guarantorCheck['success']) {
                DB::rollBack();
                return $respondError(trim($guarantorCheck['message']), 422, [
                    'guarantors' => [trim($guarantorCheck['message'])],
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
                'batch_trans_loan_guaranteed' => round((float) ($guarantorCheck['total_guaranteed'] ?? 0), 2),
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

            return $respondError(
                'Failed to process the loan application. Please try again or contact support.',
                500
            );
        }
    }
    private function validateAndProcessGuarantors($data, $loanType, $batchTransId, $loanAmount, bool $saveGuarantors = true)
    {
        $nmsg = '';
        $desiredGuarantors = [];
        $totalGuaranteed = 0.00;
        $seenGuarantorIds = [];


        $borrowerMemberId = (int) (
            $data['batch_trans_member_id'] ?? 0
        );

        $requestPrincipal = request()->user();

        $actorUserId = Auth::id()
            ?? (
                isset($requestPrincipal->member_id)
                ? (int) $requestPrincipal->member_id
                : null
            )
            ?? (
                $borrowerMemberId > 0
                ? $borrowerMemberId
                : null
            );

        if (!$actorUserId) {
            return [
                'success' => false,
                'message' => 'Unauthenticated.',
                'guarantors' => [],
                'total_guaranteed' => 0.00,
                'required_guarantee' => 0.00,
            ];
        }


        $maximumNoOfGuarantors = (int) (
            DB::table('sacco_defaults')
            ->where('default_name', 'maximum_no_of_guarantors')
            ->value('default_value') ?? 3
        );

        foreach (['max_guarantor_factor', 'max_guarantor_factor_self'] as $defaultName) {
            $exists = DB::table('sacco_defaults')
                ->where('default_name', $defaultName)
                ->exists();

            if (!$exists) {
                DB::table('sacco_defaults')->insert([
                    'default_name' => $defaultName,
                    'default_value' => 1,
                    'default_transdate' => now(),
                    'default_userid' => $actorUserId,
                    'default_ip' => request()->ip(),
                ]);
            }
        }

        $maxGuarantorFactor = (float) (
            DB::table('sacco_defaults')
            ->where('default_name', 'max_guarantor_factor')
            ->value('default_value') ?? 1
        );

        $maxGuarantorFactorSelf = (float) (
            DB::table('sacco_defaults')
            ->where('default_name', 'max_guarantor_factor_self')
            ->value('default_value') ?? 1
        );



        /*
    |--------------------------------------------------------------------------
    | Normalize web + API guarantor payloads into one structure
    |--------------------------------------------------------------------------
    */
        $submittedRows = [];

        if (isset($data['guarantors']) && is_array($data['guarantors'])) {
            foreach ($data['guarantors'] as $row) {
                $submittedRows[] = [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'amount' => !empty($row['amount'])
                        ? round((float) str_replace(',', '', (string) $row['amount']), 2)
                        : 0.00,
                ];
            }
        } else {
            $names = $data['guarantors_guarantor_name'] ?? [];
            $amounts = $data['guarantors_amount_guaranteed'] ?? [];

            for ($i = 0; $i < $maximumNoOfGuarantors; $i++) {
                $submittedRows[] = [
                    'name' => trim((string) ($names[$i] ?? '')),
                    'amount' => !empty($amounts[$i])
                        ? round((float) str_replace(',', '', (string) $amounts[$i]), 2)
                        : 0.00,
                ];
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Existing rows are only used if no guarantors are submitted.
    | If the user submits guarantor rows, those rows become the new final list.
    |--------------------------------------------------------------------------
    */
        $hasSubmittedGuarantors = collect($submittedRows)->contains(function ($row) {
            return ($row['name'] ?? '') !== '' || (float) ($row['amount'] ?? 0) > 0;
        });

        if (!$hasSubmittedGuarantors) {
            $existingRows = DB::table('sacco_loan_batch_guarantors_members as g')
                ->join('sacco_members as m', 'g.guarantors_guarantor_id', '=', 'm.member_id')
                ->select(
                    'm.member_name',
                    'm.member_sacco_id',
                    'g.guarantors_amount_guaranteed'
                )
                ->where('g.guarantors_loan_batch_trans_id', $batchTransId)
                ->whereRaw("COALESCE(g.guarantors_deleted, 'N') <> 'Y'")
                ->get();

            $submittedRows = $existingRows->map(function ($row) {
                return [
                    'name' => $row->member_name . ' - (' . $row->member_sacco_id . ')',
                    'amount' => round((float) $row->guarantors_amount_guaranteed, 2),
                ];
            })->toArray();
        }

        foreach ($submittedRows as $index => $row) {
            $guarantorName = trim((string) ($row['name'] ?? ''));
            $guarantorAmount = round((float) ($row['amount'] ?? 0), 2);

            if ($guarantorName === '' && $guarantorAmount <= 0) {
                continue;
            }

            if ($guarantorName === '') {
                $nmsg .= 'Error: Missing guarantor name on row ' . ($index + 1) . '. ';
                continue;
            }

            if ($guarantorAmount <= 0) {
                $nmsg .= "Error: Guarantor amount for {$guarantorName} must be greater than zero. ";
                continue;
            }

            $nameParts = explode(' - (', $guarantorName);
            $memberName = trim($nameParts[0] ?? '');
            $memberSaccoId = trim(isset($nameParts[1]) ? rtrim($nameParts[1], ") \t\n\r\0\x0B") : '');

            if ($memberName === '' || $memberSaccoId === '') {
                $nmsg .= "Error: Guarantor {$guarantorName} is not in the expected format MEMBER NAME - (SACCO ID). ";
                continue;
            }

            $guarantor = DB::table('sacco_members')
                ->where('member_name', $memberName)
                ->where('member_sacco_id', $memberSaccoId)
                ->where('member_active', 'Y')
                ->where('member_deleted', '<>', 'Y')
                ->first();

            if (!$guarantor) {
                $nmsg .= "Error: Guarantor {$guarantorName} not found or inactive. ";
                continue;
            }

            $guarantorId = (int) $guarantor->member_id;

            if (in_array($guarantorId, $seenGuarantorIds, true)) {
                $nmsg .= "Error: Guarantor {$guarantorName} has already been added to this loan. ";
                continue;
            }


            /*
|--------------------------------------------------------------------------
| Capacity-based guarantee control
|--------------------------------------------------------------------------
| A guarantor may appear in more than one pending application, but the
| system must subtract pending exposure from the correct guarantee pool:
|
| 1. Guarantees for other members use member_tied_shares
| 2. Self-guarantees use member_tied_shares_self
|--------------------------------------------------------------------------
*/

            if ($borrowerMemberId !== $guarantorId) {
                /*
    |--------------------------------------------------------------------------
    | Pending guarantees for OTHER members only
    |--------------------------------------------------------------------------
    */
                $pendingOtherGuaranteeAmount = DB::table('sacco_loan_batch_guarantors_members as g')
                    ->join('sacco_loan_batch_trans_members as t', 'g.guarantors_loan_batch_trans_id', '=', 't.batch_trans_id')
                    ->where('g.guarantors_guarantor_id', $guarantorId)
                    ->where('t.batch_trans_member_id', '<>', $guarantorId)
                    ->whereRaw("COALESCE(g.guarantors_deleted, 'N') <> 'Y'")
                    ->whereRaw("COALESCE(t.batch_trans_deleted, 'N') <> 'Y'")
                    ->whereRaw("COALESCE(t.batch_trans_updated, 'N') = 'N'")
                    ->where('t.batch_trans_id', '<>', $batchTransId)
                    ->sum('g.guarantors_amount_guaranteed');

                $availableToGuaranteeOthers = (
                    ((float) $guarantor->member_total_share * $maxGuarantorFactor)
                    - (float) $guarantor->member_tied_shares
                    - (float) $pendingOtherGuaranteeAmount
                );

                if ($availableToGuaranteeOthers < $guarantorAmount) {
                    $nmsg .= "Error: Guarantor {$guarantorName} does not have enough free shares to guarantee others. "
                        . "Available guarantee capacity is " . number_format(max(0, $availableToGuaranteeOthers), 2)
                        . ", pending guarantees for others are " . number_format((float) $pendingOtherGuaranteeAmount, 2)
                        . ", requested guarantee is " . number_format($guarantorAmount, 2) . ". ";
                    continue;
                }
            } else {
                /*
    |--------------------------------------------------------------------------
    | Pending SELF guarantees only
    |--------------------------------------------------------------------------
    */
                $pendingSelfGuaranteeAmount = DB::table('sacco_loan_batch_guarantors_members as g')
                    ->join('sacco_loan_batch_trans_members as t', 'g.guarantors_loan_batch_trans_id', '=', 't.batch_trans_id')
                    ->where('g.guarantors_guarantor_id', $guarantorId)
                    ->where('t.batch_trans_member_id', $guarantorId)
                    ->whereRaw("COALESCE(g.guarantors_deleted, 'N') <> 'Y'")
                    ->whereRaw("COALESCE(t.batch_trans_deleted, 'N') <> 'Y'")
                    ->whereRaw("COALESCE(t.batch_trans_updated, 'N') = 'N'")
                    ->where('t.batch_trans_id', '<>', $batchTransId)
                    ->sum('g.guarantors_amount_guaranteed');

                $availableSelfGuarantee = (
                    ((float) $guarantor->member_total_share * $maxGuarantorFactorSelf)
                    - (float) $guarantor->member_tied_shares_self
                    - (float) $pendingSelfGuaranteeAmount
                );

                if ($availableSelfGuarantee < $guarantorAmount) {
                    $nmsg .= "Error: Guarantor {$guarantorName} does not have enough free shares to self-guarantee. "
                        . "Available self-guarantee capacity is " . number_format(max(0, $availableSelfGuarantee), 2)
                        . ", pending self-guarantees are " . number_format((float) $pendingSelfGuaranteeAmount, 2)
                        . ", requested guarantee is " . number_format($guarantorAmount, 2) . ". ";
                    continue;
                }
            }

            $seenGuarantorIds[] = $guarantorId;

            $desiredGuarantors[] = [
                'id' => $guarantorId,
                'name' => (string) $guarantor->member_name,
                'member_sacco_id' => (string) $guarantor->member_sacco_id,
                'amount' => $guarantorAmount,
            ];

            $totalGuaranteed += $guarantorAmount;
        }

        if (count($desiredGuarantors) > $maximumNoOfGuarantors) {
            $nmsg .= "Error: Total guarantors on this loan cannot exceed {$maximumNoOfGuarantors}. ";
        }

        $requiredGuarantee = round(
            $loanAmount * ((float) ($loanType->loan_type_guaranteable_percent ?? 0)) / 100,
            2
        );

        /*
|--------------------------------------------------------------------------
| Stop here if there are row-level guarantor errors
|--------------------------------------------------------------------------
| Example: locked guarantor, inactive guarantor, duplicate guarantor,
| insufficient shares, invalid format, etc.
|
| This prevents confusing double messages like:
| "Guarantor is already attached..." + "This loan requires guarantors."
|--------------------------------------------------------------------------
*/
        if (!empty($nmsg)) {
            return [
                'success' => false,
                'message' => trim($nmsg),
                'guarantors' => [],
                'total_guaranteed' => 0,
                'required_guarantee' => $requiredGuarantee,
            ];
        }

        if ((float) ($loanType->loan_type_guaranteable_percent ?? 0) > 0) {
            if (empty($desiredGuarantors)) {
                $nmsg .= 'Error: This loan requires guarantors. ';
            } elseif ($requiredGuarantee > $totalGuaranteed) {
                $nmsg .= 'Error: This loan application is under-guaranteed. '
                    . 'Required guarantee is ' . number_format($requiredGuarantee, 2)
                    . ', but total submitted guarantee is ' . number_format($totalGuaranteed, 2) . '. ';
            } elseif ($totalGuaranteed > $requiredGuarantee) {
                $prorationFactor = $requiredGuarantee / $totalGuaranteed;

                foreach ($desiredGuarantors as $idx => $guarantor) {
                    $desiredGuarantors[$idx]['amount'] = round($guarantor['amount'] * $prorationFactor, 2);
                }

                $proratedTotal = round(array_sum(array_column($desiredGuarantors, 'amount')), 2);

                if (!empty($desiredGuarantors) && $proratedTotal != $requiredGuarantee) {
                    $difference = round($requiredGuarantee - $proratedTotal, 2);
                    $lastIndex = count($desiredGuarantors) - 1;
                    $desiredGuarantors[$lastIndex]['amount'] = round($desiredGuarantors[$lastIndex]['amount'] + $difference, 2);
                }

                $totalGuaranteed = round(array_sum(array_column($desiredGuarantors, 'amount')), 2);
            }
        }


        // if (count($desiredGuarantors) > $maximumNoOfGuarantors) {
        //     $nmsg .= "Error: Total guarantors on this loan cannot exceed {$maximumNoOfGuarantors}. ";
        // }

        // $requiredGuarantee = round(
        //     $loanAmount * ((float) ($loanType->loan_type_guaranteable_percent ?? 0)) / 100,
        //     2
        // );

        // if ((float) ($loanType->loan_type_guaranteable_percent ?? 0) > 0) {
        //     if (empty($desiredGuarantors)) {
        //         $nmsg .= 'Error: This loan requires guarantors. ';
        //     } elseif ($requiredGuarantee > $totalGuaranteed) {
        //         $nmsg .= 'Error: This loan application is under-guaranteed. '
        //             . 'Required guarantee is ' . number_format($requiredGuarantee, 2)
        //             . ', but total submitted guarantee is ' . number_format($totalGuaranteed, 2) . '. ';
        //     } elseif ($totalGuaranteed > $requiredGuarantee) {
        //         $prorationFactor = $requiredGuarantee / $totalGuaranteed;

        //         foreach ($desiredGuarantors as $idx => $guarantor) {
        //             $desiredGuarantors[$idx]['amount'] = round($guarantor['amount'] * $prorationFactor, 2);
        //         }

        //         $proratedTotal = round(array_sum(array_column($desiredGuarantors, 'amount')), 2);

        //         if (!empty($desiredGuarantors) && $proratedTotal != $requiredGuarantee) {
        //             $difference = round($requiredGuarantee - $proratedTotal, 2);
        //             $lastIndex = count($desiredGuarantors) - 1;
        //             $desiredGuarantors[$lastIndex]['amount'] = round($desiredGuarantors[$lastIndex]['amount'] + $difference, 2);
        //         }

        //         $totalGuaranteed = round(array_sum(array_column($desiredGuarantors, 'amount')), 2);
        //     }
        // }

        if (!empty($nmsg)) {
            return [
                'success' => false,
                'message' => trim($nmsg),
                'guarantors' => [],
                'total_guaranteed' => 0,
                'required_guarantee' => $requiredGuarantee,
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | Replace the current pending guarantors for this application
    |--------------------------------------------------------------------------
    | This resets approval because the applicant has submitted/changed the
    | guarantee structure.
    |--------------------------------------------------------------------------
    */
        if ($saveGuarantors) {
            DB::table('sacco_loan_batch_guarantors_members')
                ->where('guarantors_loan_batch_trans_id', $batchTransId)
                ->whereRaw("COALESCE(guarantors_deleted, 'N') <> 'Y'")
                ->update([
                    'guarantors_deleted' => 'Y',
                    'guarantors_deleted_by' => $actorUserId,
                    'guarantors_deleted_on' => now(),
                    'guarantors_deleted_ip' => request()->ip(),
                ]);

            foreach ($desiredGuarantors as $guarantor) {
                DB::table('sacco_loan_batch_guarantors_members')->insert([
                    'guarantors_loan_batch_trans_id' => $batchTransId,
                    'guarantors_guarantor_id' => $guarantor['id'],
                    'guarantors_amount_guaranteed' => round((float) $guarantor['amount'], 2),
                    'guarantors_description' => 'Guarantor submitted via self-service loan application',
                    'guarantors_transfered' => null,
                    'guarantors_approved' => 'N',
                    'guarantors_approved_on' => null,
                    'guarantors_email_sent' => 'N',
                    'guarantors_by' => $actorUserId,
                    'guarantors_on' => now(),
                    'guarantors_ip' => request()->ip(),
                    'guarantors_deleted' => 'N',
                    'guarantors_deleted_by' => null,
                    'guarantors_deleted_on' => null,
                    'guarantors_deleted_ip' => null,
                ]);
            }
        }

        return [
            'success' => true,
            'message' => 'Guarantors validated and saved successfully.',
            'guarantors' => $desiredGuarantors,
            'total_guaranteed' => round($totalGuaranteed, 2),
            'required_guarantee' => $requiredGuarantee,
        ];
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
            return 'Error: Member not found in the database.';
        }

        $rawInstant = $loanType->loan_type_instant_qualification ?? 0;

        $isInstant =
            (is_numeric($rawInstant) && (int) $rawInstant === 1)
            || in_array(
                strtoupper(trim((string) $rawInstant)),
                ['Y', 'YES', 'TRUE'],
                true
            );

        if ($isInstant) {
            return null;
        }

        $membershipDurationRequired = max(
            0,
            (int) ($loanType->loan_type_qualification_period ?? 0)
        );

        if (
            $membershipDurationRequired > 0
            && !empty($member->member_date_joined)
            && strtotime($member->member_date_joined)
            > strtotime("-{$membershipDurationRequired} months")
        ) {
            return "Error: Member must be {$membershipDurationRequired} "
                . 'months old in the SACCO to take this loan.';
        }

        return null;
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
            'calc_loan_interest_insurance_kass',
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
        } elseif ($calcMethod === 'calc_loan_interest_insurance_kass') {
            $insurance = round(($requestedLoanAmount * 10.4) / 1000, 2);
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
    /*
|--------------------------------------------------------------------------
| Credit Committee configuration
|--------------------------------------------------------------------------
*/

    private function getCreditCommitteeConfig(): array
    {
        $category = trim(
            (string) (
                DB::table('sacco_defaults')
                ->where(
                    'default_name',
                    'CREDIT_COMMITTEE'
                )
                ->value('default_value')
                ?? ''
            )
        );

        $requiredValue = DB::table('sacco_defaults')
            ->where(
                'default_name',
                'CREDIT_COMMITTEE_REQUIRED_APPROVALS'
            )
            ->value('default_value');

        /*
     * If this setting is somehow missing or invalid,
     * fail safely by requiring one approval rather than
     * accidentally bypassing committee approval.
     */
        $requiredApprovals = is_numeric($requiredValue)
            ? max(0, (int) $requiredValue)
            : 1;

        return [
            'category' => $category,
            'required_approvals' =>
            $requiredApprovals,
        ];
    }


    /*
|--------------------------------------------------------------------------
| Current active Credit Committee members
|--------------------------------------------------------------------------
|
| Member must:
| - be position 2
| - be active
| - not be deleted
| - have an active classification
| - have an active current assignment
| - belong to the category configured in sacco_defaults
|
| Ordering comes from classification_sort_order.
|--------------------------------------------------------------------------
*/

    private function getCreditCommitteeMembers(
        ?string $category = null
    ) {
        if ($category === null) {
            $category = $this
                ->getCreditCommitteeConfig()['category'];
        }

        $category = trim((string) $category);

        if ($category === '') {
            return collect();
        }

        $members = DB::table(
            'sacco_members as m'
        )
            ->join(
                'sacco_member_classification_members as mcm',
                'mcm.member_id',
                '=',
                'm.member_id'
            )
            ->join(
                'sacco_member_classifications as c',
                'c.classification_id',
                '=',
                'mcm.classification_id'
            )

            ->where(
                'm.member_position',
                2
            )

            ->where(
                'm.member_active',
                'Y'
            )

            ->whereRaw(
                "COALESCE(m.member_deleted, 'N') <> 'Y'"
            )

            ->where(
                'c.classification_category',
                $category
            )

            ->where(
                'c.classification_active',
                'Y'
            )

            ->where(
                'mcm.classification_member_active',
                'Y'
            )

            ->where(function ($query) {
                $query
                    ->whereNull(
                        'mcm.classification_date_from'
                    )
                    ->orWhereDate(
                        'mcm.classification_date_from',
                        '<=',
                        today()
                    );
            })

            ->where(function ($query) {
                $query
                    ->whereNull(
                        'mcm.classification_date_to'
                    )
                    ->orWhereDate(
                        'mcm.classification_date_to',
                        '>=',
                        today()
                    );
            })

            ->select(
                'm.member_id',
                'm.member_name',
                'm.member_sacco_id',

                'c.classification_id',
                'c.classification_name',
                'c.classification_code',
                'c.classification_category',
                'c.classification_sort_order'
            )

            ->orderBy(
                'c.classification_sort_order',
                'asc'
            )

            ->orderBy(
                'c.classification_name',
                'asc'
            )

            ->orderBy(
                'm.member_name',
                'asc'
            )

            ->get();

        /*
     * If somebody accidentally holds two roles inside
     * CREDIT_COMMITTEE, they must still count as ONE voter.
     *
     * The first role is retained because the query has already
     * been ordered by classification_sort_order.
     */
        return $members
            ->unique('member_id')
            ->values();
    }


    /*
|--------------------------------------------------------------------------
| Current logged-in Credit Committee member
|--------------------------------------------------------------------------
*/

    private function getAuthenticatedCreditCommitteeMember()
    {
        $user = Auth::user();

        if (
            !$user
            || empty($user->member_id)
        ) {
            return null;
        }

        $config =
            $this->getCreditCommitteeConfig();

        return $this
            ->getCreditCommitteeMembers(
                $config['category']
            )
            ->first(
                function ($member) use ($user) {
                    return (int) $member->member_id
                        === (int) $user->member_id;
                }
            );
    }


    /*
|--------------------------------------------------------------------------
| Decode saved Credit Committee decisions
|--------------------------------------------------------------------------
|
| Standard format:
|
| {
|   "120": {
|       "decision": "Y",
|       "decided_at": "2026-08-20 17:30:00",
|       "ip": "..."
|   }
| }
|
| The method also understands an optional older/wrapped:
| {"decisions": {...}}
|--------------------------------------------------------------------------
*/

    private function decodeCreditCommitteeDecisions(
        $json
    ): array {
        if (
            $json === null
            || trim((string) $json) === ''
        ) {
            return [];
        }

        $decoded = json_decode(
            (string) $json,
            true
        );

        if (!is_array($decoded)) {
            return [];
        }

        if (
            isset($decoded['decisions'])
            && is_array($decoded['decisions'])
        ) {
            $decoded = $decoded['decisions'];
        }

        /*
     * Also support imported legacy arrays such as:
     *
     * [
     *   {"member_id": 10, "decision": "Y"}
     * ]
     */
        if (array_is_list($decoded)) {
            $normalized = [];

            foreach ($decoded as $row) {
                if (
                    !is_array($row)
                    || empty($row['member_id'])
                ) {
                    continue;
                }

                $normalized[(string) (int) $row['member_id']] = $row;
            }

            return $normalized;
        }

        return $decoded;
    }


    /*
|--------------------------------------------------------------------------
| Build Credit Committee approval state
|--------------------------------------------------------------------------
*/

    private function buildCreditCommitteeApprovalState(
        $decisionJson,
        $committeeMembers,
        int $requiredApprovals,
        string $category,
        int $loggedInMemberId = 0
    ): array {

        $savedState = [];

        if (
            $decisionJson !== null
            && trim((string) $decisionJson) !== ''
        ) {
            $decoded = json_decode(
                (string) $decisionJson,
                true
            );

            if (is_array($decoded)) {
                $savedState = $decoded;
            }
        }

        /*
|--------------------------------------------------------------------------
| An interacted application keeps its original committee requirement
|--------------------------------------------------------------------------
*/

        if (
            array_key_exists(
                'required_approvals',
                $savedState
            )
            && is_numeric(
                $savedState['required_approvals']
            )
        ) {
            $requiredApprovals = max(
                0,
                (int) $savedState['required_approvals']
            );
        }

        if (
            isset($savedState['category'])
            && trim(
                (string) $savedState['category']
            ) !== ''
        ) {
            $category = trim(
                (string) $savedState['category']
            );
        }
        $decisions =
            $this->decodeCreditCommitteeDecisions(
                $decisionJson
            );

        $rows = [];

        $yesCount = 0;
        $noCount = 0;

        foreach ($committeeMembers as $member) {
            $memberId =
                (int) $member->member_id;

            $saved =
                $decisions[(string) $memberId]
                ?? null;

            $decision = null;
            $decidedAt = null;

            if (is_array($saved)) {
                $rawDecision = strtoupper(
                    trim(
                        (string) (
                            $saved['decision']
                            ?? ''
                        )
                    )
                );

                if (
                    in_array(
                        $rawDecision,
                        ['Y', 'N'],
                        true
                    )
                ) {
                    $decision = $rawDecision;
                }

                $decidedAt =
                    $saved['decided_at']
                    ?? null;
            } elseif (is_string($saved)) {
                $rawDecision = strtoupper(
                    trim($saved)
                );

                if (
                    in_array(
                        $rawDecision,
                        ['Y', 'N'],
                        true
                    )
                ) {
                    $decision = $rawDecision;
                }
            }

            if ($decision === 'Y') {
                $yesCount++;
            }

            if ($decision === 'N') {
                $noCount++;
            }

            $rows[] = [
                'member_id' =>
                $memberId,

                'member_name' =>
                $member->member_name,

                'member_sacco_id' =>
                $member->member_sacco_id,

                'classification_id' =>
                $member->classification_id,

                'classification_name' =>
                $member->classification_name,

                'classification_code' =>
                $member->classification_code,

                'classification_sort_order' =>
                $member->classification_sort_order,

                'decision' =>
                $decision,

                'decided_at' =>
                $decidedAt,

                /*
             * Only the actual logged-in committee member
             * will get editable Y/N controls in the Blade.
             */
                'can_vote' =>
                $requiredApprovals > 0
                    && $loggedInMemberId > 0
                    && $loggedInMemberId === $memberId,
            ];
        }

        $memberCount =
            count($rows);

        $pendingCount = max(
            0,
            $memberCount
                - $yesCount
                - $noCount
        );

        /*
    |--------------------------------------------------------------------------
    | Approval condition
    |--------------------------------------------------------------------------
    |
    | required = 0:
    | committee approval is bypassed completely.
    |
    | required > 0:
    | enough Y votes AND no N votes.
    |--------------------------------------------------------------------------
    */

        if ($requiredApprovals === 0) {
            $canFinalApprove = true;
            $status = 'NOT_REQUIRED';

            $blockingMessage = null;
        } elseif ($category === '') {
            $canFinalApprove = false;
            $status = 'CONFIGURATION_ERROR';

            $blockingMessage =
                'Credit Committee approval is required, '
                . 'but CREDIT_COMMITTEE is not configured.';
        } elseif ($memberCount === 0) {
            $canFinalApprove = false;
            $status = 'CONFIGURATION_ERROR';

            $blockingMessage =
                'Credit Committee approval is required, '
                . 'but no active Credit Committee members '
                . 'are configured.';
        } elseif ($requiredApprovals > $memberCount) {
            $canFinalApprove = false;
            $status = 'CONFIGURATION_ERROR';

            $blockingMessage =
                'Credit Committee requires '
                . $requiredApprovals
                . ' approvals, but only '
                . $memberCount
                . ' active committee members are available.';
        } elseif ($noCount > 0) {
            $canFinalApprove = false;
            $status = 'DECLINED';

            $blockingMessage =
                'Final loan approval is blocked because '
                . 'one or more Credit Committee members '
                . 'have selected No.';
        } elseif ($yesCount < $requiredApprovals) {
            $canFinalApprove = false;
            $status = 'PENDING';

            $blockingMessage =
                'Credit Committee approval is incomplete. '
                . $yesCount
                . ' of '
                . $requiredApprovals
                . ' required approvals have been received.';
        } else {
            $canFinalApprove = true;
            $status = 'APPROVED';

            $blockingMessage = null;
        }

        return [
            'category' =>
            $category,

            'required' =>
            $requiredApprovals > 0,

            'required_approvals' =>
            $requiredApprovals,

            'member_count' =>
            $memberCount,

            'yes_count' =>
            $yesCount,

            'no_count' =>
            $noCount,

            'pending_count' =>
            $pendingCount,

            'can_final_approve' =>
            $canFinalApprove,

            'status' =>
            $status,

            'blocking_message' =>
            $blockingMessage,

            'members' =>
            $rows,
        ];
    }

    /*
|--------------------------------------------------------------------------
| Save Credit Committee decision
|--------------------------------------------------------------------------
|
| This is NOT user-right based.
|
| The authenticated user must:
| - be position 2
| - be active
| - not be deleted
| - currently belong to the configured Credit Committee category
|
| The client never supplies member_id.
|--------------------------------------------------------------------------
*/

    public function saveCreditCommitteeDecision(
        Request $request,
        $id
    ) {
        $validator =
            \Illuminate\Support\Facades\Validator::make(
                $request->all(),
                [
                    'decision' => [
                        'required',
                        'string',
                        'in:Y,N',
                    ],
                ]
            );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' =>
                'Invalid Credit Committee decision.',
                'errors' =>
                $validator->errors(),
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Resolve committee configuration
    |--------------------------------------------------------------------------
    */

        $config =
            $this->getCreditCommitteeConfig();

        /*
     * When required approvals = 0 there is no committee
     * intervention for this workflow.
     */
        if (
            $config['required_approvals'] === 0
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Credit Committee approval is not required '
                    . 'for this SACCO configuration.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Authenticate using CATEGORY membership
    |--------------------------------------------------------------------------
    */

        $committeeMember =
            $this->getAuthenticatedCreditCommitteeMember();

        if (!$committeeMember) {
            return response()->json([
                'success' => false,
                'message' =>
                'You are not an active Credit Committee member.',
            ], 403);
        }

        $decision = strtoupper(
            trim(
                (string) $request->input(
                    'decision'
                )
            )
        );

        DB::beginTransaction();

        try {
            /*
        |--------------------------------------------------------------------------
        | Lock the application
        |--------------------------------------------------------------------------
        |
        | Important because two committee members could vote at nearly
        | the same time. Without the row lock, one JSON update could
        | overwrite the other person's decision.
        |--------------------------------------------------------------------------
        */

            $loan = DB::table(
                'sacco_loan_batch_trans_members'
            )
                ->where(
                    'batch_trans_id',
                    $id
                )
                ->where(
                    'batch_trans_updated',
                    'N'
                )
                ->whereRaw(
                    "COALESCE(batch_trans_deleted, 'N') <> 'Y'"
                )
                ->lockForUpdate()
                ->first();

            if (!$loan) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' =>
                    'Loan application not found or '
                        . 'it has already been processed.',
                ], 404);
            }

            /*
|--------------------------------------------------------------------------
| Guarantors must complete before Credit Committee voting
|--------------------------------------------------------------------------
*/

            $guaranteePercent = (float) (
                DB::table('sacco_loan_types')
                ->where(
                    'loan_type_id',
                    $loan->batch_trans_loan_type
                )
                ->value('loan_type_guaranteable_percent')
                ?? 0
            );

            if ($guaranteePercent > 0) {

                $activeGuarantors = DB::table(
                    'sacco_loan_batch_guarantors_members'
                )
                    ->where(
                        'guarantors_loan_batch_trans_id',
                        $loan->batch_trans_id
                    )
                    ->whereRaw(
                        "COALESCE(guarantors_deleted, 'N') <> 'Y'"
                    );

                $hasGuarantors =
                    (clone $activeGuarantors)->exists();

                $hasUnapprovedGuarantor =
                    (clone $activeGuarantors)
                    ->where(function ($query) {
                        $query
                            ->whereNull('guarantors_approved')
                            ->orWhere(
                                'guarantors_approved',
                                '<>',
                                'Y'
                            );
                    })
                    ->exists();

                if (
                    !$hasGuarantors
                    || $hasUnapprovedGuarantor
                ) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' =>
                        'All required guarantors must approve '
                            . 'before Credit Committee review.',
                    ], 422);
                }
            }


            /*
        |--------------------------------------------------------------------------
        | Read existing decisions
        |--------------------------------------------------------------------------
        */

            $existingJson =
                $loan->batch_trans_credit_committee_decisions
                ?? null;

            $existingState = [];

            if (
                $existingJson !== null
                && trim((string) $existingJson) !== ''
            ) {
                $decoded = json_decode(
                    (string) $existingJson,
                    true
                );

                if (is_array($decoded)) {
                    $existingState = $decoded;
                }
            }

            /*
|--------------------------------------------------------------------------
| Snapshot committee configuration on first committee action
|--------------------------------------------------------------------------
*/

            if (
                isset($existingState['decisions'])
                && is_array($existingState['decisions'])
            ) {
                /*
     * Already using the current wrapped structure.
     */
                $committeeState = $existingState;
            } else {
                /*
     * New application or older flat JSON.
     */
                $legacyDecisions =
                    $this->decodeCreditCommitteeDecisions(
                        $existingJson
                    );

                $committeeState = [
                    'category' =>
                    $config['category'],

                    'required_approvals' =>
                    $config['required_approvals'],

                    'decisions' =>
                    $legacyDecisions,
                ];
            }

            $memberKey = (string) (
                (int) $committeeMember->member_id
            );

            /*
        |--------------------------------------------------------------------------
        | Update ONLY the authenticated member's decision
        |--------------------------------------------------------------------------
        */

            $committeeState['decisions'][$memberKey] = [
                'decision' =>
                $decision,

                /*
             * Snapshot the committee role used when voting.
             */
                'classification_id' =>
                (int) $committeeMember
                    ->classification_id,

                'classification_code' =>
                $committeeMember
                    ->classification_code,

                'classification_name' =>
                $committeeMember
                    ->classification_name,

                'decided_at' =>
                now()->format(
                    'Y-m-d H:i:s'
                ),

                'ip' =>
                $request->ip(),
            ];

            $encodedDecisions = json_encode(
                $committeeState,

                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            );

            DB::table(
                'sacco_loan_batch_trans_members'
            )
                ->where(
                    'batch_trans_id',
                    $id
                )
                ->where(
                    'batch_trans_updated',
                    'N'
                )
                ->whereRaw(
                    "COALESCE(batch_trans_deleted, 'N') <> 'Y'"
                )
                ->update([
                    'batch_trans_credit_committee_decisions' =>
                    $encodedDecisions,
                ]);

            /*
        |--------------------------------------------------------------------------
        | Recalculate from authoritative database membership
        |--------------------------------------------------------------------------
        */

            $committeeMembers =
                $this->getCreditCommitteeMembers(
                    $config['category']
                );

            $loggedInMemberId = (int) (
                Auth::user()->member_id
            );

            $state =
                $this->buildCreditCommitteeApprovalState(
                    $encodedDecisions,
                    $committeeMembers,
                    $config['required_approvals'],
                    $config['category'],
                    $loggedInMemberId
                );

            DB::commit();

            return response()->json([
                'success' => true,

                'message' =>
                $decision === 'Y'
                    ? 'Credit Committee approval recorded successfully.'
                    : 'Credit Committee decline recorded successfully.',

                'batch_trans_id' =>
                (int) $id,

                'member_id' =>
                (int) $committeeMember->member_id,

                'decision' =>
                $decision,

                /*
             * Blade/JavaScript will use this response
             * to update badges and the final Approve button.
             */
                'credit_committee' =>
                $state,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error(
                'Failed to save Credit Committee loan decision',
                [
                    'batch_trans_id' =>
                    (int) $id,

                    'member_id' =>
                    (int) (
                        Auth::user()->member_id
                        ?? 0
                    ),

                    'message' =>
                    $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                'Failed to save the Credit Committee decision.',
            ], 500);
        }
    }

    private function validateGuarantorCapacityForFinalApproval($loan): array
    {
        $batchTransId = (int) $loan->batch_trans_id;
        $borrowerMemberId = (int) $loan->batch_trans_member_id;

        /*
    |--------------------------------------------------------------------------
    | Read current guarantor factors
    |--------------------------------------------------------------------------
    */
        $maxGuarantorFactor = (float) (
            DB::table('sacco_defaults')
            ->where(
                'default_name',
                'max_guarantor_factor'
            )
            ->value('default_value')
            ?? 1
        );

        $maxGuarantorFactorSelf = (float) (
            DB::table('sacco_defaults')
            ->where(
                'default_name',
                'max_guarantor_factor_self'
            )
            ->value('default_value')
            ?? 1
        );

        /*
    |--------------------------------------------------------------------------
    | Fail safely on invalid configuration
    |--------------------------------------------------------------------------
    */
        if ($maxGuarantorFactor < 0) {
            return [
                'success' => false,
                'message' =>
                'Invalid max_guarantor_factor configuration.',
            ];
        }

        if ($maxGuarantorFactorSelf < 0) {
            return [
                'success' => false,
                'message' =>
                'Invalid max_guarantor_factor_self configuration.',
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | Load active guarantors on this application
    |--------------------------------------------------------------------------
    */
        $guarantors = DB::table(
            'sacco_loan_batch_guarantors_members as g'
        )
            ->where(
                'g.guarantors_loan_batch_trans_id',
                $batchTransId
            )
            ->whereRaw(
                "COALESCE(g.guarantors_deleted, 'N') <> 'Y'"
            )
            ->get();

        if ($guarantors->isEmpty()) {
            return [
                'success' => false,
                'message' =>
                'This loan requires guarantors, but no active '
                    . 'guarantors were found.',
            ];
        }

        foreach ($guarantors as $guarantorRow) {

            /*
        |--------------------------------------------------------------------------
        | Every guarantor must have approved
        |--------------------------------------------------------------------------
        */
            if (
                strtoupper(
                    trim(
                        (string) (
                            $guarantorRow->guarantors_approved
                            ?? ''
                        )
                    )
                ) !== 'Y'
            ) {
                return [
                    'success' => false,
                    'message' =>
                    'All guarantors must approve before '
                        . 'the loan can be finally approved.',
                ];
            }

            $guarantorId =
                (int) $guarantorRow->guarantors_guarantor_id;

            $guaranteedAmount =
                round(
                    (float) (
                        $guarantorRow
                        ->guarantors_amount_guaranteed
                        ?? 0
                    ),
                    2
                );

            /*
        |--------------------------------------------------------------------------
        | Lock member capacity while final approval is being processed
        |--------------------------------------------------------------------------
        */
            $member = DB::table('sacco_members')
                ->where(
                    'member_id',
                    $guarantorId
                )
                ->where(
                    'member_active',
                    'Y'
                )
                ->whereRaw(
                    "COALESCE(member_deleted, 'N') <> 'Y'"
                )
                ->lockForUpdate()
                ->first();

            if (!$member) {
                return [
                    'success' => false,
                    'message' =>
                    'A guarantor is no longer an active member.',
                ];
            }

            /*
        |--------------------------------------------------------------------------
        | Guaranteeing another member
        |--------------------------------------------------------------------------
        */
            if ($guarantorId !== $borrowerMemberId) {

                $pendingOtherGuaranteeAmount =
                    DB::table(
                        'sacco_loan_batch_guarantors_members as g'
                    )
                    ->join(
                        'sacco_loan_batch_trans_members as t',
                        'g.guarantors_loan_batch_trans_id',
                        '=',
                        't.batch_trans_id'
                    )
                    ->where(
                        'g.guarantors_guarantor_id',
                        $guarantorId
                    )
                    ->where(
                        't.batch_trans_member_id',
                        '<>',
                        $guarantorId
                    )
                    ->whereRaw(
                        "COALESCE(g.guarantors_deleted, 'N') <> 'Y'"
                    )
                    ->whereRaw(
                        "COALESCE(t.batch_trans_deleted, 'N') <> 'Y'"
                    )
                    ->whereRaw(
                        "COALESCE(t.batch_trans_updated, 'N') = 'N'"
                    )
                    ->where(
                        't.batch_trans_id',
                        '<>',
                        $batchTransId
                    )
                    ->sum(
                        'g.guarantors_amount_guaranteed'
                    );

                $availableCapacity = (
                    (
                        (float) $member->member_total_share
                        * $maxGuarantorFactor
                    )
                    - (float) $member->member_tied_shares
                    - (float) $pendingOtherGuaranteeAmount
                );

                if ($availableCapacity < $guaranteedAmount) {
                    return [
                        'success' => false,
                        'message' =>
                        'Guarantor '
                            . $member->member_name
                            . ' no longer has sufficient capacity '
                            . 'to guarantee this loan. '
                            . 'Current available capacity is KES '
                            . number_format(
                                max(0, $availableCapacity),
                                2
                            )
                            . ', while this loan requires KES '
                            . number_format(
                                $guaranteedAmount,
                                2
                            )
                            . ' from this guarantor.',
                    ];
                }
            }

            /*
        |--------------------------------------------------------------------------
        | Self-guaranteeing
        |--------------------------------------------------------------------------
        */ else {

                $pendingSelfGuaranteeAmount =
                    DB::table(
                        'sacco_loan_batch_guarantors_members as g'
                    )
                    ->join(
                        'sacco_loan_batch_trans_members as t',
                        'g.guarantors_loan_batch_trans_id',
                        '=',
                        't.batch_trans_id'
                    )
                    ->where(
                        'g.guarantors_guarantor_id',
                        $guarantorId
                    )
                    ->where(
                        't.batch_trans_member_id',
                        $guarantorId
                    )
                    ->whereRaw(
                        "COALESCE(g.guarantors_deleted, 'N') <> 'Y'"
                    )
                    ->whereRaw(
                        "COALESCE(t.batch_trans_deleted, 'N') <> 'Y'"
                    )
                    ->whereRaw(
                        "COALESCE(t.batch_trans_updated, 'N') = 'N'"
                    )
                    ->where(
                        't.batch_trans_id',
                        '<>',
                        $batchTransId
                    )
                    ->sum(
                        'g.guarantors_amount_guaranteed'
                    );

                $availableCapacity = (
                    (
                        (float) $member->member_total_share
                        * $maxGuarantorFactorSelf
                    )
                    - (float) $member->member_tied_shares_self
                    - (float) $pendingSelfGuaranteeAmount
                );

                if ($availableCapacity < $guaranteedAmount) {
                    return [
                        'success' => false,
                        'message' =>
                        'Member '
                            . $member->member_name
                            . ' no longer has sufficient '
                            . 'self-guarantee capacity. '
                            . 'Current available self-guarantee '
                            . 'capacity is KES '
                            . number_format(
                                max(0, $availableCapacity),
                                2
                            )
                            . ', while this loan requires KES '
                            . number_format(
                                $guaranteedAmount,
                                2
                            )
                            . ' from the member.',
                    ];
                }
            }
        }

        return [
            'success' => true,
            'message' =>
            'Guarantor capacity confirmed.',
        ];
    }
}
