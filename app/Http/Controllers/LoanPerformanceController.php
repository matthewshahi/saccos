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

        if ($request->filled('search')) {
            $query = $this->applySearchFilter($query, $request->input('search'));
        }

        /**
         * For very large datasets, consider pagination:
         * $loans = $query->paginate(200);
         * (and update the view accordingly)
         */
        $loans = $query->get();

        foreach ($loans as $loan) {

            // If loan_taken_start_period is null, use loan_taken_period
            $loanStartPeriod = $loan->loan_taken_start_period ?: $loan->loan_taken_period;

            $loan->loan_category = $this->getLoanCategory(
                $loan->last_payment_period,
                $loanStartPeriod,
                $currentPeriod
            );

            $loan->position = ($loan->member_position == 2) ? 'Official' : 'Member';
        }

        if ($request->ajax()) {
            return response()->json($loans);
        }

        return view('reports.loans.loan_performance', [
            'loans'         => $loans,
            'currentPeriod' => $currentPeriod,
            'version'       => $version,
        ]);
    }

    /**
     * Build base query for outstanding loans (NO DUPLICATES)
     *
     * Strategy:
     * - Join to a grouped subquery that returns ONE ROW PER LOAN:
     *      loan_id, last_payment_on = MAX(loan_payments_on)
     * - This guarantees no duplicates even if multiple payments exist on same date.
     */
    protected function getOutstandingLoansQuery($ignoreLoanBalanceBelow, $version = null)
    {
        // Subquery: one row per loan (loan_id -> last payment date)
        $latestPaymentSub = DB::table('sacco_loan_payments as p')
            ->selectRaw('p.loan_payments_loan_id as loan_id, MAX(p.loan_payments_on) as last_payment_on')
            ->groupBy('p.loan_payments_loan_id');

        $query = DB::table('sacco_loans as l')
            ->join('sacco_members as m', 'l.loan_member', '=', 'm.member_id')
            ->join('sacco_loan_types as lt', 'l.loan_loan_type', '=', 'lt.loan_type_id')

            // Join the derived table (1 row per loan)
            ->leftJoinSub($latestPaymentSub, 'lp', function ($join) {
                $join->on('l.loan_id', '=', 'lp.loan_id');
            })

            ->select(
                'l.*',
                'm.member_name',
                'm.member_sacco_id',
                'm.member_national_id',
                'm.member_phone_no',
                'm.member_position',
                'lt.loan_type_name',

                // Canonical last payment period (YYYYMM) derived from last_payment_on
                DB::raw("DATE_FORMAT(lp.last_payment_on, '%Y%m') as last_payment_period")
            )

            ->where('l.loan_stoped', 'N')

            // NULL-safe outstanding balance check
            ->whereRaw(
                '(l.loan_amount - IFNULL(l.loan_loan_paid, 0)) > ?',
                [$ignoreLoanBalanceBelow]
            );

        if ($version == 2) {
            $query->where('m.member_position', 2);
        }

        return $query->orderBy('m.member_name');
    }

    /**
     * Apply free-text search
     */
    protected function applySearchFilter($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('m.member_name', 'like', "%{$search}%")
              ->orWhere('m.member_phone_no', 'like', "%{$search}%")
              ->orWhere('m.member_national_id', 'like', "%{$search}%");
        });
    }

    /**
     * Loan aging classifier
     */
    protected function getLoanCategory($lastPaymentPeriod, $loanStartPeriod, $currentPeriod)
    {
        $period = $lastPaymentPeriod ?: $loanStartPeriod;

        // Guard against invalid or legacy values
        if (!$period || !preg_match('/^\d{6}$/', (string) $period)) {
            return ['category' => 'Loss', 'class' => 'bg-dark'];
        }

        $lastDate    = \DateTime::createFromFormat('Ym', $period);
        $currentDate = \DateTime::createFromFormat('Ym', $currentPeriod);

        if (!$lastDate || !$currentDate) {
            return ['category' => 'Loss', 'class' => 'bg-dark'];
        }

        $interval = $lastDate->diff($currentDate);
        $months   = ($interval->y * 12) + $interval->m;

        return match (true) {
            $months <= 2 => ['category' => 'Current',     'class' => 'bg-success'],
            $months <= 4 => ['category' => 'Watch',       'class' => 'bg-info'],
            $months <= 6 => ['category' => 'Substandard', 'class' => 'bg-warning'],
            $months <= 9 => ['category' => 'Doubtful',    'class' => 'bg-danger'],
            default      => ['category' => 'Loss',        'class' => 'bg-dark'],
        };
    }
}
