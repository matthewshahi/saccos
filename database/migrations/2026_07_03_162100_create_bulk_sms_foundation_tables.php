<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Bulk SMS providers registry
        |--------------------------------------------------------------------------
        | One row per SMS provider/PRSP.
        | Example: adtel, africastalking, twilio, safaricom_direct, etc.
        |
        | No hard FK is used because this system is deployed across many SACCO DBs
        | and we do not want restore/drop issues caused by foreign key constraints.
        */

        if (!Schema::hasTable('sacco_bulk_sms_providers')) {
            Schema::create('sacco_bulk_sms_providers', function (Blueprint $table) {
                $table->increments('provider_id');

                $table->string('provider_code', 60)->unique();
                $table->string('provider_name', 150);

                // prsp, direct_mno, aggregator, internal, test
                $table->string('provider_type', 60)->default('prsp');

                // This maps to the Laravel service/driver name.
                // Example: adtel, africastalking, twilio
                $table->string('provider_driver', 120);

                $table->string('provider_base_url', 255)->nullable();
                $table->string('provider_token_url', 255)->nullable();
                $table->string('provider_send_url', 255)->nullable();
                $table->string('provider_balance_url', 255)->nullable();
                $table->string('provider_delivery_status_url', 255)->nullable();

                // For PRSPs that require network routing.
                // Default is Safaricom unless changed.
                $table->string('provider_default_network', 30)->default('safaricom');
                $table->char('provider_requires_network', 1)->default('Y');

                // Y/N provider-level switch.
                $table->char('provider_enabled', 1)->default('Y');

                $table->text('provider_notes')->nullable();

                $table->timestamp('provider_created_at')->nullable()->useCurrent();
                $table->timestamp('provider_updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->index('provider_code', 'idx_bulk_sms_provider_code');
                $table->index('provider_driver', 'idx_bulk_sms_provider_driver');
                $table->index('provider_enabled', 'idx_bulk_sms_provider_enabled');
                $table->index('provider_type', 'idx_bulk_sms_provider_type');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Provider-specific configuration
        |--------------------------------------------------------------------------
        | Do not put all provider configs in sacco_defaults.
        | sacco_defaults should only hold global module switches.
        |
        | Secrets should preferably live in .env. This table stores the env key names
        | and non-secret defaults. It can also hold non-sensitive config values.
        */

        if (!Schema::hasTable('sacco_bulk_sms_provider_configs')) {
            Schema::create('sacco_bulk_sms_provider_configs', function (Blueprint $table) {
                $table->increments('config_id');

                $table->string('provider_code', 60);
                $table->string('config_key', 100);

                // Can store non-secret config value.
                // For secrets, prefer .env and keep this NULL.
                $table->longText('config_value')->nullable();

                // Example: ADTEL_CLIENT_ID, ADTEL_CLIENT_SECRET
                $table->string('config_env_key', 100)->nullable();

                // Y/N
                $table->char('config_is_secret', 1)->default('N');

                // Y/N
                $table->char('config_is_required', 1)->default('N');

                $table->string('config_description', 255)->nullable();

                $table->timestamp('config_created_at')->nullable()->useCurrent();
                $table->timestamp('config_updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->unique(['provider_code', 'config_key'], 'uq_bulk_sms_provider_config');
                $table->index('provider_code', 'idx_bulk_sms_config_provider');
                $table->index('config_key', 'idx_bulk_sms_config_key');
                $table->index('config_env_key', 'idx_bulk_sms_config_env_key');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Provider network mappings
        |--------------------------------------------------------------------------
        | Some PRSPs may require a network value such as safaricom, airtel, telkom.
        | This table allows every provider to define its own network codes.
        */

        if (!Schema::hasTable('sacco_bulk_sms_provider_networks')) {
            Schema::create('sacco_bulk_sms_provider_networks', function (Blueprint $table) {
                $table->increments('network_id');

                $table->string('provider_code', 60);

                // Internal normalized code.
                // Example: safaricom, airtel, telkom, equitel, unknown
                $table->string('network_code', 30);

                $table->string('network_name', 100);

                // Provider-specific value to send in API payload.
                // Example: SAFARICOM, safaricom, 1, SFC, etc.
                $table->string('provider_network_value', 60)->nullable();

                $table->char('network_is_default', 1)->default('N');
                $table->char('network_enabled', 1)->default('Y');

                $table->integer('network_sort_order')->default(100);

                $table->timestamp('network_created_at')->nullable()->useCurrent();
                $table->timestamp('network_updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->unique(['provider_code', 'network_code'], 'uq_bulk_sms_provider_network');
                $table->index('provider_code', 'idx_bulk_sms_network_provider');
                $table->index('network_code', 'idx_bulk_sms_network_code');
                $table->index('network_enabled', 'idx_bulk_sms_network_enabled');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Bulk SMS message/outbox table
        |--------------------------------------------------------------------------
        | This is the SMS-specific dispatch table.
        |
        | sacco_system_notifications remains the general notification log.
        | This table tracks provider-level SMS sending, retries, responses,
        | provider IDs, delivery status, demo mode, and callback state.
        */

        if (!Schema::hasTable('sacco_bulk_sms_messages')) {
            Schema::create('sacco_bulk_sms_messages', function (Blueprint $table) {
                $table->bigIncrements('sms_id');

                // Optional link to sacco_system_notifications.notif_id
                $table->unsignedBigInteger('notif_id')->nullable();

                $table->unsignedBigInteger('member_id')->nullable();

                $table->string('provider_code', 60)->nullable();

                // demo/live
                $table->string('sms_mode', 20)->default('demo');

                // queued, skipped, demo, sending, sent, delivered, failed, rejected, expired, unknown
                $table->string('sms_status', 30)->default('queued');

                $table->string('recipient_name', 150)->nullable();

                // What was supplied by the system/user.
                $table->string('recipient_phone_raw', 50)->nullable();

                // Normalized format. Recommended: 2547XXXXXXXX
                $table->string('recipient_phone_normalized', 30)->nullable();

                // safaricom, airtel, telkom, equitel, unknown
                $table->string('recipient_network', 30)->default('safaricom');

                $table->string('sender_id', 60)->nullable();

                $table->string('sms_subject', 180)->nullable();
                $table->longText('sms_message');

                $table->integer('sms_character_count')->default(0);
                $table->integer('sms_segments')->default(1);

                // Internal reference generated before sending.
                $table->string('request_reference', 100)->nullable();

                // Provider response references.
                $table->string('provider_message_id', 150)->nullable();
                $table->string('provider_batch_id', 150)->nullable();
                $table->string('provider_reference', 150)->nullable();

                $table->integer('http_status')->nullable();

                $table->string('provider_status_code', 100)->nullable();
                $table->string('provider_status_text', 255)->nullable();

                $table->string('error_code', 100)->nullable();
                $table->text('error_message')->nullable();

                $table->integer('attempt_count')->default(0);
                $table->timestamp('next_retry_at')->nullable();

                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('last_callback_at')->nullable();

                // Store JSON as longText for compatibility across older installs.
                $table->longText('request_payload')->nullable();
                $table->longText('response_payload')->nullable();
                $table->longText('callback_payload')->nullable();
                $table->longText('sms_meta')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_ip', 45)->nullable();

                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->index('notif_id', 'idx_bulk_sms_notif_id');
                $table->index('member_id', 'idx_bulk_sms_member_id');
                $table->index('provider_code', 'idx_bulk_sms_provider_code');
                $table->index('sms_mode', 'idx_bulk_sms_mode');
                $table->index('sms_status', 'idx_bulk_sms_status');
                $table->index('recipient_phone_normalized', 'idx_bulk_sms_phone');
                $table->index('recipient_network', 'idx_bulk_sms_network');
                $table->index('request_reference', 'idx_bulk_sms_request_ref');
                $table->index('provider_message_id', 'idx_bulk_sms_provider_message');
                $table->index('provider_batch_id', 'idx_bulk_sms_provider_batch');
                $table->index('queued_at', 'idx_bulk_sms_queued_at');
                $table->index('sent_at', 'idx_bulk_sms_sent_at');
                $table->index('delivered_at', 'idx_bulk_sms_delivered_at');
                $table->index('created_at', 'idx_bulk_sms_created_at');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Callback/raw webhook table
        |--------------------------------------------------------------------------
        | Delivery reports can arrive before/after the message is matched.
        | This table stores raw callbacks safely.
        */

        if (!Schema::hasTable('sacco_bulk_sms_callbacks')) {
            Schema::create('sacco_bulk_sms_callbacks', function (Blueprint $table) {
                $table->bigIncrements('callback_id');

                $table->string('provider_code', 60)->nullable();

                $table->unsignedBigInteger('sms_id')->nullable();

                $table->string('provider_message_id', 150)->nullable();
                $table->string('provider_batch_id', 150)->nullable();
                $table->string('provider_reference', 150)->nullable();

                // delivery_report, balance_update, inbound_sms, unknown
                $table->string('callback_type', 50)->default('delivery_report');

                // received, matched, processed, ignored, failed
                $table->string('callback_status', 30)->default('received');

                $table->string('callback_ip', 45)->nullable();
                $table->string('callback_user_agent', 255)->nullable();

                $table->longText('callback_headers')->nullable();
                $table->longText('callback_query')->nullable();
                $table->longText('callback_body')->nullable();
                $table->longText('callback_parsed_payload')->nullable();

                $table->string('processing_error', 255)->nullable();

                $table->timestamp('received_at')->nullable()->useCurrent();
                $table->timestamp('processed_at')->nullable();

                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->index('provider_code', 'idx_bulk_sms_callback_provider');
                $table->index('sms_id', 'idx_bulk_sms_callback_sms_id');
                $table->index('provider_message_id', 'idx_bulk_sms_callback_provider_msg');
                $table->index('provider_batch_id', 'idx_bulk_sms_callback_provider_batch');
                $table->index('callback_type', 'idx_bulk_sms_callback_type');
                $table->index('callback_status', 'idx_bulk_sms_callback_status');
                $table->index('received_at', 'idx_bulk_sms_callback_received_at');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Global defaults in sacco_defaults
        |--------------------------------------------------------------------------
        | These are deliberately global only.
        | Provider-specific settings go to sacco_bulk_sms_provider_configs.
        */

        $defaults = [
            'BULK_SMS_ENABLED' => 'N',
            'BULK_SMS_PROVIDER' => 'adtel',
            'BULK_SMS_DEMO_MODE' => 'Y',
            'BULK_SMS_DEFAULT_NETWORK' => 'safaricom',
            'BULK_SMS_DEFAULT_SENDER_ID' => null,
            'BULK_SMS_FAIL_CLOSED' => 'Y',
            'BULK_SMS_LOG_TO_SYSTEM_NOTIFICATIONS' => 'Y',
            'BULK_SMS_MAX_RETRY_ATTEMPTS' => '3',
            'BULK_SMS_RETRY_DELAY_SECONDS' => '60',
            'BULK_SMS_PHONE_FORMAT' => '254',
        ];

        foreach ($defaults as $name => $value) {
            $exists = DB::table('sacco_defaults')
                ->where('default_name', $name)
                ->exists();

            if (!$exists) {
                DB::table('sacco_defaults')->insert([
                    'default_name' => $name,
                    'default_value' => $value,
                    'default_transdate' => now(),
                    'default_userid' => null,
                    'default_ip' => '127.0.0.1',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Seed ADTEL provider
        |--------------------------------------------------------------------------
        | SMS send URL is intentionally NULL until ADTEL provides the full API docs.
        */

        $adtelExists = DB::table('sacco_bulk_sms_providers')
            ->where('provider_code', 'adtel')
            ->exists();

        if (!$adtelExists) {
            DB::table('sacco_bulk_sms_providers')->insert([
                'provider_code' => 'adtel',
                'provider_name' => 'ADTEL Bulk SMS',
                'provider_type' => 'prsp',
                'provider_driver' => 'adtel',
                'provider_base_url' => 'https://api.adtel.co.ke',
                'provider_token_url' => 'https://api.adtel.co.ke/oath/token',
                'provider_send_url' => null,
                'provider_balance_url' => null,
                'provider_delivery_status_url' => null,
                'provider_default_network' => 'safaricom',
                'provider_requires_network' => 'Y',
                'provider_enabled' => 'Y',
                'provider_notes' => 'Authentication endpoint available. SMS send endpoint, balance endpoint and delivery-report format still pending from provider.',
                'provider_created_at' => now(),
                'provider_updated_at' => now(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Seed ADTEL config placeholders
        |--------------------------------------------------------------------------
        | Secrets should be placed in .env, not directly in the database.
        */

        $adtelConfigs = [
            [
                'config_key' => 'AUTH_URL',
                'config_value' => 'https://api.adtel.co.ke/oath/token',
                'config_env_key' => 'ADTEL_AUTH_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'ADTEL token endpoint.',
            ],
            [
                'config_key' => 'CLIENT_ID',
                'config_value' => null,
                'config_env_key' => 'ADTEL_CLIENT_ID',
                'config_is_secret' => 'Y',
                'config_is_required' => 'Y',
                'config_description' => 'Generated ADTEL API client ID. Prefer .env.',
            ],
            [
                'config_key' => 'CLIENT_SECRET',
                'config_value' => null,
                'config_env_key' => 'ADTEL_CLIENT_SECRET',
                'config_is_secret' => 'Y',
                'config_is_required' => 'Y',
                'config_description' => 'Generated ADTEL API client secret. Prefer .env.',
            ],
            [
                'config_key' => 'USERNAME',
                'config_value' => null,
                'config_env_key' => 'ADTEL_USERNAME',
                'config_is_secret' => 'Y',
                'config_is_required' => 'Y',
                'config_description' => 'Bulk5 username/email. Prefer .env.',
            ],
            [
                'config_key' => 'PASSWORD',
                'config_value' => null,
                'config_env_key' => 'ADTEL_PASSWORD',
                'config_is_secret' => 'Y',
                'config_is_required' => 'Y',
                'config_description' => 'Bulk5 password. Prefer .env.',
            ],
            [
                'config_key' => 'GRANT_TYPE',
                'config_value' => 'password',
                'config_env_key' => null,
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'OAuth password grant type.',
            ],
            [
                'config_key' => 'AUTH_MODE',
                'config_value' => 'basic_auth_password_grant',
                'config_env_key' => null,
                'config_is_secret' => 'N',
                'config_is_required' => 'Y',
                'config_description' => 'Client ID and secret are used as authorization username/password.',
            ],
            [
                'config_key' => 'SEND_URL',
                'config_value' => null,
                'config_env_key' => 'ADTEL_SEND_URL',
                'config_is_secret' => 'N',
                'config_is_required' => 'N',
                'config_description' => 'Pending from ADTEL full SMS API documentation.',
            ],
            [
                'config_key' => 'SENDER_ID',
                'config_value' => null,
                'config_env_key' => 'ADTEL_SENDER_ID',
                'config_is_secret' => 'N',
                'config_is_required' => 'N',
                'config_description' => 'Approved sender ID, if required by ADTEL.',
            ],
            [
                'config_key' => 'TIMEOUT_SECONDS',
                'config_value' => '30',
                'config_env_key' => null,
                'config_is_secret' => 'N',
                'config_is_required' => 'N',
                'config_description' => 'HTTP request timeout in seconds.',
            ],
        ];

        foreach ($adtelConfigs as $config) {
            $exists = DB::table('sacco_bulk_sms_provider_configs')
                ->where('provider_code', 'adtel')
                ->where('config_key', $config['config_key'])
                ->exists();

            if (!$exists) {
                DB::table('sacco_bulk_sms_provider_configs')->insert([
                    'provider_code' => 'adtel',
                    'config_key' => $config['config_key'],
                    'config_value' => $config['config_value'],
                    'config_env_key' => $config['config_env_key'],
                    'config_is_secret' => $config['config_is_secret'],
                    'config_is_required' => $config['config_is_required'],
                    'config_description' => $config['config_description'],
                    'config_created_at' => now(),
                    'config_updated_at' => now(),
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Seed ADTEL network mappings
        |--------------------------------------------------------------------------
        | Default network is Safaricom.
        | Provider-specific values can be edited later if ADTEL requires different codes.
        */

        $adtelNetworks = [
            [
                'network_code' => 'safaricom',
                'network_name' => 'Safaricom',
                'provider_network_value' => 'safaricom',
                'network_is_default' => 'Y',
                'network_enabled' => 'Y',
                'network_sort_order' => 10,
            ],
            [
                'network_code' => 'airtel',
                'network_name' => 'Airtel',
                'provider_network_value' => 'airtel',
                'network_is_default' => 'N',
                'network_enabled' => 'Y',
                'network_sort_order' => 20,
            ],
            [
                'network_code' => 'telkom',
                'network_name' => 'Telkom',
                'provider_network_value' => 'telkom',
                'network_is_default' => 'N',
                'network_enabled' => 'Y',
                'network_sort_order' => 30,
            ],
            [
                'network_code' => 'equitel',
                'network_name' => 'Equitel',
                'provider_network_value' => 'equitel',
                'network_is_default' => 'N',
                'network_enabled' => 'Y',
                'network_sort_order' => 40,
            ],
            [
                'network_code' => 'unknown',
                'network_name' => 'Unknown',
                'provider_network_value' => null,
                'network_is_default' => 'N',
                'network_enabled' => 'Y',
                'network_sort_order' => 99,
            ],
        ];

        foreach ($adtelNetworks as $network) {
            $exists = DB::table('sacco_bulk_sms_provider_networks')
                ->where('provider_code', 'adtel')
                ->where('network_code', $network['network_code'])
                ->exists();

            if (!$exists) {
                DB::table('sacco_bulk_sms_provider_networks')->insert([
                    'provider_code' => 'adtel',
                    'network_code' => $network['network_code'],
                    'network_name' => $network['network_name'],
                    'provider_network_value' => $network['provider_network_value'],
                    'network_is_default' => $network['network_is_default'],
                    'network_enabled' => $network['network_enabled'],
                    'network_sort_order' => $network['network_sort_order'],
                    'network_created_at' => now(),
                    'network_updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Rollback
        |--------------------------------------------------------------------------
        | Drop SMS-specific tables and remove only the defaults introduced here.
        */

        Schema::dropIfExists('sacco_bulk_sms_callbacks');
        Schema::dropIfExists('sacco_bulk_sms_messages');
        Schema::dropIfExists('sacco_bulk_sms_provider_networks');
        Schema::dropIfExists('sacco_bulk_sms_provider_configs');
        Schema::dropIfExists('sacco_bulk_sms_providers');

        DB::table('sacco_defaults')
            ->whereIn('default_name', [
                'BULK_SMS_ENABLED',
                'BULK_SMS_PROVIDER',
                'BULK_SMS_DEMO_MODE',
                'BULK_SMS_DEFAULT_NETWORK',
                'BULK_SMS_DEFAULT_SENDER_ID',
                'BULK_SMS_FAIL_CLOSED',
                'BULK_SMS_LOG_TO_SYSTEM_NOTIFICATIONS',
                'BULK_SMS_MAX_RETRY_ATTEMPTS',
                'BULK_SMS_RETRY_DELAY_SECONDS',
                'BULK_SMS_PHONE_FORMAT',
            ])
            ->delete();
    }
};