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
     * Reset credentials to default null values.
     */
    private function resetCredentials()
    {
        $this->consumerKey = null;
        $this->consumerSecret = null;
        $this->shortCode = null;
        $this->passkey = null;
        $this->accessToken = null;
        $this->tokenExpiresAt = null;
        $this->callbackUrl=null;
    }

    /**
     * Show the STK Push form with available shortcodes.
     */
    public function showSTKPushForm()
    {
        $stkConfigs = DB::table('mpesa_configs')->where('api_type', 'Mpesa_express')->get();
        $this->logTransaction('Showing STK Push form with available shortcodes.', []);
        return view('mpesa.stkpush', compact('stkConfigs'));
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


function plans(Request $request){
    //echo ;
    if (strpos(URL::current(),'epaper.nairobilawmonthly.com') !== false) {
    //if (str_contains( URL::current(), 'law')) { 
        $publication_id=2;
    }else{
        $publication_id=1;
    }

    $user_id=$request->user()->id;
    
    $publication = DB::table('publications')->where('id', $publication_id)->first();
    $packages = DB::table('packages')->where('publication_id', $publication_id)->get();

return view('packages', ["packages"=>$packages,"publication_name"=>$publication->description,"publication_id"=>$publication_id]);
}

function checkout(Request $request){
    $user_id=$request->user()->id;
    $package_id=$request->input('packageid');
    $publication_id=$request->input('publicationid');
    $publication=DB::table('publications')->where('id', $publication_id)->first();
    $package = DB::table('packages')->where('id', $package_id)->first();

if($package){

    $paybillorder=$this->createPayBillOrder($user_id,$package_id);
//return paymentsView
return ($publication)?
view('pay', ["user_id"=>$user_id,"publicationid"=>$publication_id,"packageid"=>$package_id,"amount"=>$package->price??'',"ordernumber"=>$paybillorder,"publication_name"=>$publication->description??'']):
 redirect("/");
}else{
    return redirect("/");
}


}





    /**
     * Load configuration based on the selected shortcode.
     */
    private function loadConfig($shortcode)
    {
        $config = DB::table('mpesa_configs')->where('shortcode', $shortcode)->where('api_type', 'Mpesa_express')->first();

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
        if (Carbon::now()->lt(Carbon::parse($this->tokenExpiresAt))) {
            return $this->accessToken;  // Return existing token if not expired
        }

        /*$url = env('MPESA_ENV') === 'live' ? 
            'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' : 
            'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';*/

        $url='https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' ;

        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($url);

        if ($response->failed()) {
            $this->logTransaction('Failed to generate access token: ' . $response->body(), [], 'error');
            throw new Exception('Failed to generate access token');
        }

        $this->accessToken = $response->json()['access_token'];
        $expiresIn = $response->json()['expires_in'];

        DB::table('mpesa_configs')->where('shortcode', $this->shortCode)->where('api_type', 'Mpesa_express')->update([
            'access_token' => $this->accessToken,
            'token_expires_at' => Carbon::now()->addSeconds($expiresIn)
        ]);

        $this->logTransaction('New access token generated and saved to database.', [
            'access_token' => $this->accessToken,
            'expires_in' => $expiresIn
        ]);

        return $this->accessToken;
    }

    /**
     * Initiate STK Push request to Safaricom API.
     */





     function renew(Request $request){

        $package_id=$request->package;
        $user_id=$request->user;

        $paybillorder=$this->createPayBillOrder($user_id,$package_id);
      
        $package=DB::table('packages')->where('id',$package_id)->first();
        $publicationid=$package->publication_id;

        $prevData=DB::table('stk_push_logs')->where('user_id', $user_id)->orderBy("id","DESC")->first();
        $phoneno=$prevData->phone_number;
        
     
 
 
         /**** */

        
        $unique_number=uniqid();
        $shortcode =670361;
        $this->loadConfig($shortcode);   
        $phoneNumber = $this->formatPhoneNumber($phoneno);  
        $amount =(int)$package->price;
        $accountReference =$paybillorder; 
        $transactionDesc = "Epaper Purchase Renewal";

      
        $this->logTransaction('Initiating STK Push for phone: ' . $phoneno . ', amount: ' . $amount . ' using shortcode ' . $shortcode, []);

        [$password, $timestamp] = $this->generateMpesaPassword();
        $accessToken = $this->getAccessToken();

 
        $url='https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'BusinessShortCode' => $this->shortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' =>$amount,
            'PartyA' => $phoneno,
            'PartyB' => $this->shortCode,
            'PhoneNumber' => $phoneno,
            'CallBackURL' =>'https://epaper.nairobilawmonthly.com/api/pay/stk/callback/'.$unique_number,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc,
        ]);

        if ($response->failed()) {
            $this->logTransaction('Failed to initiate STK Push: ' . $response->body(), [], 'error');
            return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'.$response->body()], 500);
        }


        $ReseponseCode=$response->json()['ResponseCode'];
        $RequestCheckoutid=$response->json()['CheckoutRequestID'];
        if($ReseponseCode=="0"){
           

            $this->logSTKPushRequest($RequestCheckoutid,$unique_number, $phoneno, $amount, $accountReference, $transactionDesc,$user_id,$package_id);


        }else{
            return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'], 500);
        }

        $this->logTransaction('STK Push initiated successfully: ' . $response->body(), []);

        //return response()->json($response->json());
    
        /***** */

    
        return view("renew",["order"=>$paybillorder,"phoneno"=>$phoneno,"publicationid"=>$publicationid]);
    }





     public function consumerinitiateSTKPush(Request $request)
     {   
         
   
         
         $request->validate([
             'phone' => 'required|string',
             'uniq' => 'required|string',
             'orderno' => 'required|string',
             'packageid' => 'required|numeric',
         ]);
         $unique_number=$request->input('uniq');
         $shortcode =670361;
         $this->loadConfig($shortcode);   
         $phoneNumber = $this->formatPhoneNumber($request->input('phone'));  
         $packageid = $request->input('packageid'); 
         $package = DB::table('packages')->where('id', $packageid)->first();
         $amount =(int)$package->price;
         $accountReference =$request->input('orderno'); 
         $transactionDesc = "Epaper Purchase";
 
       
         $this->logTransaction('Initiating STK Push for phone: ' . $phoneNumber . ', amount: ' . $amount . ' using shortcode ' . $shortcode, []);
 
         [$password, $timestamp] = $this->generateMpesaPassword();
         $accessToken = $this->getAccessToken();
 
  
         $url='https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
 
         $response = Http::withHeaders([
             'Authorization' => 'Bearer ' . $accessToken,
             'Content-Type' => 'application/json',
         ])->post($url, [
             'BusinessShortCode' => $this->shortCode,
             'Password' => $password,
             'Timestamp' => $timestamp,
             'TransactionType' => 'CustomerPayBillOnline',
             'Amount' => $amount,
             'PartyA' => $phoneNumber,
             'PartyB' => $this->shortCode,
             'PhoneNumber' => $phoneNumber,
             'CallBackURL' =>'https://epaper.nairobilawmonthly.com/api/pay/stk/callback/'.$unique_number,
             'AccountReference' => $accountReference,
             'TransactionDesc' => $transactionDesc,
         ]);
 
         if ($response->failed()) {
             $this->logTransaction('Failed to initiate STK Push: ' . $response->body(), [], 'error');
             return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'.$response->body()], 500);
         }
 
 
         $ReseponseCode=$response->json()['ResponseCode'];
         $RequestCheckoutid=$response->json()['CheckoutRequestID'];
         if($ReseponseCode=="0"){
           
             $user_id=$request->user()->id;

             $this->logSTKPushRequest($RequestCheckoutid,$unique_number, $phoneNumber, $amount, $accountReference, $transactionDesc,$user_id,$packageid);
 
 
         }else{
 
         }
 
         $this->logTransaction('STK Push initiated successfully: ' . $response->body(), []);
 
         return response()->json($response->json());
     }









     
    public function initiateSTKPush(Request $request)
    {   
        
        $unique_number=$request->input('unique_number');
        
        $request->validate([
            'shortcode' => 'required|string',
            'phone_number' => 'required|string',
            'amount' => 'required|numeric',
            'account_reference' => 'required|string',
            'transaction_desc' => 'required|string',
        ]);

        $shortcode = $request->input('shortcode');
        $this->loadConfig($shortcode);  // Load configuration based on shortcode

        $phoneNumber = $this->formatPhoneNumber($request->input('phone_number'));  // Format phone number
        $amount = $request->input('amount');
        $accountReference = $request->input('account_reference');
        $transactionDesc = $request->input('transaction_desc');

        $this->logTransaction('Initiating STK Push for phone: ' . $phoneNumber . ', amount: ' . $amount . ' using shortcode ' . $shortcode, []);

        [$password, $timestamp] = $this->generateMpesaPassword();
        $accessToken = $this->getAccessToken();

        // Log the request details to the database
        //$this->logSTKPushRequest($unique_number, $phoneNumber, $amount, $accountReference, $transactionDesc);

        /*$url = env('MPESA_ENV') === 'live' ? 
            'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest' : 
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';*/

            $url='https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'BusinessShortCode' => $this->shortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $this->shortCode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' =>'https://epaper.nairobilawmonthly.com/api/pay/stk/callback/'.$unique_number,// route('payment_stk.callback', ['unique_number' => $unique_number]),
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc,
        ]);

        if ($response->failed()) {
            $this->logTransaction('Failed to initiate STK Push: ' . $response->body(), [], 'error');
            return response()->json(['error' => 'Failed to initiate STK Push. Please try again later.'], 500);
        }


        $ReseponseCode=$response->json()['ResponseCode'];
        $RequestCheckoutid=$response->json()['CheckoutRequestID'];
        if($ReseponseCode=="0"){
          
            $user_id=$request->user()->id;
            $package_id=1;

            $this->logSTKPushRequest($RequestCheckoutid,$unique_number, $phoneNumber, $amount, $accountReference, $transactionDesc,$user_id,$package_id);


        }else{

        }

        $this->logTransaction('STK Push initiated successfully: ' . $response->body(), []);

        return response()->json($response->json());
    }

    /**
     * Handle STK Push Callback from Safaricom.
     */
    public function handleSTKPushCallback(Request $request)
{
    // Log any incoming request to this route
    $this->logTransaction('STK Push callback route hit.', ['request_data' => $request->all()]);
    $this->logTransaction('Raw STK Push callback data:', ['raw_data' => $request->getContent()]);

    // Process callback data


    $callbackJSONData=file_get_contents('php://input');
    $callbackData 	=json_decode($callbackJSONData);


    // Proceed with saving callback data

    

    try {
      DB::transaction(function () use ($callbackData) {
            $this->saveSTKCallbackData($callbackData);
       });
       // $this->logTransaction('STK Push callback data saved successfully.', []);
    } catch (Exception $e) {
        $this->logTransaction('Failed to save STK Push callback data: ' . $e->getMessage(), [], 'error');
        return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to save callback data.'.$e->getMessage()]);
    }

    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
}





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






    /**
     * Handle incoming C2B Payments from Safaricom.
     */




     function registerurls(){  // RUN ONLY ONCE TO REGISTER VALIDATION AND CONFIRMATION CALL BACK URLS

        $shortcode =670361;
        $this->loadConfig($shortcode); 

        [$password, $timestamp] = $this->generateMpesaPassword();
        $accessToken = $this->getAccessToken();


        $validationUrl="https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl"; 
        $ch = curl_init($validationUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$accessToken,
            'Content-Type: application/json'
        ]);
         
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            "ShortCode"=>$shortcode,
            "ResponseType"=>"Completed",
            "ConfirmationURL"=>"https://epaper.nairobilawmonthly.com/api/pay/paybill/confirmation/callback", //REGISTER CONFIRMATION URL
            "ValidationURL"=>"https://epaper.nairobilawmonthly.com/api/pay/paybill/validation/callback",  //REGISTER VALIDATION URL
        )));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response   = curl_exec($ch);
        var_dump($response);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            echo $error_msg;
        }
        curl_close($ch);
         
         //$error_msg;
        
        }

    function createPayBillOrder($user_id,$package_id){
        
        $package=DB::table('packages')->where('id',$package_id)->first();
        $publication=$package->name;
        $duration=(int)$package->duration;
        $amount=$package->price;
     
       /* $order=DB::table('paybill_orders')->where("user_id",$user_id)->where("is_paid","PENDING")->first();

        if($order){
           $insert=DB::table('paybill_orders')->where("user_id",$user_id)->update([
                 'amount'=>$amount,
                 'package_id'=>$package_id,
                  'balance'=>$amount
           ]);
           $orderId="NLM".$order->id;
        }else{*/
                 $insert=DB::table('paybill_orders')->insertGetId([
                'amount'=>$amount,
                'package_id'=>$package_id,
                'user_id'=>$user_id,
                'balance'=>$amount
            ]);
            $orderId=((int)$package->publication_id==2)?"NLM".$insert:"NBM".$insert;
           // $orderId="NLM".$insert;
        //}

       
        
        return $orderId;
 
    }
 

 public function validationRequest(){
    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    
 }

    public function handleC2BPayment(Request $request)
    {
        $this->logTransaction('Received C2B Payment.', []);
        $this->logTransaction('Raw C2B Payment data:', ['raw_data' => $request->getContent()]);

        $paymentData = $request->only([
            'TransactionType', 'TransID', 'TransTime', 'TransAmount', 'BusinessShortCode',
            'BillRefNumber', 'InvoiceNumber', 'OrgAccountBalance', 'ThirdPartyTransID',
            'MSISDN', 'FirstName', 'MiddleName', 'LastName'
        ]);

        $paymentData['transaction_time'] = Carbon::createFromFormat('YmdHis', $paymentData['TransTime']);

        $orderNumber=(int)substr($paymentData['BillRefNumber'],3);

        $amount=(double)$paymentData['TransAmount'];
        $order=DB::table('paybill_orders')->where("id",$orderNumber)->first();

        if($order){

         $order_amount=(double)$order->amount;
         $balance=$order_amount-$amount;
  
        if($balance<=0){

          $insert=DB::table('paybill_orders')->where("id",$orderNumber)->update([
             'is_paid'=>'PAID',
             'amount_paid'=>$amount,
             'balance'=>$balance,
              'updated_at'=>Carbon::now(),
              'TransID'=>$paymentData['TransID']
             ]);


             /***subscriber****/


           
             $package_id=$order->package_id;
             $user_id=$order->user_id;
     
             $package=DB::table('packages')->where('id',$package_id)->first();
             $publication=$package->name;
             $duration=(int)$package->duration;
     
             $publication_id=$package->publication_id;  
             $start=Carbon::now();
             $enddate=date('Y-m-d',strtotime($start->addDays($duration)));
             $start=date('Y-m-d');
             $paymode="MPESA";
             $mpesaReceiptNumber=$paymentData['TransID']??null;
        
             $subscriber=DB::table('subscribers')->where('user_id',$user_id)->where('publication_id',$publication_id)->whereDate("end_date",">=",date('Y-m-d'))->orderBy("id","DESC")->first();
 
             if($subscriber){   
                $s=Carbon::createFromFormat('Y-m-d',$subscriber->end_date)->addDay();
                $start=Carbon::createFromFormat('Y-m-d',$subscriber->end_date)->addDay();
                $enddate= $s->addDays($duration+1);
             }
                    
              $this->updateSubcribers($user_id,$publication_id, $package_id,$start,$enddate,$amount, $paymode,$mpesaReceiptNumber);
            
             /******subscriber */

    }
    else{
         
             $insert=DB::table('paybill_orders')->where("id",$orderNumber)->update([
            'amount_paid'=>$amount,
            'balance'=>$balance
         ]);

       } 
  
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
            $this->logTransaction('C2B Payment processed successfully.', []);
        } catch (Exception $e) {
            $this->logTransaction('Failed to save C2B Payment data: ' . $e->getMessage(), [], 'error');
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to save payment data.']);
        }

    }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }





    private function updateSubcribers($user_id,$publication_id, $package_id,$start_date,$end_date,$amount, $paymode, $payref)
    {
        try {
            DB::transaction(function () use ($user_id,$publication_id, $package_id,$start_date,$end_date,$amount, $paymode, $payref) {
                DB::table('subscribers')->insert([
                    'user_id' => $user_id,
                    'publication_id' =>$publication_id,
                    'package_id' => $package_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'amount_paid' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'payment_mode' => $paymode,
                    'payment_reference' => $payref,
                     
                ]);
            });
            $this->logTransaction('Subscribe updated: ' . $payref, []);
        } catch (Exception $e) {
            $this->logTransaction('Failed to update subscriber: ' . $e->getMessage(), [], 'error');
        }
    }



    /**
     * Log STK Push Request to Database.
     */
    private function logSTKPushRequest($check_out_request_id,$unique_number, $phoneNumber, $amount, $accountReference, $transactionDesc,$user_id,$package_id)
    {
        try {
            DB::transaction(function () use ($check_out_request_id,$unique_number, $phoneNumber, $amount, $accountReference, $transactionDesc,$user_id,$package_id) {
                DB::table('stk_push_logs')->insert([
                    'unique_number' => $unique_number,
                    'checkout_request_id' => $check_out_request_id,
                    'phone_number' => $phoneNumber,
                    'amount' => $amount,
                    'account_reference' => $accountReference,
                    'transaction_description' => $transactionDesc,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'user_id' => $user_id,
                    'package_id' => $package_id,
                ]);
            });
            $this->logTransaction('STK Push request logged to the database for phone: ' . $phoneNumber, []);
        } catch (Exception $e) {
            $this->logTransaction('Failed to log STK Push request: ' . $e->getMessage(), [], 'error');
        }
    }

    /**
     * Log transaction details to a custom log file.
     */
    private function logTransaction($message, $context = [], $level = 'info')
    {
        Log::channel('single')->{$level}($message, $context);
        $logFile = storage_path('logs/mpesa_trans.txt');
        file_put_contents($logFile, "[" . now() . "] " . strtoupper($level) . ": " . $message . ' ' . json_encode($context) . PHP_EOL, FILE_APPEND);
    }
}
