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
            // ✅ Always log inquiry attempt
            DB::table('payment_inquiries')->insert([
                'reference_number' => $ref,
                'status'           => 'Queried',
                'response'         => null,
                'checked_ip'       => $request->ip(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // 1. Check locally first
            $payment = DB::table('c2b_payments')->where('transaction_id', $ref)->first();
            if ($payment) {
                $status   = 'Success';
                $response = json_encode($payment, JSON_PRETTY_PRINT);
            } else {
                // 2. Pull dynamic config from DB
                $config = DB::table('mpesa_configs')->where('api_type', 'c2b')->first();
                if (!$config) {
                    throw new Exception("No C2B configuration found in DB.");
                }

                // 3. Credentials (static from config, dynamic from DB)
                $initiatorName     = config('mpesa.initiator_name');
                $initiatorPassword = config('mpesa.initiator_password');
                $shortCode         = $config->shortcode;
                $resultUrl         = config('mpesa.result_url');
                $timeoutUrl        = config('mpesa.timeout_url');

                // 4. Get Access Token (via MpesaTheController logic)
                $mpesa = new MpesaTheController();
                $token = $mpesa->getAccessToken();

                // 5. Transaction Status endpoint
                $url = config('mpesa.env') === 'live'
                    ? 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query'
                    : 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query';

                // 6. Security Credential
                $securityCredential = $this->generateSecurityCredential($initiatorPassword);

                $payload = [
                    "Initiator"          => $initiatorName,
                    "SecurityCredential" => $securityCredential,
                    "CommandID"          => "TransactionStatusQuery",
                    "TransactionID"      => $ref,
                    "PartyA"             => $shortCode,
                    "IdentifierType"     => "4",
                    "ResultURL"          => $resultUrl,
                    "QueueTimeOutURL"    => $timeoutUrl,
                    "Remarks"            => "Payment inquiry",
                    "Occasion"           => "StatusQuery"
                ];

                Log::info('Sending Transaction Status payload to Safaricom', [
    'url'     => $url,
    'headers' => ['Authorization' => 'Bearer ' . substr($token, 0, 10) . '...'],
    'payload' => $payload,
]);


                // 7. Safaricom API call
                $safaricomResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ])->post($url, $payload);

                Log::info('Safaricom Transaction Status response', [
    'status' => $safaricomResponse->status(),
    'body'   => $safaricomResponse->body(),
]);

                if ($safaricomResponse->successful()) {
                    $result   = $safaricomResponse->json();
                    $status   = $result['ResultDesc'] ?? 'Pending';
                    $response = json_encode($result, JSON_PRETTY_PRINT);
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

    private function generateSecurityCredential($initiatorPassword)
    {
        $certificates = config('mpesa.certificates');
        $env          = config('mpesa.env');

        $certPath = $certificates[$env] ?? $certificates['default'];

        if (!file_exists($certPath)) {
            throw new Exception("M-Pesa certificate not found at: {$certPath}");
        }

        $publicKey = file_get_contents($certPath);
        openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    public function handleTransactionStatusResult(Request $request)
    {
        Log::info('Transaction Status Result received', [
            'payload' => $request->all(),
        ]);

        try {
            $data = $request->json()->all();
            $ref  = $data['Result']['TransactionID'] ?? 'N/A';

            DB::table('payment_inquiries')->updateOrInsert(
                ['reference_number' => $ref],
                [
                    'status'     => 'Result',
                    'response'   => json_encode($data),
                    'checked_ip' => $request->ip(),
                    'updated_at' => now(),
                ]
            );

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Result saved']);
        } catch (Exception $e) {
            Log::error('Error saving Transaction Status Result', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to save result']);
        }
    }

    public function handleTransactionStatusTimeout(Request $request)
    {
        Log::warning('Transaction Status Timeout received', [
            'payload' => $request->all(),
        ]);

        try {
            $data = $request->json()->all();
            $ref  = $data['TransactionID'] ?? 'N/A';

            DB::table('payment_inquiries')->updateOrInsert(
                ['reference_number' => $ref],
                [
                    'status'     => 'Timeout',
                    'response'   => json_encode($data),
                    'checked_ip' => $request->ip(),
                    'updated_at' => now(),
                ]
            );

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Timeout saved']);
        } catch (Exception $e) {
            Log::error('Error saving Transaction Status Timeout', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to save timeout']);
        }
    }
}