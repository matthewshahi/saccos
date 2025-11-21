<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManualMpesaRecoveryController extends Controller
{
    public function showForm()
    {
        return view('mpesa.manual_recovery');
    }

    public function validateSms(Request $request)
    {
        $request->validate([
            'sms_message' => 'required|string'
        ]);

        $sms = $request->sms_message;

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

        $smsTime = Carbon::createFromFormat('d/m/y g:i A', $timeMatch[1] . ' ' . $timeMatch[2]);

        // ✅ BLOCK if already exists in C2B table
        if (DB::table('c2b_payments')->where('transaction_id', $receipt)->exists()) {
            return back()->withErrors("Transaction $receipt already exists in C2B records.");
        }

        $order = DB::table('stk_push_logs')->where('unique_number', $account)->first();

        if (!$order) {
            return back()->withErrors('No matching order found for this account reference.');
        }

        return view('mpesa.manual_preview', [
            'sms'       => $sms,
            'receipt'   => $receipt,
            'amount'    => $amount,
            'account'   => $account,
            'sms_time'  => $smsTime->format('Y-m-d H:i:s'),
            'phone'     => null,
            'posted_by' => auth()->user()->member_name ?? 'System User'
        ]);
    }

    public function processSms(Request $request)
    {
        $request->validate([
            'receipt'   => 'required',
            'account'   => 'required',
            'amount'    => 'required',
            'sms_time'  => 'required'
        ]);

        $order = DB::table('stk_push_logs')->where('unique_number', $request->account)->first();

        if (!$order) {
            return back()->withErrors('Order not found for this account.');
        }

        $adminName = auth()->user()->member_name ?? 'System User';
        $transactionTime = Carbon::parse($request->sms_time);

        DB::transaction(function () use ($request, $order, $adminName, $transactionTime) {

            // ✅ 1. Insert into stk_push_responses
            DB::table('stk_push_responses')->insert([
                'unique_number'        => $request->account,
                'checkout_request_id'  => $order->checkout_request_id,
                'mpesa_receipt_number' => $request->receipt,
                'amount'               => $request->amount,
                'phone_number'         => $request->phone,
                'result_code'          => 0,
                'result_description'   => 'Manual SMS Recovery by ' . $adminName,
                'transaction_date'     => $transactionTime,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            // ✅ 2. Update stk_push_logs
            DB::table('stk_push_logs')
                ->where('unique_number', $request->account)
                ->update([
                    'status' => 'completed',
                    'updated_at' => now()
                ]);

            // ✅ 3. INSERT INTO c2b_payments (CORRECT STRUCTURE)
            DB::table('c2b_payments')->insert([
                'transaction_type' => 'Pay Bill',
                'transaction_id' => $request->receipt,
                'transaction_time' => $transactionTime,
                'transaction_amount' => $request->amount,
                'business_shortcode' => $order->shortcode ?? '4185373',
                'bill_ref_number' => $request->account,
                'invoice_number' => null,
                'org_account_balance' => null,
                'third_party_transaction_id' => null,
                'msisdn' => $request->phone ?? 'N/A',
                'first_name' => 'Manual',
                'middle_name' => null,
                'last_name' => $adminName,
                'raw_payload' => json_encode([
                    'source' => 'manual_sms_recovery',
                    'sms' => $request->sms
                ]),
                'ip_address' => request()->ip(),
                'processed' => 'No',               
                'processed_date' => null,          
                'processed' => 'Yes',
                'processed_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        });

        return redirect()->route('mpesa.manual.form')
            ->with('success', '✅ Transaction successfully recovered and recorded in C2B ledger.');
    }
}
