<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemberDashboardController extends Controller
{
    /**
     * Resolve the "effective" member id for dashboard context.
     *
     * Default: logged-in member (guardian).
     * If ?view_as_member=y&jaccount=123 is present:
     *   - ONLY allow if jaccount is a junior account
     *   - AND it belongs to the logged-in guardian.
     * Otherwise fallback to logged-in member id.
     */
    private function resolveEffectiveMemberId(): int
    {
        $guardianId = (int) Auth::user()->id;

        if (request()->query('view_as_member') !== 'y') {
            return $guardianId;
        }

        $jaccount = request()->query('jaccount');
        if ($jaccount === null || $jaccount === '') {
            return $guardianId;
        }

        $juniorId = (int) $jaccount;

        $isValidJunior = DB::table('sacco_members')
            ->where('member_id', $juniorId)
            ->where('member_is_junior', 1)
            ->where('member_guardian_id', $guardianId)
            ->where('member_deleted', 'N')
            ->exists();

        return $isValidJunior ? $juniorId : $guardianId;
    }

    /**
     * Context payload for ALL blades (so you can mark "Viewing Junior Account" everywhere).
     */
    private function buildJuniorViewContext(int $effectiveMemberId): array
    {
        $guardianId = (int) Auth::user()->id;

        $isViewingJunior = ($effectiveMemberId !== $guardianId);
        $juniorContext = null;

        if ($isViewingJunior) {
            $junior = DB::table('sacco_members')
                ->select('member_id', 'member_name', 'member_sacco_id', 'member_active')
                ->where('member_id', $effectiveMemberId)
                ->where('member_is_junior', 1)
                ->where('member_guardian_id', $guardianId)
                ->where('member_deleted', 'N')
                ->first();

            if ($junior) {
                $juniorContext = [
                    'member_id'       => $junior->member_id,
                    'member_name'     => $junior->member_name,
                    'member_sacco_id' => $junior->member_sacco_id,
                    'member_active'   => $junior->member_active,
                ];
            } else {
                // Safety fallback: if something changes, do not treat as junior view.
                $isViewingJunior = false;
                $effectiveMemberId = $guardianId;
            }
        }

        return [
            'effective_member_id' => $effectiveMemberId,
            'guardian_member_id'  => $guardianId,
            'is_viewing_junior'   => $isViewingJunior,
            'junior'              => $juniorContext,
        ];
    }

    public function index()
    {
        $memberId   = $this->resolveEffectiveMemberId();
        $guardianId = (int) Auth::user()->id;

        // Fetch the effective member's data (guardian OR junior)
        $member = DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->where('member_active', 'Y')
            ->first();

        if (!$member) {
            abort(404, 'Member not found');
        }

        // Last 6 share payments (effective member)
        $shares = DB::table('sacco_shares')
            ->select('share_period', 'share_amount_paying')
            ->where('share_member_id', $memberId)
            ->orderBy('share_period', 'desc')
            ->orderBy('share_date_paid', 'desc')
            ->limit(6)
            ->get()
            ->reverse();

        $labels = $shares->pluck('share_period')->map(function ($period) {
            return substr($period, 0, 4) . '-' . substr($period, 4);
        });
        $amounts = $shares->pluck('share_amount_paying');

        // Pending loans (effective member)
        $pendingLoans = DB::table('sacco_loans')
            ->join(
                'sacco_loan_types',
                'sacco_loans.loan_loan_type',
                '=',
                'sacco_loan_types.loan_type_id'
            )
            ->select(
                'sacco_loans.loan_id',

                'sacco_loan_types.loan_type_name',
                'sacco_loan_types.loan_type_interest',
                'sacco_loan_types.loan_type_interest_type',

                'sacco_loans.loan_amount',
                'sacco_loans.loan_loan_paid',
                'sacco_loans.loan_taken_period',

                DB::raw(
                    '(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) AS loan_balance'
                )
            )
            ->where('sacco_loans.loan_member', $memberId)
            ->whereRaw(
                '(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > 1'
            )
            ->orderBy('sacco_loans.loan_taken_period', 'desc')
            ->limit(20)
            ->get();


        /*
|--------------------------------------------------------------------------
| Calculate current amount payable
|--------------------------------------------------------------------------
|
| This calculation is ONLY for display on the member dashboard.
|
| Nothing is posted to:
| - sacco_loans
| - sacco_loan_payments
| - ledger/accounts
|
|--------------------------------------------------------------------------
*/

        $currentPeriod = (int) now()->format('Ym');


        /*
|--------------------------------------------------------------------------
| Find loans whose interest has already been paid this period
|--------------------------------------------------------------------------
|
| There may be MULTIPLE repayments for one loan in the same period.
|
| If ANY repayment for the loan in YYYYMM has:
|
|     loan_payments_interest > 0
|
| then reducing-balance interest for this period has already been serviced.
|--------------------------------------------------------------------------
*/

        $loanIds = $pendingLoans
            ->pluck('loan_id')
            ->map(fn($id) => (int) $id)
            ->values();


        $loansWithInterestPaidThisPeriod = collect();

        if ($loanIds->isNotEmpty()) {

            $loansWithInterestPaidThisPeriod = DB::table(
                'sacco_loan_payments'
            )
                ->whereIn(
                    'loan_payments_loan_id',
                    $loanIds
                )
                ->where(
                    'loan_payments_period',
                    $currentPeriod
                )
                ->where(
                    'loan_payments_interest',
                    '>',
                    0
                )
                ->pluck(
                    'loan_payments_loan_id'
                )
                ->map(
                    fn($id) => (int) $id
                )
                ->unique()
                ->values();
        }


        /*
|--------------------------------------------------------------------------
| Attach display values to each loan
|--------------------------------------------------------------------------
*/

        foreach ($pendingLoans as $loan) {

            $principalBalance = max(
                0,
                (float) $loan->loan_amount
                    - (float) $loan->loan_loan_paid
            );

            $interestRate = (float) $loan->loan_type_interest;

            $interestType = strtoupper(
                trim(
                    (string) $loan->loan_type_interest_type
                )
            );

            $interestToPay = 0.0;


            /*
    |--------------------------------------------------------------------------
    | Fixed interest
    |--------------------------------------------------------------------------
    |
    | User-defined rule:
    |
    |     interest = principal balance × rate / 100
    |
    |--------------------------------------------------------------------------
    */
            if ($interestType === 'FIXED INTEREST') {

                $interestToPay =
                    $principalBalance
                    * $interestRate
                    / 100;
            }


            /*
    |--------------------------------------------------------------------------
    | Reducing balance
    |--------------------------------------------------------------------------
    |
    | Interest is charged once per YYYYMM period.
    |
    | If ANY repayment in the current period already contains interest,
    | current interest is zero.
    |
    | Otherwise:
    |
    |     balance × annual rate / 12 / 100
    |
    |--------------------------------------------------------------------------
    */ elseif ($interestType === 'REDUCING BALANCE') {

                $interestPaidThisPeriod =
                    $loansWithInterestPaidThisPeriod
                    ->contains(
                        (int) $loan->loan_id
                    );

                if (!$interestPaidThisPeriod) {

                    $interestToPay =
                        $principalBalance
                        * $interestRate
                        / 12
                        / 100;
                }
            }


            /*
    |--------------------------------------------------------------------------
    | Values used by the Blade ONLY
    |--------------------------------------------------------------------------
    */

            $loan->principal_balance =
                round(
                    $principalBalance,
                    2
                );

            $loan->interest_to_pay =
                round(
                    $interestToPay,
                    2
                );

            $loan->amount_to_pay =
                round(
                    $principalBalance
                        + $interestToPay,
                    2
                );
        }

        // Next of kin (effective member)
        $nextOfKin = DB::table('sacco_next_of_kin')
            ->join('sacco_kin_type', 'sacco_next_of_kin.kin_relationship', '=', 'sacco_kin_type.kin_type_id')
            ->where('kin_member_id', $memberId)
            ->where('kin_deleted', 'N')
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

        $operators = [];

        // Transport operators remain tied to the logged-in guardian (asset owner), not the junior
        if (config('sacco.transport_sacco') === 'Y') {
            $operators = DB::table('sacco_matatus_operators')
                ->leftJoin(
                    'sacco_matatus_operator_vehicle_assignments',
                    'sacco_matatus_operators.id',
                    '=',
                    'sacco_matatus_operator_vehicle_assignments.v_assignment_operator_id'
                )
                ->leftJoin(
                    'sacco_matatus_vehicles',
                    'sacco_matatus_operator_vehicle_assignments.v_assignment_vehicle_id',
                    '=',
                    'sacco_matatus_vehicles.id'
                )
                ->where('sacco_matatus_operators.status', 'active')
                ->where('sacco_matatus_vehicles.vehicles_member_id', $guardianId)
                ->select(
                    'sacco_matatus_operators.id AS operator_id',
                    'sacco_matatus_operators.full_name',
                    'sacco_matatus_operators.phone',
                    'sacco_matatus_operators.operator_type',
                    'sacco_matatus_operator_vehicle_assignments.v_assignment_start_date',
                    'sacco_matatus_operator_vehicle_assignments.v_assignment_end_date',
                    'sacco_matatus_vehicles.vehicles_registration_number AS vehicle_reg_no',
                    'sacco_matatus_vehicles.id AS vehicle_id'
                )
                ->orderBy('sacco_matatus_operators.full_name')
                ->get();
        }

        $data = [
            'member'         => $member,
            'pendingLoans'   => $pendingLoans,
            'nextOfKin'      => $nextOfKin,
            'labels'         => $labels,
            'amounts'        => $amounts,
            'paymentOptions' => $paymentOptions,
            'operators'      => $operators,
        ];

        // ✅ Always include junior context if available
        $data = array_merge($data, $this->buildJuniorViewContext($memberId));

        return view('dashboard.member_dashboard', compact('data'));
    }

    public function shareListings()
    {
        $memberId = $this->resolveEffectiveMemberId();

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
            ->where('share_member_id', $memberId)
            ->orderBy('share_period', 'asc')
            ->get();

        // Calculate running balance
        $runningBalance = 0;
        foreach ($shares as $share) {
            $runningBalance += $share->share_amount_paying;
            $share->running_balance = $runningBalance;
        }

        $data = ['shares' => $shares];

        // ✅ Always include junior context if available
        $data = array_merge($data, $this->buildJuniorViewContext($memberId));

        return view('members.share_listings', compact('data'));
    }

    public function capitalListings()
    {
        $memberId = $this->resolveEffectiveMemberId();

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
            ->where('share_capitalmember_id', $memberId)
            ->orderBy('share_capitalperiod', 'asc')
            ->get();

        // Calculate running balance
        $runningBalance = 0;
        foreach ($capitalShares as $capital) {
            $runningBalance += $capital->share_capitalamount_paying;
            $capital->running_balance = $runningBalance;
        }

        $data = ['capitalShares' => $capitalShares];

        // ✅ Always include junior context if available
        $data = array_merge($data, $this->buildJuniorViewContext($memberId));

        return view('members.capital_listings', compact('data'));
    }

    public function fosaListings()
    {
        $memberId = $this->resolveEffectiveMemberId();

        // Fetch contributions + join fosa types
        $fosaContributions = DB::table('sacco_fosas')
            ->leftJoin('sacco_fosa_types', 'sacco_fosas.fosa_type_id', '=', 'sacco_fosa_types.type_id')
            ->select(
                'sacco_fosas.*',
                'sacco_fosa_types.type_name',
                'sacco_fosa_types.type_prefix'
            )
            ->where('fosa_member_id', $memberId)
            ->orderBy('fosa_type_id')
            ->orderBy('fosa_period')
            ->orderBy('fosa_date_paid')
            ->get();

        // GROUPING BY TYPE
        $fosaGrouped = $fosaContributions->groupBy(function ($row) {
            return $row->type_name ?: 'UNSPECIFIED';
        });

        // Running balance PER GROUP
        foreach ($fosaGrouped as $type => $rows) {
            $running = 0;
            foreach ($rows as $r) {
                $running += $r->fosa_amount_paying;
                $r->running_balance = $running;
            }
        }

        $data = [
            'fosaGrouped' => $fosaGrouped,
        ];

        // ✅ Always include junior context if available
        $data = array_merge($data, $this->buildJuniorViewContext($memberId));

        return view('members.fosa_listings', ['data' => $data]);
    }

    public function loansTaken()
    {
        $memberId = $this->resolveEffectiveMemberId();

        // Fetch loans for the effective member
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
                DB::raw('loan_amount - loan_loan_paid AS loan_balance')
            )
            ->where('loan_member', $memberId)
            ->orderBy('loan_taken_period', 'desc')
            ->get();

        // Fetch loan repayments for those loans
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

        // Group repayments by loan ID and calculate balances
        $repaymentsByLoan = $repayments->groupBy('loan_payments_loan_id');
        foreach ($loans as $loan) {
            $loan->repayments =
                $repaymentsByLoan->get(
                    $loan->loan_id,
                    collect()
                );
            $runningBalance = $loan->loan_amount;

            foreach ($loan->repayments as $repayment) {
                $runningBalance -= $repayment->loan_payments_amount;
                $repayment->outstanding_balance = $runningBalance;
            }
        }

        $data = ['loans' => $loans];

        // ✅ Always include junior context if available
        $data = array_merge($data, $this->buildJuniorViewContext($memberId));

        return view('members.loans_taken', compact('data'));
    }

    public function specialSavingsListings()
    {
        /*
    |--------------------------------------------------------------------------
    | Resolve effective member
    |--------------------------------------------------------------------------
    | This automatically respects:
    |
    | - normal logged-in member
    | - official using ?view_as_member=y
    | - valid junior account using ?jaccount=ID
    |
    | The existing resolveEffectiveMemberId() performs the ownership check.
    |--------------------------------------------------------------------------
    */
        $memberId = $this->resolveEffectiveMemberId();

        /*
    |--------------------------------------------------------------------------
    | Statement period
    |--------------------------------------------------------------------------
    */
        $periodFrom = trim((string) request()->query(
            'period_from',
            '000000'
        ));

        $periodTo = trim((string) request()->query(
            'period_to',
            '999999'
        ));

        /*
     * Only allow YYYYMM-style numeric periods.
     */
        if (!preg_match('/^\d{6}$/', $periodFrom)) {
            $periodFrom = '000000';
        }

        if (!preg_match('/^\d{6}$/', $periodTo)) {
            $periodTo = '999999';
        }

        /*
     * Do not allow an inverted period range.
     */
        if ((int) $periodFrom > (int) $periodTo) {
            [$periodFrom, $periodTo] = [
                $periodTo,
                $periodFrom,
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | Member
    |--------------------------------------------------------------------------
    */
        $member = DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->where('member_deleted', 'N')
            ->first();

        if (!$member) {
            abort(404, 'Member not found');
        }

        /*
    |--------------------------------------------------------------------------
    | Member Special Savings Accounts
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We do NOT require the product to still be Active.
    |
    | Historical/inactive products must continue to appear where the member
    | owns a valid, non-deleted account.
    |--------------------------------------------------------------------------
    */
        $accounts = DB::table(
            'sacco_special_saving_accounts as a'
        )
            ->leftJoin(
                'sacco_special_saving_products as p',
                'p.special_saving_product_id',
                '=',
                'a.special_saving_account_product_id'
            )
            ->where(
                'a.special_saving_account_member_id',
                $memberId
            )
            ->where(
                'a.special_saving_account_deleted',
                'N'
            )
            ->select(
                'a.*',

                'p.special_saving_product_name',
                'p.special_saving_product_code',
                'p.special_saving_product_description'
            )
            ->orderByRaw(
                'p.special_saving_product_name IS NULL ASC'
            )
            ->orderBy(
                'p.special_saving_product_name'
            )
            ->orderBy(
                'a.special_saving_account_number'
            )
            ->get();

        /*
    |--------------------------------------------------------------------------
    | Build Member Special Savings Statements
    |--------------------------------------------------------------------------
    |
    | We retain the Special Savings accounting rules:
    |
    | 1. Deleted transactions are excluded.
    | 2. Reversed transactions are excluded.
    | 3. Opening balance comes from the LAST valid transaction before
    |    period_from.
    | 4. Transactions within the selected period use their stored
    |    *_balance_after snapshots.
    | 5. Historical closing balance comes from the final valid transaction
    |    within the selected period — NOT today's account balance.
    |--------------------------------------------------------------------------
    */
        $specialSavings = $accounts->map(
            function ($account) use (
                $periodFrom,
                $periodTo
            ) {

                /*
            |--------------------------------------------------------------------------
            | Opening transaction
            |--------------------------------------------------------------------------
            */
                $openingTxn = DB::table(
                    'sacco_special_saving_transactions'
                )
                    ->where(
                        'special_saving_transaction_account_id',
                        $account->special_saving_account_id
                    )
                    ->where(
                        'special_saving_transaction_deleted',
                        'N'
                    )
                    ->where(function ($query) {
                        $query
                            ->where(
                                'special_saving_transaction_reversed',
                                'N'
                            )
                            ->orWhereNull(
                                'special_saving_transaction_reversed'
                            );
                    })
                    ->where(
                        'special_saving_transaction_period',
                        '<',
                        $periodFrom
                    )
                    ->orderByDesc(
                        'special_saving_transaction_period'
                    )
                    ->orderByDesc(
                        'special_saving_transaction_date'
                    )
                    ->orderByDesc(
                        'special_saving_transaction_id'
                    )
                    ->first();

                /*
            |--------------------------------------------------------------------------
            | Transactions in selected period
            |--------------------------------------------------------------------------
            */
                $transactions = DB::table(
                    'sacco_special_saving_transactions'
                )
                    ->where(
                        'special_saving_transaction_account_id',
                        $account->special_saving_account_id
                    )
                    ->where(
                        'special_saving_transaction_deleted',
                        'N'
                    )
                    ->where(function ($query) {
                        $query
                            ->where(
                                'special_saving_transaction_reversed',
                                'N'
                            )
                            ->orWhereNull(
                                'special_saving_transaction_reversed'
                            );
                    })
                    ->whereBetween(
                        'special_saving_transaction_period',
                        [
                            $periodFrom,
                            $periodTo,
                        ]
                    )
                    ->orderBy(
                        'special_saving_transaction_period'
                    )
                    ->orderBy(
                        'special_saving_transaction_date'
                    )
                    ->orderBy(
                        'special_saving_transaction_id'
                    )
                    ->get();

                /*
            |--------------------------------------------------------------------------
            | Opening balances
            |--------------------------------------------------------------------------
            */
                $openingPrincipal = $openingTxn
                    ? (float) $openingTxn
                        ->special_saving_transaction_principal_balance_after
                    : 0.0;

                $openingAccruedInterest = $openingTxn
                    ? (float) $openingTxn
                        ->special_saving_transaction_accrued_interest_after
                    : 0.0;

                $openingAvailableInterest = $openingTxn
                    ? (float) $openingTxn
                        ->special_saving_transaction_available_interest_after
                    : 0.0;

                $openingTotal = $openingTxn
                    ? (float) $openingTxn
                        ->special_saving_transaction_total_balance_after
                    : 0.0;

                /*
            |--------------------------------------------------------------------------
            | Historical closing balances
            |--------------------------------------------------------------------------
            |
            | Do NOT use special_saving_account_total_balance for a historical
            | report. That is today's/current account position.
            |
            | Instead use the balance-after snapshot from the last transaction
            | inside the requested period.
            |--------------------------------------------------------------------------
            */
                $closingTxn = $transactions->last();

                if ($closingTxn) {
                    $closingPrincipal = (float) $closingTxn
                        ->special_saving_transaction_principal_balance_after;

                    $closingAccruedInterest = (float) $closingTxn
                        ->special_saving_transaction_accrued_interest_after;

                    $closingAvailableInterest = (float) $closingTxn
                        ->special_saving_transaction_available_interest_after;

                    $closingTotal = (float) $closingTxn
                        ->special_saving_transaction_total_balance_after;
                } else {
                    /*
                 * No movement during the requested period.
                 * Closing position therefore equals opening position.
                 */
                    $closingPrincipal = $openingPrincipal;
                    $closingAccruedInterest = $openingAccruedInterest;
                    $closingAvailableInterest = $openingAvailableInterest;
                    $closingTotal = $openingTotal;
                }

                return (object) [
                    'account' => $account,

                    'opening_principal' =>
                    $openingPrincipal,

                    'opening_accrued_interest' =>
                    $openingAccruedInterest,

                    'opening_available_interest' =>
                    $openingAvailableInterest,

                    'opening_total' =>
                    $openingTotal,

                    'closing_principal' =>
                    $closingPrincipal,

                    'closing_accrued_interest' =>
                    $closingAccruedInterest,

                    'closing_available_interest' =>
                    $closingAvailableInterest,

                    'closing_total' =>
                    $closingTotal,

                    'transactions' =>
                    $transactions,
                ];
            }
        )->values();

        /*
    |--------------------------------------------------------------------------
    | Blade payload
    |--------------------------------------------------------------------------
    */
        $data = [
            'member' => $member,

            'specialSavings' => $specialSavings,

            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ];

        /*
     * Preserve the existing secure junior-account context.
     */
        $data = array_merge(
            $data,
            $this->buildJuniorViewContext($memberId)
        );

        return view(
            'members.special_savings_listings',
            compact('data')
        );
    }
}
