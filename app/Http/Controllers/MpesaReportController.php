<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MpesaReportController extends Controller
{
    /**
     * Display a paginated list of STK push payments.
     */
    public function paymentsReceived(Request $request)
    {
        // Fetch paginated STK push payments with search functionality
        $query = DB::table('stk_push_responses')
            ->where('result_code', 0) // Only successful payments
            ->orderByDesc('transaction_date');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('unique_number', 'LIKE', "%{$search}%")
                    ->orWhere('checkout_request_id', 'LIKE', "%{$search}%")
                    ->orWhere('phone_number', 'LIKE', "%{$search}%")
                    ->orWhere('mpesa_receipt_number', 'LIKE', "%{$search}%");
            });
        }

        $stkPayments = $query->paginate(20);

        return view('reports.mpesa.stk_payments', compact('stkPayments'));
    }

    /**
     * Display a paginated list of C2B payments.
     */
    public function c2bPayments(Request $request)
    {
        // Fetch paginated C2B payments with search functionality
        $query = DB::table('c2b_payments')
            ->orderByDesc('transaction_time');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'LIKE', "%{$search}%")
                    ->orWhere('msisdn', 'LIKE', "%{$search}%")
                    ->orWhere('bill_ref_number', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%");
            });
        }

        $c2bPayments = $query->paginate(20);

        return view('reports.mpesa.c2b_payments', compact('c2bPayments'));
    }
}