<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class MpesaController extends Controller
{
    /**
     * Load M-Pesa Configuration for a given shortcode.
     */
    private function loadConfig($shortcode)
    {
        $config = DB::table('mpesa_configs')->where('shortcode', $shortcode)->first();

        if (!$config) {
            throw new Exception('M-Pesa configuration not found for the specified shortcode.');
        }

        return $config;
    }

    /**
     * Format phone number to the correct format: 2547XXXXXXXX
     */
    private function formatPhoneNumber($phoneNumber)
    {
        $phoneNumber = trim(str_replace(' ', '', $phoneNumber));

        if (substr($phoneNumber, 0, 1) === '+') {
            $phoneNumber = substr($phoneNumber, 1);
        }

        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '254' . substr($phoneNumber, 1);
        } elseif (substr($phoneNumber, 0, 3) !== '254') {
            throw new Exception('Invalid phone number format.');
        }

        return $phoneNumber;
    }

    /**
     * Generate M-Pesa password for STK Push.
     */
    private function generateMpesaPassword($shortcode, $passkey)
    {
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);

        return [$password, $timestamp];
    }

    /**
     * Get or regenerate access token.
     */
    private function getAccessToken($consumerKey, $consumerSecret)
    {
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withBasicAuth($consumerKey, $consumerSecret)->get($url);

        if ($response->failed()) {
            throw new Exception('Failed to generate access token.');
        }

        return $response->json();
    }

    /**
     * Initiate STK Push Request.
     */
    public function initiateSTKPush(Request $request)
{
    $request->validate([
        'phone_number' => 'required|string',
        'amount' => 'required|numeric|min:1',
    ]);

    try {
        // Fetch configuration for mpesa_express
        $config = DB::table('mpesa_configs')
            ->where('api_type', 'mpesa_express')
            ->first();

        if (!$config) {
            throw new Exception('M-Pesa configuration for "mpesa_express" not found.');
        }

        // Format phone number
        $phoneNumber = $this->formatPhoneNumber($request->input('phone_number'));
        $amount = $request->input('amount');
        $accountReference = 'AccountRef' . time();
        $transactionDesc = 'Payment for service';

        // Generate password and timestamp
        [$password, $timestamp] = $this->generateMpesaPassword($config->shortcode, $config->passkey);

        // Get Access Token
        $accessTokenResponse = $this->getAccessToken($config->consumer_key, $config->consumer_secret);
        $accessToken = $accessTokenResponse['access_token'];

        // Determine URL based on environment
        $baseUrl = env('MPESA_ENV') === 'live' 
            ? 'https://api.safaricom.co.ke' 
            : 'https://sandbox.safaricom.co.ke';
        $url = $baseUrl . '/mpesa/stkpush/v1/processrequest';

        // Initiate STK Push
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'BusinessShortCode' => $config->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $config->shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $config->confirmation_url,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc,
        ]);

        if ($response->failed()) {
            throw new Exception('STK Push failed: ' . $response->body());
        }

        return response()->json([
            'message' => 'STK Push initiated successfully',
            'response' => $response->json(),
        ]);

    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}
public function showSTKPushForm()
{
    return view('mpesa.stkpush'); // Blade template for the form
}
}