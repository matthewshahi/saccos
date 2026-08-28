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
     * Loan Performance / Risk Classification Report
     */
    public function reportLoanPerformance(Request $request, $version = null)
    {
        /*
         * Use a report-specific variable name.
         *
         * This avoids collisions with any $currentPeriod variable
         * that may already be shared by layouts, middleware or
         * other application components.
         */
        $reportPeriod = date('Ym');

        $query = $this->getOutstandingLoansQuery(
            $this->ignoreLoanBalanceBelow,
            $version
        );

        /*
         * Server-side report search.
         */
        if ($request->filled('search')) {
            $query = $this->applySearchFilter(
                $query,
                $request->input('search')
            );
        }

        /*
         * Load all matching loans.
         *
         * One row per loan is preserved by the aggregated repayment
         * subquery inside getOutstandingLoansQuery().
         */
        $loans = $query->get();

        foreach ($loans as $loan) {

            /*
             * Determine the loan starting accounting period.
             *
             * Priority:
             * 1. loan_taken_start_period
             * 2. loan_taken_period
             */
            $loanStartPeriod = $loan->loan_taken_start_period
                ?: $loan->loan_taken_period;

            /*
             * Determine risk classification.
             */
            $loan->loan_category = $this->getLoanCategory(
                $loan->last_payment_period,
                $loanStartPeriod,
                $reportPeriod
            );

            /*
             * Human-readable member position.
             */
            $loan->position = ((int) $loan->member_position === 2)
                ? 'Official'
                : 'Member';
        }

        /*
         * Preserve AJAX response support.
         */
        if ($request->ajax()) {
            return response()->json($loans);
        }

        return view('reports.loans.loan_performance', [
            'loans'        => $loans,
            'reportPeriod' => $reportPeriod,
            'version'      => $version,
        ]);
    }

    /**
     * Build the base query for outstanding loans.
     *
     * Guarantees:
     * - One row per loan.
     * - No duplicate loan rows from repayments.
     * - Company is optional and cannot cause loans to disappear.
     * - Outstanding balance calculation is NULL-safe.
     */
    protected function getOutstandingLoansQuery(
        $ignoreLoanBalanceBelow,
        $version = null
    ) {
        /*
         * One repayment-summary row per loan.
         *
         * We deliberately aggregate here rather than joining directly
         * to sacco_loan_payments, because a loan may have multiple
         * repayment transactions in the same or different periods.
         */
        $latestPaymentSub = DB::table('sacco_loan_payments as p')
            ->selectRaw('
                p.loan_payments_loan_id as loan_id,
                MAX(p.loan_payments_period) as last_payment_period
            ')
            ->groupBy('p.loan_payments_loan_id');

        $query = DB::table('sacco_loans as l')

            /*
             * Loan member.
             */
            ->join(
                'sacco_members as m',
                'l.loan_member',
                '=',
                'm.member_id'
            )

            /*
             * Department.
             *
             * LEFT JOIN is intentional.
             *
             * Historical members may have missing or incomplete
             * organisation relationships. Their loans must still
             * remain visible in this report.
             */
            ->leftJoin(
                'sacco_department as d',
                'm.member_dept',
                '=',
                'd.department_id'
            )

            /*
             * Company / Institution.
             *
             * Relationship:
             *
             * sacco_members.member_dept
             *      -> sacco_department.department_id
             *      -> sacco_department.department_company_id
             *      -> sacco_company.company_id
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
             * Latest repayment period.
             *
             * One joined row only per loan.
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
                /*
                 * All loan fields.
                 */
                'l.*',

                /*
                 * Member fields required by report.
                 */
                'm.member_name',
                'm.member_sacco_id',
                'm.member_national_id',
                'm.member_phone_no',
                'm.member_position',

                /*
                 * Company / Institution.
                 */
                'c.company_name',

                /*
                 * Loan product.
                 */
                'lt.loan_type_name',

                /*
                 * Latest authoritative repayment period.
                 */
                'lp.last_payment_period',

                /*
                 * Consistent outstanding balance.
                 *
                 * This is also the same expression used by the
                 * outstanding-balance filter below.
                 */
                DB::raw(
                    '(l.loan_amount - IFNULL(l.loan_loan_paid, 0))
                    as outstanding_balance'
                )
            )

            /*
             * Preserve the existing report rule:
             * stopped loans are not included.
             */
            ->where(
                'l.loan_stoped',
                'N'
            )

            /*
             * Only loans above the configured minimum outstanding
             * balance should appear.
             */
            ->whereRaw(
                '(l.loan_amount - IFNULL(l.loan_loan_paid, 0)) > ?',
                [$ignoreLoanBalanceBelow]
            );

        /*
         * Preserve the existing version behavior.
         *
         * Version 2 = officials only.
         */
        if ((int) $version === 2) {
            $query->where(
                'm.member_position',
                2
            );
        }

        /*
         * Stable ordering.
         *
         * loan_id provides deterministic ordering where one member
         * has multiple loans.
         */
        return $query
            ->orderBy('m.member_name')
            ->orderBy('l.loan_id');
    }

    /**
     * Apply report search.
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
     * This deliberately preserves the current classification
     * methodology already used by the report.
     *
     * Priority:
     * latest repayment period -> loan start period.
     */
    protected function getLoanCategory(
        $lastPaymentPeriod,
        $loanStartPeriod,
        $reportPeriod
    ) {
        /*
         * Use the most recent repayment period where available.
         *
         * If no repayment exists, use the loan start period.
         */
        $period = $lastPaymentPeriod
            ?: $loanStartPeriod;

        /*
         * YYYYMM validation.
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

        /*
         * Additional month validation.
         *
         * preg_match alone would accept values such as 202613.
         */
        $year = (int) substr((string) $period, 0, 4);
        $month = (int) substr((string) $period, 4, 2);

        $reportYear = (int) substr((string) $reportPeriod, 0, 4);
        $reportMonth = (int) substr((string) $reportPeriod, 4, 2);

        if (
            $year < 1900
            || $month < 1
            || $month > 12
            || $reportYear < 1900
            || $reportMonth < 1
            || $reportMonth > 12
        ) {
            return [
                'category' => 'Loss',
                'class'    => 'bg-dark',
            ];
        }

        /*
         * Work directly with YYYYMM values.
         *
         * This is simpler and safer than relying on DateTime::diff()
         * for a month-only accounting period.
         */
        $periodIndex = ($year * 12) + $month;
        $reportIndex = ($reportYear * 12) + $reportMonth;

        /*
         * Do not allow future periods to create a misleading positive
         * aging interval.
         *
         * A future period is treated as 0 months old.
         */
        $months = max(
            0,
            $reportIndex - $periodIndex
        );

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