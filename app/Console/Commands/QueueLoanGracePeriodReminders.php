<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class QueueLoanGracePeriodReminders extends Command
{
    private const SYSTEM_USER_ID = 1;
    private const SYSTEM_IP = '127.0.0.1';

    private const NOTIFICATION_TYPE = 'loan_payment_reminder';

    /*
     * Safety stop when scanning old contractual periods.
     */
    private const MAX_DUE_CYCLES_TO_SCAN = 600;

    protected $signature = 'loans:queue-grace-period-reminders {--limit=5}';

    protected $description =
        'Queue monthly loan payment reminders for active loans whose grace period has expired';

    private ?float $thresholdAmountCache = null;
    private ?int $monthlyCutOffDayCache = null;

    public function handle(): int
    {
        /*
        |--------------------------------------------------------------------------
        | Hard limit
        |--------------------------------------------------------------------------
        |
        | Never examine more than 5 loans per invocation.
        |--------------------------------------------------------------------------
        */

        $limit = max(
            1,
            min(
                5,
                (int) $this->option('limit')
            )
        );

        $today = now('Africa/Nairobi')->startOfDay();

        $reminderMonth = $today->format('Ym');

        /*
        |--------------------------------------------------------------------------
        | SACCO configuration
        |--------------------------------------------------------------------------
        */

        $threshold = $this->thresholdAmount();

        $appUrl = rtrim(
            trim((string) config('app.url')),
            '/'
        );

        if (
            $appUrl === ''
            || !filter_var($appUrl, FILTER_VALIDATE_URL)
        ) {
            $this->error(
                'Loan reminder configuration error: APP_URL is missing or invalid.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | M-PESA Paybill / Business Number
        |--------------------------------------------------------------------------
        |
        | The C2B shortcode is the Paybill / Business Number members use
        | when making direct loan repayments.
        |--------------------------------------------------------------------------
        */

        $paybill = DB::table('mpesa_configs')
            ->whereRaw(
                "LOWER(TRIM(COALESCE(api_type, ''))) = 'c2b'"
            )
            ->whereNotNull('shortcode')
            ->where('shortcode', '<>', '')
            ->orderByDesc('id')
            ->value('shortcode');

        $paybill = trim((string) $paybill);

        if ($paybill === '') {
            $this->error(
                'Loan reminder configuration error: no M-PESA C2B shortcode was found.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | SACCO name
        |--------------------------------------------------------------------------
        */

        $companyName = DB::table('sacco_defaults')
            ->where(
                'default_name',
                'company_name'
            )
            ->value('default_value');

        $companyName = trim(
            (string) (
                $companyName
                ?: 'SACCO'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Cursor
        |--------------------------------------------------------------------------
        |
        | We scan through the loan book gradually.
        |
        | The month is included in the cursor key so a new month naturally
        | starts a fresh reminder pass.
        |--------------------------------------------------------------------------
        */

        $databaseName = (string)
            DB::connection()->getDatabaseName();

        $cursorKey =
            'loan-reminder:cursor:'
            . sha1(
                $databaseName
                . ':'
                . $reminderMonth
            );

        $lastLoanId = (int)
            Cache::get(
                $cursorKey,
                0
            );

        $candidateIds = $this->candidateLoanIds(
            $lastLoanId,
            $limit,
            $threshold
        );

        /*
        |--------------------------------------------------------------------------
        | End of pass
        |--------------------------------------------------------------------------
        */

        if ($candidateIds->isEmpty()) {
            if ($lastLoanId > 0) {
                Cache::put(
                    $cursorKey,
                    0,
                    now()->addDays(40)
                );
            }

            $this->info(
                'Loan reminders: no candidate loans in this cursor window.'
            );

            return self::SUCCESS;
        }

        $queued = 0;
        $alreadyReminded = 0;
        $notDue = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($candidateIds as $loanId) {
            $loanId = (int) $loanId;

            try {
                $result = $this->processLoan(
                    $loanId,
                    $threshold,
                    $reminderMonth,
                    $paybill,
                    $appUrl,
                    $companyName
                );

                $status = $result['status'] ?? 'skipped';

                if ($status === 'queued') {
                    $queued++;

                    $this->line(
                        'QUEUED '
                        . ($result['reference'] ?? '')
                        . ' - '
                        . ($result['email'] ?? '')
                    );
                } elseif ($status === 'already_reminded') {
                    $alreadyReminded++;
                } elseif ($status === 'not_due') {
                    $notDue++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $errors++;

                Log::error(
                    'Loan grace-period reminder processing failed',
                    [
                        'loan_id' => $loanId,
                        'message' => $e->getMessage(),
                    ]
                );

                $this->error(
                    "Loan reminder {$loanId}: {$e->getMessage()}"
                );
            } finally {
                /*
                 * One problematic loan must never block the entire SACCO.
                 */
                Cache::put(
                    $cursorKey,
                    $loanId,
                    now()->addDays(40)
                );
            }
        }

        $this->info(
            "Loan reminders complete: "
            . "examined={$candidateIds->count()}, "
            . "queued={$queued}, "
            . "already_reminded={$alreadyReminded}, "
            . "not_due={$notDue}, "
            . "skipped={$skipped}, "
            . "errors={$errors}."
        );

        return $errors > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Candidate loans
    |--------------------------------------------------------------------------
    */

    private function candidateLoanIds(
        int $afterLoanId,
        int $limit,
        float $threshold
    ) {
        return DB::table('sacco_loans as l')
            ->where(
                'l.loan_id',
                '>',
                $afterLoanId
            )

            /*
             * Active loans only.
             */
            ->whereRaw(
                "COALESCE(l.loan_stoped, 'N') <> 'Y'"
            )

            /*
             * Member must still owe at least threshold_amount.
             *
             * IMPORTANT:
             * Equal to threshold is eligible.
             */
            ->whereRaw(
                '(COALESCE(l.loan_amount,0) '
                . '- COALESCE(l.loan_loan_paid,0)) >= ?',
                [$threshold]
            )
            ->orderBy('l.loan_id')
            ->limit($limit)
            ->pluck('l.loan_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Process one loan
    |--------------------------------------------------------------------------
    */

    private function processLoan(
        int $loanId,
        float $threshold,
        string $reminderMonth,
        string $paybill,
        string $appUrl,
        string $companyName
    ): array {
        return DB::transaction(
            function () use (
                $loanId,
                $threshold,
                $reminderMonth,
                $paybill,
                $appUrl,
                $companyName
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
                        'l.loan_amount',
                        'l.loan_loan_paid',
                        'l.loan_on',
                        'l.loan_taken_period',
                        'l.loan_start_deduction_period',
                        'l.loan_monthly_repayment_amount',
                        'l.loan_stoped',

                        't.loan_type_name',
                        't.loan_type_duration',
                        't.loan_type_grace_days_after_due'
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$loan) {
                    return [
                        'status' => 'skipped',
                        'reason' => 'loan_not_found',
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Active loan
                |--------------------------------------------------------------------------
                */

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
                    return [
                        'status' => 'skipped',
                        'reason' => 'loan_stopped',
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Threshold check
                |--------------------------------------------------------------------------
                */

                $outstanding = round(
                    (float) (
                        $loan->loan_amount
                        ?? 0
                    )
                    -
                    (float) (
                        $loan->loan_loan_paid
                        ?? 0
                    ),
                    2
                );

                if ($outstanding < $threshold) {
                    return [
                        'status' => 'skipped',
                        'reason' => 'below_threshold',
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Member
                |--------------------------------------------------------------------------
                */

                $member = DB::table('sacco_members')
                    ->where(
                        'member_id',
                        $loan->loan_member
                    )
                    ->first();

                if (!$member) {
                    return [
                        'status' => 'skipped',
                        'reason' => 'member_not_found',
                    ];
                }

                $email = trim(
                    (string) (
                        $member->member_email
                        ?? ''
                    )
                );

                if (
                    $email === ''
                    || !filter_var(
                        $email,
                        FILTER_VALIDATE_EMAIL
                    )
                ) {
                    return [
                        'status' => 'skipped',
                        'reason' => 'invalid_member_email',
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | One reminder per loan per month
                |--------------------------------------------------------------------------
                |
                | Once this reference exists in sacco_system_notifications,
                | this month's reminder is considered complete.
                |
                | We deliberately DO NOT inspect notif_status.
                |--------------------------------------------------------------------------
                */

                $reference =
                    'LPR-L'
                    . $loanId
                    . '-M'
                    . $reminderMonth;

                if (
                    DB::table('sacco_system_notifications')
                        ->where(
                            'notif_related_doc',
                            $reference
                        )
                        ->exists()
                ) {
                    return [
                        'status' => 'already_reminded',
                        'reference' => $reference,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Has grace period actually expired?
                |--------------------------------------------------------------------------
                */

                $overdue = $this->findOverdueCycle(
                    $loan,
                    $threshold
                );

                if ($overdue === null) {
                    return [
                        'status' => 'not_due',
                        'reason' => 'grace_period_not_expired_or_payment_satisfied',
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Payment code
                |--------------------------------------------------------------------------
                |
                | Loan ID 14764 becomes:
                |
                | LN14764
                |--------------------------------------------------------------------------
                */

                $paymentCode =
                    'LN'
                    . (int) $loan->loan_id;

                /*
                |--------------------------------------------------------------------------
                | Email
                |--------------------------------------------------------------------------
                */

                $loanTypeName = trim(
                    (string) (
                        $loan->loan_type_name
                        ?? 'Loan'
                    )
                );

                if ($loanTypeName === '') {
                    $loanTypeName = 'Loan';
                }

                $memberName = trim(
                    (string) (
                        $member->member_name
                        ?? 'Member'
                    )
                );

                if ($memberName === '') {
                    $memberName = 'Member';
                }

                $subject =
                    'Payment Reminder - '
                    . $loanTypeName
                    . ' Loan';

                $message = $this->buildEmailMessage(
                    $memberName,
                    $loanTypeName,
                    $paybill,
                    $paymentCode,
                    $appUrl,
                    $companyName
                );

                /*
                |--------------------------------------------------------------------------
                | Queue notification
                |--------------------------------------------------------------------------
                |
                | Inserting this row counts as this month's reminder.
                |
                | Existing SendPendingNotificationsJob handles delivery.
                |--------------------------------------------------------------------------
                */

                DB::table(
                    'sacco_system_notifications'
                )
                    ->insert([
                        'notif_recipient_name' =>
                            $memberName,

                        'notif_recipient_email' =>
                            $email,

                        'notif_recipient_phone' =>
                            (string) (
                                $member->member_phone_no
                                ?? ''
                            ),

                        'notif_subject' =>
                            $subject,

                        'notif_message' =>
                            $message,

                        'notif_status' =>
                            'unread',

                        'notif_sent_at' =>
                            null,

                        'notif_read_at' =>
                            null,

                        'notif_member_id' =>
                            (int) $member->member_id,

                        'notif_related_doc' =>
                            $reference,

                        'notif_type' =>
                            self::NOTIFICATION_TYPE,

                        'notif_created_by' =>
                            self::SYSTEM_USER_ID,

                        'notif_ip' =>
                            self::SYSTEM_IP,

                        'notif_meta' =>
                            json_encode(
                                [
                                    'loan_id' =>
                                        (int) $loan->loan_id,

                                    'reminder_month' =>
                                        $reminderMonth,

                                    'reminder_type' =>
                                        'grace_period_expired',

                                    'due_date' =>
                                        $overdue['due_date'],

                                    'default_date' =>
                                        $overdue['default_date'],

                                    'due_period' =>
                                        $overdue['due_period']
                                        ?? null,

                                    'payment_code' =>
                                        $paymentCode,
                                ],
                                JSON_UNESCAPED_SLASHES
                            ),

                        'notif_created_at' =>
                            now('Africa/Nairobi'),
                    ]);

                return [
                    'status' => 'queued',
                    'loan_id' => $loanId,
                    'email' => $email,
                    'reference' => $reference,
                ];
            },
            3
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Overdue cycle
    |--------------------------------------------------------------------------
    */

    private function findOverdueCycle(
        $loan,
        float $threshold
    ): ?array {
        if ($this->isShortTermLoan($loan)) {
            return $this->findShortTermOverdueCycle(
                $loan
            );
        }

        return $this->findLongTermOverdueCycle(
            $loan,
            $threshold
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Short-term loans
    |--------------------------------------------------------------------------
    */

    private function findShortTermOverdueCycle(
        $loan
    ): ?array {
        $loanOn = $this->loanOnDate(
            $loan
        );

        $graceDays = max(
            0,
            (int) (
                $loan->loan_type_grace_days_after_due
                ?? 45
            )
        );

        $today =
            now('Africa/Nairobi')
                ->startOfDay();

        $cycleBase =
            $loanOn->copy();

        for (
            $i = 0;
            $i < self::MAX_DUE_CYCLES_TO_SCAN;
            $i++
        ) {
            $dueDate =
                $this->nextAnchoredMonthlyDate(
                    $cycleBase,
                    $cycleBase->day
                );

            $defaultDate =
                $dueDate
                    ->copy()
                    ->addDays(
                        $graceDays
                    );

            /*
             * Grace period has not expired yet.
             */
            if ($defaultDate->gt($today)) {
                return null;
            }

            /*
             * Genuine payment before/through the default date resets
             * the short-term rolling repayment clock.
             */
            $paymentDate =
                $this->latestGenuinePaymentDateBetween(
                    (int) $loan->loan_id,
                    $cycleBase,
                    $defaultDate
                );

            if ($paymentDate !== null) {
                $cycleBase =
                    $paymentDate->copy();

                continue;
            }

            return [
                'due_date' =>
                    $dueDate->toDateString(),

                'default_date' =>
                    $defaultDate->toDateString(),

                'due_period' =>
                    $dueDate->format('Ym'),
            ];
        }

        throw new RuntimeException(
            "Loan reminder safety stop for loan "
            . "{$loan->loan_id}: too many short-term cycles."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Long-term loans
    |--------------------------------------------------------------------------
    */

    private function findLongTermOverdueCycle(
        $loan,
        float $threshold
    ): ?array {
        $graceDays = max(
            0,
            (int) (
                $loan->loan_type_grace_days_after_due
                ?? 45
            )
        );

        $today =
            now('Africa/Nairobi')
                ->startOfDay();

        $scheduledPayment = round(
            (float) (
                $loan->loan_monthly_repayment_amount
                ?? 0
            ),
            2
        );

        if ($scheduledPayment <= 0) {
            throw new RuntimeException(
                "Loan reminder data error for loan "
                . "{$loan->loan_id}: "
                . 'loan_monthly_repayment_amount is zero or missing.'
            );
        }

        $dueMonth =
            $this->firstLongTermDueMonth(
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

            if ($defaultDate->gt($today)) {
                return null;
            }

            $duePeriod =
                $dueMonth->format('Ym');

            $cashPaid =
                $this->cashPaidForPeriodByDate(
                    (int) $loan->loan_id,
                    $duePeriod,
                    $defaultDate
                );

            $unpaidDueAmount = round(
                max(
                    0,
                    $scheduledPayment
                    - $cashPaid
                ),
                2
            );

            /*
             * Period sufficiently paid.
             */
            if ($unpaidDueAmount <= $threshold) {
                $dueMonth =
                    $dueMonth
                        ->copy()
                        ->addMonthNoOverflow()
                        ->startOfMonth();

                continue;
            }

            return [
                'due_date' =>
                    $dueDate->toDateString(),

                'default_date' =>
                    $defaultDate->toDateString(),

                'due_period' =>
                    $duePeriod,
            ];
        }

        throw new RuntimeException(
            "Loan reminder safety stop for loan "
            . "{$loan->loan_id}: too many monthly periods."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Genuine repayments
    |--------------------------------------------------------------------------
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
                . 'COALESCE(loan_payments_amount,0)'
                . ' + '
                . 'COALESCE(loan_payments_interest,0)'
                . ') > 0'
            )
            ->whereRaw(
                "UPPER(TRIM(COALESCE("
                . "loan_payments_paid_in_by, ''"
                . "))) NOT IN (?, ?)",
                [
                    'AUTO-DFI',
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

    private function latestGenuinePaymentDateBetween(
        int $loanId,
        Carbon $afterDate,
        Carbon $throughDate
    ): ?Carbon {
        $row =
            $this->genuineRepaymentQuery(
                $loanId
            )
                ->whereRaw(
                    'DATE(COALESCE('
                    . 'loan_payments_paid_on, '
                    . 'loan_payments_on'
                    . ')) > ?',
                    [
                        $afterDate->toDateString()
                    ]
                )
                ->whereRaw(
                    'DATE(COALESCE('
                    . 'loan_payments_paid_on, '
                    . 'loan_payments_on'
                    . ')) <= ?',
                    [
                        $throughDate->toDateString()
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
                    . ')) AS effective_payment_date'
                )
                ->first();

        if (
            !$row
            || empty(
                $row->effective_payment_date
            )
        ) {
            return null;
        }

        return Carbon::parse(
            $row->effective_payment_date,
            'Africa/Nairobi'
        )
            ->startOfDay();
    }

    private function cashPaidForPeriodByDate(
        int $loanId,
        string $period,
        Carbon $defaultDate
    ): float {
        $amount =
            $this->genuineRepaymentQuery(
                $loanId
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
                        $defaultDate->toDateString()
                    ]
                )
                ->selectRaw(
                    'COALESCE(SUM('
                    . 'COALESCE(loan_payments_amount,0)'
                    . ' + '
                    . 'COALESCE(loan_payments_interest,0)'
                    . '),0) AS cash_paid'
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

    /*
    |--------------------------------------------------------------------------
    | Long-term starting period
    |--------------------------------------------------------------------------
    */

    private function firstLongTermDueMonth(
        $loan
    ): Carbon {
        $loanOn =
            $this->loanOnDate(
                $loan
            );

        $explicitStart = false;

        if (
            $this->isValidPeriod(
                $loan->loan_start_deduction_period
                ?? null
            )
        ) {
            $dueMonth =
                $this->periodToMonth(
                    (string)
                        $loan->loan_start_deduction_period
                );

            $explicitStart = true;
        } elseif (
            $this->isValidPeriod(
                $loan->loan_taken_period
                ?? null
            )
        ) {
            $dueMonth =
                $this->periodToMonth(
                    (string)
                        $loan->loan_taken_period
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
                > $this->monthlyCutOffDay()
        ) {
            $dueMonth =
                $dueMonth
                    ->copy()
                    ->addMonthNoOverflow()
                    ->startOfMonth();
        }

        while (
            $dueMonth
                ->copy()
                ->endOfMonth()
                ->startOfDay()
                ->lte($loanOn)
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

    /*
    |--------------------------------------------------------------------------
    | Email formatting
    |--------------------------------------------------------------------------
    */

    private function buildEmailMessage(
        string $memberName,
        string $loanTypeName,
        string $paybill,
        string $paymentCode,
        string $appUrl,
        string $companyName
    ): string {
        $memberName = $this->escapeHtml(
            $memberName
        );

        $loanTypeName = $this->escapeHtml(
            $loanTypeName
        );

        $paybill = $this->escapeHtml(
            $paybill
        );

        $paymentCode = $this->escapeHtml(
            $paymentCode
        );

        $companyName = $this->escapeHtml(
            $companyName
        );

        $safeAppUrl = $this->escapeHtml(
            $appUrl
        );

        return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;color:#222222;">

    <p>Dear {$memberName},</p>

    <p>
        This is a reminder that payment on your
        <strong>{$loanTypeName}</strong> loan is due and the applicable
        grace period has passed.
    </p>

    <p>
        You can make your loan payment through M-PESA using the details below:
    </p>

    <table cellpadding="0" cellspacing="0" border="0"
           style="width:100%;max-width:520px;border-collapse:collapse;margin:18px 0;">
        <tr>
            <td style="padding:12px;border:1px solid #dddddd;font-weight:bold;width:42%;">
                M-PESA Paybill
            </td>
            <td style="padding:12px;border:1px solid #dddddd;">
                {$paybill}
            </td>
        </tr>
        <tr>
            <td style="padding:12px;border:1px solid #dddddd;font-weight:bold;">
                Account Number
            </td>
            <td style="padding:12px;border:1px solid #dddddd;">
                {$paymentCode}
            </td>
        </tr>
    </table>

    <div style="margin:20px 0;padding:14px;border:1px solid #dddddd;background:#f7f7f7;">
        <strong>For your security:</strong><br>
        We do not include your outstanding loan balance or other sensitive
        financial information in this email.
    </div>

    <p>
        Please log in securely to the SACCO platform to review your current
        loan balance and payment details.
    </p>

    <p style="margin:22px 0;">
        <a href="{$safeAppUrl}"
           style="display:inline-block;padding:11px 18px;text-decoration:none;
                  border-radius:4px;background:#333333;color:#ffffff;font-weight:bold;">
            Log in to SACCO Platform
        </a>
    </p>

    <p>
        If you have already made the required payment, please disregard this reminder.
    </p>

    <div style="margin-top:24px;padding-top:16px;border-top:1px solid #dddddd;">
        <strong>Important:</strong>
        If this email has been sent to you by mistake, kindly ignore it,
        delete it, and immediately notify SACCO management.
    </div>

    <p style="margin-top:24px;">
        Regards,<br>
        <strong>{$companyName} Management</strong>
    </p>

</div>
HTML;
    }

    private function escapeHtml(
        string $value
    ): string {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    private function thresholdAmount(): float
    {
        if (
            $this->thresholdAmountCache
            !== null
        ) {
            return $this->thresholdAmountCache;
        }

        $value =
            DB::table('sacco_defaults')
                ->where(
                    'default_name',
                    'threshold_amount'
                )
                ->value(
                    'default_value'
                );

        $this->thresholdAmountCache =
            is_numeric($value)
                ? max(
                    0,
                    (float) $value
                )
                : 1.00;

        return $this->thresholdAmountCache;
    }

    private function monthlyCutOffDay(): int
    {
        if (
            $this->monthlyCutOffDayCache
            !== null
        ) {
            return $this->monthlyCutOffDayCache;
        }

        $value =
            DB::table('sacco_defaults')
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

        $this->monthlyCutOffDayCache =
            max(
                1,
                min(
                    31,
                    $day
                )
            );

        return $this->monthlyCutOffDayCache;
    }

    /*
    |--------------------------------------------------------------------------
    | Date helpers
    |--------------------------------------------------------------------------
    */

    private function loanOnDate(
        $loan
    ): Carbon {
        if (empty($loan->loan_on)) {
            throw new RuntimeException(
                "Loan reminder data error for loan "
                . "{$loan->loan_id}: loan_on is missing."
            );
        }

        try {
            return Carbon::parse(
                $loan->loan_on,
                'Africa/Nairobi'
            )
                ->startOfDay();
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Loan reminder data error for loan "
                . "{$loan->loan_id}: loan_on is invalid."
            );
        }
    }

    private function isValidPeriod(
        $value
    ): bool {
        if (!is_scalar($value)) {
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
                $date->format('Ym')
                    === $period;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function periodToMonth(
        string $period
    ): Carbon {
        if (
            !$this->isValidPeriod(
                $period
            )
        ) {
            throw new RuntimeException(
                "Invalid loan period {$period}."
            );
        }

        return Carbon::createFromFormat(
            '!Ym',
            trim($period),
            'Africa/Nairobi'
        )
            ->startOfMonth()
            ->startOfDay();
    }

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

        $day =
            min(
                max(
                    1,
                    $anchorDay
                ),
                $nextMonth->daysInMonth
            );

        return $nextMonth
            ->copy()
            ->day($day)
            ->startOfDay();
    }

    private function isShortTermLoan(
        $loan
    ): bool {
        $duration =
            $loan->loan_type_duration
            ?? null;

        return is_numeric($duration)
            &&
            (int) $duration <= 1;
    }
}