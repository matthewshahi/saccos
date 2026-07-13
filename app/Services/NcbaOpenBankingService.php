<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NcbaOpenBankingService
{
    private const CONFIG_PREFIX =
        'disbursements.providers.ncba';

    private const TOKEN_ENDPOINT =
        '/api/v1/Auth/generate-token';

    private const MPESA_DISBURSEMENT_ENDPOINT =
        '/api/v1/MobileMoneyTransfer/mobilemoneytransfer';

    private const TRANSACTION_STATUS_ENDPOINT =
        '/api/v1/TransactionStatusQuery/transactionstatusquery';

    private const MINIMUM_MPESA_AMOUNT = 50.00;

    private const MAXIMUM_MPESA_AMOUNT = 250000.00;

    private const TOKEN_CACHE_MINUTES = 50;

    /**
     * Build the NCBA M-Pesa disbursement payload.
     */
    public function buildMpesaPayload(
        object $disbursement
    ): array {
        $this->validateConfiguration();

        $this->validateDisbursement(
            $disbursement
        );

        $amount = number_format(
            (float) $disbursement->amount,
            2,
            '.',
            ''
        );

        return [
            'beneficiaryName' => trim(
                (string) $disbursement->member_name
            ),

            'date' => now(
                'Africa/Nairobi'
            )->toDateString(),

            'debitAccount' => trim(
                (string) $this->config(
                    'debit_account'
                )
            ),

            'debitAmount' => $amount,

            'mobileNumber' => trim(
                (string) $disbursement->member_phone
            ),

            'transactionNarration' =>
                $this->cleanNarration(
                    $disbursement->narration
                    ?: 'LOAN DISBURSEMENT'
                ),

            'uniqueReferenceNumber' => trim(
                (string) $disbursement->transaction_ref
            ),
        ];
    }

    /**
     * Submit an M-Pesa disbursement through NCBA.
     */
    public function sendMpesaDisbursement(
        object $disbursement
    ): array {
        $this->validateConfiguration();

        $payload = $this->buildMpesaPayload(
            $disbursement
        );

        /*
         * Fail-safe protection.
         *
         * The Artisan command already controls live mode, but the service
         * itself must also refuse transmission when NCBA is disabled or
         * configured for dry-run operation.
         */
        if (
            !(bool) $this->config(
                'enabled',
                false
            )
            || (bool) $this->config(
                'dry_run',
                true
            )
        ) {
            return [
                'dry_run' => true,

                'endpoint' =>
                    self::MPESA_DISBURSEMENT_ENDPOINT,

                'payload' => $payload,

                'response' => [
                    'message' =>
                        'Dry run only. No request was sent to NCBA.',
                ],
            ];
        }

        $response = $this->post(
            self::MPESA_DISBURSEMENT_ENDPOINT,
            $payload
        );

        return [
            'dry_run' => false,

            'endpoint' =>
                self::MPESA_DISBURSEMENT_ENDPOINT,

            'payload' => $payload,

            'http_status' =>
                $response['http_status'],

            'response' =>
                $response['json'],
        ];
    }

    /**
     * Query NCBA for a transaction's current status.
     */
    public function queryTransactionStatus(
        string $transactionRef
    ): array {
        $this->validateConfiguration();

        $transactionRef = trim(
            $transactionRef
        );

        if ($transactionRef === '') {
            throw new RuntimeException(
                'Transaction reference is required for the NCBA status query.'
            );
        }

        $payload = [
            'country' => strtoupper(
                trim(
                    (string) $this->config(
                        'country_code',
                        'KE'
                    )
                )
            ),

            'transactionId' =>
                $transactionRef,
        ];

        $response = $this->post(
            self::TRANSACTION_STATUS_ENDPOINT,
            $payload
        );

        return [
            'endpoint' =>
                self::TRANSACTION_STATUS_ENDPOINT,

            'payload' => $payload,

            'http_status' =>
                $response['http_status'],

            'response' =>
                $response['json'],
        ];
    }

    /**
     * Send an authenticated request to NCBA.
     */
    private function post(
        string $endpoint,
        array $payload
    ): array {
        $token = $this->getToken();

        $response = Http::connectTimeout(15)
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' =>
                    trim(
                        (string) $this->config(
                            'subscription_key'
                        )
                    ),

                'Authorization' =>
                    'Bearer ' . $token,
            ])
            ->post(
                $this->buildUrl($endpoint),
                $payload
            );

        return $this->formatResponse(
            $response
        );
    }

    /**
     * Request and cache an NCBA authentication token.
     */
    private function getToken(): string
    {
        $cacheKey = $this->tokenCacheKey();

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(
                self::TOKEN_CACHE_MINUTES
            ),
            function (): string {
                $response = Http::connectTimeout(15)
                    ->timeout(30)
                    ->acceptJson()
                    ->asJson()
                    ->withHeaders([
                        'Ocp-Apim-Subscription-Key' =>
                            trim(
                                (string) $this->config(
                                    'subscription_key'
                                )
                            ),
                    ])
                    ->post(
                        $this->buildUrl(
                            self::TOKEN_ENDPOINT
                        ),
                        [
                            'userID' => trim(
                                (string) $this->config(
                                    'user_id'
                                )
                            ),

                            'password' => (string) $this->config(
                                'password'
                            ),
                        ]
                    );

                if (!$response->successful()) {
                    throw new RuntimeException(
                        sprintf(
                            'NCBA token request failed: HTTP %d - %s',
                            $response->status(),
                            $response->body()
                        )
                    );
                }

                $json = $response->json();

                if (!is_array($json)) {
                    throw new RuntimeException(
                        'NCBA token response was not valid JSON.'
                    );
                }

                $token =
                    $json['token']
                    ?? $json['access_token']
                    ?? $json['accessToken']
                    ?? $json['data']['token']
                    ?? $json['data']['access_token']
                    ?? $json['data']['accessToken']
                    ?? null;

                if (
                    $token === null
                    || trim((string) $token) === ''
                ) {
                    throw new RuntimeException(
                        'NCBA token response did not contain a '
                        . 'recognizable token field: '
                        . json_encode(
                            $json,
                            JSON_UNESCAPED_SLASHES
                            | JSON_UNESCAPED_UNICODE
                        )
                    );
                }

                return trim(
                    (string) $token
                );
            }
        );
    }

    /**
     * Validate the outgoing disbursement.
     */
    private function validateDisbursement(
        object $disbursement
    ): void {
        $amount = round(
            (float) (
                $disbursement->amount
                ?? 0
            ),
            2
        );

        if (
            $amount
            < self::MINIMUM_MPESA_AMOUNT
        ) {
            throw new RuntimeException(
                sprintf(
                    'Amount is below the NCBA M-Pesa minimum of KES %s.',
                    number_format(
                        self::MINIMUM_MPESA_AMOUNT,
                        2
                    )
                )
            );
        }

        if (
            $amount
            > self::MAXIMUM_MPESA_AMOUNT
        ) {
            throw new RuntimeException(
                sprintf(
                    'Amount is above the NCBA M-Pesa maximum of KES %s.',
                    number_format(
                        self::MAXIMUM_MPESA_AMOUNT,
                        2
                    )
                )
            );
        }

        $memberName = trim(
            (string) (
                $disbursement->member_name
                ?? ''
            )
        );

        if ($memberName === '') {
            throw new RuntimeException(
                'Member name is required.'
            );
        }

        $memberPhone = trim(
            (string) (
                $disbursement->member_phone
                ?? ''
            )
        );

        if (
            !preg_match(
                '/^254[17][0-9]{8}$/',
                $memberPhone
            )
        ) {
            throw new RuntimeException(
                'Invalid member phone. Expected format is '
                . '2547XXXXXXXX or 2541XXXXXXXX.'
            );
        }

        $transactionReference = trim(
            (string) (
                $disbursement->transaction_ref
                ?? ''
            )
        );

        if ($transactionReference === '') {
            throw new RuntimeException(
                'Missing transaction reference.'
            );
        }

        $provider = strtoupper(
            trim(
                (string) (
                    $disbursement->disbursement_provider
                    ?? ''
                )
            )
        );

        if (
            $provider !== ''
            && $provider !== 'NCBA'
        ) {
            throw new RuntimeException(
                sprintf(
                    'Disbursement provider [%s] is not NCBA.',
                    $provider
                )
            );
        }

        $channel = strtoupper(
            trim(
                (string) (
                    $disbursement->disbursement_channel
                    ?? ''
                )
            )
        );

        if ($channel !== 'MPESA') {
            throw new RuntimeException(
                sprintf(
                    'Unsupported NCBA disbursement channel [%s].',
                    $channel !== ''
                        ? $channel
                        : 'NONE'
                )
            );
        }

        $currency = strtoupper(
            trim(
                (string) (
                    $disbursement->currency
                    ?? ''
                )
            )
        );

        $configuredCurrency = strtoupper(
            trim(
                (string) $this->config(
                    'currency',
                    'KES'
                )
            )
        );

        if (
            $currency !== ''
            && $currency !== $configuredCurrency
        ) {
            throw new RuntimeException(
                sprintf(
                    'Disbursement currency [%s] does not match '
                    . 'the NCBA configured currency [%s].',
                    $currency,
                    $configuredCurrency
                )
            );
        }
    }

    /**
     * Validate all required NCBA configuration values.
     */
    private function validateConfiguration(): void
    {
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
            $value = $this->config(
                $setting
            );

            if (
                $value === null
                || trim((string) $value) === ''
            ) {
                throw new RuntimeException(
                    sprintf(
                        'Missing required NCBA configuration: %s.',
                        strtoupper($setting)
                    )
                );
            }
        }

        $baseUrl = trim(
            (string) $this->config(
                'base_url'
            )
        );

        if (
            filter_var(
                $baseUrl,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            throw new RuntimeException(
                'NCBA base URL is invalid.'
            );
        }

        $currency = strtoupper(
            trim(
                (string) $this->config(
                    'currency'
                )
            )
        );

        if (
            !preg_match(
                '/^[A-Z]{3}$/',
                $currency
            )
        ) {
            throw new RuntimeException(
                'NCBA currency must be a valid three-letter currency code.'
            );
        }
    }

    /**
     * Build a normalized NCBA request URL.
     */
    private function buildUrl(
        string $endpoint
    ): string {
        $baseUrl = rtrim(
            trim(
                (string) $this->config(
                    'base_url'
                )
            ),
            '/'
        );

        return $baseUrl
            . '/'
            . ltrim(
                $endpoint,
                '/'
            );
    }

    /**
     * Convert an HTTP response into a predictable structure.
     */
    private function formatResponse(
        Response $response
    ): array {
        $json = $response->json();

        if (!is_array($json)) {
            $json = [
                'raw' => $response->body(),
            ];
        }

        return [
            'http_status' =>
                $response->status(),

            'json' =>
                $json,
        ];
    }

    /**
     * Generate an environment-specific token cache key.
     */
    private function tokenCacheKey(): string
    {
        $identity = implode('|', [
            trim(
                (string) $this->config(
                    'base_url'
                )
            ),

            trim(
                (string) $this->config(
                    'user_id'
                )
            ),

            trim(
                (string) $this->config(
                    'debit_account'
                )
            ),
        ]);

        return 'ncba_open_banking_token:'
            . sha1($identity);
    }

    /**
     * Read an NCBA value from config/disbursements.php.
     */
    private function config(
        string $key,
        mixed $default = null
    ): mixed {
        return config(
            self::CONFIG_PREFIX
            . '.'
            . $key,
            $default
        );
    }

    /**
     * Prepare a bank-safe narration.
     */
    private function cleanNarration(
        string $narration
    ): string {
        $narration = strtoupper(
            trim($narration)
        );

        $narration = preg_replace(
            '/[^A-Z0-9 ]/',
            '',
            $narration
        );

        $narration = preg_replace(
            '/\s+/',
            ' ',
            (string) $narration
        );

        return substr(
            trim((string) $narration),
            0,
            50
        );
    }
}