<?php

namespace App\Jobs;

use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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
        $lock = Cache::lock('mpesa-stk-reconciliation-job-lock', 300);

        if (!$lock->get()) {
            Log::info('STK reconciliation skipped because another run is active.');
            return;
        }

        try {
            $this->runReconciliation();
        } finally {
            optional($lock)->release();
        }
    }

    private function runReconciliation(): void
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
            ->orderByDesc('created_at')
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
                if ($this->tryResolveFromLocalStkResponse($stkLog)) {
                    continue;
                }

                if ($this->tryResolveFromC2B($stkLog, $c2bMatchWindowMinutes)) {
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
            $receiptNumber = $this->normalizeTransactionId($response->mpesa_receipt_number ?? null);

            if (!$this->hasFullPaymentData(
                $receiptNumber,
                $response->transaction_date ?? null,
                $response->phone_number ?? null,
                $response->amount ?? null
            )) {
                $this->markMissingFullData($stkLog, 'local_stk_push_responses', $response, $response->id ?? null);
                return true;
            }

            if ($this->mpesaTransactionUsedElsewhere(
                $receiptNumber,
                null,
                $stkLog->id,
                $stkLog->checkout_request_id
            )) {
                $this->markDuplicateTransactionSkipped(
                    $stkLog,
                    $receiptNumber,
                    'local_stk_push_responses',
                    null,
                    $response->id ?? null,
                    $response
                );

                return true;
            }

            DB::transaction(function () use ($stkLog, $response, $receiptNumber) {
                DB::table('stk_push_logs')
                    ->where('id', $stkLog->id)
                    ->update([
                        'merchant_request_id' => $response->merchant_request_id ?? $stkLog->merchant_request_id,
                        'result_code' => $response->result_code,
                        'result_description' => $response->result_description,
                        'transaction_id' => $receiptNumber,
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
                    'mpesa_receipt_number' => $receiptNumber,
                    'transaction_time' => $response->transaction_date,
                    'raw_response' => json_encode($response),
                    'notes' => 'Resolved from local stk_push_responses after full-data and duplicate checks.',
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
                'notes' => 'Failed/cancelled response found in stk_push_responses. No payment imported.',
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

        $transactionId = $this->normalizeTransactionId($c2b->transaction_id);

        $phoneNumber = $stkLog->phone_number ?? null;
        $amount = $c2b->transaction_amount ?? $stkLog->amount ?? null;
        $transactionTime = $c2b->transaction_time ?? null;

        if (!$this->hasFullPaymentData($transactionId, $transactionTime, $phoneNumber, $amount)) {
            $this->markMissingFullData($stkLog, 'local_c2b_payments', $c2b, null, $c2b->id ?? null);
            return true;
        }

        if ($this->mpesaTransactionUsedElsewhere(
            $transactionId,
            $c2b->id,
            $stkLog->id,
            $stkLog->checkout_request_id
        )) {
            $this->markDuplicateTransactionSkipped(
                $stkLog,
                $transactionId,
                'local_c2b_payments',
                $c2b->id,
                null,
                $c2b
            );

            return true;
        }

        DB::transaction(function () use ($stkLog, $c2b, $transactionId, $phoneNumber, $amount, $transactionTime) {
            $stkResponseId = $this->insertOrUpdateStkPushResponseIfSafe([
                'unique_number' => $stkLog->unique_number,
                'checkout_request_id' => $stkLog->checkout_request_id,
                'merchant_request_id' => $stkLog->merchant_request_id ?? null,
                'result_code' => 0,
                'result_description' => 'Payment confirmed from local c2b_payments.',
                'mpesa_receipt_number' => $transactionId,
                'transaction_date' => $transactionTime,
                'phone_number' => $phoneNumber,
                'amount' => $amount,
                'processed' => 'N',
            ]);

            if (!$stkResponseId) {
                DB::table('stk_push_logs')
                    ->where('id', $stkLog->id)
                    ->update([
                        'recon_status' => 'stk_response_import_skipped',
                        'recon_source' => 'local_c2b_payments',
                        'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                        'last_reconciled_at' => now(),
                        'recon_message' => 'Skipped because full STK response import was not safe.',
                        'updated_at' => now(),
                    ]);

                $this->writeReconAudit($stkLog, [
                    'c2b_payment_id' => $c2b->id,
                    'recon_status' => 'stk_response_import_skipped',
                    'recon_source' => 'local_c2b_payments',
                    'match_confidence' => 'blocked',
                    'mpesa_receipt_number' => $transactionId,
                    'transaction_time' => $transactionTime,
                    'raw_response' => json_encode($c2b),
                    'notes' => 'Could not safely insert into stk_push_responses.',
                ]);

                return;
            }

            DB::table('stk_push_logs')
                ->where('id', $stkLog->id)
                ->update([
                    'result_code' => '0',
                    'result_description' => 'Payment confirmed from local c2b_payments.',
                    'transaction_id' => $transactionId,
                    'transaction_time' => $transactionTime,
                    'status' => 'completed',
                    'recon_status' => 'local_c2b_success',
                    'recon_source' => 'local_c2b_payments',
                    'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                    'last_reconciled_at' => now(),
                    'recon_message' => 'Payment imported into stk_push_responses with processed=N.',
                    'updated_at' => now(),
                ]);

            $this->writeReconAudit($stkLog, [
                'c2b_payment_id' => $c2b->id,
                'stk_push_response_id' => $stkResponseId,
                'recon_status' => 'local_c2b_success',
                'recon_source' => 'local_c2b_payments',
                'match_confidence' => 'high',
                'safaricom_result_code' => '0',
                'safaricom_result_description' => 'Payment confirmed from local c2b_payments.',
                'mpesa_receipt_number' => $transactionId,
                'transaction_time' => $transactionTime,
                'raw_response' => json_encode($c2b),
                'notes' => 'Matched by reference, amount, shortcode and transaction time. Inserted into stk_push_responses with processed=N.',
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
            DB::table('stk_push_logs')
                ->where('id', $stkLog->id)
                ->update([
                    'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                    'last_reconciled_at' => now(),
                    'recon_message' => 'Safaricom query HTTP failure. Will retry later.',
                    'updated_at' => now(),
                ]);

            Log::warning('Safaricom STK query HTTP failure', [
                'stk_push_log_id' => $stkLog->id,
                'checkout_request_id' => $stkLog->checkout_request_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $responseCode = isset($body['ResponseCode']) ? (string) $body['ResponseCode'] : null;
        $resultCode = isset($body['ResultCode']) ? (string) $body['ResultCode'] : null;
        $resultDesc = $body['ResultDesc']
            ?? $body['ResponseDescription']
            ?? $body['errorMessage']
            ?? 'No result description returned.';

        if ($resultCode === '0') {
            /*
             * Safaricom STK query usually confirms success/failure,
             * but may not return MpesaReceiptNumber, transaction date, phone and amount.
             * Since you said only full data should be imported, we do NOT insert into
             * stk_push_responses from STK Query unless full payment details are available.
             */
            $receiptNumber = $this->normalizeTransactionId($body['MpesaReceiptNumber'] ?? null);
            $transactionTime = $body['TransactionDate'] ?? null;
            $phoneNumber = $body['PhoneNumber'] ?? $stkLog->phone_number ?? null;
            $amount = $body['Amount'] ?? $stkLog->amount ?? null;

            if (!$this->hasFullPaymentData($receiptNumber, $transactionTime, $phoneNumber, $amount)) {
                DB::transaction(function () use ($stkLog, $body, $resultCode, $resultDesc) {
                    DB::table('stk_push_logs')
                        ->where('id', $stkLog->id)
                        ->update([
                            'result_code' => $resultCode,
                            'result_description' => $resultDesc,
                            'recon_status' => 'safaricom_success_missing_full_data',
                            'recon_source' => 'safaricom_stk_query',
                            'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                            'last_reconciled_at' => now(),
                            'recon_message' => 'Safaricom confirmed success, but full payment data was missing. Not imported.',
                            'updated_at' => now(),
                        ]);

                    $this->writeReconAudit($stkLog, [
                        'recon_status' => 'safaricom_success_missing_full_data',
                        'recon_source' => 'safaricom_stk_query',
                        'match_confidence' => 'medium',
                        'safaricom_result_code' => $resultCode,
                        'safaricom_result_description' => $resultDesc,
                        'raw_response' => json_encode($body),
                        'notes' => 'Safaricom confirmed success but did not return full payment details. Skipped import.',
                    ]);
                });

                return true;
            }

            if ($this->mpesaTransactionUsedElsewhere(
                $receiptNumber,
                null,
                $stkLog->id,
                $stkLog->checkout_request_id
            )) {
                $this->markDuplicateTransactionSkipped(
                    $stkLog,
                    $receiptNumber,
                    'safaricom_stk_query',
                    null,
                    null,
                    $body
                );

                return true;
            }

            DB::transaction(function () use ($stkLog, $body, $resultCode, $resultDesc, $receiptNumber, $transactionTime, $phoneNumber, $amount) {
                $stkResponseId = $this->insertOrUpdateStkPushResponseIfSafe([
                    'unique_number' => $stkLog->unique_number,
                    'checkout_request_id' => $stkLog->checkout_request_id,
                    'merchant_request_id' => $body['MerchantRequestID'] ?? $stkLog->merchant_request_id ?? null,
                    'result_code' => 0,
                    'result_description' => $resultDesc,
                    'mpesa_receipt_number' => $receiptNumber,
                    'transaction_date' => $transactionTime,
                    'phone_number' => $phoneNumber,
                    'amount' => $amount,
                    'processed' => 'N',
                ]);

                if (!$stkResponseId) {
                    DB::table('stk_push_logs')
                        ->where('id', $stkLog->id)
                        ->update([
                            'recon_status' => 'stk_response_import_skipped',
                            'recon_source' => 'safaricom_stk_query',
                            'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                            'last_reconciled_at' => now(),
                            'recon_message' => 'Safaricom success received but STK response import was not safe.',
                            'updated_at' => now(),
                        ]);

                    return;
                }

                DB::table('stk_push_logs')
                    ->where('id', $stkLog->id)
                    ->update([
                        'merchant_request_id' => $body['MerchantRequestID'] ?? $stkLog->merchant_request_id,
                        'result_code' => $resultCode,
                        'result_description' => $resultDesc,
                        'transaction_id' => $receiptNumber,
                        'transaction_time' => $transactionTime,
                        'status' => 'completed',
                        'recon_status' => 'safaricom_success',
                        'recon_source' => 'safaricom_stk_query',
                        'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                        'last_reconciled_at' => now(),
                        'recon_message' => 'Safaricom confirmed success and full data was imported with processed=N.',
                        'updated_at' => now(),
                    ]);

                $this->writeReconAudit($stkLog, [
                    'stk_push_response_id' => $stkResponseId,
                    'recon_status' => 'safaricom_success',
                    'recon_source' => 'safaricom_stk_query',
                    'match_confidence' => 'high',
                    'safaricom_result_code' => $resultCode,
                    'safaricom_result_description' => $resultDesc,
                    'mpesa_receipt_number' => $receiptNumber,
                    'transaction_time' => $transactionTime,
                    'raw_response' => json_encode($body),
                    'notes' => 'Safaricom confirmed success and full payment data was imported with processed=N.',
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

                $this->writeReconAudit($stkLog, [
                    'recon_status' => 'safaricom_failed',
                    'recon_source' => 'safaricom_stk_query',
                    'match_confidence' => 'high',
                    'safaricom_result_code' => $resultCode,
                    'safaricom_result_description' => $resultDesc,
                    'raw_response' => json_encode($body),
                    'notes' => 'Safaricom confirmed failed/cancelled/timeout status. No payment imported.',
                ]);
            });

            return true;
        }

        if ($responseCode !== null && $responseCode !== '0') {
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
                    'notes' => 'Safaricom did not confirm this CheckoutRequestID. No payment imported.',
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

    private function insertOrUpdateStkPushResponseIfSafe(array $data): ?int
    {
        $receiptNumber = $this->normalizeTransactionId($data['mpesa_receipt_number'] ?? null);
        $checkoutRequestId = $data['checkout_request_id'] ?? null;

        if (!$this->hasFullPaymentData(
            $receiptNumber,
            $data['transaction_date'] ?? null,
            $data['phone_number'] ?? null,
            $data['amount'] ?? null
        )) {
            return null;
        }

        if (empty($checkoutRequestId)) {
            return null;
        }

        $existingByReceipt = DB::table('stk_push_responses')
            ->where('mpesa_receipt_number', $receiptNumber)
            ->first();

        if ($existingByReceipt && $existingByReceipt->checkout_request_id !== $checkoutRequestId) {
            return null;
        }

        $existingByCheckout = DB::table('stk_push_responses')
            ->where('checkout_request_id', $checkoutRequestId)
            ->first();

        $payload = [
            'unique_number' => $data['unique_number'] ?? null,
            'merchant_request_id' => $data['merchant_request_id'] ?? null,
            'checkout_request_id' => $checkoutRequestId,
            'result_code' => $data['result_code'],
            'result_description' => $data['result_description'],
            'mpesa_receipt_number' => $receiptNumber,
            'transaction_date' => $data['transaction_date'],
            'phone_number' => $data['phone_number'],
            'amount' => $data['amount'],
            'processed' => 'N',
            'updated_at' => now(),
        ];

        if ($existingByCheckout) {
            if (!empty($existingByCheckout->mpesa_receipt_number)
                && $this->normalizeTransactionId($existingByCheckout->mpesa_receipt_number) !== $receiptNumber) {
                return null;
            }

            DB::table('stk_push_responses')
                ->where('id', $existingByCheckout->id)
                ->update($payload);

            return (int) $existingByCheckout->id;
        }

        $payload['created_at'] = now();

        return (int) DB::table('stk_push_responses')->insertGetId($payload);
    }

    private function hasFullPaymentData($receiptNumber, $transactionTime, $phoneNumber, $amount): bool
    {
        $receiptNumber = $this->normalizeTransactionId($receiptNumber);

        if (!$receiptNumber) {
            return false;
        }

        if (empty($transactionTime)) {
            return false;
        }

        if (empty($phoneNumber)) {
            return false;
        }

        if ($amount === null || $amount === '' || (float) $amount <= 0) {
            return false;
        }

        return true;
    }

    private function normalizeTransactionId(?string $transactionId): ?string
    {
        $transactionId = trim((string) $transactionId);
        return $transactionId !== '' ? strtoupper($transactionId) : null;
    }

    private function mpesaTransactionUsedElsewhere(
        ?string $transactionId,
        ?int $currentC2bId = null,
        ?int $currentStkLogId = null,
        ?string $currentCheckoutRequestId = null
    ): bool {
        $transactionId = $this->normalizeTransactionId($transactionId);

        if (!$transactionId) {
            return false;
        }

        $c2bQuery = DB::table('c2b_payments')
            ->where('transaction_id', $transactionId);

        if ($currentC2bId) {
            $c2bQuery->where('id', '!=', $currentC2bId);
        }

        if ($c2bQuery->exists()) {
            return true;
        }

        $stkResponseQuery = DB::table('stk_push_responses')
            ->where('mpesa_receipt_number', $transactionId);

        if ($currentCheckoutRequestId) {
            $stkResponseQuery->where('checkout_request_id', '!=', $currentCheckoutRequestId);
        }

        if ($stkResponseQuery->exists()) {
            return true;
        }

        $stkLogQuery = DB::table('stk_push_logs')
            ->where('transaction_id', $transactionId);

        if ($currentStkLogId) {
            $stkLogQuery->where('id', '!=', $currentStkLogId);
        }

        return $stkLogQuery->exists();
    }

    private function markDuplicateTransactionSkipped(
        object $stkLog,
        string $transactionId,
        string $source,
        ?int $c2bPaymentId = null,
        ?int $stkPushResponseId = null,
        $rawResponse = null
    ): void {
        DB::transaction(function () use (
            $stkLog,
            $transactionId,
            $source,
            $c2bPaymentId,
            $stkPushResponseId,
            $rawResponse
        ) {
            DB::table('stk_push_logs')
                ->where('id', $stkLog->id)
                ->update([
                    'recon_status' => 'duplicate_transaction_id_skipped',
                    'recon_source' => $source,
                    'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                    'last_reconciled_at' => now(),
                    'recon_message' => 'Skipped: duplicate M-Pesa transaction ID already exists: ' . $transactionId,
                    'updated_at' => now(),
                ]);

            $this->writeReconAudit($stkLog, [
                'c2b_payment_id' => $c2bPaymentId,
                'stk_push_response_id' => $stkPushResponseId,
                'recon_status' => 'duplicate_transaction_id_skipped',
                'recon_source' => $source,
                'match_confidence' => 'blocked',
                'mpesa_receipt_number' => $transactionId,
                'raw_response' => $rawResponse ? json_encode($rawResponse) : null,
                'notes' => 'Skipped because this M-Pesa transaction ID already exists elsewhere. No duplicate payment imported.',
            ]);
        });
    }

    private function markMissingFullData(
        object $stkLog,
        string $source,
        $rawResponse = null,
        ?int $stkPushResponseId = null,
        ?int $c2bPaymentId = null
    ): void {
        DB::transaction(function () use ($stkLog, $source, $rawResponse, $stkPushResponseId, $c2bPaymentId) {
            DB::table('stk_push_logs')
                ->where('id', $stkLog->id)
                ->update([
                    'recon_status' => 'missing_full_payment_data',
                    'recon_source' => $source,
                    'recon_attempts' => DB::raw('COALESCE(recon_attempts, 0) + 1'),
                    'last_reconciled_at' => now(),
                    'recon_message' => 'Skipped: full payment data was missing. No payment imported.',
                    'updated_at' => now(),
                ]);

            $this->writeReconAudit($stkLog, [
                'c2b_payment_id' => $c2bPaymentId,
                'stk_push_response_id' => $stkPushResponseId,
                'recon_status' => 'missing_full_payment_data',
                'recon_source' => $source,
                'match_confidence' => 'none',
                'raw_response' => $rawResponse ? json_encode($rawResponse) : null,
                'notes' => 'Skipped because receipt number, transaction time, phone number, or amount was missing.',
            ]);
        });
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