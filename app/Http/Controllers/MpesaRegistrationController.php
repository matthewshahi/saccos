<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaRegistrationController extends Controller
{
    /**
     * Display the form to register M-Pesa URLs.
     *
     * @return \Illuminate\View\View
     */
    public function showForm()
    {
        $config = DB::table('mpesa_configs')->first(); // Display the first config by default
        $configs = DB::table('mpesa_configs')->get(); // Fetch all configurations for listing

        return view('mpesa.register', compact('config', 'configs'));
    }

    /**
     * Handle the registration of M-Pesa URLs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function registerUrls(Request $request)
    {
        $validatedData = $request->validate([
            'shortcode' => 'required|numeric',
            'response_type' => 'required|string|in:Completed,Cancelled',
            'confirmation_url' => 'required|url',
            'validation_url' => 'required|url',
            'consumer_key' => 'required|string',
            'consumer_secret' => 'required|string',
            'passkey' => 'required|string',  // Validate passkey
            'api_type' => 'required|string|in:c2b,mpesa_express',  // API type validation
        ]);

        // Check for existing config with the same shortcode and API type
        $existingConfig = DB::table('mpesa_configs')
            ->where('shortcode', $validatedData['shortcode'])
            ->where('api_type', $validatedData['api_type'])
            ->first();

        if ($existingConfig) {
            // Update existing config
            DB::table('mpesa_configs')->where('id', $existingConfig->id)->update([
                'response_type' => $validatedData['response_type'],
                'confirmation_url' => $validatedData['confirmation_url'],
                'validation_url' => $validatedData['validation_url'],
                'consumer_key' => $validatedData['consumer_key'],
                'consumer_secret' => $validatedData['consumer_secret'],
                'passkey' => $validatedData['passkey'],  // Save passkey
                'updated_at' => now(),
            ]);
        } else {
            // Insert new config
            DB::table('mpesa_configs')->insert([
                'shortcode' => $validatedData['shortcode'],
                'response_type' => $validatedData['response_type'],
                'confirmation_url' => $validatedData['confirmation_url'],
                'validation_url' => $validatedData['validation_url'],
                'consumer_key' => $validatedData['consumer_key'],
                'consumer_secret' => $validatedData['consumer_secret'],
                'passkey' => $validatedData['passkey'],  // Save passkey
                'api_type' => $validatedData['api_type'],  // Save API type
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $config = (object) $validatedData; // Convert array to object for use in generateAccessToken
        $accessToken = $this->generateAccessToken($config);
        if (!$accessToken) {
            return back()->withErrors(['error' => 'Failed to generate access token.']);
        }

        $data = [
            "ShortCode" => $config->shortcode,
            "ResponseType" => $config->response_type,
            "ConfirmationURL" => $config->confirmation_url,
            "ValidationURL" => $config->validation_url,
        ];

        // Use the appropriate URL for live or sandbox
        $url = env('MPESA_ENV') === 'live' ? 
               'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl' : 
               'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $data);

            $result = $response->json();

            Log::info('M-Pesa URL Registration Response:', $result);

            if ($response->successful()) {
                return back()->with('success', 'URLs registered successfully: ' . $result['ResponseDescription']);
            } else {
                return back()->withErrors(['error' => 'Failed to register URLs: ' . ($result['ResponseDescription'] ?? 'Unknown error')]);
            }
        } catch (\Exception $e) {
            Log::error('Error during M-Pesa URL Registration: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate an access token for M-Pesa API.
     *
     * @param object $config
     * @return string|null
     */
    private function generateAccessToken($config)
    {
        $credentials = base64_encode($config->consumer_key . ':' . $config->consumer_secret);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
            ])->get(env('MPESA_ENV') === 'live' ? 
                    'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' : 
                    'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

            if ($response->successful()) {
                $accessToken = $response->json()['access_token'];
                Log::info('M-Pesa Access Token generated successfully.', ['access_token' => $accessToken]);
                return $accessToken;
            } else {
                Log::error('Failed to generate M-Pesa Access Token.', ['response' => $response->body()]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error during M-Pesa Access Token generation: ' . $e->getMessage());
            return null;
        }
    }
}
