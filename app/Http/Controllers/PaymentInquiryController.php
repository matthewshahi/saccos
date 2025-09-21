<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentInquiryController extends Controller
{
    public function index()
    {
        return view('payments.check');
    }

    public function check(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string|max:50',
        ]);

        $ref      = trim($request->reference_number);
        $status   = 'Pending';
        $response = null;

        try {
            // ✅ 1. First check locally in c2b_payments
            $payment = DB::table('c2b_payments')->where('transaction_id', $ref)->first();

            if ($payment) {
                $status   = 'Success';
                $response = json_encode($payment, JSON_PRETTY_PRINT);
            } else {
                // ✅ 2. Not found locally → check Safaricom Transaction Status API
                $mpesa   = new MpesaTheController();
                $token   = $mpesa->getAccessToken();     // handles validity refresh
                $shortcode = $mpesa->getShortCode();     // ✅ use getter, not protected property

                $url = env('MPESA_ENV') === 'live'
                    ? 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query'
                    : 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query';

                $payload = [
                    "CommandID"      => "TransactionStatusQuery",
                    "PartyA"         => $shortcode,
                    "IdentifierType" => "4",  // 4 = Transaction ID
                    "Remarks"        => "Payment inquiry",
                    "Initiator"      => $shortcode,
                    "TransactionID"  => $ref,
                    "Occasion"       => "StatusQuery"
                ];

                $safaricomResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ])->post($url, $payload);

                if ($safaricomResponse->successful()) {
                    $result   = $safaricomResponse->json();
                    $status   = $result['ResultDesc'] ?? 'Pending';
                    $response = json_encode($result, JSON_PRETTY_PRINT);

                    // ✅ Cache confirmed successful payments locally
                    if (isset($result['ResultCode']) && $result['ResultCode'] == 0) {
                        DB::table('c2b_payments')->updateOrInsert(
                            ['transaction_id' => $ref],
                            [
                                'transaction_type'   => 'Queried',
                                'transaction_amount' => $result['ResultParameters']['TransAmount'] ?? 0,
                                'msisdn'             => $result['ResultParameters']['MSISDN'] ?? null,
                                'transaction_time'   => now(),
                                'raw_payload'        => json_encode($result),
                                'created_at'         => now(),
                                'updated_at'         => now(),
                            ]
                        );
                    }
                } else {
                    $status   = 'Error';
                    $response = $safaricomResponse->body();
                }
            }
        } catch (Exception $e) {
            Log::error('Payment inquiry failed', ['error' => $e->getMessage()]);
            $status   = 'Error';
            $response = $e->getMessage();
        }

        return view('payments.result', compact('ref', 'status', 'response'));
    }
}