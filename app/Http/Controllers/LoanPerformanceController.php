<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanPerformanceController extends Controller
{
    protected $ignoreLoanBalanceBelow;

    public function __construct()
    {
        $this->ignoreLoanBalanceBelow = (float) (
            DB::table('sacco_defaults')
                ->where('default_name', 'min_loan_amount_bill_able')
                ->value('default_value') ?? 0
        );
    }

    /**
     * Loan Performance / Risk Classification report.
     */
    public function reportLoanPerformance(Request $request, $version = null)
    {
        $currentPeriod = date('Ym');

        $query = $this->getOutstandingLoansQuery(
            $this->ignoreLoanBalanceBelow,
            $version
        );

        if ($request->filled('search')) {
            $query = $this->applySearchFilter(
                $query,
                $request->input('search')
            );
        }

        /*
         * Keep all matching loans in the report because DataTables handles
         * the on-screen ordering/searching/export from this result set.
         *
         * Important:
         * getOutstandingLoansQuery() still guarantees one row per loan.
         */
        $loans = $query->get();

        foreach ($loans as $loan) {
            /*
             * Loan start-period fallback:
             *
             * 1. loan_taken_start_period
             * 2. loan_taken_period
             */
            $loanStartPeriod = $loan->loan_taken_start_period
                ?: $loan->loan_taken_period;

            /*
             * Classification:
             *
             * 1. latest repayment period
             * 2. loan start period
             */
            $loan->loan_category = $this->getLoanCategory(
                $loan->last_payment_period,
                $loanStartPeriod,
                $currentPeriod
            );

            $loan->position = ((int) $loan->member_position === 2)
                ? 'Official'
                : 'Member';
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
     * Build the base outstanding-loans query.
     *
     * Important guarantees:
     * - One row per loan.
     * - Latest repayment is obtained from an aggregated subquery.
     * - Company/department are LEFT JOINed so historical loans are not
     *   removed merely because member institution data is incomplete.
     * - Outstanding balance calculation remains NULL-safe.
     */
    protected function getOutstandingLoansQuery(
        $ignoreLoanBalanceBelow,
        $version = null
    ) {
        /*
         * One row per loan containing only its latest repayment period.
         *
         * This avoids the duplicate-loan problem that can occur when a loan
         * has multiple repayment records within the same accounting period.
         */
        $latestPaymentSub = DB::table('sacco_loan_payments as p')
            ->selectRaw('
                p.loan_payments_loan_id as loan_id,
                MAX(p.loan_payments_period) as last_payment_period
            ')
            ->groupBy('p.loan_payments_loan_id');

        $query = DB::table('sacco_loans as l')

            /*
             * Member owning the loan.
             */
            ->join(
                'sacco_members as m',
                'l.loan_member',
                '=',
                'm.member_id'
            )

            /*
             * Member department.
             *
             * LEFT JOIN is deliberate:
             * older members with incomplete department records must still
             * appear in this loan report.
             */
            ->leftJoin(
                'sacco_department as d',
                'm.member_dept',
                '=',
                'd.department_id'
            )

            /*
             * Company / institution.
             *
             * This follows:
             *
             * member_dept
             *      -> department_id
             *      -> department_company_id
             *      -> company_id
             */
            ->leftJoin(
                'sacco_company as c',
                'd.department_company_id',
                '=',
                'c.company_id'
            )

            /*
             * Loan product.
             */
            ->join(
                'sacco_loan_types as lt',
                'l.loan_loan_type',
                '=',
                'lt.loan_type_id'
            )

            /*
             * Exactly one aggregated repayment row per loan.
             */
            ->leftJoinSub(
                $latestPaymentSub,
                'lp',
                function ($join) {
                    $join->on(
                        'l.loan_id',
                        '=',
                        'lp.loan_id'
                    );
                }
            )

            ->select(
                'l.*',

                'm.member_name',
                'm.member_sacco_id',
                'm.member_national_id',
                'm.member_phone_no',
                'm.member_position',

                /*
                 * Company / institution.
                 */
                'c.company_name',

                /*
                 * Loan product.
                 */
                'lt.loan_type_name',

                /*
                 * Latest authoritative repayment accounting period.
                 */
                'lp.last_payment_period',

                /*
                 * Calculate the balance once and use exactly the same
                 * expression throughout the report.
                 */
                DB::raw(
                    '(l.loan_amount - IFNULL(l.loan_loan_paid, 0))
                    as outstanding_balance'
                )
            )

            /*
             * Existing report behaviour:
             * only non-stopped loans.
             */
            ->where(
                'l.loan_stoped',
                'N'
            )

            /*
             * Existing minimum outstanding balance threshold.
             */
            ->whereRaw(
                '(l.loan_amount - IFNULL(l.loan_loan_paid, 0)) > ?',
                [$ignoreLoanBalanceBelow]
            );

        /*
         * Preserve the existing version behaviour exactly as it currently is.
         */
        if ((int) $version === 2) {
            $query->where(
                'm.member_position',
                2
            );
        }

        return $query
            ->orderBy('m.member_name')
            ->orderBy('l.loan_id');
    }

    /**
     * Apply free-text search.
     *
     * Company has now been added to the search without changing the existing
     * member search behaviour.
     */
    protected function applySearchFilter($query, $search)
    {
        $search = trim((string) $search);

        return $query->where(function ($q) use ($search) {
            $q->where(
                'm.member_name',
                'like',
                "%{$search}%"
            )
            ->orWhere(
                'm.member_sacco_id',
                'like',
                "%{$search}%"
            )
            ->orWhere(
                'm.member_phone_no',
                'like',
                "%{$search}%"
            )
            ->orWhere(
                'm.member_national_id',
                'like',
                "%{$search}%"
            )
            ->orWhere(
                'c.company_name',
                'like',
                "%{$search}%"
            );
        });
    }

    /**
     * Loan aging classifier.
     *
     * Period based (YYYYMM), preserving the existing classification rules.
     */
    protected function getLoanCategory(
        $lastPaymentPeriod,
        $loanStartPeriod,
        $currentPeriod
    ) {
        /*
         * Priority:
         *
         * latest repayment period
         *          ↓
         * loan start period
         */
        $period = $lastPaymentPeriod ?: $loanStartPeriod;

        /*
         * Must be exactly YYYYMM.
         */
        if (
            !$period
            || !preg_match('/^\d{6}$/', (string) $period)
        ) {
            return [
                'category' => 'Loss',
                'class'    => 'bg-dark',
            ];
        }

        $lastDate = \DateTime::createFromFormat(
            'Ym',
            (string) $period
        );

        $currentDate = \DateTime::createFromFormat(
            'Ym',
            (string) $currentPeriod
        );

        if (!$lastDate || !$currentDate) {
            return [
                'category' => 'Loss',
                'class'    => 'bg-dark',
            ];
        }

        $interval = $lastDate->diff($currentDate);

        $months = ($interval->y * 12)
            + $interval->m;

        return match (true) {
            $months <= 2 => [
                'category' => 'Current',
                'class'    => 'bg-success',
            ],

            $months <= 4 => [
                'category' => 'Watch',
                'class'    => 'bg-info',
            ],

            $months <= 6 => [
                'category' => 'Substandard',
                'class'    => 'bg-warning',
            ],

            $months <= 9 => [
                'category' => 'Doubtful',
                'class'    => 'bg-danger',
            ],

            default => [
                'category' => 'Loss',
                'class'    => 'bg-dark',
            ],
        };
    }
}