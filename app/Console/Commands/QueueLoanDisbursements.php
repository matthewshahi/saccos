<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueLoanDisbursements extends Command
{
    /**
     * Absolute maximum number of loans processed per execution.
     */
    private const MAX_BATCH_SIZE = 10;

    /**
     * Only loans created within the previous 24 hours are eligible.
     */
    private const LOAN_WINDOW_HOURS = 24;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'loans:queue-disbursements
                            {--limit=10 : Maximum loans to process, capped at 10}
                            {--dry-run : Display eligible loans without creating records}';

    /**
     * The console command description.
     */
    protected $description = 'Queue recent approved loans whose loan types allow instant disbursement';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /*
        |--------------------------------------------------------------------------
        | Global disbursement switch
        |--------------------------------------------------------------------------
        */

        if (!(bool) config('disbursements.enabled', false)) {
            $this->warn(
                'Loan disbursement processing is disabled. '
                . 'Set LOAN_DISBURSEMENT_ENABLED=true to enable it.'
            );

            return self::SUCCESS;
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve global SACCO routing
        |--------------------------------------------------------------------------
        |
        | Each SACCO deployment uses one configured outbound provider.
        |
        | Example:
        | Provider: NCBA
        | Channel:  MPESA
        | Currency: KES
        |
        */

        $provider = strtoupper(
            trim(
                (string) config('disbursements.provider')
            )
        );

        $channel = strtoupper(
            trim(
                (string) config('disbursements.channel')
            )
        );

        $currency = strtoupper(
            trim(
                (string) config(
                    'disbursements.currency',
                    'KES'
                )
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Validate global routing
        |--------------------------------------------------------------------------
        */

        if (
            $provider === ''
            || !preg_match('/^[A-Z0-9_-]{2,30}$/', $provider)
        ) {
            $this->error(
                'Invalid or missing LOAN_DISBURSEMENT_PROVIDER configuration.'
            );

            return self::FAILURE;
        }

        if (
            $channel === ''
            || !preg_match('/^[A-Z0-9_-]{2,30}$/', $channel)
        ) {
            $this->error(
                'Invalid or missing LOAN_DISBURSEMENT_CHANNEL configuration.'
            );

            return self::FAILURE;
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $this->error(
                'Invalid LOAN_DISBURSEMENT_CURRENCY configuration.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate selected provider
        |--------------------------------------------------------------------------
        |
        | The provider must exist under:
        |
        | config/disbursements.php
        | providers.{provider}
        |
        | and must explicitly be enabled.
        |
        */

        $providerKey = strtolower($provider);

        $providerConfiguration = config(
            "disbursements.providers.{$providerKey}"
        );

        if (!is_array($providerConfiguration)) {
            $this->error(
                "The configured disbursement provider [{$provider}] "
                . 'does not exist in config/disbursements.php.'
            );

            return self::FAILURE;
        }

        if (
            !(bool) (
                $providerConfiguration['enabled']
                ?? false
            )
        ) {
            $this->error(
                "The configured disbursement provider [{$provider}] "
                . 'is disabled.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Controlled batch size
        |--------------------------------------------------------------------------
        */

        $requestedLimit = (int) $this->option('limit');

        if ($requestedLimit < 1) {
            $this->error(
                'The --limit option must be at least 1.'
            );

            return self::FAILURE;
        }

        /*
         * Even if someone manually supplies --limit=100, this command
         * cannot process more than 10 loans in one execution.
         */
        $limit = min(
            $requestedLimit,
            self::MAX_BATCH_SIZE
        );

        /*
        |--------------------------------------------------------------------------
        | Rolling loan eligibility window
        |--------------------------------------------------------------------------
        */

        $now = CarbonImmutable::now('Africa/Nairobi');

        $cutoff = $now->subHours(
            self::LOAN_WINDOW_HOURS
        );

        $this->line(
            sprintf(
                'Provider: %s | Channel: %s | Currency: %s',
                $provider,
                $channel,
                $currency
            )
        );

        $this->line(
            sprintf(
                'Checking a maximum of %d loan(s) created between %s and %s.',
                $limit,
                $cutoff->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s')
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Retrieve eligible loans
        |--------------------------------------------------------------------------
        */

        try {
            $loans = $this->getEligibleLoans(
                $limit,
                $cutoff,
                $now
            );
        } catch (Throwable $exception) {
            $this->error(
                'Unable to read eligible loans: '
                . $exception->getMessage()
            );

            Log::error(
                'Unable to retrieve loans for disbursement queue',
                [
                    'provider'  => $provider,
                    'channel'   => $channel,
                    'currency'  => $currency,
                    'cutoff'    => $cutoff->format('Y-m-d H:i:s'),
                    'now'       => $now->format('Y-m-d H:i:s'),
                    'limit'     => $limit,
                    'exception' => $exception,
                ]
            );

            return self::FAILURE;
        }

        if ($loans->isEmpty()) {
            $this->info(
                'No eligible instant-disbursement loans were found.'
            );

            return self::SUCCESS;
        }

        /*
        |--------------------------------------------------------------------------
        | Dry run
        |--------------------------------------------------------------------------
        */

        if ((bool) $this->option('dry-run')) {
            return $this->displayDryRun(
                $loans,
                $cutoff,
                $now,
                $provider,
                $channel,
                $currency
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Queue loans
        |--------------------------------------------------------------------------
        */

        $results = [
            'ready'              => 0,
            'validation_failed'  => 0,
            'already_queued'     => 0,
            'no_longer_eligible' => 0,
            'failed'             => 0,
        ];

        foreach ($loans as $candidate) {
            try {
                $result = DB::transaction(
                    function () use (
                        $candidate,
                        $cutoff,
                        $now,
                        $provider,
                        $channel,
                        $currency
                    ): string {
                        /*
                         * Lock the source loan.
                         *
                         * This prevents concurrent command executions from
                         * queueing the same loan simultaneously.
                         */
                        $loan = DB::table('sacco_loans')
                            ->where(
                                'loan_id',
                                $candidate->loan_id
                            )
                            ->lockForUpdate()
                            ->first();

                        if ($loan === null) {
                            return 'NO_LONGER_ELIGIBLE';
                        }

                        /*
                         * Recheck for an existing disbursement after obtaining
                         * the source-loan lock.
                         */
                        $alreadyExists = DB::table(
                            'sacco_loan_disbursements'
                        )
                            ->where(
                                'loan_id',
                                $loan->loan_id
                            )
                            ->exists();

                        if ($alreadyExists) {
                            return 'ALREADY_QUEUED';
                        }

                        /*
                         * Recheck that the loan remains eligible.
                         */
                        if (
                            !$this->loanIsStillEligible(
                                $loan,
                                $cutoff,
                                $now
                            )
                        ) {
                            return 'NO_LONGER_ELIGIBLE';
                        }

                        /*
                         * Recheck the loan type.
                         */
                        $loanType = DB::table(
                            'sacco_loan_types'
                        )
                            ->where(
                                'loan_type_id',
                                $loan->loan_loan_type
                            )
                            ->first();

                        if (
                            !$this->loanTypeAllowsInstantDisbursement(
                                $loanType
                            )
                        ) {
                            return 'NO_LONGER_ELIGIBLE';
                        }

                        /*
                         * Recheck the member.
                         */
                        $member = DB::table('sacco_members')
                            ->where(
                                'member_id',
                                $loan->loan_member
                            )
                            ->first();

                        if (!$this->memberIsEligible($member)) {
                            return 'NO_LONGER_ELIGIBLE';
                        }

                        $amount = round(
                            (float) $loan->loan_net_disbursement,
                            2
                        );

                        if ($amount <= 0) {
                            return 'NO_LONGER_ELIGIBLE';
                        }

                        /*
                         * Prepare member details.
                         */
                        $memberName = trim(
                            (string) (
                                $member->member_name
                                ?? ''
                            )
                        );

                        $memberPhone = $this->normalizeKenyanPhone(
                            $member->member_phone_no
                            ?? null
                        );

                        $validationErrors = [];

                        if ($memberName === '') {
                            $validationErrors[] =
                                'Member name is missing';
                        }

                        if ($memberPhone === null) {
                            $validationErrors[] =
                                'Member phone number is missing or invalid';
                        }

                        $hasValidationError =
                            count($validationErrors) > 0;

                        /*
                         * Save an auditable record even if validation fails.
                         *
                         * VALIDATION_FAILED records cannot be picked by the
                         * provider sender because it only selects
                         * READY_TO_SEND records.
                         */
                        $storedMemberName = $memberName !== ''
                            ? substr($memberName, 0, 150)
                            : 'MEMBER ' . $member->member_id;

                        $storedMemberPhone = $memberPhone
                            ?? $this->phoneForAudit(
                                $member->member_phone_no
                                ?? null
                            );

                        $status = $hasValidationError
                            ? 'VALIDATION_FAILED'
                            : 'READY_TO_SEND';

                        $lastError = $hasValidationError
                            ? implode(
                                '; ',
                                $validationErrors
                            )
                            : null;

                        /*
                         * Deterministic transaction reference.
                         *
                         * The same loan always produces the same reference.
                         */
                        $transactionReference =
                            'LOAN-DISB-' . $loan->loan_id;

                        $narration = $this->buildNarration(
                            $loanType->loan_type_code
                            ?? null,
                            $loan->loan_id
                        );

                        $timestamp = $now->format(
                            'Y-m-d H:i:s'
                        );

                        DB::table(
                            'sacco_loan_disbursements'
                        )->insert([
                            'loan_id' => $loan->loan_id,

                            'member_id' => $member->member_id,

                            'member_name' => $storedMemberName,

                            'member_phone' => $storedMemberPhone,

                            'amount' => number_format(
                                $amount,
                                2,
                                '.',
                                ''
                            ),

                            /*
                             * Snapshot the configured SACCO routing.
                             */
                            'currency' => $currency,

                            'disbursement_channel' => $channel,

                            'disbursement_provider' => $provider,

                            'transaction_ref' =>
                                $transactionReference,

                            'narration' => $narration,

                            'status' => $status,

                            /*
                             * The loan already completed the normal SACCO
                             * application and approval workflow.
                             */
                            'approved_by' => $loan->loan_by,

                            'approved_at' => $timestamp,

                            'sent_at' => null,

                            'confirmed_at' => null,

                            'bank_reference' => null,

                            'core_reference' =>
                                $loan->loan_doc_no,

                            'bank_status_code' => null,

                            'bank_status_message' => null,

                            'retry_count' => 0,

                            'next_retry_at' => null,

                            'last_error' => $lastError,

                            'request_payload' => null,

                            'response_payload' => null,

                            'created_by' => $loan->loan_by,

                            'created_at' => $timestamp,

                            'updated_at' => $timestamp,
                        ]);

                        return $hasValidationError
                            ? 'VALIDATION_FAILED'
                            : 'READY_TO_SEND';
                    },
                    3
                );

                switch ($result) {
                    case 'READY_TO_SEND':
                        $results['ready']++;

                        $this->info(
                            sprintf(
                                'Loan %s queued for %s as READY_TO_SEND.',
                                $candidate->loan_id,
                                $provider
                            )
                        );
                        break;

                    case 'VALIDATION_FAILED':
                        $results['validation_failed']++;

                        $this->warn(
                            sprintf(
                                'Loan %s recorded as VALIDATION_FAILED.',
                                $candidate->loan_id
                            )
                        );
                        break;

                    case 'ALREADY_QUEUED':
                        $results['already_queued']++;

                        $this->line(
                            sprintf(
                                'Loan %s was already queued.',
                                $candidate->loan_id
                            )
                        );
                        break;

                    default:
                        $results['no_longer_eligible']++;

                        $this->warn(
                            sprintf(
                                'Loan %s is no longer eligible.',
                                $candidate->loan_id
                            )
                        );
                        break;
                }
            } catch (QueryException $exception) {
                /*
                 * MySQL error 1062 indicates that another process inserted
                 * the same loan or transaction reference first.
                 */
                if (
                    (int) (
                        $exception->errorInfo[1]
                        ?? 0
                    ) === 1062
                ) {
                    $results['already_queued']++;

                    $this->line(
                        sprintf(
                            'Loan %s was already queued by another process.',
                            $candidate->loan_id
                        )
                    );

                    continue;
                }

                $results['failed']++;

                $this->error(
                    sprintf(
                        'Loan %s could not be queued: %s',
                        $candidate->loan_id,
                        $exception->getMessage()
                    )
                );

                Log::error(
                    'Database error while queueing loan disbursement',
                    [
                        'loan_id'   => $candidate->loan_id,
                        'provider'  => $provider,
                        'channel'   => $channel,
                        'exception' => $exception,
                    ]
                );
            } catch (Throwable $exception) {
                $results['failed']++;

                $this->error(
                    sprintf(
                        'Loan %s could not be queued: %s',
                        $candidate->loan_id,
                        $exception->getMessage()
                    )
                );

                Log::error(
                    'Unexpected error while queueing loan disbursement',
                    [
                        'loan_id'   => $candidate->loan_id,
                        'provider'  => $provider,
                        'channel'   => $channel,
                        'exception' => $exception,
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Results
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->table(
            [
                'Result',
                'Count',
            ],
            [
                [
                    'Queued as READY_TO_SEND',
                    $results['ready'],
                ],
                [
                    'Recorded as VALIDATION_FAILED',
                    $results['validation_failed'],
                ],
                [
                    'Already queued',
                    $results['already_queued'],
                ],
                [
                    'No longer eligible',
                    $results['no_longer_eligible'],
                ],
                [
                    'Failed',
                    $results['failed'],
                ],
            ]
        );

        Log::info(
            'Loan disbursement queue command completed',
            [
                'provider'           => $provider,
                'channel'            => $channel,
                'currency'           => $currency,
                'window_start'       =>
                    $cutoff->format('Y-m-d H:i:s'),
                'window_end'         =>
                    $now->format('Y-m-d H:i:s'),
                'requested_limit'    => $requestedLimit,
                'effective_limit'    => $limit,
                'ready'              => $results['ready'],
                'validation_failed'  =>
                    $results['validation_failed'],
                'already_queued'     =>
                    $results['already_queued'],
                'no_longer_eligible' =>
                    $results['no_longer_eligible'],
                'failed'             => $results['failed'],
            ]
        );

        return $results['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * Return the oldest eligible loans created during the rolling window.
     */
    private function getEligibleLoans(
        int $limit,
        CarbonImmutable $cutoff,
        CarbonImmutable $now
    ): Collection {
        return DB::table('sacco_loans as l')
            ->join(
                'sacco_loan_types as lt',
                'lt.loan_type_id',
                '=',
                'l.loan_loan_type'
            )
            ->join(
                'sacco_members as m',
                'm.member_id',
                '=',
                'l.loan_member'
            )

            /*
             * Only loan types configured for instant disbursement.
             */
            ->where(
                'lt.loan_type_instant_disbursement',
                1
            )
            ->where(
                'lt.loan_type_active',
                1
            )
            ->where(function ($query) {
                $query
                    ->whereNull('lt.loan_type_deleted')
                    ->orWhere(
                        'lt.loan_type_deleted',
                        'N'
                    );
            })

            /*
             * Only unstopped loans with positive net disbursement.
             */
            ->where(function ($query) {
                $query
                    ->whereNull('l.loan_stoped')
                    ->orWhere(
                        'l.loan_stoped',
                        'N'
                    );
            })
            ->where(
                'l.loan_net_disbursement',
                '>',
                0
            )

            /*
             * Exact rolling 24-hour eligibility window.
             */
            ->where(
                'l.loan_on',
                '>=',
                $cutoff->format('Y-m-d H:i:s')
            )
            ->where(
                'l.loan_on',
                '<=',
                $now->format('Y-m-d H:i:s')
            )

            /*
             * Only active and non-deleted members.
             */
            ->where(
                'm.member_active',
                'Y'
            )
            ->where(function ($query) {
                $query
                    ->whereNull('m.member_deleted')
                    ->orWhere(
                        'm.member_deleted',
                        'N'
                    );
            })

            /*
             * Do not pick any loan that already has a disbursement record.
             *
             * Bank failures and retries must use the existing disbursement
             * record instead of creating another one.
             */
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from(
                        'sacco_loan_disbursements as d'
                    )
                    ->whereColumn(
                        'd.loan_id',
                        'l.loan_id'
                    );
            })
            ->select([
                'l.loan_id',
                'l.loan_member',
                'l.loan_loan_type',
                'l.loan_doc_no',
                'l.loan_description',
                'l.loan_net_disbursement',
                'l.loan_on',
                'l.loan_by',
                'lt.loan_type_name',
                'lt.loan_type_code',
                'm.member_id',
                'm.member_name',
                'm.member_phone_no',
                'm.member_sacco_id',
            ])

            /*
             * Controlled FIFO processing.
             */
            ->orderBy(
                'l.loan_on',
                'asc'
            )
            ->orderBy(
                'l.loan_id',
                'asc'
            )
            ->limit($limit)
            ->get();
    }

    /**
     * Confirm that the locked loan remains eligible.
     */
    private function loanIsStillEligible(
        object $loan,
        CarbonImmutable $cutoff,
        CarbonImmutable $now
    ): bool {
        $stopped = strtoupper(
            trim(
                (string) (
                    $loan->loan_stoped
                    ?? 'N'
                )
            )
        );

        if ($stopped !== 'N') {
            return false;
        }

        $amount = round(
            (float) (
                $loan->loan_net_disbursement
                ?? 0
            ),
            2
        );

        if ($amount <= 0) {
            return false;
        }

        if (empty($loan->loan_on)) {
            return false;
        }

        try {
            $loanDate = CarbonImmutable::parse(
                $loan->loan_on,
                'Africa/Nairobi'
            );
        } catch (Throwable) {
            return false;
        }

        if ($loanDate->lt($cutoff)) {
            return false;
        }

        if ($loanDate->gt($now)) {
            return false;
        }

        return true;
    }

    /**
     * Confirm that the loan type allows instant disbursement.
     */
    private function loanTypeAllowsInstantDisbursement(
        ?object $loanType
    ): bool {
        if ($loanType === null) {
            return false;
        }

        if (
            (int) (
                $loanType->loan_type_instant_disbursement
                ?? 0
            ) !== 1
        ) {
            return false;
        }

        if (
            (int) (
                $loanType->loan_type_active
                ?? 0
            ) !== 1
        ) {
            return false;
        }

        $deleted = strtoupper(
            trim(
                (string) (
                    $loanType->loan_type_deleted
                    ?? 'N'
                )
            )
        );

        return $deleted !== 'Y';
    }

    /**
     * Confirm that the member is active and not deleted.
     */
    private function memberIsEligible(
        ?object $member
    ): bool {
        if ($member === null) {
            return false;
        }

        $active = strtoupper(
            trim(
                (string) (
                    $member->member_active
                    ?? 'N'
                )
            )
        );

        if ($active !== 'Y') {
            return false;
        }

        $deleted = strtoupper(
            trim(
                (string) (
                    $member->member_deleted
                    ?? 'N'
                )
            )
        );

        return $deleted !== 'Y';
    }

    /**
     * Display eligible loans without creating disbursement records.
     */
    private function displayDryRun(
        Collection $loans,
        CarbonImmutable $cutoff,
        CarbonImmutable $now,
        string $provider,
        string $channel,
        string $currency
    ): int {
        $rows = [];

        foreach ($loans as $loan) {
            $memberName = trim(
                (string) (
                    $loan->member_name
                    ?? ''
                )
            );

            $memberPhone = $this->normalizeKenyanPhone(
                $loan->member_phone_no
                ?? null
            );

            $validation = [];

            if ($memberName === '') {
                $validation[] = 'Missing name';
            }

            if ($memberPhone === null) {
                $validation[] = 'Invalid phone';
            }

            $rows[] = [
                $loan->loan_id,
                $loan->loan_type_name,
                $memberName !== ''
                    ? $memberName
                    : 'MISSING',
                $memberPhone
                    ?? 'INVALID',
                $currency . ' ' . number_format(
                    (float) $loan->loan_net_disbursement,
                    2
                ),
                $provider,
                $channel,
                $loan->loan_on,
                count($validation) === 0
                    ? 'READY_TO_SEND'
                    : 'VALIDATION_FAILED: '
                        . implode(', ', $validation),
            ];
        }

        $this->table(
            [
                'Loan ID',
                'Loan type',
                'Member',
                'Phone',
                'Net amount',
                'Provider',
                'Channel',
                'Loan created',
                'Proposed status',
            ],
            $rows
        );

        $this->newLine();

        $this->warn(
            sprintf(
                'Dry run only. Window: %s to %s. No records were inserted.',
                $cutoff->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s')
            )
        );

        return self::SUCCESS;
    }

    /**
     * Normalize Kenyan mobile numbers to 254XXXXXXXXX.
     */
    private function normalizeKenyanPhone(
        ?string $phone
    ): ?string {
        $digits = preg_replace(
            '/\D+/',
            '',
            trim((string) $phone)
        );

        if (
            $digits === null
            || $digits === ''
        ) {
            return null;
        }

        /*
         * 002547XXXXXXXX becomes 2547XXXXXXXX.
         */
        if (str_starts_with($digits, '00254')) {
            $digits = substr(
                $digits,
                2
            );
        }

        /*
         * 07XXXXXXXX or 01XXXXXXXX becomes
         * 2547XXXXXXXX or 2541XXXXXXXX.
         */
        if (
            strlen($digits) === 10
            && str_starts_with(
                $digits,
                '0'
            )
        ) {
            $digits = '254' . substr(
                $digits,
                1
            );
        }

        /*
         * 7XXXXXXXX or 1XXXXXXXX becomes
         * 2547XXXXXXXX or 2541XXXXXXXX.
         */
        if (
            strlen($digits) === 9
            && in_array(
                substr($digits, 0, 1),
                ['7', '1'],
                true
            )
        ) {
            $digits = '254' . $digits;
        }

        if (
            !preg_match(
                '/^254[17][0-9]{8}$/',
                $digits
            )
        ) {
            return null;
        }

        return $digits;
    }

    /**
     * Retain an auditable phone value when validation fails.
     */
    private function phoneForAudit(
        ?string $phone
    ): string {
        $value = preg_replace(
            '/[^0-9+]/',
            '',
            trim((string) $phone)
        );

        if (
            $value === null
            || $value === ''
        ) {
            return 'INVALID';
        }

        return substr(
            $value,
            0,
            15
        );
    }

    /**
     * Build a provider-safe narration no longer than 80 characters.
     */
    private function buildNarration(
        ?string $loanTypeCode,
        int|string $loanId
    ): string {
        $loanTypeCode = strtoupper(
            trim(
                (string) $loanTypeCode
            )
        );

        $narration = $loanTypeCode !== ''
            ? sprintf(
                'LOAN DISBURSEMENT %s %s',
                $loanTypeCode,
                $loanId
            )
            : sprintf(
                'LOAN DISBURSEMENT %s',
                $loanId
            );

        return substr(
            $narration,
            0,
            80
        );
    }
}