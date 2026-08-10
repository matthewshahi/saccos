<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MpesaConfigController extends Controller
{
    /**
     * Mask a credential before sending it to the browser.
     *
     * Example:
     * ABCDEFGHIJKLMNOPXYZ
     *
     * Becomes:
     * ABC*****XYZ
     *
     * The full value therefore never appears in the edit form HTML.
     */
    private function maskSecret(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        /*
         * If the value is too short to safely expose
         * first 3 + last 3, mask the whole thing.
         */
        if (strlen($value) <= 6) {
            return '*****';
        }

        return substr($value, 0, 3)
            . '*****'
            . substr($value, -3);
    }

    /**
     * Decide whether a submitted credential should replace
     * the existing database value.
     *
     * RULE:
     *
     * Contains "*" = masked value from the form
     *                -> KEEP EXISTING DATABASE VALUE
     *
     * No "*"         = user entered a new complete value
     *                -> UPDATE DATABASE VALUE
     */
    private function preserveMaskedSecret(
        ?string $submitted,
        ?string $existing
    ): ?string {
        if ($submitted !== null && str_contains($submitted, '*')) {
            return $existing;
        }

        return $submitted;
    }

    /**
     * List all M-Pesa configurations.
     */
    public function index()
    {
        $configs = DB::table('mpesa_configs')->get();

        /*
         * Mask sensitive credentials before sending
         * configurations to the browser.
         *
         * This protects them even if the index Blade
         * happens to display these fields.
         */
        $configs->transform(function ($config) {
            $config->consumer_key = $this->maskSecret(
                $config->consumer_key ?? null
            );

            $config->consumer_secret = $this->maskSecret(
                $config->consumer_secret ?? null
            );

            $config->passkey = $this->maskSecret(
                $config->passkey ?? null
            );

            return $config;
        });

        return view('mpesa.configs.index', compact('configs'));
    }

    /**
     * Show form for creating a new M-Pesa configuration.
     */
    public function create()
    {
        return view('mpesa.configs.create');
    }

    /**
     * Store a new M-Pesa configuration.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shortcode'        => 'required|string|max:255',
            'api_type'         => 'required|string',
            'response_type'    => 'required|string',
            'confirmation_url' => 'required|url',
            'validation_url'   => 'required|url',
            'consumer_key'     => 'required|string',
            'consumer_secret'  => 'required|string',
            'passkey'          => 'required|string',
        ]);

        DB::table('mpesa_configs')->insert([
            'shortcode'        => $validated['shortcode'],
            'api_type'         => $validated['api_type'],
            'response_type'    => $validated['response_type'],
            'confirmation_url' => $validated['confirmation_url'],
            'validation_url'   => $validated['validation_url'],
            'consumer_key'     => $validated['consumer_key'],
            'consumer_secret'  => $validated['consumer_secret'],
            'passkey'          => $validated['passkey'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return redirect()
            ->route('mpesa_config.index')
            ->with(
                'success',
                'Configuration added successfully.'
            );
    }

    /**
     * Show form for editing an existing M-Pesa configuration.
     */
    public function edit($id)
    {
        $config = DB::table('mpesa_configs')->find($id);

        if (!$config) {
            return redirect()
                ->route('mpesa_config.index')
                ->with(
                    'error',
                    'Configuration not found.'
                );
        }

        /*
         * IMPORTANT:
         *
         * Mask credentials BEFORE passing the configuration
         * object to Blade.
         *
         * Browser receives:
         *
         * ABC*****XYZ
         *
         * Browser never receives:
         *
         * ABC123456789XYZ
         */
        $config->consumer_key = $this->maskSecret(
            $config->consumer_key ?? null
        );

        $config->consumer_secret = $this->maskSecret(
            $config->consumer_secret ?? null
        );

        $config->passkey = $this->maskSecret(
            $config->passkey ?? null
        );

        return view(
            'mpesa.configs.edit',
            compact('config')
        );
    }

    /**
     * Update an existing M-Pesa configuration.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'shortcode'        => 'required|string|max:255',
            'api_type'         => 'required|string',
            'response_type'    => 'required|string',
            'confirmation_url' => 'required|url',
            'validation_url'   => 'required|url',
            'consumer_key'     => 'required|string',
            'consumer_secret'  => 'required|string',
            'passkey'          => 'required|string',
        ]);

        /*
         * Get the existing REAL configuration directly
         * from the database.
         */
        $existing = DB::table('mpesa_configs')->find($id);

        if (!$existing) {
            return redirect()
                ->route('mpesa_config.index')
                ->with(
                    'error',
                    'Configuration not found.'
                );
        }

        /*
         |--------------------------------------------------------------------------
         | Credential Update Logic
         |--------------------------------------------------------------------------
         |
         | Example submitted values:
         |
         | consumer_key:
         | ABC*****XYZ
         |
         | Contains "*"
         | -> Existing value is retained.
         |
         |
         | consumer_secret:
         | NEWREALCONSUMERSECRET123
         |
         | Contains no "*"
         | -> New value is stored.
         |
         */

        $consumerKey = $this->preserveMaskedSecret(
            $validated['consumer_key'],
            $existing->consumer_key
        );

        $consumerSecret = $this->preserveMaskedSecret(
            $validated['consumer_secret'],
            $existing->consumer_secret
        );

        $passkey = $this->preserveMaskedSecret(
            $validated['passkey'],
            $existing->passkey
        );

        /*
         * Update configuration.
         */
        DB::table('mpesa_configs')
            ->where('id', $id)
            ->update([
                'shortcode'        => $validated['shortcode'],
                'api_type'         => $validated['api_type'],
                'response_type'    => $validated['response_type'],
                'confirmation_url' => $validated['confirmation_url'],
                'validation_url'   => $validated['validation_url'],

                /*
                 * Sensitive fields.
                 *
                 * Masked value submitted:
                 * Existing DB value retained.
                 *
                 * New unmasked value submitted:
                 * New DB value stored.
                 */
                'consumer_key'     => $consumerKey,
                'consumer_secret'  => $consumerSecret,
                'passkey'          => $passkey,

                'updated_at'       => now(),
            ]);

        return redirect()
            ->route('mpesa_config.index')
            ->with(
                'success',
                'Configuration updated successfully.'
            );
    }
}