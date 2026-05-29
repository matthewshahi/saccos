<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Redirect;
use Illuminate\Support\Facades\URL;



class MpesaTheController extends Controller
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $shortCode;
    protected $passkey;
    protected $accessToken;
    protected $tokenExpiresAt;
    protected $callbackUrl;

    public function __construct()
    {
        $this->resetCredentials();
    }

    /**
     * Reset credentials by fetching them from the database.
     */
    private function resetCredentials()
    {
        // Fetch the first configuration with `mpesa_express` API type
        $stkConfig = DB::table('mpesa_configs')->where('api_type', 'mpesa_express')->first();

        if ($stkConfig) {
            // Initialize credentials from the database
            $this->consumerKey = $stkConfig->consumer_key;
            $this->consumerSecret = $stkConfig->consumer_secret;
            $this->shortCode = $stkConfig->shortcode;
            $this->passkey = $stkConfig->passkey;
            $this->accessToken = $stkConfig->access_token;
            $this->tokenExpiresAt = $stkConfig->token_expires_at;
            $this->callbackUrl = $stkConfig->confirmation_url;

            Log::info('M-Pesa credentials initialized successfully.');
        } else {
            // Default to null if no configuration is found
            $this->consumerKey = null;
            $this->consumerSecret = null;
            $this->shortCode = null;
            $this->passkey = null;
            $this->accessToken = null;
            $this->tokenExpiresAt = null;
            $this->callbackUrl = null;

            Log::error('No M-Pesa configuration found for initialization.');
        }
    }

    public function showSTKPushForm(Request $request, $unique_number = null)
    {
        // Use the unique_number if provided, otherwise generate a new one
        $unicode = $unique_number ?? uniqid('DOC_'); // Fallback to a new unique ID if not provided

        // Example dynamic amount
        $amount = 0; // Replace with dynamic value if needed

        // Log the generated values
        Log::info('STK Push form loaded.', [
            'unicode' => $unicode,
            'amount' => $amount,
            'shortcode' => $this->shortCode,
        ]);

        // Return the form view with dynamic values
        return view('mpesa.stkpush', [
            'unicode' => $unicode,
            'amount' => $amount,
            'shortcode' => $this->shortCode,
        ]);
    }

    public function storeStkPush(Request $request)
    {
        $request->validate([
            'phone'  => 'required|string',
            'uniq'   => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $unique_number = $request->input('uniq');
        $phoneNumber   = $this->formatPhoneNumber($request->input('phone'));
        $amount        = (float) $request->input('amount');
        $shortcode     = $this->shortCode;

        if (!$this->consumerKey || !$this->consumerSecret || !$this->shortCode || !$this->passkey) {
            Log::error('M-Pesa STK credentials missing.', [
                'has_consumer_key'    => !empty($this->consumerKey),
                'has_consumer_secret' => !empty($this->consumerSecret),
                'has_shortcode'       => !empty($this->shortCode),
                'has_passkey'         => !empty($this->passkey),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'M-Pesa configuration is incomplete.',
            ], 500);
        }

        $url = $this->mpesaBaseUrl() . '/mpesa/stkpush/v1/processrequest';

        [$password, $timestamp] = $this->generateMpesaPassword();
        $accessToken = $this->getAccessToken();

        $callbackUrl = rtrim($this->callbackUrl, '/') . '/' . $unique_number;

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phoneNumber,
            'PartyB'            => $shortcode,
            'PhoneNumber'       => $phoneNumber,
            'CallBackURL'       => $callbackUrl,
            'AccountReference'  => $unique_number,
            'TransactionDesc'   => 'Online Transaction',
        ];

        Log::info('STK PUSH PAYLOAD SENT TO SAFARICOM', [
            'url'               => $url,
            'BusinessShortCode' => $payload['BusinessShortCode'],
            'Amount'            => $payload['Amount'],
            'PartyA'            => $payload['PartyA'],
            'PartyB'            => $payload['PartyB'],
            'PhoneNumber'       => $payload['PhoneNumber'],
            'CallBackURL'       => $payload['CallBackURL'],
            'AccountReference'  => $payload['AccountReference'],
            'TransactionDesc'   => $payload['TransactionDesc'],
            'mpesa_env'         => $this->mpesaEnv(),
            'raw_callback_base' => $this->callbackUrl,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->post($url, $payload);

        Log::info('STK PUSH SAFARICOM RAW RESPONSE', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'json'   => $response->json(),
        ]);

        if ($response->failed()) {
            Log::error('FAILED TO INITIATE STK PUSH', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'url'    => $url,
                'env'    => $this->mpesaEnv(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to initiate payment. Please try again.',
                'mpesa'   => $response->json(),
            ], 500);
        }

        $responseBody = $response->json();

        if (isset($responseBody['ResponseCode']) && $responseBody['ResponseCode'] === '0') {
            DB::table('stk_push_logs')->insert([
                'unique_number'           => $unique_number,
                'checkout_request_id'     => $responseBody['CheckoutRequestID'] ?? null,
                'phone_number'            => $phoneNumber,
                'amount'                  => $amount,
                'account_reference'       => $unique_number,
                'transaction_description' => 'Online Transaction',
                'shortcode'               => $shortcode,
                'status'                  => 'pending',
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);

            Log::info('STK PUSH REQUEST ACCEPTED BY SAFARICOM', [
                'unique_number'     => $unique_number,
                'checkoutRequestId' => $responseBody['CheckoutRequestID'] ?? null,
                'merchantRequestId' => $responseBody['MerchantRequestID'] ?? null,
                'customerMessage'   => $responseBody['CustomerMessage'] ?? null,
                'callback_url_used' => $callbackUrl,
            ]);

            return response()->json([
                'status'            => 'success',
                'checkoutRequestId' => $responseBody['CheckoutRequestID'] ?? null,
                'merchantRequestId' => $responseBody['MerchantRequestID'] ?? null,
            ], 200);
        }

        Log::error('STK PUSH REQUEST REJECTED BY SAFARICOM', [
            'response'          => $responseBody,
            'callback_url_used' => $callbackUrl,
            'url'               => $url,
            'env'               => $this->mpesaEnv(),
        ]);

        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to initiate payment. Please try again.',
            'mpesa'   => $responseBody,
        ], 500);
    }


    function checkPayment(Request $request)
    {

        $order = $request->id;
        $paid = DB::table('stk_push_logs')->whereNotNull("result_code")->where("result_code", 0)->where('account_reference', $order)->first();
        if ($paid) {
            return "good";
        } else {

            $order = (int)substr($order, 3);
            $paid = DB::table('paybill_orders')->where("is_paid", "PAID")->where("id", $order)->first();
            if ($paid) {
                return "good";
            }
            return "bad";
        }
    }


    private function loadConfig($shortcode)
    {
        $config = DB::table('mpesa_configs')->where('shortcode', $shortcode)->where('api_type', 'mpesa_express')->first();

        if (!$config) {
            $this->logTransaction('M-Pesa credentials not found in the database for shortcode ' . $shortcode . '.', [], 'error');
            throw new Exception('M-Pesa configuration not found for the selected shortcode.');
        }

        $this->consumerKey = $config->consumer_key;
        $this->consumerSecret = $config->consumer_secret;
        $this->shortCode = $config->shortcode;
        $this->passkey = $config->passkey;
        $this->accessToken = $config->access_token;
        $this->tokenExpiresAt = $config->token_expires_at;
        $this->callbackUrl = $config->validation_url;

       $this->logTransaction('M-Pesa credentials loaded for shortcode ' . $shortcode . ' from database.', [
    'has_consumer_key'    => !empty($this->consumerKey),
    'has_consumer_secret' => !empty($this->consumerSecret),
    'shortcode'           => $this->shortCode,
    'has_passkey'         => !empty($this->passkey),
    'has_access_token'    => !empty($this->accessToken),
    'token_expires_at'    => $this->tokenExpiresAt,
    'callbackUrl'         => $this->callbackUrl,
]);
    }

    /**
     * Format phone number to the correct format: 2547XXXXXXXX
     */
    private function formatPhoneNumber($phoneNumber)
    {
        $phoneNumber = trim(str_replace(' ', '', $phoneNumber));  // Remove spaces and trim
        if (substr($phoneNumber, 0, 1) === '+') {
            $phoneNumber = substr($phoneNumber, 1);  // Remove '+'
        }
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '254' . substr($phoneNumber, 1);  // Replace '0' with '254'
        } elseif (substr($phoneNumber, 0, 3) !== '254') {
            throw new Exception('Invalid phone number format.');
        }

        return $phoneNumber;
    }

    /**
     * Generate M-Pesa Password for STK Push.
     */
    private function generateMpesaPassword()
    {
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortCode . $this->passkey . $timestamp);
        $this->logTransaction('Generated M-Pesa password.', []);
        return [$password, $timestamp];
    }

    /**
     * Get or generate a new access token.
     */
    public function getAccessToken()
    {
        // ✅ Reuse token if still valid (refresh 5 mins before expiry)
        if (
            $this->accessToken && $this->tokenExpiresAt &&
            Carbon::now()->lt(Carbon::parse($this->tokenExpiresAt)->subMinutes(5))
        ) {
            return $this->accessToken;
        }

        // Otherwise fetch a new token
        $url = $this->mpesaBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($url);

        if ($response->failed()) {
            Log::error('Failed to generate access token: ' . $response->body());
            throw new Exception('Failed to generate access token.');
        }

        $accessToken = $response['access_token'];
        $expiresIn   = $response['expires_in'];

        // ✅ Update DB for all related rows (C2B + STK etc.)
        DB::table('mpesa_configs')
            ->where('shortcode', $this->shortCode)
            ->update([
                'access_token'     => $accessToken,
                'token_expires_at' => Carbon::now()->addSeconds($expiresIn),
            ]);

        // ✅ Update current instance
        $this->accessToken    = $accessToken;
        $this->tokenExpiresAt = Carbon::now()->addSeconds($expiresIn);

        Log::info('New access token generated and saved.');

        return $accessToken;
    }

    public function handleSTKPushCallback(Request $request, $unique_number = null)
    {
        Log::info('STK Push callback received.', [
            'unique_number' => $unique_number,
            'request_data' => $request->all(),
        ]);

        try {
            // Decode the callback data
            $callbackJSONData = $request->getContent();
            $callbackData = json_decode($callbackJSONData);

            if (!isset($callbackData->Body->stkCallback)) {
                Log::error('Invalid STK Push callback data.', ['callbackData' => $callbackData]);
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback data.']);
            }

            // Extract data
            $stkCallback = $callbackData->Body->stkCallback;
            $merchantRequestId = $stkCallback->MerchantRequestID ?? null;
            $checkoutRequestId = $stkCallback->CheckoutRequestID ?? null;
            $resultCode = $stkCallback->ResultCode ?? null;
            $resultDesc = $stkCallback->ResultDesc ?? null;

            // Default values
            $mpesaReceiptNumber = null;
            $transactionDate = null;
            $phoneNumber = null;
            $amount = null;

            if (isset($stkCallback->CallbackMetadata->Item)) {
                foreach ($stkCallback->CallbackMetadata->Item as $item) {
                    switch ($item->Name) {
                        case 'MpesaReceiptNumber':
                            $mpesaReceiptNumber = $item->Value;
                            break;
                        case 'TransactionDate':
                            $transactionDate = $item->Value;
                            break;
                        case 'PhoneNumber':
                            $phoneNumber = $item->Value;
                            break;
                        case 'Amount':
                            $amount = $item->Value;
                            break;
                    }
                }
            }

            // Format transaction date
            $formattedTransactionDate = $transactionDate
                ? \Carbon\Carbon::createFromFormat('YmdHis', $transactionDate)->toDateTimeString()
                : null;

            // Save to database
            DB::transaction(function () use (
                $unique_number,
                $merchantRequestId,
                $checkoutRequestId,
                $resultCode,
                $resultDesc,
                $mpesaReceiptNumber,
                $formattedTransactionDate,
                $phoneNumber,
                $amount
            ) {
                DB::table('stk_push_responses')->updateOrInsert(
                    ['checkout_request_id' => $checkoutRequestId],
                    [
                        'unique_number' => $unique_number,
                        'merchant_request_id' => $merchantRequestId,
                        'result_code' => $resultCode,
                        'result_description' => $resultDesc,
                        'mpesa_receipt_number' => $mpesaReceiptNumber,
                        'transaction_date' => $formattedTransactionDate,
                        'phone_number' => $phoneNumber,
                        'amount' => $amount,
                        'updated_at' => now(),
                    ]
                );
            });


            // If STK payment was successful, also create/update c2b_payments
            if ((int) $resultCode === 0 && !empty($mpesaReceiptNumber)) {

                // Convert Safaricom date e.g. 20260504141308 to MySQL datetime
                $transactionTime = null;

                if (!empty($transactionDate)) {
                    try {
                        $transactionTime = \Carbon\Carbon::createFromFormat('YmdHis', (string) $transactionDate)
                            ->format('Y-m-d H:i:s');
                    } catch (\Throwable $e) {
                        \Log::warning('STK callback transaction date parse failed', [
                            'transaction_date' => $transactionDate,
                            'error' => $e->getMessage(),
                        ]);

                        $transactionTime = now()->format('Y-m-d H:i:s');
                    }
                } else {
                    $transactionTime = now()->format('Y-m-d H:i:s');
                }

                DB::table('c2b_payments')->updateOrInsert(
                    [
                        'transaction_id' => $mpesaReceiptNumber,
                    ],
                    [
                        'transaction_type'           => 'Pay Bill',
                        'transaction_time'           => $transactionTime,
                        'transaction_amount'         => $amount ?? 0,
                        'business_shortcode'         => $this->shortCode ?? null,
                        'bill_ref_number'            => $unique_number,
                        'invoice_number'             => null,
                        'org_account_balance'        => null,
                        'third_party_transaction_id' => $checkoutRequestId ?? null,
                        'msisdn'                     => $phoneNumber ?? null,
                        'first_name'                 => null,
                        'middle_name'                => null,
                        'last_name'                  => null,
                        'raw_payload'                => json_encode($request->all()),
                        'ip_address'                 => $request->ip(),

                        // Important: make it available for ProcessTransactionsJob
                        'processed'                  => 'No',
                        'picked'                     => 'No',
                        'failure_reason'             => null,
                        'processed_date'             => null,

                        'created_at'                 => now(),
                        'updated_at'                 => now(),
                    ]
                );

                Log::info('STK callback inserted/updated c2b_payments', [
                    'unique_number' => $unique_number,
                    'transaction_id' => $mpesaReceiptNumber,
                    'amount' => $amount,
                    'phone' => $phoneNumber,
                    'checkout_request_id' => $checkoutRequestId ?? null,
                ]);
            }

            Log::info('STK Push callback processed successfully.');

            // Redirect based on ResultCode
            if ($resultCode === 0) {
                // Payment successful
                return redirect()->route('payment.success', ['unique_number' => $unique_number]);
            } else {
                // Payment failed
                return redirect()->route('payment.failed', ['unique_number' => $unique_number]);
            }
        } catch (\Exception $e) {
            Log::error('Error processing STK Push callback.', ['exception' => $e->getMessage()]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Error processing callback.']);
        }
    }

    // public function handleSTKPushCallback(Request $request, $unique_number = null)
    // {
    //     Log::info('STK Push callback received.', [
    //         'unique_number' => $unique_number,
    //         'request_data' => $request->all(),
    //     ]);

    //     // Process the callback data
    //     try {
    //         $callbackJSONData = $request->getContent();
    //         $callbackData = json_decode($callbackJSONData);

    //         if (!isset($callbackData->Body->stkCallback)) {
    //             Log::error('Invalid STK Push callback data.', ['callbackData' => $callbackData]);
    //             return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback data.']);
    //         }

    //         $stkCallback = $callbackData->Body->stkCallback;
    //         $merchantRequestId = $stkCallback->MerchantRequestID ?? null;
    //         $checkoutRequestId = $stkCallback->CheckoutRequestID ?? null;
    //         $resultCode = $stkCallback->ResultCode ?? null;
    //         $resultDesc = $stkCallback->ResultDesc ?? null;

    //         $amount = null;
    //         $mpesaReceiptNumber = null;
    //         $transactionDate = null;
    //         $phoneNumber = null;

    //         // Extract CallbackMetadata items
    //         if (isset($stkCallback->CallbackMetadata->Item)) {
    //             foreach ($stkCallback->CallbackMetadata->Item as $item) {
    //                 switch ($item->Name) {
    //                     case 'Amount':
    //                         $amount = $item->Value ?? null;
    //                         break;
    //                     case 'MpesaReceiptNumber':
    //                         $mpesaReceiptNumber = $item->Value ?? null;
    //                         break;
    //                     case 'TransactionDate':
    //                         $transactionDate = $item->Value ?? null;
    //                         break;
    //                     case 'PhoneNumber':
    //                         $phoneNumber = $item->Value ?? null;
    //                         break;
    //                 }
    //             }
    //         }

    //         // Convert transaction date to a proper format
    //         $formattedTransactionDate = $transactionDate ? \Carbon\Carbon::createFromFormat('YmdHis', $transactionDate)->toDateTimeString() : null;

    //         // Save the callback data in the database
    //         DB::transaction(function () use (
    //             $unique_number,
    //             $checkoutRequestId,
    //             $merchantRequestId,
    //             $resultCode,
    //             $resultDesc,
    //             $amount,
    //             $mpesaReceiptNumber,
    //             $formattedTransactionDate,
    //             $phoneNumber
    //         ) {
    //             DB::table('stk_push_responses')->updateOrInsert(
    //                 ['checkout_request_id' => $checkoutRequestId],  
    //                 [
    //                     'unique_number' => $unique_number,
    //                     'merchant_request_id' => $merchantRequestId,
    //                     'result_code' => $resultCode,
    //                     'result_description' => $resultDesc,
    //                     'mpesa_receipt_number' => $mpesaReceiptNumber,
    //                     'transaction_date' => $formattedTransactionDate,
    //                     'phone_number' => $phoneNumber,
    //                     'amount' => $amount,
    //                     'updated_at' => now(),
    //                 ]
    //             );
    //         });

    //         Log::info('STK Push callback processed successfully.');
    //         return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully.']);


    //     } catch (\Exception $e) {
    //         Log::error('Error processing STK Push callback.', ['exception' => $e->getMessage()]);
    //         return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Error processing callback.']);
    //     }
    // }

    // public function handleSTKPushCallback(Request $request, $unique_number = null)
    //     {
    //         Log::info('STK Push callback received.', [
    //             'unique_number' => $unique_number,
    //             'request_data' => $request->all(),
    //         ]);

    //         // Process the callback data
    //         try {
    //             $callbackJSONData = $request->getContent();
    //             $callbackData = json_decode($callbackJSONData);

    //             if (!isset($callbackData->Body->stkCallback)) {
    //                 Log::error('Invalid STK Push callback data.', ['callbackData' => $callbackData]);
    //                 return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback data.']);
    //             }

    //             DB::transaction(function () use ($callbackData, $unique_number) {
    //                 $this->saveSTKCallbackData($callbackData, $unique_number);
    //             });

    //             Log::info('STK Push callback processed successfully.');
    //             return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully.']);

    //         } catch (Exception $e) {
    //             Log::error('Error processing STK Push callback.', ['exception' => $e->getMessage()]);
    //             return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Error processing callback.']);
    //         }
    //     }


    private function saveSTKCallbackData($callbackData, $unique_number = null)
    {
        $merchantRequestID = $callbackData->Body->stkCallback->MerchantRequestID ?? null;
        $checkoutRequestID = $callbackData->Body->stkCallback->CheckoutRequestID ?? null;
        $resultCode = $callbackData->Body->stkCallback->ResultCode ?? null;
        $resultDesc = $callbackData->Body->stkCallback->ResultDesc ?? null;

        $amount = $callbackData->Body->stkCallback->CallbackMetadata->Item[0]->Value ?? null;
        $mpesaReceiptNumber = $callbackData->Body->stkCallback->CallbackMetadata->Item[1]->Value ?? null;
        $transactionDate = $callbackData->Body->stkCallback->CallbackMetadata->Item[2]->Value ?? null;
        $phoneNumber = $callbackData->Body->stkCallback->CallbackMetadata->Item[3]->Value ?? null;

        DB::table('stk_push_logs')
            ->where('checkout_request_id', $checkoutRequestID)
            ->update([
                'merchant_request_id' => $merchantRequestID,
                'result_code' => $resultCode,
                'result_description' => $resultDesc,
                'amount' => $amount,
                'transaction_id' => $mpesaReceiptNumber,
                'transaction_time' => $transactionDate ? Carbon::createFromFormat('YmdHis', $transactionDate) : null,
                'phone_number' => $phoneNumber,
                'updated_at' => now(),
            ]);
    }

    public function registerUrls()
    {
        try {
            // Retrieve the shortcode configuration from the database
            $config = DB::table('mpesa_configs')
                ->where('api_type', 'c2b')
                ->first();

            if (!$config) {
                throw new Exception('M-Pesa configuration for C2B not found.');
            }

            // Load dynamic configuration
            $this->consumerKey = $config->consumer_key;
            $this->consumerSecret = $config->consumer_secret;
            $this->shortCode = $config->shortcode;
            $this->callbackUrl = [
                'ConfirmationURL' => $config->confirmation_url,
                'ValidationURL' => $config->validation_url,
            ];

            $accessToken = $this->getAccessToken();

            // Dynamic API URL based on environment
            $queryUrl = $this->mpesaEnv() === 'live'
                ? $this->mpesaBaseUrl() . '/mpesa/c2b/v2/queryurl'
                : $this->mpesaBaseUrl() . '/mpesa/c2b/v1/queryurl';

            // First, fetch the current registered URLs
            $currentUrls = $this->getRegisteredUrls($accessToken);

            // Check if URLs are already registered and match
            if (
                $currentUrls['ConfirmationURL'] === $config->confirmation_url &&
                $currentUrls['ValidationURL'] === $config->validation_url
            ) {
                Log::info('URLs are already registered and up to date.');
                return response()->json([
                    'message' => 'URLs are already registered and up to date.',
                ]);
            }

            // Register new URLs
            $ch = curl_init($validationUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "ShortCode" => $this->shortCode,
                "ResponseType" => $config->response_type,
                "ConfirmationURL" => $this->callbackUrl['ConfirmationURL'],
                "ValidationURL" => $this->callbackUrl['ValidationURL'],
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);

            $responseBody = json_decode($response, true);

            // Log and return response
            Log::info('URL Registration Response:', [
                'response' => $responseBody,
                'config' => $config,
            ]);

            if (isset($responseBody['errorCode']) && $responseBody['errorCode'] === '500.003.1001') {
                throw new Exception('URLs are already registered.');
            }

            return response()->json([
                'message' => 'URLs registered successfully.',
                'response' => $responseBody,
            ]);
        } catch (Exception $e) {
            Log::error('Error registering URLs:', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch the currently registered URLs for the shortcode.
     *
     * @param string $accessToken
     * @return array
     */
    private function getRegisteredUrls($accessToken)
    {
        // Dynamic API URL based on environment
        $queryUrl = $this->mpesaEnv() === 'live'
            ? $this->mpesaBaseUrl() . '/mpesa/c2b/v2/queryurl'
            : $this->mpesaBaseUrl() . '/mpesa/c2b/v1/queryurl';

        $ch = curl_init($queryUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, 0); // Query existing URLs (GET request)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        $responseBody = json_decode($response, true);

        // Log the fetched URLs for debugging
        Log::info('Fetched registered URLs:', ['response' => $responseBody]);

        return [
            'ConfirmationURL' => $responseBody['ConfirmationURL'] ?? '',
            'ValidationURL' => $responseBody['ValidationURL'] ?? '',
        ];
    }




    public function validationRequest()
    {
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }


    public function handleC2BPayment(Request $request)
    {
        Log::info('C2B Payment received.', [
            'all' => $request->all(),          // Parsed form-data
            'raw' => $request->getContent(),   // Raw JSON or form string
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);

        try {
            // Decode JSON, fallback to form-data if needed
            $paymentData = json_decode($request->getContent(), true);
            if (empty($paymentData)) {
                $paymentData = $request->all();
            }

            // Validate required fields (at minimum we need TransID + Amount)
            if (empty($paymentData['TransID']) || empty($paymentData['TransAmount'])) {
                Log::warning('Invalid C2B payload, missing TransID or TransAmount', ['payload' => $paymentData]);
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
            }

            // Format transaction time safely
            $transactionTime = null;
            if (!empty($paymentData['TransTime'])) {
                try {
                    $transactionTime = Carbon::createFromFormat('YmdHis', $paymentData['TransTime'])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    Log::warning('Invalid TransTime format', ['TransTime' => $paymentData['TransTime']]);
                }
            }

            // Prevent duplicates
            $exists = DB::table('c2b_payments')
                ->where('transaction_id', $paymentData['TransID'])
                ->exists();

            if ($exists) {
                Log::info('Duplicate C2B transaction ignored.', ['TransID' => $paymentData['TransID']]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Duplicate transaction']);
            }

            // Save transaction
            DB::table('c2b_payments')->insert([
                'transaction_type' => $paymentData['TransactionType'] ?? null,
                'transaction_id' => $paymentData['TransID'],
                'transaction_time' => $transactionTime,
                'transaction_amount' => $paymentData['TransAmount'] ?? 0.00,
                'business_shortcode' => $paymentData['BusinessShortCode'] ?? null,
                'bill_ref_number' => $paymentData['BillRefNumber'] ?? null,
                'invoice_number' => $paymentData['InvoiceNumber'] ?? null,
                'org_account_balance' => $paymentData['OrgAccountBalance'] ?? null,
                'third_party_transaction_id' => $paymentData['ThirdPartyTransID'] ?? null,
                'msisdn' => $paymentData['MSISDN'] ?? null,
                'first_name' => $paymentData['FirstName'] ?? null,
                'middle_name' => $paymentData['MiddleName'] ?? null,
                'last_name' => $paymentData['LastName'] ?? null,
                'raw_payload' => $request->getContent(),
                'ip_address' => $request->ip(),
                'processed' => 'No',
                'processed_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('C2B Payment saved successfully.', ['TransID' => $paymentData['TransID']]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        } catch (\Exception $e) {
            Log::error('Failed to save C2B payment.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to process payment']);
        }
    }

    //  public function handleC2BPayment(Request $request)
    // {
    //     // Log the receipt of the C2B payment
    //     $this->logTransaction('Received C2B Payment.', []);
    //     $this->logTransaction('Raw C2B Payment data:', ['raw_data' => $request->getContent()]);

    //     try {
    //         // Extract required fields from the request
    //         $paymentData = json_decode($request->getContent(), true);

    //         // Format the transaction time
    //         $transactionTime = Carbon::createFromFormat('YmdHis', $paymentData['TransTime'])->format('Y-m-d H:i:s');

    //         // Ensure no duplicate entries
    //         $existingTransaction = DB::table('c2b_payments')->where('transaction_id', $paymentData['TransID'])->first();
    //         if ($existingTransaction) {
    //             $this->logTransaction('Duplicate transaction detected.', ['transaction_id' => $paymentData['TransID']]);
    //             return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Duplicate transaction.']);
    //         }

    //         // Insert the payment record
    //         DB::table('c2b_payments')->insert([
    //             'transaction_type' => $paymentData['TransactionType'] ?? null,
    //             'transaction_id' => $paymentData['TransID'] ?? null,
    //             'transaction_time' => $transactionTime,
    //             'transaction_amount' => $paymentData['TransAmount'] ?? 0.00,
    //             'business_shortcode' => $paymentData['BusinessShortCode'] ?? null,
    //             'bill_ref_number' => $paymentData['BillRefNumber'] ?? null,
    //             'invoice_number' => $paymentData['InvoiceNumber'] ?? null,
    //             'org_account_balance' => $paymentData['OrgAccountBalance'] ?? null,
    //             'third_party_transaction_id' => $paymentData['ThirdPartyTransID'] ?? null,
    //             'msisdn' => $paymentData['MSISDN'] ?? null,
    //             'first_name' => $paymentData['FirstName'] ?? null,
    //             'middle_name' => $paymentData['MiddleName'] ?? null,
    //             'last_name' => $paymentData['LastName'] ?? null,
    //             'ip_address' => $request->ip(),
    //             'processed' => 'No',
    //             'processed_date' => null,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);

    //         // Log the successful insertion
    //         $this->logTransaction('C2B Payment inserted successfully.', ['transaction_id' => $paymentData['TransID']]);

    //         return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    //     } catch (Exception $e) {
    //         $this->logTransaction('Error handling C2B payment: ' . $e->getMessage(), [], 'error');
    //         return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to process payment.']);
    //     }
    // }

    private function logSTKPushRequest(
        $check_out_request_id,
        $unique_number,
        $phoneNumber,
        $amount,
        $accountReference,
        $transactionDesc,
        $package_id = null
    ) {
        // Get the logged-in user ID
        $user_id = auth()->id();

        try {
            // Use database transactions for atomicity
            DB::transaction(function () use (
                $check_out_request_id,
                $unique_number,
                $phoneNumber,
                $amount,
                $accountReference,
                $transactionDesc,
                $user_id,
                $package_id
            ) {
                // Insert STK push details into the logs table
                DB::table('stk_push_logs')->insert([
                    'unique_number' => $unique_number,
                    'checkout_request_id' => $check_out_request_id,
                    'phone_number' => $phoneNumber,
                    'amount' => $amount,
                    'account_reference' => $accountReference,
                    'transaction_description' => $transactionDesc,
                    'user_id' => $user_id,
                    'package_id' => $package_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            // Log success message
            $this->logTransaction(
                'STK Push request successfully logged for phone: ' . $phoneNumber,
                ['unique_number' => $unique_number, 'amount' => $amount, 'user_id' => $user_id]
            );
        } catch (Exception $e) {
            // Log failure message
            $this->logTransaction(
                'Failed to log STK Push request: ' . $e->getMessage(),
                ['unique_number' => $unique_number, 'amount' => $amount, 'user_id' => $user_id],
                'error'
            );
        }
    }
    private function logTransaction($message, $context = [], $level = 'info')
    {
        Log::{$level}($message, $context);
    }


    public function paymentSuccess(Request $request)
    {
        // Retrieve the unique number from the request
        $uniqueNumber = $request->input('unique_number');

        // Fetch payment details from the database
        $payment = DB::table('stk_push_responses')
            ->where('unique_number', $uniqueNumber)
            // ->where('created_at', '<', now()->subMinute()) // Only consider records inserted more than one minute ago
            ->orderBy('created_at', 'desc')
            ->first();

        // Check if payment exists
        if (!$payment) {
            return view('mpesa.payment-failed', [
                'message' => 'Payment details could not be found. Please try again or contact support.',
                'transaction_id' => 'N/A', // Provide a default value for transaction_id
            ]);
        }

        // Pass the payment details to the view
        return view('mpesa.payment-success', [
            'message' => 'Your payment was successful. Thank you!',
            'transaction_id' => $payment->mpesa_receipt_number ?? 'N/A',
            'amount' => $payment->amount ?? 0.00,
            'phone_number' => $payment->phone_number ?? 'N/A',
        ]);
    }

    public function checkStatus(Request $request)
    {
        $checkoutRequestId = $request->input('checkoutRequestId');

        // Find the payment record
        $payment = DB::table('stk_push_responses')->where('checkout_request_id', $checkoutRequestId)->first();

        if ($payment) {
            if ($payment->result_code == 0) {
                // Payment successful
                return response()->json(['status' => 'success', 'unique_number' => $payment->unique_number]);
            } elseif ($payment->result_code != 0) {
                // Payment failed
                return response()->json(['status' => 'failed', 'unique_number' => $payment->unique_number]);
            }
        }

        // Default response (e.g., still pending or not found)
        return response()->json(['status' => 'pending']);
    }

    public function paymentFailed($unique_number = null)
    {
        if ($unique_number) {
            // Retrieve the payment details from the database
            $payment = DB::table('stk_push_responses')->where('unique_number', $unique_number)->first();

            if ($payment) {
                return view('mpesa.payment-failed', [
                    'message' => $payment->result_description ?? 'Payment failed.',
                    'transaction_id' => $payment->mpesa_receipt_number ?? 'N/A',
                    'amount' => $payment->amount ?? 0.00,
                    'phone_number' => $payment->phone_number ?? 'N/A',
                ]);
            }
        }

        // If unique_number is null or no record is found, show a generic message
        return view('mpesa.payment-failed', [
            'message' => 'Payment failed. No additional details are available.',
            'transaction_id' => 'N/A',
            'amount' => 0.00,
            'phone_number' => 'N/A',
        ]);
    }
    public function waitForPayment($checkoutRequestId)
    {
        return view('mpesa.waiting', ['checkoutRequestId' => $checkoutRequestId]);
    }

    private function mpesaEnv(): string
    {
        return strtolower(trim((string) config('services.mpesa.env', 'sandbox')));
    }

    private function mpesaBaseUrl(): string
    {
        return $this->mpesaEnv() === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }
}
