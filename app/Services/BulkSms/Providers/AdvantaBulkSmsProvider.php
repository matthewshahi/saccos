<?php

namespace App\Services\BulkSms\Providers;

use App\Services\BulkSms\BulkSmsConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdvantaBulkSmsProvider
{
    public function __construct(
        protected BulkSmsConfigService $config
    ) {
    }

    public function send(object $sms): array
    {
        $sendUrl = (string) $this->config->providerConfig(
            'SEND_URL',
            'https://quicksms.advantasms.com/api/services/sendsms'
        );

        $apiKey = (string) $this->config->providerConfig('API_KEY');
        $partnerId = (string) $this->config->providerConfig('PARTNER_ID');

        $shortcode = (string) (
            $sms->sender_id
            ?: $this->config->providerConfig('SHORTCODE')
            ?: $this->config->defaultSenderId()
        );

        $payload = [
            'apikey' => $apiKey,
            'partnerID' => $partnerId,
            'message' => (string) $sms->sms_message,
            'shortcode' => $shortcode,
            'mobile' => (string) $sms->recipient_phone_normalized,
        ];

        /*
         * Never persist the real API key in request logs.
         */
        $safePayload = $payload;
        $safePayload['apikey'] = $apiKey !== '' ? '********' : null;

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(15)
                ->timeout(45)
                ->retry(2, 1000, throw: false)
                ->post($sendUrl, $payload);

            $body = $response->json();

            if (!is_array($body)) {
                $body = [
                    'raw_response' => $response->body(),
                ];
            }

            /*
             * Success responses place the result inside responses[0].
             * Credential and other top-level errors use response-code.
             */
            $providerResponse = $body['responses'][0] ?? $body;

            $responseCode = (int) (
                $providerResponse['response-code']
                ?? $body['response-code']
                ?? 0
            );

            $responseDescription = (string) (
                $providerResponse['response-description']
                ?? $body['response-description']
                ?? ''
            );

            $messageId = $providerResponse['messageid'] ?? null;

            $sent = $response->successful()
                && $responseCode === 200
                && $messageId !== null
                && $messageId !== '';

            return [
                'success' => $sent,
                'sent' => $sent,
                'provider_message_id' => $messageId
                    ? (string) $messageId
                    : null,
                'http_status' => $response->status(),
                'request_payload' => json_encode(
                    $safePayload,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
                'response_payload' => json_encode(
                    $body,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
                'error_code' => $sent
                    ? null
                    : ($responseCode > 0
                        ? 'ADVANTA_' . $responseCode
                        : 'ADVANTA_SEND_FAILED'),
                'error_message' => $sent
                    ? null
                    : ($responseDescription !== ''
                        ? $responseDescription
                        : 'Advanta SMS request failed.'),
            ];
        } catch (Throwable $e) {
            Log::error('Advanta SMS request exception.', [
                'sms_id' => $sms->sms_id ?? null,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'sent' => false,
                'provider_message_id' => null,
                'http_status' => null,
                'request_payload' => json_encode(
                    $safePayload,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
                'response_payload' => null,
                'error_code' => 'ADVANTA_EXCEPTION',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    public function getBalance(): array
    {
        $balanceUrl = (string) $this->config->providerConfig(
            'BALANCE_URL',
            'https://quicksms.advantasms.com/api/services/getbalance'
        );

        $payload = [
            'apikey' => (string) $this->config->providerConfig('API_KEY'),
            'partnerID' => (string) $this->config->providerConfig('PARTNER_ID'),
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(15)
                ->timeout(30)
                ->post($balanceUrl, $payload);

            $body = $response->json();

            if (!is_array($body)) {
                $body = [
                    'raw_response' => $response->body(),
                ];
            }

            $responseCode = (int) ($body['response-code'] ?? 0);

            return [
                'success' => $response->successful() && $responseCode === 200,
                'http_status' => $response->status(),
                'response_code' => $responseCode,
                'credit' => $body['credit'] ?? null,
                'partner_id' => $body['partner-id'] ?? null,
                'error_message' => $responseCode === 200
                    ? null
                    : ($body['response-description'] ?? 'Balance request failed.'),
                'response_payload' => $body,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'http_status' => null,
                'response_code' => null,
                'credit' => null,
                'partner_id' => null,
                'error_message' => $e->getMessage(),
                'response_payload' => null,
            ];
        }
    }
}