<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class LoanDefaultInterestService
{
    private const SYSTEM_USER_ID = 1;
    private const SYSTEM_IP = '127.0.0.1';

    private const SOURCE = 'DEFAULTED INTEREST';
    private const PAYMENT_SOURCE = 'AUTO-DFI';

    private const DEFAULT_MAX_PERIODS = 'loan_default_cut_off_max_period';
    private const DEFAULT_INTEREST_ACCOUNT = 'loan_type_default_int_account';
    private const DEFAULT_INTEREST_ACCOUNT_NAME = 'INTEREST ON DEFAULTED LOANS';

    private const MAX_DUE_CYCLES_TO_SCAN = 600;

    private ?float $thresholdAmountCache = null;
    private ?int $monthlyCutOffDayCache = null;
    private ?string $activePeriodCache = null;
    private ?int $maxDefaultPeriodsCache = null;
    private ?int $defaultInterestAccountIdCache = null;

    /**
     * Process at most ONE defaulted-interest cycle for one loan.
     *
     * Global rules:
     * - loan_default_cut_off_max_period = 0 means DFI is globally OFF.
     * - A positive value is the maximum number of AUTO-DFI cycles allowed
     *   for a loan.
     * - One invocation posts at most one new cycle for a loan.
     */
    public function processLoan(int $loanId): array
    {
        if ($this->maxDefaultPeriods() <= 0) {
            return $this->skipped(
                $loanId,
                'global_default_interest_disabled'
            );
        }

        $notificationContext = null;

        $result = DB::transaction(
            function () use (
                $loanId,
                &$notificationContext
            ) {
                $loan = DB::table('sacco_loans as l')
                    ->join(
                        'sacco_loan_types as t',
                        'l.loan_loan_type',
                        '=',
                        't.loan_type_id'
                    )
                    ->where(
                        'l.loan_id',
                        $loanId
                    )
                    ->select(
                        'l.loan_id',
                        'l.loan_member',
                        'l.loan_loan_type',
                        'l.loan_amount',
                        'l.loan_loan_paid',
                        'l.loan_taken_period',
                        'l.loan_payment_period',
                        'l.loan_start_deduction_period',
                        'l.loan_taken_start_period',
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
                        't.loan_type_duration',
                        't.loan_type_acount',
                        't.loan_type_int_account'
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$loan) {
                    return $this->skipped(
                        $loanId,
                        'loan_not_found'
                    );
                }

                if (
                    strtoupper(
                        trim(
                            (string) (
                                $loan->loan_stoped
                                ?? 'N'
                            )
                        )
                    ) === 'Y'
                ) {
                    return $this->skipped(
                        $loanId,
                        'loan_stopped'
                    );
                }

                if (
                    !$this->isAutoDefaultInterestEnabled(
                        $loan
                            ->loan_type_auto_interest_on_period_change
                        ?? null
                    )
                ) {
                    return $this->skipped(
                        $loanId,
                        'auto_interest_disabled'
                    );
                }

                $threshold =
                    $this->thresholdAmount();

                $previousOutstanding =
                    $this->outstanding(
                        $loan
                    );

                if (
                    $previousOutstanding
                    <= $threshold
                ) {
                    return $this->skipped(
                        $loanId,
                        'below_threshold',
                        [
                            'outstanding' =>
                                $previousOutstanding,

                            'threshold' =>
                                $threshold,
                        ]
                    );
                }

                if (
                    empty(
                        $loan->loan_type_acount
                    )
                ) {
                    throw new RuntimeException(
                        "DFI configuration error for loan {$loanId}: "
                        . 'loan_type_acount is missing.'
                    );
                }

                $postedCycleCount =
                    $this
                        ->countPostedDefaultCycles(
                            $loanId
                        );

                $maxDefaultPeriods =
                    $this
                        ->maxDefaultPeriods();

                if (
                    $postedCycleCount
                    >= $maxDefaultPeriods
                ) {
                    return $this->skipped(
                        $loanId,
                        'max_default_periods_reached',
                        [
                            'posted_cycles' =>
                                $postedCycleCount,

                            'max_cycles' =>
                                $maxDefaultPeriods,
                        ]
                    );
                }

                $member = DB::table(
                    'sacco_members'
                )
                    ->where(
                        'member_id',
                        $loan->loan_member
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$member) {
                    throw new RuntimeException(
                        "DFI data error for loan {$loanId}: "
                        . "member {$loan->loan_member} "
                        . 'was not found.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Resolve dedicated Defaulted Interest account
                |--------------------------------------------------------------------------
                |
                | The migration provisions:
                |
                | sacco_defaults.default_name
                | = loan_type_default_int_account
                |
                | sacco_defaults.default_value
                | = sacco_sub_account.sub_account_id
                |
                | Runtime DOES NOT automatically create/redirect accounts.
                |--------------------------------------------------------------------------
                */

                $defaultInterestAccountId =
                    $this
                        ->defaultInterestAccountId();

                /*
                |--------------------------------------------------------------------------
                | Find one eligible default cycle
                |--------------------------------------------------------------------------
                */

                $defaultable =
                    $this
                        ->findNextDefaultableDueCycle(
                            $loan,
                            $threshold,
                            $defaultInterestAccountId
                        );

                if (
                    $defaultable === null
                ) {
                    return $this->skipped(
                        $loanId,
                        'not_in_default'
                    );
                }

                /** @var Carbon $dueDate */
                $dueDate =
                    $defaultable[
                        'due_date'
                    ];

                /** @var Carbon $defaultDate */
                $defaultDate =
                    $defaultable[
                        'default_date'
                    ];

                $docNo =
                    $this
                        ->documentNumber(
                            $loan,
                            $dueDate
                        );

                $description =
                    $this
                        ->description(
                            $loan,
                            $member,
                            $dueDate
                        );

                /*
                |--------------------------------------------------------------------------
                | Final idempotency check
                |--------------------------------------------------------------------------
                */

                $existingPosting =
                    $this
                        ->existingPostingForCycle(
                            $loan,
                            $dueDate,
                            $defaultInterestAccountId
                        );

                if (
                    $existingPosting
                    !== null
                ) {
                    return $existingPosting;
                }

                /*
                |--------------------------------------------------------------------------
                | Effective defaulted-interest rate
                |--------------------------------------------------------------------------
                |
                | Preserve existing product semantics:
                |
                | loan_type_default_interest
                |
                | If NULL:
                |
                | loan_type_interest
                |--------------------------------------------------------------------------
                */

                $effectiveRate =
                    $loan
                        ->loan_type_default_interest
                    !== null

                    ? (float)
                        $loan
                            ->loan_type_default_interest

                    : (float) (
                        $loan
                            ->loan_type_interest
                        ?? 0
                    );

                if (
                    $effectiveRate <= 0
                ) {
                    return $this->skipped(
                        $loanId,
                        'zero_default_interest_rate',
                        [
                            'due_date' =>
                                $dueDate
                                    ->toDateString(),
                        ]
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | LOCKED DFI calculation
                |--------------------------------------------------------------------------
                */

                $interest =
                    $this
                        ->calculateDefaultInterest(
                            $loan,
                            $previousOutstanding,
                            $effectiveRate
                        );

                if (
                    $interest <= 0
                ) {
                    return $this->skipped(
                        $loanId,
                        'calculated_interest_zero',
                        [
                            'due_date' =>
                                $dueDate
                                    ->toDateString(),
                        ]
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Posting context
                |--------------------------------------------------------------------------
                */

                $postedAt =
                    now(
                        'Africa/Nairobi'
                    );

                /*
                 * Fail closed if there is no valid ACTIVE
                 * SACCO accounting period.
                 */
                $postingPeriod =
                    $this
                        ->activePeriod();

                $duePeriod =
                    $dueDate
                        ->format('Ym');

                /*
                |--------------------------------------------------------------------------
                | sacco_loan_payments
                |--------------------------------------------------------------------------
                |
                | This is NOT a genuine member cash repayment.
                |
                | loan_payments_amount:
                |     negative value capitalises DFI into the loan.
                |
                | loan_payments_interest:
                |     positive amount identifies defaulted interest.
                |
                | Net cash effect:
                |     zero.
                |--------------------------------------------------------------------------
                */

                DB::table(
                    'sacco_loan_payments'
                )
                    ->insert([
                        'loan_payments_amount' =>
                            0 - $interest,

                        'loan_payments_interest' =>
                            $interest,

                        'loan_payments_description' =>
                            $description,

                        'loan_payments_docno' =>
                            $docNo,

                        'loan_payments_paid_in_by' =>
                            self::PAYMENT_SOURCE,

                        'loan_payments_period' =>
                            $duePeriod,

                        'loan_payments_paid_on' =>
                            $postedAt
                                ->toDateString(),

                        'loan_payments_loan_id' =>
                            $loanId,

                        'loan_end_month_proc' =>
                            'N',

                        'loan_payments_by' =>
                            self::SYSTEM_USER_ID,

                        'loan_payments_ip' =>
                            self::SYSTEM_IP,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Loan/member balances
                |--------------------------------------------------------------------------
                |
                | Outstanding:
                |
                | loan_amount - loan_loan_paid
                |
                | Therefore:
                |
                | loan_loan_paid -= interest
                |
                | increases the amount outstanding.
                |--------------------------------------------------------------------------
                */

                $interestSql =
                    number_format(
                        $interest,
                        2,
                        '.',
                        ''
                    );

                DB::table(
                    'sacco_loans'
                )
                    ->where(
                        'loan_id',
                        $loanId
                    )
                    ->update([
                        'loan_loan_paid' =>
                            DB::raw(
                                'COALESCE('
                                . 'loan_loan_paid, 0'
                                . ') - '
                                . $interestSql
                            ),
                    ]);

                DB::table(
                    'sacco_members'
                )
                    ->where(
                        'member_id',
                        $loan
                            ->loan_member
                    )
                    ->update([
                        'member_total_loan' =>
                            DB::raw(
                                'COALESCE('
                                . 'member_total_loan, 0'
                                . ') + '
                                . $interestSql
                            ),
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Ledger
                |--------------------------------------------------------------------------
                |
                | DR:
                |
                | sacco_loan_types.loan_type_acount
                |
                |     Loan receivable increases.
                |
                | CR:
                |
                | sacco_defaults.loan_type_default_int_account
                |
                |     INTEREST ON DEFAULTED LOANS income increases.
                |
                | IMPORTANT:
                |
                | Normal loan_type_int_account is deliberately NOT
                | credited by new DFI postings.
                |--------------------------------------------------------------------------
                */

                $this
                    ->postLedgerEntry(
                        (int)
                            $loan
                                ->loan_type_acount,

                        $interest,

                        0.00,

                        $docNo,

                        $description,

                        $postingPeriod,

                        $postedAt,

                        (int)
                            $loan
                                ->loan_member
                    );

                $this
                    ->postLedgerEntry(
                        $defaultInterestAccountId,

                        0.00,

                        $interest,

                        $docNo,

                        $description,

                        $postingPeriod,

                        $postedAt,

                        (int)
                            $loan
                                ->loan_member
                    );

                $newOutstanding =
                    round(
                        $previousOutstanding
                        + $interest,
                        2
                    );

                /*
                |--------------------------------------------------------------------------
                | Notification context
                |--------------------------------------------------------------------------
                */

                $notificationContext = [
                    'loan_id' =>
                        (int) $loanId,

                    'loan_type_name' =>
                        (string) (
                            $loan
                                ->loan_type_name
                            ?? 'Loan'
                        ),

                    'member_id' =>
                        (int)
                            $loan
                                ->loan_member,

                    'member_name' =>
                        (string) (
                            $member
                                ->member_name
                            ?? ''
                        ),

                    'member_sacco_id' =>
                        (string) (
                            $member
                                ->member_sacco_id
                            ?? ''
                        ),

                    'member_email' =>
                        (string) (
                            $member
                                ->member_email
                            ?? ''
                        ),

                    'member_phone_no' =>
                        (string) (
                            $member
                                ->member_phone_no
                            ?? ''
                        ),

                    'previous_outstanding' =>
                        $previousOutstanding,

                    'interest' =>
                        $interest,

                    'new_outstanding' =>
                        $newOutstanding,

                    'rate' =>
                        $effectiveRate,

                    'interest_type' =>
                        strtoupper(
                            trim(
                                (string) (
                                    $loan
                                        ->loan_type_interest_type
                                    ?? ''
                                )
                            )
                        ),

                    'due_date' =>
                        $dueDate
                            ->toDateString(),

                    'due_period' =>
                        $duePeriod,

                    'default_date' =>
                        $defaultDate
                            ->toDateString(),

                    'posted_at' =>
                        $postedAt
                            ->format(
                                'Y-m-d H:i:s'
                            ),

                    'doc_no' =>
                        $docNo,

                    'posted_cycle' =>
                        $postedCycleCount + 1,

                    'max_cycles' =>
                        $maxDefaultPeriods,
                ];

                return [
                    'status' =>
                        'posted',

                    'loan_id' =>
                        (int) $loanId,

                    'doc_no' =>
                        $docNo,

                    'description' =>
                        $description,

                    'due_date' =>
                        $dueDate
                            ->toDateString(),

                    'due_period' =>
                        $duePeriod,

                    'default_date' =>
                        $defaultDate
                            ->toDateString(),

                    'rate' =>
                        $effectiveRate,

                    'interest' =>
                        $interest,

                    'previous_outstanding' =>
                        $previousOutstanding,

                    'new_outstanding' =>
                        $newOutstanding,

                    'posted_cycle' =>
                        $postedCycleCount + 1,

                    'max_cycles' =>
                        $maxDefaultPeriods,
                ];
            },
            3
        );

        /*
        |--------------------------------------------------------------------------
        | Notifications happen AFTER financial commit
        |--------------------------------------------------------------------------
        |
        | Notification/outbox failure must never reverse a
        | successful financial transaction.
        |--------------------------------------------------------------------------
        */

        if (
            (
                $result['status']
                ?? null
            ) === 'posted'
            && $notificationContext !== null
        ) {
            $this
                ->queueNotifications(
                    $notificationContext
                );
        }

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Global configuration
    |--------------------------------------------------------------------------
    */

    /**
     * Global master switch and maximum automatic DFI cycles.
     *
     * 0:
     * Automatic DFI globally OFF.
     *
     * Positive integer:
     * Maximum permitted automatic DFI cycles per loan.
     *
     * Missing/invalid/negative configuration fails closed to zero.
     */
    public function maxDefaultPeriods(): int
    {
        if (
            $this
                ->maxDefaultPeriodsCache
            !== null
        ) {
            return $this
                ->maxDefaultPeriodsCache;
        }

        $value =
            DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    self::DEFAULT_MAX_PERIODS
                )
                ->value(
                    'default_value'
                );

        $this
            ->maxDefaultPeriodsCache =
                is_numeric($value)
                    ? max(
                        0,
                        (int) $value
                    )
                    : 0;

        return $this
            ->maxDefaultPeriodsCache;
    }


    public function thresholdAmount(): float
    {
        if (
            $this
                ->thresholdAmountCache
            !== null
        ) {
            return $this
                ->thresholdAmountCache;
        }

        $value =
            DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    'threshold_amount'
                )
                ->value(
                    'default_value'
                );

        $this
            ->thresholdAmountCache =
                is_numeric($value)
                    ? max(
                        0,
                        (float) $value
                    )
                    : 1.00;

        return $this
            ->thresholdAmountCache;
    }


    private function monthlyCutOffDay(): int
    {
        if (
            $this
                ->monthlyCutOffDayCache
            !== null
        ) {
            return $this
                ->monthlyCutOffDayCache;
        }

        $value =
            DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    'monthly_cut_of_day'
                )
                ->value(
                    'default_value'
                );

        $day =
            is_numeric($value)
                ? (int) $value
                : 28;

        $this
            ->monthlyCutOffDayCache =
                max(
                    1,
                    min(
                        $day,
                        31
                    )
                );

        return $this
            ->monthlyCutOffDayCache;
    }


    /**
     * Active accounting period.
     *
     * IMPORTANT:
     *
     * DFI is an accounting transaction.
     *
     * It must never silently post to today's calendar YYYYMM
     * when the SACCO does not have a valid active accounting period.
     */
    private function activePeriod(): string
    {
        if (
            $this
                ->activePeriodCache
            !== null
        ) {
            return $this
                ->activePeriodCache;
        }

        $period =
            DB::table(
                'sacco_period'
            )
                ->where(
                    'period_active',
                    'Y'
                )
                ->whereRaw(
                    "COALESCE("
                    . "period_deleted, 'N'"
                    . ") <> 'Y'"
                )
                ->value(
                    'period_name'
                );

        /*
        |--------------------------------------------------------------------------
        | Fail closed
        |--------------------------------------------------------------------------
        */

        if (
            !$this
                ->isValidPeriod(
                    $period
                )
        ) {
            throw new RuntimeException(
                'DFI configuration error: '
                . 'no valid active SACCO accounting period was found.'
            );
        }

        $this
            ->activePeriodCache =
                trim(
                    (string) $period
                );

        return $this
            ->activePeriodCache;
    }


    /**
     * Dedicated INTEREST ON DEFAULTED LOANS GL account.
     */
    private function defaultInterestAccountId(): int
    {
        if (
            $this
                ->defaultInterestAccountIdCache
            !== null
        ) {
            return $this
                ->defaultInterestAccountIdCache;
        }

        $value =
            DB::table(
                'sacco_defaults'
            )
                ->where(
                    'default_name',
                    self::DEFAULT_INTEREST_ACCOUNT
                )
                ->value(
                    'default_value'
                );

        if (
            !is_numeric($value)
            || (int) $value <= 0
        ) {
            throw new RuntimeException(
                'DFI configuration error: '
                . 'sacco_defaults.loan_type_default_int_account '
                . 'is missing or invalid.'
            );
        }

        $account =
            DB::table(
                'sacco_sub_account as s'
            )
                ->join(
                    'sacco_main_account as m',
                    's.sub_account_main_account',
                    '=',
                    'm.main_account_id'
                )
                ->where(
                    's.sub_account_id',
                    (int) $value
                )
                ->whereRaw(
                    "COALESCE("
                    . "s.sub_account_deleted, 'N'"
                    . ") <> 'Y'"
                )
                ->whereRaw(
                    "COALESCE("
                    . "m.main_account_deleted, 'N'"
                    . ") <> 'Y'"
                )
                ->select(
                    's.sub_account_id',
                    's.sub_account_name',
                    'm.main_account_code',
                    'm.main_account_type'
                )
                ->first();

        if (!$account) {
            throw new RuntimeException(
                'DFI configuration error: '
                . 'configured defaulted-interest sub-account '
                . 'does not exist or is deleted.'
            );
        }

        if (
            strtoupper(
                trim(
                    (string)
                        $account
                            ->sub_account_name
                )
            )
            !== self::DEFAULT_INTEREST_ACCOUNT_NAME
        ) {
            throw new RuntimeException(
                'DFI configuration error: '
                . 'configured account must be named '
                . self::DEFAULT_INTEREST_ACCOUNT_NAME
                . '.'
            );
        }

        if (
            strtoupper(
                trim(
                    (string)
                        $account
                            ->main_account_type
                )
            ) !== 'INCOME'

            ||

            strtoupper(
                substr(
                    trim(
                        (string)
                            $account
                                ->main_account_code
                    ),
                    0,
                    1
                )
            ) !== 'I'
        ) {
            throw new RuntimeException(
                'DFI configuration error: '
                . self::DEFAULT_INTEREST_ACCOUNT_NAME
                . ' must belong to an INCOME (Ixxx) main account.'
            );
        }

        $this
            ->defaultInterestAccountIdCache =
                (int)
                    $account
                        ->sub_account_id;

        return $this
            ->defaultInterestAccountIdCache;
    }


    private function isAutoDefaultInterestEnabled(
        $value
    ): bool {
        if (
            is_numeric($value)
            && (int) $value === 1
        ) {
            return true;
        }

        return in_array(
            strtoupper(
                trim(
                    (string) $value
                )
            ),
            [
                'Y',
                'YES',
                'TRUE',
            ],
            true
        );
    }


    private function outstanding(
        $loan
    ): float {
        return round(
            (float) (
                $loan
                    ->loan_amount
                ?? 0
            )
            -
            (float) (
                $loan
                    ->loan_loan_paid
                ?? 0
            ),
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Default cycle evaluation
    |--------------------------------------------------------------------------
    */

    /**
     * LOCKED branch:
     *
     * loan_type_duration <= 1:
     * Use actual payment dates / rolling month.
     *
     * loan_type_duration > 1:
     * Use contractual loan_payments_period YYYYMM.
     */
    private function findNextDefaultableDueCycle(
        $loan,
        float $threshold,
        int $defaultInterestAccountId
    ): ?array {
        if (
            $this
                ->isShortTermLoan(
                    $loan
                )
        ) {
            return $this
                ->findNextShortTermDefaultableCycle(
                    $loan,
                    $defaultInterestAccountId
                );
        }

        return $this
            ->findNextLongTermDefaultableCycle(
                $loan,
                $threshold,
                $defaultInterestAccountId
            );
    }


    /**
     * SHORT TERM / ONE-MONTH PRODUCT
     *
     * The clock is date driven.
     *
     * A genuine repayment made before the current cycle's default
     * date resets the one-month clock from that real payment date.
     *
     * A payment made after default has already crystallised does
     * not erase that historical default cycle.
     */
    private function findNextShortTermDefaultableCycle(
        $loan,
        int $defaultInterestAccountId
    ): ?array {
        $loanOn =
            $this
                ->loanOnDate(
                    $loan
                );

        $graceDays =
            max(
                0,
                (int) (
                    $loan
                        ->loan_type_grace_days_after_due
                    ?? 45
                )
            );

        $today =
            now(
                'Africa/Nairobi'
            )
                ->startOfDay();

        $cycleBase =
            $loanOn
                ->copy();

        for (
            $i = 0;
            $i < self::MAX_DUE_CYCLES_TO_SCAN;
            $i++
        ) {
            $dueDate =
                $this
                    ->nextAnchoredMonthlyDate(
                        $cycleBase,
                        $cycleBase
                            ->day
                    );

            $defaultDate =
                $dueDate
                    ->copy()
                    ->addDays(
                        $graceDays
                    );

            if (
                $defaultDate
                    ->gt(
                        $today
                    )
            ) {
                return null;
            }

            /*
             * Already charged?
             */
            $existing =
                $this
                    ->existingPostingForCycle(
                        $loan,
                        $dueDate,
                        $defaultInterestAccountId
                    );

            if (
                $existing !== null
            ) {
                /*
                 * Move to the next monthly DFI cycle.
                 */
                $cycleBase =
                    $dueDate
                        ->copy();

                continue;
            }

            /*
             * Genuine member repayment inside this date window?
             */
            $lastRealPaymentInWindow =
                $this
                    ->latestGenuinePaymentDateBetween(
                        $loan,
                        $cycleBase,
                        $defaultDate
                    );

            if (
                $lastRealPaymentInWindow
                !== null
            ) {
                /*
                 * Reset the rolling clock from actual repayment date.
                 */
                $cycleBase =
                    $lastRealPaymentInWindow
                        ->copy();

                continue;
            }

            return [
                'due_date' =>
                    $dueDate,

                'default_date' =>
                    $defaultDate,

                'evaluation_mode' =>
                    'DATE',
            ];
        }

        throw new RuntimeException(
            "DFI safety stop for short-term loan "
            . "{$loan->loan_id}: more than "
            . self::MAX_DUE_CYCLES_TO_SCAN
            . ' date cycles would need to be scanned.'
        );
    }


    /**
     * LONG TERM / MULTI-MONTH PRODUCT
     *
     * Contractual YYYYMM periods are authoritative.
     */
    private function findNextLongTermDefaultableCycle(
        $loan,
        float $threshold,
        int $defaultInterestAccountId
    ): ?array {
        $graceDays =
            max(
                0,
                (int) (
                    $loan
                        ->loan_type_grace_days_after_due
                    ?? 45
                )
            );

        $today =
            now(
                'Africa/Nairobi'
            )
                ->startOfDay();

        $scheduledPayment =
            round(
                (float) (
                    $loan
                        ->loan_monthly_repayment_amount
                    ?? 0
                ),
                2
            );

        if (
            $scheduledPayment <= 0
        ) {
            throw new RuntimeException(
                "DFI data error for loan "
                . "{$loan->loan_id}: "
                . 'loan_monthly_repayment_amount '
                . 'is zero or missing.'
            );
        }

        $dueMonth =
            $this
                ->firstLongTermDueMonth(
                    $loan
                );

        for (
            $i = 0;
            $i < self::MAX_DUE_CYCLES_TO_SCAN;
            $i++
        ) {
            $dueDate =
                $dueMonth
                    ->copy()
                    ->endOfMonth()
                    ->startOfDay();

            $defaultDate =
                $dueDate
                    ->copy()
                    ->addDays(
                        $graceDays
                    );

            if (
                $defaultDate
                    ->gt(
                        $today
                    )
            ) {
                return null;
            }

            /*
             * Existing new/legacy DFI posting?
             */
            $existing =
                $this
                    ->existingPostingForCycle(
                        $loan,
                        $dueDate,
                        $defaultInterestAccountId
                    );

            if (
                $existing !== null
            ) {
                $dueMonth =
                    $dueMonth
                        ->copy()
                        ->addMonthNoOverflow()
                        ->startOfMonth();

                continue;
            }

            $duePeriod =
                $dueMonth
                    ->format(
                        'Ym'
                    );

            $cashPaidByDefaultDate =
                $this
                    ->cashPaidForLongTermPeriodByDate(
                        $loan,
                        $duePeriod,
                        $defaultDate
                    );

            $unpaidDueAmount =
                round(
                    max(
                        0,
                        $scheduledPayment
                        - $cashPaidByDefaultDate
                    ),
                    2
                );

            if (
                $unpaidDueAmount
                <= $threshold
            ) {
                $dueMonth =
                    $dueMonth
                        ->copy()
                        ->addMonthNoOverflow()
                        ->startOfMonth();

                continue;
            }

            return [
                'due_date' =>
                    $dueDate,

                'default_date' =>
                    $defaultDate,

                'due_period' =>
                    $duePeriod,

                'scheduled_payment' =>
                    $scheduledPayment,

                'paid_by_default_date' =>
                    $cashPaidByDefaultDate,

                'unpaid_due_amount' =>
                    $unpaidDueAmount,

                'evaluation_mode' =>
                    'PERIOD',
            ];
        }

        throw new RuntimeException(
            "DFI safety stop for loan "
            . "{$loan->loan_id}: more than "
            . self::MAX_DUE_CYCLES_TO_SCAN
            . ' monthly periods would need to be scanned.'
        );
    }


    /**
     * First contractual period for multi-month loans.
     *
     * Priority:
     *
     * 1. loan_start_deduction_period
     * 2. loan_taken_period
     * 3. loan_on month
     *
     * If start period is not explicit and loan_on falls after
     * monthly_cut_of_day, begin in the following month.
     *
     * Long-term due date is the end of the contractual period.
     */
    private function firstLongTermDueMonth(
        $loan
    ): Carbon {
        $loanOn =
            $this
                ->loanOnDate(
                    $loan
                );

        $explicitStart =
            false;

        if (
            $this
                ->isValidPeriod(
                    $loan
                        ->loan_start_deduction_period
                    ?? null
                )
        ) {
            $dueMonth =
                $this
                    ->periodToMonth(
                        (string)
                            $loan
                                ->loan_start_deduction_period
                    );

            $explicitStart =
                true;
        } elseif (
            $this
                ->isValidPeriod(
                    $loan
                        ->loan_taken_period
                    ?? null
                )
        ) {
            $dueMonth =
                $this
                    ->periodToMonth(
                        (string)
                            $loan
                                ->loan_taken_period
                    );
        } else {
            $dueMonth =
                $loanOn
                    ->copy()
                    ->startOfMonth();
        }

        if (
            !$explicitStart
            &&
            $loanOn->day
                > $this
                    ->monthlyCutOffDay()
        ) {
            $dueMonth =
                $dueMonth
                    ->copy()
                    ->addMonthNoOverflow()
                    ->startOfMonth();
        }

        /*
         * Never create a long-term due date on/before origination.
         */
        while (
            $dueMonth
                ->copy()
                ->endOfMonth()
                ->startOfDay()
                ->lte(
                    $loanOn
                )
        ) {
            $dueMonth =
                $dueMonth
                    ->copy()
                    ->addMonthNoOverflow()
                    ->startOfMonth();
        }

        return $dueMonth
            ->startOfMonth();
    }


    private function loanOnDate(
        $loan
    ): Carbon {
        if (
            empty(
                $loan
                    ->loan_on
            )
        ) {
            throw new RuntimeException(
                "DFI data error for loan "
                . "{$loan->loan_id}: "
                . 'loan_on is missing.'
            );
        }

        try {
            return Carbon::parse(
                $loan
                    ->loan_on,
                'Africa/Nairobi'
            )
                ->startOfDay();
        } catch (
            Throwable $e
        ) {
            throw new RuntimeException(
                "DFI data error for loan "
                . "{$loan->loan_id}: "
                . 'loan_on is invalid.'
            );
        }
    }


    /**
     * Latest genuine repayment date strictly after $afterDate
     * and on/before $throughDate.
     *
     * AUTO-DFI/system accruals are excluded.
     */
    private function latestGenuinePaymentDateBetween(
        $loan,
        Carbon $afterDate,
        Carbon $throughDate
    ): ?Carbon {
        $row =
            $this
                ->genuineRepaymentQuery(
                    (int)
                        $loan
                            ->loan_id
                )
                ->whereRaw(
                    'DATE(COALESCE('
                    . 'loan_payments_paid_on, '
                    . 'loan_payments_on'
                    . ')) > ?',
                    [
                        $afterDate
                            ->toDateString()
                    ]
                )
                ->whereRaw(
                    'DATE(COALESCE('
                    . 'loan_payments_paid_on, '
                    . 'loan_payments_on'
                    . ')) <= ?',
                    [
                        $throughDate
                            ->toDateString()
                    ]
                )
                ->orderByRaw(
                    'DATE(COALESCE('
                    . 'loan_payments_paid_on, '
                    . 'loan_payments_on'
                    . ')) DESC'
                )
                ->orderByDesc(
                    'loan_payments_id'
                )
                ->selectRaw(
                    'DATE(COALESCE('
                    . 'loan_payments_paid_on, '
                    . 'loan_payments_on'
                    . ')) '
                    . 'as effective_payment_date'
                )
                ->first();

        if (
            !$row
            ||
            empty(
                $row
                    ->effective_payment_date
            )
        ) {
            return null;
        }

        return Carbon::parse(
            $row
                ->effective_payment_date,
            'Africa/Nairobi'
        )
            ->startOfDay();
    }


    /**
     * Sum genuine cash repayments for one contractual YYYYMM
     * period received by the end of that period's grace date.
     */
    private function cashPaidForLongTermPeriodByDate(
        $loan,
        string $period,
        Carbon $defaultDate
    ): float {
        $amount =
            $this
                ->genuineRepaymentQuery(
                    (int)
                        $loan
                            ->loan_id
                )
                ->where(
                    'loan_payments_period',
                    (int) $period
                )
                ->whereRaw(
                    'DATE(COALESCE('
                    . 'loan_payments_paid_on, '
                    . 'loan_payments_on'
                    . ')) <= ?',
                    [
                        $defaultDate
                            ->toDateString()
                    ]
                )
                ->selectRaw(
                    'COALESCE(SUM('
                    . 'COALESCE('
                    . 'loan_payments_amount,0'
                    . ') '
                    . '+ '
                    . 'COALESCE('
                    . 'loan_payments_interest,0'
                    . ')'
                    . '),0) '
                    . 'AS cash_paid'
                )
                ->value(
                    'cash_paid'
                );

        return round(
            max(
                0,
                (float) $amount
            ),
            2
        );
    }


    /**
     * Genuine member repayments only.
     *
     * DFI/system-generated accruals MUST NOT:
     *
     * - reset short-term repayment dates;
     * - satisfy long-term contractual repayment periods.
     */
    private function genuineRepaymentQuery(
        int $loanId
    ): Builder {
        return DB::table(
            'sacco_loan_payments'
        )
            ->where(
                'loan_payments_loan_id',
                $loanId
            )
            ->whereRaw(
                '('
                . 'COALESCE('
                . 'loan_payments_amount,0'
                . ') '
                . '+ '
                . 'COALESCE('
                . 'loan_payments_interest,0'
                . ')'
                . ') > 0'
            )
            ->whereRaw(
                "UPPER(TRIM(COALESCE("
                . "loan_payments_paid_in_by, ''"
                . "))) NOT IN (?, ?)",
                [
                    self::PAYMENT_SOURCE,
                    'SYSTEM',
                ]
            )
            ->where(
                function ($query) {
                    $query
                        ->whereNull(
                            'loan_payments_docno'
                        )
                        ->orWhere(
                            'loan_payments_docno',
                            'NOT LIKE',
                            'DFI-%'
                        );
                }
            );
    }


    /*
    |--------------------------------------------------------------------------
    | DFI calculation
    |--------------------------------------------------------------------------
    */

    /**
     * LOCKED formula.
     *
     * FIXED INTEREST:
     *
     * Outstanding × Rate ÷ 100
     *
     * REDUCING / NON-FIXED:
     *
     * Outstanding × Rate ÷ 12 ÷ 100
     *
     * Normal repayment splitting is deliberately NOT used here.
     */
    private function calculateDefaultInterest(
        $loan,
        float $outstanding,
        float $effectiveRate
    ): float {
        $interestType =
            strtoupper(
                trim(
                    (string) (
                        $loan
                            ->loan_type_interest_type
                        ?? ''
                    )
                )
            );

        if (
            $interestType
            === 'FIXED INTEREST'
        ) {
            $interest =
                $outstanding
                * $effectiveRate
                / 100;

            return round(
                max(
                    0,
                    $interest
                ),
                2
            );
        }

        $interest =
            $outstanding
            * $effectiveRate
            / 12
            / 100;

        return round(
            max(
                0,
                $interest
            ),
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DFI cycle count / idempotency
    |--------------------------------------------------------------------------
    */

    private function countPostedDefaultCycles(
        int $loanId
    ): int {
        $newPrefix =
            'DFI-L'
            . $loanId
            . '-%';

        $legacyPrefix =
            'DFI-'
            . $loanId
            . '-%';

        return (int)
            DB::table(
                'sacco_loan_payments'
            )
                ->where(
                    'loan_payments_loan_id',
                    $loanId
                )
                ->where(
                    function ($query) use (
                        $newPrefix,
                        $legacyPrefix
                    ) {
                        $query
                            ->whereRaw(
                                "UPPER(TRIM(COALESCE("
                                . "loan_payments_paid_in_by, ''"
                                . "))) = ?",
                                [
                                    self::PAYMENT_SOURCE
                                ]
                            )
                            ->orWhere(
                                'loan_payments_docno',
                                'LIKE',
                                $newPrefix
                            )
                            ->orWhere(
                                'loan_payments_docno',
                                'LIKE',
                                $legacyPrefix
                            );
                    }
                )
                ->where(
                    'loan_payments_interest',
                    '>',
                    0
                )
                ->distinct()
                ->count(
                    'loan_payments_docno'
                );
    }


    /**
     * Detect an existing DFI posting for the exact contractual cycle.
     *
     * New postings:
     *
     * Short-term:
     * DFI-L{loan_id}-D{YYYYMMDD}
     *
     * Long-term:
     * DFI-L{loan_id}-P{YYYYMM}
     *
     * Legacy postings:
     *
     * DFI-{loan_id}-{YYYYMMDD}
     *
     * IMPORTANT LEGACY RULE
     * ---------------------
     *
     * The old long-term implementation used an anchored day-of-month
     * inside its document number.
     *
     * The new long-term implementation correctly treats the
     * contractual YYYYMM repayment period as authoritative and uses
     * end-of-month as the due date.
     *
     * Therefore:
     *
     * DO NOT reconstruct legacy long-term doc numbers from the new
     * month-end due date.
     *
     * Match old long-term DFI using:
     *
     * loan_id + loan_payments_period
     *
     * and then validate the actual stored legacy document.
     */
    private function existingPostingForCycle(
        $loan,
        Carbon $dueDate,
        int $defaultInterestAccountId
    ): ?array {
        /*
        |--------------------------------------------------------------------------
        | 1. New deterministic DFI document
        |--------------------------------------------------------------------------
        */

        $newDocNo =
            $this
                ->documentNumber(
                    $loan,
                    $dueDate
                );

        $newPaymentExists =
            DB::table(
                'sacco_loan_payments'
            )
                ->where(
                    'loan_payments_loan_id',
                    $loan
                        ->loan_id
                )
                ->where(
                    'loan_payments_docno',
                    $newDocNo
                )
                ->exists();

        $newLedgerExists =
            DB::table(
                'sacco_accounts_trans'
            )
                ->where(
                    'accounts_trans_doc_no',
                    $newDocNo
                )
                ->exists();

        if (
            $newPaymentExists
            ||
            $newLedgerExists
        ) {
            return $this
                ->checkExistingPostingIntegrity(
                    $loan,
                    $newDocNo,
                    $defaultInterestAccountId,
                    false
                );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Legacy short-term/date-driven DFI
        |--------------------------------------------------------------------------
        |
        | For one-month/date-driven loans the contractual due date itself
        | remains the cycle identity.
        |--------------------------------------------------------------------------
        */

        if (
            $this
                ->isShortTermLoan(
                    $loan
                )
        ) {
            $legacyDocNo =
                'DFI-'
                . (int)
                    $loan
                        ->loan_id
                . '-'
                . $dueDate
                    ->format(
                        'Ymd'
                    );

            $legacyPaymentExists =
                DB::table(
                    'sacco_loan_payments'
                )
                    ->where(
                        'loan_payments_loan_id',
                        $loan
                            ->loan_id
                    )
                    ->where(
                        'loan_payments_docno',
                        $legacyDocNo
                    )
                    ->exists();

            $legacyLedgerExists =
                DB::table(
                    'sacco_accounts_trans'
                )
                    ->where(
                        'accounts_trans_doc_no',
                        $legacyDocNo
                    )
                    ->exists();

            if (
                $legacyPaymentExists
                ||
                $legacyLedgerExists
            ) {
                return $this
                    ->checkExistingPostingIntegrity(
                        $loan,
                        $legacyDocNo,
                        $defaultInterestAccountId,
                        true
                    );
            }

            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Legacy LONG-TERM DFI — match by contractual YYYYMM
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | Due period:
        | 202512
        |
        | Old document:
        | DFI-16994-20251214
        |
        | Current due date:
        | 2025-12-31
        |
        | We use the stored loan_payments_period to prevent December
        | from being charged a second time.
        |--------------------------------------------------------------------------
        */

        $duePeriod =
            $dueDate
                ->format(
                    'Ym'
                );

        $legacyPrefix =
            'DFI-'
            . (int)
                $loan
                    ->loan_id
            . '-';

        $legacyPayments =
            DB::table(
                'sacco_loan_payments'
            )
                ->where(
                    'loan_payments_loan_id',
                    $loan
                        ->loan_id
                )
                ->where(
                    'loan_payments_period',
                    $duePeriod
                )
                ->where(
                    'loan_payments_docno',
                    'LIKE',
                    $legacyPrefix . '%'
                )
                ->where(
                    'loan_payments_interest',
                    '>',
                    0
                )
                ->orderBy(
                    'loan_payments_id'
                )
                ->get();

        /*
         * Multiple legacy DFI entries in one contractual period
         * are ambiguous/corrupt.
         *
         * Fail closed instead of guessing.
         */
        if (
            $legacyPayments
                ->count()
            > 1
        ) {
            throw new RuntimeException(
                'DFI integrity error for loan '
                . (int)
                    $loan
                        ->loan_id
                . ' period '
                . $duePeriod
                . ': multiple legacy DFI payment rows already exist.'
            );
        }

        if (
            $legacyPayments
                ->count()
            === 1
        ) {
            $legacyDocNo =
                trim(
                    (string)
                        $legacyPayments
                            ->first()
                            ->loan_payments_docno
                );

            if (
                $legacyDocNo === ''
            ) {
                throw new RuntimeException(
                    'DFI integrity error for loan '
                    . (int)
                        $loan
                            ->loan_id
                    . ' period '
                    . $duePeriod
                    . ': legacy DFI payment has no document number.'
                );
            }

            return $this
                ->checkExistingPostingIntegrity(
                    $loan,
                    $legacyDocNo,
                    $defaultInterestAccountId,
                    true
                );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Detect orphan LEGACY ledger posting
        |--------------------------------------------------------------------------
        |
        | Old document:
        |
        | DFI-{loan_id}-{YYYYMMDD}
        |
        | Therefore:
        |
        | DFI-{loan_id}-{YYYYMM}%
        |
        | identifies ledger entries from the same contractual month.
        |
        | If a ledger exists but the corresponding sacco_loan_payments
        | row is missing, the integrity checker deliberately fails and
        | prevents creation of another financial charge.
        |--------------------------------------------------------------------------
        */

        $legacyLedgerDocNos =
            DB::table(
                'sacco_accounts_trans'
            )
                ->where(
                    'accounts_trans_doc_no',
                    'LIKE',
                    $legacyPrefix
                    . $duePeriod
                    . '%'
                )
                ->whereNotNull(
                    'accounts_trans_doc_no'
                )
                ->distinct()
                ->pluck(
                    'accounts_trans_doc_no'
                )
                ->filter(
                    function ($docNo) {
                        return trim(
                            (string) $docNo
                        ) !== '';
                    }
                )
                ->values();

        if (
            $legacyLedgerDocNos
                ->count()
            > 1
        ) {
            throw new RuntimeException(
                'DFI integrity error for loan '
                . (int)
                    $loan
                        ->loan_id
                . ' period '
                . $duePeriod
                . ': multiple legacy DFI ledger document numbers '
                . 'exist without a unique payment match.'
            );
        }

        if (
            $legacyLedgerDocNos
                ->count()
            === 1
        ) {
            return $this
                ->checkExistingPostingIntegrity(
                    $loan,

                    trim(
                        (string)
                            $legacyLedgerDocNos
                                ->first()
                    ),

                    $defaultInterestAccountId,

                    true
                );
        }

        return null;
    }


    /**
     * Validate existing DFI financial integrity.
     */
    private function checkExistingPostingIntegrity(
        $loan,
        string $docNo,
        int $defaultInterestAccountId,
        bool $legacyDocument = false
    ): array {
        $payment =
            DB::table(
                'sacco_loan_payments'
            )
                ->where(
                    'loan_payments_loan_id',
                    $loan
                        ->loan_id
                )
                ->where(
                    'loan_payments_docno',
                    $docNo
                )
                ->first();

        $ledgerRows =
            DB::table(
                'sacco_accounts_trans'
            )
                ->where(
                    'accounts_trans_doc_no',
                    $docNo
                )
                ->get();

        if (
            !$payment
            &&
            $ledgerRows
                ->isNotEmpty()
        ) {
            throw new RuntimeException(
                "DFI integrity error {$docNo}: "
                . 'ledger exists but '
                . 'sacco_loan_payments row is missing.'
            );
        }

        if (!$payment) {
            throw new RuntimeException(
                "DFI integrity error {$docNo}: "
                . 'an unexpected posting state was detected.'
            );
        }

        $postedInterest =
            round(
                (float) (
                    $payment
                        ->loan_payments_interest
                    ?? 0
                ),
                2
            );

        if (
            $postedInterest <= 0
        ) {
            throw new RuntimeException(
                "DFI integrity error {$docNo}: "
                . 'payment row contains no positive '
                . 'defaulted interest.'
            );
        }

        $receivableDebit =
            round(
                (float)
                    $ledgerRows
                        ->where(
                            'accounts_trans_sub_account',
                            (int)
                                $loan
                                    ->loan_type_acount
                        )
                        ->sum(
                            'accounts_trans_debit'
                        ),
                2
            );

        /*
         * New DFI:
         *
         * credit dedicated defaulted-interest account.
         */
        $acceptedCreditAccounts = [
            $defaultInterestAccountId,
        ];

        /*
         * Legacy DFI:
         *
         * previous implementation may have credited
         * loan_type_int_account.
         *
         * Accept that ONLY when validating legacy documents.
         */
        if (
            $legacyDocument
            &&
            is_numeric(
                $loan
                    ->loan_type_int_account
                ?? null
            )
            &&
            (int)
                $loan
                    ->loan_type_int_account
            > 0
        ) {
            $acceptedCreditAccounts[] =
                (int)
                    $loan
                        ->loan_type_int_account;
        }

        $acceptedCreditAccounts =
            array_values(
                array_unique(
                    $acceptedCreditAccounts
                )
            );

        $interestCredit =
            round(
                (float)
                    $ledgerRows
                        ->whereIn(
                            'accounts_trans_sub_account',
                            $acceptedCreditAccounts
                        )
                        ->sum(
                            'accounts_trans_credit'
                        ),
                2
            );

        if (
            abs(
                $receivableDebit
                - $postedInterest
            ) > 0.01

            ||

            abs(
                $interestCredit
                - $postedInterest
            ) > 0.01
        ) {
            throw new RuntimeException(
                "DFI integrity error {$docNo}: "
                . 'payment exists but balanced DFI '
                . 'ledger legs do not match it.'
            );
        }

        return [
            'status' =>
                'duplicate',

            'loan_id' =>
                (int)
                    $loan
                        ->loan_id,

            'doc_no' =>
                $docNo,

            'interest' =>
                $postedInterest,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | General ledger posting
    |--------------------------------------------------------------------------
    */

    private function postLedgerEntry(
        int $subAccountId,
        float $debit,
        float $credit,
        string $docNo,
        string $description,
        string $period,
        Carbon $postedAt,
        int $memberId
    ): void {
        if (
            $subAccountId <= 0
        ) {
            throw new RuntimeException(
                "DFI ledger error {$docNo}: "
                . 'invalid sub-account.'
            );
        }

        $subAccount =
            DB::table(
                'sacco_sub_account'
            )
                ->where(
                    'sub_account_id',
                    $subAccountId
                )
                ->whereRaw(
                    "COALESCE("
                    . "sub_account_deleted, 'N'"
                    . ") <> 'Y'"
                )
                ->lockForUpdate()
                ->first();

        if (!$subAccount) {
            throw new RuntimeException(
                "DFI ledger error {$docNo}: "
                . "active sub-account {$subAccountId} "
                . 'does not exist.'
            );
        }

        $mainAccountId =
            (int) (
                $subAccount
                    ->sub_account_main_account
                ?? 0
            );

        if (
            $mainAccountId <= 0
        ) {
            throw new RuntimeException(
                "DFI ledger error {$docNo}: "
                . "sub-account {$subAccountId} "
                . 'has no main-account mapping.'
            );
        }

        $mainAccount =
            DB::table(
                'sacco_main_account'
            )
                ->where(
                    'main_account_id',
                    $mainAccountId
                )
                ->whereRaw(
                    "COALESCE("
                    . "main_account_deleted, 'N'"
                    . ") <> 'Y'"
                )
                ->lockForUpdate()
                ->first();

        if (!$mainAccount) {
            throw new RuntimeException(
                "DFI ledger error {$docNo}: "
                . "active main account {$mainAccountId} "
                . 'does not exist.'
            );
        }

        $row = [
            'accounts_trans_sub_account' =>
                $subAccountId,

            'accounts_trans_period' =>
                $period,

            'accounts_trans_debit' =>
                round(
                    $debit,
                    2
                ),

            'accounts_trans_credit' =>
                round(
                    $credit,
                    2
                ),

            'accounts_trans_doc_no' =>
                $docNo,

            'accounts_trans_decription' =>
                $description,

            'accounts_trans_dat_date' =>
                $postedAt
                    ->toDateString(),

            'accounts_trans_transdate' =>
                $postedAt,

            'accounts_trans_user_id' =>
                self::SYSTEM_USER_ID,

            'accounts_trans_ip' =>
                self::SYSTEM_IP,
        ];

        /*
         * Add stronger traceability where columns exist.
         */
        if (
            Schema::hasColumn(
                'sacco_accounts_trans',
                'accounts_trans_member_id'
            )
        ) {
            $row[
                'accounts_trans_member_id'
            ] = $memberId;
        }

        if (
            Schema::hasColumn(
                'sacco_accounts_trans',
                'accounts_trans_source'
            )
        ) {
            $row[
                'accounts_trans_source'
            ] = self::SOURCE;
        }

        if (
            Schema::hasColumn(
                'sacco_accounts_trans',
                'accounts_trans_app_name'
            )
        ) {
            $row[
                'accounts_trans_app_name'
            ] = 'iSacco';
        }

        DB::table(
            'sacco_accounts_trans'
        )
            ->insert(
                $row
            );

        /*
        |--------------------------------------------------------------------------
        | Update accumulated account totals
        |--------------------------------------------------------------------------
        */

        $debitAmount =
            number_format(
                round(
                    $debit,
                    2
                ),
                2,
                '.',
                ''
            );

        $creditAmount =
            number_format(
                round(
                    $credit,
                    2
                ),
                2,
                '.',
                ''
            );

        DB::table(
            'sacco_sub_account'
        )
            ->where(
                'sub_account_id',
                $subAccountId
            )
            ->update([
                'sub_account_debit' =>
                    DB::raw(
                        'COALESCE('
                        . 'sub_account_debit, 0'
                        . ') + '
                        . $debitAmount
                    ),

                'sub_account_credit' =>
                    DB::raw(
                        'COALESCE('
                        . 'sub_account_credit, 0'
                        . ') + '
                        . $creditAmount
                    ),
            ]);

        DB::table(
            'sacco_main_account'
        )
            ->where(
                'main_account_id',
                $mainAccountId
            )
            ->update([
                'main_account_debit' =>
                    DB::raw(
                        'COALESCE('
                        . 'main_account_debit, 0'
                        . ') + '
                        . $debitAmount
                    ),

                'main_account_credit' =>
                    DB::raw(
                        'COALESCE('
                        . 'main_account_credit, 0'
                        . ') + '
                        . $creditAmount
                    ),
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    private function queueNotifications(
        array $context
    ): void {
        try {
            $borrowerSubject =
                'Loan Defaulted Interest Applied - '
                . $context[
                    'loan_type_name'
                ];

            $borrowerMessage =
                implode(
                    "\n",
                    [
                        'Dear '
                            . $context[
                                'member_name'
                            ]
                            . ',',

                        '',

                        'This is to notify you that '
                            . 'defaulted interest has been applied '
                            . 'to your '
                            . $context[
                                'loan_type_name'
                            ]
                            . ' loan after the applicable '
                            . 'repayment obligation remained unpaid '
                            . 'beyond the grace period.',

                        '',

                        'Loan: '
                            . $context[
                                'loan_type_name'
                            ],

                        'Loan Number: '
                            . $context[
                                'loan_id'
                            ],

                        'Previous Outstanding Balance: KES '
                            . number_format(
                                $context[
                                    'previous_outstanding'
                                ],
                                2
                            ),

                        'Defaulted Interest Rate: '
                            . rtrim(
                                rtrim(
                                    number_format(
                                        $context[
                                            'rate'
                                        ],
                                        4
                                    ),
                                    '0'
                                ),
                                '.'
                            )
                            . '%',

                        'Defaulted Interest Applied: KES '
                            . number_format(
                                $context[
                                    'interest'
                                ],
                                2
                            ),

                        'New Outstanding Balance: KES '
                            . number_format(
                                $context[
                                    'new_outstanding'
                                ],
                                2
                            ),

                        'Due Period: '
                            . $context[
                                'due_period'
                            ],

                        'Contractual Due Date: '
                            . $context[
                                'due_date'
                            ],

                        'Default Date: '
                            . $context[
                                'default_date'
                            ],

                        'Default Cycle: '
                            . $context[
                                'posted_cycle'
                            ]
                            . ' of maximum '
                            . $context[
                                'max_cycles'
                            ],

                        'Transaction Reference: '
                            . $context[
                                'doc_no'
                            ],

                        '',

                        'For clarification or repayment assistance, '
                            . 'please contact the SACCO office.',

                        '',

                        'Regards,',

                        'SACCO Management',
                    ]
                );

            $this
                ->insertNotification([
                    'member_id' =>
                        $context[
                            'member_id'
                        ],

                    'name' =>
                        $context[
                            'member_name'
                        ],

                    'email' =>
                        $context[
                            'member_email'
                        ],

                    'phone' =>
                        $context[
                            'member_phone_no'
                        ],

                    'subject' =>
                        $borrowerSubject,

                    'message' =>
                        $borrowerMessage,

                    'doc_no' =>
                        $context[
                            'doc_no'
                        ],
                ]);

            /*
            |--------------------------------------------------------------------------
            | SACCO officials
            |--------------------------------------------------------------------------
            */

            $officials =
                DB::table(
                    'sacco_members'
                )
                    ->where(
                        'member_position',
                        2
                    )
                    ->where(
                        'member_active',
                        'Y'
                    )
                    ->whereRaw(
                        "COALESCE("
                        . "member_deleted, 'N'"
                        . ") <> 'Y'"
                    )
                    ->select(
                        'member_id',
                        'member_name',
                        'member_email',
                        'member_phone_no'
                    )
                    ->get();

            foreach (
                $officials
                as $official
            ) {
                $officialSubject =
                    'Defaulted Interest Applied - '
                    . $context[
                        'member_name'
                    ]
                    . ' - '
                    . $context[
                        'loan_type_name'
                    ];

                /*
                 * Minimal notification for officials.
                 */
                $officialMessage =
                    implode(
                        "\n",
                        [
                            'Dear '
                                . $official
                                    ->member_name
                                . ',',

                            '',

                            'Defaulted interest has been '
                                . 'automatically applied to '
                                . $context[
                                    'member_name'
                                ]
                                . ' for the '
                                . $context[
                                    'loan_type_name'
                                ]
                                . ' loan.',

                            '',

                            'For full details, please log into '
                                . 'the SACCO system.',

                            '',

                            'Regards,',

                            'SACCO System',
                        ]
                    );

                $this
                    ->insertNotification([
                        'member_id' =>
                            (int)
                                $official
                                    ->member_id,

                        'name' =>
                            (string)
                                $official
                                    ->member_name,

                        'email' =>
                            (string) (
                                $official
                                    ->member_email
                                ?? ''
                            ),

                        'phone' =>
                            (string) (
                                $official
                                    ->member_phone_no
                                ?? ''
                            ),

                        'subject' =>
                            $officialSubject,

                        'message' =>
                            $officialMessage,

                        'doc_no' =>
                            $context[
                                'doc_no'
                            ],
                    ]);
            }
        } catch (
            Throwable $e
        ) {
            Log::error(
                'DFI notification queueing failed '
                . 'after successful financial posting',
                [
                    'loan_id' =>
                        $context[
                            'loan_id'
                        ]
                        ?? null,

                    'doc_no' =>
                        $context[
                            'doc_no'
                        ]
                        ?? null,

                    'message' =>
                        $e
                            ->getMessage(),
                ]
            );
        }
    }


    private function insertNotification(
        array $data
    ): void {
        /*
         * Notification infrastructure is optional.
         *
         * Financial posting must remain valid even where
         * notification table is unavailable.
         */
        if (
            !Schema::hasTable(
                'sacco_system_notifications'
            )
        ) {
            return;
        }

        $row = [
            'notif_subject' =>
                $data[
                    'subject'
                ],

            'notif_message' =>
                $data[
                    'message'
                ],

            'notif_recipient_name' =>
                $data[
                    'name'
                ],

            'notif_recipient_email' =>
                $data[
                    'email'
                ],

            'notif_recipient_phone' =>
                $data[
                    'phone'
                ],

            'notif_member_id' =>
                $data[
                    'member_id'
                ],

            'notif_type' =>
                'system',

            'notif_status' =>
                'unread',

            'notif_created_at' =>
                now(
                    'Africa/Nairobi'
                ),

            'notif_created_by' =>
                self::SYSTEM_USER_ID,

            'notif_ip' =>
                self::SYSTEM_IP,
        ];

        if (
            Schema::hasColumn(
                'sacco_system_notifications',
                'notif_related_doc'
            )
        ) {
            $row[
                'notif_related_doc'
            ] = $data[
                'doc_no'
            ];
        }

        DB::table(
            'sacco_system_notifications'
        )
            ->insert(
                $row
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Document numbers / descriptions
    |--------------------------------------------------------------------------
    */

    /**
     * New deterministic document numbers.
     *
     * Short term:
     *
     * DFI-L16994-D20260905
     *
     * Long term:
     *
     * DFI-L16994-P202609
     */
    private function documentNumber(
        $loan,
        Carbon $dueDate
    ): string {
        if (
            $this
                ->isShortTermLoan(
                    $loan
                )
        ) {
            return 'DFI-L'
                . (int)
                    $loan
                        ->loan_id
                . '-D'
                . $dueDate
                    ->format(
                        'Ymd'
                    );
        }

        return 'DFI-L'
            . (int)
                $loan
                    ->loan_id
            . '-P'
            . $dueDate
                ->format(
                    'Ym'
                );
    }


    private function description(
        $loan,
        $member,
        Carbon $dueDate
    ): string {
        $memberLabel =
            'MEMBER ID '
            . (int)
                $loan
                    ->loan_member;

        if (
            !empty(
                $member
                    ->member_sacco_id
            )
        ) {
            $memberLabel .=
                '/'
                . strtoupper(
                    trim(
                        (string)
                            $member
                                ->member_sacco_id
                    )
                );
        }

        if (
            $this
                ->isShortTermLoan(
                    $loan
                )
        ) {
            return 'DEFAULTED INTEREST - '
                . $memberLabel
                . ' - LOAN '
                . (int)
                    $loan
                        ->loan_id
                . ' - DUE DATE '
                . $dueDate
                    ->format(
                        'Y-m-d'
                    );
        }

        return 'DEFAULTED INTEREST - '
            . $memberLabel
            . ' - LOAN '
            . (int)
                $loan
                    ->loan_id
            . ' - DUE PERIOD '
            . $dueDate
                ->format(
                    'Ym'
                );
    }


    /*
    |--------------------------------------------------------------------------
    | Period/date helpers
    |--------------------------------------------------------------------------
    */

    private function isValidPeriod(
        $value
    ): bool {
        if (
            !is_scalar(
                $value
            )
        ) {
            return false;
        }

        $period =
            trim(
                (string) $value
            );

        if (
            !preg_match(
                '/^\d{6}$/',
                $period
            )
            ||
            $period === '000000'
        ) {
            return false;
        }

        try {
            $date =
                Carbon::createFromFormat(
                    '!Ym',
                    $period,
                    'Africa/Nairobi'
                );

            return $date !== false
                &&
                $date
                    ->format(
                        'Ym'
                    ) === $period;
        } catch (
            Throwable $e
        ) {
            return false;
        }
    }


    private function periodToMonth(
        string $period
    ): Carbon {
        if (
            !$this
                ->isValidPeriod(
                    $period
                )
        ) {
            throw new RuntimeException(
                "DFI period error: invalid "
                . "YYYYMM period {$period}."
            );
        }

        return Carbon::createFromFormat(
            '!Ym',
            trim(
                $period
            ),
            'Africa/Nairobi'
        )
            ->startOfMonth()
            ->startOfDay();
    }


    private function dateInMonthUsingAnchor(
        Carbon $month,
        int $anchorDay
    ): Carbon {
        $monthStart =
            $month
                ->copy()
                ->startOfMonth()
                ->startOfDay();

        $day =
            min(
                max(
                    1,
                    $anchorDay
                ),
                $monthStart
                    ->daysInMonth
            );

        return $monthStart
            ->copy()
            ->day(
                $day
            )
            ->startOfDay();
    }


    /**
     * Calendar-aware month movement.
     *
     * December + 1 month = January next year.
     *
     * Never manipulate YYYYMM using numeric + 1.
     */
    private function nextAnchoredMonthlyDate(
        Carbon $date,
        int $anchorDay
    ): Carbon {
        $nextMonth =
            $date
                ->copy()
                ->startOfMonth()
                ->addMonthNoOverflow()
                ->startOfMonth();

        return $this
            ->dateInMonthUsingAnchor(
                $nextMonth,
                $anchorDay
            );
    }


    private function skipped(
        int $loanId,
        string $reason,
        array $extra = []
    ): array {
        return array_merge(
            [
                'status' =>
                    'skipped',

                'loan_id' =>
                    $loanId,

                'reason' =>
                    $reason,
            ],
            $extra
        );
    }


    /**
     * LOCKED classification:
     *
     * loan_type_duration <= 1
     *
     * means date-driven short-term treatment.
     *
     * We deliberately do NOT use loan_payment_period
     * for this classification.
     */
    private function isShortTermLoan(
        $loan
    ): bool {
        $duration =
            $loan
                ->loan_type_duration
            ?? null;

        return is_numeric(
            $duration
        )
            &&
            (int) $duration <= 1;
    }
}