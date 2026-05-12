<?php

namespace App\Jobs;

use App\Http\Controllers\PayrollDeductionsImportController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessPayrollDeductionsImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;
    public $failOnTimeout = false;

    protected string $storagePath;
    protected int $companyId;
    protected string $docNo;
    protected string $paymentDate;
    protected string $period;
    protected ?int $userId;
    protected ?string $requestIp;

    public function __construct(
        string $storagePath,
        int $companyId,
        string $docNo,
        string $paymentDate,
        string $period,
        ?int $userId,
        ?string $requestIp
    ) {
        $this->storagePath = $storagePath;
        $this->companyId = $companyId;
        $this->docNo = $docNo;
        $this->paymentDate = $paymentDate;
        $this->period = $period;
        $this->userId = $userId;
        $this->requestIp = $requestIp;
    }

    public function handle(): void
    {
        Log::info('Starting payroll deductions import job.', [
            'period' => $this->period,
            'company_id' => $this->companyId,
            'doc_no' => $this->docNo,
            'file' => $this->storagePath,
        ]);

        if (!Storage::disk('local')->exists($this->storagePath)) {
            throw new \RuntimeException("Payroll import file not found: {$this->storagePath}");
        }

        $controller = app(PayrollDeductionsImportController::class);

        $validated = $controller->buildValidatedPayload(
            $this->storagePath,
            $this->companyId,
            $this->docNo,
            $this->paymentDate,
            $this->period
        );

        if (!$validated['ok']) {
            $message = 'Payroll import validation failed in background job: ' . implode(' | ', $validated['errors']);
            Log::error($message);
            $this->notifyUser('Payroll Import Failed', $message, 'failed');
            throw new \RuntimeException($message);
        }

        $company = $validated['company'];
        $rows = $validated['rows'];
        $summaryData = $validated['summary'];

        $companyAccount = (int) $company->company_account;
        $this->ensureAccountExists($companyAccount, "Company account for {$company->company_name}");

        $defaultDepositAccount = null;
        $defaultCapitalAccount = null;
        $defaultOthersAccount = null;

        if (($summaryData['total_deposits'] ?? 0) > 0) {
            $defaultDepositAccount = $this->getRequiredDefaultAccount('default_share_account');
        }

        if (($summaryData['total_capital'] ?? 0) > 0) {
            $defaultCapitalAccount = $this->getRequiredDefaultAccount('default_share_capital_account');
        }

        if (($summaryData['total_others'] ?? 0) > 0) {
            $defaultOthersAccount = $this->getRequiredDefaultAccount('default_fosa_account');
        }

        if ($this->documentAlreadyImported($this->docNo, $this->period)) {
            $message = "Payroll import stopped. Document {$this->docNo} already exists in period {$this->period}.";
            Log::error($message);
            $this->notifyUser('Payroll Import Failed', $message, 'failed');
            throw new \RuntimeException($message);
        }

        $summary = [
            'deposits_count' => 0,
            'capital_count' => 0,
            'others_count' => 0,
            'loan_payment_count' => 0,
            'deposits_total' => 0,
            'capital_total' => 0,
            'others_total' => 0,
            'loan_total' => 0,
        ];

        DB::transaction(function () use (
            $rows,
            $company,
            $companyAccount,
            $defaultDepositAccount,
            $defaultCapitalAccount,
            $defaultOthersAccount,
            &$summary
        ) {
            if ($this->documentAlreadyImported($this->docNo, $this->period)) {
                throw new \RuntimeException("Document {$this->docNo} already exists in period {$this->period}.");
            }

            foreach ($rows as $row) {
                $memberId = (int) $row['member_id'];
                $memberName = (string) $row['member_name'];
                $nationalId = (string) $row['member_national_id'];

                foreach (($row['deposits'] ?? []) as $posting) {
                    $amount = round((float) ($posting['amount'] ?? 0), 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    $this->postDepositContribution(
                        $memberId,
                        $memberName,
                        $nationalId,
                        $posting,
                        $amount,
                        $company,
                        $companyAccount,
                        (int) $defaultDepositAccount
                    );

                    $summary['deposits_count']++;
                    $summary['deposits_total'] += $amount;
                }

                foreach (($row['capital'] ?? []) as $posting) {
                    $amount = round((float) ($posting['amount'] ?? 0), 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    $this->postCapitalContribution(
                        $memberId,
                        $memberName,
                        $nationalId,
                        $posting,
                        $amount,
                        $company,
                        $companyAccount,
                        (int) $defaultCapitalAccount
                    );

                    $summary['capital_count']++;
                    $summary['capital_total'] += $amount;
                }

                foreach (($row['others'] ?? []) as $posting) {
                    $amount = round((float) ($posting['amount'] ?? 0), 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    $this->postOthersContribution(
                        $memberId,
                        $memberName,
                        $nationalId,
                        $posting,
                        $amount,
                        $company,
                        $companyAccount,
                        (int) $defaultOthersAccount
                    );

                    $summary['others_count']++;
                    $summary['others_total'] += $amount;
                }

                foreach (($row['loans'] ?? []) as $posting) {
                    $amount = round((float) ($posting['amount'] ?? 0), 2);

                    if ($amount <= 0) {
                        continue;
                    }

                   $this->postLoanRepayment(
    $memberId,
    $memberName,
    $nationalId,
    $posting,
    $amount,
    $company,
    $companyAccount
);

                    $summary['loan_payment_count']++;
                    $summary['loan_total'] += $amount;
                }
            }
        }, 1);

        Storage::disk('local')->delete($this->storagePath);

        $message = "Payroll import completed for period {$this->period}. "
            . "Deposits: {$summary['deposits_count']} records / {$summary['deposits_total']}. "
            . "Capital: {$summary['capital_count']} records / {$summary['capital_total']}. "
            . "Others/FOSA: {$summary['others_count']} records / {$summary['others_total']}. "
            . "Loans: {$summary['loan_payment_count']} records / {$summary['loan_total']}.";

        Log::info($message, $summary);

        $this->notifyUser('Payroll Import Completed', $message, 'sent');
    }

    private function postDepositContribution(
        int $memberId,
        string $memberName,
        string $nationalId,
        array $posting,
        float $amount,
        object $company,
        int $companyAccount,
        int $defaultDepositAccount
    ): void {
        $itemName = $posting['item_name'] ?? 'DEPOSIT';

        $description = $this->limitText(
            "{$this->period} Payroll Import - Deposit - {$itemName} - {$memberName} / {$nationalId}",
            100
        );

        DB::table('sacco_shares')->insert([
            'share_member_id' => $memberId,
            'share_amount_paying' => $amount,
            'share_paid_by' => $this->limitText($company->company_name ?? 'PAYROLL IMPORT', 100),
            'share_period' => $this->period,
            'share_description' => $description,
            'share_doc_no' => $this->docNo,
            'share_date_paid' => $this->paymentDate,
            'share_end_month_proc' => 'Y',
            'share_by' => $this->userId,
            'share_ip' => $this->requestIp,
        ]);

        DB::update(
            'UPDATE sacco_members 
             SET member_total_share = COALESCE(member_total_share, 0) + ? 
             WHERE member_id = ?',
            [$amount, $memberId]
        );

        $source = "{$this->period} Payroll Import - Deposits - {$company->company_name}";

        $this->updateSaccoAccountsTrans(
            $defaultDepositAccount,
            0,
            $amount,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );

        $this->updateSaccoAccountsTrans(
            $companyAccount,
            $amount,
            0,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );
    }

    private function postCapitalContribution(
        int $memberId,
        string $memberName,
        string $nationalId,
        array $posting,
        float $amount,
        object $company,
        int $companyAccount,
        int $defaultCapitalAccount
    ): void {
        $itemName = $posting['item_name'] ?? 'CAPITAL';

        $description = $this->limitText(
            "{$this->period} Payroll Import - Capital - {$itemName} - {$memberName} / {$nationalId}",
            100
        );

        DB::table('sacco_capital_shares')->insert([
            'share_capitalmember_id' => $memberId,
            'share_capitalamount_paying' => $amount,
            'share_capitalpaid_by' => $this->limitText($company->company_name ?? 'PAYROLL IMPORT', 100),
            'share_capitalperiod' => $this->period,
            'share_capitaldescription' => $description,
            'share_capitaldoc_no' => $this->docNo,
            'share_capitaldate_paid' => $this->paymentDate,
            'share_capitalend_month_proc' => 'Y',
            'share_capitalby' => $this->userId,
            'share_capitalip' => $this->requestIp,
        ]);

        DB::update(
            'UPDATE sacco_members 
             SET member_total_share_capital = COALESCE(member_total_share_capital, 0) + ? 
             WHERE member_id = ?',
            [$amount, $memberId]
        );

        $source = "{$this->period} Payroll Import - Capital - {$company->company_name}";

        $this->updateSaccoAccountsTrans(
            $defaultCapitalAccount,
            0,
            $amount,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );

        $this->updateSaccoAccountsTrans(
            $companyAccount,
            $amount,
            0,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );
    }

    private function postOthersContribution(
        int $memberId,
        string $memberName,
        string $nationalId,
        array $posting,
        float $amount,
        object $company,
        int $companyAccount,
        int $defaultOthersAccount
    ): void {
        $fosaTypeId = $posting['fosa_type_id'] ?? null;
        $fosaTypeName = $posting['fosa_type_name'] ?? ($posting['item_name'] ?? 'OTHERS');

        $description = $this->limitText(
            "{$this->period} Payroll Import - Others/FOSA - {$fosaTypeName} - {$memberName} / {$nationalId}",
            255
        );

        $insert = [
            'fosa_member_id' => $memberId,
            'fosa_amount_paying' => $amount,
            'fosa_paid_by' => $this->limitText($company->company_name ?? 'PAYROLL IMPORT', 100),
            'fosa_period' => $this->period,
            'fosa_description' => $description,
            'fosa_doc_no' => $this->docNo,
            'fosa_date_paid' => $this->paymentDate,
            'fosa_end_month_proc' => 'Y',
            'fosa_by' => $this->userId,
            'fosa_ip' => $this->requestIp,
        ];

        if (Schema::hasColumn('sacco_fosas', 'fosa_type_id')) {
            $insert['fosa_type_id'] = $fosaTypeId;
        }

        DB::table('sacco_fosas')->insert($insert);

        DB::update(
            'UPDATE sacco_members 
             SET member_total_fosa = COALESCE(member_total_fosa, 0) + ? 
             WHERE member_id = ?',
            [$amount, $memberId]
        );

        $source = "{$this->period} Payroll Import - Others/FOSA - {$company->company_name}";

        $this->updateSaccoAccountsTrans(
            $defaultOthersAccount,
            0,
            $amount,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );

        $this->updateSaccoAccountsTrans(
            $companyAccount,
            $amount,
            0,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );
    }

   private function postLoanRepayment(
    int $memberId,
    string $memberName,
    string $nationalId,
    array $posting,
    float $deductedAmount,
    object $company,
    int $companyAccount
): void {
    $loanTypeId = (int) ($posting['loan_type_id'] ?? 0);
    $loanTypeName = (string) ($posting['loan_type_name'] ?? 'UNKNOWN LOAN');

    if ($loanTypeId <= 0) {
        throw new \RuntimeException("Invalid loan type for {$memberName} / {$nationalId}.");
    }

    /*
     * Important:
     * Use the target loan selected during validation first.
     * This prevents the job from re-searching exact-only and missing migrated loans.
     */
    $loan = $this->getTargetLoanForPosting($memberId, $posting);

    /*
     * Keep using the exact Excel-matched loan type for accounting configuration.
     * Example: Excel NORMAL uses NORMAL accounts, even if the member loan is stored as NORMAL LOAN.
     */
    $loanType = $this->getLoanType($loanTypeId);

    if (empty($loanType->loan_type_acount)) {
        throw new \RuntimeException("Loan principal account missing for loan type {$loanTypeName}.");
    }

    if (empty($loanType->loan_type_int_account)) {
        throw new \RuntimeException("Loan interest account missing for loan type {$loanTypeName}.");
    }

    $principalAccount = (int) $loanType->loan_type_acount;
    $interestAccount = (int) $loanType->loan_type_int_account;

    $this->ensureAccountExists($principalAccount, "Loan principal account for {$loanTypeName}");
    $this->ensureAccountExists($interestAccount, "Loan interest account for {$loanTypeName}");

    $split = $this->calculateLoanSplit($loan, $loanType, $deductedAmount);

    $interest = $split['interest'];
    $principalPaid = $split['principal'];

    $description = $this->limitText(
        "{$this->period} Payroll Import - Loan - {$loanTypeName} - {$memberName} / {$nationalId}",
        255
    );

    DB::table('sacco_loan_payments')->insert([
        'loan_payments_amount' => $principalPaid,
        'loan_payments_description' => $description,
        'loan_payments_docno' => $this->docNo,
        'loan_payments_paid_in_by' => $this->limitText($company->company_name ?? 'PAYROLL IMPORT', 100),
        'loan_payments_period' => $this->period,
        'loan_payments_paid_on' => $this->paymentDate,
        'loan_payments_loan_id' => $loan->loan_id,
        'loan_payments_interest' => $interest,
        'loan_end_month_proc' => 'Y',
        'loan_payments_by' => $this->userId,
        'loan_payments_ip' => $this->requestIp,
    ]);

    DB::update(
        'UPDATE sacco_loans 
         SET loan_loan_paid = COALESCE(loan_loan_paid, 0) + ? 
         WHERE loan_id = ?',
        [$principalPaid, $loan->loan_id]
    );

    DB::update(
        'UPDATE sacco_members 
         SET member_total_loan = COALESCE(member_total_loan, 0) - ? 
         WHERE member_id = ?',
        [$principalPaid, $memberId]
    );

    $source = "{$this->period} Payroll Import - Loan Repayment - {$company->company_name}";

    if ($principalPaid >= 0) {
        $this->updateSaccoAccountsTrans(
            $principalAccount,
            0,
            $principalPaid,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );
    } else {
        $this->updateSaccoAccountsTrans(
            $principalAccount,
            abs($principalPaid),
            0,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );
    }

    if ($interest > 0) {
        $this->updateSaccoAccountsTrans(
            $interestAccount,
            0,
            $interest,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );
    }

    if ($deductedAmount > 0) {
        $this->updateSaccoAccountsTrans(
            $companyAccount,
            $deductedAmount,
            0,
            $this->docNo,
            $description,
            $this->paymentDate,
            $this->period,
            $source
        );
    }

    if ($principalPaid > 0) {
        $this->releaseGuarantorShares($loan, $principalPaid);
    }
}

   private function getTargetLoanForMemberAndType(
    int $memberId,
    int $primaryLoanTypeId,
    array $lookupLoanTypeIds
): object {
    if ($primaryLoanTypeId <= 0) {
        throw new \RuntimeException("Invalid loan type while searching loan for member {$memberId}.");
    }

    $minLoanAmountBillable = DB::table('sacco_defaults')
        ->where('default_name', 'min_loan_amount_bill_able')
        ->value('default_value');

    if (!is_numeric($minLoanAmountBillable)) {
        $minLoanAmountBillable = 1;
    }

    $threshold = max(1, (float) $minLoanAmountBillable);
    $period = $this->period;

    $lookupLoanTypeIds = array_values(array_unique(array_filter(array_map('intval', $lookupLoanTypeIds))));

    if (empty($lookupLoanTypeIds)) {
        $lookupLoanTypeIds = [$primaryLoanTypeId];
    }

    if (!in_array($primaryLoanTypeId, $lookupLoanTypeIds, true)) {
        array_unshift($lookupLoanTypeIds, $primaryLoanTypeId);
    }

    $fallbackLoanTypeIds = array_values(array_diff($lookupLoanTypeIds, [$primaryLoanTypeId]));

    /*
     * 1. First try exact Excel-matched loan type.
     */
    $loan = $this->findLoanByTypeIds(
        $memberId,
        [$primaryLoanTypeId],
        $period,
        $threshold,
        true
    );

    if ($loan) {
        return $loan;
    }

    /*
     * 2. Then try naming variants.
     * Example: NORMAL -> NORMAL LOAN / NORMAL LOANS.
     */
    if (!empty($fallbackLoanTypeIds)) {
        $loan = $this->findLoanByTypeIds(
            $memberId,
            $fallbackLoanTypeIds,
            $period,
            $threshold,
            true
        );

        if ($loan) {
            return $loan;
        }
    }

    /*
     * 3. Existing fallback: latest exact loan.
     */
    $loan = $this->findLoanByTypeIds(
        $memberId,
        [$primaryLoanTypeId],
        $period,
        $threshold,
        false
    );

    if ($loan) {
        return $loan;
    }

    /*
     * 4. Last fallback: latest naming-variant loan.
     */
    if (!empty($fallbackLoanTypeIds)) {
        $loan = $this->findLoanByTypeIds(
            $memberId,
            $fallbackLoanTypeIds,
            $period,
            $threshold,
            false
        );

        if ($loan) {
            return $loan;
        }
    }

    throw new \RuntimeException(
        "No loan record found for member {$memberId}, loan type {$primaryLoanTypeId}."
    );
}

   private function baseLoanQuery(int $memberId, array $loanTypeIds)
{
    $loanTypeIds = array_values(array_unique(array_filter(array_map('intval', $loanTypeIds))));

    return DB::table('sacco_loans')
        ->where('loan_member', $memberId)
        ->whereIn('loan_loan_type', $loanTypeIds)
        ->where('loan_amount', '>', 0)
        ->where(function ($query) {
            $query->whereNull('loan_stoped')
                ->orWhere('loan_stoped', '')
                ->orWhere('loan_stoped', '<>', 'Y');
        });
}

    private function applyLoanOrdering($query): void
    {
        if (Schema::hasColumn('sacco_loans', 'loan_taken_period')) {
            $query->orderBy('loan_taken_period', 'desc');
        }

        if (Schema::hasColumn('sacco_loans', 'loan_on')) {
            $query->orderBy('loan_on', 'desc');
        }

        $query->orderBy('loan_id', 'desc');
    }

    private function calculateLoanSplit(object $loan, object $loanType, float $deductedAmount): array
{
    $loanAmount = (float) $loan->loan_amount;
    $loanPaid = (float) ($loan->loan_loan_paid ?? 0);
    $outstanding = max(0, $loanAmount - $loanPaid);

    /*
     * If loan is already cleared, do not charge interest.
     * Full payroll deduction becomes principal/overpayment.
     */
    if ($outstanding <= 1) {
        return [
            'interest' => 0,
            'principal' => round($deductedAmount, 2),
            'outstanding' => $outstanding,
        ];
    }

    $interestType = strtoupper(trim((string) ($loanType->loan_type_interest_type ?? '')));
    $interestRate = (float) ($loanType->loan_type_interest ?? 0);

    if ($interestType === 'FIXED INTEREST') {
        /*
         * Fixed interest:
         * Interest is charged on the deducted amount.
         */
        $interest = round(($interestRate / 100) * $deductedAmount, 2);
    } else {
        /*
         * Reducing balance:
         * loan_type_interest is annual.
         * Monthly interest = annual rate / 12 / 100 × outstanding balance.
         * Charge only once per loan per period.
         */
        if ($this->interestAlreadyChargedForPeriod((int) $loan->loan_id)) {
            $interest = 0;
        } else {
            $monthlyRate = ($interestRate / 12) / 100;
            $interest = round($monthlyRate * $outstanding, 2);
        }
    }

    $principal = round($deductedAmount - $interest, 2);

    return [
        'interest' => $interest,
        'principal' => $principal,
        'outstanding' => $outstanding,
    ];
}
    private function releaseGuarantorShares(object $loan, float $principalPaid): void
    {
        $loanAmount = (float) $loan->loan_amount;

        if ($loanAmount <= 0 || $principalPaid <= 0 || !Schema::hasTable('sacco_loan_guarantors')) {
            return;
        }

        $guarantors = DB::table('sacco_loan_guarantors')
            ->where('loan_guar_loan_id', $loan->loan_id)
            ->where(function ($query) {
                $query->whereNull('loan_guar_deleted')
                    ->orWhere('loan_guar_deleted', '')
                    ->orWhere('loan_guar_deleted', '<>', 'Y');
            })
            ->get();

        foreach ($guarantors as $guarantor) {
            $guaranteed = (float) ($guarantor->loan_guar_amount_guaranteed ?? 0);
            $alreadyFreed = (float) ($guarantor->loan_guar_amount_freed ?? 0);
            $remaining = max(0, $guaranteed - $alreadyFreed);

            if ($remaining <= 0) {
                continue;
            }

            $freedAmount = round(($principalPaid / $loanAmount) * $guaranteed, 2);
            $freedAmount = min($freedAmount, $remaining);

            if ($freedAmount <= 0) {
                continue;
            }

            DB::update(
                'UPDATE sacco_loan_guarantors
                 SET loan_guar_amount_freed = COALESCE(loan_guar_amount_freed, 0) + ?
                 WHERE loan_guar_id = ?',
                [$freedAmount, $guarantor->loan_guar_id]
            );

            if ((int) $loan->loan_member === (int) $guarantor->loan_guar_guarantor_id) {
                DB::update(
                    'UPDATE sacco_members
                     SET member_tied_shares_self = GREATEST(COALESCE(member_tied_shares_self, 0) - ?, 0)
                     WHERE member_id = ?',
                    [$freedAmount, $guarantor->loan_guar_guarantor_id]
                );
            } else {
                DB::update(
                    'UPDATE sacco_members
                     SET member_tied_shares = GREATEST(COALESCE(member_tied_shares, 0) - ?, 0)
                     WHERE member_id = ?',
                    [$freedAmount, $guarantor->loan_guar_guarantor_id]
                );
            }
        }
    }

    private function getLoanType(int $loanTypeId): object
    {
        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $loanTypeId)
            ->first();

        if (!$loanType) {
            throw new \RuntimeException("Loan type {$loanTypeId} was not found.");
        }

        return $loanType;
    }

    private function getRequiredDefaultAccount(string $defaultName): int
    {
        $value = DB::table('sacco_defaults')
            ->where('default_name', $defaultName)
            ->value('default_value');

        if (!is_numeric($value) || (int) $value <= 0) {
            throw new \RuntimeException("Missing or invalid default account: {$defaultName}");
        }

        $accountId = (int) $value;
        $this->ensureAccountExists($accountId, $defaultName);

        return $accountId;
    }

    private function ensureAccountExists(int $subAccountId, string $label): void
    {
        $exists = DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->where(function ($query) {
                $query->whereNull('sub_account_deleted')
                    ->orWhere('sub_account_deleted', '')
                    ->orWhere('sub_account_deleted', '<>', 'Y');
            })
            ->exists();

        if (!$exists) {
            throw new \RuntimeException("{$label} is invalid. Sub account ID {$subAccountId} was not found.");
        }
    }

    private function updateSaccoAccountsTrans(
        int $subAccountId,
        float $debit,
        float $credit,
        string $docNo,
        string $description,
        string $date,
        string $period,
        string $sourceDescription
    ): void {
        $debit = round($debit, 2);
        $credit = round($credit, 2);

        if ($debit == 0 && $credit == 0) {
            return;
        }

        DB::table('sacco_accounts_trans')->insert([
            'accounts_trans_sub_account' => $subAccountId,
            'accounts_trans_period' => $period,
            'accounts_trans_debit' => $debit,
            'accounts_trans_credit' => $credit,
            'accounts_trans_doc_no' => $docNo,
            'accounts_trans_decription' => $description,
            'accounts_trans_dat_date' => $date,
            'accounts_trans_user_id' => $this->userId,
            'accounts_trans_ip' => $this->requestIp,
            'accounts_trans_source' => $sourceDescription,
            'accounts_trans_app_name' => 'iSacco',
        ]);

        DB::update(
            'UPDATE sacco_sub_account
             SET sub_account_debit = COALESCE(sub_account_debit, 0) + ?,
                 sub_account_credit = COALESCE(sub_account_credit, 0) + ?
             WHERE sub_account_id = ?',
            [$debit, $credit, $subAccountId]
        );

        $mainAccountId = DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->value('sub_account_main_account');

        if ($mainAccountId) {
            DB::update(
                'UPDATE sacco_main_account
                 SET main_account_debit = COALESCE(main_account_debit, 0) + ?,
                     main_account_credit = COALESCE(main_account_credit, 0) + ?
                 WHERE main_account_id = ?',
                [$debit, $credit, $mainAccountId]
            );
        }
    }

    private function documentAlreadyImported(string $docNo, string $period): bool
    {
        $shareExists = DB::table('sacco_shares')
            ->where('share_period', $period)
            ->where('share_doc_no', $docNo)
            ->where('share_end_month_proc', 'Y')
            ->exists();

        $capitalExists = DB::table('sacco_capital_shares')
            ->where('share_capitalperiod', $period)
            ->where('share_capitaldoc_no', $docNo)
            ->where('share_capitalend_month_proc', 'Y')
            ->exists();

        $fosaExists = DB::table('sacco_fosas')
            ->where('fosa_period', $period)
            ->where('fosa_doc_no', $docNo)
            ->where('fosa_end_month_proc', 'Y')
            ->exists();

        $loanExists = DB::table('sacco_loan_payments')
            ->where('loan_payments_period', $period)
            ->where('loan_payments_docno', $docNo)
            ->where('loan_end_month_proc', 'Y')
            ->exists();

        return $shareExists || $capitalExists || $fosaExists || $loanExists;
    }

    private function notifyUser(string $subject, string $message, string $status = 'unread'): void
    {
        try {
            if (!$this->userId || !Schema::hasTable('sacco_system_notifications')) {
                return;
            }

            $member = DB::table('sacco_members')
                ->where('member_id', $this->userId)
                ->first();

            DB::table('sacco_system_notifications')->insert([
                'notif_recipient_name' => $member->member_name ?? null,
                'notif_recipient_email' => $member->member_email ?? null,
                'notif_recipient_phone' => $member->member_phone_no ?? null,
                'notif_subject' => $subject,
                'notif_message' => $message,
                'notif_status' => $status === 'sent' ? 'unread' : 'failed',
                'notif_member_id' => $this->userId,
                'notif_related_doc' => $this->docNo,
                'notif_type' => 'payroll_import',
                'notif_created_by' => $this->userId,
                'notif_ip' => $this->requestIp,
                'notif_meta' => json_encode([
                    'period' => $this->period,
                    'company_id' => $this->companyId,
                    'file' => $this->storagePath,
                ]),
                'notif_created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to create payroll import notification: ' . $e->getMessage());
        }
    }

    private function limitText(?string $value, int $limit): string
    {
        $value = trim((string) $value);

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Payroll deductions import job failed.', [
            'period' => $this->period,
            'company_id' => $this->companyId,
            'doc_no' => $this->docNo,
            'file' => $this->storagePath,
            'error' => $exception->getMessage(),
        ]);

        $this->notifyUser(
            'Payroll Import Failed',
            'Payroll import failed for document ' . $this->docNo . ': ' . $exception->getMessage(),
            'failed'
        );
    }
private function interestAlreadyChargedForPeriod(int $loanId): bool
{
    $query = DB::table('sacco_loan_payments')
        ->where('loan_payments_loan_id', $loanId)
        ->where('loan_payments_period', $this->period)
        ->where('loan_payments_interest', '>', 0);

    if (Schema::hasColumn('sacco_loan_payments', 'loan_payments_deleted')) {
        $query->where(function ($q) {
            $q->whereNull('loan_payments_deleted')
                ->orWhere('loan_payments_deleted', '')
                ->orWhere('loan_payments_deleted', '<>', 'Y');
        });
    }

    return $query->exists();
}
private function getTargetLoanForPosting(int $memberId, array $posting): object
{
    $targetLoanId = (int) ($posting['target_loan_id'] ?? 0);

    /*
     * Best path:
     * The controller already validated the correct target loan.
     */
    if ($targetLoanId > 0) {
        $loan = DB::table('sacco_loans')
            ->where('loan_id', $targetLoanId)
            ->where('loan_member', $memberId)
            ->where('loan_amount', '>', 0)
            ->where(function ($query) {
                $query->whereNull('loan_stoped')
                    ->orWhere('loan_stoped', '')
                    ->orWhere('loan_stoped', '<>', 'Y');
            })
            ->first();

        if ($loan) {
            return $loan;
        }

        throw new \RuntimeException(
            "Validated target loan {$targetLoanId} was not found for member {$memberId} during posting."
        );
    }

    /*
     * Fallback path:
     * Should rarely be used, but keeps the job safe if old session data exists.
     */
    return $this->getTargetLoanForMemberAndType(
        $memberId,
        (int) ($posting['loan_type_id'] ?? 0),
        $posting['lookup_loan_type_ids'] ?? [(int) ($posting['loan_type_id'] ?? 0)]
    );
}private function findLoanByTypeIds(
    int $memberId,
    array $loanTypeIds,
    string $period,
    float $threshold,
    bool $outstandingOnly
) {
    $loanTypeIds = array_values(array_unique(array_filter(array_map('intval', $loanTypeIds))));

    if (empty($loanTypeIds)) {
        return null;
    }

    $query = $this->baseLoanQuery($memberId, $loanTypeIds)
        ->where(function ($query) use ($period) {
            $query->whereNull('loan_taken_period')
                ->orWhere('loan_taken_period', '')
                ->orWhere('loan_taken_period', '<=', $period);
        });

    if ($outstandingOnly) {
        $query->where(function ($query) use ($period) {
            $query->whereNull('loan_start_deduction_period')
                ->orWhere('loan_start_deduction_period', '')
                ->orWhere('loan_start_deduction_period', '<=', $period);
        });

        $query->whereRaw(
            '(COALESCE(loan_amount, 0) - COALESCE(loan_loan_paid, 0)) > ?',
            [$threshold]
        );
    }

    $placeholders = implode(',', array_fill(0, count($loanTypeIds), '?'));

    $query->orderByRaw(
        "FIELD(loan_loan_type, {$placeholders}) ASC",
        $loanTypeIds
    );

    $this->applyLoanOrdering($query);

    return $query->first();
}
}