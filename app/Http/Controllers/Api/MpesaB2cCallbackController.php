<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class MpesaB2cCallbackController extends Controller
{
    private const PROVIDER = 'MPESA';
    private const CHANNEL = 'MPESA';

    private const STATUS_CONFIRMED = 'CONFIRMED';
    private const STATUS_FAILED_FINAL = 'FAILED_FINAL';
    private const STATUS_SEND_UNKNOWN = 'SEND_UNKNOWN';

    private const CALLBACK_ELIGIBLE_STATUSES = [
        'SENDING',
        'SENT_TO_MPESA',
        'SENT_TO_PROVIDER',
        'SENT_TO_BANK',
        'SEND_UNKNOWN',
    ];

    private const MAX_CALLBACK_BYTES = 131072;

    /**
     * Receive the final Safaricom B2C result callback.
     */
    public function result(Request $request): JsonResponse
    {
        return $this->processCallback($request, 'result');
    }

    /**
     * Receive the Safaricom B2C queue-timeout callback.
     */
    public function timeout(Request $request): JsonResponse
    {
        return $this->processCallback($request, 'timeout');
    }

    /**
     * Process callbacks idempotently and without unsafe automatic resends.
     */
    private function processCallback(
        Request $request,
        string $callbackType
    ): JsonResponse {
        try {
            $callback = $this->parseCallback($request);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Invalid M-Pesa B2C callback rejected', [
                'callback_type' => $callbackType,
                'ip_address' => $request->ip(),
                'reason' => $exception->getMessage(),
            ]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => $exception->getMessage(),
            ], 422);
        }

        try {
            $outcome = DB::transaction(function () use (
                $request,
                $callbackType,
                $callback
            ): array {
                $disbursement = $this->findDisbursement(
                    $callback['originator_conversation_id'],
                    $callback['conversation_id']
                );

                if ($disbursement === null) {
                    return [
                        'found' => false,
                    ];
                }

                $storedPayload = $this->mergeResponsePayload(
                    $disbursement->response_payload ?? null,
                    $callbackType,
                    $callback['payload'],
                    $request->ip()
                );

                $currentStatus = strtoupper(
                    trim((string) ($disbursement->status ?? ''))
                );

                /*
                 * A confirmed payout must never be downgraded by a duplicate,
                 * late failure, or timeout callback.
                 */
                if ($currentStatus === self::STATUS_CONFIRMED) {
                    DB::table('sacco_loan_disbursements')
                        ->where('id', $disbursement->id)
                        ->update([
                            'response_payload' => $storedPayload,
                            'updated_at' => now(),
                        ]);

                    $storedTransactionId = $this->stringOrNull(
                        $disbursement->core_reference ?? null
                    );

                    $sameSuccess = $callbackType === 'result'
                        && $callback['result_code'] === 0
                        && $callback['transaction_id'] !== null
                        && $storedTransactionId !== null
                        && hash_equals(
                            $storedTransactionId,
                            $callback['transaction_id']
                        );

                    return [
                        'found' => true,
                        'level' => $sameSuccess ? 'info' : 'critical',
                        'message' => $sameSuccess
                            ? 'Duplicate M-Pesa B2C success callback accepted'
                            : 'Conflicting callback received for confirmed M-Pesa B2C payout',
                        'context' => $this->logContext(
                            $disbursement,
                            $callback,
                            $callbackType
                        ),
                        'response' =>
                            'Callback acknowledged; confirmed payout preserved.',
                    ];
                }

                /*
                 * A provider callback may only automatically finalise a row
                 * that previously reached a transmission state.
                 */
                if (
                    !in_array(
                        $currentStatus,
                        self::CALLBACK_ELIGIBLE_STATUSES,
                        true
                    )
                    && $currentStatus !== self::STATUS_FAILED_FINAL
                ) {
                    $error = sprintf(
                        'Callback received while local status was [%s]; manual review required.',
                        $currentStatus !== '' ? $currentStatus : 'EMPTY'
                    );

                    $this->markUnknown(
                        $disbursement,
                        $callback,
                        $storedPayload,
                        $error
                    );

                    return [
                        'found' => true,
                        'level' => 'critical',
                        'message' =>
                            'M-Pesa B2C callback received in an invalid local state',
                        'context' => $this->logContext(
                            $disbursement,
                            $callback,
                            $callbackType
                        ) + ['current_status' => $currentStatus],
                        'response' =>
                            'Callback accepted for manual review.',
                    ];
                }

                /*
                 * A timeout is ambiguous. The customer may still have been
                 * paid, so this record must never be automatically resent.
                 */
                if ($callbackType === 'timeout') {
                    $this->markUnknown(
                        $disbursement,
                        $callback,
                        $storedPayload,
                        'B2C timeout received; outcome unknown. Do not resend automatically.'
                    );

                    return [
                        'found' => true,
                        'level' => 'critical',
                        'message' =>
                            'M-Pesa B2C payout outcome is unknown after timeout',
                        'context' => $this->logContext(
                            $disbursement,
                            $callback,
                            $callbackType
                        ),
                        'response' => 'B2C timeout callback accepted.',
                    ];
                }

                if ($callback['result_code'] === null) {
                    $this->markUnknown(
                        $disbursement,
                        $callback,
                        $storedPayload,
                        'B2C result callback did not contain ResultCode.'
                    );

                    return [
                        'found' => true,
                        'level' => 'critical',
                        'message' =>
                            'M-Pesa B2C result callback has no ResultCode',
                        'context' => $this->logContext(
                            $disbursement,
                            $callback,
                            $callbackType
                        ),
                        'response' =>
                            'Callback accepted for manual review.',
                    ];
                }

                /*
                 * A non-zero final result is stored as a final failure. It is
                 * not placed into an automatic retry cycle.
                 */
                if ($callback['result_code'] !== 0) {
                    DB::table('sacco_loan_disbursements')
                        ->where('id', $disbursement->id)
                        ->update([
                            'status' => self::STATUS_FAILED_FINAL,
                            'bank_reference' =>
                                $callback['originator_conversation_id']
                                ?? $disbursement->bank_reference,
                            'bank_status_code' =>
                                (string) $callback['result_code'],
                            'bank_status_message' =>
                                $callback['result_description']
                                ?? 'M-Pesa B2C transaction failed',
                            'response_payload' => $storedPayload,
                            'next_retry_at' => null,
                            'last_error' =>
                                $callback['result_description']
                                ?? 'M-Pesa B2C transaction failed.',
                            'updated_at' => now(),
                        ]);

                    return [
                        'found' => true,
                        'level' => 'warning',
                        'message' => 'M-Pesa B2C disbursement failed',
                        'context' => $this->logContext(
                            $disbursement,
                            $callback,
                            $callbackType
                        ),
                        'response' => 'B2C failure callback accepted.',
                    ];
                }

                $validationErrors = $this->validateSuccess(
                    $disbursement,
                    $callback,
                    $currentStatus
                );

                if ($validationErrors !== []) {
                    $this->markUnknown(
                        $disbursement,
                        $callback,
                        $storedPayload,
                        implode('; ', $validationErrors)
                    );

                    return [
                        'found' => true,
                        'level' => 'critical',
                        'message' =>
                            'M-Pesa B2C success callback failed local validation',
                        'context' => $this->logContext(
                            $disbursement,
                            $callback,
                            $callbackType
                        ) + ['validation_errors' => $validationErrors],
                        'response' =>
                            'Callback accepted for manual review.',
                    ];
                }

                DB::table('sacco_loan_disbursements')
                    ->where('id', $disbursement->id)
                    ->update([
                        'status' => self::STATUS_CONFIRMED,
                        'confirmed_at' => now(),
                        'bank_reference' =>
                            $callback['originator_conversation_id']
                            ?? $disbursement->bank_reference,
                        'core_reference' => $callback['transaction_id'],
                        'bank_status_code' => '0',
                        'bank_status_message' =>
                            $callback['result_description']
                            ?? 'M-Pesa B2C transaction completed successfully',
                        'response_payload' => $storedPayload,
                        'next_retry_at' => null,
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                return [
                    'found' => true,
                    'level' => 'info',
                    'message' =>
                        'M-Pesa B2C loan disbursement confirmed',
                    'context' => $this->logContext(
                        $disbursement,
                        $callback,
                        $callbackType
                    ),
                    'response' =>
                        'B2C result processed successfully.',
                ];
            }, 3);

            if (($outcome['found'] ?? false) !== true) {
                Log::critical('M-Pesa B2C callback could not be matched', [
                    'callback_type' => $callbackType,
                    'originator_conversation_id' =>
                        $callback['originator_conversation_id'],
                    'conversation_id' => $callback['conversation_id'],
                    'transaction_id' => $callback['transaction_id'],
                    'ip_address' => $request->ip(),
                ]);

                /*
                 * Do not falsely acknowledge an unmatched financial callback.
                 */
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' =>
                        'Matching B2C disbursement was not found.',
                ], 503);
            }

            Log::log(
                $outcome['level'],
                $outcome['message'],
                $outcome['context']
            );

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => $outcome['response'],
            ]);
        } catch (Throwable $exception) {
            Log::critical('M-Pesa B2C callback processing failed', [
                'callback_type' => $callbackType,
                'originator_conversation_id' =>
                    $callback['originator_conversation_id'],
                'conversation_id' => $callback['conversation_id'],
                'transaction_id' => $callback['transaction_id'],
                'ip_address' => $request->ip(),
                'exception' => $exception,
            ]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' =>
                    'Temporary callback processing failure. Please retry.',
            ], 500);
        }
    }

    /**
     * Parse and validate the common B2C callback envelope.
     */
    private function parseCallback(Request $request): array
    {
        $rawBody = (string) $request->getContent();

        if (strlen($rawBody) > self::MAX_CALLBACK_BYTES) {
            throw new InvalidArgumentException(
                'Callback payload is too large.'
            );
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            $payload = $request->all();
        }

        $result = $payload['Result']
            ?? $payload['result']
            ?? null;

        if (!is_array($result)) {
            throw new InvalidArgumentException(
                'Invalid B2C callback structure.'
            );
        }

        $parameters = $this->extractResultParameters($result);

        $originatorConversationId = $this->stringOrNull(
            $result['OriginatorConversationID']
            ?? $result['originatorConversationID']
            ?? $result['originatorConversationId']
            ?? null
        );

        $conversationId = $this->stringOrNull(
            $result['ConversationID']
            ?? $result['conversationID']
            ?? $result['conversationId']
            ?? null
        );

        if (
            $originatorConversationId === null
            && $conversationId === null
        ) {
            throw new InvalidArgumentException(
                'Missing B2C conversation reference.'
            );
        }

        return [
            'payload' => $payload,
            'originator_conversation_id' =>
                $originatorConversationId,
            'conversation_id' => $conversationId,
            'result_code' => $this->integerOrNull(
                $result['ResultCode']
                ?? $result['resultCode']
                ?? null
            ),
            'result_description' => $this->stringOrNull(
                $result['ResultDesc']
                ?? $result['resultDesc']
                ?? null
            ),
            'transaction_id' => $this->stringOrNull(
                $result['TransactionID']
                ?? $result['transactionID']
                ?? $result['transactionId']
                ?? $parameters['TransactionReceipt']
                ?? null
            ),
            'amount' => $this->decimalOrNull(
                $parameters['TransactionAmount']
                ?? $parameters['Amount']
                ?? null
            ),
            'receiver_phone' => $this->extractKenyanPhone(
                $this->stringOrNull(
                    $parameters['ReceiverPartyPublicName']
                    ?? null
                )
            ),
        ];
    }

    /**
     * Find and lock the matching disbursement record.
     */
    private function findDisbursement(
        ?string $originatorConversationId,
        ?string $conversationId
    ): ?object {
        return DB::table('sacco_loan_disbursements')
            ->where('disbursement_provider', self::PROVIDER)
            ->where('disbursement_channel', self::CHANNEL)
            ->where(function ($query) use (
                $originatorConversationId,
                $conversationId
            ): void {
                if ($originatorConversationId !== null) {
                    $query->where(
                        'bank_reference',
                        $originatorConversationId
                    );
                }

                if ($conversationId !== null) {
                    if ($originatorConversationId !== null) {
                        $query->orWhere(
                            'core_reference',
                            $conversationId
                        );
                    } else {
                        $query->where(
                            'core_reference',
                            $conversationId
                        );
                    }
                }
            })
            ->lockForUpdate()
            ->first();
    }

    /**
     * Validate a provider-reported success before confirming the payout.
     */
    private function validateSuccess(
        object $disbursement,
        array $callback,
        string $currentStatus
    ): array {
        $errors = [];

        if ($callback['transaction_id'] === null) {
            $errors[] =
                'Successful callback has no M-Pesa transaction ID';
        }

        if ($currentStatus === self::STATUS_FAILED_FINAL) {
            $errors[] =
                'Success callback conflicts with an existing final failure';
        }

        $expectedAmount = round(
            (float) ($disbursement->amount ?? 0),
            2
        );

        if (
            $callback['amount'] !== null
            && abs($callback['amount'] - $expectedAmount) > 0.01
        ) {
            $errors[] = sprintf(
                'Amount mismatch: expected %.2f, received %.2f',
                $expectedAmount,
                $callback['amount']
            );
        }

        $expectedPhone = $this->normalizeKenyanPhone(
            $disbursement->member_phone ?? null
        );

        if (
            $callback['receiver_phone'] !== null
            && $expectedPhone !== null
            && !hash_equals(
                $expectedPhone,
                $callback['receiver_phone']
            )
        ) {
            $errors[] = sprintf(
                'Recipient phone mismatch: expected %s, received %s',
                $expectedPhone,
                $callback['receiver_phone']
            );
        }

        return $errors;
    }

    /**
     * Mark an ambiguous callback for manual review and block auto-retry.
     */
    private function markUnknown(
        object $disbursement,
        array $callback,
        string $storedPayload,
        string $error
    ): void {
        DB::table('sacco_loan_disbursements')
            ->where('id', $disbursement->id)
            ->update([
                'status' => self::STATUS_SEND_UNKNOWN,
                'bank_reference' =>
                    $callback['originator_conversation_id']
                    ?? $disbursement->bank_reference,
                'bank_status_code' =>
                    $callback['result_code'] !== null
                    ? (string) $callback['result_code']
                    : null,
                'bank_status_message' =>
                    $callback['result_description']
                    ?? 'M-Pesa B2C callback requires manual review',
                'response_payload' => $storedPayload,
                'next_retry_at' => null,
                'last_error' => $error,
                'updated_at' => now(),
            ]);
    }

    /**
     * Convert ResultParameter items into an associative array.
     */
    private function extractResultParameters(array $result): array
    {
        $items = $result['ResultParameters']['ResultParameter']
            ?? $result['resultParameters']['resultParameter']
            ?? [];

        if (!is_array($items)) {
            return [];
        }

        if (array_key_exists('Key', $items)) {
            $items = [$items];
        }

        $parameters = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $this->stringOrNull(
                $item['Key']
                ?? $item['key']
                ?? null
            );

            if ($key !== null) {
                $parameters[$key] = $item['Value']
                    ?? $item['value']
                    ?? null;
            }
        }

        return $parameters;
    }

    /**
     * Preserve the initial API response and store the latest callback.
     */
    private function mergeResponsePayload(
        ?string $existingPayload,
        string $callbackType,
        array $callbackPayload,
        ?string $ipAddress
    ): string {
        $storage = [];

        if (
            $existingPayload !== null
            && trim($existingPayload) !== ''
        ) {
            $decoded = json_decode($existingPayload, true);

            if (
                is_array($decoded)
                && ($decoded['_b2c_callback_store'] ?? false) === true
            ) {
                $storage = $decoded;
            } elseif (is_array($decoded)) {
                $storage['initial_response'] = $decoded;
            } else {
                $storage['initial_response_raw'] = $existingPayload;
            }
        }

        $storage['_b2c_callback_store'] = true;
        $storage['latest_' . $callbackType . '_callback'] = [
            'received_at' => now()->toIso8601String(),
            'ip_address' => $ipAddress,
            'payload' => $callbackPayload,
        ];

        return json_encode(
            $storage,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
    }

    private function logContext(
        object $disbursement,
        array $callback,
        string $callbackType
    ): array {
        return [
            'callback_type' => $callbackType,
            'disbursement_id' => $disbursement->id,
            'loan_id' => $disbursement->loan_id,
            'transaction_ref' => $disbursement->transaction_ref,
            'originator_conversation_id' =>
                $callback['originator_conversation_id'],
            'conversation_id' => $callback['conversation_id'],
            'transaction_id' => $callback['transaction_id'],
            'provider_result_code' => $callback['result_code'],
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (
            $value === null
            || is_array($value)
            || is_object($value)
        ) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function decimalOrNull(mixed $value): ?float
    {
        return is_numeric($value)
            ? round((float) $value, 2)
            : null;
    }

    private function extractKenyanPhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match(
            '/(?<![0-9])(254[17][0-9]{8})(?![0-9])/',
            $value,
            $matches
        ) === 1
            ? $matches[1]
            : null;
    }

    private function normalizeKenyanPhone(mixed $phone): ?string
    {
        if (
            $phone === null
            || is_array($phone)
            || is_object($phone)
        ) {
            return null;
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            trim((string) $phone)
        );

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00254')) {
            $digits = substr($digits, 2);
        }

        if (
            strlen($digits) === 10
            && str_starts_with($digits, '0')
        ) {
            $digits = '254' . substr($digits, 1);
        }

        if (
            strlen($digits) === 9
            && in_array($digits[0], ['7', '1'], true)
        ) {
            $digits = '254' . $digits;
        }

        return preg_match('/^254[17][0-9]{8}$/', $digits) === 1
            ? $digits
            : null;
    }
}
