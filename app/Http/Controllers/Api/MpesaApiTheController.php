<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class MpesaApiTheController extends Controller
{
    /**
     * Receive STK push initiation request from mobile app
     * (LOG ONLY – no processing yet)
     */
    public function initiateStkPushFromApp(Request $request)
    {
        // Log the authenticated user (member)
        Log::info('STK PUSH INITIATION (APP)', [
            'user_id'   => optional($request->user())->id,
            'user'      => $request->user(),
        ]);

        // Log the raw request payload
        Log::info('STK PUSH REQUEST PAYLOAD', [
            'payload' => $request->all(),
        ]);

        // Return a clean JSON response to the app
        return response()->json([
            'status'  => 'received',
            'message' => 'STK push request received and logged successfully',
        ], 200);
    }
}
