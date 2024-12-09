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
 
    public function showSTKPushForm()
    {
        // Generate a unique document code
        $unicode = uniqid('DOC_'); // Generates something like "DOC_64fc94b8d19f1"

        // Example dynamic amount
        $amount = 5; // Replace with dynamic value if needed

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
            'shortcode'=>$this->shortCode,
        ]);
    }
    public function storeStkPush(Request $request)
{
    $request->validate([
        'phone' => 'required|string',
        'uniq' => 'required|string',
        'amount' => 'required|numeric|min:1',
    ]);

    $unique_number = $request->input('uniq');
    $phoneNumber = $this->formatPhoneNumber($request->input('phone'));
    $amount = $request->input('amount');
    $shortcode = $this->shortCode;

    // Define the environment-specific URL
    $url = env('MPESA_ENV') === 'live'
        ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    [$password, $timestamp] = $this->generateMpesaPassword();
    $accessToken = $this->getAccessToken();

    $payload = [
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phoneNumber,
        'PartyB' => $shortcode,
        'PhoneNumber' => $phoneNumber,
        'CallBackURL' => $this->callbackUrl . "/" . $unique_number,
        'AccountReference' => $unique_number,
        'TransactionDesc' => "Online Transaction",
    ];

    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json',
    ])->post($url, $payload);

    if ($response->failed()) {
        Log::error('Failed to initiate STK Push', ['response' => $response->body()]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to initiate payment. Please try again.',
        ], 500);
    }

    $responseBody = $response->json();

    if (isset($responseBody['ResponseCode']) && $responseBody['ResponseCode'] === "0") {
        DB::table('stk_push_logs')->insert([
            'unique_number' => $unique_number,
            'checkout_request_id' => $responseBody['CheckoutRequestID'],
            'phone_number' => $phoneNumber,
            'amount' => $amount,
            'account_reference' => $unique_number,
            'transaction_description' => "Online Transaction",
            'shortcode' => $shortcode,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'checkoutRequestId' => $responseBody['CheckoutRequestID'],
        ]);
    } else {
        Log::error('STK Push request failed', ['response' => $responseBody]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to initiate payment. Please try again.',
        ]);
    }
}

//     public function storeStkPush(Request $request)
// {
//     // Validate posted fields
//     $request->validate([
//         'phone' => 'required|string',
//         'uniq' => 'required|string',
//         'amount' => 'required|numeric|min:1',
//     ]); 

//     $unique_number = $request->input('uniq');
//     $phoneNumber = $this->formatPhoneNumber($request->input('phone'));
//     $amount = $request->input('amount');
//     $shortcode = $this->shortCode;

//     // Define the environment-specific URL
//     $url = env('MPESA_ENV') === 'live'
//         ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
//         : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

//     // Generate password and access token
//     [$password, $timestamp] = $this->generateMpesaPassword();
//     $accessToken = $this->getAccessToken();

//     // Prepare the payload for the STK Push request
//     $payload = [
//         'BusinessShortCode' => $shortcode,
//         'Password' => $password,
//         'Timestamp' => $timestamp,
//         'TransactionType' => 'CustomerPayBillOnline',
//         'Amount' => $amount,
//         'PartyA' => $phoneNumber,
//         'PartyB' => $shortcode,
//         'PhoneNumber' => $phoneNumber,
//         'CallBackURL' => $this->callbackUrl . "/" . $unique_number,
//         'AccountReference' => $unique_number,
//         'TransactionDesc' => "Online Transaction",
//     ];

//     // Make the STK Push request
//     $response = Http::withHeaders([
//         'Authorization' => 'Bearer ' . $accessToken,
//         'Content-Type' => 'application/json',
//     ])->post($url, $payload);

//     if ($response->failed()) {
//         // Log failure and return to payment failed page
//         Log::error('Failed to initiate STK Push', ['response' => $response->body()]);
//         return view('mpesa.payment-failed', [
//             'message' => 'Unfortunately, your payment could not be completed. Please try again.',
//         ]);
//     }

//     $responseBody = $response->json();

//     if (isset($responseBody['ResponseCode']) && $responseBody['ResponseCode'] === "0") {
//         // Save the STK Push details
//         DB::table('stk_push_logs')->insert([
//             'unique_number' => $unique_number,
//             'checkout_request_id' => $responseBody['CheckoutRequestID'],
//             'phone_number' => $phoneNumber,
//             'amount' => $amount,
//             'account_reference' => $unique_number,
//             'transaction_description' => "Online Transaction",
//             'shortcode' => $shortcode,
//             'status' => 'pending',
//             'created_at' => now(),
//             'updated_at' => now(),
//         ]);

//         // Redirect the user to the waiting page
//         return redirect()->back()->with('checkoutRequestId', $responseBody['CheckoutRequestID']);
//         // return redirect()->route('stkpush.wait', ['checkoutRequestId' => $responseBody['CheckoutRequestID']]);
//     } else {
//         Log::error('STK Push request failed', ['response' => $responseBody]);
//         return view('mpesa.payment-failed', [
//             'message' => 'Failed to initiate payment. Please try again.',
//         ]);
//     }
// }

//     public function storeStkPush(Request $request)
// {
//     // Validate posted fields
//     $request->validate([
//         'phone' => 'required|string',
//         'uniq' => 'required|string',
//         'amount' => 'required|numeric|min:1', // Validate that the amount is a valid number greater than 0
//     ]); 

//     $unique_number = $request->input('uniq');
//     $phoneNumber = $this->formatPhoneNumber($request->input('phone'));
//     $amount = $request->input('amount'); // Amount comes from posted input

//     // Set the shortcode dynamically from the constructor
//     $shortcode = $this->shortCode;

//     // Define the environment-specific URL
//     $url = env('MPESA_ENV') === 'live'
//         ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
//         : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

//     // Other transaction details
//     $accountReference = $unique_number;
//     $transactionDesc = "Online Transaction";

//     // Generate password and access token
//     [$password, $timestamp] = $this->generateMpesaPassword();
//     $accessToken = $this->getAccessToken();

//     // Prepare the payload for the STK Push request
//     $payload = [
//         'BusinessShortCode' => $shortcode,
//         'Password' => $password,
//         'Timestamp' => $timestamp,
//         'TransactionType' => 'CustomerPayBillOnline',
//         'Amount' => $amount,
//         'PartyA' => $phoneNumber,
//         'PartyB' => $shortcode,
//         'PhoneNumber' => $phoneNumber,
//         'CallBackURL' => $this->callbackUrl . "/" . $unique_number,
//         'AccountReference' => $accountReference,
//         'TransactionDesc' => $transactionDesc,
//     ];

//     // Log the full payload for debugging
//     Log::info('STK Push payload:', $payload);

//     // Make the STK Push request
//     $response = Http::withHeaders([
//         'Authorization' => 'Bearer ' . $accessToken,
//         'Content-Type' => 'application/json',
//     ])->post($url, $payload);

//     // Handle response
//     if ($response->failed()) {
//         // Log failure and return an error response
//         Log::error('Failed to initiate STK Push', [
//             'response' => $response->body(),
//         ]);
//         //return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'], 500);
//         return view('mpesa.payment-failed', [
//             'message' => 'Unfortunately, your payment could not be completed. Please try again.',
//         ]);
//     }

//     $responseBody = $response->json();

//     // Check response code
//     if (isset($responseBody['ResponseCode']) && $responseBody['ResponseCode'] === "0") {
//         // Log the successful request
//         Log::info('STK Push request successful', [
//             'CheckoutRequestID' => $responseBody['CheckoutRequestID'],
//             'unique_number' => $unique_number,
//             'phone' => $phoneNumber,
//             'amount' => $amount,
//         ]);

//         // Save the outgoing STK Push details in the database
//         DB::table('stk_push_logs')->insert([
//             'unique_number' => $unique_number,
//             'checkout_request_id' => $responseBody['CheckoutRequestID'],
//             'phone_number' => $phoneNumber,
//             'amount' => $amount,
//             'account_reference' => $accountReference,
//             'transaction_description' => $transactionDesc,
//             'shortcode' => $shortcode,
//             'status' => 'pending',
//             'created_at' => now(),
//             'updated_at' => now(),
//         ]);

//         // Return the successful response
//        // return response()->json($responseBody);
//         =========what do i do here.....

//     } else {
//         // Log failure details and return an error response
//         Log::error('STK Push request failed', [
//             'response' => $responseBody,
//         ]);
//         //return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'], 500);
//     }
// }

	// public function storeStkPush(Request $request)
    //     {
    //         // Validate posted fields
    //         $request->validate([
    //             'phone' => 'required|string',
    //             'uniq' => 'required|string',
    //             'amount' => 'required|numeric|min:1', // Validate that the amount is a valid number greater than 0
    //         ]);

    //         $unique_number = $request->input('uniq');
    //         $phoneNumber = $this->formatPhoneNumber($request->input('phone'));
    //         $amount = $request->input('amount'); // Amount comes from posted input

    //         // Set the shortcode dynamically from the constructor
    //         $shortcode = $this->shortCode;

    //         // Define the environment-specific URL
    //         $url = env('MPESA_ENV') === 'live'
    //             ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
    //             : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    //         // Other transaction details
    //         $accountReference = $unique_number;
    //         $transactionDesc = "Online Transaction";

    //         // Generate password and access token
    //         [$password, $timestamp] = $this->generateMpesaPassword();
    //         $accessToken = $this->getAccessToken();

    //         // Make the STK Push request
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $accessToken,
    //             'Content-Type' => 'application/json',
    //         ])->post($url, [
    //             'BusinessShortCode' => $shortcode,
    //             'Password' => $password,
    //             'Timestamp' => $timestamp,
    //             'TransactionType' => 'CustomerPayBillOnline',
    //             'Amount' => $amount,
    //             'PartyA' => $phoneNumber,
    //             'PartyB' => $shortcode,
    //             'PhoneNumber' => $phoneNumber,
    //             'CallBackURL' => $this->callbackUrl . "/" . $unique_number,
    //             'AccountReference' => $accountReference,
    //             'TransactionDesc' => $transactionDesc,
    //         ]);

    //         // Handle response
    //         if ($response->failed()) {
    //             // Log failure and return an error response
    //             Log::error('Failed to initiate STK Push', [
    //                 'response' => $response->body(),
    //             ]);
    //             return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'], 500);
    //         }

    //         $responseBody = $response->json();

    //         // Check response code
    //         if (isset($responseBody['ResponseCode']) && $responseBody['ResponseCode'] === "0") {
    //             // Log the successful request
    //             Log::info('STK Push request successful', [
    //                 'CheckoutRequestID' => $responseBody['CheckoutRequestID'],
    //                 'unique_number' => $unique_number,
    //                 'phone' => $phoneNumber,
    //                 'amount' => $amount,
    //             ]);

    //             // Save the outgoing STK Push details in the database
    //             DB::table('stk_push_logs')->insert([
    //                 'unique_number' => $unique_number,
    //                 'checkout_request_id' => $responseBody['CheckoutRequestID'],
    //                 'phone_number' => $phoneNumber,
    //                 'amount' => $amount,
    //                 'account_reference' => $accountReference,
    //                 'transaction_description' => $transactionDesc,
    //                 'shortcode' => $shortcode,
    //                 'status' => 'pending',
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);

    //             // Return the successful response
    //             return response()->json($responseBody);
    //         } else {
    //             // Log failure details and return an error response
    //             Log::error('STK Push request failed', [
    //                 'response' => $responseBody,
    //             ]);
    //             return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'], 500);
    //         }
    //     }



function checkPayment(Request $request){

    $order=$request->id;
    $paid = DB::table('stk_push_logs')->whereNotNull("result_code")->where("result_code",0)->where('account_reference', $order)->first();
    if($paid){
        return "good";
    }
    else{

        $order=(int)substr($order,3);
        $paid = DB::table('paybill_orders')->where("is_paid","PAID")->where("id",$order)->first();
        if($paid){
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
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
            'shortcode' => $this->shortCode,
            'passkey' => $this->passkey,
            'access_token' => $this->accessToken,
            'token_expires_at' => $this->tokenExpiresAt,
            'callbackUrl' => $this->callbackUrl,
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
    
     private function getAccessToken()
{
    // Check if token is already stored and valid
    // if (Carbon::now()->lt(Carbon::parse($this->tokenExpiresAt))) {
    //     return $this->accessToken; // Return valid token
    // }

    // Fetch a new token
    $url = env('MPESA_ENV') === 'live' 
        ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' 
        : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($url);

    if ($response->failed()) {
        Log::error('Failed to generate access token: ' . $response->body());
        throw new Exception('Failed to generate access token.');
    }

    $accessToken = $response->json()['access_token'];
    $expiresIn = $response->json()['expires_in'];

    // Save new token to the database
    DB::table('mpesa_configs')->where('shortcode', $this->shortCode)->where('api_type', 'c2b')->update([
        'access_token' => $accessToken,
        'token_expires_at' => Carbon::now()->addSeconds($expiresIn),
    ]);

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
        $validationUrl = env('MPESA_ENV') === 'live'
            ? 'https://api.safaricom.co.ke/mpesa/c2b/v2/registerurl'
            : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';

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
    $queryUrl = env('MPESA_ENV') === 'live'
        ? 'https://api.safaricom.co.ke/mpesa/c2b/v2/queryurl'
        : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/queryurl';

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
    // public function registerUrls()
    // {
    //     try {
    //         // Retrieve the shortcode configuration from the database
    //         $config = DB::table('mpesa_configs')
    //             ->where('api_type', 'c2b')
    //             ->first();
    
    //             //dd($config);
    //         if (!$config) {
    //             throw new Exception('M-Pesa configuration for C2B not found.');
    //         }
    
    //         // Load dynamic configuration
    //         $this->consumerKey = $config->consumer_key;
    //         $this->consumerSecret = $config->consumer_secret;
    //         $this->shortCode = $config->shortcode;
    //         $this->callbackUrl = [
    //             'ConfirmationURL' => $config->confirmation_url,
    //             'ValidationURL' => $config->validation_url,
    //         ];
            
           
    //         $accessToken = $this->getAccessToken();
    
    //         // Dynamic API URL based on environment
    //         $validationUrl = env('MPESA_ENV') === 'live'
    //             ? 'https://api.safaricom.co.ke/mpesa/c2b/v2/registerurl'
    //             : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';
                   
    
    //         // CURL request to register URLs
    //         $ch = curl_init($validationUrl);
    //         curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //             'Authorization: Bearer ' . $accessToken,
    //             'Content-Type: application/json',
    //         ]);
    //         curl_setopt($ch, CURLOPT_POST, 1);
    //         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    //             "ShortCode" => $this->shortCode,
    //             "ResponseType" => $config->response_type, // Dynamically fetched ResponseType
    //             "ConfirmationURL" => $this->callbackUrl['ConfirmationURL'],
    //             "ValidationURL" => $this->callbackUrl['ValidationURL'],
    //         ]));
    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    //         $response = curl_exec($ch);
    //         if (curl_errno($ch)) {
    //             throw new Exception('Curl error: ' . curl_error($ch));
    //         }
    
    //         curl_close($ch);
    
    //         // Log response for debugging
    //         Log::info('URL Registration Response:', [
    //             'response' => $response,
    //             'config' => $config,
    //         ]);
    
    //         return response()->json([
    //             'message' => 'URLs registered successfully.',
    //             'response' => json_decode($response, true),
    //         ]);
    //     } catch (Exception $e) {
    //         Log::error('Error registering URLs:', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    

    
 

 public function validationRequest(){
    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    
 }

 public function handleC2BPayment(Request $request)
 {
     // Log the receipt of the C2B payment
     $this->logTransaction('Received C2B Payment.', []);
     $this->logTransaction('Raw C2B Payment data:', ['raw_data' => $request->getContent()]);
 
     // Extract required fields from the request
     $paymentData = $request->only([
         'TransactionType', 'TransID', 'TransTime', 'TransAmount', 
         'BusinessShortCode', 'BillRefNumber', 'InvoiceNumber', 
         'OrgAccountBalance', 'ThirdPartyTransID', 'MSISDN', 
         'FirstName', 'MiddleName', 'LastName'
     ]);
 
     // Format the transaction time
     $paymentData['transaction_time'] = Carbon::createFromFormat('YmdHis', $paymentData['TransTime']);
 
     // Extract the order number from the BillRefNumber
     $orderNumber = (int)substr($paymentData['BillRefNumber'], 3);
 
     // Payment amount
     $amount = (double)$paymentData['TransAmount'];
 
     // Fetch the corresponding order from the database
     $order = DB::table('paybill_orders')->where("id", $orderNumber)->first();
 
     if ($order) {
         $order_amount = (double)$order->amount;
         $balance = $order_amount - $amount;
 
         // Update the order as fully paid if the balance is zero or less
         if ($balance <= 0) {
             DB::table('paybill_orders')->where("id", $orderNumber)->update([
                 'is_paid' => 'PAID',
                 'amount_paid' => $amount,
                 'balance' => $balance,
                 'updated_at' => Carbon::now(),
                 'TransID' => $paymentData['TransID']
             ]);
         } else {
             // Otherwise, just update the amount paid and balance
             DB::table('paybill_orders')->where("id", $orderNumber)->update([
                 'amount_paid' => $amount,
                 'balance' => $balance,
                 'updated_at' => Carbon::now()
             ]);
         }
 
         // Save the payment details in the c2b_payments table
         try {
             DB::transaction(function () use ($paymentData) {
                 DB::table('c2b_payments')->insert([
                     'TransactionType' => $paymentData['TransactionType'],
                     'TransID' => $paymentData['TransID'],
                     'TransTime' => $paymentData['transaction_time'],
                     'TransAmount' => $paymentData['TransAmount'],
                     'BusinessShortCode' => $paymentData['BusinessShortCode'],
                     'BillRefNumber' => $paymentData['BillRefNumber'],
                     'InvoiceNumber' => $paymentData['InvoiceNumber'],
                     'OrgAccountBalance' => $paymentData['OrgAccountBalance'],
                     'ThirdPartyTransID' => $paymentData['ThirdPartyTransID'],
                     'MSISDN' => $paymentData['MSISDN'],
                     'FirstName' => $paymentData['FirstName'],
                     'MiddleName' => $paymentData['MiddleName'],
                     'LastName' => $paymentData['LastName'],
                     'created_at' => now(),
                     'updated_at' => now(),
                 ]);
             });
 
             // Log the successful processing of the C2B payment
             $this->logTransaction('C2B Payment processed successfully.', []);
         } catch (Exception $e) {
             // Log any error that occurs during saving the payment data
             $this->logTransaction('Failed to save C2B Payment data: ' . $e->getMessage(), [], 'error');
 
             // Return a failure response to Safaricom
             return response()->json([
                 'ResultCode' => 1, 
                 'ResultDesc' => 'Failed to save payment data.'
             ]);
         }
     }
 
     // Return a success response to Safaricom
     return response()->json([
         'ResultCode' => 0, 
         'ResultDesc' => 'Success'
     ]);
 }

 
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
        Log::channel('single')->{$level}($message, $context);
        $logFile = storage_path('logs/mpesa_trans.txt');
        file_put_contents($logFile, "[" . now() . "] " . strtoupper($level) . ": " . $message . ' ' . json_encode($context) . PHP_EOL, FILE_APPEND);
    }

    public function paymentSuccess(Request $request)
    {
        // Retrieve the unique number from the request
        $uniqueNumber = $request->input('unique_number'); // or pass this as a route parameter
        
        // Fetch payment details from the database
        $payment = DB::table('stk_push_responses')
            ->where('unique_number', $uniqueNumber)
            ->first();
    
        // Check if payment exists
        if (!$payment) {
            return view('mpesa.payment-failed', [
                'message' => 'Payment details could not be found. Please try again or contact support.',
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
}