<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaTestController extends Controller
{
    public function showSTKPushForm()
    {
        return view('mpesa.stkpush');  
    }
    public function testConfirmation(Request $request)
    {
        // Simulating a confirmation request
        $response = Http::post(route('mPesaConfirmation'), [
            'TransID' => 'TEST12345',
            'TransactionType' => 'PayBill',
            'TransAmount' => '100',
            'BillRefNumber' => 'INV001',
            'MSISDN' => '254700000000',
            'InvoiceNumber' => 'INV12345',
            'OrgAccountBalance' => '5000',
            'ThirdPartyTransID' => 'THIRD12345',
            'TransTime' => now()->toDateTimeString(),
        ]);

        // Log the response
        Log::info('Test Confirmation Response:', ['response' => $response->json()]);

        return $response->body();
    }

    public function testValidation(Request $request)
    {
        // Simulating a validation request
        $response = Http::post(route('mPesaValidation'), [
            'TransID' => 'TEST54321',
            'TransactionType' => 'PayBill',
            'TransAmount' => '200',
            'BillRefNumber' => 'INV002',
            'MSISDN' => '254700000000',
            'InvoiceNumber' => 'INV54321',
            'TransTime' => now()->toDateTimeString(),
        ]);

        // Log the response
        Log::info('Test Validation Response:', ['response' => $response->json()]);

        return $response->body();
    }
}


