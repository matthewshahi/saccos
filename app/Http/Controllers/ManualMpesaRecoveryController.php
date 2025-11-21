<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ManualMpesaRecoveryController extends Controller
{
    /**
     * Show manual recovery form
     */
    public function showForm()
    {
        return view('mpesa.manual_recovery');
    }

    /**
     * Validate SMS and show preview
     */
    public function validateSms(Request $request)
    {
        $request->validate([
            'sms_message' => 'required|string'
        ]);

        $sms = $request->sms_message;

        // Extract required details from SMS
        preg_match('/\b([A-Z0-9]{10})\b/', $sms, $receiptMatch);
        preg_match('/Ksh([\d,\.]+)/i', $sms, $amountMatch);
        preg_match('/account\s+([A-Z0-9]+)/i', $sms, $accountMatch);
        preg_match('/(\d{2}\/\d{2}\/\d{2})\s+at\s+(\d{1,2}:\d{2}\s+(AM|PM))/i', $sms, $timeMatch);

        $receipt = $receiptMatch[1] ?? null;
        $amount  = isset($amountMatch[1]) ? floatval(str_replace(',', '', $amountMatch[1])) : null;
        $account = $accountMatch[1] ?? null;

        if (!$receipt || !$amount || !$account || empty($timeMatch)) {
            return back()->withErrors('Invalid or unsupported M-Pesa message format.');
        }

        // Parse SMS timestamp
        $smsTime = Carbon::createFromFormat('d/m/y g:i A', $timeMatch[1] . ' ' . $timeMatch[2]);

        /**
         * ✅ PRIMARY: Receipt must not already exist
         */
        if (DB::table('stk_push_responses')
            ->where('mpesa_receipt_number', $receipt)
            ->exists()) {
            return back()->withErrors('This receipt has already been used.');
        }

        /**
         * ✅ SECONDARY: Prevent similar duplicate within ±5 minutes
         */
        $windowStart = $smsTime->copy()->subMinutes(5);
        $windowEnd   = $smsTime->copy()->addMinutes(5);

        $duplicateNearTime = DB::table('stk_push_logs')
            ->where('unique_number', $account)
            ->where('amount', $amount)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->exists();

        if ($duplicateNearTime) {
            return back()->withErrors(
                "A similar transaction already exists for $account within 5 minutes of this time."
            );
        }

        /**
         * ✅ Ensure related STK order exists
         */
        $order = DB::table('stk_push_logs')
            ->where('unique_number', $account)
            ->first();

        if (!$order) {
            return back()->withErrors('No matching STK order found for this account reference.');
        }

        return view('mpesa.manual_preview', [
            'sms'       => $sms,
            'receipt'   => $receipt,
            'amount'    => $amount,
            'account'   => $account,
            'sms_time'  => $smsTime->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Final posting into system
     */
    public function processSms(Request $request)
    {
        $request->validate([
            'receipt'   => 'required',
            'account'   => 'required',
            'amount'    => 'required',
            'sms_time'  => 'required'
        ]);

        $order = DB::table('stk_push_logs')
            ->where('unique_number', $request->account)
            ->first();

        if (!$order) {
            return redirect()->back()->withErrors('Order not found for this account.');
        }

        $adminName = auth()->user()->name ?? 'System User';
        $transactionTime = Carbon::parse($request->sms_time);

        DB::transaction(function () use ($request, $order, $adminName, $transactionTime) {

            DB::table('stk_push_responses')->insert([
                'unique_number'        => $request->account,
                'checkout_request_id'  => $order->checkout_request_id,
                'mpesa_receipt_number' => $request->receipt,
                'amount'               => $request->amount,
                'phone_number'         => $request->phone ?? null,
                'result_code'          => 0,
                'result_description'   => 'Manual SMS Recovered by ' . $adminName,
                'transaction_date'     => $transactionTime,  // ✅ REAL SMS TIME
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            DB::table('stk_push_logs')
                ->where('unique_number', $request->account)
                ->update([
                    'status' => 'completed',
                    'updated_at' => now()
                ]);
        });

        return redirect()->route('mpesa.manual.form')
            ->with('success', 'Transaction successfully recovered and posted.');
    }
}
