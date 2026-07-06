<?php

namespace App\Services\BulkSms;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkSmsOutboxService
{
    public function __construct(
        protected BulkSmsConfigService $config
    ) {
    }

    public function queue(array $data): array
    {
        $rawPhone = $data['phone'] ?? $data['recipient_phone'] ?? null;
        $message = trim((string) ($data['message'] ?? ''));

        $normalizedPhone = $this->normalizeKenyanPhone($rawPhone);

        $network = $data['network'] ?? $this->detectKenyanNetwork($normalizedPhone);
        $network = strtolower(trim((string) $network));

        $finalNetwork = $network !== ''
            ? $network
            : $this->config->defaultNetwork();

        $providerCode = $this->config->providerCode();

        $isEnabled = $this->config->isEnabled();
        $isDemoMode = $this->config->isDemoMode();

        $status = 'queued';
        $mode = $isDemoMode ? 'demo' : 'live';
        $errorCode = null;
        $errorMessage = null;
        $failedAt = null;

        if (!$isEnabled) {
            $status = 'skipped';
            $errorCode = 'BULK_SMS_DISABLED';
            $errorMessage = 'Bulk SMS is disabled in sacco_defaults.';
        } elseif ($isDemoMode) {
            $status = 'demo';
            $errorCode = 'BULK_SMS_DEMO_MODE';
            $errorMessage = 'Bulk SMS demo mode is enabled. Message was logged but not sent.';
        }

        if (!$normalizedPhone) {
            $status = 'failed';
            $errorCode = 'INVALID_PHONE';
            $errorMessage = 'Recipient phone number is missing or invalid.';
            $failedAt = now();
        }

        if ($message === '') {
            $status = 'failed';
            $errorCode = 'EMPTY_MESSAGE';
            $errorMessage = 'SMS message is empty.';
            $failedAt = now();
        }

        $characterCount = mb_strlen($message);
        $segments = $this->estimateSmsSegments($message);

        $smsId = DB::table('sacco_bulk_sms_messages')->insertGetId([
            'notif_id' => $data['notif_id'] ?? null,
            'member_id' => $data['member_id'] ?? null,

            'provider_code' => $providerCode,
            'sms_mode' => $mode,
            'sms_status' => $status,

            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_phone_raw' => $rawPhone,
            'recipient_phone_normalized' => $normalizedPhone,
            'recipient_network' => $finalNetwork,

            'sender_id' => $data['sender_id'] ?? $this->config->defaultSenderId(),

            'sms_subject' => $data['subject'] ?? null,
            'sms_message' => $message,
            'sms_character_count' => $characterCount,
            'sms_segments' => $segments,

            'request_reference' => $data['request_reference'] ?? $this->makeRequestReference(),

            'error_code' => $errorCode,
            'error_message' => $errorMessage,

            'attempt_count' => 0,

            'queued_at' => now(),
            'failed_at' => $failedAt,

            'request_payload' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'sms_meta' => isset($data['meta'])
                ? json_encode($data['meta'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,

            'created_by' => Auth::id(),
            'created_ip' => request()?->ip(),

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'logged' => true,
            'sendable' => $status === 'queued',
            'sent' => false,
            'sms_id' => $smsId,
            'status' => $status,
            'mode' => $mode,
            'provider_code' => $providerCode,
            'phone' => $normalizedPhone,
            'network' => $finalNetwork,
            'segments' => $segments,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];
    }

    public function normalizeKenyanPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = trim($phone);
        $phone = str_replace([' ', '-', '(', ')'], '', $phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (str_starts_with($phone, '07') || str_starts_with($phone, '01')) {
            $phone = '254' . substr($phone, 1);
        }

        if (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            $phone = '254' . $phone;
        }

        if (!preg_match('/^254(7|1)[0-9]{8}$/', $phone)) {
            return null;
        }

        return $phone;
    }

    public function detectKenyanNetwork(?string $phone): string
    {
        if (!$phone) {
            return $this->config->defaultNetwork();
        }

        /*
         * Practical default:
         * Number portability means this is not guaranteed.
         * If unsure, use configured default network.
         */

        $prefix = substr($phone, 0, 6);

        $safaricomPrefixes = [
            '25470', '25471', '25472', '25474', '25479',
            '25411',
        ];

        $airtelPrefixes = [
            '25473', '25475', '25478', '25410',
        ];

        $telkomPrefixes = [
            '25477',
        ];

        foreach ($safaricomPrefixes as $p) {
            if (str_starts_with($prefix, $p)) {
                return 'safaricom';
            }
        }

        foreach ($airtelPrefixes as $p) {
            if (str_starts_with($prefix, $p)) {
                return 'airtel';
            }
        }

        foreach ($telkomPrefixes as $p) {
            if (str_starts_with($prefix, $p)) {
                return 'telkom';
            }
        }

        return $this->config->defaultNetwork();
    }

    public function estimateSmsSegments(string $message): int
    {
        $length = mb_strlen($message);

        if ($length <= 160) {
            return 1;
        }

        return (int) ceil($length / 153);
    }

    protected function makeRequestReference(): string
    {
        return 'SMS-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(8));
    }
}