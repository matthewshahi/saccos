<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemberDashboardController extends Controller
{
    public function index()
    {
        // Fetch the logged-in member's data
        $member = DB::table('sacco_members')
            
            ->where('member_id', Auth::user()->id)
            ->where('member_active', 'Y')
            ->first();

        // Check if the member data exists
        if (!$member) {
            abort(404, 'Member not found');
        }

        // Fetch the last 6 share payments for the logged-in member
        $shares = DB::table('sacco_shares')
            ->select('share_period', 'share_amount_paying')
            ->where('share_member_id', Auth::user()->id)
            ->orderBy('share_period', 'desc')
            ->orderBy('share_date_paid', 'desc')
            ->limit(6)
            ->get()
            ->reverse(); // Reverse the order to display oldest to latest

        // Extract periods and amounts for the chart
        $labels = $shares->pluck('share_period')->map(function ($period) {
            return substr($period, 0, 4) . '-' . substr($period, 4); // Format YYYYMM as YYYY-MM
        });
        $amounts = $shares->pluck('share_amount_paying');

        $pendingLoans = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_loans.loan_id', 
                'sacco_loan_types.loan_type_name', // Fetch the loan type name
                'sacco_loans.loan_amount',
                'sacco_loans.loan_loan_paid',
                'sacco_loans.loan_taken_period',
                DB::raw('loan_amount - loan_loan_paid AS loan_balance') // Calculate loan balance
            )
            ->where('loan_member', Auth::user()->id)
            ->whereRaw('loan_amount - loan_loan_paid > 1') // Exclude fully paid loans
            ->orderBy('loan_taken_period', 'desc')
            ->limit(6)
            ->get();

        // Fetch the next of kin for the logged-in member
        $nextOfKin = DB::table('sacco_next_of_kin')
            ->join('sacco_kin_type', 'sacco_next_of_kin.kin_relationship', '=', 'sacco_kin_type.kin_type_id')
            ->where('kin_member_id', Auth::user()->id)
            ->where('kin_deleted', 'N') // Ensure only active records
            ->select(
                'sacco_next_of_kin.kin_names',
                'sacco_next_of_kin.kin_address',
                'sacco_next_of_kin.kin_national_id',
                'sacco_next_of_kin.kin_percent',
                'sacco_kin_type.kin_type_name as kin_relationship'
            )
            ->get();

       // Dynamic FOSA types (active, alphabetical)
    $paymentOptions = DB::table('sacco_fosa_types')
        ->where('type_active', 'Y')
        ->orderBy('type_prefix')
        ->get();

    // Package all data for the view
    $data = [
        'member'        => $member,
        'pendingLoans'  => $pendingLoans,
        'nextOfKin'     => $nextOfKin,
        'labels'        => $labels,
        'amounts'       => $amounts,
        'paymentOptions'=> $paymentOptions,
    ];
      

        return view('dashboard.member_dashboard', compact('data'));
    }
    public function shareListings()
        {
            $shares = DB::table('sacco_shares')
                ->select(
                    'share_id',
                    'share_amount_paying',
                    'share_paid_by',
                    'share_period',
                    'share_description',
                    'share_doc_no',
                    'share_date_paid'
                )
                ->where('share_member_id', Auth::user()->id)
                ->orderBy('share_period', 'asc')
                ->get();

            // Calculate running balance
            $runningBalance = 0;
            foreach ($shares as $share) {
                $runningBalance += $share->share_amount_paying;
                $share->running_balance = $runningBalance; // Add running balance to each record
            }

            $data = [
                'shares' => $shares,
            ];

            return view('members.share_listings', compact('data'));
        }
        public function capitalListings()
        {
            $capitalShares = DB::table('sacco_capital_shares')
                ->select(
                    'share_capitalid',
                    'share_capitalamount_paying',
                    'share_capitalpaid_by',
                    'share_capitalperiod',
                    'share_capitaldescription',
                    'share_capitaldoc_no',
                    'share_capitaldate_paid'
                )
                ->where('share_capitalmember_id', Auth::user()->id)
                ->orderBy('share_capitalperiod', 'asc')
                ->get();
        
            // Calculate running balance
            $runningBalance = 0;
            foreach ($capitalShares as $capital) {
                $runningBalance += $capital->share_capitalamount_paying;
                $capital->running_balance = $runningBalance; // Add running balance to each record
            }
        
            $data = [
                'capitalShares' => $capitalShares,
            ];
        
            return view('members.capital_listings', compact('data'));
        }
        public function fosaListings()
        {
            $fosaContributions = DB::table('sacco_fosas')
                ->select(
                    'fosa_id',
                    'fosa_amount_paying',
                    'fosa_paid_by',
                    'fosa_period',
                    'fosa_description',
                    'fosa_doc_no',
                    'fosa_date_paid'
                )
                ->where('fosa_member_id', Auth::user()->id)
                ->orderBy('fosa_period', 'asc')
                ->get();
        
            // Calculate running balance
            $runningBalance = 0;
            foreach ($fosaContributions as $fosa) {
                $runningBalance += $fosa->fosa_amount_paying;
                $fosa->running_balance = $runningBalance; // Add running balance to each record
            }
        
            $data = [
                'fosaContributions' => $fosaContributions,
            ];
        
            return view('members.fosa_listings', compact('data'));
        }

        public function loansTaken()
        {
            // Fetch outstanding loans for the logged-in member
            $loans = DB::table('sacco_loans')
                ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
                ->join('sacco_loan_category', 'sacco_loans.loan_loan_category', '=', 'sacco_loan_category.loan_category_id')
                ->select(
                    'sacco_loans.loan_id',
                    'sacco_loan_types.loan_type_name',
                    'sacco_loan_category.loan_category_name',
                    'sacco_loans.loan_amount',
                    'sacco_loans.loan_commision',
                    'sacco_loans.loan_insurance',
                    'sacco_loans.loan_loan_paid',
                    'sacco_loans.loan_taken_period',
                    'sacco_loans.loan_doc_no',
                    'sacco_loans.loan_description',
                    DB::raw('loan_amount - loan_loan_paid AS loan_balance') // Calculate loan balance
                )
                ->where('loan_member', Auth::user()->id)
                // ->whereRaw('loan_amount - loan_loan_paid > 1') // Only outstanding loans
                ->orderBy('loan_taken_period', 'desc')
                ->get();
        
            // Fetch loan repayments for each loan
            $repayments = DB::table('sacco_loan_payments')
                ->whereIn('loan_payments_loan_id', $loans->pluck('loan_id'))
                ->select(
                    'loan_payments_loan_id',
                    'loan_payments_amount',
                    'loan_payments_description',
                    'loan_payments_docno',
                    'loan_payments_period',
                    'loan_payments_paid_on',
                    'loan_payments_interest'
                )
                ->orderBy('loan_payments_period', 'asc')
                ->get();
        
            // Group repayments by loan ID and dynamically calculate balances
            $repaymentsByLoan = $repayments->groupBy('loan_payments_loan_id');
            foreach ($loans as $loan) {
                $loan->repayments = $repaymentsByLoan[$loan->loan_id] ?? [];
                $runningBalance = $loan->loan_amount; // Start with the loan amount
        
                foreach ($loan->repayments as $repayment) {
                    // Subtract principal immediately from the initial balance
                    $runningBalance -= $repayment->loan_payments_amount;
                    $repayment->outstanding_balance = $runningBalance; // Set the current balance
                }
            }
        
            // Prepare data for the view
            $data = [
                'loans' => $loans,
            ];
        
            return view('members.loans_taken', compact('data'));
        }

}