<?php

namespace App\Services\BulkSms;

use Illuminate\Support\Facades\DB;

class BulkSmsDispatchService
{
    public function __construct(
        protected BulkSmsConfigService $config
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

        /*
         * Real provider sending will be added here after we confirm:
         * - provider send URL
         * - request payload format
         * - success response format
         * - error response format
         * - delivery callback format
         */

        return $this->markStopped(
            $smsId,
            'failed',
            'PROVIDER_SEND_NOT_IMPLEMENTED',
            'Provider send function has not yet been implemented.',
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