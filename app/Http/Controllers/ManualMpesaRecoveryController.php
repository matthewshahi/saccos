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

        // Extract values using regex
        preg_match('/([A-Z0-9]{10})/i', $sms, $receiptMatch);
        preg_match('/Ksh([\d,\.]+)/i', $sms, $amountMatch);
        preg_match('/account\s+([A-Z0-9]+)/i', $sms, $accountMatch);
        preg_match('/2547\d{8}/', $sms, $phoneMatch);

        $receipt = $receiptMatch[1] ?? null;
        $amount = isset($amountMatch[1]) ? floatval(str_replace(',', '', $amountMatch[1])) : null;
        $account = $accountMatch[1] ?? null;
        $phone = $phoneMatch[0] ?? null;

        if (!$receipt || !$amount || !$account) {
            return back()->withErrors('Invalid or unsupported M-Pesa message format.');
        }

        // Check if order exists
        $order = DB::table('stk_push_logs')
            ->where('unique_number', $account)
            ->first();

        if (!$order) {
            return back()->withErrors('No matching STK order found for this account reference.');
        }

        if ($order->status !== 'pending') {
            return back()->withErrors('This order has already been processed.');
        }

        // Check duplicate receipt
        $exists = DB::table('stk_push_responses')
            ->where('mpesa_receipt_number', $receipt)
            ->exists();

        if ($exists) {
            return back()->withErrors('This transaction already exists in the system.');
        }

        if ((float)$order->amount != (float)$amount) {
            return back()->withErrors('Amount mismatch between order and SMS.');
        }

        return view('mpesa.manual_preview', compact(
            'sms', 'receipt', 'amount', 'account', 'phone'
        ));
    }

    /**
     * Final posting into system
     */
    public function processSms(Request $request)
    {
        $request->validate([
            'receipt' => 'required',
            'account' => 'required',
            'amount' => 'required'
        ]);

        $order = DB::table('stk_push_logs')
            ->where('unique_number', $request->account)
            ->first();

        DB::transaction(function () use ($request, $order) {

            DB::table('stk_push_responses')->insert([
                'unique_number' => $request->account,
                'checkout_request_id' => $order->checkout_request_id,
                'mpesa_receipt_number' => $request->receipt,
                'amount' => $request->amount,
                'phone_number' => $request->phone,
                'result_code' => 0,
                'result_description' => 'Manual SMS Recovered',
                'transaction_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
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
