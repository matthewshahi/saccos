<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Http\Controllers\MpesaTheController;

class MpesaApiTheController extends Controller
{
    /**
     * Receive STK push initiation request from mobile app
     *
     * Expected payload (Tap / Quick Payments):
     * {
     *   phone: "0722400737",
     *   amount: 1,
     *   reference: "CA250"
     * }
     *
     * Member is resolved authoritatively via auth.api middleware.
     */
    public function initiateStkPushFromApp(Request $request)
    {
        // -------------------------------------------------
        // 0. Log raw payload (for traceability only)
        // -------------------------------------------------
        Log::info('STK PUSH INITIATION (APP)', $request->all());

        // -------------------------------------------------
        // 1. Resolve authenticated member (authoritative)
        // -------------------------------------------------
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = (int) $member->member_id;

        // -------------------------------------------------
        // 2. Validate payload (NO user_id here)
        // -------------------------------------------------
        $validator = Validator::make($request->all(), [
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

        // -------------------------------------------------
        // 3. Normalise & validate Safaricom phone
        // -------------------------------------------------
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

        // -------------------------------------------------
        // 4. Validate amount
        // -------------------------------------------------
        $amount = (float) $request->amount;

        if ($amount <= 0 || $amount > 1000000) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid transaction amount',
            ], 422);
        }

        // -------------------------------------------------
        // 5. Duplicate pending protection (2 minutes)
        // -------------------------------------------------
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
                'message' => 'A similar transaction is already pending. Please wait before retrying.',
            ], 409);
        }

        // -------------------------------------------------
        // 6. Persist STK request
        // -------------------------------------------------
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

        // -------------------------------------------------
        // 7. Hand off to canonical STK engine
        // -------------------------------------------------
        $stkRequest = new Request([
            'phone'  => $phone,
            'uniq'   => $request->reference,
            'amount' => $amount, 
        ]);

        $stkController = app(MpesaTheController::class);
        $stkResponse = $stkController->storeStkPush($stkRequest);

        if (method_exists($stkResponse, 'getStatusCode')
            && $stkResponse->getStatusCode() !== 200) {
            return $stkResponse;
        }

        // -------------------------------------------------
        // 8. Final response
        // -------------------------------------------------
        return response()->json([
            'status'  => 'ok',
            'message' => 'STK request received and queued successfully',
            'data'    => [
                'member_id' => $memberId,
                'phone'     => $phone,
                'amount'    => $amount,
                'reference' => $request->reference,
            ],
        ], 200);
    }
}
