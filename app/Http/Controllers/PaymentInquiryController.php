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

        $ref     = trim($request->reference_number);
        $status  = 'Pending';
        $response = null;

        try {
            if (
                empty(config('mpesa.initiator_name')) ||
                empty(config('mpesa.initiator_password')) ||
                empty(config('mpesa.result_url')) ||
                empty(config('mpesa.timeout_url'))
            ) {
                throw new Exception(
                    "M-Pesa ENV variables missing. Please set MPESA_INITIATOR_NAME, " .
                    "MPESA_INITIATOR_PASSWORD, MPESA_RESULT_URL and MPESA_TIMEOUT_URL in your .env file."
                );
            }

            // ✅ 1. Check locally first
            $payment = DB::table('c2b_payments')->where('transaction_id', $ref)->first();

            if ($payment) {
                $status   = 'Success';
                $response = json_encode($payment, JSON_PRETTY_PRINT);
            } else {
                // ✅ 2. Pull config from DB (shortcode, keys, urls)
                $config = DB::table('mpesa_configs')
                    ->where('api_type', 'c2b')
                    ->first();

                if (!$config) {
                    throw new Exception("No C2B configuration found in DB.");
                }

                // ✅ 3. Get access token via your MpesaTheController
                $mpesa = new MpesaTheController();
                $token = $mpesa->getAccessToken();

                $url = env('MPESA_ENV') === 'live'
                    ? 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query'
                    : 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query';

                // ✅ 4. Build SecurityCredential from .env password + cert
                $securityCredential = $this->generateSecurityCredential(
                    config('mpesa.initiator_password')
                );

                $payload = [
                    "Initiator"          => config('mpesa.initiator_name'),
                    "SecurityCredential" => $securityCredential,
                    "CommandID"          => "TransactionStatusQuery",
                    "TransactionID"      => $ref,
                    "PartyA"             => $config->shortcode,  // from DB
                    "IdentifierType"     => "4", // TransactionID
                    "ResultURL"          => config('mpesa.result_url'),
                    "QueueTimeOutURL"    => config('mpesa.timeout_url'),
                    "Remarks"            => "Payment inquiry",
                    "Occasion"           => "StatusQuery"
                ];

                // ✅ 5. Make Safaricom request
                $safaricomResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ])->post($url, $payload);

                if ($safaricomResponse->successful()) {
                    $result   = $safaricomResponse->json();
                    $status   = $result['ResultDesc'] ?? 'Pending';
                    $response = json_encode($result, JSON_PRETTY_PRINT);

                    // ✅ Save if confirmed success
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