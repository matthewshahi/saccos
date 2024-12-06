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
        dd( $this->callbackUrl);
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
            // Validate posted fields
            $request->validate([
                'phone' => 'required|string',
                'uniq' => 'required|string',
                'amount' => 'required|numeric|min:1', // Validate that the amount is a valid number greater than 0
            ]);

            $unique_number = $request->input('uniq');
            $phoneNumber = $this->formatPhoneNumber($request->input('phone'));
            $amount = $request->input('amount'); // Amount comes from posted input

            // Set the shortcode dynamically from the constructor
            $shortcode = $this->shortCode;

            // Define the environment-specific URL
            $url = env('MPESA_ENV') === 'live'
                ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
                : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

            // Other transaction details
            $accountReference = $unique_number;
            $transactionDesc = "Online Transaction";

            // Generate password and access token
            [$password, $timestamp] = $this->generateMpesaPassword();
            $accessToken = $this->getAccessToken();

            // Make the STK Push request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $phoneNumber,
                'PartyB' => $shortcode,
                'PhoneNumber' => $phoneNumber,
                'CallBackURL' => $this->callbackUrl . "/" . $unique_number,
                'AccountReference' => $accountReference,
                'TransactionDesc' => $transactionDesc,
            ]);

            // Handle response
            if ($response->failed()) {
                // Log failure and return an error response
                Log::error('Failed to initiate STK Push', [
                    'response' => $response->body(),
                ]);
                return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'], 500);
            }

            $responseBody = $response->json();

            // Check response code
            if (isset($responseBody['ResponseCode']) && $responseBody['ResponseCode'] === "0") {
                // Log the successful request
                Log::info('STK Push request successful', [
                    'CheckoutRequestID' => $responseBody['CheckoutRequestID'],
                    'unique_number' => $unique_number,
                    'phone' => $phoneNumber,
                    'amount' => $amount,
                ]);

                // Save the outgoing STK Push details in the database
                DB::table('stk_push_logs')->insert([
                    'unique_number' => $unique_number,
                    'checkout_request_id' => $responseBody['CheckoutRequestID'],
                    'phone_number' => $phoneNumber,
                    'amount' => $amount,
                    'account_reference' => $accountReference,
                    'transaction_description' => $transactionDesc,
                    'shortcode' => $shortcode,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Return the successful response
                return response()->json($responseBody);
            } else {
                // Log failure details and return an error response
                Log::error('STK Push request failed', [
                    'response' => $responseBody,
                ]);
                return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'], 500);
            }
        }



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
public function handleSTKPushCallback(Request $request)
{
    // Log the incoming request
    $this->logTransaction('STK Push callback route hit.', ['request_data' => $request->all()]);

    // Decode the raw JSON payload
    $callbackJSONData = file_get_contents('php://input');
    $callbackData = json_decode($callbackJSONData);

    // Validate callback data
    if (!isset($callbackData->Body->stkCallback)) {
        $this->logTransaction('Invalid STK Push callback data.', ['callbackData' => $callbackData], 'error');
        return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback data.']);
    }

    // Save callback data
    try {
        DB::transaction(function () use ($callbackData) {
            $this->saveSTKCallbackData($callbackData);
        });

        $this->logTransaction('STK Push callback data saved successfully.', [
            'checkout_request_id' => $callbackData->Body->stkCallback->CheckoutRequestID,
            'result_code' => $callbackData->Body->stkCallback->ResultCode,
        ]);

    } catch (Exception $e) {
        $this->logTransaction('Failed to save STK Push callback data: ' . $e->getMessage(), [], 'error');
        return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to save callback data.']);
    }

    // Respond to Safaricom
    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully.']);
}


//     public function handleSTKPushCallback(Request $request)
// {
//     // Log any incoming request to this route
//     $this->logTransaction('STK Push callback route hit.', ['request_data' => $request->all()]);
//     $this->logTransaction('Raw STK Push callback data:', ['raw_data' => $request->getContent()]);

//     // Process callback data


//     $callbackJSONData=file_get_contents('php://input');
//     $callbackData 	=json_decode($callbackJSONData);


//     // Proceed with saving callback data

    

//     try {
//       DB::transaction(function () use ($callbackData) {
//             $this->saveSTKCallbackData($callbackData);
//        });
//        // $this->logTransaction('STK Push callback data saved successfully.', []);
//     } catch (Exception $e) {
//         $this->logTransaction('Failed to save STK Push callback data: ' . $e->getMessage(), [], 'error');
//         return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to save callback data.'.$e->getMessage()]);
//     }

//     return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
// }





    /**
     * Save STK Callback Data.
     */
    private function saveSTKCallbackData($callbackData)
    {

       
        $merchantRequestID=$callbackData->Body->stkCallback->MerchantRequestID??null;
        $checkoutRequestID = $callbackData->Body->stkCallback->CheckoutRequestID?? null;
        $resultCode = $callbackData->Body->stkCallback->ResultCode?? null;
        $resultDesc = $callbackData->Body->stkCallback->ResultDesc?? null;

        $amount =$callbackData->Body->stkCallback->CallbackMetadata->Item[0]->Value?? null;
        $mpesaReceiptNumber =$callbackData->Body->stkCallback->CallbackMetadata->Item[1]->Value??null;
        $transactionDate = $callbackData->Body->stkCallback->CallbackMetadata->Item[2]->Value??null;
        $phoneNumber =$callbackData->Body->stkCallback->CallbackMetadata->Item[3]->Value??null;
          
        DB::table('stk_push_logs')->where('checkout_request_id',$checkoutRequestID)->update([
            'merchant_request_id' => $merchantRequestID,
           // 'checkout_request_id' => $checkoutRequestID,
            'result_code' => $resultCode,
            'result_description' => $resultDesc,
            'amount' => $amount,
            'transaction_id' => $mpesaReceiptNumber,
            'transaction_time' =>Carbon::createFromFormat('YmdHis', $transactionDate),
            'phone_number' => $phoneNumber,
            'updated_at' => now(),
        ]);



        $packageData=DB::table('stk_push_logs')->where('checkout_request_id', $checkoutRequestID)->first();
        $package_id=$packageData->package_id;
        $user_id=$packageData->user_id;

        $package=DB::table('packages')->where('id',$package_id)->first();
        $publication=$package->name;
        $duration=(int)$package->duration;

        $publication_id=$package->publication_id;  
        $start=Carbon::now();
        $enddate=date('Y-m-d',strtotime($start->addDays($duration)));
        $start=date('Y-m-d');
        $paymode="MPESA";

        
 $subscriber=DB::table('subscribers')->where('user_id',$user_id)->where('publication_id',$publication_id)->whereDate("end_date",">=",date('Y-m-d'))->orderBy("id","DESC")->first();
 
 if($subscriber){   
    $s=Carbon::createFromFormat('Y-m-d',$subscriber->end_date)->addDay();
    $start=Carbon::createFromFormat('Y-m-d',$subscriber->end_date)->addDay();
    $enddate= $s->addDays($duration+1);
 }
        

   $this->updateSubcribers($user_id,$publication_id, $package_id,$start,$enddate,$amount, $paymode,$mpesaReceiptNumber);



    }

    public function registerUrls()
    {
        try {
            // Retrieve the shortcode configuration from the database
            $config = DB::table('mpesa_configs')
                ->where('api_type', 'c2b')
                ->first();
    
                //dd($config);
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
                ? 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl'
                : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';
    
            // CURL request to register URLs
            $ch = curl_init($validationUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "ShortCode" => $this->shortCode,
                "ResponseType" => $config->response_type, // Dynamically fetched ResponseType
                "ConfirmationURL" => $this->callbackUrl['ConfirmationURL'],
                "ValidationURL" => $this->callbackUrl['ValidationURL'],
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('Curl error: ' . curl_error($ch));
            }
    
            curl_close($ch);
    
            // Log response for debugging
            Log::info('URL Registration Response:', [
                'response' => $response,
                'config' => $config,
            ]);
    
            return response()->json([
                'message' => 'URLs registered successfully.',
                'response' => json_decode($response, true),
            ]);
        } catch (Exception $e) {
            Log::error('Error registering URLs:', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    

    
 

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

    public function paymentSuccess()
    {
        // Example data passed to the success view
        $data = [
            'message' => 'Your payment was successful. Thank you!',
            'transaction_id' => session('transaction_id', 'N/A'), // Retrieve transaction ID from session if set
            'amount' => session('amount', 'N/A'), // Retrieve amount from session if set
            'phone_number' => session('phone_number', 'N/A'), // Retrieve phone number from session if set
        ];
    
        // Return the success view with the data
        return view('mpesa.payment-success', $data);
    }
}
