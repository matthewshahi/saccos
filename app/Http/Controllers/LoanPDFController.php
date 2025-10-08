<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Middleware\CheckUserRights;




class LoanPDFController extends Controller
{
    public function downloadLoanForm($loanId)
    {
        $userId = Auth::id();

        $userId = Auth::id();

    // 1️⃣ Check access rights first
    $hasRight = CheckUserRights::userHasRight('downloadloanpdf');

    // 2️⃣ Fetch minimal loan info just to verify ownership (lightweight and safe)
    $loan = DB::table('sacco_loan_batch_trans_members')
        ->select('batch_trans_id', 'batch_trans_member_id')
        ->where('batch_trans_id', $loanId)
        ->first();

    $isOwner = $loan && ($loan->batch_trans_member_id == $userId);

    // 3️⃣ Deny if neither ownership nor rights are granted
    if (!$hasRight && !$isOwner) {
        abort(403, 'Access denied — you do not have permission to download this loan PDF.');
    }


        // Fetch loan details
        $loan = DB::table('sacco_loan_batch_trans_members as trans')
            ->join('sacco_members as m', 'trans.batch_trans_member_id', '=', 'm.member_id')
            ->join('sacco_loan_types as t', 'trans.batch_trans_loan_type', '=', 't.loan_type_id')
            ->join('sacco_loan_category as c', 'trans.batch_trans_loan_category', '=', 'c.loan_category_id')
            ->select(
                'trans.*',
                'm.*',
                't.loan_type_name',
                'c.loan_category_name',
                DB::raw('trans.batch_trans_on as loan_created_at') // ✅ use your SACCO timestamp
            )
            ->where('trans.batch_trans_id', $loanId)
            ->first();

        if (!$loan) {
            abort(403, "Unauthorized: This loan does not belong to you.");
        }

        // Fetch guarantors
        $guarantors = DB::table('sacco_loan_batch_guarantors_members as g')
            ->join('sacco_members as m', 'g.guarantors_guarantor_id', '=', 'm.member_id')
            ->select(
                'g.*',
                'm.member_name',
                'm.member_sacco_id',
                'm.member_phone_no',
                DB::raw('g.guarantors_on as guarantor_created_at') // ✅ use guarantor timestamp
            )
            ->where('g.guarantors_loan_batch_trans_id', $loanId)
            ->get();

        // Sacco defaults (company name etc.)
        $companyName = DB::table('sacco_defaults')
            ->where('default_name', 'company_name')
            ->value('default_value') ?? 'SACCO Ltd';

        // PDF data
        $data = [
            'loan'        => $loan,
            'guarantors'  => $guarantors,
            'companyName' => $companyName,
            'today'       => Carbon::now()->format('d/m/Y'),
        ];

        // Load Blade template
        $pdf = Pdf::loadView('pdf.loan_form', $data)->setPaper('A4');

        return $pdf->download("LoanForm_{$loan->batch_trans_id}.pdf");
    }
}