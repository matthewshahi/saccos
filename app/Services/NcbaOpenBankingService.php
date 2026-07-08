<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NcbaOpenBankingService
{
    public function buildMpesaPayload(object $disbursement): array
    {
        $amount = number_format((float) $disbursement->amount, 2, '.', '');

        return [
            'beneficiaryName' => $disbursement->member_name,
            'date' => now()->toDateString(),
            'debitAccount' => config('services.ncba.debit_account'),
            'debitAmount' => $amount,
            'mobileNumber' => $disbursement->member_phone,
            'transactionNarration' => $this->cleanNarration($disbursement->narration ?: 'LOAN DISBURSEMENT'),
            'uniqueReferenceNumber' => $disbursement->transaction_ref,
        ];
    }

    public function sendMpesaDisbursement(object $disbursement): array
    {
        $this->validateDisbursement($disbursement);

        $payload = $this->buildMpesaPayload($disbursement);

        if (! config('services.ncba.enabled') || config('services.ncba.dry_run')) {
            return [
                'dry_run' => true,
                'endpoint' => '/api/v1/MobileMoneyTransfer/mobilemoneytransfer',
                'payload' => $payload,
                'response' => [
                    'message' => 'Dry run only. No request was sent to NCBA.',
                ],
            ];
        }

        $response = $this->post('/api/v1/MobileMoneyTransfer/mobilemoneytransfer', $payload);

        return [
            'dry_run' => false,
            'endpoint' => '/api/v1/MobileMoneyTransfer/mobilemoneytransfer',
            'payload' => $payload,
            'http_status' => $response['http_status'],
            'response' => $response['json'],
        ];
    }

    public function queryTransactionStatus(string $transactionRef): array
    {
        $payload = [
            'country' => config('services.ncba.country_code', 'KE'),
            'transactionId' => $transactionRef,
        ];

        $response = $this->post('/api/v1/TransactionStatusQuery/transactionstatusquery', $payload);

        return [
            'endpoint' => '/api/v1/TransactionStatusQuery/transactionstatusquery',
            'payload' => $payload,
            'http_status' => $response['http_status'],
            'response' => $response['json'],
        ];
    }

    private function post(string $endpoint, array $payload): array
    {
        $token = $this->getToken();

        $response = Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Ocp-Apim-Subscription-Key' => config('services.ncba.subscription_key'),
                'Authorization' => 'Bearer ' . $token,
            ])
            ->post(config('services.ncba.base_url') . $endpoint, $payload);

        return [
            'http_status' => $response->status(),
            'json' => $response->json() ?? [
                'raw' => $response->body(),
            ],
        ];
    }

    private function getToken(): string
    {
        return Cache::remember('ncba_open_banking_token', now()->addMinutes(50), function () {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Ocp-Apim-Subscription-Key' => config('services.ncba.subscription_key'),
                ])
                ->post(config('services.ncba.base_url') . '/api/v1/Auth/generate-token', [
                    'userID' => config('services.ncba.user_id'),
                    'password' => config('services.ncba.password'),
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('NCBA token request failed: HTTP ' . $response->status() . ' - ' . $response->body());
            }

            $json = $response->json();

            $token = $json['token']
                ?? $json['access_token']
                ?? $json['accessToken']
                ?? $json['data']['token']
                ?? $json['data']['access_token']
                ?? $json['data']['accessToken']
                ?? null;

            if (! $token) {
                throw new RuntimeException('NCBA token response did not contain a recognizable token field: ' . json_encode($json));
            }

            return $token;
        });
    }

    private function validateDisbursement(object $disbursement): void
    {
        if ((float) $disbursement->amount < 50) {
            throw new RuntimeException('Amount is below NCBA M-Pesa minimum of KES 50.');
        }

        if ((float) $disbursement->amount > 250000) {
            throw new RuntimeException('Amount is above NCBA M-Pesa maximum of KES 250,000.');
        }

        if (! preg_match('/^254(7|1)[0-9]{8}$/', $disbursement->member_phone)) {
            throw new RuntimeException('Invalid member phone. Expected format is 2547XXXXXXXX or 2541XXXXXXXX.');
        }

        if (empty($disbursement->transaction_ref)) {
            throw new RuntimeException('Missing transaction reference.');
        }

        if (empty(config('services.ncba.debit_account'))) {
            throw new RuntimeException('NCBA debit account is not configured.');
        }
    }

    private function cleanNarration(string $narration): string
    {
        $narration = strtoupper($narration);
        $narration = preg_replace('/[^A-Z0-9 ]/', '', $narration);
        $narration = trim(preg_replace('/\s+/', ' ', $narration));

        return substr($narration, 0, 50);
    }
}
