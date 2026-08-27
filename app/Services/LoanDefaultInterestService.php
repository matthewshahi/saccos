<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class LoanDefaultInterestService
{
    private const SYSTEM_USER_ID = 1;
    private const SYSTEM_IP = '127.0.0.1';
    private const SOURCE = 'DFI';
    private const PAYMENT_SOURCE = 'AUTO-DFI';
    private const MAX_DUE_CYCLES_TO_SCAN = 600;

    private ?float $thresholdAmountCache = null;
    private ?int $monthlyCutOffDayCache = null;
    private ?string $activePeriodCache = null;

    /**
     * Process at most ONE default-interest due cycle for one loan.
     *
     * This is deliberate: the scheduler limits work to 20 loans/minute.
     * A heavily overdue loan is caught up one monthly due cycle at a time,
     * without a single invocation producing an unbounded amount of work.
     */
    public function processLoan(int $loanId): array
    {
        $notificationContext = null;

        $result = DB::transaction(function () use ($loanId, &$notificationContext) {
            $loan = DB::table('sacco_loans as l')
                ->join('sacco_loan_types as t', 'l.loan_loan_type', '=', 't.loan_type_id')
                ->where('l.loan_id', $loanId)
                ->select(
                    'l.loan_id',
                    'l.loan_member',
                    'l.loan_loan_type',
                    'l.loan_amount',
                    'l.loan_loan_paid',
                    'l.loan_taken_period',
                    'l.loan_payment_period',
                    'l.loan_start_deduction_period',
                    'l.loan_on',
                    'l.loan_monthly_repayment_amount',
                    'l.loan_monthly_repayment_principal',
                    'l.loan_stoped',
                    't.loan_type_name',
                    't.loan_type_interest',
                    't.loan_type_interest_type',
                    't.loan_type_auto_interest_on_period_change',
                    't.loan_type_grace_days_after_due',
                    't.loan_type_default_interest',
                    't.loan_type_acount',
                    't.loan_type_int_account'
                )
                ->lockForUpdate()
                ->first();

            if (!$loan) {
                return $this->skipped($loanId, 'loan_not_found');
            }

            if (strtoupper(trim((string) ($loan->loan_stoped ?? 'N'))) === 'Y') {
                return $this->skipped($loanId, 'loan_stopped');
            }

            if (!$this->isAutoDefaultInterestEnabled($loan->loan_type_auto_interest_on_period_change ?? null)) {
                return $this->skipped($loanId, 'auto_interest_disabled');
            }

            $threshold = $this->thresholdAmount();
            $previousOutstanding = $this->outstanding($loan);

            if ($previousOutstanding <= $threshold) {
                return $this->skipped($loanId, 'below_threshold', [
                    'outstanding' => $previousOutstanding,
                    'threshold' => $threshold,
                ]);
            }

            if (empty($loan->loan_type_acount) || empty($loan->loan_type_int_account)) {
                throw new RuntimeException(
                    "DFI configuration error for loan {$loanId}: loan receivable or interest account is missing."
                );
            }

            $member = DB::table('sacco_members')
                ->where('member_id', $loan->loan_member)
                ->lockForUpdate()
                ->first();

            if (!$member) {
                throw new RuntimeException(
                    "DFI data error for loan {$loanId}: member {$loan->loan_member} was not found."
                );
            }

            $defaultable = $this->findNextDefaultableDueCycle($loan, $threshold);

            if ($defaultable === null) {
                return $this->skipped($loanId, 'not_in_default');
            }

            /** @var Carbon $dueDate */
            $dueDate = $defaultable['due_date'];
            /** @var Carbon $defaultDate */
            $defaultDate = $defaultable['default_date'];

            $docNo = $this->documentNumber($loanId, $dueDate);
            $description = $this->description($loanId, $dueDate);

            $existingPosting = $this->checkExistingPostingIntegrity(
                $loan,
                $docNo
            );

            if ($existingPosting !== null) {
                return $existingPosting;
            }

            $effectiveRate = $loan->loan_type_default_interest !== null
                ? (float) $loan->loan_type_default_interest
                : (float) ($loan->loan_type_interest ?? 0);

            if ($effectiveRate <= 0) {
                return $this->skipped($loanId, 'zero_default_interest_rate', [
                    'due_date' => $dueDate->toDateString(),
                ]);
            }

            $interest = $this->calculateDefaultInterest(
                $loan,
                $previousOutstanding,
                $effectiveRate
            );

            if ($interest <= 0) {
                return $this->skipped($loanId, 'calculated_interest_zero', [
                    'due_date' => $dueDate->toDateString(),
                ]);
            }

            $postedAt = now('Africa/Nairobi');
            $postingPeriod = $this->activePeriod();
            $duePeriod = $dueDate->format('Ym');

            /*
            |------------------------------------------------------------------
            | sacco_loan_payments
            |------------------------------------------------------------------
            | Negative principal movement capitalises the interest.
            | Net cash effect is zero because:
            |   loan_payments_amount   = -interest
            |   loan_payments_interest = +interest
            |------------------------------------------------------------------
            */
            DB::table('sacco_loan_payments')->insert([
                'loan_payments_amount' => 0 - $interest,
                'loan_payments_interest' => $interest,
                'loan_payments_docno' => $docNo,
                'loan_payments_paid_on' => $postedAt->toDateString(),
                'loan_payments_loan_id' => $loanId,
                'loan_payments_period' => $duePeriod,
                'loan_payments_by' => self::SYSTEM_USER_ID,
                'loan_payments_ip' => self::SYSTEM_IP,
                'loan_end_month_proc' => 'N',
                'loan_payments_description' => $description,
                'loan_payments_paid_in_by' => self::PAYMENT_SOURCE,
            ]);

            /*
            |------------------------------------------------------------------
            | sacco_loans + sacco_members
            |------------------------------------------------------------------
            | Outstanding = loan_amount - loan_loan_paid.
            | Decreasing loan_loan_paid therefore capitalises the interest.
            |------------------------------------------------------------------
            */
            DB::table('sacco_loans')
                ->where('loan_id', $loanId)
                ->update([
                    'loan_loan_paid' => DB::raw(
                        'COALESCE(loan_loan_paid, 0) - ' . (float) $interest
                    ),
                ]);

            DB::table('sacco_members')
                ->where('member_id', $loan->loan_member)
                ->update([
                    'member_total_loan' => DB::raw(
                        'COALESCE(member_total_loan, 0) + ' . (float) $interest
                    ),
                ]);

            /*
            |------------------------------------------------------------------
            | Ledger
            |------------------------------------------------------------------
            | DR Loan receivable / loan principal account
            | CR Loan interest income account
            |------------------------------------------------------------------
            */
            $this->postLedgerEntry(
                (int) $loan->loan_type_acount,
                $interest,
                0.00,
                $docNo,
                $description,
                $postingPeriod,
                $postedAt
            );

            $this->postLedgerEntry(
                (int) $loan->loan_type_int_account,
                0.00,
                $interest,
                $docNo,
                $description,
                $postingPeriod,
                $postedAt
            );

            $newOutstanding = round($previousOutstanding + $interest, 2);

            $notificationContext = [
                'loan_id' => (int) $loanId,
                'loan_type_name' => (string) ($loan->loan_type_name ?? 'Loan'),
                'member_id' => (int) $loan->loan_member,
                'member_name' => (string) ($member->member_name ?? ''),
                'member_sacco_id' => (string) ($member->member_sacco_id ?? ''),
                'member_email' => (string) ($member->member_email ?? ''),
                'member_phone_no' => (string) ($member->member_phone_no ?? ''),
                'previous_outstanding' => $previousOutstanding,
                'interest' => $interest,
                'new_outstanding' => $newOutstanding,
                'rate' => $effectiveRate,
                'due_date' => $dueDate->toDateString(),
                'default_date' => $defaultDate->toDateString(),
                'posted_at' => $postedAt->format('Y-m-d H:i:s'),
                'doc_no' => $docNo,
            ];

            return [
                'status' => 'posted',
                'loan_id' => (int) $loanId,
                'doc_no' => $docNo,
                'description' => $description,
                'due_date' => $dueDate->toDateString(),
                'default_date' => $defaultDate->toDateString(),
                'rate' => $effectiveRate,
                'interest' => $interest,
                'previous_outstanding' => $previousOutstanding,
                'new_outstanding' => $newOutstanding,
            ];
        }, 3);

        /*
        |------------------------------------------------------------------
        | Notifications are intentionally AFTER financial COMMIT.
        | Email/outbox failure must never reverse a valid financial posting.
        |------------------------------------------------------------------
        */
        if (($result['status'] ?? null) === 'posted' && $notificationContext !== null) {
            $this->queueNotifications($notificationContext);
        }

        return $result;
    }

    public function thresholdAmount(): float
    {
        if ($this->thresholdAmountCache !== null) {
            return $this->thresholdAmountCache;
        }

        $value = DB::table('sacco_defaults')
            ->where('default_name', 'threshold_amount')
            ->value('default_value');

        $this->thresholdAmountCache = is_numeric($value)
            ? max(0, (float) $value)
            : 1.00;

        return $this->thresholdAmountCache;
    }

    private function monthlyCutOffDay(): int
    {
        if ($this->monthlyCutOffDayCache !== null) {
            return $this->monthlyCutOffDayCache;
        }

        $value = DB::table('sacco_defaults')
            ->where('default_name', 'monthly_cut_of_day')
            ->value('default_value');

        $day = is_numeric($value) ? (int) $value : 22;

        $this->monthlyCutOffDayCache = max(1, min($day, 31));

        return $this->monthlyCutOffDayCache;
    }

    private function activePeriod(): string
    {
        if ($this->activePeriodCache !== null) {
            return $this->activePeriodCache;
        }

        $period = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->whereRaw("COALESCE(period_deleted, 'N') <> 'Y'")
            ->value('period_name');

        $this->activePeriodCache = $this->isValidPeriod($period)
            ? (string) $period
            : now('Africa/Nairobi')->format('Ym');

        return $this->activePeriodCache;
    }

    private function isAutoDefaultInterestEnabled($value): bool
    {
        if (is_numeric($value) && (int) $value === 1) {
            return true;
        }

        return in_array(
            strtoupper(trim((string) $value)),
            ['Y', 'YES', 'TRUE'],
            true
        );
    }

    private function outstanding($loan): float
    {
        return round(
            (float) ($loan->loan_amount ?? 0)
                - (float) ($loan->loan_loan_paid ?? 0),
            2
        );
    }

    /**
     * Find the earliest monthly due cycle that:
     *  - has reached due date + grace days,
     *  - was not sufficiently paid by the end of the grace date,
     *  - has not already received its DFI posting.
     */
    private function findNextDefaultableDueCycle($loan, float $threshold): ?array
    {
        $firstDueDate = $this->firstContractualDueDate($loan);
        $anchorDay = Carbon::parse($loan->loan_on)->day;
        $graceDays = max(0, (int) ($loan->loan_type_grace_days_after_due ?? 45));
        $today = now('Africa/Nairobi')->startOfDay();

        $scheduledPayment = round(
            (float) ($loan->loan_monthly_repayment_amount ?? 0),
            2
        );

        if ($scheduledPayment <= 0) {
            throw new RuntimeException(
                "DFI data error for loan {$loan->loan_id}: loan_monthly_repayment_amount is zero or missing."
            );
        }

        $dueDate = $firstDueDate->copy();

        for ($i = 0; $i < self::MAX_DUE_CYCLES_TO_SCAN; $i++) {
            $defaultDate = $dueDate->copy()->addDays($graceDays);

            // Later due cycles cannot be in default if this one has not reached its default date yet.
            if ($defaultDate->gt($today)) {
                return null;
            }

            $docNo = $this->documentNumber((int) $loan->loan_id, $dueDate);

            // This due cycle has already been charged; advance to the next monthly cycle.
            if (
                DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loan->loan_id)
                ->where('loan_payments_docno', $docNo)
                ->exists()
            ) {
                // Validate that the already-posted payment has its matching
                // DR receivable / CR interest ledger legs before moving on.
                $this->checkExistingPostingIntegrity($loan, $docNo);

                $dueDate = $this->nextAnchoredMonthlyDate($dueDate, $anchorDay);
                continue;
            }

            $cashPaidByDefaultDate = $this->cashPaidForDueCycleByDate(
                $loan,
                $dueDate,
                $defaultDate
            );

            $unpaidDueAmount = round(
                max(0, $scheduledPayment - $cashPaidByDefaultDate),
                2
            );

            // A shortfall at/below threshold is treated as immaterial/cleared.
            if ($unpaidDueAmount <= $threshold) {
                $dueDate = $this->nextAnchoredMonthlyDate($dueDate, $anchorDay);
                continue;
            }

            return [
                'due_date' => $dueDate,
                'default_date' => $defaultDate,
                'scheduled_payment' => $scheduledPayment,
                'paid_by_default_date' => $cashPaidByDefaultDate,
                'unpaid_due_amount' => $unpaidDueAmount,
            ];
        }

        throw new RuntimeException(
            "DFI safety stop for loan {$loan->loan_id}: more than "
                . self::MAX_DUE_CYCLES_TO_SCAN
                . ' monthly due cycles would need to be scanned.'
        );
    }

    /**
     * Contractual monthly due date.
     *
     * Rules:
     *  - Preserve the loan_on day-of-month as the due-day anchor.
     *  - A valid loan_start_deduction_period selects the first repayment month.
     *  - If start period is missing, monthly_cut_of_day selects the cycle month.
     *  - Never produce a first due date on/before loan_on; a loan taken on the
     *    14th is therefore first due on the 14th of a later applicable month.
     */
    private function firstContractualDueDate($loan): Carbon
    {
        if (empty($loan->loan_on)) {
            throw new RuntimeException(
                "DFI data error for loan {$loan->loan_id}: loan_on is missing."
            );
        }

        try {
            $loanOn = Carbon::parse($loan->loan_on, 'Africa/Nairobi')->startOfDay();
        } catch (Throwable $e) {
            throw new RuntimeException(
                "DFI data error for loan {$loan->loan_id}: loan_on is invalid."
            );
        }

        $anchorDay = $loanOn->day;
        if ($this->isShortTermLoan($loan)) {
            return $this->nextAnchoredMonthlyDate(
                $loanOn,
                $anchorDay
            )->startOfDay();
        }

        $periodStart = null;

        if ($this->isValidPeriod($loan->loan_start_deduction_period ?? null)) {
            $periodStart = Carbon::createFromFormat(
                'Ym',
                (string) $loan->loan_start_deduction_period,
                'Africa/Nairobi'
            )->startOfMonth()->startOfDay();
        }

        if ($periodStart === null) {
            if ($this->isValidPeriod($loan->loan_taken_period ?? null)) {
                $periodStart = Carbon::createFromFormat(
                    'Ym',
                    (string) $loan->loan_taken_period,
                    'Africa/Nairobi'
                )->startOfMonth()->startOfDay();
            } else {
                $periodStart = $loanOn->copy()->startOfMonth();
            }

            if ($loanOn->day > $this->monthlyCutOffDay()) {
                $periodStart = $periodStart->copy()->addMonthNoOverflow()->startOfMonth();
            }
        }

        $candidate = $this->dateInMonthUsingAnchor($periodStart, $anchorDay);

        if ($candidate->lte($loanOn)) {
            $candidate = $this->nextAnchoredMonthlyDate($loanOn, $anchorDay);
        }

        return $candidate->startOfDay();
    }

    private function cashPaidForDueCycleByDate(
        $loan,
        Carbon $dueDate,
        Carbon $defaultDate
    ): float {
        /*
    |--------------------------------------------------------------------------
    | SHORT-TERM / ONE-MONTH LOANS
    |--------------------------------------------------------------------------
    | Use the actual repayment date, not loan_payments_period.
    |
    | Any real cash payment made from loan origination up to the end of the
    | grace period counts toward clearing the obligation.
    |--------------------------------------------------------------------------
    */
        if ($this->isShortTermLoan($loan)) {
            $loanOn = Carbon::parse(
                $loan->loan_on,
                'Africa/Nairobi'
            )->startOfDay();

            $amount = DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loan->loan_id)
                ->where(function ($query) use ($loanOn, $defaultDate) {
                    /*
                 * Preferred date: loan_payments_paid_on.
                 */
                    $query->where(function ($dated) use ($loanOn, $defaultDate) {
                        $dated
                            ->whereNotNull('loan_payments_paid_on')
                            ->whereDate(
                                'loan_payments_paid_on',
                                '>=',
                                $loanOn->toDateString()
                            )
                            ->whereDate(
                                'loan_payments_paid_on',
                                '<=',
                                $defaultDate->toDateString()
                            );
                    })

                        /*
                 * Historical fallback:
                 * if paid_on is missing, use loan_payments_on.
                 */
                        ->orWhere(function ($fallback) use ($loanOn, $defaultDate) {
                            $fallback
                                ->whereNull('loan_payments_paid_on')
                                ->whereNotNull('loan_payments_on')
                                ->whereDate(
                                    'loan_payments_on',
                                    '>=',
                                    $loanOn->toDateString()
                                )
                                ->whereDate(
                                    'loan_payments_on',
                                    '<=',
                                    $defaultDate->toDateString()
                                );
                        })

                        /*
                 * If both dates are unavailable, treat the historical
                 * repayment conservatively as having been made in time.
                 */
                        ->orWhere(function ($unknownDate) {
                            $unknownDate
                                ->whereNull('loan_payments_paid_on')
                                ->whereNull('loan_payments_on');
                        });
                })
                ->selectRaw(
                    'COALESCE(SUM('
                        . 'COALESCE(loan_payments_amount,0) '
                        . '+ COALESCE(loan_payments_interest,0)'
                        . '),0) AS cash_paid'
                )
                ->value('cash_paid');

            return round(max(0, (float) $amount), 2);
        }

        /*
    |--------------------------------------------------------------------------
    | NORMAL TERM LOANS
    |--------------------------------------------------------------------------
    | Continue using the contractual repayment period.
    |--------------------------------------------------------------------------
    */
        $period = $dueDate->format('Ym');

        $amount = DB::table('sacco_loan_payments')
            ->where('loan_payments_loan_id', $loan->loan_id)
            ->where('loan_payments_period', $period)
            ->where(function ($query) use ($defaultDate) {
                $query->whereNull('loan_payments_paid_on')
                    ->orWhereDate(
                        'loan_payments_paid_on',
                        '<=',
                        $defaultDate->toDateString()
                    );
            })
            ->selectRaw(
                'COALESCE(SUM('
                    . 'COALESCE(loan_payments_amount,0) '
                    . '+ COALESCE(loan_payments_interest,0)'
                    . '),0) AS cash_paid'
            )
            ->value('cash_paid');

        return round(max(0, (float) $amount), 2);
    }

    /**
     * Use only the two interest treatments already present in the shared code.
     * No loan_calc_method and no insurance/origination calculator is used here.
     */
    private function calculateDefaultInterest(
        $loan,
        float $outstanding,
        float $effectiveRate
    ): float {
        $interestType = strtoupper(trim((string) ($loan->loan_type_interest_type ?? '')));

        if ($interestType === 'FIXED INTEREST') {
            $scheduledPayment = round(
                (float) ($loan->loan_monthly_repayment_amount ?? 0),
                2
            );

            if ($scheduledPayment <= 0) {
                throw new RuntimeException(
                    "DFI data error for fixed-interest loan {$loan->loan_id}: monthly repayment is missing."
                );
            }

            $interest = $effectiveRate > 0
                ? $scheduledPayment
                - ($scheduledPayment * 100 / ($effectiveRate + 100))
                : 0.00;

            return round(max(0, $interest), 2);
        }

        // REDUCING BALANCE / existing non-fixed periodic treatment.
        $interest = $outstanding * $effectiveRate / 12 / 100;

        return round(max(0, $interest), 2);
    }

    private function checkExistingPostingIntegrity($loan, string $docNo): ?array
    {
        $payment = DB::table('sacco_loan_payments')
            ->where('loan_payments_loan_id', $loan->loan_id)
            ->where('loan_payments_docno', $docNo)
            ->first();

        $ledgerRows = DB::table('sacco_accounts_trans')
            ->where('accounts_trans_doc_no', $docNo)
            ->whereIn('accounts_trans_sub_account', [
                $loan->loan_type_acount,
                $loan->loan_type_int_account,
            ])
            ->get();

        if (!$payment && $ledgerRows->isNotEmpty()) {
            throw new RuntimeException(
                "DFI integrity error {$docNo}: ledger exists but sacco_loan_payments row is missing."
            );
        }

        if (!$payment) {
            return null;
        }

        $postedInterest = round((float) ($payment->loan_payments_interest ?? 0), 2);

        $receivableDebit = round((float) $ledgerRows
            ->where('accounts_trans_sub_account', $loan->loan_type_acount)
            ->sum('accounts_trans_debit'), 2);

        $interestCredit = round((float) $ledgerRows
            ->where('accounts_trans_sub_account', $loan->loan_type_int_account)
            ->sum('accounts_trans_credit'), 2);

        if (
            abs($receivableDebit - $postedInterest) > 0.01
            || abs($interestCredit - $postedInterest) > 0.01
        ) {
            throw new RuntimeException(
                "DFI integrity error {$docNo}: payment exists but balanced DFI ledger legs do not match it."
            );
        }

        return [
            'status' => 'duplicate',
            'loan_id' => (int) $loan->loan_id,
            'doc_no' => $docNo,
            'interest' => $postedInterest,
        ];
    }

    private function postLedgerEntry(
        int $subAccountId,
        float $debit,
        float $credit,
        string $docNo,
        string $description,
        string $period,
        Carbon $postedAt
    ): void {
        if ($subAccountId <= 0) {
            throw new RuntimeException("DFI ledger error {$docNo}: invalid sub-account.");
        }

        $subAccount = DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->lockForUpdate()
            ->first();

        if (!$subAccount) {
            throw new RuntimeException(
                "DFI ledger error {$docNo}: sub-account {$subAccountId} does not exist."
            );
        }

        $mainAccountId = (int) ($subAccount->sub_account_main_account ?? 0);

        if ($mainAccountId <= 0) {
            throw new RuntimeException(
                "DFI ledger error {$docNo}: sub-account {$subAccountId} has no main-account mapping."
            );
        }

        $mainAccountExists = DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccountId)
            ->lockForUpdate()
            ->exists();

        if (!$mainAccountExists) {
            throw new RuntimeException(
                "DFI ledger error {$docNo}: main account {$mainAccountId} does not exist."
            );
        }

        $row = [
            'accounts_trans_sub_account' => $subAccountId,
            'accounts_trans_period' => $period,
            'accounts_trans_debit' => round($debit, 2),
            'accounts_trans_credit' => round($credit, 2),
            'accounts_trans_doc_no' => $docNo,
            'accounts_trans_decription' => $description,
            'accounts_trans_dat_date' => $postedAt->toDateString(),
            'accounts_trans_user_id' => self::SYSTEM_USER_ID,
            'accounts_trans_ip' => self::SYSTEM_IP,
            'accounts_trans_transdate' => $postedAt,
        ];

        if (Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_source')) {
            $row['accounts_trans_source'] = self::SOURCE;
        }

        if (Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_app_name')) {
            $row['accounts_trans_app_name'] = 'iSacco';
        }

        DB::table('sacco_accounts_trans')->insert($row);

        $debitAmount = (float) round($debit, 2);
        $creditAmount = (float) round($credit, 2);

        DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->update([
                'sub_account_debit' => DB::raw(
                    'COALESCE(sub_account_debit, 0) + ' . $debitAmount
                ),
                'sub_account_credit' => DB::raw(
                    'COALESCE(sub_account_credit, 0) + ' . $creditAmount
                ),
            ]);

        DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccountId)
            ->update([
                'main_account_debit' => DB::raw(
                    'COALESCE(main_account_debit, 0) + ' . $debitAmount
                ),
                'main_account_credit' => DB::raw(
                    'COALESCE(main_account_credit, 0) + ' . $creditAmount
                ),
            ]);
    }

    private function queueNotifications(array $context): void
    {
        try {
            $borrowerSubject = 'Loan Default Interest Applied - ' . $context['loan_type_name'];

            $borrowerMessage = implode("\n", [
                'Dear ' . $context['member_name'] . ',',
                '',
                'This is to notify you that default interest has been applied to your '
                    . $context['loan_type_name']
                    . ' loan after the repayment obligation remained unpaid beyond the applicable grace period.',
                '',
                'Loan: ' . $context['loan_type_name'],
                'Loan Number: ' . $context['loan_id'],
                'Previous Outstanding Balance: KES ' . number_format($context['previous_outstanding'], 2),
                'Default Interest Rate: ' . rtrim(rtrim(number_format($context['rate'], 4), '0'), '.') . '%',
                'Default Interest Applied: KES ' . number_format($context['interest'], 2),
                'New Outstanding Balance: KES ' . number_format($context['new_outstanding'], 2),
                'Contractual Due Date: ' . $context['due_date'],
                'Default Date: ' . $context['default_date'],
                'Transaction Reference: ' . $context['doc_no'],
                '',
                'For clarification or repayment assistance, please contact the SACCO office.',
                '',
                'Regards,',
                'SACCO Management',
            ]);

            $this->insertNotification([
                'member_id' => $context['member_id'],
                'name' => $context['member_name'],
                'email' => $context['member_email'],
                'phone' => $context['member_phone_no'],
                'subject' => $borrowerSubject,
                'message' => $borrowerMessage,
                'doc_no' => $context['doc_no'],
            ]);

            $officials = DB::table('sacco_members')
                ->where('member_position', 2)
                ->where('member_active', 'Y')
                ->whereRaw("COALESCE(member_deleted, 'N') <> 'Y'")
                ->select(
                    'member_id',
                    'member_name',
                    'member_email',
                    'member_phone_no'
                )
                ->get();

            foreach ($officials as $official) {
                $officialSubject = 'Default Interest Applied - '
                    . $context['member_name']
                    . ' - '
                    . $context['loan_type_name'];

                // Deliberately minimal for data protection.
                $officialMessage = implode("\n", [
                    'Dear ' . $official->member_name . ',',
                    '',
                    'Default interest has been automatically applied to '
                        . $context['member_name']
                        . ' for the '
                        . $context['loan_type_name']
                        . ' loan.',
                    '',
                    'For full details, please log into the SACCO system.',
                    '',
                    'Regards,',
                    'SACCO System',
                ]);

                $this->insertNotification([
                    'member_id' => (int) $official->member_id,
                    'name' => (string) $official->member_name,
                    'email' => (string) ($official->member_email ?? ''),
                    'phone' => (string) ($official->member_phone_no ?? ''),
                    'subject' => $officialSubject,
                    'message' => $officialMessage,
                    'doc_no' => $context['doc_no'],
                ]);
            }
        } catch (Throwable $e) {
            Log::error('DFI notification queueing failed after successful financial posting', [
                'loan_id' => $context['loan_id'] ?? null,
                'doc_no' => $context['doc_no'] ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function insertNotification(array $data): void
    {
        $row = [
            'notif_subject' => $data['subject'],
            'notif_message' => $data['message'],
            'notif_recipient_name' => $data['name'],
            'notif_recipient_email' => $data['email'],
            'notif_recipient_phone' => $data['phone'],
            'notif_member_id' => $data['member_id'],
            'notif_type' => 'system',
            'notif_status' => 'unread',
            'notif_created_at' => now('Africa/Nairobi'),
            'notif_created_by' => self::SYSTEM_USER_ID,
            'notif_ip' => self::SYSTEM_IP,
        ];

        if (Schema::hasColumn('sacco_system_notifications', 'notif_related_doc')) {
            $row['notif_related_doc'] = $data['doc_no'];
        }

        DB::table('sacco_system_notifications')->insert($row);
    }

    private function documentNumber(int $loanId, Carbon $dueDate): string
    {
        return self::SOURCE . '-' . $loanId . '-' . $dueDate->format('Ymd');
    }

    private function description(int $loanId, Carbon $dueDate): string
    {
        return self::SOURCE . ' | L:' . $loanId . ' | DUE:' . $dueDate->format('Ymd');
    }

    private function isValidPeriod($value): bool
    {
        return is_scalar($value)
            && preg_match('/^\d{6}$/', trim((string) $value)) === 1
            && trim((string) $value) !== '000000';
    }

    private function dateInMonthUsingAnchor(Carbon $month, int $anchorDay): Carbon
    {
        $monthStart = $month->copy()->startOfMonth()->startOfDay();
        $day = min(max(1, $anchorDay), $monthStart->daysInMonth);

        return $monthStart->copy()->day($day)->startOfDay();
    }

    private function nextAnchoredMonthlyDate(Carbon $date, int $anchorDay): Carbon
    {
        $nextMonth = $date->copy()->startOfMonth()->addMonthNoOverflow()->startOfMonth();

        return $this->dateInMonthUsingAnchor($nextMonth, $anchorDay);
    }

    private function skipped(int $loanId, string $reason, array $extra = []): array
    {
        return array_merge([
            'status' => 'skipped',
            'loan_id' => $loanId,
            'reason' => $reason,
        ], $extra);
    }

    private function isShortTermLoan($loan): bool
    {
        $period = $loan->loan_payment_period ?? null;

        return is_numeric($period)
            && (int) $period === 1;
    }
}
