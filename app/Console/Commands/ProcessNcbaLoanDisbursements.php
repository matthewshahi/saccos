<?php

namespace App\Console\Commands;

use App\Services\NcbaOpenBankingService;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessNcbaLoanDisbursements extends Command
{
    /**
     * This command can only process NCBA transactions.
     */
    private const PROVIDER = 'NCBA';

    /**
     * The current NCBA implementation disburses to M-Pesa.
     */
    private const CHANNEL = 'MPESA';

    /**
     * Prevent excessively large manual batches.
     */
    private const MAX_BATCH_SIZE = 10;

    /**
     * Minimum amount accepted for NCBA M-Pesa disbursement.
     */
    private const MINIMUM_AMOUNT = 50;

    /**
     * Maximum number of retries after an explicit unsuccessful bank response.
     */
    private const MAX_RETRY_ATTEMPTS = 5;

    /**
     * Delay between explicit failed-response retries.
     */
    private const RETRY_DELAY_MINUTES = 10;

    /**
     * SENDING records older than this are reported but not automatically reset.
     *
     * Automatically resending an uncertain transaction could result in a
     * duplicate payment.
     */
    private const STALE_SENDING_MINUTES = 15;

    protected $signature = 'ncba:process-loan-disbursements
                            {--live : Actually send requests to NCBA}
                            {--limit=1 : Number of records to process, capped at 10}
                            {--show-payload : Display generated payloads during dry run}';

    protected $description = 'Process queued SACCO loan disbursements through NCBA Open Banking';

    /**
     * Execute the command.
     */
    public function handle(NcbaOpenBankingService $ncba): int
    {
        /*
        |--------------------------------------------------------------------------
        | Global disbursement validation
        |--------------------------------------------------------------------------
        */

        if (!(bool) config('disbursements.enabled', false)) {
            $this->warn(
                'Loan disbursement processing is disabled. '
                . 'Set LOAN_DISBURSEMENT_ENABLED=true to enable it.'
            );

            return self::SUCCESS;
        }

        $selectedProvider = strtoupper(
            trim(
                (string) config('disbursements.provider')
            )
        );

        if ($selectedProvider === '') {
            $this->error(
                'LOAN_DISBURSEMENT_PROVIDER has not been configured.'
            );

            return self::FAILURE;
        }

        /*
         * Each SACCO uses one provider.
         *
         * If this installation is configured for another provider, the NCBA
         * command exits safely without touching any disbursement records.
         */
        if ($selectedProvider !== self::PROVIDER) {
            $this->info(
                sprintf(
                    'NCBA processor skipped. Configured provider is [%s].',
                    $selectedProvider
                )
            );

            return self::SUCCESS;
        }

        $selectedChannel = strtoupper(
            trim(
                (string) config('disbursements.channel')
            )
        );

        if ($selectedChannel !== self::CHANNEL) {
            $this->error(
                sprintf(
                    'The NCBA processor currently supports channel [%s], '
                    . 'but [%s] is configured.',
                    self::CHANNEL,
                    $selectedChannel !== ''
                        ? $selectedChannel
                        : 'NONE'
                )
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | NCBA provider configuration
        |--------------------------------------------------------------------------
        */

        $ncbaConfiguration = config(
            'disbursements.providers.ncba'
        );

        if (!is_array($ncbaConfiguration)) {
            $this->error(
                'NCBA configuration is missing from config/disbursements.php.'
            );

            return self::FAILURE;
        }

        if (
            !(bool) (
                $ncbaConfiguration['enabled']
                ?? false
            )
        ) {
            $this->error(
                'NCBA is the selected provider, but NCBA_ENABLED is not true.'
            );

            return self::FAILURE;
        }

        $requiredSettings = [
            'base_url',
            'user_id',
            'password',
            'subscription_key',
            'debit_account',
            'country_code',
            'sender_country',
            'currency',
        ];

        foreach ($requiredSettings as $setting) {
            $value = $ncbaConfiguration[$setting] ?? null;

            if (
                $value === null
                || trim((string) $value) === ''
            ) {
                $this->error(
                    sprintf(
                        'Missing required NCBA configuration: %s.',
                        strtoupper($setting)
                    )
                );

                return self::FAILURE;
            }
        }

        /*
         * Ensure the generic and provider-specific currencies agree.
         */
        $genericCurrency = strtoupper(
            trim(
                (string) config(
                    'disbursements.currency',
                    'KES'
                )
            )
        );

        $ncbaCurrency = strtoupper(
            trim(
                (string) (
                    $ncbaConfiguration['currency']
                    ?? ''
                )
            )
        );

        if ($genericCurrency !== $ncbaCurrency) {
            $this->error(
                sprintf(
                    'Currency configuration mismatch: '
                    . 'LOAN_DISBURSEMENT_CURRENCY=%s but NCBA_CURRENCY=%s.',
                    $genericCurrency,
                    $ncbaCurrency
                )
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Processing mode
        |--------------------------------------------------------------------------
        */

        $requestedLimit = (int) $this->option('limit');

        if ($requestedLimit < 1) {
            $this->error(
                'The --limit option must be at least 1.'
            );

            return self::FAILURE;
        }

        $limit = min(
            $requestedLimit,
            self::MAX_BATCH_SIZE
        );

        $live = (bool) $this->option('live');

        /*
         * Live transmission requires both:
         *
         * 1. --live
         * 2. NCBA_DRY_RUN=false
         */
        if (
            $live
            && (bool) (
                $ncbaConfiguration['dry_run']
                ?? true
            )
        ) {
            $this->error(
                'Live mode is blocked because NCBA_DRY_RUN is true. '
                . 'Set NCBA_DRY_RUN=false before using --live.'
            );

            return self::FAILURE;
        }

        $this->line(
            sprintf(
                'Provider: %s | Channel: %s | Currency: %s | Limit: %d',
                self::PROVIDER,
                self::CHANNEL,
                $genericCurrency,
                $limit
            )
        );

        if (!$live) {
            $this->warn(
                'DRY RUN MODE: payloads will be generated, but no request '
                . 'will be sent to NCBA.'
            );

            return $this->processDryRunBatch(
                $ncba,
                $limit
            );
        }

        /*
         * Do not automatically reset uncertain SENDING transactions.
         */
        $this->warnAboutStaleSendingRecords();

        /*
        |--------------------------------------------------------------------------
        | Live processing
        |--------------------------------------------------------------------------
        */

        for ($i = 0; $i < $limit; $i++) {
            $processed = $this->processOneLive($ncba);

            if (!$processed) {
                if ($i === 0) {
                    $this->info(
                        'No eligible NCBA disbursement found.'
                    );
                }

                break;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Process a dry-run batch without repeatedly selecting the same row.
     */
    private function processDryRunBatch(
        NcbaOpenBankingService $ncba,
        int $limit
    ): int {
        $rows = $this->eligibleDisbursementsQuery()
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info(
                'No eligible NCBA disbursement found.'
            );

            return self::SUCCESS;
        }

        $successful = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                /*
                 * Dry run only builds the request payload.
                 * It must never invoke the NCBA endpoint.
                 */
                $payload = $ncba->buildMpesaPayload($row);

                DB::table('sacco_loan_disbursements')
                    ->where('id', $row->id)
                    ->update([
                        'request_payload' => $this->encodeJson(
                            $payload
                        ),
                        

                        'response_payload' => $this->encodeJson([
                            'message' =>
                                'Dry run only. No request was sent to NCBA.',
                        ]),

                        'bank_status_message' =>
                            'DRY RUN ONLY - payload generated, no request sent',

                        'last_error' => null,

                        'updated_at' => now(),
                    ]);

                $successful++;

                $this->info(
                    "DRY RUN OK: {$row->transaction_ref}"
                );

                if ((bool) $this->option('show-payload')) {
                    $this->line(
                        json_encode(
                            $payload,
                            JSON_PRETTY_PRINT
                            | JSON_UNESCAPED_SLASHES
                            | JSON_UNESCAPED_UNICODE
                        )
                    );
                }
            } catch (Throwable $exception) {
                $failed++;

                DB::table('sacco_loan_disbursements')
                    ->where('id', $row->id)
                    ->update([
                        'last_error' => $exception->getMessage(),

                        'bank_status_message' =>
                            'DRY RUN PAYLOAD GENERATION FAILED',

                        'updated_at' => now(),
                    ]);

                Log::error(
                    'NCBA disbursement dry run failed',
                    [
                        'disbursement_id' => $row->id,
                        'loan_id' => $row->loan_id,
                        'transaction_ref' =>
                            $row->transaction_ref,
                        'exception' => $exception,
                    ]
                );

                $this->error(
                    sprintf(
                        'DRY RUN FAILED: %s - %s',
                        $row->transaction_ref,
                        $exception->getMessage()
                    )
                );
            }
        }

        $this->newLine();

        $this->table(
            ['Dry-run result', 'Count'],
            [
                ['Payloads generated', $successful],
                ['Failed', $failed],
            ]
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * Claim and process one live NCBA disbursement.
     */
    private function processOneLive(
        NcbaOpenBankingService $ncba
    ): bool {
        /*
         * Claim one eligible record.
         *
         * The transaction and row lock prevent another process from claiming
         * the same record simultaneously.
         */
        $row = DB::transaction(function () {
            $row = $this->eligibleDisbursementsQuery()
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return null;
            }

            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->update([
                    'status' => 'SENDING',
                    'next_retry_at' => null,
                    'updated_at' => now(),
                ]);

            return $row;
        }, 3);

        if ($row === null) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Build and save payload before transmission
        |--------------------------------------------------------------------------
        |
        | A payload-generation failure occurs before contacting NCBA and can
        | safely be classified as a validation failure.
        |
        */

        try {
            $payload = $ncba->buildMpesaPayload($row);

            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->where('status', 'SENDING')
                ->update([
                    'request_payload' => $this->encodeJson(
                        $payload
                    ),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->where('status', 'SENDING')
                ->update([
                    'status' => 'VALIDATION_FAILED',

                    'bank_status_message' =>
                        'NCBA payload generation failed',

                    'last_error' => $exception->getMessage(),

                    'next_retry_at' => null,

                    'updated_at' => now(),
                ]);

            Log::error(
                'NCBA payload generation failed',
                [
                    'disbursement_id' => $row->id,
                    'loan_id' => $row->loan_id,
                    'transaction_ref' =>
                        $row->transaction_ref,
                    'exception' => $exception,
                ]
            );

            $this->error(
                sprintf(
                    'PAYLOAD FAILED: %s - %s',
                    $row->transaction_ref,
                    $exception->getMessage()
                )
            );

            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Send to NCBA
        |--------------------------------------------------------------------------
        */

        try {
            $result = $ncba->sendMpesaDisbursement($row);

            $response = is_array(
                $result['response'] ?? null
            )
                ? $result['response']
                : [];

            $parsed = $this->parseBankResponse(
                $response
            );

            if ($parsed['succeeded']) {
                DB::table('sacco_loan_disbursements')
                    ->where('id', $row->id)
                    ->where('status', 'SENDING')
                    ->update([
                        'status' => 'SENT_TO_BANK',

                        'sent_at' => now(),

                        'bank_reference' =>
                            $parsed['bank_reference'],

                        'core_reference' =>
                            $parsed['core_reference']
                            ?: $row->core_reference,

                        'bank_status_code' =>
                            $parsed['status_code'],

                        'bank_status_message' =>
                            $parsed['message']
                            ?: 'Request accepted by NCBA',

                        'request_payload' => $this->encodeJson(
                            $result['payload']
                            ?? $payload
                        ),

                        'response_payload' => $this->encodeJson(
                            $response
                        ),

                        'next_retry_at' => null,

                        'last_error' => null,

                        'updated_at' => now(),
                    ]);

                Log::info(
                    'NCBA disbursement sent to bank',
                    [
                        'disbursement_id' => $row->id,
                        'loan_id' => $row->loan_id,
                        'transaction_ref' =>
                            $row->transaction_ref,
                        'bank_reference' =>
                            $parsed['bank_reference'],
                        'core_reference' =>
                            $parsed['core_reference'],
                    ]
                );

                $this->info(
                    "SENT TO BANK: {$row->transaction_ref}"
                );

                $this->line(
                    'Bank Ref: '
                    . (
                        $parsed['bank_reference']
                        ?: 'N/A'
                    )
                );

                $this->line(
                    'Core Ref: '
                    . (
                        $parsed['core_reference']
                        ?: 'N/A'
                    )
                );

                return true;
            }

            /*
             * NCBA returned an explicit non-success response.
             *
             * This is different from a transport exception because the bank
             * returned a response. Controlled retries are permitted, up to the
             * configured maximum.
             */
            $nextRetryCount =
                (int) $row->retry_count + 1;

            $retryExhausted =
                $nextRetryCount >= self::MAX_RETRY_ATTEMPTS;

            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->where('status', 'SENDING')
                ->update([
                    'status' => $retryExhausted
                        ? 'FAILED_FINAL'
                        : 'FAILED_RETRY',

                    'sent_at' => now(),

                    'bank_reference' =>
                        $parsed['bank_reference'],

                    'core_reference' =>
                        $parsed['core_reference']
                        ?: $row->core_reference,

                    'bank_status_code' =>
                        $parsed['status_code'],

                    'bank_status_message' =>
                        $parsed['message']
                        ?: 'NCBA returned a non-success response',

                    'request_payload' => $this->encodeJson(
                        $result['payload']
                        ?? $payload
                    ),

                    'response_payload' => $this->encodeJson(
                        $response
                    ),

                    'retry_count' => $nextRetryCount,

                    'next_retry_at' => $retryExhausted
                        ? null
                        : now()->addMinutes(
                            self::RETRY_DELAY_MINUTES
                        ),

                    'last_error' => $this->encodeJson(
                        $response
                    ),

                    'updated_at' => now(),
                ]);

            Log::warning(
                'NCBA returned a non-success disbursement response',
                [
                    'disbursement_id' => $row->id,
                    'loan_id' => $row->loan_id,
                    'transaction_ref' =>
                        $row->transaction_ref,
                    'status_code' =>
                        $parsed['status_code'],
                    'message' =>
                        $parsed['message'],
                    'retry_count' =>
                        $nextRetryCount,
                    'retry_exhausted' =>
                        $retryExhausted,
                    'response' => $response,
                ]
            );

            if ($retryExhausted) {
                $this->error(
                    sprintf(
                        'NCBA REJECTED - RETRIES EXHAUSTED: %s',
                        $row->transaction_ref
                    )
                );
            } else {
                $this->warn(
                    sprintf(
                        'NCBA DID NOT CONFIRM SUCCESS: %s. '
                        . 'Retry %d of %d scheduled.',
                        $row->transaction_ref,
                        $nextRetryCount,
                        self::MAX_RETRY_ATTEMPTS
                    )
                );
            }

            return true;
        } catch (Throwable $exception) {
            /*
             * An exception during transmission is ambiguous.
             *
             * NCBA may have received the request even though the application
             * did not receive a response. Automatically retrying could result
             * in a duplicate disbursement.
             *
             * SEND_UNKNOWN must be checked by the NCBA confirmation process or
             * reviewed manually before any retry.
             */
            DB::table('sacco_loan_disbursements')
                ->where('id', $row->id)
                ->where('status', 'SENDING')
                ->update([
                    'status' => 'SEND_UNKNOWN',

                    'response_payload' => $this->encodeJson([
                        'exception' => $exception->getMessage(),
                    ]),

                    'bank_status_message' =>
                        'Transmission outcome unknown; confirmation required',

                    'next_retry_at' => null,

                    'last_error' => $exception->getMessage(),

                    'updated_at' => now(),
                ]);

            Log::critical(
                'NCBA disbursement transmission outcome is unknown',
                [
                    'disbursement_id' => $row->id,
                    'loan_id' => $row->loan_id,
                    'transaction_ref' =>
                        $row->transaction_ref,
                    'exception' => $exception,
                ]
            );

            $this->error(
                sprintf(
                    'SEND OUTCOME UNKNOWN: %s - %s',
                    $row->transaction_ref,
                    $exception->getMessage()
                )
            );

            return true;
        }
    }

    /**
     * Query records currently eligible for NCBA submission.
     */
    private function eligibleDisbursementsQuery(): Builder
    {
        return DB::table('sacco_loan_disbursements')
            ->where(
                'disbursement_provider',
                self::PROVIDER
            )
            ->where(
                'disbursement_channel',
                self::CHANNEL
            )
            ->whereNotNull('approved_by')
            ->where(
                'amount',
                '>=',
                self::MINIMUM_AMOUNT
            )
            ->where(function (Builder $query) {
                $query
                    ->where(
                        'status',
                        'READY_TO_SEND'
                    )
                    ->orWhere(function (Builder $retryQuery) {
                        $retryQuery
                            ->where(
                                'status',
                                'FAILED_RETRY'
                            )
                            ->where(
                                'retry_count',
                                '<',
                                self::MAX_RETRY_ATTEMPTS
                            )
                            ->whereNotNull(
                                'next_retry_at'
                            )
                            ->where(
                                'next_retry_at',
                                '<=',
                                now()
                            );
                    });
            })
            ->orderBy('id');
    }

    /**
     * Report SENDING records that may have been interrupted.
     *
     * They are deliberately not reset or resent automatically.
     */
    private function warnAboutStaleSendingRecords(): void
    {
        $staleRecords = DB::table(
            'sacco_loan_disbursements'
        )
            ->where(
                'disbursement_provider',
                self::PROVIDER
            )
            ->where(
                'status',
                'SENDING'
            )
            ->where(
                'updated_at',
                '<=',
                now()->subMinutes(
                    self::STALE_SENDING_MINUTES
                )
            )
            ->count();

        if ($staleRecords > 0) {
            $this->warn(
                sprintf(
                    '%d stale SENDING disbursement(s) require confirmation '
                    . 'or manual review. They will not be resent automatically.',
                    $staleRecords
                )
            );
        }
    }

    /**
     * Parse an NCBA response conservatively.
     */
    private function parseBankResponse(
        array $response
    ): array {
        $statusCode =
            $response['errorCode']
            ?? $response['ErrorCode']
            ?? $response['resErrorCode']
            ?? $response['statusCode']
            ?? $response['resultCode']
            ?? $response['data']['errorCode']
            ?? null;

        $message =
            $response['errorMessage']
            ?? $response['ErrorMessage']
            ?? $response['resErrorMessage']
            ?? $response['resErrorDesc']
            ?? $response['statusDescription']
            ?? $response['resultDesc']
            ?? $response['message']
            ?? $response['data']['message']
            ?? null;

        $messageUpper = strtoupper(
            trim(
                (string) $message
            )
        );

        /*
         * Success is deliberately conservative.
         *
         * Unknown responses must not be treated as successful.
         */
        $succeeded = (
            ($response['succeeded'] ?? false) === true
            || (string) $statusCode === '000'
            || $messageUpper === 'SUCCESS'
        );

        $bankReference =
            $response['bankRef']
            ?? $response['bankReference']
            ?? $response['bankReferenceNo']
            ?? $response['cbxReferenceNumber']
            ?? $response['resCbxReferenceNo']
            ?? $response['data']['bankRef']
            ?? $response['data']['bankReference']
            ?? null;

        $coreReference =
            $response['txnReferenceNo']
            ?? $response['transactionId']
            ?? $response['transactionID']
            ?? $response['CoreReference']
            ?? $response['coreReference']
            ?? $response['resCoreReferenceNo']
            ?? $response['data']['id']
            ?? $response['id']
            ?? null;

        return [
            'succeeded' => $succeeded,

            'status_code' => $statusCode !== null
                ? (string) $statusCode
                : null,

            'message' => $message !== null
                ? (string) $message
                : null,

            'bank_reference' => $bankReference !== null
                ? (string) $bankReference
                : null,

            'core_reference' => $coreReference !== null
                ? (string) $coreReference
                : null,
        ];
    }

    /**
     * Encode payloads consistently and fail visibly on invalid data.
     */
    private function encodeJson(
        mixed $value
    ): string {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
    }
}