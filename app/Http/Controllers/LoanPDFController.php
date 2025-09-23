<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
 

class LoanPDFController extends Controller
{
    public function downloadLoanForm($loanId)
    {
        $userId = Auth::id();

        // Fetch loan details
        $loan = DB::table('sacco_loan_batch_trans_members as trans')
            ->join('sacco_members as m', 'trans.batch_trans_member_id', '=', 'm.member_id')
            ->join('sacco_loan_types as t', 'trans.batch_trans_loan_type', '=', 't.loan_type_id')
            ->join('sacco_loan_category as c', 'trans.batch_trans_loan_category', '=', 'c.loan_category_id')
            ->select('trans.*', 'm.*', 't.loan_type_name', 'c.loan_category_name')
            ->where('trans.batch_trans_id', $loanId)
            ->first();

        if (!$loan || $loan->member_id != $userId) {
            abort(403, "Unauthorized: This loan does not belong to you.");
        }

        // Fetch guarantors
        $guarantors = DB::table('sacco_loan_batch_guarantors_members as g')
            ->join('sacco_members as m', 'g.guarantors_guarantor_id', '=', 'm.member_id')
            ->select('g.*', 'm.member_name', 'm.member_sacco_id', 'm.member_phone_no')
            ->where('g.guarantors_loan_batch_trans_id', $loanId)
            ->get();

        // Sacco defaults (company name etc.)
        $companyName = DB::table('sacco_defaults')->where('default_name', 'company_name')->value('default_value') ?? 'SACCO Ltd';

        // PDF data
        $data = [
            'loan' => $loan,
            'guarantors' => $guarantors,
            'companyName' => $companyName,
            'today' => Carbon::now()->format('d/m/Y'),
        ];

        // Load Blade template
        $pdf = PDF::loadView('pdf.loan_form', $data)->setPaper('A4');

        return $pdf->download("LoanForm_{$loan->batch_trans_id}.pdf");
    }
}