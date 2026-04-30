<?php

namespace App\Jobs;

use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReconcileStkPushPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    private bool $defaultsEnsured = false;

    public function handle(): void
    {
        $this->ensureMpesaStkReconDefaults();

        $batchSize = (int) $this->getSaccoDefault('MPESA_STK_RECON_BATCH_SIZE', 10);
        $minAgeMinutes = (int) $this->getSaccoDefault('MPESA_STK_RECON_MIN_AGE_MINUTES', 10);
        $lookbackHours = (int) $this->getSaccoDefault('MPESA_STK_RECON_LOOKBACK_HOURS', 96);
        $c2bMatchWindowMinutes = (int) $this->getSaccoDefault('MPESA_STK_RECON_C2B_MATCH_WINDOW_MINUTES', 90);
        $delaySeconds = (int) $this->getSaccoDefault('MPESA_STK_RECON_DELAY_SECONDS', 6);

        $batchSize = max(1, min($batchSize, 50));
        $minAgeMinutes = max(5, $minAgeMinutes);
        $lookbackHours = max(1, $lookbackHours);
        $c2bMatchWindowMinutes = max(10, $c2bMatchWindowMinutes);
        $delaySeconds = max(1, min($delaySeconds, 30));

        $from = Carbon::now()->subHours($lookbackHours);
        $to = Carbon::now()->subMinutes($minAgeMinutes);

        $records = DB::table('stk_push_logs')
            ->where('status', 'pending')
            ->whereNotNull('checkout_request_id')
            ->whereNull('recon_status')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at') // latest first
            ->limit($batchSize)
            ->get();

        Log::info('STK reconciliation job started', [
            'records_found' => $records->count(),
            'batch_size' => $batchSize,
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
        ]);

        foreach ($records as $index => $stkLog) {
            try {
                $resolvedLocally = $this->tryResolveFromLocalStkResponse($stkLog);

                if ($resolvedLocally) {
                    continue;
                }

                $resolvedFromC2B = $this->tryResolveFromC2B($stkLog, $c2bMatchWindowMinutes);

                if ($resolvedFromC2B) {
                    continue;
                }

                $this->tryResolveFromSafaricom($stkLog);

                if ($index < ($records->count() - 1)) {
                    sleep($delaySeconds);
                }
            } catch (Exception $e) {
                Log::error('STK reconciliation record failed', [
                    'stk_push_log_id' => $stkLog->id ?? null,
                    'checkout_request_id' => $stkLog->checkout_request_id ?? null,
                    'error' => $e->getMessage(),
                ]);

                DB::table('stk_push_logs')
                    ->where('id', $stkLog->id)
                    ->update([
                        'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                        'last_reconciled_at' => now(),
                        'recon_message' => 'Temporary reconciliation error: ' . $e->getMessage(),
                        'updated_at' => now(),
                    ]);
            }
        }

        Log::info('STK reconciliation job completed');
    }

    private function tryResolveFromLocalStkResponse(object $stkLog): bool
    {
        $response = DB::table('stk_push_responses')
            ->where('checkout_request_id', $stkLog->checkout_request_id)
            ->orderByDesc('id')
            ->first();

        if (!$response) {
            return false;
        }

        $resultCode = (string) $response->result_code;

        if ($resultCode === '0') {
            DB::transaction(function () use ($stkLog, $response) {
                DB::table('stk_push_logs')
                    ->where('id', $stkLog->id)
                    ->update([
                        'merchant_request_id' => $response->merchant_request_id ?? $stkLog->merchant_request_id,
                        'result_code' => $response->result_code,
                        'result_description' => $response->result_description,
                        'transaction_id' => $response->mpesa_receipt_number,
                        'transaction_time' => $response->transaction_date,
                        'status' => 'completed',
                        'recon_status' => 'local_stk_response_success',
                        'recon_source' => 'local_stk_push_responses',
                        'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                        'last_reconciled_at' => now(),
                        'recon_message' => 'Payment found locally in stk_push_responses.',
                        'updated_at' => now(),
                    ]);

                $this->writeReconAudit($stkLog, [
                    'stk_push_response_id' => $response->id ?? null,
                    'recon_status' => 'local_stk_response_success',
                    'recon_source' => 'local_stk_push_responses',
                    'match_confidence' => 'high',
                    'safaricom_result_code' => $response->result_code,
                    'safaricom_result_description' => $response->result_description,
                    'mpesa_receipt_number' => $response->mpesa_receipt_number,
                    'transaction_time' => $response->transaction_date,
                    'raw_response' => json_encode($response),
                    'notes' => 'Resolved from local stk_push_responses.',
                ]);
            });

            return true;
        }

        DB::transaction(function () use ($stkLog, $response) {
            DB::table('stk_push_logs')
                ->where('id', $stkLog->id)
                ->update([
                    'merchant_request_id' => $response->merchant_request_id ?? $stkLog->merchant_request_id,
                    'result_code' => $response->result_code,
                    'result_description' => $response->result_description,
                    'status' => 'failed',
                    'recon_status' => 'local_stk_response_failed',
                    'recon_source' => 'local_stk_push_responses',
                    'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                    'last_reconciled_at' => now(),
                    'recon_message' => 'Failed/cancelled STK response found locally.',
                    'updated_at' => now(),
                ]);

            $this->writeReconAudit($stkLog, [
                'stk_push_response_id' => $response->id ?? null,
                'recon_status' => 'local_stk_response_failed',
                'recon_source' => 'local_stk_push_responses',
                'match_confidence' => 'high',
                'safaricom_result_code' => $response->result_code,
                'safaricom_result_description' => $response->result_description,
                'raw_response' => json_encode($response),
                'notes' => 'Failed/cancelled response found in stk_push_responses.',
            ]);
        });

        return true;
    }

    private function tryResolveFromC2B(object $stkLog, int $matchWindowMinutes): bool
    {
        $start = Carbon::parse($stkLog->created_at)->subMinutes(5);
        $end = Carbon::parse($stkLog->created_at)->addMinutes($matchWindowMinutes);

        $query = DB::table('c2b_payments')
            ->where('bill_ref_number', $stkLog->unique_number)
            ->where('transaction_amount', $stkLog->amount)
            ->whereBetween('transaction_time', [$start, $end])
            ->orderBy('transaction_time', 'asc');

        if (!empty($stkLog->shortcode)) {
            $query->where('business_shortcode', $stkLog->shortcode);
        }

        $c2b = $query->first();

        if (!$c2b) {
            return false;
        }

        DB::transaction(function () use ($stkLog, $c2b) {
            DB::table('stk_push_logs')
                ->where('id', $stkLog->id)
                ->update([
                    'result_code' => '0',
                    'result_description' => 'Payment confirmed from local c2b_payments.',
                    'transaction_id' => $c2b->transaction_id,
                    'transaction_time' => $c2b->transaction_time,
                    'status' => 'completed',
                    'recon_status' => 'local_c2b_success',
                    'recon_source' => 'local_c2b_payments',
                    'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                    'last_reconciled_at' => now(),
                    'recon_message' => 'Payment found locally in c2b_payments.',
                    'updated_at' => now(),
                ]);

            DB::table('stk_push_responses')->updateOrInsert(
                ['checkout_request_id' => $stkLog->checkout_request_id],
                [
                    'unique_number' => $stkLog->unique_number,
                    'merchant_request_id' => $stkLog->merchant_request_id,
                    'result_code' => 0,
                    'result_description' => 'Payment confirmed from local c2b_payments.',
                    'mpesa_receipt_number' => $c2b->transaction_id,
                    'transaction_date' => $c2b->transaction_time,
                    'phone_number' => $stkLog->phone_number,
                    'amount' => $c2b->transaction_amount,
                    'processed' => 'N',
                    'updated_at' => now(),
                ]
            );

            $stkResponseId = DB::table('stk_push_responses')
                ->where('checkout_request_id', $stkLog->checkout_request_id)
                ->value('id');

            $this->writeReconAudit($stkLog, [
                'c2b_payment_id' => $c2b->id,
                'stk_push_response_id' => $stkResponseId,
                'recon_status' => 'local_c2b_success',
                'recon_source' => 'local_c2b_payments',
                'match_confidence' => 'high',
                'safaricom_result_code' => '0',
                'safaricom_result_description' => 'Payment confirmed from c2b_payments.',
                'mpesa_receipt_number' => $c2b->transaction_id,
                'transaction_time' => $c2b->transaction_time,
                'raw_response' => json_encode($c2b),
                'notes' => 'Matched by reference, amount, shortcode and transaction time window.',
            ]);
        });

        return true;
    }

    private function tryResolveFromSafaricom(object $stkLog): bool
    {
        $config = $this->getMpesaConfig($stkLog->shortcode);

        if (!$config) {
            throw new Exception('No M-Pesa Express configuration found.');
        }

        $accessToken = $this->getAccessToken($config);
        [$password, $timestamp] = $this->generatePassword($config->shortcode, $config->passkey);

        $url = env('MPESA_ENV') === 'live'
            ? 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';

        $payload = [
            'BusinessShortCode' => $config->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $stkLog->checkout_request_id,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, $payload);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            Log::warning('Safaricom STK query HTTP failure', [
                'stk_push_log_id' => $stkLog->id,
                'checkout_request_id' => $stkLog->checkout_request_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            DB::table('stk_push_logs')
                ->where('id', $stkLog->id)
                ->update([
                    'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                    'last_reconciled_at' => now(),
                    'recon_message' => 'Safaricom query HTTP failure. Will retry later.',
                    'updated_at' => now(),
                ]);

            return false;
        }

        $responseCode = isset($body['ResponseCode']) ? (string) $body['ResponseCode'] : null;
        $resultCode = isset($body['ResultCode']) ? (string) $body['ResultCode'] : null;
        $resultDesc = $body['ResultDesc']
            ?? $body['ResponseDescription']
            ?? $body['errorMessage']
            ?? 'No result description returned.';

        if ($responseCode !== null && $responseCode !== '0' && $resultCode === null) {
            DB::transaction(function () use ($stkLog, $body, $responseCode, $resultDesc) {
                DB::table('stk_push_logs')
                    ->where('id', $stkLog->id)
                    ->update([
                        'recon_status' => 'safaricom_not_found',
                        'recon_source' => 'safaricom_stk_query',
                        'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                        'last_reconciled_at' => now(),
                        'recon_message' => $resultDesc,
                        'updated_at' => now(),
                    ]);

                $this->writeReconAudit($stkLog, [
                    'recon_status' => 'safaricom_not_found',
                    'recon_source' => 'safaricom_stk_query',
                    'match_confidence' => 'none',
                    'safaricom_result_code' => $responseCode,
                    'safaricom_result_description' => $resultDesc,
                    'raw_response' => json_encode($body),
                    'notes' => 'Safaricom did not confirm this CheckoutRequestID.',
                ]);
            });

            return true;
        }

        if ($resultCode === '0') {
            DB::transaction(function () use ($stkLog, $body, $resultCode, $resultDesc) {
                DB::table('stk_push_logs')
                    ->where('id', $stkLog->id)
                    ->update([
                        'merchant_request_id' => $body['MerchantRequestID'] ?? $stkLog->merchant_request_id,
                        'result_code' => $resultCode,
                        'result_description' => $resultDesc,
                        'status' => 'completed',
                        'recon_status' => 'safaricom_success',
                        'recon_source' => 'safaricom_stk_query',
                        'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                        'last_reconciled_at' => now(),
                        'recon_message' => 'Safaricom STK Query confirmed success. Receipt may require callback/C2B/statement match.',
                        'updated_at' => now(),
                    ]);

                DB::table('stk_push_responses')->updateOrInsert(
                    ['checkout_request_id' => $stkLog->checkout_request_id],
                    [
                        'unique_number' => $stkLog->unique_number,
                        'merchant_request_id' => $body['MerchantRequestID'] ?? $stkLog->merchant_request_id,
                        'result_code' => 0,
                        'result_description' => $resultDesc,
                        'mpesa_receipt_number' => null,
                        'transaction_date' => null,
                        'phone_number' => $stkLog->phone_number,
                        'amount' => $stkLog->amount,
                        'processed' => 'N',
                        'updated_at' => now(),
                    ]
                );

                $stkResponseId = DB::table('stk_push_responses')
                    ->where('checkout_request_id', $stkLog->checkout_request_id)
                    ->value('id');

                $this->writeReconAudit($stkLog, [
                    'stk_push_response_id' => $stkResponseId,
                    'recon_status' => 'safaricom_success',
                    'recon_source' => 'safaricom_stk_query',
                    'match_confidence' => 'medium',
                    'safaricom_result_code' => $resultCode,
                    'safaricom_result_description' => $resultDesc,
                    'raw_response' => json_encode($body),
                    'notes' => 'Safaricom confirmed success. STK Query may not return MpesaReceiptNumber.',
                ]);
            });

            return true;
        }

        if ($resultCode !== null && $resultCode !== '0') {
            DB::transaction(function () use ($stkLog, $body, $resultCode, $resultDesc) {
                DB::table('stk_push_logs')
                    ->where('id', $stkLog->id)
                    ->update([
                        'merchant_request_id' => $body['MerchantRequestID'] ?? $stkLog->merchant_request_id,
                        'result_code' => $resultCode,
                        'result_description' => $resultDesc,
                        'status' => 'failed',
                        'recon_status' => 'safaricom_failed',
                        'recon_source' => 'safaricom_stk_query',
                        'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                        'last_reconciled_at' => now(),
                        'recon_message' => $resultDesc,
                        'updated_at' => now(),
                    ]);

                DB::table('stk_push_responses')->updateOrInsert(
                    ['checkout_request_id' => $stkLog->checkout_request_id],
                    [
                        'unique_number' => $stkLog->unique_number,
                        'merchant_request_id' => $body['MerchantRequestID'] ?? $stkLog->merchant_request_id,
                        'result_code' => $resultCode,
                        'result_description' => $resultDesc,
                        'phone_number' => null,
                        'amount' => null,
                        'processed' => 'N',
                        'updated_at' => now(),
                    ]
                );

                $stkResponseId = DB::table('stk_push_responses')
                    ->where('checkout_request_id', $stkLog->checkout_request_id)
                    ->value('id');

                $this->writeReconAudit($stkLog, [
                    'stk_push_response_id' => $stkResponseId,
                    'recon_status' => 'safaricom_failed',
                    'recon_source' => 'safaricom_stk_query',
                    'match_confidence' => 'high',
                    'safaricom_result_code' => $resultCode,
                    'safaricom_result_description' => $resultDesc,
                    'raw_response' => json_encode($body),
                    'notes' => 'Safaricom confirmed failed/cancelled/timeout status.',
                ]);
            });

            return true;
        }

        DB::table('stk_push_logs')
            ->where('id', $stkLog->id)
            ->update([
                'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                'last_reconciled_at' => now(),
                'recon_message' => 'Safaricom query returned unclear response. Will retry later.',
                'updated_at' => now(),
            ]);

        Log::warning('Unclear Safaricom STK query response', [
            'stk_push_log_id' => $stkLog->id,
            'checkout_request_id' => $stkLog->checkout_request_id,
            'response' => $body,
        ]);

        return false;
    }

    private function writeReconAudit(object $stkLog, array $data): void
    {
        DB::table('mpesa_stk_reconciliations')->insert([
            'stk_push_log_id' => $stkLog->id,
            'c2b_payment_id' => $data['c2b_payment_id'] ?? null,
            'stk_push_response_id' => $data['stk_push_response_id'] ?? null,

            'unique_number' => $stkLog->unique_number,
            'checkout_request_id' => $stkLog->checkout_request_id,
            'merchant_request_id' => $stkLog->merchant_request_id,

            'phone_number' => $stkLog->phone_number,
            'amount' => $stkLog->amount,
            'shortcode' => $stkLog->shortcode,

            'recon_status' => $data['recon_status'] ?? null,
            'recon_source' => $data['recon_source'] ?? null,
            'match_confidence' => $data['match_confidence'] ?? null,

            'safaricom_result_code' => $data['safaricom_result_code'] ?? null,
            'safaricom_result_description' => $data['safaricom_result_description'] ?? null,

            'mpesa_receipt_number' => $data['mpesa_receipt_number'] ?? null,
            'transaction_time' => $data['transaction_time'] ?? null,

            'raw_response' => $data['raw_response'] ?? null,
            'notes' => $data['notes'] ?? null,

            'attempt_no' => ((int) ($stkLog->recon_attempts ?? 0)) + 1,
            'attempted_at' => now(),

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureMpesaStkReconDefaults(): void
    {
        if ($this->defaultsEnsured) {
            return;
        }

        $defaults = [
            'MPESA_STK_RECON_BATCH_SIZE' => '10',
            'MPESA_STK_RECON_MIN_AGE_MINUTES' => '10',
            'MPESA_STK_RECON_LOOKBACK_HOURS' => '96',
            'MPESA_STK_RECON_C2B_MATCH_WINDOW_MINUTES' => '90',
            'MPESA_STK_RECON_DELAY_SECONDS' => '6',
        ];

        foreach ($defaults as $name => $value) {
            $exists = DB::table('sacco_defaults')
                ->where('default_name', $name)
                ->exists();

            if (!$exists) {
                DB::table('sacco_defaults')->insert([
                    'default_name' => $name,
                    'default_value' => $value,
                    'default_transdate' => now(),
                    'default_userid' => null,
                    'default_ip' => '127.0.0.1',
                ]);
            }
        }

        $this->defaultsEnsured = true;
    }

    private function getSaccoDefault(string $name, $fallback = null)
    {
        $value = DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->value('default_value');

        return $value !== null && $value !== '' ? $value : $fallback;
    }

    private function getMpesaConfig(?string $shortcode = null): ?object
    {
        $query = DB::table('mpesa_configs')
            ->where('api_type', 'mpesa_express');

        if (!empty($shortcode)) {
            $query->where('shortcode', $shortcode);
        }

        return $query->first() ?: DB::table('mpesa_configs')
            ->where('api_type', 'mpesa_express')
            ->first();
    }

    private function getAccessToken(object $config): string
    {
        if (!empty($config->access_token) && !empty($config->token_expires_at)) {
            if (Carbon::now()->lt(Carbon::parse($config->token_expires_at)->subMinutes(2))) {
                return $config->access_token;
            }
        }

        $url = env('MPESA_ENV') === 'live'
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withBasicAuth($config->consumer_key, $config->consumer_secret)
            ->timeout(30)
            ->get($url);

        if ($response->failed()) {
            throw new Exception('Failed to generate M-Pesa access token: ' . $response->body());
        }

        $body = $response->json();

        if (empty($body['access_token'])) {
            throw new Exception('M-Pesa access token missing from response.');
        }

        $accessToken = $body['access_token'];
        $expiresIn = (int) ($body['expires_in'] ?? 3599);

        DB::table('mpesa_configs')
            ->where('shortcode', $config->shortcode)
            ->update([
                'access_token' => $accessToken,
                'token_expires_at' => Carbon::now()->addSeconds($expiresIn),
                'updated_at' => now(),
            ]);

        return $accessToken;
    }

    private function generatePassword(string $shortcode, string $passkey): array
    {
        $timestamp = Carbon::now()->format('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);

        return [$password, $timestamp];
    }
}