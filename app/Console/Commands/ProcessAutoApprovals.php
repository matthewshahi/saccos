<?php

namespace App\Console\Commands;

use App\Services\MemberLoanLimitService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ProcessAutoApprovals extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'loans:process-auto-approvals
                        {--limit=10 : Maximum number of eligible applications to approve per run}
                        {--max-age-days=30 : Maximum application age allowed for automatic approval}
                        {--user= : System user ID to record as the approver}
                        {--dry-run : Validate applications without approving them}';

    /**
     * The console command description.
     */
    protected $description = 'Validate and automatically approve eligible self-service loan applications.';

    /**
     * Execute the console command.
     */
    public function handle(
        MemberLoanLimitService $memberLoanLimitService
    ): int {
        if (
            !Schema::hasColumn(
                'sacco_loan_types',
                'loan_type_auto_approval'
            )
        ) {
            $this->error(
                'The sacco_loan_types.loan_type_auto_approval column does not exist.'
            );

            return self::FAILURE;
        }

        if (
            !Schema::hasColumn(
                'sacco_members',
                'member_mobile_banking_active'
            )
        ) {
            $this->error(
                'The sacco_members.member_mobile_banking_active column does not exist. '
                    . 'Run the mobile banking eligibility migration first.'
            );

            return self::FAILURE;
        }


        $limit = max(
            1,
            min(
                100,
                (int) $this->option('limit')
            )
        );

        $maxAgeDays = max(
            1,
            min(
                365,
                (int) $this->option('max-age-days')
            )
        );

        $autoApprovalCutoff = now()->subDays($maxAgeDays);

        $dryRun = (bool) $this->option('dry-run');

        $systemUserId = $this->resolveSystemUserId();

        if ($systemUserId <= 0) {
            $this->error(
                'Auto-approval system user is not configured. '
                    . 'Add auto_approval_system_user_id to sacco_defaults '
                    . 'or run the command with --user=<id>.'
            );

            return self::FAILURE;
        }

        $applicationIds = $this->candidateApplicationIds(
            $limit,
            $autoApprovalCutoff
        );

        if ($applicationIds->isEmpty()) {
            $this->info(
                'No pending auto-approval loan applications found.'
            );

            return self::SUCCESS;
        }

        $inspected = 0;
        $approved = 0;
        $eligible = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($applicationIds as $applicationId) {
            if (!$dryRun && $approved >= $limit) {
                break;
            }

            if ($dryRun && $eligible >= $limit) {
                break;
            }

            $inspected++;

            try {
                $result = $this->processApplication(
                    (int) $applicationId,
                    $systemUserId,
                    $memberLoanLimitService,
                    $autoApprovalCutoff,
                    $dryRun
                );

                if ($result['status'] === 'approved') {
                    $approved++;

                    $this->info(
                        'APPROVED: Application '
                            . $applicationId
                            . ' created loan '
                            . $result['loan_id']
                            . '.'
                    );

                    continue;
                }

                if ($result['status'] === 'eligible') {
                    $eligible++;

                    $this->line(
                        'ELIGIBLE: Application '
                            . $applicationId
                            . ' passed all auto-approval checks.'
                    );

                    continue;
                }

                $skipped++;

                $this->warn(
                    'SKIPPED: Application '
                        . $applicationId
                        . ' - '
                        . $result['message']
                );
            } catch (RuntimeException $e) {
                $skipped++;

                Log::warning(
                    'Automatic loan approval skipped',
                    [
                        'batch_trans_id' => (int) $applicationId,
                        'message' => $e->getMessage(),
                    ]
                );

                $this->warn(
                    'SKIPPED: Application '
                        . $applicationId
                        . ' - '
                        . $e->getMessage()
                );
            } catch (Throwable $e) {
                $failed++;

                Log::error(
                    'Automatic loan approval failed',
                    [
                        'batch_trans_id' => (int) $applicationId,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );

                $this->error(
                    'FAILED: Application '
                        . $applicationId
                        . ' - '
                        . $e->getMessage()
                );
            }
        }

        $this->newLine();

        $this->info(
            'Auto-approval completed. '
                . 'Inspected: '
                . $inspected
                . ', Approved: '
                . $approved
                . ', Eligible in dry-run: '
                . $eligible
                . ', Skipped: '
                . $skipped
                . ', Failed: '
                . $failed
                . '.'
        );

        return self::SUCCESS;
    }

    /**
     * Resolve the system user whose ID will be used for automatic approvals.
     */
    private function resolveSystemUserId(): int
    {
        $optionUserId = (int) $this->option('user');

        if ($optionUserId > 0) {
            return $optionUserId;
        }

        return (int) (
            DB::table('sacco_defaults')
            ->where(
                'default_name',
                'auto_approval_system_user_id'
            )
            ->value('default_value') ?? 0
        );
    }

    /**
     * Find pending applications attached to auto-approval products.
     */
    private function candidateApplicationIds(
        int $approvalLimit,
        $autoApprovalCutoff
    ) {
        /*
    |--------------------------------------------------------------------------
    | Candidate scan pool
    |--------------------------------------------------------------------------
    | Scan beyond the approval limit so one invalid application does not
    | permanently prevent later eligible applications from being considered.
    |
    | Only active, non-deleted members explicitly enabled for mobile banking
    | are considered for automatic approval.
    |
    | Top-up applications are deliberately excluded and remain pending for
    | manual approval.
    |--------------------------------------------------------------------------
    */

        $scanLimit = max(
            100,
            min(
                1000,
                $approvalLimit * 10
            )
        );

        $query = DB::table(
            'sacco_loan_batch_trans_members as trans'
        )
            ->join(
                'sacco_loan_types as loan_types',
                'trans.batch_trans_loan_type',
                '=',
                'loan_types.loan_type_id'
            )
            ->join(
                'sacco_members as members',
                'trans.batch_trans_member_id',
                '=',
                'members.member_id'
            )

            /*
        |--------------------------------------------------------------------------
        | Loan-product eligibility
        |--------------------------------------------------------------------------
        */

            ->where(
                'loan_types.loan_type_auto_approval',
                1
            )
            ->whereRaw(
                "COALESCE(loan_types.loan_type_deleted, 'N') <> 'Y'"
            )

            /*
        |--------------------------------------------------------------------------
        | Member eligibility
        |--------------------------------------------------------------------------
        */

            ->where(
                'members.member_active',
                'Y'
            )
            ->whereRaw(
                "COALESCE(members.member_deleted, 'N') <> 'Y'"
            )
            ->whereRaw(
                "COALESCE(members.member_mobile_banking_active, 'N') = 'Y'"
            )

            /*
        |--------------------------------------------------------------------------
        | Pending application eligibility
        |--------------------------------------------------------------------------
        */

            ->whereRaw(
                "COALESCE(trans.batch_trans_updated, 'N') = 'N'"
            )
            ->whereRaw(
                "COALESCE(trans.batch_trans_deleted, 'N') <> 'Y'"
            )
            ->whereRaw(
                'COALESCE(trans.batch_trans_loan_to_top_up, 0) = 0'
            )
            ->whereNotNull(
                'trans.batch_trans_on'
            )
            ->where(
                'trans.batch_trans_on',
                '>=',
                $autoApprovalCutoff
            );

        /*
    |--------------------------------------------------------------------------
    | Optional loan-product active column
    |--------------------------------------------------------------------------
    */

        if (
            Schema::hasColumn(
                'sacco_loan_types',
                'loan_type_active'
            )
        ) {
            $query->where(
                'loan_types.loan_type_active',
                1
            );
        }

        return $query
            ->orderBy(
                'trans.batch_trans_on',
                'asc'
            )
            ->orderBy(
                'trans.batch_trans_id',
                'asc'
            )
            ->limit(
                $scanLimit
            )
            ->pluck(
                'trans.batch_trans_id'
            );
    }

    /**
     * Validate and approve one pending application.
     */
    private function processApplication(
        int $applicationId,
        int $systemUserId,
        MemberLoanLimitService $memberLoanLimitService,
        $autoApprovalCutoff,
        bool $dryRun = false
    ): array {
        $systemIp = '127.0.0.1';
        $transdate = now();

        return DB::transaction(
            function () use (
                $applicationId,
                $systemUserId,
                $memberLoanLimitService,
                $autoApprovalCutoff,
                $dryRun,
                $systemIp,
                $transdate
            ) {
                /*
                |--------------------------------------------------------------------------
                | Lock the pending application
                |--------------------------------------------------------------------------
                */

                $loanQuery = DB::table(
                    'sacco_loan_batch_trans_members as trans'
                )
                    ->join(
                        'sacco_loan_category as category',
                        'trans.batch_trans_loan_category',
                        '=',
                        'category.loan_category_id'
                    )
                    ->join(
                        'sacco_loan_types as loan_types',
                        'trans.batch_trans_loan_type',
                        '=',
                        'loan_types.loan_type_id'
                    )
                    ->join(
                        'sacco_members as members',
                        'trans.batch_trans_member_id',
                        '=',
                        'members.member_id'
                    )
                    ->where(
                        'trans.batch_trans_id',
                        $applicationId
                    )
                    ->where(
                        'loan_types.loan_type_auto_approval',
                        1
                    )
                    ->where(
                        'trans.batch_trans_on',
                        '>=',
                        $autoApprovalCutoff
                    )
                    ->whereRaw(
                        "COALESCE(trans.batch_trans_updated, 'N') = 'N'"
                    )
                    ->whereRaw(
                        "COALESCE(trans.batch_trans_deleted, 'N') <> 'Y'"
                    )
                    ->whereRaw(
                        "COALESCE(loan_types.loan_type_deleted, 'N') <> 'Y'"
                    )
                    ->whereRaw(
                        "COALESCE(category.loan_category_deleted, 'N') <> 'Y'"
                    )
                    ->whereRaw(
                        "COALESCE(members.member_deleted, 'N') <> 'Y'"
                    )
                    ->whereRaw(
                        'COALESCE(trans.batch_trans_loan_to_top_up, 0) = 0'
                    )
                    ->select(
                        'trans.*',

                        'loan_types.loan_type_id as loan_type_id',
                        'loan_types.loan_type_name',
                        'loan_types.loan_type_max_amount',
                        'loan_types.loan_type_duration',
                        'loan_types.loan_type_qualification_period',
                        'loan_types.loan_type_share_factor',
                        'loan_types.loan_type_instant_qualification',
                        'loan_types.loan_type_auto_approval',
                        'loan_types.loan_type_guaranteable_percent',
                        'loan_types.loan_type_commission_effect',
                        'loan_types.loan_type_insurance_effect',
                        'loan_types.loan_type_acount',
                        'loan_types.loan_type_active',
                        'loan_types.loan_type_deleted as loan_type_deleted',

                        'category.loan_category_name',
                        'category.loan_category_deleted',

                        'members.member_id',
                        'members.member_name',
                        'members.member_sacco_id',
                        'members.member_active',
                        'members.member_mobile_banking_active',
                        'members.member_deleted',
                        'members.member_date_joined',
                        'members.member_total_share',
                        'members.member_total_loan'
                    );

                if (
                    Schema::hasColumn(
                        'sacco_loan_category',
                        'loan_category_active'
                    )
                ) {
                    $loanQuery->where(
                        'category.loan_category_active',
                        1
                    );
                }

                $loan = $loanQuery
                    ->lockForUpdate()
                    ->first();

                if (!$loan) {
                    return [
                        'status' => 'skipped',
                        'message' =>
                        'Application is no longer pending, is older than the '
                            . 'automatic-approval window, is a top-up, the product '
                            . 'is not auto-approved, or the record is inactive.',
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent duplicate approved loans
                |--------------------------------------------------------------------------
                */

                if (
                    Schema::hasColumn(
                        'sacco_loans',
                        'loan_batch_trans_id'
                    )
                ) {
                    $existingLoanId = DB::table('sacco_loans')
                        ->where(
                            'loan_batch_trans_id',
                            $applicationId
                        )
                        ->value('loan_id');

                    if ($existingLoanId) {
                        throw new RuntimeException(
                            'Application has already created loan ID '
                                . $existingLoanId
                                . '.'
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Active accounting period
                |--------------------------------------------------------------------------
                */

                $currentPeriod = DB::table('sacco_period')
                    ->where(
                        'period_active',
                        'Y'
                    )
                    ->whereRaw(
                        "COALESCE(period_deleted, 'N') <> 'Y'"
                    )
                    ->value('period_name');

                if (!$currentPeriod) {
                    throw new RuntimeException(
                        'Active accounting period not found.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Member and product eligibility
                |--------------------------------------------------------------------------
                */

                $this->validateApplicationEligibility(
                    $loan,
                    $memberLoanLimitService
                );

                /*
                |--------------------------------------------------------------------------
                | Guarantor approval and current capacity
                |--------------------------------------------------------------------------
                */

                $this->validateApprovedGuarantors(
                    $loan
                );

                /*
                |--------------------------------------------------------------------------
                | Default ledger accounts
                |--------------------------------------------------------------------------
                */

                $defaultBankAccount = $this->getDefaultAccount(
                    'default_bank_account'
                );

                $defaultInsuranceAccount = $this->getDefaultAccount(
                    'default_insurance_account'
                );

                $defaultCommissionAccount = $this->getDefaultAccount(
                    'default_loan_commission_account'
                );

                if (
                    !$defaultBankAccount
                    || !$defaultInsuranceAccount
                    || !$defaultCommissionAccount
                ) {
                    throw new RuntimeException(
                        'Missing default bank, insurance, '
                            . 'or commission account.'
                    );
                }

                $this->assertSubAccountExists(
                    $loan->loan_type_acount,
                    'loan receivable'
                );

                $this->assertSubAccountExists(
                    $defaultBankAccount,
                    'default bank'
                );

                $this->assertSubAccountExists(
                    $defaultInsuranceAccount,
                    'default insurance'
                );

                $this->assertSubAccountExists(
                    $defaultCommissionAccount,
                    'default commission'
                );

                /*
                |--------------------------------------------------------------------------
                | Charges and financial amounts
                |--------------------------------------------------------------------------
                */

                $chargeSummary = $this->getSelfServiceChargeSummary(
                    $loan->batch_trans_id
                );

                $chargeAccountErrors =
                    $this->validateSelfServiceChargeAccounts(
                        $chargeSummary['rows']
                    );

                if (!empty($chargeAccountErrors)) {
                    throw new RuntimeException(
                        implode(
                            ' ',
                            $chargeAccountErrors
                        )
                    );
                }

                $requestedAmount = round(
                    (float) $loan->batch_trans_loan_amount,
                    2
                );

                $insurance = round(
                    (float) (
                        $loan->batch_trans_insurance ?? 0
                    ),
                    2
                );

                $commission = round(
                    (float) (
                        $loan->batch_trans_commission ?? 0
                    ),
                    2
                );

                $totalAddToLoan = round(
                    (float) (
                        $chargeSummary['add_to_loan'] ?? 0
                    ),
                    2
                );

                $totalDeductFromDisbursement = round(
                    (float) (
                        $chargeSummary['deduct_from_disbursement'] ?? 0
                    ),
                    2
                );

                $commissionEffect = strtoupper(
                    trim(
                        (string) (
                            $loan->loan_type_commission_effect
                            ?? 'ADD_TO_LOAN'
                        )
                    )
                );

                if (
                    !in_array(
                        $commissionEffect,
                        [
                            'ADD_TO_LOAN',
                            'DEDUCT_FROM_DISBURSEMENT',
                        ],
                        true
                    )
                ) {
                    $commissionEffect = 'ADD_TO_LOAN';
                }

                $insuranceEffect = strtoupper(
                    trim(
                        (string) (
                            $loan->loan_type_insurance_effect
                            ?? 'ADD_TO_LOAN'
                        )
                    )
                );

                if (
                    !in_array(
                        $insuranceEffect,
                        [
                            'ADD_TO_LOAN',
                            'DEDUCT_FROM_DISBURSEMENT',
                        ],
                        true
                    )
                ) {
                    $insuranceEffect = 'ADD_TO_LOAN';
                }

                $commissionAddedToLoan =
                    $commissionEffect === 'ADD_TO_LOAN'
                    ? $commission
                    : 0.00;

                $commissionDeductedFromDisbursement =
                    $commissionEffect ===
                    'DEDUCT_FROM_DISBURSEMENT'
                    ? $commission
                    : 0.00;

                $insuranceAddedToLoan =
                    $insuranceEffect === 'ADD_TO_LOAN'
                    ? $insurance
                    : 0.00;

                $insuranceDeductedFromDisbursement =
                    $insuranceEffect ===
                    'DEDUCT_FROM_DISBURSEMENT'
                    ? $insurance
                    : 0.00;

                $totalLoan = round(
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

                if ($totalLoan <= 0) {
                    throw new RuntimeException(
                        'Computed total loan amount is invalid.'
                    );
                }

                if ($netDisbursement <= 0) {
                    throw new RuntimeException(
                        'Net disbursement is zero or negative. '
                            . 'Requested: '
                            . number_format(
                                $requestedAmount,
                                2
                            )
                            . ', Commission deducted: '
                            . number_format(
                                $commissionDeductedFromDisbursement,
                                2
                            )
                            . ', Insurance deducted: '
                            . number_format(
                                $insuranceDeductedFromDisbursement,
                                2
                            )
                            . ', Other deductions: '
                            . number_format(
                                $totalDeductFromDisbursement,
                                2
                            )
                            . ', Net: '
                            . number_format(
                                $netDisbursement,
                                2
                            )
                            . '.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Dry-run stops before financial records are created
                |--------------------------------------------------------------------------
                */

                if ($dryRun) {
                    return [
                        'status' => 'eligible',
                        'loan_id' => null,
                        'message' => 'Application passed all validation checks.',
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Create actual approved loan
                |--------------------------------------------------------------------------
                */

                $batchNumber =
                    'Self Applied Loan-'
                    . $loan->batch_trans_id
                    . '-'
                    . $loan->member_name;

                $loanInsert = [
                    'loan_member' =>
                    $loan->batch_trans_member_id,

                    'loan_loan_type' =>
                    $loan->batch_trans_loan_type,

                    'loan_loan_category' =>
                    $loan->batch_trans_loan_category,

                    'loan_amount' => $totalLoan,

                    'loan_insurance' => $insurance,

                    'loan_commision' => $commission,

                    'loan_taken_period' => $currentPeriod,

                    'loan_payment_period' =>
                    $loan->batch_trans_loan_duration,

                    'loan_interest_payable' =>
                    $loan->batch_trans_expected_interest,

                    'loan_monthly_repayment_amount' =>
                    $loan->batch_trans_monthly_payment,

                    'loan_amount_guaranteed' =>
                    $loan->batch_trans_loan_guaranteed,

                    'loan_loan_paid' => 0,

                    'loan_doc_no' =>
                    $loan->batch_trans_doc_no,

                    'loan_description' =>
                    $loan->batch_trans_description,

                    'loan_batch_no' => $batchNumber,

                    'loan_start_deduction_period' =>
                    $currentPeriod,

                    'loan_account_credited' =>
                    $defaultBankAccount,

                    'loan_account_debited' =>
                    $loan->loan_type_acount,

                    'loan_on' => $transdate,

                    'loan_by' => $systemUserId,

                    'loan_ip' => $systemIp,

                    'loan_stoped' => 'N',

                    'loan_taken_start_period' =>
                    $currentPeriod,
                ];

                if (
                    Schema::hasColumn(
                        'sacco_loans',
                        'loan_requested_amount'
                    )
                ) {
                    $loanInsert['loan_requested_amount'] =
                        $requestedAmount;
                }

                if (
                    Schema::hasColumn(
                        'sacco_loans',
                        'loan_other_additions'
                    )
                ) {
                    $loanInsert['loan_other_additions'] =
                        $totalAddToLoan;
                }

                if (
                    Schema::hasColumn(
                        'sacco_loans',
                        'loan_other_deductions'
                    )
                ) {
                    $loanInsert['loan_other_deductions'] =
                        $totalDeductFromDisbursement;
                }

                if (
                    Schema::hasColumn(
                        'sacco_loans',
                        'loan_net_disbursement'
                    )
                ) {
                    $loanInsert['loan_net_disbursement'] =
                        $netDisbursement;
                }

                if (
                    Schema::hasColumn(
                        'sacco_loans',
                        'loan_charges_snapshot'
                    )
                ) {
                    $loanInsert['loan_charges_snapshot'] =
                        $chargeSummary['charges_snapshot'];
                }

                if (
                    Schema::hasColumn(
                        'sacco_loans',
                        'loan_batch_trans_id'
                    )
                ) {
                    $loanInsert['loan_batch_trans_id'] =
                        $loan->batch_trans_id;
                }

                if (
                    Schema::hasColumn(
                        'sacco_loans',
                        'loan_approval_source'
                    )
                ) {
                    $loanInsert['loan_approval_source'] =
                        'SYSTEM_AUTO';
                }

                $loanId = DB::table('sacco_loans')
                    ->insertGetId($loanInsert);

                /*
                |--------------------------------------------------------------------------
                | Transfer approved guarantors
                |--------------------------------------------------------------------------
                */

                $guarantors = DB::table(
                    'sacco_loan_batch_guarantors_members'
                )
                    ->where(
                        'guarantors_loan_batch_trans_id',
                        $loan->batch_trans_id
                    )
                    ->whereRaw(
                        "COALESCE(guarantors_deleted, 'N') <> 'Y'"
                    )
                    ->where(
                        'guarantors_approved',
                        'Y'
                    )
                    ->get();

                foreach ($guarantors as $guarantor) {
                    DB::table(
                        'sacco_loan_guarantors'
                    )->insert([
                        'loan_guar_loan_id' => $loanId,

                        'loan_guar_guarantor_id' =>
                        $guarantor
                            ->guarantors_guarantor_id,

                        'loan_guar_amount_guaranteed' =>
                        $guarantor
                            ->guarantors_amount_guaranteed,

                        'loan_guar_description' =>
                        'Guarantor for automatic '
                            . 'self-service loan approval - '
                            . $loan->member_name,

                        'loan_guar_by' => $systemUserId,

                        'loan_guar_on' => $transdate,

                        'loan_guar_ip' => $systemIp,

                        'loan_guar_deleted' => 'N',
                    ]);

                    if (
                        (int) $guarantor
                            ->guarantors_guarantor_id
                        ===
                        (int) $loan
                            ->batch_trans_member_id
                    ) {
                        DB::table('sacco_members')
                            ->where(
                                'member_id',
                                $guarantor
                                    ->guarantors_guarantor_id
                            )
                            ->increment(
                                'member_tied_shares_self',
                                $guarantor
                                    ->guarantors_amount_guaranteed
                            );
                    } else {
                        DB::table('sacco_members')
                            ->where(
                                'member_id',
                                $guarantor
                                    ->guarantors_guarantor_id
                            )
                            ->increment(
                                'member_tied_shares',
                                $guarantor
                                    ->guarantors_amount_guaranteed
                            );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Transfer charges to permanent loan deductions
                |--------------------------------------------------------------------------
                */

                $this->transferSelfServiceChargesToLoan(
                    $loanId,
                    $loan->batch_trans_id,
                    $systemUserId,
                    $systemIp
                );

                /*
                |--------------------------------------------------------------------------
                | Update member total loan
                |--------------------------------------------------------------------------
                */

                DB::table('sacco_members')
                    ->where(
                        'member_id',
                        $loan->batch_trans_member_id
                    )
                    ->increment(
                        'member_total_loan',
                        $totalLoan
                    );

                /*
                |--------------------------------------------------------------------------
                | Mark application as approved and processed
                |--------------------------------------------------------------------------
                */

                $applicationUpdate = [
                    'batch_trans_updated' => 'Y',
                    'batch_trans_ip' => $systemIp,
                ];

                if (
                    Schema::hasColumn(
                        'sacco_loan_batch_trans_members',
                        'batch_trans_approved_by'
                    )
                ) {
                    $applicationUpdate['batch_trans_approved_by'] = $systemUserId;
                }

                if (
                    Schema::hasColumn(
                        'sacco_loan_batch_trans_members',
                        'batch_trans_approved_on'
                    )
                ) {
                    $applicationUpdate['batch_trans_approved_on'] = $transdate;
                }

                if (
                    Schema::hasColumn(
                        'sacco_loan_batch_trans_members',
                        'batch_trans_approval_source'
                    )
                ) {
                    $applicationUpdate['batch_trans_approval_source'] = 'SYSTEM_AUTO';
                }

                if (
                    Schema::hasColumn(
                        'sacco_loan_batch_trans_members',
                        'batch_trans_approved_loan_id'
                    )
                ) {
                    $applicationUpdate['batch_trans_approved_loan_id'] = $loanId;
                }

                $updated = DB::table(
                    'sacco_loan_batch_trans_members'
                )
                    ->where(
                        'batch_trans_id',
                        $applicationId
                    )
                    ->whereRaw(
                        "COALESCE(batch_trans_updated, 'N') = 'N'"
                    )
                    ->whereRaw(
                        "COALESCE(batch_trans_deleted, 'N') <> 'Y'"
                    )
                    ->update($applicationUpdate);

                if ($updated !== 1) {
                    throw new RuntimeException(
                        'The pending application could not '
                            . 'be marked as processed.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Post accounting ledger entries
                |--------------------------------------------------------------------------
                */

                $this->postSelfServiceLoanLedgerEntries(
                    $loan,
                    $chargeSummary,
                    $defaultBankAccount,
                    $defaultInsuranceAccount,
                    $defaultCommissionAccount,
                    $currentPeriod,
                    $systemUserId,
                    $systemIp,
                    $transdate
                );

                return [
                    'status' => 'approved',
                    'loan_id' => (int) $loanId,
                    'message' => 'Loan automatically approved.',
                ];
            },
            3
        );
    }

    /**
     * Revalidate the member, amount, duration, membership period,
     * individual limit, product limit, share factor and top-up rules.
     */
    private function validateApplicationEligibility(
        object $loan,
        MemberLoanLimitService $memberLoanLimitService
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Top-up loans require manual approval
        |--------------------------------------------------------------------------
        | The current manual approval flow does not automatically settle and close
        | the topped-up loan. Auto approval must therefore fail closed for top-ups.
        |--------------------------------------------------------------------------
        */
        $topUpLoanId = (int) (
            $loan->batch_trans_loan_to_top_up ?? 0
        );

        if ($topUpLoanId > 0) {
            throw new RuntimeException(
                'Top-up loan applications require manual approval.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Active member and product
        |--------------------------------------------------------------------------
        */
        if (
            strtoupper(trim((string) ($loan->member_active ?? 'N'))) !== 'Y'
        ) {
            throw new RuntimeException('Member is inactive.');
        }

        if (
            strtoupper(
                trim(
                    (string) (
                        $loan->member_mobile_banking_active
                        ?? 'N'
                    )
                )
            ) !== 'Y'
        ) {
            throw new RuntimeException(
                'Member is not enabled for mobile banking automatic loan approval.'
            );
        }



        if (
            strtoupper(trim((string) ($loan->member_deleted ?? 'N'))) === 'Y'
        ) {
            throw new RuntimeException('Member has been deleted.');
        }

        if (
            isset($loan->loan_type_active)
            && (int) $loan->loan_type_active !== 1
        ) {
            throw new RuntimeException('Loan product is inactive.');
        }

        if (
            strtoupper(trim((string) ($loan->loan_type_deleted ?? 'N'))) === 'Y'
        ) {
            throw new RuntimeException('Loan product has been deleted.');
        }

        /*
        |--------------------------------------------------------------------------
        | Product maximum and individual member limit
        |--------------------------------------------------------------------------
        | This is the same final amount-limit recheck performed by approveLoan().
        |--------------------------------------------------------------------------
        */
        $requestedAmount = round(
            (float) ($loan->batch_trans_loan_amount ?? 0),
            2
        );

        if ($requestedAmount <= 0) {
            throw new RuntimeException('Requested loan amount is invalid.');
        }

        $memberLimitValidation = $memberLoanLimitService
            ->validateRequestedAmount(
                (int) $loan->batch_trans_member_id,
                $loan,
                $requestedAmount
            );

        if (!$memberLimitValidation['is_valid']) {
            throw new RuntimeException(
                $memberLimitValidation['message']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Repayment duration
        |--------------------------------------------------------------------------
        */
        $loanDuration = (int) (
            $loan->batch_trans_loan_duration ?? 0
        );

        $maximumDuration = (int) (
            $loan->loan_type_duration ?? 0
        );

        if ($loanDuration <= 0) {
            throw new RuntimeException('Repayment duration is invalid.');
        }

        if (
            $maximumDuration > 0
            && $loanDuration > $maximumDuration
        ) {
            throw new RuntimeException(
                'Repayment duration exceeds the product maximum of '
                    . $maximumDuration
                    . ' months.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Membership qualification period
        |--------------------------------------------------------------------------
        | This mirrors validateMemberEligibility() in the self-service controller.
        |--------------------------------------------------------------------------
        */
        $joinedDateValue = trim(
            (string) ($loan->member_date_joined ?? '')
        );

        if ($joinedDateValue === '') {
            throw new RuntimeException('Member joining date is missing.');
        }

        try {
            $joinedDate = Carbon::parse($joinedDateValue);
        } catch (Throwable $e) {
            throw new RuntimeException('Member joining date is invalid.');
        }

        if ($joinedDate->isFuture()) {
            throw new RuntimeException('Member joining date cannot be in the future.');
        }

        $minimumMonths = max(
            0,
            (int) ($loan->loan_type_qualification_period ?? 0)
        );

        if ($joinedDate->greaterThan(now()->subMonths($minimumMonths))) {
            throw new RuntimeException(
                'Member must be '
                    . $minimumMonths
                    . ' months old in the SACCO to take this loan.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shares/deposits qualification factor
        |--------------------------------------------------------------------------
        | Instant qualification bypasses only this factor. Product limits,
        | membership period, guarantors, charges and accounting checks still apply.
        |--------------------------------------------------------------------------
        */
        $instantQualification =
            (int) ($loan->loan_type_instant_qualification ?? 0) === 1;

        if ($instantQualification) {
            return;
        }

        $shareFactor = $this->resolveLoanShareFactor($loan);

        if ($shareFactor <= 0) {
            return;
        }

        $memberShares = max(
            0,
            (float) ($loan->member_total_share ?? 0)
        );

        $currentOutstanding = (float) (
            DB::table('sacco_loans')
            ->where(
                'loan_member',
                $loan->batch_trans_member_id
            )
            ->selectRaw(
                'COALESCE(SUM('
                    . 'GREATEST('
                    . 'COALESCE(loan_amount, 0) '
                    . '- COALESCE(loan_loan_paid, 0), '
                    . '0'
                    . ')'
                    . '), 0) as outstanding'
            )
            ->value('outstanding') ?? 0
        );

        $availableQualification = round(
            max(
                0,
                ($memberShares * $shareFactor) - $currentOutstanding
            ),
            2
        );

        if ($requestedAmount > $availableQualification + 0.01) {
            throw new RuntimeException(
                'Member does not meet the shares × '
                    . number_format($shareFactor, 2)
                    . ' qualification requirement. Available qualification is KES '
                    . number_format($availableQualification, 2)
                    . ', while the requested amount is KES '
                    . number_format($requestedAmount, 2)
                    . '.'
            );
        }
    }

    /**
     * Resolve the product share factor.
     *
     * The database contains loan_type_share_factor, normally defaulting
     * to 3 for the standard shares-times-three principle.
     */
    private function resolveLoanShareFactor(
        object $loan
    ): float {
        $productFactor =
            $loan->loan_type_share_factor ?? null;

        if (
            $productFactor !== null
            && $productFactor !== ''
            && is_numeric($productFactor)
        ) {
            return max(
                0,
                (float) $productFactor
            );
        }

        $defaultFactor = DB::table(
            'sacco_defaults'
        )
            ->where(
                'default_name',
                'loan_factor_or_shares'
            )
            ->value('default_value');

        if (
            $defaultFactor === null
            || $defaultFactor === ''
            || !is_numeric($defaultFactor)
        ) {
            return 3.00;
        }

        return max(
            0,
            (float) $defaultFactor
        );
    }

    /**
     * Confirm that the required guarantee has been approved and that
     * every approved guarantor still has sufficient current capacity.
     */
    private function validateApprovedGuarantors(
        object $loan
    ): void {
        $guaranteablePercent = max(
            0,
            (float) (
                $loan->loan_type_guaranteable_percent
                ?? 0
            )
        );

        if ($guaranteablePercent <= 0) {
            return;
        }

        $guarantors = DB::table(
            'sacco_loan_batch_guarantors_members'
        )
            ->where(
                'guarantors_loan_batch_trans_id',
                $loan->batch_trans_id
            )
            ->whereRaw(
                "COALESCE(guarantors_deleted, 'N') <> 'Y'"
            )
            ->where(
                'guarantors_approved',
                'Y'
            )
            ->get();

        if ($guarantors->isEmpty()) {
            throw new RuntimeException(
                'This loan requires approved guarantors.'
            );
        }

        $requiredGuarantee = round(
            (
                (float) $loan->batch_trans_loan_amount
                * $guaranteablePercent
            ) / 100,
            2
        );

        $totalGuaranteed = round(
            (float) $guarantors->sum(
                'guarantors_amount_guaranteed'
            ),
            2
        );

        if (
            abs(
                $totalGuaranteed
                    - $requiredGuarantee
            ) > 1
        ) {
            throw new RuntimeException(
                'Loan is not fully guaranteed. '
                    . 'Required: KES '
                    . number_format(
                        $requiredGuarantee,
                        2
                    )
                    . ', approved: KES '
                    . number_format(
                        $totalGuaranteed,
                        2
                    )
                    . '.'
            );
        }

        $maxOtherFactor = (float) (
            DB::table('sacco_defaults')
            ->where(
                'default_name',
                'max_guarantor_factor'
            )
            ->value('default_value') ?? 1
        );

        $maxSelfFactor = (float) (
            DB::table('sacco_defaults')
            ->where(
                'default_name',
                'max_guarantor_factor_self'
            )
            ->value('default_value') ?? 1
        );

        foreach ($guarantors as $guarantorRow) {
            $guarantor = DB::table(
                'sacco_members'
            )
                ->where(
                    'member_id',
                    $guarantorRow
                        ->guarantors_guarantor_id
                )
                ->where(
                    'member_active',
                    'Y'
                )
                ->whereRaw(
                    "COALESCE(member_deleted, 'N') <> 'Y'"
                )
                ->first();

            if (!$guarantor) {
                throw new RuntimeException(
                    'An approved guarantor is inactive '
                        . 'or no longer exists.'
                );
            }

            $guaranteedAmount = round(
                (float) $guarantorRow
                    ->guarantors_amount_guaranteed,
                2
            );

            $isSelfGuarantee =
                (int) $guarantor->member_id
                ===
                (int) $loan->batch_trans_member_id;

            if ($isSelfGuarantee) {
                $pendingExposure = DB::table(
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
                        $guarantor->member_id
                    )
                    ->where(
                        't.batch_trans_member_id',
                        $guarantor->member_id
                    )
                    ->where(
                        't.batch_trans_id',
                        '<>',
                        $loan->batch_trans_id
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
                    ->sum(
                        'g.guarantors_amount_guaranteed'
                    );

                $availableCapacity = (
                    (
                        (float) (
                            $guarantor->member_total_share
                            ?? 0
                        )
                        * $maxSelfFactor
                    )
                    -
                    (float) (
                        $guarantor
                        ->member_tied_shares_self
                        ?? 0
                    )
                    -
                    (float) $pendingExposure
                );
            } else {
                $pendingExposure = DB::table(
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
                        $guarantor->member_id
                    )
                    ->where(
                        't.batch_trans_member_id',
                        '<>',
                        $guarantor->member_id
                    )
                    ->where(
                        't.batch_trans_id',
                        '<>',
                        $loan->batch_trans_id
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
                    ->sum(
                        'g.guarantors_amount_guaranteed'
                    );

                $availableCapacity = (
                    (
                        (float) (
                            $guarantor->member_total_share
                            ?? 0
                        )
                        * $maxOtherFactor
                    )
                    -
                    (float) (
                        $guarantor->member_tied_shares
                        ?? 0
                    )
                    -
                    (float) $pendingExposure
                );
            }

            if (
                $availableCapacity + 0.01
                < $guaranteedAmount
            ) {
                throw new RuntimeException(
                    'Guarantor '
                        . $guarantor->member_name
                        . ' no longer has sufficient guarantee '
                        . 'capacity. Available: KES '
                        . number_format(
                            max(
                                0,
                                $availableCapacity
                            ),
                            2
                        )
                        . ', required: KES '
                        . number_format(
                            $guaranteedAmount,
                            2
                        )
                        . '.'
                );
            }
        }
    }

    /**
     * Read a configured default account.
     */
    private function getDefaultAccount(
        string $accountName
    ) {
        return DB::table('sacco_defaults')
            ->where(
                'default_name',
                $accountName
            )
            ->value('default_value');
    }

    /**
     * Confirm that a configured sub-account exists.
     */
    private function assertSubAccountExists(
        $subAccountId,
        string $label
    ): void {
        if (empty($subAccountId)) {
            throw new RuntimeException(
                ucfirst($label)
                    . ' account is not configured.'
            );
        }

        $exists = DB::table('sacco_sub_account')
            ->where(
                'sub_account_id',
                $subAccountId
            )
            ->whereRaw(
                "COALESCE(sub_account_deleted, 'N') <> 'Y'"
            )
            ->exists();

        if (!$exists) {
            throw new RuntimeException(
                ucfirst($label)
                    . ' account ID '
                    . $subAccountId
                    . ' does not exist or is deleted.'
            );
        }
    }

    /**
     * Retrieve active application charges.
     */
    private function getSelfServiceChargeSummary(
        int $transactionId
    ): array {
        $rows = DB::table(
            'sacco_loan_batch_trans_members_deductions'
        )
            ->where(
                'batch_trans_deduction_batch_trans_id',
                $transactionId
            )
            ->where(
                'batch_trans_deduction_deleted',
                'N'
            )
            ->orderBy(
                'batch_trans_deduction_id'
            )
            ->get();

        $addToLoan = 0.00;
        $deductFromDisbursement = 0.00;
        $snapshotParts = [];

        foreach ($rows as $row) {
            $amount = round(
                (float) (
                    $row
                    ->batch_trans_deduction_amount
                    ?? 0
                ),
                2
            );

            $effect = strtoupper(
                trim(
                    (string) (
                        $row
                        ->batch_trans_deduction_effect
                        ?? ''
                    )
                )
            );

            if ($effect === 'ADD_TO_LOAN') {
                $addToLoan += $amount;
            } elseif (
                $effect ===
                'DEDUCT_FROM_DISBURSEMENT'
            ) {
                $deductFromDisbursement += $amount;
            }

            if (
                !empty($row
                    ->batch_trans_deduction_description)
            ) {
                $snapshotParts[] = trim(
                    (string) $row
                        ->batch_trans_deduction_description
                );
            } else {
                $snapshotParts[] =
                    trim(
                        (string) (
                            $row
                            ->batch_trans_deduction_name
                            ?? 'Charge'
                        )
                    )
                    . ' - '
                    . number_format(
                        $amount,
                        2
                    );
            }
        }

        return [
            'rows' => $rows,
            'add_to_loan' => round(
                $addToLoan,
                2
            ),
            'deduct_from_disbursement' => round(
                $deductFromDisbursement,
                2
            ),
            'charges_snapshot' => implode(
                ' | ',
                $snapshotParts
            ),
        ];
    }

    /**
     * Validate accounts attached to additional charges.
     */
    private function validateSelfServiceChargeAccounts(
        $rows
    ): array {
        $errors = [];

        foreach ($rows as $row) {
            $amount = round(
                (float) (
                    $row
                    ->batch_trans_deduction_amount
                    ?? 0
                ),
                2
            );

            if ($amount <= 0) {
                continue;
            }

            $accountId =
                $row
                ->batch_trans_deduction_account
                ?? null;

            if (empty($accountId)) {
                $errors[] =
                    'Missing ledger account for charge "'
                    . (
                        $row
                        ->batch_trans_deduction_name
                        ?? 'Unknown Charge'
                    )
                    . '".';

                continue;
            }

            $exists = DB::table(
                'sacco_sub_account'
            )
                ->where(
                    'sub_account_id',
                    $accountId
                )
                ->whereRaw(
                    "COALESCE(sub_account_deleted, 'N') <> 'Y'"
                )
                ->exists();

            if (!$exists) {
                $errors[] =
                    'Ledger account '
                    . $accountId
                    . ' for charge "'
                    . (
                        $row
                        ->batch_trans_deduction_name
                        ?? 'Unknown Charge'
                    )
                    . '" does not exist or is deleted.';
            }
        }

        return array_values(
            array_unique($errors)
        );
    }

    /**
     * Move application charges to permanent loan deductions.
     */
    private function transferSelfServiceChargesToLoan(
        int $loanId,
        int $transactionId,
        int $systemUserId,
        string $systemIp
    ): void {
        $rows = DB::table(
            'sacco_loan_batch_trans_members_deductions'
        )
            ->where(
                'batch_trans_deduction_batch_trans_id',
                $transactionId
            )
            ->where(
                'batch_trans_deduction_deleted',
                'N'
            )
            ->get();

        foreach ($rows as $row) {
            DB::table(
                'sacco_loan_deductions'
            )->insert([
                'loan_deduction_loan_id' => $loanId,

                'loan_deduction_batch_id' => null,

                'loan_deduction_batch_trans_id' =>
                $transactionId,

                'loan_deduction_batch_deduction_id' =>
                $row->batch_trans_deduction_id
                    ?? null,

                'loan_deduction_deduction_type_id' =>
                $row
                    ->batch_trans_deduction_deduction_type_id,

                'loan_deduction_name' =>
                $row
                    ->batch_trans_deduction_name,

                'loan_deduction_code' =>
                $row
                    ->batch_trans_deduction_code,

                'loan_deduction_value_type' =>
                $row
                    ->batch_trans_deduction_value_type,

                'loan_deduction_effect' =>
                $row
                    ->batch_trans_deduction_effect,

                'loan_deduction_account' =>
                $row
                    ->batch_trans_deduction_account,

                'loan_deduction_amount' =>
                (float) $row
                    ->batch_trans_deduction_amount,

                'loan_deduction_description' =>
                $row
                    ->batch_trans_deduction_description,

                'loan_deduction_updated' => 'N',

                'loan_deduction_by' =>
                $systemUserId,

                'loan_deduction_ip' =>
                $systemIp,

                'loan_deduction_deleted' => 'N',
            ]);
        }

        DB::table(
            'sacco_loan_batch_trans_members_deductions'
        )
            ->where(
                'batch_trans_deduction_batch_trans_id',
                $transactionId
            )
            ->where(
                'batch_trans_deduction_deleted',
                'N'
            )
            ->update([
                'batch_trans_deduction_updated' => 'Y',
            ]);
    }

    /**
     * Post balanced loan approval ledger entries.
     */
    private function postSelfServiceLoanLedgerEntries(
        object $loan,
        array $chargeSummary,
        $defaultBankAccount,
        $defaultInsuranceAccount,
        $defaultCommissionAccount,
        string $currentPeriod,
        int $systemUserId,
        string $systemIp,
        $transdate
    ): void {
        $requestedAmount = round(
            (float) (
                $loan->batch_trans_loan_amount ?? 0
            ),
            2
        );

        $insurance = round(
            (float) (
                $loan->batch_trans_insurance ?? 0
            ),
            2
        );

        $commission = round(
            (float) (
                $loan->batch_trans_commission ?? 0
            ),
            2
        );

        $totalAddToLoan = round(
            (float) (
                $chargeSummary['add_to_loan'] ?? 0
            ),
            2
        );

        $totalDeductFromDisbursement = round(
            (float) (
                $chargeSummary['deduct_from_disbursement'] ?? 0
            ),
            2
        );

        $commissionEffect = strtoupper(
            trim(
                (string) (
                    $loan->loan_type_commission_effect
                    ?? 'ADD_TO_LOAN'
                )
            )
        );

        if (
            !in_array(
                $commissionEffect,
                [
                    'ADD_TO_LOAN',
                    'DEDUCT_FROM_DISBURSEMENT',
                ],
                true
            )
        ) {
            $commissionEffect = 'ADD_TO_LOAN';
        }

        $insuranceEffect = strtoupper(
            trim(
                (string) (
                    $loan->loan_type_insurance_effect
                    ?? 'ADD_TO_LOAN'
                )
            )
        );

        if (
            !in_array(
                $insuranceEffect,
                [
                    'ADD_TO_LOAN',
                    'DEDUCT_FROM_DISBURSEMENT',
                ],
                true
            )
        ) {
            $insuranceEffect = 'ADD_TO_LOAN';
        }

        $commissionAddedToLoan =
            $commissionEffect === 'ADD_TO_LOAN'
            ? $commission
            : 0.00;

        $commissionDeducted =
            $commissionEffect ===
            'DEDUCT_FROM_DISBURSEMENT'
            ? $commission
            : 0.00;

        $insuranceAddedToLoan =
            $insuranceEffect === 'ADD_TO_LOAN'
            ? $insurance
            : 0.00;

        $insuranceDeducted =
            $insuranceEffect ===
            'DEDUCT_FROM_DISBURSEMENT'
            ? $insurance
            : 0.00;

        $loanDebit = round(
            $requestedAmount
                + $totalAddToLoan
                + $commissionAddedToLoan
                + $insuranceAddedToLoan,
            2
        );

        $bankCredit = round(
            $requestedAmount
                - $commissionDeducted
                - $insuranceDeducted
                - $totalDeductFromDisbursement,
            2
        );

        if ($loanDebit <= 0) {
            throw new RuntimeException(
                'Computed loan ledger debit is invalid.'
            );
        }

        if ($bankCredit <= 0) {
            throw new RuntimeException(
                'Computed bank disbursement credit '
                    . 'is zero or negative.'
            );
        }

        $memberNumber =
            !empty($loan->member_sacco_id)
            ? $loan->member_sacco_id
            : $loan->batch_trans_member_id;

        $memberLabel =
            $memberNumber
            . ' - '
            . $loan->member_name;

        $chargeRows = collect(
            $chargeSummary['rows'] ?? []
        )->filter(
            function ($row) {
                return (float) (
                    $row
                    ->batch_trans_deduction_amount
                    ?? 0
                ) > 0;
            }
        );

        $chargesSnapshot = $chargeRows
            ->map(
                function ($row) {
                    return trim(
                        (string) (
                            $row
                            ->batch_trans_deduction_description
                            ?? ''
                        )
                    );
                }
            )
            ->filter()
            ->implode(' | ');

        $loanDescription =
            'Loan principal plus financed charges '
            . 'for member '
            . $memberLabel;

        $bankDescription =
            'Net loan disbursement to member '
            . $memberLabel;

        if ($chargesSnapshot !== '') {
            $loanDescription .=
                ' | ' . $chargesSnapshot;

            $bankDescription .=
                ' | ' . $chargesSnapshot;
        }

        $postings = [
            [
                'account' =>
                $loan->loan_type_acount,

                'debit' => $loanDebit,

                'credit' => 0,

                'doc_no' =>
                $loan->batch_trans_doc_no,

                'description' =>
                $loanDescription,

                'source' => 'Loan Account',
            ],
            [
                'account' =>
                $defaultBankAccount,

                'debit' => 0,

                'credit' => $bankCredit,

                'doc_no' =>
                $loan->batch_trans_doc_no,

                'description' =>
                $bankDescription,

                'source' => 'Loan Approved',
            ],
        ];

        if ($insurance > 0) {
            $postings[] = [
                'account' =>
                $defaultInsuranceAccount,

                'debit' => 0,

                'credit' => $insurance,

                'doc_no' =>
                $loan->batch_trans_doc_no,

                'description' =>
                'Loan insurance for member '
                    . $memberLabel,

                'source' => 'Loan Insurance',
            ];
        }

        if ($commission > 0) {
            $postings[] = [
                'account' =>
                $defaultCommissionAccount,

                'debit' => 0,

                'credit' => $commission,

                'doc_no' =>
                $loan->batch_trans_doc_no,

                'description' =>
                'Loan commission for member '
                    . $memberLabel,

                'source' => 'Loan Commission',
            ];
        }

        foreach (
            $chargeSummary['rows'] ?? []
            as $row
        ) {
            $amount = round(
                (float) (
                    $row
                    ->batch_trans_deduction_amount
                    ?? 0
                ),
                2
            );

            if ($amount <= 0) {
                continue;
            }

            $chargeDescription =
                !empty($row
                    ->batch_trans_deduction_description)
                ? $row
                ->batch_trans_deduction_description
                : (
                    (
                        $row
                        ->batch_trans_deduction_name
                        ?? 'Loan Charge'
                    )
                    . ' for member '
                    . $memberLabel
                );

            if (
                stripos(
                    $chargeDescription,
                    'member'
                ) === false
            ) {
                $chargeDescription .=
                    ' | Member: ' . $memberLabel;
            }

            $postings[] = [
                'account' =>
                $row
                    ->batch_trans_deduction_account,

                'debit' => 0,

                'credit' => $amount,

                'doc_no' =>
                $loan->batch_trans_doc_no,

                'description' =>
                $chargeDescription,

                'source' => 'Loan Charge',
            ];
        }

        $totalDebit = round(
            array_sum(
                array_column(
                    $postings,
                    'debit'
                )
            ),
            2
        );

        $totalCredit = round(
            array_sum(
                array_column(
                    $postings,
                    'credit'
                )
            ),
            2
        );

        if (
            abs(
                $totalDebit
                    - $totalCredit
            ) > 0.01
        ) {
            throw new RuntimeException(
                'Automatic loan approval ledger '
                    . 'is not balanced. Debits: '
                    . number_format(
                        $totalDebit,
                        2
                    )
                    . ', credits: '
                    . number_format(
                        $totalCredit,
                        2
                    )
                    . '.'
            );
        }

        foreach ($postings as $posting) {
            $this->updateSaccoAccountsTrans(
                $posting['account'],
                $posting['debit'],
                $posting['credit'],
                $posting['doc_no'],
                $posting['description'],
                $transdate,
                $currentPeriod,
                $posting['source'],
                $systemUserId,
                $systemIp
            );
        }
    }

    /**
     * Insert one accounting transaction and update cumulative totals.
     */
    private function updateSaccoAccountsTrans(
        $subAccountId,
        float $debit,
        float $credit,
        $docNo,
        string $description,
        $date,
        string $period,
        string $sourceDescription,
        int $systemUserId,
        string $systemIp
    ): void {
        DB::table('sacco_accounts_trans')
            ->insert([
                'accounts_trans_sub_account' =>
                $subAccountId,

                'accounts_trans_period' =>
                $period,

                'accounts_trans_debit' =>
                round($debit, 2),

                'accounts_trans_credit' =>
                round($credit, 2),

                'accounts_trans_doc_no' =>
                $docNo,

                'accounts_trans_decription' =>
                $description,

                'accounts_trans_dat_date' =>
                $date,

                'accounts_trans_user_id' =>
                $systemUserId,

                'accounts_trans_ip' =>
                $systemIp,

                'accounts_trans_source' =>
                $sourceDescription,

                'accounts_trans_app_name' =>
                'iSacco',
            ]);

        if ($debit > 0) {
            DB::table('sacco_sub_account')
                ->where(
                    'sub_account_id',
                    $subAccountId
                )
                ->increment(
                    'sub_account_debit',
                    round($debit, 2)
                );
        }

        if ($credit > 0) {
            DB::table('sacco_sub_account')
                ->where(
                    'sub_account_id',
                    $subAccountId
                )
                ->increment(
                    'sub_account_credit',
                    round($credit, 2)
                );
        }
    }
}
