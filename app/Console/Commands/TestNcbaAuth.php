<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestNcbaAuth extends Command
{
    protected $signature = 'ncba:test-auth';

    protected $description = 'Test NCBA Open Banking UAT authentication only';

    public function handle(): int
    {
        $baseUrl = rtrim(config('services.ncba.base_url'), '/');

        if (empty($baseUrl)) {
            $this->error('NCBA_BASE_URL is missing.');
            return self::FAILURE;
        }

        if (empty(config('services.ncba.user_id'))) {
            $this->error('NCBA_USER_ID is missing.');
            return self::FAILURE;
        }

        if (empty(config('services.ncba.password'))) {
            $this->error('NCBA_PASSWORD is missing.');
            return self::FAILURE;
        }

        if (empty(config('services.ncba.subscription_key'))) {
            $this->error('NCBA_SUBSCRIPTION_KEY is missing.');
            return self::FAILURE;
        }

        $url = $baseUrl . '/api/v1/Auth/generate-token';

        $this->info('Testing NCBA auth...');
        $this->line('URL: ' . $url);

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Ocp-Apim-Subscription-Key' => config('services.ncba.subscription_key'),
            ])
            ->post($url, [
                'userID' => config('services.ncba.user_id'),
                'password' => config('services.ncba.password'),
            ]);

        $this->line('HTTP Status: ' . $response->status());

        $json = $response->json();

        if (! is_array($json)) {
            $this->error('NCBA returned non-JSON response:');
            $this->line($response->body());
            return self::FAILURE;
        }

        $redacted = $this->redactTokens($json);

        $this->line(json_encode($redacted, JSON_PRETTY_PRINT));

        if (! $response->successful()) {
            $this->error('NCBA auth failed.');
            return self::FAILURE;
        }

        $token = $json['token']
            ?? $json['access_token']
            ?? $json['accessToken']
            ?? $json['data']['token']
            ?? $json['data']['access_token']
            ?? $json['data']['accessToken']
            ?? null;

        if (! $token) {
            $this->warn('Request succeeded, but token field was not recognized. We may need to adjust token parsing.');
            return self::FAILURE;
        }

        $this->info('NCBA auth successful. Token received and redacted.');

        return self::SUCCESS;
    }

    private function redactTokens(array $data): array
    {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (str_contains($lowerKey, 'token')) {
                $data[$key] = '***REDACTED***';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactTokens($value);
            }
        }

        return $data;
    }
}
