<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanPerformanceController extends Controller
{
    protected $ignoreLoanBalanceBelow;

    public function __construct()
    {
        $this->ignoreLoanBalanceBelow = DB::table('sacco_defaults')
            ->where('default_name', 'min_loan_amount_bill_able')
            ->value('default_value') ?? 0;
    }

    public function reportLoanPerformance(Request $request, $version = null)
    {
        $currentPeriod = date('Ym');
        $ignoreLoanBalanceBelow = $this->ignoreLoanBalanceBelow;

        $query = $this->getOutstandingLoansQuery($ignoreLoanBalanceBelow, $version);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query = $this->applySearchFilter($query, $search);
        }

        $loans = $query->get();

        foreach ($loans as $loan) {
            $loan->loan_category = $this->getLoanCategory($loan->loan_payments_period, $loan->loan_taken_period, $currentPeriod);
            $loan->position = $loan->member_position == 2 ? 'Official' : 'Member';
        }

        if ($request->ajax()) {
            return response()->json($loans);
        }

        return view('reports.loans.loan_performance', [
            'loans' => $loans,
            'currentPeriod' => $currentPeriod,
            'version' => $version
        ]);
    }

    protected function getOutstandingLoansQuery($ignoreLoanBalanceBelow, $version = null)
    {
        $query = DB::table('sacco_loans')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->leftJoin('sacco_loan_payments', function ($join) {
                $join->on('sacco_loans.loan_id', '=', 'sacco_loan_payments.loan_payments_loan_id')
                     ->whereRaw('sacco_loan_payments.loan_payments_period = (
                         SELECT MAX(lp.loan_payments_period)
                         FROM sacco_loan_payments lp
                         WHERE lp.loan_payments_loan_id = sacco_loans.loan_id
                     )');
            })
            ->select(
                'sacco_loans.*',
                'sacco_members.member_name',
                'sacco_members.member_sacco_id',
                'sacco_members.member_national_id',
                'sacco_members.member_phone_no',
                'sacco_members.member_position',
                'sacco_loan_types.loan_type_name',
                'sacco_loan_payments.loan_payments_period'
            )
            ->where('sacco_loans.loan_stoped', 'N')
            ->where(DB::raw('sacco_loans.loan_amount - sacco_loans.loan_loan_paid'), '>', $ignoreLoanBalanceBelow);

        if ($version == 2) {
            $query->where('sacco_members.member_position', 2);
        }

        return $query->orderBy('sacco_members.member_name');
    }

    protected function applySearchFilter($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('sacco_members.member_name', 'like', "%{$search}%")
                ->orWhere('sacco_members.member_phone_no', 'like', "%{$search}%")
                ->orWhere('sacco_members.member_national_id', 'like', "%{$search}%");
        });
    }

    protected function getLoanCategory($loanPaymentsPeriod, $loanTakenPeriod, $currentPeriod)
    {
        $period = $loanPaymentsPeriod ?? $loanTakenPeriod;

        if (!$period) {
            return ['category' => 'Loss', 'class' => 'bg-dark'];
        }

        $lastPeriodDate = \DateTime::createFromFormat('Ym', $period);
        $currentDate = \DateTime::createFromFormat('Ym', $currentPeriod);

        $interval = $lastPeriodDate->diff($currentDate);
        $months = $interval->y * 12 + $interval->m;

        if ($months <= 2) {
            return ['category' => 'Current', 'class' => 'bg-success'];
        } elseif ($months <= 4) {
            return ['category' => 'Watch', 'class' => 'bg-info'];
        } elseif ($months <= 6) {
            return ['category' => 'Substandard', 'class' => 'bg-warning'];
        } elseif ($months <= 9) {
            return ['category' => 'Doubtful', 'class' => 'bg-danger'];
        } else {
            return ['category' => 'Loss', 'class' => 'bg-dark'];
        }
    }
}
