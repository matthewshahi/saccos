<?php

namespace App\Services\BulkSms;

use App\Services\BulkSms\Providers\AdtelBulkSmsProvider;
use Illuminate\Support\Facades\DB;

class BulkSmsDispatchService
{
    public function __construct(
        protected BulkSmsConfigService $config,
        protected AdtelBulkSmsProvider $adtel
    ) {
    }

    public function dispatchOne(int $smsId): array
    {
        $sms = DB::table('sacco_bulk_sms_messages')
            ->where('sms_id', $smsId)
            ->first();

        if (!$sms) {
            return [
                'success' => false,
                'sent' => false,
                'error_code' => 'SMS_NOT_FOUND',
                'error_message' => 'SMS record was not found.',
            ];
        }

        if ($sms->sms_status !== 'queued') {
            return [
                'success' => true,
                'sent' => false,
                'sms_id' => $smsId,
                'status' => $sms->sms_status,
                'message' => 'SMS is not queued for sending.',
            ];
        }

        $readiness = $this->config->readiness();

        if (!$this->config->isEnabled()) {
            return $this->markStopped(
                $smsId,
                'skipped',
                'BULK_SMS_DISABLED',
                'Bulk SMS is disabled in sacco_defaults.',
                $readiness
            );
        }

        if ($this->config->isDemoMode()) {
            return $this->markStopped(
                $smsId,
                'demo',
                'BULK_SMS_DEMO_MODE',
                'Bulk SMS demo mode is enabled. Message was not sent.',
                $readiness
            );
        }

        if (!$readiness['ready_to_send']) {
            return $this->markStopped(
                $smsId,
                'failed',
                'BULK_SMS_NOT_READY',
                implode(' ', $readiness['issues']),
                $readiness
            );
        }

        $providerCode = strtolower((string) $sms->provider_code);

        if ($providerCode === 'adtel') {
            $result = $this->adtel->send($sms);

            return $this->applyProviderResult($smsId, $result);
        }

        return $this->markStopped(
            $smsId,
            'failed',
            'UNSUPPORTED_SMS_PROVIDER',
            'Unsupported SMS provider: ' . $providerCode,
            $readiness
        );
    }

    public function dispatchQueued(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $messages = DB::table('sacco_bulk_sms_messages')
            ->where('sms_status', 'queued')
            ->orderBy('sms_id')
            ->limit($limit)
            ->get();

        $results = [];

        foreach ($messages as $message) {
            $results[] = $this->dispatchOne((int) $message->sms_id);
        }

        return [
            'count' => count($results),
            'results' => $results,
        ];
    }

    protected function applyProviderResult(int $smsId, array $result): array
    {
        $sent = (bool) ($result['sent'] ?? false);
        $status = $sent ? 'sent' : 'failed';

        $update = [
            'sms_status' => $status,
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'request_payload' => $result['request_payload'] ?? null,
            'response_payload' => $result['response_payload'] ?? null,
            'error_code' => $sent ? null : ($result['error_code'] ?? 'SMS_SEND_FAILED'),
            'error_message' => $sent ? null : ($result['error_message'] ?? 'SMS send failed.'),
            'attempt_count' => DB::raw('COALESCE(attempt_count, 0) + 1'),
            'updated_at' => now(),
        ];

        if ($sent) {
            $update['sent_at'] = now();
            $update['failed_at'] = null;
        } else {
            $update['failed_at'] = now();
        }

        DB::table('sacco_bulk_sms_messages')
            ->where('sms_id', $smsId)
            ->update($update);

        return [
            'success' => $sent,
            'sent' => $sent,
            'sms_id' => $smsId,
            'status' => $status,
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'http_status' => $result['http_status'] ?? null,
            'error_code' => $sent ? null : ($result['error_code'] ?? 'SMS_SEND_FAILED'),
            'error_message' => $sent ? null : ($result['error_message'] ?? 'SMS send failed.'),
        ];
    }

    protected function markStopped(
        int $smsId,
        string $status,
        string $errorCode,
        string $errorMessage,
        array $readiness = []
    ): array {
        $update = [
            'sms_status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'response_payload' => json_encode([
                'readiness' => $readiness,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'attempt_count' => DB::raw('COALESCE(attempt_count, 0) + 1'),
            'updated_at' => now(),
        ];

        if ($status === 'failed') {
            $update['failed_at'] = now();
        }

        DB::table('sacco_bulk_sms_messages')
            ->where('sms_id', $smsId)
            ->update($update);

        return [
            'success' => $status !== 'failed',
            'sent' => false,
            'sms_id' => $smsId,
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];
    }
}