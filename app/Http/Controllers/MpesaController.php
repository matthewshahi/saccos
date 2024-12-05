<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MpesaController extends Controller
{
    public function showStkPushForm()
    {
        $configs = DB::table('mpesa_configs')->get();
        return view('mpesa.stkpush', compact('configs'));
    }

    public function storeStkPush(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'shortcode' => 'required|string'
        ]);

        $config = DB::table('mpesa_configs')->where('shortcode', $request->shortcode)->where('api_type', 'mpesa_express')->first();
        if (!$config) {
            return redirect()->back()->withErrors('Invalid shortcode configuration.');
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($config->shortcode . $config->passkey . $timestamp);

        $stkPushRequest = [
            'BusinessShortCode' => $config->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $request->amount,
            'PartyA' => $request->phone_number,
            'PartyB' => $config->shortcode,
            'PhoneNumber' => $request->phone_number,
            'CallBackURL' => route('stkpush.callback'),
            'AccountReference' => 'Transaction' . now()->timestamp,
            'TransactionDesc' => 'Payment for goods or services',
        ];

        $url = env('MPESA_ENV') === 'live'
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $response = Http::withToken($this->getAccessToken($config))->post($url, $stkPushRequest);

        if ($response->failed()) {
            return redirect()->back()->withErrors('STK Push failed: ' . $response->body());
        }

        $responseBody = $response->json();

        DB::table('stk_push_logs')->insert([
            'checkout_request_id' => $responseBody['CheckoutRequestID'],
            'phone_number' => $request->phone_number,
            'amount' => $request->amount,
            'account_reference' => $stkPushRequest['AccountReference'],
            'transaction_description' => $stkPushRequest['TransactionDesc'],
            'shortcode' => $request->shortcode,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        return redirect()->route('stkpush.index')->with('success', 'STK Push request sent successfully.');
    }

    private function getAccessToken($config)
    {
        $url = env('MPESA_ENV') === 'live'
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withBasicAuth($config->consumer_key, $config->consumer_secret)->get($url);

        if ($response->failed()) {
            abort(500, 'Failed to generate access token.');
        }

        return $response->json()['access_token'];
    }

    public function handleStkPushCallback(Request $request)
    {
        $data = $request->json()->all();

        DB::table('stk_push_responses')->insert([
            'checkout_request_id' => $data['Body']['stkCallback']['CheckoutRequestID'],
            'merchant_request_id' => $data['Body']['stkCallback']['MerchantRequestID'] ?? null,
            'result_code' => $data['Body']['stkCallback']['ResultCode'],
            'result_description' => $data['Body']['stkCallback']['ResultDesc'],
            'mpesa_receipt_number' => $data['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'] ?? null,
            'transaction_date' => now(),
            'phone_number' => $data['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'] ?? null,
            'amount' => $data['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'] ?? null,
            'created_at' => now(),
        ]);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }
}