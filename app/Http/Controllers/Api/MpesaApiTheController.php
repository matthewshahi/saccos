<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Http\Controllers\MpesaTheController;


class MpesaApiTheController extends Controller
{
    /**
     * Receive STK push initiation request from mobile app
     * (VALIDATE + CHECK DUPLICATE + SAVE ONLY)
     */
    public function initiateStkPushFromApp(Request $request)
    {

    Log::info('RAW PAYLOAD', $request->all());

        // -----------------------------
        // 1. Basic validation
        // -----------------------------
        $validator = Validator::make($request->all(), [
    'user_id'   => 'required|integer',
    'phone'     => 'required|string',
    'amount'    => 'required|numeric',
    'reference' => 'required|string',
]);



        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid request data',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // -----------------------------
        // 2. Clean & normalize phone
        // -----------------------------
        $rawPhone = trim($request->phone);
        $phone = preg_replace('/[^0-9]/', '', $rawPhone);

        if (strlen($phone) === 10 && str_starts_with($phone, '07')) {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9 && str_starts_with($phone, '7')) {
            $phone = '254' . $phone;
        }

        $safaricomPrefixes = [
            '25470', '25471', '25472', '25474',
            '25475', '25476', '25479',
        ];

        $isSafaricom = false;
        foreach ($safaricomPrefixes as $prefix) {
            if (str_starts_with($phone, $prefix)) {
                $isSafaricom = true;
                break;
            }
        }

        if (strlen($phone) !== 12 || !$isSafaricom) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid Safaricom phone number',
            ], 422);
        }

        // -----------------------------
        // 3. Validate amount
        // -----------------------------
        $amount = (float) $request->amount;

        if ($amount <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Amount must be greater than zero',
            ], 422);
        }

        if ($amount > 1000000) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Amount exceeds allowed limit',
            ], 422);
        }

        // -----------------------------
        // 4. Duplicate check (DB-based)
        // -----------------------------
        // $memberId = optional($request->user())->id;
        $memberId = (int) $request->user_id;


        $twoMinutesAgo = Carbon::now()->subMinutes(2);

        $existing = DB::table('mpesa_app_stk_requests')
            ->where('member_id', $memberId)
            ->where('amount', $amount)
            ->where('status', 'PENDING')
            ->where('created_at', '>=', $twoMinutesAgo)
            ->first();

        if ($existing) {
    return response()->json([
        'status'  => 'error',
        'code'    => 'DUPLICATE_PENDING',
        'message' => 'A similar transaction is already pending. Please wait for 2 minutes before retrying.',
    ], 409);
}


        // -----------------------------
        // 5. Save to database
        // -----------------------------
        DB::table('mpesa_app_stk_requests')->insert([
            'member_id'  => $memberId,
            'phone'      => $phone,
            'amount'     => $amount,
            'reference'  => $request->reference,
            'status'     => 'PENDING',
            'request_ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // -----------------------------
// 6. Hand off to canonical STK processor
// -----------------------------
$stkRequest = new Request([
    'phone'  => $phone,
    'uniq'   => $request->reference,
    'amount' => $amount,
]);

$stkController = app(MpesaTheController::class);
 

$stkResponse = $stkController->storeStkPush($stkRequest);

if (method_exists($stkResponse, 'getStatusCode') && $stkResponse->getStatusCode() !== 200) {
    return $stkResponse;
}



        // -----------------------------
        // 6. Response
        // -----------------------------
        return response()->json([
            'status'  => 'ok',
            'message' => 'STK request received and queued successfully',
            'data'    => [
                'phone'     => $phone,
                'amount'    => $amount,
                'reference' => $request->reference,
            ],
        ], 200);
    }
}
