<?php

namespace App\Services\BulkSms;

use Illuminate\Support\Facades\DB;

class BulkSmsConfigService
{
    /**
     * Read a value from sacco_defaults.
     */
    public function defaultValue(string $name, mixed $fallback = null): mixed
    {
        $value = DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->value('default_value');

        return $value !== null ? $value : $fallback;
    }

    /**
     * Check whether the Bulk SMS module is enabled.
     */
    public function isEnabled(): bool
    {
        return strtoupper(
            (string) $this->defaultValue('BULK_SMS_ENABLED', 'N')
        ) === 'Y';
    }

    /**
     * Check whether messages should only be logged without being sent.
     */
    public function isDemoMode(): bool
    {
        return strtoupper(
            (string) $this->defaultValue('BULK_SMS_DEMO_MODE', 'Y')
        ) === 'Y';
    }

    /**
     * Determine whether SMS processing should stop when configuration
     * or provider readiness checks fail.
     */
    public function failClosed(): bool
    {
        return strtoupper(
            (string) $this->defaultValue('BULK_SMS_FAIL_CLOSED', 'Y')
        ) === 'Y';
    }

    /**
     * Get the active SMS provider code.
     *
     * Examples:
     * - adtel
     * - advanta
     */
    public function providerCode(): string
    {
        return strtolower(trim(
            (string) $this->defaultValue('BULK_SMS_PROVIDER', 'adtel')
        ));
    }

    /**
     * Get the default recipient network.
     */
    public function defaultNetwork(): string
    {
        return strtolower(trim(
            (string) $this->defaultValue(
                'BULK_SMS_DEFAULT_NETWORK',
                'safaricom'
            )
        ));
    }

    /**
     * Get the default sender ID configured for the SACCO.
     */
    public function defaultSenderId(): ?string
    {
        $value = $this->defaultValue('BULK_SMS_DEFAULT_SENDER_ID');

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * Get the active provider database record.
     */
    public function provider(): ?object
    {
        return DB::table('sacco_bulk_sms_providers')
            ->where('provider_code', $this->providerCode())
            ->first();
    }

    /**
     * Check whether the active provider is enabled.
     */
    public function providerIsEnabled(): bool
    {
        $provider = $this->provider();

        if (!$provider) {
            return false;
        }

        return strtoupper(
            (string) $provider->provider_enabled
        ) === 'Y';
    }

    /**
     * Get one configuration value for the active provider.
     *
     * Environment variables take priority over database values.
     */
    public function providerConfig(
        string $key,
        mixed $fallback = null
    ): mixed {
        $providerCode = $this->providerCode();
        $configKey = strtoupper(trim($key));

        $config = DB::table('sacco_bulk_sms_provider_configs')
            ->where('provider_code', $providerCode)
            ->where('config_key', $configKey)
            ->first();

        if (!$config) {
            return $fallback;
        }

        /*
         * When an environment key is defined and contains a value,
         * use it instead of the database value.
         */
        if (!empty($config->config_env_key)) {
            $environmentValue = env(
                trim((string) $config->config_env_key)
            );

            if (
                $environmentValue !== null
                && $environmentValue !== ''
            ) {
                return $environmentValue;
            }
        }

        return $config->config_value !== null
            ? $config->config_value
            : $fallback;
    }

    /**
     * Return all configurations for the active provider.
     *
     * Secret values are masked before being returned to the interface.
     */
    public function providerConfigs(): array
    {
        $providerCode = $this->providerCode();

        $configs = DB::table('sacco_bulk_sms_provider_configs')
            ->where('provider_code', $providerCode)
            ->orderBy('config_key')
            ->get();

        $output = [];

        foreach ($configs as $config) {
            $value = $config->config_value;

            if (!empty($config->config_env_key)) {
                $environmentValue = env(
                    trim((string) $config->config_env_key)
                );

                if (
                    $environmentValue !== null
                    && $environmentValue !== ''
                ) {
                    $value = $environmentValue;
                }
            }

            $isSecret = strtoupper(
                (string) $config->config_is_secret
            ) === 'Y';

            $output[$config->config_key] = [
                'value' => $isSecret && $value
                    ? '********'
                    : $value,

                'env_key' => $config->config_env_key,
                'is_secret' => $config->config_is_secret,
                'is_required' => $config->config_is_required,
            ];
        }

        return $output;
    }

    /**
     * Check whether the active provider is fully configured and ready
     * to send SMS messages.
     */
    public function readiness(): array
    {
        $providerCode = $this->providerCode();
        $provider = $this->provider();

        $issues = [];

        /*
         * Global Bulk SMS status.
         */
        if (!$this->isEnabled()) {
            $issues[] = 'Bulk SMS is disabled in sacco_defaults.';
        }

        /*
         * Provider existence and status.
         */
        if (!$provider) {
            $issues[] = 'Selected Bulk SMS provider does not exist.';
        }

        if (
            $provider
            && strtoupper((string) $provider->provider_enabled) !== 'Y'
        ) {
            $issues[] = 'Selected Bulk SMS provider is disabled.';
        }

        /*
         * The send URL may be supplied through:
         *
         * 1. SEND_URL in sacco_bulk_sms_provider_configs; or
         * 2. provider_send_url in sacco_bulk_sms_providers.
         */
        $configuredSendUrl = $this->providerConfig('SEND_URL')
            ?: ($provider->provider_send_url ?? null);

        if ($provider && !$configuredSendUrl) {
            $issues[] = 'Provider SMS send URL is not configured.';
        }

        /*
         |--------------------------------------------------------------------------
         | ADTEL readiness
         |--------------------------------------------------------------------------
         */
        if ($providerCode === 'adtel') {
            $authUrl = $this->providerConfig('AUTH_URL')
                ?: ($provider->provider_token_url ?? null);

            $username = $this->providerConfig('USERNAME');
            $password = $this->providerConfig('PASSWORD');

            $clientId = $this->providerConfig('CLIENT_ID');
            $clientSecret = $this->providerConfig('CLIENT_SECRET');
            $basicAuthToken = $this->providerConfig(
                'BASIC_AUTH_TOKEN'
            );

            $senderId = $this->providerConfig('SENDER_ID')
                ?: $this->defaultSenderId();

            $actionResponseUrl = $this->providerConfig(
                'ACTION_RESPONSE_URL'
            );

            if (!$authUrl) {
                $issues[] = 'Missing required ADTEL config: AUTH_URL.';
            }

            if (!$username) {
                $issues[] = 'Missing required ADTEL config: USERNAME.';
            }

            if (!$password) {
                $issues[] = 'Missing required ADTEL config: PASSWORD.';
            }

            if (
                !$basicAuthToken
                && (!$clientId || !$clientSecret)
            ) {
                $issues[] = 'Missing ADTEL Basic Auth credentials: provide either BASIC_AUTH_TOKEN or CLIENT_ID and CLIENT_SECRET.';
            }

            if (!$senderId) {
                $issues[] = 'Missing ADTEL sender ID. Set SENDER_ID or BULK_SMS_DEFAULT_SENDER_ID.';
            }

            if (!$actionResponseUrl) {
                $issues[] = 'Missing required ADTEL config: ACTION_RESPONSE_URL.';
            }
        }

        /*
         |--------------------------------------------------------------------------
         | Advanta readiness
         |--------------------------------------------------------------------------
         */
        elseif ($providerCode === 'advanta') {
            $apiKey = $this->providerConfig('API_KEY');
            $partnerId = $this->providerConfig('PARTNER_ID');

            $shortcode = $this->providerConfig('SHORTCODE')
                ?: $this->providerConfig('SENDER_ID')
                ?: $this->defaultSenderId();

            if (!$apiKey) {
                $issues[] = 'Missing required Advanta config: API_KEY.';
            }

            if (!$partnerId) {
                $issues[] = 'Missing required Advanta config: PARTNER_ID.';
            }

            if (!$shortcode) {
                $issues[] = 'Missing Advanta shortcode. Set SHORTCODE, SENDER_ID or BULK_SMS_DEFAULT_SENDER_ID.';
            }
        }

        /*
         |--------------------------------------------------------------------------
         | Other providers
         |--------------------------------------------------------------------------
         |
         | For providers without custom validation rules, validate all
         | database configuration records marked as required.
         */
        else {
            $requiredConfigs = DB::table(
                'sacco_bulk_sms_provider_configs'
            )
                ->where('provider_code', $providerCode)
                ->where('config_is_required', 'Y')
                ->get();

            foreach ($requiredConfigs as $config) {
                $value = $this->providerConfig(
                    $config->config_key
                );

                if ($value === null || trim((string) $value) === '') {
                    $issues[] = sprintf(
                        'Missing required provider config: %s.',
                        $config->config_key
                    );
                }
            }
        }

        return [
            'enabled' => $this->isEnabled(),
            'demo_mode' => $this->isDemoMode(),

            'provider_code' => $providerCode,
            'provider_exists' => $provider !== null,
            'provider_enabled' => $this->providerIsEnabled(),

            'default_network' => $this->defaultNetwork(),
            'default_sender_id' => $this->defaultSenderId(),

            'ready_to_send' => empty($issues),
            'issues' => array_values(array_unique($issues)),
        ];
    }
}