<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SaccoBankIpnController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SBM IPN receiver
    |--------------------------------------------------------------------------
    | For now this only receives, validates where possible, stores, and responds.
    | It does NOT update member/share/loan/FOSA records.
    */
    public function sbm(Request $request)
    {
        $bankCode = 'SBM';
        $batchId  = (string) Str::uuid();
        $rawBody  = $request->getContent();

        $firstInsertedId = null;
        $firstReference  = null;
        $firstAccount    = null;

        try {
            $baseData = $this->baseIpnData($request, $bankCode, $batchId, $rawBody);

            /*
             * Insert immediately so even bad/invalid calls are logged.
             */
            $firstInsertedId = DB::table('sacco_bank_ipns')->insertGetId(array_merge($baseData, [
                'payload_format'     => 'unknown',
                'validation_status'  => 'received',
                'validation_message' => 'IPN received and awaiting parsing',
                'processing_status'  => 'pending',
                'received_at'        => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]));

            $parsed = $this->parseSbmPayload($request, $rawBody);

            $payload = $parsed['payload'];
            $items   = $payload['Data'] ?? $payload['data'] ?? [];

            if (!is_array($items) || count($items) < 1) {
                throw new \Exception('SBM IPN has no transaction items in Data.');
            }

            $this->validateSbmPayload($payload);

            $savedCount     = 0;
            $duplicateCount = 0;

            foreach ($items as $index => $item) {
                $normalized = $this->normalizeSbmItem($item);

                if (!$firstReference) {
                    $firstReference = $normalized['transaction_reference'];
                }

                if (!$firstAccount) {
                    $firstAccount = $normalized['bank_account'];
                }

                $rowData = array_merge($baseData, [
                    'payload_format'        => $parsed['payload_format'],
                    'encrypted_payload'     => $parsed['encrypted_payload'],
                    'decrypted_payload'     => $parsed['decrypted_payload'],
                    'full_payload_json'     => json_encode($payload),
                    'item_payload_json'     => json_encode($item),

                    'transaction_reference' => $normalized['transaction_reference'],
                    'bank_account'          => $normalized['bank_account'],
                    'transaction_type'      => $normalized['transaction_type'],
                    'transaction_amount'    => $normalized['transaction_amount'],
                    'currency'              => $normalized['currency'],
                    'transaction_date'      => $normalized['transaction_date'],
                    'narration'             => $normalized['narration'],
                    'customer_unique_id'    => $normalized['customer_unique_id'],
                    'customer_name'         => $normalized['customer_name'],
                    'account_balance'       => $normalized['account_balance'],
                    'member_reference'      => $normalized['member_reference'],
                    'normalized_reference'  => $normalized['normalized_reference'],

                    'is_valid'              => true,
                    'validation_status'     => 'valid',
                    'validation_message'    => 'SBM IPN received and stored successfully',

                    /*
                     * Important:
                     * This remains false until a later job updates SACCO/member records.
                     */
                    'is_picked'             => false,
                    'is_processed'          => false,
                    'processing_status'     => 'pending',
                    'processing_message'    => 'Waiting for allocation job',

                    'received_at'           => now(),
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);

                /*
                 * First transaction updates the first row we already inserted.
                 * Extra transactions from the same IPN create extra rows with same batch ID.
                 */
                if ($index === 0 && $firstInsertedId) {
                    $result = $this->updateFirstIpnRow($firstInsertedId, $rowData);
                } else {
                    $result = $this->insertExtraIpnRow($rowData);
                }

                if ($result === 'saved') {
                    $savedCount++;
                } else {
                    $duplicateCount++;
                }
            }

            $responseBody = [
                'status'    => '00',
                'reference' => $firstReference ?: '',
                'DebitAc'   => $firstAccount ?: '',
            ];

            $this->saveBankResponse($batchId, $responseBody, 200);

            return $this->sbmResponse($responseBody, 200);

        } catch (Throwable $e) {
            Log::error('SBM IPN receive failed', [
                'batch_id' => $batchId,
                'error'    => $e->getMessage(),
            ]);

            if ($firstInsertedId) {
                DB::table('sacco_bank_ipns')
                    ->where('id', $firstInsertedId)
                    ->update([
                        'is_valid'              => false,
                        'validation_status'     => $this->validationStatusFromException($e),
                        'validation_message'    => $e->getMessage(),
                        'processing_status'     => 'failed',
                        'processing_message'    => 'Failed at receiving/parsing stage',
                        'last_error'            => $e->getMessage(),
                        'transaction_reference' => $firstReference,
                        'bank_account'          => $firstAccount,
                        'updated_at'            => now(),
                    ]);
            }

            $responseBody = [
                'status'    => '99',
                'reference' => $firstReference ?: '',
                'DebitAc'   => $firstAccount ?: '',
            ];

            $this->saveBankResponse($batchId, $responseBody, 200);

            return $this->sbmResponse($responseBody, 200);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Base IPN storage data
    |--------------------------------------------------------------------------
    */
    private function baseIpnData(Request $request, string $bankCode, string $batchId, string $rawBody): array
    {
        return [
            'bank_code'              => $bankCode,
            'endpoint'               => '/' . ltrim($request->path(), '/'),
            'ipn_batch_id'           => $batchId,

            'source_ip'              => $this->getClientIp($request),
            'remote_addr'            => $_SERVER['REMOTE_ADDR'] ?? null,
            'cf_connecting_ip'       => $request->header('CF-Connecting-IP'),
            'x_forwarded_for'        => $request->header('X-Forwarded-For'),
            'x_real_ip'              => $request->header('X-Real-IP'),

            'request_host'           => $request->getHost(),
            'request_scheme'         => $request->getScheme(),
            'request_url'            => $request->fullUrl(),
            'request_path'           => '/' . ltrim($request->path(), '/'),
            'http_method'            => $request->method(),

            'origin_header'          => $request->header('Origin'),
            'referer_header'         => $request->header('Referer'),
            'possible_sender_domain' => $this->getPossibleSenderDomain($request),
            'reverse_dns'            => null,

            'user_agent'             => $request->userAgent(),

            'headers_json'           => json_encode($this->safeHeaders($request)),
            'query_json'             => json_encode($request->query()),
            'form_payload_json'      => json_encode($request->except([])),

            'raw_body'               => $rawBody,
            'raw_body_hash'          => hash('sha256', $rawBody),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SBM payload parsing
    |--------------------------------------------------------------------------
    */
    private function parseSbmPayload(Request $request, string $rawBody): array
    {
        $rawBody = trim($rawBody);

        if ($rawBody === '') {
            throw new \Exception('Empty SBM IPN body.');
        }

        /*
         * Test mode: allow plain JSON so you can test locally/Postman before SBM
         * starts sending encrypted AES/Base64 payloads.
         */
        $json = json_decode($rawBody, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            if (isset($json['IPNUsername']) || isset($json['Data']) || isset($json['data'])) {
                return [
                    'payload_format'    => 'json',
                    'encrypted_payload' => null,
                    'decrypted_payload' => $rawBody,
                    'payload'           => $json,
                ];
            }

            /*
             * Some providers wrap encrypted payload inside JSON.
             */
            $possibleEncrypted = $json['payload']
                ?? $json['message']
                ?? $json['data']
                ?? $json['encryptedPayload']
                ?? null;

            if ($possibleEncrypted) {
                $plain = $this->decryptSbmPayload((string) $possibleEncrypted);

                return [
                    'payload_format'    => 'encrypted_base64_json_wrapper',
                    'encrypted_payload' => (string) $possibleEncrypted,
                    'decrypted_payload' => $plain,
                    'payload'           => json_decode($plain, true, 512, JSON_THROW_ON_ERROR),
                ];
            }
        }

        /*
         * Real SBM mode: raw request body is expected to be Base64(AES(JSON)).
         */
        $plain = $this->decryptSbmPayload($rawBody);

        return [
            'payload_format'    => 'encrypted_base64',
            'encrypted_payload' => $rawBody,
            'decrypted_payload' => $plain,
            'payload'           => json_decode($plain, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    private function validateSbmPayload(array $payload): void
    {
        /*
         * During testing, keep SBM_IPN_STRICT_AUTH=false.
         * In production, set it true and configure username/password.
         */
        $strictAuth = filter_var(env('SBM_IPN_STRICT_AUTH', false), FILTER_VALIDATE_BOOLEAN);

        if (!$strictAuth) {
            return;
        }

        $expectedUsername = (string) env('SBM_IPN_USERNAME', '');
        $expectedPassword = (string) env('SBM_IPN_PASSWORD', '');

        if ($expectedUsername === '' || $expectedPassword === '') {
            throw new \Exception('SBM strict auth enabled but username/password not configured.');
        }

        $actualUsername = (string) ($payload['IPNUsername'] ?? '');
        $actualPassword = (string) ($payload['IPNPassword'] ?? '');

        if (!hash_equals($expectedUsername, $actualUsername)) {
            throw new \Exception('Invalid SBM IPNUsername.');
        }

        if (!hash_equals($expectedPassword, $actualPassword)) {
            throw new \Exception('Invalid SBM IPNPassword.');
        }
    }

    private function normalizeSbmItem(array $item): array
    {
        $reference = strtoupper(trim((string) ($item['reference'] ?? $item['Reference'] ?? '')));
        $account   = trim((string) ($item['DebitAc'] ?? $item['debitAc'] ?? $item['DebitAC'] ?? ''));

        if ($reference === '') {
            throw new \Exception('SBM transaction reference missing.');
        }

        $narration = $item['description'] ?? $item['Description'] ?? null;

        $memberReference = $this->extractMemberReference(
            trim(($item['customerUniqueId'] ?? '') . ' ' . ($narration ?? ''))
        );

        return [
            'transaction_reference' => $reference,
            'bank_account'          => $account ?: null,
            'transaction_type'      => $item['type'] ?? $item['Type'] ?? null,
            'transaction_amount'    => $this->cleanAmount($item['amount'] ?? $item['Amount'] ?? 0),
            'currency'              => strtoupper((string) ($item['currency'] ?? $item['Currency'] ?? 'KES')),
            'transaction_date'      => $this->parseBankDate($item['date'] ?? $item['Date'] ?? null),
            'narration'             => $narration,
            'customer_unique_id'    => $item['customerUniqueId'] ?? $item['CustomerUniqueId'] ?? null,
            'customer_name'         => $item['customerName'] ?? $item['CustomerName'] ?? null,
            'account_balance'       => $this->nullableAmount($item['AcBalance'] ?? $item['Acbalance'] ?? $item['acBalance'] ?? null),
            'member_reference'      => $memberReference,
            'normalized_reference'  => $memberReference ? strtoupper(str_replace(' ', '', $memberReference)) : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Insert/update helpers
    |--------------------------------------------------------------------------
    */
    private function updateFirstIpnRow(int $id, array $rowData): string
    {
        /*
         * Check duplicate before setting transaction_reference because your current
         * migration has a unique key on bank_code + transaction_reference.
         */
        if ($this->transactionReferenceExists(
            $rowData['bank_code'],
            $rowData['transaction_reference'],
            $id
        )) {
            DB::table('sacco_bank_ipns')
                ->where('id', $id)
                ->update(array_merge($rowData, [
                    'is_valid'           => false,
                    'validation_status'  => 'duplicate',
                    'validation_message' => 'Duplicate bank transaction reference received',
                    'processing_status'  => 'duplicate',
                    'processing_message' => 'Duplicate IPN stored but not available for processing',
                    'updated_at'         => now(),
                ]));

            return 'duplicate';
        }

        DB::table('sacco_bank_ipns')
            ->where('id', $id)
            ->update($rowData);

        return 'saved';
    }

    private function insertExtraIpnRow(array $rowData): string
    {
        if ($this->transactionReferenceExists(
            $rowData['bank_code'],
            $rowData['transaction_reference']
        )) {
            /*
             * Because the migration has a unique index, we cannot insert the same
             * transaction_reference again. Log duplicate in Laravel log for now.
             */
            Log::warning('Duplicate bank IPN item skipped due to unique transaction reference', [
                'bank_code'             => $rowData['bank_code'],
                'transaction_reference' => $rowData['transaction_reference'],
                'ipn_batch_id'          => $rowData['ipn_batch_id'] ?? null,
            ]);

            return 'duplicate';
        }

        DB::table('sacco_bank_ipns')->insert($rowData);

        return 'saved';
    }

    private function transactionReferenceExists(string $bankCode, ?string $reference, ?int $excludeId = null): bool
    {
        if (!$reference) {
            return false;
        }

        $query = DB::table('sacco_bank_ipns')
            ->where('bank_code', $bankCode)
            ->where('transaction_reference', $reference);

        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | SBM response
    |--------------------------------------------------------------------------
    */
    private function sbmResponse(array $responseBody, int $httpStatus)
    {
        $json = json_encode($responseBody);

        $encryptedResponse = null;

        try {
            if ((string) env('SBM_IPN_SECRET_KEY', '') !== '') {
                $encryptedResponse = $this->encryptSbmResponse($json);
            }
        } catch (Throwable $e) {
            Log::warning('SBM response encryption failed; returning plain JSON', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($encryptedResponse) {
            return response($encryptedResponse, $httpStatus)
                ->header('Content-Type', 'text/plain');
        }

        return response()->json($responseBody, $httpStatus);
    }

    private function saveBankResponse(string $batchId, array $responseBody, int $httpStatus): void
    {
        $json = json_encode($responseBody);

        $encryptedResponse = null;

        try {
            if ((string) env('SBM_IPN_SECRET_KEY', '') !== '') {
                $encryptedResponse = $this->encryptSbmResponse($json);
            }
        } catch (Throwable $e) {
            $encryptedResponse = null;
        }

        DB::table('sacco_bank_ipns')
            ->where('ipn_batch_id', $batchId)
            ->update([
                'response_status'       => $responseBody['status'] ?? null,
                'http_status_returned' => $httpStatus,
                'response_body'         => $json,
                'encrypted_response'    => $encryptedResponse,
                'updated_at'            => now(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | AES helpers
    |--------------------------------------------------------------------------
    */
    private function decryptSbmPayload(string $encryptedBase64): string
    {
        $secretKey = (string) env('SBM_IPN_SECRET_KEY', '');
        $cipher    = (string) env('SBM_IPN_AES_CIPHER', 'AES-128-ECB');
        $iv        = (string) env('SBM_IPN_AES_IV', '');

        if ($secretKey === '') {
            throw new \Exception('SBM_IPN_SECRET_KEY is not configured. Use plain JSON for testing or configure the key.');
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $encryptedBase64), true);

        if ($binary === false) {
            throw new \Exception('SBM payload is not valid Base64.');
        }

        if (str_contains(strtoupper($cipher), 'ECB')) {
            $plain = openssl_decrypt($binary, $cipher, $secretKey, OPENSSL_RAW_DATA);
        } else {
            $plain = openssl_decrypt($binary, $cipher, $secretKey, OPENSSL_RAW_DATA, $iv);
        }

        if ($plain === false || trim($plain) === '') {
            throw new \Exception('SBM AES decryption failed.');
        }

        return $plain;
    }

    private function encryptSbmResponse(string $plainJson): string
    {
        $secretKey = (string) env('SBM_IPN_SECRET_KEY', '');
        $cipher    = (string) env('SBM_IPN_AES_CIPHER', 'AES-128-ECB');
        $iv        = (string) env('SBM_IPN_AES_IV', '');

        if ($secretKey === '') {
            throw new \Exception('SBM_IPN_SECRET_KEY is not configured.');
        }

        if (str_contains(strtoupper($cipher), 'ECB')) {
            $encrypted = openssl_encrypt($plainJson, $cipher, $secretKey, OPENSSL_RAW_DATA);
        } else {
            $encrypted = openssl_encrypt($plainJson, $cipher, $secretKey, OPENSSL_RAW_DATA, $iv);
        }

        if ($encrypted === false) {
            throw new \Exception('SBM response encryption failed.');
        }

        return base64_encode($encrypted);
    }

    /*
    |--------------------------------------------------------------------------
    | Utility helpers
    |--------------------------------------------------------------------------
    */
    private function getClientIp(Request $request): ?string
    {
        if ($request->header('CF-Connecting-IP')) {
            return trim($request->header('CF-Connecting-IP'));
        }

        if ($request->header('X-Real-IP')) {
            return trim($request->header('X-Real-IP'));
        }

        if ($request->header('X-Forwarded-For')) {
            return trim(explode(',', $request->header('X-Forwarded-For'))[0]);
        }

        return $request->ip();
    }

    private function getPossibleSenderDomain(Request $request): ?string
    {
        $value = $request->header('Origin') ?: $request->header('Referer');

        if (!$value) {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return $host ?: null;
    }

    private function safeHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        unset($headers['authorization']);
        unset($headers['x-bank-token']);
        unset($headers['x-ipn-token']);

        return $headers;
    }

    private function cleanAmount($amount): float
    {
        $clean = preg_replace('/[^\d\.\-]/', '', (string) $amount);

        return round((float) $clean, 2);
    }

    private function nullableAmount($amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return $this->cleanAmount($amount);
    }

    private function parseBankDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            $digits = preg_replace('/\D/', '', (string) $value);

            if (strlen($digits) === 14) {
                return Carbon::createFromFormat('YmdHis', $digits)->toDateTimeString();
            }

            if (strlen($digits) === 8) {
                return Carbon::createFromFormat('Ymd', $digits)->startOfDay()->toDateTimeString();
            }

            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function extractMemberReference(?string $text): ?string
    {
        $text = strtoupper(trim((string) $text));

        if ($text === '') {
            return null;
        }

        /*
         * Expected references:
         * SH123
         * LN45
         * CA123
         * RF123
         * OPSH-15
         * OPLN-15-26
         * SACCO number / National ID fallback
         */
        if (preg_match('/\b(OP[A-Z]{2}-\d+(?:-\d+)?|SH\d+|LN\d+|CA\d+|RF\d+|[A-Z]{2}\d+)\b/', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/\b\d{5,12}\b/', $text, $match)) {
            return $match[0];
        }

        return null;
    }

    private function validationStatusFromException(Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'decrypt') || str_contains($message, 'base64') || str_contains($message, 'aes')) {
            return 'decrypt_failed';
        }

        if (str_contains($message, 'username') || str_contains($message, 'password') || str_contains($message, 'auth')) {
            return 'auth_failed';
        }

        return 'invalid';
    }
}