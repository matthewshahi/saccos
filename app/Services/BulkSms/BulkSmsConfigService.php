<?php

namespace App\Services\BulkSms;

use Illuminate\Support\Facades\DB;

class BulkSmsConfigService
{
    /**
     * Read a value from sacco_defaults.
     */
    public function defaultValue(
        string $name,
        mixed $fallback = null
    ): mixed {
        $value = DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->value('default_value');

        return $value !== null
            ? $value
            : $fallback;
    }

    /**
     * Determine whether a configuration value is present.
     */
    protected function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    /**
     * Normalize a provider code.
     */
    protected function normalizeProviderCode(
        mixed $provider
    ): string {
        return strtolower(trim((string) $provider));
    }

    /**
     * Check whether the Bulk SMS module is enabled.
     *
     * This remains database-controlled so administrators can safely
     * enable or disable sending through the Bulk SMS settings page.
     */
    public function isEnabled(): bool
    {
        return strtoupper(
            trim(
                (string) $this->defaultValue(
                    'BULK_SMS_ENABLED',
                    'N'
                )
            )
        ) === 'Y';
    }

    /**
     * Check whether demo mode is enabled.
     *
     * Demo mode remains database-controlled.
     */
    public function isDemoMode(): bool
    {
        return strtoupper(
            trim(
                (string) $this->defaultValue(
                    'BULK_SMS_DEMO_MODE',
                    'Y'
                )
            )
        ) === 'Y';
    }

    /**
     * Determine whether processing should stop when readiness fails.
     */
    public function failClosed(): bool
    {
        return strtoupper(
            trim(
                (string) $this->defaultValue(
                    'BULK_SMS_FAIL_CLOSED',
                    'Y'
                )
            )
        ) === 'Y';
    }

    /**
     * Get the active SMS provider.
     *
     * Provider priority:
     *
     * 1. BULK_SMS_PROVIDER from config/bulk_sms.php and .env
     * 2. BULK_SMS_PROVIDER from sacco_defaults
     * 3. advanta fallback
     *
     * Since each SACCO installation has its own database and environment,
     * an explicitly configured installation provider should take priority.
     */
    public function providerCode(): string
    {
        $installationProvider = config('bulk_sms.provider');

        if ($this->hasValue($installationProvider)) {
            return $this->normalizeProviderCode(
                $installationProvider
            );
        }

        return $this->normalizeProviderCode(
            $this->defaultValue(
                'BULK_SMS_PROVIDER',
                'advanta'
            )
        );
    }

    /**
     * Get the default recipient network.
     */
    public function defaultNetwork(): string
    {
        $network = $this->defaultValue(
            'BULK_SMS_DEFAULT_NETWORK',
            'safaricom'
        );

        return strtolower(trim((string) $network));
    }

    /**
     * Get the default sender ID.
     *
     * Sender ID priority:
     *
     * 1. BULK_SMS_DEFAULT_SENDER_ID from .env
     * 2. Provider-specific SHORTCODE
     * 3. Provider-specific SENDER_ID
     * 4. BULK_SMS_DEFAULT_SENDER_ID from sacco_defaults
     */
    public function defaultSenderId(): ?string
    {
        $installationSenderId = config(
            'bulk_sms.default_sender_id'
        );

        if ($this->hasValue($installationSenderId)) {
            return trim((string) $installationSenderId);
        }

        $providerShortcode = $this->providerConfig(
            'SHORTCODE'
        );

        if ($this->hasValue($providerShortcode)) {
            return trim((string) $providerShortcode);
        }

        $providerSenderId = $this->providerConfig(
            'SENDER_ID'
        );

        if ($this->hasValue($providerSenderId)) {
            return trim((string) $providerSenderId);
        }

        $databaseSenderId = $this->defaultValue(
            'BULK_SMS_DEFAULT_SENDER_ID'
        );

        if (!$this->hasValue($databaseSenderId)) {
            return null;
        }

        return trim((string) $databaseSenderId);
    }

    /**
     * Get the active provider database record.
     */
    public function provider(): ?object
    {
        return DB::table('sacco_bulk_sms_providers')
            ->where(
                'provider_code',
                $this->providerCode()
            )
            ->first();
    }

    /**
     * Check whether the active provider exists and is enabled.
     */
    public function providerIsEnabled(): bool
    {
        $provider = $this->provider();

        if (!$provider) {
            return false;
        }

        return strtoupper(
            trim((string) $provider->provider_enabled)
        ) === 'Y';
    }

    /**
     * Resolve one configuration value for the active provider.
     *
     * Resolution priority:
     *
     * 1. config/bulk_sms.php direct provider value
     * 2. Environment key registered by the database configuration row
     * 3. Database config_value
     * 4. Supplied fallback
     */
    public function providerConfig(
        string $key,
        mixed $fallback = null
    ): mixed {
        $providerCode = $this->providerCode();
        $configKey = strtoupper(trim($key));

        /*
         * First use the installation configuration.
         *
         * Example:
         * config('bulk_sms.providers.advanta.API_KEY')
         */
        $directConfiguration = config(
            'bulk_sms.providers.'
            . $providerCode
            . '.'
            . $configKey
        );

        if ($this->hasValue($directConfiguration)) {
            return $directConfiguration;
        }

        /*
         * Then check the provider configuration row.
         */
        $configurationRow = DB::table(
            'sacco_bulk_sms_provider_configs'
        )
            ->where('provider_code', $providerCode)
            ->where('config_key', $configKey)
            ->first();

        if (!$configurationRow) {
            return $fallback;
        }

        /*
         * Resolve the environment key through config/bulk_sms.php.
         *
         * Do not call env() directly here because direct runtime env()
         * calls are unreliable after Laravel configuration is cached.
         */
        if (!empty($configurationRow->config_env_key)) {
            $environmentKey = trim(
                (string) $configurationRow->config_env_key
            );

            $environmentValue = config(
                'bulk_sms.environment.'
                . $environmentKey
            );

            if ($this->hasValue($environmentValue)) {
                return $environmentValue;
            }
        }

        /*
         * Finally use the database value.
         */
        if ($this->hasValue(
            $configurationRow->config_value
        )) {
            return $configurationRow->config_value;
        }

        return $fallback;
    }

    /**
     * Return all configurations for the active provider.
     *
     * Secret values are masked before being returned.
     */
    public function providerConfigs(): array
    {
        $providerCode = $this->providerCode();

        $configurations = DB::table(
            'sacco_bulk_sms_provider_configs'
        )
            ->where('provider_code', $providerCode)
            ->orderBy('config_key')
            ->get();

        $output = [];

        foreach ($configurations as $configuration) {
            $value = $this->providerConfig(
                (string) $configuration->config_key
            );

            $isSecret = strtoupper(
                trim(
                    (string) $configuration->config_is_secret
                )
            ) === 'Y';

            $output[$configuration->config_key] = [
                'value' => $isSecret
                    && $this->hasValue($value)
                        ? '********'
                        : $value,

                'env_key' => $configuration->config_env_key,

                'is_secret' => $configuration
                    ->config_is_secret,

                'is_required' => $configuration
                    ->config_is_required,

                'source' => $this->configurationSource(
                    $providerCode,
                    (string) $configuration->config_key,
                    $configuration
                ),
            ];
        }

        return $output;
    }

    /**
     * Describe where a provider configuration value came from.
     *
     * This is safe for diagnostics because it does not expose the value.
     */
    protected function configurationSource(
        string $providerCode,
        string $configKey,
        object $configuration
    ): string {
        $directValue = config(
            'bulk_sms.providers.'
            . $providerCode
            . '.'
            . strtoupper(trim($configKey))
        );

        if ($this->hasValue($directValue)) {
            return 'environment';
        }

        if (!empty($configuration->config_env_key)) {
            $environmentValue = config(
                'bulk_sms.environment.'
                . trim(
                    (string) $configuration->config_env_key
                )
            );

            if ($this->hasValue($environmentValue)) {
                return 'environment';
            }
        }

        if ($this->hasValue(
            $configuration->config_value
        )) {
            return 'database';
        }

        return 'missing';
    }

    /**
     * Check whether the selected provider is ready to send.
     */
    public function readiness(): array
    {
        $providerCode = $this->providerCode();
        $provider = $this->provider();

        $issues = [];

        /*
        |--------------------------------------------------------------------------
        | Global module status
        |--------------------------------------------------------------------------
        */

        if (!$this->isEnabled()) {
            $issues[] = 'Bulk SMS is disabled in sacco_defaults.';
        }

        /*
        |--------------------------------------------------------------------------
        | Provider status
        |--------------------------------------------------------------------------
        */

        if (!$provider) {
            $issues[] = sprintf(
                'Selected Bulk SMS provider "%s" does not exist.',
                $providerCode
            );
        }

        if (
            $provider
            && strtoupper(
                trim((string) $provider->provider_enabled)
            ) !== 'Y'
        ) {
            $issues[] = sprintf(
                'Selected Bulk SMS provider "%s" is disabled.',
                $providerCode
            );
        }

        /*
         * The send URL can be stored in either provider configs,
         * the environment, or the provider database record.
         */
        $configuredSendUrl = $this->providerConfig(
            'SEND_URL'
        ) ?: ($provider->provider_send_url ?? null);

        if ($provider && !$this->hasValue(
            $configuredSendUrl
        )) {
            $issues[] = 'Provider SMS send URL is not configured.';
        }

        /*
        |--------------------------------------------------------------------------
        | ADTEL readiness
        |--------------------------------------------------------------------------
        */

        if ($providerCode === 'adtel') {
            $authUrl = $this->providerConfig(
                'AUTH_URL'
            ) ?: ($provider->provider_token_url ?? null);

            $username = $this->providerConfig(
                'USERNAME'
            );

            $password = $this->providerConfig(
                'PASSWORD'
            );

            $clientId = $this->providerConfig(
                'CLIENT_ID'
            );

            $clientSecret = $this->providerConfig(
                'CLIENT_SECRET'
            );

            $basicAuthToken = $this->providerConfig(
                'BASIC_AUTH_TOKEN'
            );

            $senderId = $this->providerConfig(
                'SENDER_ID'
            ) ?: $this->defaultSenderId();

            $actionResponseUrl = $this->providerConfig(
                'ACTION_RESPONSE_URL'
            );

            if (!$this->hasValue($authUrl)) {
                $issues[] = 'Missing required ADTEL config: AUTH_URL.';
            }

            if (!$this->hasValue($username)) {
                $issues[] = 'Missing required ADTEL config: USERNAME.';
            }

            if (!$this->hasValue($password)) {
                $issues[] = 'Missing required ADTEL config: PASSWORD.';
            }

            if (
                !$this->hasValue($basicAuthToken)
                && (
                    !$this->hasValue($clientId)
                    || !$this->hasValue($clientSecret)
                )
            ) {
                $issues[] = 'Missing ADTEL Basic Auth credentials: provide either BASIC_AUTH_TOKEN or CLIENT_ID and CLIENT_SECRET.';
            }

            if (!$this->hasValue($senderId)) {
                $issues[] = 'Missing ADTEL sender ID. Set SENDER_ID or BULK_SMS_DEFAULT_SENDER_ID.';
            }

            if (!$this->hasValue(
                $actionResponseUrl
            )) {
                $issues[] = 'Missing required ADTEL config: ACTION_RESPONSE_URL.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Advanta readiness
        |--------------------------------------------------------------------------
        */

        elseif ($providerCode === 'advanta') {
            $apiKey = $this->providerConfig(
                'API_KEY'
            );

            $partnerId = $this->providerConfig(
                'PARTNER_ID'
            );

            $shortcode = $this->providerConfig(
                'SHORTCODE'
            ) ?: $this->defaultSenderId();

            if (!$this->hasValue($apiKey)) {
                $issues[] = 'Missing required Advanta config: API_KEY.';
            }

            if (!$this->hasValue($partnerId)) {
                $issues[] = 'Missing required Advanta config: PARTNER_ID.';
            }

            if (!$this->hasValue($shortcode)) {
                $issues[] = 'Missing Advanta shortcode. Set ADVANTA_SHORTCODE or BULK_SMS_DEFAULT_SENDER_ID.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Other providers
        |--------------------------------------------------------------------------
        */

        else {
            $requiredConfigurations = DB::table(
                'sacco_bulk_sms_provider_configs'
            )
                ->where(
                    'provider_code',
                    $providerCode
                )
                ->where(
                    'config_is_required',
                    'Y'
                )
                ->get();

            foreach (
                $requiredConfigurations
                as $configuration
            ) {
                $value = $this->providerConfig(
                    (string) $configuration->config_key
                );

                if (!$this->hasValue($value)) {
                    $issues[] = sprintf(
                        'Missing required provider config: %s.',
                        $configuration->config_key
                    );
                }
            }
        }

        return [
            'enabled' => $this->isEnabled(),

            'demo_mode' => $this->isDemoMode(),

            'provider_code' => $providerCode,

            'provider_exists' => $provider !== null,

            'provider_enabled' => $this
                ->providerIsEnabled(),

            'default_network' => $this
                ->defaultNetwork(),

            'default_sender_id' => $this
                ->defaultSenderId(),

            'send_url' => $configuredSendUrl,

            'ready_to_send' => empty($issues),

            'issues' => array_values(
                array_unique($issues)
            ),
        ];
    }
}