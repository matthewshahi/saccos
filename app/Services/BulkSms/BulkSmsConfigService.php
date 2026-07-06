<?php

namespace App\Services\BulkSms;

use Illuminate\Support\Facades\DB;

class BulkSmsConfigService
{
    public function defaultValue(string $name, $fallback = null)
    {
        $value = DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->value('default_value');

        return $value !== null ? $value : $fallback;
    }

    public function isEnabled(): bool
    {
        return strtoupper((string) $this->defaultValue('BULK_SMS_ENABLED', 'N')) === 'Y';
    }

    public function isDemoMode(): bool
    {
        return strtoupper((string) $this->defaultValue('BULK_SMS_DEMO_MODE', 'Y')) === 'Y';
    }

    public function failClosed(): bool
    {
        return strtoupper((string) $this->defaultValue('BULK_SMS_FAIL_CLOSED', 'Y')) === 'Y';
    }

    public function providerCode(): string
    {
        return strtolower(trim((string) $this->defaultValue('BULK_SMS_PROVIDER', 'adtel')));
    }

    public function defaultNetwork(): string
    {
        return strtolower(trim((string) $this->defaultValue('BULK_SMS_DEFAULT_NETWORK', 'safaricom')));
    }

    public function defaultSenderId(): ?string
    {
        $value = $this->defaultValue('BULK_SMS_DEFAULT_SENDER_ID');

        return $value ? trim((string) $value) : null;
    }

    public function provider(): ?object
    {
        return DB::table('sacco_bulk_sms_providers')
            ->where('provider_code', $this->providerCode())
            ->first();
    }

    public function providerIsEnabled(): bool
    {
        $provider = $this->provider();

        if (!$provider) {
            return false;
        }

        return strtoupper((string) $provider->provider_enabled) === 'Y';
    }

    public function providerConfig(string $key, $fallback = null)
    {
        $providerCode = $this->providerCode();

        $config = DB::table('sacco_bulk_sms_provider_configs')
            ->where('provider_code', $providerCode)
            ->where('config_key', strtoupper($key))
            ->first();

        if (!$config) {
            return $fallback;
        }

        /*
         * If an ENV key is defined and has a value, ENV wins.
         * This keeps secrets outside the database.
         */
        if (!empty($config->config_env_key)) {
            $envValue = env($config->config_env_key);

            if ($envValue !== null && $envValue !== '') {
                return $envValue;
            }
        }

        return $config->config_value !== null ? $config->config_value : $fallback;
    }

    public function providerConfigs(): array
    {
        $providerCode = $this->providerCode();

        $configs = DB::table('sacco_bulk_sms_provider_configs')
            ->where('provider_code', $providerCode)
            ->get();

        $out = [];

        foreach ($configs as $config) {
            $value = $config->config_value;

            if (!empty($config->config_env_key)) {
                $envValue = env($config->config_env_key);

                if ($envValue !== null && $envValue !== '') {
                    $value = $envValue;
                }
            }

            $out[$config->config_key] = [
                'value' => $config->config_is_secret === 'Y' && $value
                    ? '********'
                    : $value,
                'env_key' => $config->config_env_key,
                'is_secret' => $config->config_is_secret,
                'is_required' => $config->config_is_required,
            ];
        }

        return $out;
    }

    public function readiness(): array
    {
        $provider = $this->provider();

        $issues = [];

        if (!$this->isEnabled()) {
            $issues[] = 'Bulk SMS is disabled in sacco_defaults.';
        }

        if (!$provider) {
            $issues[] = 'Selected Bulk SMS provider does not exist.';
        }

        if ($provider && strtoupper((string) $provider->provider_enabled) !== 'Y') {
            $issues[] = 'Selected Bulk SMS provider is disabled.';
        }

        if ($provider && empty($provider->provider_send_url)) {
            $issues[] = 'Provider SMS send URL is not configured.';
        }

        $requiredConfigs = DB::table('sacco_bulk_sms_provider_configs')
            ->where('provider_code', $this->providerCode())
            ->where('config_is_required', 'Y')
            ->get();

        foreach ($requiredConfigs as $config) {
            $value = $this->providerConfig($config->config_key);

            if ($value === null || $value === '') {
                $issues[] = "Missing required provider config: {$config->config_key}.";
            }
        }

        return [
            'enabled' => $this->isEnabled(),
            'demo_mode' => $this->isDemoMode(),
            'provider_code' => $this->providerCode(),
            'provider_exists' => $provider !== null,
            'provider_enabled' => $this->providerIsEnabled(),
            'default_network' => $this->defaultNetwork(),
            'default_sender_id' => $this->defaultSenderId(),
            'ready_to_send' => empty($issues),
            'issues' => $issues,
        ];
    }
}