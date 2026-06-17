<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Logo
    |--------------------------------------------------------------------------
    |
    | If APP_LOGO=Y, the login page will try to display a logo from:
    |
    | public/{APP_LOGO_FOLDER}/{safe_app_name}_logo.{APP_LOGO_EXTENSION}
    |
    | Example:
    | APP_NAME="Kass Sacco"
    | APP_LOGO=Y
    |
    | public/logo/kass_sacco_logo.jpg
    |
    */

    'logo' => [
        'enabled' => strtoupper((string) env('APP_LOGO', 'N')) === 'Y',

        'folder' => trim((string) env('APP_LOGO_FOLDER', 'logo'), '/'),

        'extension' => ltrim(strtolower((string) env('APP_LOGO_EXTENSION', 'jpg')), '.'),

        'filename' => strtolower(
            trim(
                preg_replace(
                    '/_+/',
                    '_',
                    preg_replace('/[^A-Za-z0-9]+/', '_', env('APP_NAME', 'app'))
                ),
                '_'
            )
        ) . '_logo.' . ltrim(strtolower((string) env('APP_LOGO_EXTENSION', 'jpg')), '.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Migration Mode
    |--------------------------------------------------------------------------
    |
    | Custom setting used by the SACCO application to enable or disable
    | migration mode.
    |
    */

    'migration_mode' => strtoupper((string) env('MIGRATION', 'N')) === 'Y' ? 'Y' : 'N',

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions.
    |
    */

    'timezone' => 'Africa/Nairobi',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe.
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store' => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
        'Log' => Illuminate\Support\Facades\Log::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Custom Application Settings
    |--------------------------------------------------------------------------
    |
    | These custom settings allow you to define application-specific
    | configurations such as ERP and support contacts that can be globally
    | accessed within your application.
    |
    */

    'erp_contact' => env('ERP_CONTACT', '+254 722 400737'),

    'sacco_support' => env('SACCO_SUPPORT', '+254 722 400737'),

];