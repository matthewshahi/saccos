<?php

namespace App\Services\BulkSms\Providers;

use App\Services\BulkSms\BulkSmsConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdtelBulkSmsProvider
{
    public function __construct(
        protected BulkSmsConfigService $config
    ) {
    }

    public function send(object $sms): array
    {
        $tokenResult = $this->getAccessToken();

        if (!($tokenResult['success'] ?? false)) {
            return [
                'success' => false,
                'sent' => false,
                'status' => 'failed',
                'error_code' => $tokenResult['error_code'] ?? 'ADTEL_TOKEN_FAILED',
                'error_message' => $tokenResult['error_message'] ?? 'Unable to obtain ADTEL access token.',
                'request_payload' => null,
                'response_payload' => json_encode($tokenResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'http_status' => $tokenResult['http_status'] ?? null,
                'provider_message_id' => null,
            ];
        }

        $sendUrl = $this->providerSendUrl();
        $senderId = $sms->sender_id
            ?: $this->config->providerConfig('SENDER_ID')
            ?: $this->config->defaultSenderId();

        $actionResponseUrl = $this->config->providerConfig('ACTION_RESPONSE_URL', 'https://api.adtel.co.ke');
        $timeout = (int) $this->config->providerConfig('TIMEOUT_SECONDS', 30);

        if (!$sendUrl) {
            return $this->failedResult(
                'ADTEL_SEND_URL_MISSING',
                'ADTEL SMS send URL is missing.'
            );
        }

        if (!$senderId) {
            return $this->failedResult(
                'ADTEL_SENDER_ID_MISSING',
                'ADTEL sender ID is missing.'
            );
        }

        if (empty($sms->recipient_phone_normalized)) {
            return $this->failedResult(
                'ADTEL_MSISDN_MISSING',
                'Recipient phone number is missing.'
            );
        }

        if (trim((string) $sms->sms_message) === '') {
            return $this->failedResult(
                'ADTEL_MESSAGE_EMPTY',
                'SMS message is empty.'
            );
        }

        $timestamp = now()->format('Y-m-d H:i:s');

        $payload = [
            'timeStamp' => $timestamp,
            'messageBag' => [
                [
                    'msisdn' => $sms->recipient_phone_normalized,
                    'message' => $sms->sms_message,
                    'from' => $senderId,
                    'uniqueId' => $sms->request_reference ?: ('SMS-' . $sms->sms_id),
                    'timeStamp' => $timestamp,
                    'actionResponseURL' => $actionResponseUrl,
                ],
            ],
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($tokenResult['access_token'])
                ->timeout($timeout)
                ->post($sendUrl, $payload);

            $json = $response->json();
            $body = $response->body();

            $providerMessageId = $this->extractProviderMessageId($json, $sms);

            $sent = $this->isSuccessfulSend($response->status(), $json, $body);

            return [
                'success' => $sent,
                'sent' => $sent,
                'status' => $sent ? 'sent' : 'failed',
                'provider_message_id' => $providerMessageId,
                'http_status' => $response->status(),
                'request_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'response_payload' => json_encode([
                    'http_status' => $response->status(),
                    'json' => $json,
                    'body_preview' => mb_substr($body, 0, 1000),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'error_code' => $sent ? null : 'ADTEL_SEND_FAILED',
                'error_message' => $sent ? null : $this->extractErrorMessage($json, $body),
            ];
        } catch (\Throwable $e) {
            Log::error('ADTEL SMS send exception.', [
                'sms_id' => $sms->sms_id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'sent' => false,
                'status' => 'failed',
                'provider_message_id' => null,
                'http_status' => null,
                'request_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'response_payload' => json_encode([
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'error_code' => 'ADTEL_SEND_EXCEPTION',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    public function getAccessToken(): array
    {
        $authUrl = $this->providerAuthUrl();
        $clientId = $this->config->providerConfig('CLIENT_ID');
        $clientSecret = $this->config->providerConfig('CLIENT_SECRET');
        $basicAuthToken = $this->config->providerConfig('BASIC_AUTH_TOKEN');
        $username = $this->config->providerConfig('USERNAME');
        $password = $this->config->providerConfig('PASSWORD');
        $grantType = $this->config->providerConfig('GRANT_TYPE', 'password');
        $timeout = (int) $this->config->providerConfig('TIMEOUT_SECONDS', 30);

        $missing = [];

        if (!$authUrl) {
            $missing[] = 'AUTH_URL';
        }

        if (!$basicAuthToken && (!$clientId || !$clientSecret)) {
            $missing[] = 'CLIENT_ID/CLIENT_SECRET or BASIC_AUTH_TOKEN';
        }

        if (!$username) {
            $missing[] = 'USERNAME';
        }

        if (!$password) {
            $missing[] = 'PASSWORD';
        }

        if (!empty($missing)) {
            return [
                'success' => false,
                'authenticated' => false,
                'error_code' => 'ADTEL_AUTH_CONFIG_MISSING',
                'error_message' => 'Missing ADTEL auth config: ' . implode(', ', $missing),
            ];
        }

        try {
            $request = Http::asForm()
                ->acceptJson()
                ->timeout($timeout);

            if ($basicAuthToken) {
                $request = $request->withHeaders([
                    'Authorization' => 'Basic ' . $basicAuthToken,
                ]);
            } else {
                $request = $request->withBasicAuth($clientId, $clientSecret);
            }

            $response = $request->post($authUrl, [
                'grant_type' => $grantType,
                'username' => $username,
                'password' => $password,
            ]);

            $json = $response->json();
            $accessToken = is_array($json) ? ($json['access_token'] ?? null) : null;

            if ($response->successful() && $accessToken) {
                return [
                    'success' => true,
                    'authenticated' => true,
                    'http_status' => $response->status(),
                    'access_token' => $accessToken,
                    'token_type' => $json['token_type'] ?? null,
                    'expires_in' => $json['expires_in'] ?? null,
                    'scope' => $json['scope'] ?? null,
                ];
            }

            return [
                'success' => false,
                'authenticated' => false,
                'http_status' => $response->status(),
                'error_code' => 'ADTEL_TOKEN_FAILED',
                'error_message' => 'ADTEL token request failed.',
                'response_preview' => mb_substr($response->body(), 0, 1000),
            ];
        } catch (\Throwable $e) {
            Log::error('ADTEL token exception.', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'authenticated' => false,
                'http_status' => null,
                'error_code' => 'ADTEL_TOKEN_EXCEPTION',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    protected function providerAuthUrl(): ?string
    {
        $url = $this->config->providerConfig('AUTH_URL');

        if ($url) {
            return $url;
        }

        $provider = $this->config->provider();

        return $provider->provider_token_url ?? null;
    }

    protected function providerSendUrl(): ?string
    {
        $url = $this->config->providerConfig('SEND_URL');

        if ($url) {
            return $url;
        }

        $provider = $this->config->provider();

        return $provider->provider_send_url ?? null;
    }

    protected function failedResult(string $code, string $message): array
    {
        return [
            'success' => false,
            'sent' => false,
            'status' => 'failed',
            'provider_message_id' => null,
            'http_status' => null,
            'request_payload' => null,
            'response_payload' => null,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }

    protected function extractProviderMessageId($json, object $sms): ?string
    {
        if (!is_array($json)) {
            return $sms->request_reference ?? null;
        }

        return $json['message_id']
            ?? $json['messageId']
            ?? $json['id']
            ?? $json['transaction_id']
            ?? $json['transactionId']
            ?? $json['uniqueId']
            ?? $json['data']['message_id']
            ?? $json['data']['messageId']
            ?? $sms->request_reference
            ?? null;
    }

    protected function isSuccessfulSend(int $httpStatus, $json, string $body): bool
    {
        if ($httpStatus < 200 || $httpStatus >= 300) {
            return false;
        }

        if (!is_array($json)) {
            return true;
        }

        $status = strtolower((string) (
            $json['status']
            ?? $json['response_status']
            ?? $json['responseStatus']
            ?? $json['message']
            ?? ''
        ));

        $code = strtolower((string) (
            $json['code']
            ?? $json['response_code']
            ?? $json['responseCode']
            ?? ''
        ));

        $badWords = ['fail', 'failed', 'error', 'invalid', 'denied', 'unauthorized', 'insufficient'];

        foreach ($badWords as $badWord) {
            if (str_contains($status, $badWord) || str_contains($code, $badWord)) {
                return false;
            }
        }

        return true;
    }

    protected function extractErrorMessage($json, string $body): string
    {
        if (is_array($json)) {
            return (string) (
                $json['error_description']
                ?? $json['error']
                ?? $json['message']
                ?? $json['response_message']
                ?? $json['responseMessage']
                ?? 'ADTEL SMS send failed.'
            );
        }

        return mb_substr($body, 0, 500) ?: 'ADTEL SMS send failed.';
    }
}