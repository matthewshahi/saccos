<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $ref = trim($request->reference_number);

        // 1. Look up locally in known SACCO tables
        $share = DB::table('sacco_shares')->where('share_doc_no', $ref)->first();
        $capital = DB::table('sacco_capital_shares')->where('share_capitaldoc_no', $ref)->first();
        $fosa = DB::table('sacco_fosas')->where('fosa_doc_no', $ref)->first();

        $found = $share ?? $capital ?? $fosa;

        $status = 'Not Found';
        $response = null;
        $memberId = null;

        if ($found) {
            $status = 'Success';
            $response = json_encode($found);
            $memberId = $found->share_member_id ?? $found->share_capitalmember_id ?? $found->fosa_member_id;
        } else {
            // 2. Optionally query Safaricom’s Transaction Status API here
            // For now we simulate pending status
            $status = 'Pending';
            $response = 'Reference not found locally. Try again later.';
        }

        // 3. Save inquiry log
        DB::table('payment_inquiries')->insert([
            'reference_number' => $ref,
            'status' => $status,
            'response' => $response,
            'member_id' => $memberId,
            'checked_ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return view('payments.result', compact('ref', 'status', 'response'));
    }
}