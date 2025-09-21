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
                // ✅ 2. Pull config from DB (main source)
                $config = DB::table('mpesa_configs')
                    ->where('api_type', 'c2b')
                    ->first();

                // ✅ 3. Merge DB values with fallback to .env
                $initiatorName     = $config->initiator_name     ?? config('mpesa.initiator_name');
                $initiatorPassword = $config->initiator_password ?? config('mpesa.initiator_password');
                $shortCode         = $config->shortcode          ?? config('mpesa.shortcode');
                $resultUrl         = $config->result_url         ?? config('mpesa.result_url');
                $timeoutUrl        = $config->timeout_url        ?? config('mpesa.timeout_url');

                if (empty($initiatorName) || empty($initiatorPassword) || empty($shortCode) || empty($resultUrl) || empty($timeoutUrl)) {
                    throw new Exception("M-Pesa configuration incomplete. Please set initiator, password, shortcode, and callback URLs in DB or .env.");
                }

                // ✅ 4. Get access token via MpesaTheController
                $mpesa = new MpesaTheController();
                $token = $mpesa->getAccessToken();

                $url = env('MPESA_ENV') === 'live'
                    ? 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query'
                    : 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query';

                // ✅ 5. Generate SecurityCredential
                $securityCredential = $this->generateSecurityCredential($initiatorPassword);

                $payload = [
                    "Initiator"          => $initiatorName,
                    "SecurityCredential" => $securityCredential,
                    "CommandID"          => "TransactionStatusQuery",
                    "TransactionID"      => $ref,
                    "PartyA"             => $shortCode,
                    "IdentifierType"     => "4", // TransactionID
                    "ResultURL"          => url($resultUrl),
                    "QueueTimeOutURL"    => url($timeoutUrl),
                    "Remarks"            => "Payment inquiry",
                    "Occasion"           => "StatusQuery"
                ];

                // ✅ 6. Call Safaricom API
                $safaricomResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ])->post($url, $payload);

                if ($safaricomResponse->successful()) {
                    $result   = $safaricomResponse->json();
                    $status   = $result['ResultDesc'] ?? 'Pending';
                    $response = json_encode($result, JSON_PRETTY_PRINT);

                    // ✅ Cache in local DB if confirmed success
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

    /**
     * Encrypt initiator password into SecurityCredential
     */
    private function generateSecurityCredential($initiatorPassword)
    {
        $certPath = config('mpesa.certificates.' . config('mpesa.env'));

        if (!file_exists($certPath)) {
            throw new Exception("M-Pesa certificate not found at: {$certPath}");
        }

        $publicKey = file_get_contents($certPath);
        openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Handle Safaricom Transaction Status Result Callback
     */
    public function handleTransactionStatusResult(Request $request)
    {
        Log::info('Transaction Status Result received', [
            'payload' => $request->all(),
            'raw' => $request->getContent(),
        ]);

        try {
            $data = $request->json()->all();

            DB::table('payment_inquiries')->insert([
                'reference_number' => $data['Result']['TransactionID'] ?? 'N/A',
                'status'           => 'Result',
                'response'         => json_encode($data),
                'checked_ip'       => $request->ip(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Result received successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error saving Transaction Status Result', ['error' => $e->getMessage()]);
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Failed to process result'
            ]);
        }
    }

    /**
     * Handle Safaricom Transaction Status Timeout Callback
     */
    public function handleTransactionStatusTimeout(Request $request)
    {
        Log::warning('Transaction Status Timeout received', [
            'payload' => $request->all(),
            'raw' => $request->getContent(),
        ]);

        try {
            $data = $request->json()->all();

            DB::table('payment_inquiries')->insert([
                'reference_number' => $data['TransactionID'] ?? 'N/A',
                'status'           => 'Timeout',
                'response'         => json_encode($data),
                'checked_ip'       => $request->ip(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Timeout received successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error saving Transaction Status Timeout', ['error' => $e->getMessage()]);
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Failed to process timeout'
            ]);
        }
    }
}