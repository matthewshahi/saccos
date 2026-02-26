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

        // Junior context (for blade clarity / avoiding misreporting)
        $isViewingJunior = ($memberId !== $guardianId);
        $juniorContext = null;

        if ($isViewingJunior) {
            $juniorContext = [
                'member_id'       => $member->member_id,
                'member_name'     => $member->member_name,
                'member_sacco_id' => $member->member_sacco_id,
                'member_active'   => $member->member_active,
            ];
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
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_loans.loan_id',
                'sacco_loan_types.loan_type_name',
                'sacco_loans.loan_amount',
                'sacco_loans.loan_loan_paid',
                'sacco_loans.loan_taken_period',
                DB::raw('loan_amount - loan_loan_paid AS loan_balance')
            )
            ->where('loan_member', $memberId)
            ->whereRaw('loan_amount - loan_loan_paid > 1')
            ->orderBy('loan_taken_period', 'desc')
            ->limit(6)
            ->get();

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

            // Context for blade
            'effective_member_id' => $memberId,
            'guardian_member_id'  => $guardianId,
            'is_viewing_junior'   => $isViewingJunior,
            'junior'              => $juniorContext,
        ];

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

        $runningBalance = 0;
        foreach ($shares as $share) {
            $runningBalance += $share->share_amount_paying;
            $share->running_balance = $runningBalance;
        }

        $data = ['shares' => $shares];

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

        $runningBalance = 0;
        foreach ($capitalShares as $capital) {
            $runningBalance += $capital->share_capitalamount_paying;
            $capital->running_balance = $runningBalance;
        }

        $data = ['capitalShares' => $capitalShares];

        return view('members.capital_listings', compact('data'));
    }

    public function fosaListings()
    {
        $memberId = $this->resolveEffectiveMemberId();

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

        $fosaGrouped = $fosaContributions->groupBy(function ($row) {
            return $row->type_name ?: 'UNSPECIFIED';
        });

        foreach ($fosaGrouped as $type => $rows) {
            $running = 0;
            foreach ($rows as $r) {
                $running += $r->fosa_amount_paying;
                $r->running_balance = $running;
            }
        }

        return view('members.fosa_listings', [
            'data' => [
                'fosaGrouped' => $fosaGrouped,
            ],
        ]);
    }

    public function loansTaken()
    {
        $memberId = $this->resolveEffectiveMemberId();

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

        $repaymentsByLoan = $repayments->groupBy('loan_payments_loan_id');
        foreach ($loans as $loan) {
            $loan->repayments = $repaymentsByLoan[$loan->loan_id] ?? [];
            $runningBalance = $loan->loan_amount;

            foreach ($loan->repayments as $repayment) {
                $runningBalance -= $repayment->loan_payments_amount;
                $repayment->outstanding_balance = $runningBalance;
            }
        }

        $data = ['loans' => $loans];

        return view('members.loans_taken', compact('data'));
    }
}