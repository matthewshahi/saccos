<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Route;


class HomeController extends Controller
{
    protected $recordLimit;
    protected $minAge;
    protected $minimumLoanThreshold;
    protected $currentPeriod;
    protected $IgnoreLoanBalanceBelow;
    protected $default_company_name;

    public function __construct()
    {
        // dd(Route::currentRouteName());
        // $this->middleware('auth');
        $this->recordLimit = 3000;
        $this->minAge = 18; // Minimum age to join
        $this->minimumLoanThreshold = 1; // Minimum loan threshold
        $this->currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $this->IgnoreLoanBalanceBelow = DB::table('sacco_defaults')
            ->where('default_name', 'min_loan_amount_bill_able')
            ->value('default_value') ?? 0;
    }

    public function redirectBasedOnAuth()
    {

        if (auth()->check()) {
            $user = auth()->user();


            if ($user->member_position == 2) {
                return redirect('/dashboard');
            } elseif ($user->member_position == 1) {
                return redirect('/dashboard/member_dashboard');
            } else {
                return redirect('/register');
            }
        }

        return redirect('/register');
        // return view('home'); 
    }

    public function member_statement_self()
    {
        dd("56789 what need sto go here");
    }


    // public function redirectBasedOnAuth()
    // {
    //     if (auth()->check()) {
    //         dd(auth()->user()->member_position);
    //         return redirect()->route('dashboard');
    //     }

    //     return view('home'); 
    // }




    public function index()
    {
        $activeMembersCount = $this->dashboard_getActiveMembersCount();
        $newMembersCount = $this->dashboard_getNewMembersCount();
        // $pendingAppsCount = $this->dashboard_getPendingAppsCount();
        $savingsDepositsTotal = $this->dashboard_getSavingsDepositsTotal();
        $loansIssuedTotal = $this->dashboard_getLoansIssuedTotal();
        $repaymentsTotal = $this->dashboard_getRepaymentsTotal();
        $activeLoansCount = $this->dashboard_getActiveLoansCount();
        $delinquentLoansCount = $this->dashboard_getDelinquentLoansCount();
        $loansAndRepayments = $this->dashboard_getLoansAndRepayments();
        $topLoanBalances = $this->dashboard_getTopLoanBalances();
        $savingsPerMonth = $this->dashboard_getSavingsPerMonth();
        $latestMembers = $this->dashboard_getLatestMembers();
        $saccoOfficials = $this->dashboard_getSaccoOfficials();
        $pendingAppsCount = $this->dashboard_showProfitabilityThisMonth();

        $currentPeriod = $this->currentPeriod;

        return view('dashboard', compact(
            'activeMembersCount',
            'newMembersCount',
            'pendingAppsCount',
            'savingsDepositsTotal',
            'loansIssuedTotal',
            'repaymentsTotal',
            'activeLoansCount',
            'delinquentLoansCount',
            'loansAndRepayments',
            'topLoanBalances',
            'savingsPerMonth',
            'latestMembers',
            'saccoOfficials',
            'currentPeriod'
        ));
    }

    protected function dashboard_getLatestMembers()
    {
        return DB::table('sacco_members')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->where('member_deleted', '<>', 'Y')
            ->orderBy('member_date_joined', 'desc')
            ->select('sacco_members.*', 'sacco_company.company_name')
            ->limit(10)
            ->get();
    }

    protected function dashboard_getSaccoOfficials()
    {
        return DB::table('sacco_members')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->where('sacco_members.member_position', 2)
            ->select('sacco_members.*', 'sacco_company.company_name')
            ->limit(10)
            ->get();
    }

    protected function dashboard_getSavingsPerMonth()
    {
        $last12Months = now()->subMonths(11)->format('Ym'); // Start from 11 months ago
        return DB::table('sacco_shares')
            ->select(DB::raw('SUM(share_amount_paying) as total_savings'), 'share_period')
            ->where('share_period', '>=', $last12Months)
            ->groupBy('share_period')
            ->orderBy('share_period', 'asc')
            ->get();
    }





    protected function dashboard_getTopLoanBalances()
    {
        return DB::table('sacco_members')
            ->where('member_active', 'Y')
            ->orderBy('member_total_loan', 'desc')
            ->take(10)
            ->get(['member_name', 'member_total_loan']);
    }

    private function dashboard_getActiveMembersCount()
    {
        return DB::table('sacco_members')
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->count();
    }

    private function dashboard_getNewMembersCount()
    {
        $currentYear = date('Y');
        $currentMonth = date('m');

        return DB::table('sacco_members')
            ->whereYear('member_date_joined', $currentYear)
            ->whereMonth('member_date_joined', $currentMonth)
            ->where('member_deleted', '<>', 'Y')
            ->count();
    }

    private function dashboard_getPendingAppsCount()
    {
        $batchPendingCount = DB::table('sacco_loan_batch')
            ->where('batch_approved', '<>', 'Y')
            ->where('batch_deleted', '<>', 'Y')
            ->count();

        $directPendingCount = DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_updated', '<>', 'Y')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->count();

        return $batchPendingCount + $directPendingCount;
    }

    private function dashboard_getSavingsDepositsTotal()
    {
        $totalShares = DB::table('sacco_members')
            ->where('member_deleted', '<>', 'Y')
            ->sum('member_total_share');

        $totalFosa = DB::table('sacco_members')
            ->where('member_deleted', '<>', 'Y')
            ->sum('member_total_fosa');

        $totalShareCapital = DB::table('sacco_members')
            ->where('member_deleted', '<>', 'Y')
            ->sum('member_total_share_capital');

        return $totalShares + $totalFosa + $totalShareCapital;
    }

    private function dashboard_getLoansIssuedTotal()
    {
        return DB::table('sacco_loans')
            // ->where('loan_deleted', '<>', 'Y')
            ->sum('loan_amount');
    }

    private function dashboard_getRepaymentsTotal()
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->value('period_name');

        return DB::table('sacco_loan_payments')
            ->where('loan_payments_period', $currentPeriod)
            ->where('loan_end_month_proc', '<>', 'Y')
            ->sum('loan_payments_amount');
    }


    private function dashboard_getActiveLoansCount()
    {
        $default = DB::table('sacco_defaults')
            ->where('default_name', 'threshold_amount')
            ->first();

        $threshold_amount = $default ? $default->default_value : 1;
        if (!is_numeric($threshold_amount)) {
            $threshold_amount = 1;
        }

        return DB::table('sacco_loans')
            ->whereRaw('(loan_amount - loan_loan_paid) > ?', [$threshold_amount])
            ->where('loan_stoped', '<>', 'Y')
            // ->where('loan_deleted', '<>', 'Y')
            ->count();
    }


    private function dashboard_getDelinquentLoansCount()
    {



        $currentPeriod = date('YYYYmm');


        $PeriodNow = $currentPeriod;
        $IgnoreLoanBalanceBelow = 5;

        // Calculate the current period year and month
        $periodNowYear = intval(substr($PeriodNow, 0, 4));
        $periodNowMonth = intval(substr($PeriodNow, 4, 2));

        // Function to calculate the months difference between two periods
        $monthsDifference = function ($relevantPeriod) use ($periodNowYear, $periodNowMonth) {
            $relevantYear = intval(substr($relevantPeriod, 0, 4));
            $relevantMonth = intval(substr($relevantPeriod, 4, 2));

            return ($periodNowYear - $relevantYear) * 12 + ($periodNowMonth - $relevantMonth);
        };

        $loans = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->leftJoin(DB::raw('(SELECT loan_payments_loan_id, MAX(loan_payments_period) as last_paid FROM sacco_loan_payments GROUP BY loan_payments_loan_id) as sacco_loan_payments'), 'sacco_loans.loan_id', '=', 'sacco_loan_payments.loan_payments_loan_id')
            ->select('loan_taken_period', 'sacco_loan_payments.last_paid', 'loan_taken_start_period', DB::raw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) as OutstandingAmount'))
            ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$IgnoreLoanBalanceBelow])
            ->orderBy('sacco_loans.loan_id', 'desc')
            ->get();

        $count = 0;

        foreach ($loans as $loan) {
            // Check if $loan->last_paid exists and is not empty, else use $loan->loan_taken_period
            $relevantPeriod = (isset($loan->last_paid) && !empty($loan->last_paid)) ? $loan->last_paid : $loan->loan_taken_period;

            // New condition
            if ($relevantPeriod < $loan->loan_taken_start_period) {
                if ($loan->loan_taken_start_period > $PeriodNow) {
                    $relevantPeriod = $PeriodNow;
                } else {
                    $relevantPeriod = $loan->loan_taken_start_period;
                }
            }

            $monthsDiff = $monthsDifference($relevantPeriod);

            if ($monthsDiff > 2) {
                $count++;
            }
        }

        return $count;
    }

    private function dashboard_getLoansAndRepayments()
    {
        $currentPeriod = $this->currentPeriod->period_name;
        $startPeriod = date('Ym', strtotime($currentPeriod . ' -12 months'));

        $loans = DB::table('sacco_loans')
            ->select(
                'loan_taken_period as period',
                DB::raw('SUM(loan_amount) as total_loans')
            )
            ->whereBetween('loan_taken_period', [$startPeriod, $currentPeriod])
            ->groupBy('loan_taken_period')
            ->orderBy('loan_taken_period')
            ->get();

        $repayments = DB::table('sacco_loan_payments')
            ->select(
                'loan_payments_period as period',
                DB::raw('SUM(loan_payments_amount) as total_repayments')
            )
            ->whereBetween('loan_payments_period', [$startPeriod, $currentPeriod])
            ->groupBy('loan_payments_period')
            ->orderBy('loan_payments_period')
            ->get();

        return ['loans' => $loans, 'repayments' => $repayments];
    }


    private function dashboard_showProfitabilityThisMonth()
    {
        $currentPeriod = date('Ym');

        // Fetch total income for the current period
        $totalIncome = DB::table('sacco_accounts_trans')
            ->join('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_accounts_trans.accounts_trans_period', $currentPeriod)
            ->where('sacco_main_account.main_account_type', 'income')
            ->sum(DB::raw('sacco_accounts_trans.accounts_trans_credit - sacco_accounts_trans.accounts_trans_debit'));

        // Fetch total expenses for the current period
        $totalExpenses = DB::table('sacco_accounts_trans')
            ->join('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_accounts_trans.accounts_trans_period', $currentPeriod)
            ->where('sacco_main_account.main_account_type', 'expense')
            ->sum(DB::raw('sacco_accounts_trans.accounts_trans_debit - sacco_accounts_trans.accounts_trans_credit'));

        // Calculate profitability
        $profitability = $totalIncome - $totalExpenses;

        return $profitability;
    }




    public function membersList(Request $request)
    {
        $orderby = $request->input('orderby', 'member_name');
        $sort_order = 'asc';
        $search = $request->input('pms_srch', '');

        $members = $this->getMembers($orderby, $sort_order, $search);

        $data = [
            'members' => $members,
            'orderby' => $orderby,
            'sort_order' => $sort_order,
            'pms_srch' => $search,
        ];

        return view('members.list', $data);
    }

    public function membersActive($status, Request $request)
    {
        $orderby = $request->input('orderby', 'member_name');
        $sort_order = 'asc';
        $search = $request->input('pms_srch', '');

        $members = $this->getMembers($orderby, $sort_order, $search, $this->recordLimit, $status);

        $data = [
            'members' => $members,
            'orderby' => $orderby,
            'sort_order' => $sort_order,
            'pms_srch' => $search,
            'status' => $status
        ];

        return view('members.list', $data);
    }

    public function addNewMember()
    {
        $departments = DB::table('sacco_department')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->where('company_deleted', '<>', 'Y')
            ->where('department_deleted', '<>', 'Y')
            ->orderBy('company_name')
            ->select('department_id', 'company_name', 'department_name')
            ->get();

        $positions = DB::table('sacco_position')
            ->where('position_deleted', '<>', 'Y')
            ->orderBy('position_name')
            ->select('position_id', 'position_name')
            ->get();

        return view('members.add', compact('departments', 'positions'));
    }

    public function storeNewMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_name' => 'required|string|max:100',
            'member_date_joined' => 'required|date',
            'member_sacco_id' => 'required|string|max:100|unique:sacco_members',
            'member_national_id' => 'required|string|max:100|unique:sacco_members',
            'member_email' => 'required|email|max:100|unique:sacco_members',
            'member_dept' => 'required|integer',
            'member_position' => 'required|integer',
            'member_phone_no' => 'nullable|string|max:100',
            'member_postal_address' => 'nullable|string',
            'member_gender' => 'required|string|in:M,F',
            'member_kra_pin' => 'nullable|string|max:255',
            'member_dob' => 'nullable|date|before_or_equal:' . now()->subYears($this->minAge)->format('Y-m-d'),
            'bank_name' => 'nullable|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->only([
            'member_name',
            'member_date_joined',
            'member_dept',
            'member_sacco_id',
            'member_national_id',
            'member_postal_address',
            'member_phone_no',
            'member_gender',
            'member_email',
            'member_position',
            'member_kra_pin',
            'member_dob',
            'bank_name',
            'bank_branch',
            'bank_account_number'
        ]);

        $data['member_active'] = 'Y';
        $data['member_ip'] = $request->ip();
        $data['member_user_id'] = auth()->id();

        DB::table('sacco_members')->insert($data);

        return redirect()->route('members.list')->with('success', 'Member added successfully.');
    }

    private function getMembers($orderby = 'member_name', $sort_order = 'asc', $search = '', $limit = null, $status = null)
    {
        if (is_null($limit)) {
            $limit = $this->recordLimit;
        }

        $query = DB::table('sacco_members')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id')
            ->where('member_deleted', '<>', 'Y')
            ->where(function ($query) use ($search) {
                if (!empty($search)) {
                    $search = '%' . $search . '%';
                    $query->where('department_name', 'like', $search)
                        ->orWhere('position_name', 'like', $search)
                        ->orWhere('company_name', 'like', $search)
                        ->orWhere('member_name', 'like', $search)
                        ->orWhere('member_sacco_id', 'like', $search)
                        ->orWhere('member_national_id', 'like', $search)
                        ->orWhere('member_email', 'like', $search)
                        ->orWhere('member_phone_no', 'like', $search);
                }
            });

        if (!is_null($status)) {
            $query->where('sacco_members.member_active', '=', $status);
        }

        return $query->orderBy($orderby, $sort_order)
            ->select('*')
            ->limit($limit)
            ->get();
    }

    public function editMember($id)
    {
        $member = DB::table('sacco_members')->where('member_id', $id)->first();
        $departments = DB::table('sacco_department')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->select('sacco_department.department_id', 'sacco_department.department_name', 'sacco_company.company_name')
            ->where('department_deleted', '<>', 'Y')
            ->get();
        $positions = DB::table('sacco_position')
            ->select('position_id', 'position_name')
            ->where('position_deleted', '<>', 'Y')
            ->get();

        $data = [
            'member' => $member,
            'departments' => $departments,
            'positions' => $positions
        ];

        return view('members.edit', compact('data'));
    }

    public function updateMember(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'member_name' => 'required|string|max:255',
            'member_sacco_id' => 'required|string|max:255',
            'member_national_id' => 'required|string|max:255',
            'member_email' => 'required|email|max:255',
            'member_date_joined' => 'required|date',
            'member_gender' => 'nullable|string|max:1',
            'member_dob' => 'nullable|date',
            'member_kra_pin' => 'nullable|string|max:255',
            'member_phone_no' => 'nullable|string|max:255',
            'member_postal_address' => 'nullable|string',
            'member_dept' => 'required|integer|exists:sacco_department,department_id',
            'member_position' => 'required|integer|exists:sacco_position,position_id',
            'bank_name' => 'nullable|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // dd($request->input('member_dob'));
        DB::table('sacco_members')
            ->where('member_id', $id)
            ->update([
                'member_name' => strtoupper($request->input('member_name')),
                'member_sacco_id' => strtoupper($request->input('member_sacco_id')),
                'member_national_id' => strtoupper($request->input('member_national_id')),
                'member_email' => $request->input('member_email'),

                'member_date_joined' => $request->input('member_date_joined'),
                'member_gender' => $request->input('member_gender'),
                'member_dob' => $request->input('member_dob'),
                'member_kra_pin' => strtoupper($request->input('member_kra_pin')),
                'member_phone_no' => $request->input('member_phone_no'),
                'member_postal_address' => strtoupper($request->input('member_postal_address')),
                'member_dept' => $request->input('member_dept'),
                'member_position' => $request->input('member_position'),
                'bank_name' => strtoupper($request->input('bank_name')),
                'bank_branch' => strtoupper($request->input('bank_branch')),
                'bank_account_number' => strtoupper($request->input('bank_account_number'))
            ]);

        return redirect()->route('members.list')->with('success', 'Member updated successfully.');
    }

    // Status
    public function editStatus($id)
    {
        $data['member'] = DB::table('sacco_members')->where('member_id', $id)->first();
        // Additional status-related data here
        return view('members.status', compact('data'));
    }



    private function getAccounts()
    {
        return DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sub_account_deleted', '<>', 'Y')
            ->orderBy('sub_account_name')
            ->select(
                'sacco_sub_account.sub_account_id',
                'sacco_sub_account.sub_account_name',
                'sacco_main_account.main_account_code',
                'sacco_sub_account.sub_account_code'
            )
            ->get();
    }

    public function addInstitution()
    {
        $companies = DB::table('sacco_company')
            ->where('company_deleted', '<>', 'Y')
            ->orderBy('company_name')
            ->select('company_id', 'company_name', 'company_details', 'company_account')
            ->get();

        $accounts = $this->getAccounts();

        return view('institutions.add', ['data' => ['companies' => $companies, 'accounts' => $accounts]]);
    }

    public function storeInstitution(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'nullable|string|max:255|unique:sacco_company,company_name',
            'company_id' => 'nullable|integer|exists:sacco_company,company_id',
            'company_details' => 'nullable|string|max:1000',
            'department_name' => 'nullable|string|max:255|unique:sacco_department,department_name,NULL,department_id,department_company_id,' . $request->input('company_id'),
            'sub_account_id' => 'required|integer|exists:sacco_sub_account,sub_account_id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $companyId = $request->input('company_id');
        $departmentId = null;

        // Insert or update company if a new company name or details are provided
        if ($request->filled('company_name')) {
            $companyName = strtoupper($request->input('company_name'));
            $companyDetails = $request->input('company_details');
            $companyId = DB::table('sacco_company')->insertGetId([
                'company_name' => $companyName,
                'company_details' => $companyDetails,
                'company_account' => $request->input('sub_account_id'),
                'company_deleted' => 'N',
                'company_transdate' => now(),
            ]);
        } else if ($companyId) {
            DB::table('sacco_company')
                ->where('company_id', $companyId)
                ->update([
                    'company_details' => $request->input('company_details'),
                    'company_account' => $request->input('sub_account_id'),
                    'company_transdate' => now(),
                ]);
        }

        // Insert department if a new department name is provided
        if ($request->filled('department_name')) {
            $departmentName = strtoupper($request->input('department_name'));
            $departmentId = DB::table('sacco_department')->insertGetId([
                'department_name' => $departmentName,
                'department_company_id' => $companyId,
                'department_deleted' => 'N',
                'department_user_id' => auth()->id(),
                'department_ip' => $request->ip(),
                'department_transdate' => now(),
            ]);
        }

        return redirect()->route('institutions.list')->with('success', 'Institution added successfully.');
    }

    public function listInstitutions()
    {
        $institutions = DB::table('sacco_company')
            ->leftJoin('sacco_sub_account', 'sacco_company.company_account', '=', 'sacco_sub_account.sub_account_id')
            ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->leftJoin('sacco_department', 'sacco_company.company_id', '=', 'sacco_department.department_company_id')
            ->where('sacco_company.company_deleted', '<>', 'Y')
            ->select(
                'sacco_company.company_name',
                'sacco_company.company_details',
                'sacco_main_account.main_account_code',
                'sacco_sub_account.sub_account_code',
                'sacco_sub_account.sub_account_name',
                'sacco_department.department_name'
            )
            ->orderBy('sacco_company.company_name')
            ->get();

        return view('institutions.list', ['data' => ['institutions' => $institutions]]);
    }

    public function editNextOfKin($id)
    {
        $member = DB::table('sacco_members')
            ->where('member_id', $id)
            ->first();

        if (!$member) {
            return redirect()->route('members.list')->with('error', 'Member not found.');
        }

        $nextOfKins = DB::table('sacco_next_of_kin')
            ->where('kin_member_id', $id)
            ->where('kin_deleted', '<>', 'Y')
            ->get();

        $relationTypes = DB::table('sacco_kin_type')
            ->where('kin_type_deleted', '<>', 'Y')
            ->orderBy('kin_type_name')
            ->get();

        return view('members.next_of_kin', [
            'member' => $member,
            'nextOfKins' => $nextOfKins,
            'relationTypes' => $relationTypes
        ]);
    }

    public function updateNextOfKin(Request $request, $id)
    {
        $validatedData = $request->validate([
            'kin_names' => 'required|string|max:255',
            'kin_address' => 'nullable|string|max:255',
            'kin_national_id' => 'required|string|max:255',
            'kin_relationship' => 'required|integer|exists:sacco_kin_type,kin_type_id',
            'kin_percent' => 'required|integer|min:1|max:100',
        ]);

        $totalAllocatedPercentage = DB::table('sacco_next_of_kin')
            ->where('kin_member_id', $id)
            ->where('kin_deleted', '<>', 'Y')
            ->sum('kin_percent');

        if (($totalAllocatedPercentage + $request->kin_percent) > 100) {
            return redirect()->back()->withErrors(['The total percentage allocated exceeds 100%.'])->withInput();
        }

        $existingKin = DB::table('sacco_next_of_kin')
            ->where(function ($query) use ($request, $id) {
                $query->where('kin_names', $request->kin_names)
                    ->orWhere('kin_national_id', $request->kin_national_id);
            })
            ->where('kin_member_id', $id)
            ->first();

        if ($existingKin) {
            return redirect()->back()->withErrors(['Duplicate next of kin exists. Record not saved.'])->withInput();
        }

        DB::table('sacco_next_of_kin')->insert([
            'kin_member_id' => $id,
            'kin_names' => strtoupper($request->kin_names),
            'kin_address' => strtoupper($request->kin_address),
            'kin_national_id' => strtoupper($request->kin_national_id),
            'kin_percent' => $request->kin_percent,
            'kin_relationship' => $request->kin_relationship,
            'kin_transdate' => now(),
        ]);

        return redirect()->route('members.nextOfKin', $id)->with('success', 'Next of kin added successfully.');
    }

    public function deleteNextOfKin($member_id, $kin_id)
    {
        DB::table('sacco_next_of_kin')
            ->where('kin_id', $kin_id)
            ->where('kin_member_id', $member_id)
            ->update(['kin_deleted' => 'Y']);

        return redirect()->route('members.nextOfKin', $member_id)->with('success', 'Next of kin deleted successfully.');
    }

    public function memberStatus($id)
    {

        $showHyperlinks = true;
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }
        if ($user->member_position != 2 || $id === null) {
            $id = $user->member_id;
            $showHyperlinks = false;
        }

        // Fetch the threshold amount from the sacco_defaults table
        $default = DB::table('sacco_defaults')
            ->where('default_name', 'threshold_amount')
            ->first();

        $threshold_amount = $default ? $default->default_value : 1;
        if (!is_numeric($threshold_amount)) {
            $threshold_amount = 1;
        }

        // Fetch member details
        $member = DB::table('sacco_members')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id')
            ->where('member_deleted', '<>', 'Y')
            ->where('member_id', $id)
            ->select('sacco_members.*', 'sacco_department.department_name', 'sacco_company.company_name', 'sacco_position.position_name')
            ->first();

        if (!$member) {
            return redirect()->route('home');
        }

        // Fetch member's financial details
        $memberFinancials = [
            'total_share_deposit' => $member->member_total_share,
            'total_capital_shares' => $member->member_total_share_capital,
            'total_fosa_deposits' => $member->member_total_fosa,
            'unpaid_loan' => $member->member_total_loan,
            'tied_shares_others' => $member->member_tied_shares,
            'tied_shares_self' => $member->member_tied_shares_self,
        ];

        // Fetch loans taken by the member
        $loansTaken = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_loan_category', 'sacco_loans.loan_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->where('loan_member', $id)
            ->whereRaw('COALESCE(loan_amount,0) - COALESCE(loan_loan_paid,0) > ?', [$threshold_amount])
            //->whereRaw('loan_amount - loan_loan_paid > ?', [$threshold_amount])
            ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', 'sacco_loan_category.loan_category_name')
            ->orderBy('loan_taken_period')
            ->orderBy('loan_on')
            ->get();

        // Fetch guarantors for loans taken by the member
        $loansTakenWithGuarantors = [];
        foreach ($loansTaken as $loan) {
            $guarantors = DB::table('sacco_loan_guarantors')
                ->join('sacco_members', 'sacco_loan_guarantors.loan_guar_guarantor_id', '=', 'sacco_members.member_id')
                ->where('loan_guar_loan_id', $loan->loan_id)
                ->whereRaw('COALESCE(loan_guar_amount_guaranteed, 0) - COALESCE(loan_guar_amount_freed, 0) > ?', [$threshold_amount])
                // ->whereRaw('loan_guar_amount_guaranteed - loan_guar_amount_freed > ?', [$threshold_amount])
                ->where('loan_guar_deleted', '<>', 'Y')
                ->select('sacco_loan_guarantors.*', 'sacco_members.member_id', 'sacco_members.member_name', 'sacco_members.member_sacco_id')
                ->get();
            $loan->guarantors = $guarantors;
            $loansTakenWithGuarantors[] = $loan;
        }

        // Fetch loans guaranteed by the member
        $loansGuaranteed = DB::table('sacco_loan_guarantors')
            ->join('sacco_loans', 'sacco_loan_guarantors.loan_guar_loan_id', '=', 'sacco_loans.loan_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->where('loan_guar_guarantor_id', $id)
            ->whereRaw('loan_guar_amount_guaranteed - loan_guar_amount_freed > ?', [$threshold_amount])
            //->whereRaw('loan_amount - loan_loan_paid > ?', [$threshold_amount])
            ->whereRaw('COALESCE(loan_amount,0) - COALESCE(loan_loan_paid,0) > ?', [$threshold_amount])
            ->where('loan_guar_deleted', '<>', 'Y')
            ->select('sacco_loan_guarantors.*', 'sacco_loans.loan_amount', 'sacco_loans.loan_loan_paid', 'sacco_loans.loan_taken_period', 'sacco_loan_types.loan_type_name', 'sacco_members.member_id', 'sacco_members.member_name', 'sacco_members.member_sacco_id')
            ->get();

        $data = [
            'member' => $member,
            'memberFinancials' => $memberFinancials,
            'loansTakenWithGuarantors' => $loansTakenWithGuarantors,
            'loansGuaranteed' => $loansGuaranteed,
            'threshold_amount' => $threshold_amount,
            'showHyperlinks' => $showHyperlinks
        ];

        return view('members.status', $data);
    }





    public function deleteGuarantor($member_id, $guarantor_id)
    {
        DB::table('sacco_loan_guarantors')
            ->where('loan_guar_id', $guarantor_id)
            ->update(['loan_guar_deleted' => 'Y']);

        return redirect()->route('members.status', ['id' => $member_id])->with('success', 'Guarantor deleted successfully.');
    }

    public function changeGuarantors($member_id, $guarantor_id)
    {
        // Fetch the current guarantor details
        $currentGuarantor = DB::table('sacco_loan_guarantors')
            ->join('sacco_members', 'sacco_loan_guarantors.loan_guar_guarantor_id', '=', 'sacco_members.member_id')
            ->where('sacco_loan_guarantors.loan_guar_id', $guarantor_id)
            ->where('sacco_loan_guarantors.loan_guar_deleted', '<>', 'Y')
            ->select('sacco_loan_guarantors.*', 'sacco_members.member_name', 'sacco_members.member_sacco_id')
            ->first();

        if (!$currentGuarantor) {
            return redirect()->route('members.list')->with('error', 'Invalid guarantor details.');
        }

        // Fetch the loan information along with the guarantor and loan type details
        $loan = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_loan_guarantors', 'sacco_loans.loan_id', '=', 'sacco_loan_guarantors.loan_guar_loan_id')
            ->where('sacco_loan_guarantors.loan_guar_id', $guarantor_id)
            ->where('sacco_members.member_deleted', '<>', 'Y')
            ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', 'sacco_members.member_name as loan_taker_name', 'sacco_members.member_sacco_id as loan_taker_sacco_id')
            ->first();

        if (!$loan) {
            return redirect()->route('members.list')->with('error', 'Invalid loan details.');
        }

        // Fetch the maximum number of guarantors from sacco_defaults
        $default = DB::table('sacco_defaults')
            ->where('default_name', 'maximum_no_of_guarantors')
            ->first();

        $maximum_no_of_guarantors = $default ? $default->default_value : 5;

        $data = [
            'loan' => $loan,
            'currentGuarantor' => $currentGuarantor,
            'maximum_no_of_guarantors' => $maximum_no_of_guarantors,
            'member_id' => $member_id,
            'guarantor_id' => $guarantor_id,
        ];





        return view('guarantors.change', $data);
    }


    public function ajaxGetMembers(Request $request)
    {
        $query = $request->input('q');

        $members = DB::table('sacco_members')
            ->where('member_active', '=', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->whereRaw('member_total_share > member_tied_shares + 10')
            ->where(function ($subQuery) use ($query) {
                $subQuery->where('member_name', 'like', '%' . $query . '%')
                    ->orWhere('member_sacco_id', 'like', '%' . $query . '%')
                    ->orWhere('member_national_id', 'like', '%' . $query . '%')
                    ->orWhere('member_phone_no', 'like', '%' . $query . '%')
                    ->orWhere('member_email', 'like', '%' . $query . '%');
            })
            ->select('member_id as id', 'member_name as name', 'member_sacco_id as sacco_id', 'member_total_share', 'member_tied_shares')
            ->limit(30)
            ->get();

        return response()->json($members);
    }

    public function updateGuarantors(Request $request, $member_id, $guarantor_id)
    {
        // Debug output
        // dd($request->all());

        $loan_id = $request->input('loan_id');
        $current_loan_guar_id = $request->input('loan_guar_id');
        $batch_tied_shares_to_pay = $request->input('batch_tied_shares_to_pay');

        // Fetch the maximum number of guarantors from sacco_defaults
        $default = DB::table('sacco_defaults')
            ->where('default_name', 'maximum_no_of_guarantors')
            ->first();

        $maximum_no_of_guarantors = $default ? $default->default_value : 5;

        // Fetch the max_guarantor_factor from sacco_defaults
        $defaultFactor = DB::table('sacco_defaults')
            ->where('default_name', 'loan_factor_or_shares')
            ->first();

        $max_guarantor_factor = $defaultFactor ? $defaultFactor->default_value : 3;

        // Determine the number of rows submitted
        $submittedRows = $request->input('submittedRows');

        $guarantors_guarantor_name = [];
        $guarantors_amount_guaranteed = [];
        $guarantors_guarantor_id = [];

        for ($ix = 1; $ix <= $submittedRows; $ix++) {
            $guarantors_guarantor_name[$ix] = $request->input("guarantors_guarantor_name{$ix}");
            $guarantors_amount_guaranteed[$ix] = floatval(str_replace(',', '', $request->input("guarantors_amount_guaranteed{$ix}")));
        }

        // Initial validation and checks
        $nmsg = '';

        for ($ix = 1; $ix <= $submittedRows; $ix++) {
            if (!empty($guarantors_guarantor_name[$ix])) {
                $memno = explode(" (", $guarantors_guarantor_name[$ix]);
                $memno[0] = trim($memno[0]);
                $memno[1] = trim($memno[1]);
                $memno1 = substr($memno[1], 0, strlen($memno[1]) - 1);
                $memno1 = trim($memno1);

                $member = DB::table('sacco_members')
                    ->where('member_name', $memno[0])
                    ->where('member_sacco_id', $memno1)
                    ->where('member_active', 'Y')
                    ->where('member_deleted', '<>', 'Y')
                    ->first();

                if (!$member) {
                    $nmsg .= "Error, {$guarantors_guarantor_name[$ix]} has no file<br>";
                    continue;
                }

                $guarantors_guarantor_id[$ix] = $member->member_id;

                if ($member_id != $guarantors_guarantor_id[$ix]) {
                    if (($member->member_total_share * $max_guarantor_factor) - $member->member_tied_shares < $guarantors_amount_guaranteed[$ix]) {
                        $nmsg .= "Error, {$guarantors_guarantor_name[$ix]}, in row " . ($ix) . " has over guaranteed<br>";
                    }
                } else {
                    if ($member->member_total_share - $member->member_tied_shares_self < $guarantors_amount_guaranteed[$ix]) {
                        $nmsg .= "Error, {$guarantors_guarantor_name[$ix]}, row " . ($ix) . " has over guaranteed him/herself<br>";
                    }
                }
            }
        }

        if (!empty($nmsg)) {
            return redirect()->back()->withErrors(['error' => $nmsg])->withInput();
        }

        // Calculate the total value guaranteed
        $val = 0;

        for ($ix = 1; $ix <= $submittedRows; $ix++) {
            if (!empty($guarantors_guarantor_name[$ix])) {
                $val += $guarantors_amount_guaranteed[$ix];
            }
        }

        if ($batch_tied_shares_to_pay > $val) {
            $nmsg = "Error, this member has been under guaranteed<br>";
        }

        if ($val > 0) {
            $g_factor = $batch_tied_shares_to_pay / $val;
        }

        if (!empty($nmsg)) {
            return redirect()->back()->withErrors(['error' => $nmsg])->withInput();
        }

        // Mark the current guarantor as freed
        $transferredToIds = implode(", ", $guarantors_guarantor_id);

        DB::table('sacco_loan_guarantors')
            ->where('loan_guar_id', $current_loan_guar_id)
            ->update([
                'loan_guar_amount_freed' => DB::raw('loan_guar_amount_guaranteed'),
                'loan_guar_description' => DB::raw("CONCAT(loan_guar_description, ' - transferred on ', NOW())"),
                'loan_guar_transfered' => "transferred to $transferredToIds"
            ]);

        // Insert new guarantors
        $desc = "New Guarantor added - directly from GID " . $current_loan_guar_id;

        for ($ix = 1; $ix <= $submittedRows; $ix++) {
            if (!empty($guarantors_guarantor_name[$ix])) {
                $guarantors_amount_guaranteed[$ix] = $guarantors_amount_guaranteed[$ix] * $g_factor;

                DB::table('sacco_loan_guarantors')->insert([
                    'loan_guar_loan_id' => $loan_id,
                    'loan_guar_guarantor_id' => $guarantors_guarantor_id[$ix],
                    'loan_guar_amount_guaranteed' => $guarantors_amount_guaranteed[$ix],
                    'loan_guar_description' => $desc,
                    'loan_guar_by' => auth()->id(),
                    'loan_guar_ip' => $request->ip(),
                ]);

                $tiedO = $guarantors_amount_guaranteed[$ix];
                $tiedS = 0;
                if ($member_id == $guarantors_guarantor_id[$ix]) {
                    $tiedO = 0;
                    $tiedS = $guarantors_amount_guaranteed[$ix];
                }

                DB::table('sacco_members')->where('member_id', $guarantors_guarantor_id[$ix])->update([
                    'member_tied_shares' => DB::raw("member_tied_shares + $tiedO"),
                    'member_tied_shares_self' => DB::raw("member_tied_shares_self + $tiedS"),
                ]);
            }
        }

        return redirect()->route('members.list')->with('success', 'Guarantors updated successfully.');
    }


    public function viewStatement($id = null)
    {

        $user = auth()->user();

        // If the user is not an official or no ID is provided, use the authenticated user's ID
        if ($user->member_position != 2) {
            $id = $user->member_id;
        }

        if ($id === null) {
            $id = $user->member_id;
        }

        $member = DB::table('sacco_members')->where('member_id', $id)->first();
        if (!$member) {
            return back()->withErrors(['error' => 'Member not found.']);
        }

        // Set period_from and period_to, defaulting to '000000' and '999900' if not provided
        $period_from = request('period_from', '000000');
        $period_to = request('period_to', '999900');

        // Fetch the threshold amount for determining cleared loans
        $threshold = DB::table('sacco_defaults')
            ->where('default_name', 'threshold_amount')
            ->value('default_value');
        $threshold_amount = $threshold !== null ? (float)$threshold : 1.0;

        // Fetch FOSA contributions
        $fosaContributions = DB::table('sacco_fosas')
            ->join('sacco_members', 'sacco_fosas.fosa_member_id', '=', 'sacco_members.member_id')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->where('sacco_members.member_deleted', '<>', 'Y')
            ->where('sacco_fosas.fosa_member_id', $id)
            ->orderBy('sacco_fosas.fosa_period')
            ->orderBy('sacco_fosas.fosa_date_paid')
            ->get();

        // Calculate opening balance for shares
        $openingBalanceShares = DB::table('sacco_shares')
            ->where('share_member_id', $id)
            ->where('share_period', '<', $period_from)
            ->sum('share_amount_paying');

        $shareContributions = DB::table('sacco_shares')
            ->join('sacco_members', 'sacco_shares.share_member_id', '=', 'sacco_members.member_id')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->where('sacco_shares.share_member_id', $id)
            ->whereBetween('sacco_shares.share_period', [$period_from, $period_to])
            ->orderBy('sacco_shares.share_period')
            ->orderBy('sacco_shares.share_date_paid')
            ->get();

        // Fetch Capital contributions
        $openingBalanceCapital = DB::table('sacco_capital_shares')
            ->where('share_capitalmember_id', $id)
            ->where('share_capitalperiod', '<', $period_from)
            ->sum('share_capitalamount_paying');

        $capitalContributions = DB::table('sacco_capital_shares')
            ->join('sacco_members', 'sacco_capital_shares.share_capitalmember_id', '=', 'sacco_members.member_id')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->where('sacco_capital_shares.share_capitalmember_id', $id)
            ->whereBetween('sacco_capital_shares.share_capitalperiod', [$period_from, $period_to])
            ->orderBy('sacco_capital_shares.share_capitalperiod')
            ->orderBy('sacco_capital_shares.share_capitaldate_paid')
            ->get();

        // Fetch loans
        $loans = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_loan_category', 'sacco_loans.loan_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->where('sacco_loans.loan_member', $id)
            ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', 'sacco_loan_category.loan_category_name')
            ->orderBy('sacco_loans.loan_taken_period')
            ->orderBy('sacco_loans.loan_on')
            ->get();

        // Fetch all loan payments
        $loanPayments = DB::table('sacco_loan_payments')
            ->join('sacco_loans', 'sacco_loan_payments.loan_payments_loan_id', '=', 'sacco_loans.loan_id')
            ->where('sacco_loans.loan_member', $id)
            ->select('sacco_loan_payments.*', 'sacco_loans.loan_loan_type')
            ->orderBy('loan_payments_period')
            ->orderBy('loan_payments_paid_on')
            ->get();

        // Group loan payments by loan_id
        $paymentsByLoan = $loanPayments->groupBy('loan_payments_loan_id');

        $data = [
            'member' => $member,
            'fosaContributions' => $fosaContributions,
            'shareContributions' => $shareContributions,
            'capitalContributions' => $capitalContributions,
            'openingBalanceShares' => $openingBalanceShares,
            'openingBalanceCapital' => $openingBalanceCapital,
            'loans' => $loans,
            'paymentsByLoan' => $paymentsByLoan,
            'period_from' => $period_from,
            'period_to' => $period_to,
            'threshold_amount' => $threshold_amount,
        ];

        return view('members.statement', compact('data'));
    }
    public function viewContributions($id, Request $request)
    {
        $member = DB::table('sacco_members')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id')
            ->where('sacco_members.member_id', $id)
            ->where('member_deleted', '<>', 'Y')
            ->select('sacco_members.*', 'sacco_company.company_name', 'sacco_department.department_name')
            ->first();

        if (!$member) {
            return redirect()->route('members.list');
        }

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            foreach ($sanitizedData as $key => $value) {
                if (is_string($value)) {
                    $sanitizedData[$key] = str_replace(',', '', $value); // Remove commas
                }
            }

            $validatedData = Validator::make($sanitizedData, [
                'member_share_contr_monthly' => 'required|numeric|min:0',
                'member_fosa_contr_monthly' => 'required|numeric|min:0',
            ])->validate();

            $minFosaContribution = DB::table('sacco_defaults')
                ->where('default_name', 'min_fosa_contribution')
                ->value('default_value');

            $minShareContribution = DB::table('sacco_defaults')
                ->where('default_name', 'min_share_contribution')
                ->value('default_value');

            $member_fosa_contr_monthly = $sanitizedData['member_fosa_contr_monthly'];
            $member_share_contr_monthly = $sanitizedData['member_share_contr_monthly'];

            if ($member_fosa_contr_monthly < $minFosaContribution && $member_fosa_contr_monthly != 0) {
                return back()->withErrors(['member_fosa_contr_monthly' => "Invalid monthly FOSA contribution, minimum FOSA contribution is $minFosaContribution"])->withInput();
            }

            if ($member_share_contr_monthly < $minShareContribution && $member_share_contr_monthly != 0) {
                return back()->withErrors(['member_share_contr_monthly' => "Invalid monthly share contribution, minimum SHARE contribution is $minShareContribution"])->withInput();
            }

            $loans_no = $request->input('t_loans_no');
            $errors = [];

            for ($i = 0; $i < $loans_no; $i++) {
                $loan_id = $sanitizedData["loan_id{$i}"];
                $loan_monthly_repayment_amount = $sanitizedData["loan_monthly_repayment_amount{$i}"];
                $loan_stoped = $sanitizedData["loan_stoped{$i}"];

                if (!is_numeric($loan_monthly_repayment_amount) || $loan_monthly_repayment_amount < 0) {
                    $errors["loan_monthly_repayment_amount{$i}"] = "Invalid EMI contribution in ROW " . ($i + 1);
                }

                if ($loan_stoped != 'Y' && $loan_stoped != 'N') {
                    $errors["loan_stoped{$i}"] = "Invalid value for stopped field in ROW " . ($i + 1);
                }

                if (!empty($errors)) {
                    return back()->withErrors($errors)->withInput();
                }

                DB::table('sacco_loans')
                    ->where('loan_id', $loan_id)
                    ->where('loan_member', $id)
                    ->update([
                        'loan_monthly_repayment_amount' => $loan_monthly_repayment_amount,
                        'loan_stoped' => $loan_stoped,
                    ]);
            }

            DB::table('sacco_members')
                ->where('member_id', $id)
                ->update([
                    'member_fosa_contr_monthly' => $member_fosa_contr_monthly,
                    'member_share_contr_monthly' => $member_share_contr_monthly,
                ]);

            return back()->with('success', 'Contributions updated successfully.');
        }



        $loans = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_loan_category', 'sacco_loans.loan_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->where('sacco_loans.loan_member', $id)
            ->whereRaw('loan_amount > COALESCE(loan_loan_paid, 0)')
            ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', 'sacco_loan_category.loan_category_name')
            ->get();

        $data = [
            'member' => $member,
            'loans' => $loans,
        ];

        return view('members.contributions', compact('data'));
    }



    // public function viewContributions($id, Request $request)
    // {
    //     $member = DB::table('sacco_members')
    //         ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
    //         ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
    //         ->join('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id')
    //         ->where('sacco_members.member_id', $id)
    //         ->where('member_deleted', '<>', 'Y')
    //         ->select('sacco_members.*', 'sacco_company.company_name', 'sacco_department.department_name')
    //         ->first();

    //     if (!$member) {
    //         return redirect()->route('members.list');
    //     }

    //     if ($request->isMethod('post')) {
    //         $sanitizedData = $request->all();
    //         foreach ($sanitizedData as $key => $value) {
    //             if (is_string($value)) {
    //                 $sanitizedData[$key] = str_replace(',', '', $value); // Remove commas
    //             }
    //         }

    //         $validatedData = Validator::make($sanitizedData, [
    //             'member_share_contr_monthly' => 'required|numeric|min:0',
    //             'member_fosa_contr_monthly' => 'required|numeric|min:0',
    //         ])->validate();

    //         $minFosaContribution = DB::table('sacco_defaults')
    //             ->where('default_name', 'min_fosa_contribution')
    //             ->value('default_value');

    //         $minShareContribution = DB::table('sacco_defaults')
    //             ->where('default_name', 'min_share_contribution')
    //             ->value('default_value');

    //         $member_fosa_contr_monthly = $sanitizedData['member_fosa_contr_monthly'];
    //         $member_share_contr_monthly = $sanitizedData['member_share_contr_monthly'];

    //         if ($member_fosa_contr_monthly < $minFosaContribution && $member_fosa_contr_monthly != 0) {
    //             return back()->withErrors(['member_fosa_contr_monthly' => "Invalid monthly FOSA contribution, minimum FOSA contribution is $minFosaContribution"])->withInput();
    //         }

    //         if ($member_share_contr_monthly < $minShareContribution && $member_share_contr_monthly != 0) {
    //             return back()->withErrors(['member_share_contr_monthly' => "Invalid monthly share contribution, minimum SHARE contribution is $minShareContribution"])->withInput();
    //         }

    //         $loans_no = $request->input('t_loans_no');
    //         $errors = [];

    //         for ($i = 0; $i < $loans_no; $i++) {
    //             $loan_id = $sanitizedData["loan_id{$i}"];
    //             $loan_monthly_repayment_amount = $sanitizedData["loan_monthly_repayment_amount{$i}"];
    //             $loan_stoped = $sanitizedData["loan_stoped{$i}"];

    //             if (!is_numeric($loan_monthly_repayment_amount) || $loan_monthly_repayment_amount < 0) {
    //                 $errors["loan_monthly_repayment_amount{$i}"] = "Invalid EMI contribution in ROW " . ($i + 1);
    //             }

    //             if ($loan_stoped != 'Y' && $loan_stoped != 'N') {
    //                 $errors["loan_stoped{$i}"] = "Invalid value for stopped field in ROW " . ($i + 1);
    //             }

    //             if (!empty($errors)) {
    //                 return back()->withErrors($errors)->withInput();
    //             }

    //             DB::table('sacco_loans')
    //                 ->where('loan_id', $loan_id)
    //                 ->where('loan_member', $id)
    //                 ->update([
    //                     'loan_monthly_repayment_amount' => $loan_monthly_repayment_amount,
    //                     'loan_stoped' => $loan_stoped,
    //                 ]);
    //         }

    //         DB::table('sacco_members')
    //             ->where('member_id', $id)
    //             ->update([
    //                 'member_fosa_contr_monthly' => $member_fosa_contr_monthly,
    //                 'member_share_contr_monthly' => $member_share_contr_monthly,
    //             ]);

    //         return back()->with('success', 'Contributions updated successfully.');
    //     }

    //     $loans = DB::table('sacco_loans')
    //         ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
    //         ->join('sacco_loan_category', 'sacco_loans.loan_loan_category', '=', 'sacco_loan_category.loan_category_id')
    //         ->where('sacco_loans.loan_member', $id)
    //         ->whereRaw('loan_amount > loan_loan_paid')
    //         ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', 'sacco_loan_category.loan_category_name')
    //         ->get();

    //     return view('members.contributions', compact('member', 'loans'));
    // }

    // public function changePassword($id)
    //     {
    //         $member = DB::table('sacco_members')->where('member_id', $id)->first();

    //         if (!$member) {
    //             return redirect()->route('members.list')->withErrors(['Member not found.']);
    //         }

    //         return view('members.changePassword', compact('member'));
    //     }

    //     public function updatePassword(Request $request, $id)
    //     {
    //         $request->validate([
    //             'member_password' => 'required|string|min:6',
    //             'member_password1' => 'required|string|same:member_password|min:6',
    //         ]);

    //         $member_password = $request->input('member_password');

    //         DB::table('sacco_members')
    //             ->where('member_id', $id)
    //             ->update([
    //                 'member_password' => md5($member_password),
    //                 'member_password_last_changed' => now(),
    //                 // 'member_password_changed_by' => auth()->user()->id,
    //             ]);

    //         return redirect()->route('members.changePassword', ['id' => $id])->with('success', 'Password changed successfully.');
    //     }
    public function changePassword($id)
    {
        $member = DB::table('sacco_members')->where('member_id', $id)->first();

        if (!$member) {
            $data = [
                'error' => 'Member not found.',
                'success' => '',
            ];
            return redirect()->route('members.list')->withErrors($data['error']);
        }

        $data = [
            'member' => $member,
            'error' => '',
            'success' => '',
        ];

        return view('members.changePassword', $data);
    }

    public function updatePassword(Request $request, $id)
    {
        $request->validate([
            'member_password' => 'required|string|min:6',
            'member_password1' => 'required|string|same:member_password|min:6',
        ]);

        $member_password = $request->input('member_password');

        DB::table('sacco_members')
            ->where('member_id', $id)
            ->update([
                'member_password' => md5($member_password),
                'member_password_last_changed' => now(),
                // 'member_password_changed_by' => auth()->user()->id,
            ]);

        $data = [
            'success' => 'Password changed successfully.',
            'error' => '',
        ];

        return redirect()->route('members.changePassword', ['id' => $id])->with($data);
    }

    public function showChangeSelfPasswordForm()
    {
        $data = [];
        return view('profile.password', $data);
    }

    public function updateSelfPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();

        // if (md5($request->current_password) !== $user->getAuthPassword()) {
        //     return redirect()->back()->withErrors(['current_password' => 'Current password is incorrect']);
        // }

        if (md5($request->current_password) !== $user->member_password) {
            return redirect()->back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        DB::table('sacco_members')
            ->where('member_id', $user->member_id)
            ->update([
                'member_password' => md5($request->new_password),
                'member_password_last_changed' => now(),
            ]);

        return redirect()->route('profile.password')->with('success', 'Password changed successfully.');
    }

    public function adminPeriods()
    {
        // Check if the current period (YYYYMM) exists in the table
        $currentPeriodName = date('Ym');

        $existingPeriod = DB::table('sacco_period')
            ->where('period_name', $currentPeriodName)
            ->where('period_deleted', '<>', 'Y')
            ->first();

        // If the current period does not exist, add it
        if (!$existingPeriod) {
            DB::table('sacco_period')->insert([
                'period_name' => $currentPeriodName,
                'period_active' => 'N',
                'period_user_id' => Auth::id(),
                'period_transdate' => now(),
                'period_ip' => request()->ip(),
            ]);
        }

        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        if (!$currentPeriod) {
            return redirect()->route('admin.periods.create');
        }

        $periods = DB::table('sacco_period')
            ->orderBy('period_active', 'desc')
            ->orderBy('period_name', 'desc')
            ->get();

        $data = [
            'currentPeriod' => $currentPeriod,
            'periods' => $periods,
        ];

        return view('admin.periods.index', compact('data'));
    }

    public function adminPeriodsCreate()
    {
        $data = [];
        return view('admin.periods.create', compact('data'));
    }

    public function adminPeriodsStore(Request $request)
    {
        $request->validate([
            'period_name' => 'required|numeric|digits:6',
        ]);

        $period_name = strtoupper($request->input('period_name'));

        $currentYear = date('Y');
        $cYear = (int)substr($period_name, 0, 4);
        $cMonth = (int)substr($period_name, 4, 2);

        if ($cYear > ($currentYear + 1) || $cYear < $currentYear || $cMonth > 12 || $cMonth < 1) {
            return back()->withErrors(['period_name' => 'Invalid period. Record not saved.'])->withInput();
        }

        $duplicate = DB::table('sacco_period')
            ->where('period_name', $period_name)
            ->where('period_deleted', '<>', 'Y')
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['period_name' => 'Duplicate period exists. Record not saved.'])->withInput();
        }

        if ($request->input('period_active') == 'Y') {
            DB::table('sacco_period')
                ->update(['period_active' => 'N']);
        }

        DB::table('sacco_period')
            ->insert([
                'period_name' => $period_name,
                'period_active' => $request->input('period_active', 'N'),
                'period_user_id' => Auth::id(),
                'period_transdate' => now(),
                'period_ip' => $request->ip(),
            ]);

        $data = [];
        return redirect()->route('admin.periods')->with('success', 'Period added successfully.')->with('data', $data);
    }

    public function adminPeriodsActivate($id)
    {
        $period = DB::table('sacco_period')->where('period_id', $id)->first();

        // dd($period->period_name );

        if (!$period) {
            return redirect()->route('admin.periods')->withErrors(['Period not found.']);
        }

        $isOpen = $this->isPeriodOpen($period->period_name);

        if (!$isOpen) {
            return redirect()->route('admin.periods')->withErrors(['Period is closed.']);
        }

        // dd($isOpen);
        DB::table('sacco_period')
            ->update(['period_active' => 'N']);

        DB::table('sacco_period')
            ->where('period_id', $id)
            ->update([
                'period_active' => 'Y',
                'period_user_id' => Auth::id(),
                'period_transdate' => now(),
                'period_ip' => request()->ip(),
            ]);

        $data = [];
        return redirect()->route('admin.periods')->with('success', 'Period activated successfully.')->with('data', $data);
    }


    private function isPeriodOpen($periodToCheck)
    {
        // Fetch the last closed period from the table
        $lastClosedPeriod = DB::table('sacco_end_year_proc')
            ->orderBy('end_year_proc_period', 'desc')
            ->value('end_year_proc_period');
        if ($periodToCheck <= $lastClosedPeriod) {
            return false;
        }

        $currentDate = Carbon::now();
        $checkDate = Carbon::createFromFormat('Ym', $periodToCheck)->startOfMonth();

        // Calculate the difference in months between the current date and the period to check
        $monthsDifference = $currentDate->diffInMonths($checkDate);


        if ($monthsDifference > 16) {
            return false; // Period is too far behind
        }
        return true;
    }


    public function modifyShares(Request $request)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $modify_member_shares_journal_entries = DB::table('sacco_defaults')
            ->where('default_name', 'modify_member_shares_journal_entries')
            ->value('default_value');

        $data = [
            'modify_member_shares_journal_entries' => $modify_member_shares_journal_entries,
        ];

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            foreach ($sanitizedData as $key => $value) {
                if (is_string($value)) {
                    $sanitizedData[$key] = strtoupper(addslashes(trim($value)));
                }
            }

            $errors = [];
            $validEntries = 0;

            for ($ix = 0; $ix < $modify_member_shares_journal_entries; $ix++) {
                $member_name = $sanitizedData['member_name'][$ix] ?? '';
                $sub_account_name = $sanitizedData['sub_account_name'][$ix] ?? '';
                $share_action = $sanitizedData['share_action'][$ix] ?? '';
                $amount = isset($sanitizedData['amount'][$ix]) ? str_replace(',', '', $sanitizedData['amount'][$ix]) : '';
                $share_doc_no = $sanitizedData['share_doc_no'][$ix] ?? '';
                $share_description = $sanitizedData['share_description'][$ix] ?? '';
                $share_date_paid = $sanitizedData['share_date_paid'][$ix] ?? '';

                if (!empty($member_name) || !empty($sub_account_name)) {
                    $validEntries++;

                    if (empty($share_action)) {
                        $errors[] = "Error, missing action in row " . ($ix + 1);
                    }
                    if (empty($share_date_paid)) {
                        $errors[] = "Error, missing date in row " . ($ix + 1);
                    }
                    if (empty($share_description)) {
                        $errors[] = "Error, missing description in row " . ($ix + 1);
                    }
                    if (empty($share_doc_no)) {
                        $errors[] = "Error, missing document number in row " . ($ix + 1);
                    }

                    if (count($errors) > 0) {
                        continue;
                    }

                    $memno = explode("- (", $member_name);
                    if (count($memno) < 2) {
                        $errors[] = "Error, wrong member name format in row " . ($ix + 1);
                        continue;
                    }
                    $share_description = "Share adjusted for " . $sanitizedData['member_name'][$ix] . " - " . ($sanitizedData['share_description'][$ix] ?? '');
                    $memno[0] = trim($memno[0]);
                    $memno[1] = trim($memno[1], ")");
                    $member = DB::table('sacco_members')
                        ->where('member_name', $memno[0])
                        ->where('member_sacco_id', $memno[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->first();

                    if (!$member) {
                        $errors[] = "Error, wrong member name in row " . ($ix + 1);
                        continue;
                    }

                    $member_id = $member->member_id;

                    $accno = explode("- (", $sub_account_name);
                    if (count($accno) < 2) {
                        $errors[] = "Error, wrong account format in row " . ($ix + 1);
                        continue;
                    }

                    $accno[0] = trim($accno[0]);
                    $accno[1] = trim($accno[1], ")");
                    $accno2 = explode("/", $accno[0]);

                    $subAccount = DB::table('sacco_sub_account')
                        ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
                        ->where('sub_account_deleted', '<>', 'Y')
                        ->where('sub_account_name', $accno[1])
                        ->where('sub_account_code', $accno2[1])
                        ->where('main_account_code', $accno2[0])
                        ->first();

                    if (!$subAccount) {
                        $errors[] = "Error, wrong account no. in row " . ($ix + 1);
                        continue;
                    }

                    $laccount = $subAccount->sub_account_id;

                    if ($share_action != '-1' && $share_action != '1') {
                        $errors[] = "Error, wrong action in row " . ($ix + 1);
                        continue;
                    }

                    if (!empty($amount) && !is_numeric($amount)) {
                        $errors[] = "Error, invalid amount in row " . ($ix + 1);
                        continue;
                    }

                    if (count($errors) == 0) {
                        $amount = $amount ? $amount * $share_action : 0;

                        DB::table('sacco_shares')->insert([
                            'share_member_id' => $member_id,
                            'share_amount_paying' => $amount,
                            'share_paid_by' => 'JOURNAL',
                            'share_period' => $currentPeriod->period_name,
                            'share_description' => $share_description,
                            'share_doc_no' => $share_doc_no,
                            'share_date_paid' => $share_date_paid,
                            'share_end_month_proc' => 'N',
                            'share_by' => Auth::id(),
                            'share_ip' => $request->ip(),
                        ]);

                        DB::table('sacco_members')
                            ->where('member_id', $member_id)
                            ->increment('member_total_share', $amount);

                        $default_share_account = DB::table('sacco_defaults')
                            ->where('default_name', 'default_share_account')
                            ->value('default_value');

                        $this->updateSaccoAccountsTrans($default_share_account, 0, $amount, $share_doc_no, $share_description, $share_date_paid, $currentPeriod->period_name, 'Modify Shares');
                        $this->updateSaccoAccountsTrans($laccount, $amount, 0, $share_doc_no, $share_description, $share_date_paid, $currentPeriod->period_name, 'Modify Shares');
                    }
                }
            }

            if (count($errors) == 0 && $validEntries > 0) {
                return redirect()->route('modify.member.shares')->with('success', 'Transactions processed successfully.');
            } else {
                return redirect()->route('modify.member.shares')->withInput()->with('error', $errors);
            }
        }


        return view('shares.modify', $data);
    }

    private function updateSaccoAccountsTrans($subAccountId, $debit, $credit, $docNo, $description, $date, $period, $sourceDescription)
    {
        DB::table('sacco_accounts_trans')->insert([
            'accounts_trans_sub_account' => $subAccountId,
            'accounts_trans_period' => $period,
            'accounts_trans_debit' => $debit,
            'accounts_trans_credit' => $credit,
            'accounts_trans_doc_no' => $docNo,
            'accounts_trans_decription' => $description,
            'accounts_trans_dat_date' => $date,
            'accounts_trans_user_id' => Auth::id(),
            'accounts_trans_ip' => request()->ip(),
            'accounts_trans_source' => $sourceDescription,
            'accounts_trans_app_name' => 'iSacco',
        ]);

        DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->increment('sub_account_debit', $debit);
        DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->increment('sub_account_credit', $credit);

        $mainAccountId = DB::table('sacco_sub_account')
            ->where('sub_account_id', $subAccountId)
            ->value('sub_account_main_account');

        DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccountId)
            ->increment('main_account_debit', $debit);
        DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccountId)
            ->increment('main_account_credit', $credit);
    }

    // private function updateSaccoAccountsTrans($subAccountId, $debit, $credit, $docNo, $description, $date, $period)
    // {
    //     DB::table('sacco_accounts_trans')->insert([
    //         'accounts_trans_sub_account' => $subAccountId,
    //         'accounts_trans_period' => $period,
    //         'accounts_trans_debit' => $debit,
    //         'accounts_trans_credit' => $credit,
    //         'accounts_trans_doc_no' => $docNo,
    //         'accounts_trans_decription' => $description,
    //         'accounts_trans_dat_date' => $date,
    //         'accounts_trans_user_id' => Auth::id(),
    //         'accounts_trans_ip' => request()->ip(),
    //         'accounts_trans_source' => 'Modifying member shares',
    //         'accounts_trans_app_name' => 'iSacco',
    //     ]);

    //     DB::table('sacco_sub_account')
    //         ->where('sub_account_id', $subAccountId)
    //         ->increment('sub_account_debit', $debit);
    //     DB::table('sacco_sub_account')
    //         ->where('sub_account_id', $subAccountId)
    //         ->increment('sub_account_credit', $credit);

    //     $mainAccountId = DB::table('sacco_sub_account')
    //         ->where('sub_account_id', $subAccountId)
    //         ->value('sub_account_main_account');

    //     DB::table('sacco_main_account')
    //         ->where('main_account_id', $mainAccountId)
    //         ->increment('main_account_debit', $debit);
    //     DB::table('sacco_main_account')
    //         ->where('main_account_id', $mainAccountId)
    //         ->increment('main_account_credit', $credit);
    // }



    public function searchMembers(Request $request)
    {
        $query = $request->input('query');
        $members = DB::table('sacco_members')
            ->select('member_name', 'member_sacco_id')
            ->where(function ($q) use ($query) {
                $q->where('member_name', 'LIKE', '%' . $query . '%')
                    ->orWhere('member_phone_no', 'LIKE', '%' . $query . '%')
                    ->orWhere('member_sacco_id', 'LIKE', '%' . $query . '%')
                    ->orWhere('member_national_id', 'LIKE', '%' . $query . '%');
            })
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->orderBy('member_name')
            ->limit(5)
            ->get();

        $results = [];
        foreach ($members as $member) {
            $results[] = [
                'label' => $member->member_name . ' - (' . $member->member_sacco_id . ')',
                'value' => $member->member_name . ' - (' . $member->member_sacco_id . ')',
            ];
        }

        return response()->json($results);
    }




    public function searchAccounts(Request $request)
    {
        $query = $request->input('query');
        $accounts = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->select('sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code')
            ->where('sacco_sub_account.sub_account_name', 'LIKE', '%' . $query . '%')
            ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
            ->orderBy('sacco_sub_account.sub_account_name')
            ->limit(5)
            ->get();

        $results = [];
        foreach ($accounts as $account) {
            $results[] = [
                'label' => $account->main_account_code . '/' . $account->sub_account_code . ' - (' . $account->sub_account_name . ')',
                'value' => $account->main_account_code . '/' . $account->sub_account_code . ' - (' . $account->sub_account_name . ')',
            ];
        }

        return response()->json($results);
    }


    public function modifyShareCapital(Request $request)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $modify_member_shares_journal_entries = DB::table('sacco_defaults')
            ->where('default_name', 'modify_member_shares_journal_entries')
            ->value('default_value');

        $data = [
            'modify_member_shares_journal_entries' => $modify_member_shares_journal_entries,
        ];

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            foreach ($sanitizedData as $key => $value) {
                if (is_string($value)) {
                    $sanitizedData[$key] = strtoupper(addslashes(trim($value)));
                }
            }

            $errors = [];
            $validEntries = 0;

            for ($ix = 0; $ix < $modify_member_shares_journal_entries; $ix++) {
                $member_name = $sanitizedData['member_name'][$ix] ?? '';
                $sub_account_name = $sanitizedData['sub_account_name'][$ix] ?? '';
                $share_action = $sanitizedData['share_action'][$ix] ?? '';
                $amount = isset($sanitizedData['amount'][$ix]) ? str_replace(',', '', $sanitizedData['amount'][$ix]) : '';
                $share_doc_no = $sanitizedData['share_doc_no'][$ix] ?? '';
                $share_description = $sanitizedData['share_description'][$ix] ?? '';
                $share_date_paid = $sanitizedData['share_date_paid'][$ix] ?? '';

                if (!empty($member_name) || !empty($sub_account_name)) {
                    $validEntries++;

                    if (empty($share_action)) {
                        $errors[] = "Error, missing action in row " . ($ix + 1);
                    }
                    if (empty($share_date_paid)) {
                        $errors[] = "Error, missing date in row " . ($ix + 1);
                    }
                    if (empty($share_description)) {
                        $errors[] = "Error, missing description in row " . ($ix + 1);
                    }
                    if (empty($share_doc_no)) {
                        $errors[] = "Error, missing document number in row " . ($ix + 1);
                    }

                    if (count($errors) > 0) {
                        continue;
                    }

                    $memno = explode("- (", $member_name);
                    if (count($memno) < 2) {
                        $errors[] = "Error, wrong member name format in row " . ($ix + 1);
                        continue;
                    }
                    $share_description = "Share adjusted for " . $sanitizedData['member_name'][$ix] . " - " . ($sanitizedData['share_description'][$ix] ?? '');
                    $memno[0] = trim($memno[0]);
                    $memno[1] = trim($memno[1], ")");
                    $member = DB::table('sacco_members')
                        ->where('member_name', $memno[0])
                        ->where('member_sacco_id', $memno[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->first();

                    if (!$member) {
                        $errors[] = "Error, wrong member name in row " . ($ix + 1);
                        continue;
                    }

                    $member_id = $member->member_id;

                    $accno = explode("- (", $sub_account_name);
                    if (count($accno) < 2) {
                        $errors[] = "Error, wrong account format in row " . ($ix + 1);
                        continue;
                    }

                    $accno[0] = trim($accno[0]);
                    $accno[1] = trim($accno[1], ")");
                    $accno2 = explode("/", $accno[0]);

                    $subAccount = DB::table('sacco_sub_account')
                        ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
                        ->where('sub_account_deleted', '<>', 'Y')
                        ->where('sub_account_name', $accno[1])
                        ->where('sub_account_code', $accno2[1])
                        ->where('main_account_code', $accno2[0])
                        ->first();

                    if (!$subAccount) {
                        $errors[] = "Error, wrong account no. in row " . ($ix + 1);
                        continue;
                    }

                    $laccount = $subAccount->sub_account_id;

                    if ($share_action != '-1' && $share_action != '1') {
                        $errors[] = "Error, wrong action in row " . ($ix + 1);
                        continue;
                    }

                    if (!is_numeric($amount)) {
                        $errors[] = "Error, invalid amount in row " . ($ix + 1);
                        continue;
                    }

                    if (count($errors) == 0) {
                        $amount = $amount * $share_action;

                        DB::table('sacco_capital_shares')->insert([
                            'share_capitalmember_id' => $member_id,
                            'share_capitalamount_paying' => $amount,
                            'share_capitalpaid_by' => 'JOURNAL',
                            'share_capitalperiod' => $currentPeriod->period_name,
                            'share_capitaldescription' => $share_description,
                            'share_capitaldoc_no' => $share_doc_no,
                            'share_capitaldate_paid' => $share_date_paid,
                            'share_capitalend_month_proc' => 'N',
                            'share_capitalby' => Auth::id(),
                            'share_capitalip' => $request->ip(),
                        ]);

                        DB::table('sacco_members')
                            ->where('member_id', $member_id)
                            ->increment('member_total_share_capital', $amount);

                        $default_share_capital_account = DB::table('sacco_defaults')
                            ->where('default_name', 'default_share_capital_account')
                            ->value('default_value');

                        $this->updateSaccoAccountsTrans($default_share_capital_account, 0, $amount, $share_doc_no, $share_description, $share_date_paid, $currentPeriod->period_name, 'Modify Capital');
                        $this->updateSaccoAccountsTrans($laccount, $amount, 0, $share_doc_no, $share_description, $share_date_paid, $currentPeriod->period_name, 'Modify Capital');
                    }
                }
            }

            if (count($errors) == 0 && $validEntries > 0) {
                return redirect()->route('modify.member.share.capital')->with('success', 'Transactions processed successfully.');
            } else {
                return redirect()->route('modify.member.share.capital')->with('error', $errors)->withInput();
            }
        }

        return view('shares.modify_capital', $data);
    }


    public function modifyFosas(Request $request)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $modify_member_shares_journal_entries = DB::table('sacco_defaults')
            ->where('default_name', 'modify_member_shares_journal_entries')
            ->value('default_value');

        $data = [
            'modify_member_shares_journal_entries' => $modify_member_shares_journal_entries,
        ];

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            foreach ($sanitizedData as $key => $value) {
                if (is_string($value)) {
                    $sanitizedData[$key] = strtoupper(addslashes(trim($value)));
                }
            }

            $errors = [];
            $validEntries = 0;

            for ($ix = 0; $ix < $modify_member_shares_journal_entries; $ix++) {
                $member_name = $sanitizedData['member_name'][$ix] ?? '';
                $sub_account_name = $sanitizedData['sub_account_name'][$ix] ?? '';
                $fosa_action = $sanitizedData['fosa_action'][$ix] ?? '';
                $amount = isset($sanitizedData['amount'][$ix]) ? str_replace(',', '', $sanitizedData['amount'][$ix]) : '';
                $fosa_doc_no = $sanitizedData['fosa_doc_no'][$ix] ?? '';
                $fosa_description = $sanitizedData['fosa_description'][$ix] ?? '';
                $fosa_date_paid = $sanitizedData['fosa_date_paid'][$ix] ?? '';

                if (!empty($member_name) || !empty($sub_account_name)) {
                    $validEntries++;

                    if (empty($fosa_action)) {
                        $errors[] = "Error, missing action in row " . ($ix + 1);
                    }
                    if (empty($fosa_date_paid)) {
                        $errors[] = "Error, missing date in row " . ($ix + 1);
                    }
                    if (empty($fosa_description)) {
                        $errors[] = "Error, missing description in row " . ($ix + 1);
                    }
                    if (empty($fosa_doc_no)) {
                        $errors[] = "Error, missing document number in row " . ($ix + 1);
                    }

                    if (count($errors) > 0) {
                        continue;
                    }

                    $memno = explode("- (", $member_name);
                    if (count($memno) < 2) {
                        $errors[] = "Error, wrong member name format in row " . ($ix + 1);
                        continue;
                    }
                    $fosa_description = "FOSA adjusted for " . $sanitizedData['member_name'][$ix] . " - " . ($sanitizedData['fosa_description'][$ix] ?? '');
                    $memno[0] = trim($memno[0]);
                    $memno[1] = trim($memno[1], ")");
                    $member = DB::table('sacco_members')
                        ->where('member_name', $memno[0])
                        ->where('member_sacco_id', $memno[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->first();

                    if (!$member) {
                        $errors[] = "Error, wrong member name in row " . ($ix + 1);
                        continue;
                    }

                    $member_id = $member->member_id;

                    $accno = explode("- (", $sub_account_name);
                    if (count($accno) < 2) {
                        $errors[] = "Error, wrong account format in row " . ($ix + 1);
                        continue;
                    }

                    $accno[0] = trim($accno[0]);
                    $accno[1] = trim($accno[1], ")");
                    $accno2 = explode("/", $accno[0]);

                    $subAccount = DB::table('sacco_sub_account')
                        ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
                        ->where('sub_account_deleted', '<>', 'Y')
                        ->where('sub_account_name', $accno[1])
                        ->where('sub_account_code', $accno2[1])
                        ->where('main_account_code', $accno2[0])
                        ->first();

                    if (!$subAccount) {
                        $errors[] = "Error, wrong account no. in row " . ($ix + 1);
                        continue;
                    }

                    $laccount = $subAccount->sub_account_id;

                    if ($fosa_action != '-1' && $fosa_action != '1') {
                        $errors[] = "Error, wrong action in row " . ($ix + 1);
                        continue;
                    }

                    if (!is_numeric($amount)) {
                        $errors[] = "Error, invalid amount in row " . ($ix + 1);
                        continue;
                    }

                    if (count($errors) == 0) {
                        $amount = $amount * $fosa_action;

                        DB::table('sacco_fosas')->insert([
                            'fosa_member_id' => $member_id,
                            'fosa_amount_paying' => $amount,
                            'fosa_paid_by' => 'JOURNAL',
                            'fosa_period' => $currentPeriod->period_name,
                            'fosa_description' => $fosa_description,
                            'fosa_doc_no' => $fosa_doc_no,
                            'fosa_date_paid' => $fosa_date_paid,
                            'fosa_end_month_proc' => 'N',
                            'fosa_by' => Auth::id(),
                            'fosa_ip' => $request->ip(),
                        ]);

                        DB::table('sacco_members')
                            ->where('member_id', $member_id)
                            ->increment('member_total_fosa', $amount);

                        $default_fosa_account = DB::table('sacco_defaults')
                            ->where('default_name', 'default_fosa_account')
                            ->value('default_value');

                        $this->updateSaccoAccountsTrans($default_fosa_account, 0, $amount, $fosa_doc_no, $fosa_description, $fosa_date_paid, $currentPeriod->period_name, 'Modify Fosa');
                        $this->updateSaccoAccountsTrans($laccount, $amount, 0, $fosa_doc_no, $fosa_description, $fosa_date_paid, $currentPeriod->period_name, 'Modify Fosa');
                    }
                }
            }

            if (count($errors) == 0 && $validEntries > 0) {
                return redirect()->route('modify.member.fosas')->with('success', 'Transactions processed successfully.');
            } else {
                return redirect()->route('modify.member.fosas')->with('error', $errors)->withInput();
            }
        }

        return view('shares.modify_fosas', $data);
    }

    public function transferShares(Request $request)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $modify_member_shares_journal_entries = DB::table('sacco_defaults')
            ->where('default_name', 'modify_member_shares_journal_entries')
            ->value('default_value');

        $data = [
            'modify_member_shares_journal_entries' => $modify_member_shares_journal_entries,
        ];

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            foreach ($sanitizedData as $key => $value) {
                if (is_string($value)) {
                    $sanitizedData[$key] = strtoupper(addslashes(trim($value)));
                }
            }

            $errors = [];
            $validEntries = 0;

            for ($ix = 0; $ix < $modify_member_shares_journal_entries; $ix++) {
                $member_name = $sanitizedData['member_name'][$ix] ?? '';
                $member_namex1 = $sanitizedData['member_namex1'][$ix] ?? '';
                $amount = isset($sanitizedData['amount'][$ix]) ? str_replace(',', '', $sanitizedData['amount'][$ix]) : '';
                $share_doc_no = $sanitizedData['share_doc_no'][$ix] ?? '';
                $share_description = $sanitizedData['share_description'][$ix] ?? '';
                $share_date_paid = $sanitizedData['share_date_paid'][$ix] ?? '';

                if (!empty($member_name) || !empty($member_namex1)) {
                    $validEntries++;

                    if (empty($share_date_paid)) {
                        $errors[] = "Error, missing date in row " . ($ix + 1);
                    }
                    if (empty($amount)) {
                        $errors[] = "Error, missing amount in row " . ($ix + 1);
                    }
                    if (empty($share_description)) {
                        $errors[] = "Error, missing description in row " . ($ix + 1);
                    }
                    if (empty($share_doc_no)) {
                        $errors[] = "Error, missing document number in row " . ($ix + 1);
                    }

                    if (count($errors) > 0) {
                        continue;
                    }

                    $memno = explode("- (", $member_name);
                    if (count($memno) < 2) {
                        $errors[] = "Error, wrong 'from' member name format in row " . ($ix + 1);
                        continue;
                    }
                    $memno[0] = trim($memno[0]);
                    $memno[1] = trim($memno[1], ")");
                    $member = DB::table('sacco_members')
                        ->where('member_name', $memno[0])
                        ->where('member_sacco_id', $memno[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->first();

                    if (!$member) {
                        $errors[] = "Error, wrong 'from' member name in row " . ($ix + 1);
                        continue;
                    }

                    $member_id = $member->member_id;

                    $memno1 = explode("- (", $member_namex1);
                    if (count($memno1) < 2) {
                        $errors[] = "Error, wrong 'to' member name format in row " . ($ix + 1);
                        continue;
                    }
                    $memno1[0] = trim($memno1[0]);
                    $memno1[1] = trim($memno1[1], ")");
                    $member1 = DB::table('sacco_members')
                        ->where('member_name', $memno1[0])
                        ->where('member_sacco_id', $memno1[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->first();

                    if (!$member1) {
                        $errors[] = "Error, wrong 'to' member name in row " . ($ix + 1);
                        continue;
                    }

                    $member_id1 = $member1->member_id;

                    if (!is_numeric($amount)) {
                        $errors[] = "Error, invalid amount in row " . ($ix + 1);
                        continue;
                    }

                    if (count($errors) == 0) {
                        $amount = $amount * -1;
                        $share_description1x = $share_description . " (Transfer to " . $member_namex1 . ")";
                        $share_description2x = $share_description . " (Transfer from " . $member_name . ")";

                        DB::table('sacco_shares')->insert([
                            'share_member_id' => $member_id,
                            'share_amount_paying' => $amount,
                            'share_paid_by' => 'JOURNAL',
                            'share_period' => $currentPeriod->period_name,
                            'share_description' => $share_description1x,
                            'share_doc_no' => $share_doc_no,
                            'share_date_paid' => $share_date_paid,
                            'share_end_month_proc' => 'N',
                            'share_by' => Auth::id(),
                            'share_ip' => $request->ip(),
                        ]);

                        DB::table('sacco_members')
                            ->where('member_id', $member_id)
                            ->increment('member_total_share', $amount);

                        $amount = $amount * -1;

                        DB::table('sacco_shares')->insert([
                            'share_member_id' => $member_id1,
                            'share_amount_paying' => $amount,
                            'share_paid_by' => 'JOURNAL',
                            'share_period' => $currentPeriod->period_name,
                            'share_description' => $share_description2x,
                            'share_doc_no' => $share_doc_no,
                            'share_date_paid' => $share_date_paid,
                            'share_end_month_proc' => 'N',
                            'share_by' => Auth::id(),
                            'share_ip' => $request->ip(),
                        ]);

                        DB::table('sacco_members')
                            ->where('member_id', $member_id1)
                            ->increment('member_total_share', $amount);

                        $default_share_account = DB::table('sacco_defaults')
                            ->where('default_name', 'default_share_account')
                            ->value('default_value');

                        $this->updateSaccoAccountsTrans($default_share_account, $amount, 0, $share_doc_no, $share_description1x, $share_date_paid, $currentPeriod->period_name, 'Transfer Shares');
                        $this->updateSaccoAccountsTrans($default_share_account, 0, $amount, $share_doc_no, $share_description2x, $share_date_paid, $currentPeriod->period_name, 'Transfer Shares');
                    }
                }
            }

            if (count($errors) == 0 && $validEntries > 0) {
                return redirect()->route('transfer.member.shares')->with('success', 'Transactions processed successfully.');
            } else {
                return redirect()->route('transfer.member.shares')->with('error', $errors);
            }
        }

        return view('shares.transfer', $data);
    }


    public function transferCapitalShares(Request $request)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $modify_member_shares_journal_entries = DB::table('sacco_defaults')
            ->where('default_name', 'modify_member_shares_journal_entries')
            ->value('default_value');

        $data = [
            'modify_member_shares_journal_entries' => $modify_member_shares_journal_entries,
        ];

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            foreach ($sanitizedData as $key => $value) {
                if (is_string($value)) {
                    $sanitizedData[$key] = strtoupper(addslashes(trim($value)));
                }
            }

            $errors = [];
            $validEntries = 0;

            for ($ix = 0; $ix < $modify_member_shares_journal_entries; $ix++) {
                $member_name = $sanitizedData['member_name'][$ix] ?? '';
                $member_namex1 = $sanitizedData['member_namex1'][$ix] ?? '';
                $amount = isset($sanitizedData['amount'][$ix]) ? str_replace(',', '', $sanitizedData['amount'][$ix]) : '';
                $share_doc_no = $sanitizedData['share_doc_no'][$ix] ?? '';
                $share_description = $sanitizedData['share_description'][$ix] ?? '';
                $share_date_paid = $sanitizedData['share_date_paid'][$ix] ?? '';

                if (!empty($member_name) || !empty($member_namex1)) {
                    $validEntries++;

                    if (empty($amount)) {
                        $errors[] = "Error, missing amount in row " . ($ix + 1);
                    }
                    if (empty($share_date_paid)) {
                        $errors[] = "Error, missing date in row " . ($ix + 1);
                    }
                    if (empty($share_description)) {
                        $errors[] = "Error, missing description in row " . ($ix + 1);
                    }
                    if (empty($share_doc_no)) {
                        $errors[] = "Error, missing document number in row " . ($ix + 1);
                    }

                    if (count($errors) > 0) {
                        continue;
                    }

                    $memno = explode("- (", $member_name);
                    if (count($memno) < 2) {
                        $errors[] = "Error, wrong 'from' member name format in row " . ($ix + 1);
                        continue;
                    }
                    $memno[0] = trim($memno[0]);
                    $memno[1] = trim($memno[1], ")");
                    $member = DB::table('sacco_members')
                        ->where('member_name', $memno[0])
                        ->where('member_sacco_id', $memno[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->where('member_total_share_capital', '>=', $amount)
                        ->first();

                    if (!$member) {
                        $errors[] = "Error, wrong 'from' member name or member doesn't have enough share CAPITAL in row " . ($ix + 1);
                        continue;
                    }

                    $member_id = $member->member_id;

                    $memno = explode("- (", $member_namex1);
                    if (count($memno) < 2) {
                        $errors[] = "Error, wrong 'to' member name format in row " . ($ix + 1);
                        continue;
                    }
                    $memno[0] = trim($memno[0]);
                    $memno[1] = trim($memno[1], ")");
                    $member1 = DB::table('sacco_members')
                        ->where('member_name', $memno[0])
                        ->where('member_sacco_id', $memno[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->first();

                    if (!$member1) {
                        $errors[] = "Error, wrong 'to' member name in row " . ($ix + 1);
                        continue;
                    }

                    $member_id1 = $member1->member_id;

                    if (!is_numeric($amount) || $amount < 1) {
                        $errors[] = "Error, invalid amount in row " . ($ix + 1);
                        continue;
                    }

                    $amount = $amount * -1;
                    $share_description1 = $share_description . " (" . $member_namex1 . ")";
                    $share_description2 = $share_description . " (" . $member_name . ")";

                    DB::table('sacco_capital_shares')->insert([
                        'share_capitalmember_id' => $member_id,
                        'share_capitalamount_paying' => $amount,
                        'share_capitalpaid_by' => 'JOURNAL',
                        'share_capitalperiod' => $currentPeriod->period_name,
                        'share_capitaldescription' => $share_description1,
                        'share_capitaldoc_no' => $share_doc_no,
                        'share_capitaldate_paid' => $share_date_paid,
                        'share_capitalend_month_proc' => 'N',
                        'share_capitalby' => Auth::id(),
                        'share_capitalip' => $request->ip(),
                    ]);

                    DB::table('sacco_members')
                        ->where('member_id', $member_id)
                        ->increment('member_total_share_capital', $amount);

                    $amount = $amount * -1;

                    DB::table('sacco_capital_shares')->insert([
                        'share_capitalmember_id' => $member_id1,
                        'share_capitalamount_paying' => $amount,
                        'share_capitalpaid_by' => 'JOURNAL',
                        'share_capitalperiod' => $currentPeriod->period_name,
                        'share_capitaldescription' => $share_description2,
                        'share_capitaldoc_no' => $share_doc_no,
                        'share_capitaldate_paid' => $share_date_paid,
                        'share_capitalend_month_proc' => 'N',
                        'share_capitalby' => Auth::id(),
                        'share_capitalip' => $request->ip(),
                    ]);

                    DB::table('sacco_members')
                        ->where('member_id', $member_id1)
                        ->increment('member_total_share_capital', $amount);

                    $default_share_capital_account = DB::table('sacco_defaults')
                        ->where('default_name', 'default_share_capital_account')
                        ->value('default_value');

                    $this->updateSaccoAccountsTrans($default_share_capital_account, $amount, 0, $share_doc_no, $share_description1, $share_date_paid, $currentPeriod->period_name, "Capital");
                    $this->updateSaccoAccountsTrans($default_share_capital_account, 0, $amount, $share_doc_no, $share_description2, $share_date_paid, $currentPeriod->period_name, "Capital");
                }
            }

            if (count($errors) == 0 && $validEntries > 0) {
                return redirect()->route('transfer.member.capital.shares')->with('success', 'Transactions processed successfully.');
            } else {
                return redirect()->route('transfer.member.capital.shares')->with('error', $errors)->withInput();
            }
        }

        return view('shares.transfer_capital', $data);
    }

    public function transferFosa(Request $request)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $modify_member_shares_journal_entries = DB::table('sacco_defaults')
            ->where('default_name', 'modify_member_shares_journal_entries')
            ->value('default_value');

        $data = [
            'modify_member_shares_journal_entries' => $modify_member_shares_journal_entries,
        ];

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            foreach ($sanitizedData as $key => $value) {
                if (is_string($value)) {
                    $sanitizedData[$key] = strtoupper(addslashes(trim($value)));
                }
            }

            $errors = [];
            $validEntries = 0;

            for ($ix = 0; $ix < $modify_member_shares_journal_entries; $ix++) {
                $member_name = $sanitizedData['member_name'][$ix] ?? '';
                $member_namex1 = $sanitizedData['member_namex1'][$ix] ?? '';
                $amount = isset($sanitizedData['amount'][$ix]) ? str_replace(',', '', $sanitizedData['amount'][$ix]) : '';
                $fosa_doc_no = $sanitizedData['fosa_doc_no'][$ix] ?? '';
                $fosa_description = $sanitizedData['fosa_description'][$ix] ?? '';
                $fosa_date_paid = $sanitizedData['fosa_date_paid'][$ix] ?? '';

                if (!empty($member_name) || !empty($member_namex1)) {
                    $validEntries++;

                    if (empty($amount)) {
                        $errors[] = "Error, missing amount in row " . ($ix + 1);
                    }
                    if (empty($fosa_date_paid)) {
                        $errors[] = "Error, missing date in row " . ($ix + 1);
                    }
                    if (empty($fosa_description)) {
                        $errors[] = "Error, missing description in row " . ($ix + 1);
                    }
                    if (empty($fosa_doc_no)) {
                        $errors[] = "Error, missing document number in row " . ($ix + 1);
                    }

                    if (count($errors) > 0) {
                        continue;
                    }

                    $memno = explode("- (", $member_name);
                    if (count($memno) < 2) {
                        $errors[] = "Error, wrong 'from' member name format in row " . ($ix + 1);
                        continue;
                    }
                    $memno[0] = trim($memno[0]);
                    $memno[1] = trim($memno[1], ")");
                    $member = DB::table('sacco_members')
                        ->where('member_name', $memno[0])
                        ->where('member_sacco_id', $memno[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->first();

                    if (!$member) {
                        $errors[] = "Error, wrong 'from' member name in row " . ($ix + 1);
                        continue;
                    }

                    $member_id = $member->member_id;

                    $memno = explode("- (", $member_namex1);
                    if (count($memno) < 2) {
                        $errors[] = "Error, wrong 'to' member name format in row " . ($ix + 1);
                        continue;
                    }
                    $memno[0] = trim($memno[0]);
                    $memno[1] = trim($memno[1], ")");
                    $member = DB::table('sacco_members')
                        ->where('member_name', $memno[0])
                        ->where('member_sacco_id', $memno[1])
                        ->where('member_active', 'Y')
                        ->where('member_deleted', '<>', 'Y')
                        ->first();

                    if (!$member) {
                        $errors[] = "Error, wrong 'to' member name in row " . ($ix + 1);
                        continue;
                    }

                    $member_id1 = $member->member_id;

                    if (!is_numeric($amount)) {
                        $errors[] = "Error, invalid amount in row " . ($ix + 1);
                        continue;
                    }

                    if ($amount <= 0) {
                        $errors[] = "Error, amount must be greater than zero in row " . ($ix + 1);
                        continue;
                    }

                    if (count($errors) == 0) {
                        $amount = $amount * -1;
                        $fosa_description1x = $fosa_description . " (" . $sanitizedData['member_namex1'][$ix] . ")";
                        $fosa_description2x = $fosa_description . " (" . $sanitizedData['member_name'][$ix] . ")";

                        DB::table('sacco_fosas')->insert([
                            'fosa_member_id' => $member_id,
                            'fosa_amount_paying' => $amount,
                            'fosa_paid_by' => 'JOURNAL',
                            'fosa_period' => $currentPeriod->period_name,
                            'fosa_description' => $fosa_description1x,
                            'fosa_doc_no' => $fosa_doc_no,
                            'fosa_date_paid' => $fosa_date_paid,
                            'fosa_end_month_proc' => 'N',
                            'fosa_by' => Auth::id(),
                            'fosa_ip' => $request->ip(),
                        ]);

                        DB::table('sacco_members')
                            ->where('member_id', $member_id)
                            ->increment('member_total_fosa', $amount);

                        $amount = $amount * -1;
                        DB::table('sacco_fosas')->insert([
                            'fosa_member_id' => $member_id1,
                            'fosa_amount_paying' => $amount,
                            'fosa_paid_by' => 'JOURNAL',
                            'fosa_period' => $currentPeriod->period_name,
                            'fosa_description' => $fosa_description2x,
                            'fosa_doc_no' => $fosa_doc_no,
                            'fosa_date_paid' => $fosa_date_paid,
                            'fosa_end_month_proc' => 'N',
                            'fosa_by' => Auth::id(),
                            'fosa_ip' => $request->ip(),
                        ]);

                        DB::table('sacco_members')
                            ->where('member_id', $member_id1)
                            ->increment('member_total_fosa', $amount);

                        $default_fosa_account = DB::table('sacco_defaults')
                            ->where('default_name', 'default_fosa_account')
                            ->value('default_value');

                        $this->updateSaccoAccountsTrans($default_fosa_account, $amount, 0, $fosa_doc_no, $fosa_description1x, $fosa_date_paid, $currentPeriod->period_name, "FOSA");
                        $this->updateSaccoAccountsTrans($default_fosa_account, 0, $amount, $fosa_doc_no, $fosa_description2x, $fosa_date_paid, $currentPeriod->period_name, "FOSA");
                    }
                }
            }

            if (count($errors) == 0 && $validEntries > 0) {
                return redirect()->route('transfer.member.fosa')->with('success', 'Transactions processed successfully.');
            } else {
                return redirect()->route('transfer.member.fosa')->withInput()->with('error', $errors);
            }
        }

        return view('shares.transfer_fosa', $data);
    }

    public function processEndMonthShares(Request $request)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $monthlyCutOffDay = DB::table('sacco_defaults')
            ->where('default_name', 'monthly_cut_of_day')
            ->value('default_value');

        if (!$monthlyCutOffDay) {
            $monthlyCutOffDay = 28; // Default to 28th of the month if not found
        }

        $periodDate = Carbon::createFromFormat('Ym', $currentPeriod->period_name);
        $monthlyCutOffDay = $periodDate->format('Y-m') . '-' . str_pad($monthlyCutOffDay, 2, '0', STR_PAD_LEFT);

        if (!Carbon::hasFormat($monthlyCutOffDay, 'Y-m-d')) {
            $monthlyCutOffDay = $periodDate->format('Y-m') . '-28';
        }

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            $errors = [];

            $submitted = (int)$sanitizedData['submitted'];
            for ($ix = 0; $ix < $submitted; $ix++) {
                if (isset($sanitizedData["update{$ix}"])) {
                    $companyId = $sanitizedData["update{$ix}"];
                    $shareDocNo = $sanitizedData["share_doc_no{$ix}"];
                    $shareDatePaid = $sanitizedData["share_date_paid{$ix}"];
                    $companyName = $sanitizedData["company_name{$ix}"];

                    if (empty($shareDocNo)) {
                        $errors[] = "Error, missing document number for company: " . $companyName;
                    }
                    if (empty($shareDatePaid)) {
                        $errors[] = "Error, missing date paid for company: " . $companyName;
                    }

                    if (count($errors) > 0) {
                        continue;
                    }

                    $totalShareContributions = DB::table('sacco_department')
                        ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
                        ->join('sacco_members', 'sacco_department.department_id', '=', 'sacco_members.member_dept')
                        ->join('sacco_shares', 'sacco_members.member_id', '=', 'sacco_shares.share_member_id')
                        ->where('share_period', $currentPeriod->period_name)
                        ->where('sacco_company.company_id', $companyId)
                        ->where('share_end_month_proc', 'Y')
                        ->count();

                    if ($totalShareContributions > 0) {
                        $errors[] = "End month processing for - " . $companyName . " - is already done";
                        continue;
                    }

                    $members = DB::table('sacco_department')
                        ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
                        ->join('sacco_members', 'sacco_department.department_id', '=', 'sacco_members.member_dept')
                        ->where('sacco_members.member_deleted', '<>', 'Y')
                        ->where('sacco_members.member_active', 'Y')
                        ->where('sacco_company.company_id', $companyId)
                        ->where('sacco_members.member_date_joined', '<=', $monthlyCutOffDay)
                        ->where('sacco_members.member_share_contr_monthly', '>', 0)
                        ->get();

                    foreach ($members as $member) {
                        $shareDescription = "{$currentPeriod->period_name} Payroll - {$member->member_name} / {$member->member_sacco_id}";

                        DB::table('sacco_shares')->insert([
                            'share_member_id' => $member->member_id,
                            'share_amount_paying' => $member->member_share_contr_monthly,
                            'share_paid_by' => $member->company_name,
                            'share_period' => $currentPeriod->period_name,
                            'share_description' => $shareDescription,
                            'share_doc_no' => $shareDocNo,
                            'share_date_paid' => $shareDatePaid,
                            'share_end_month_proc' => 'Y',
                            'share_by' => Auth::id(),
                            'share_ip' => $request->ip(),
                        ]);

                        DB::table('sacco_members')
                            ->where('member_id', $member->member_id)
                            ->increment('member_total_share', $member->member_share_contr_monthly);

                        $defaultShareAccount = DB::table('sacco_defaults')
                            ->where('default_name', 'default_share_account')
                            ->value('default_value');

                        $sourceDescription = "{$currentPeriod->period_name} Payroll - {$member->member_name} and {$member->company_name}";

                        $this->updateSaccoAccountsTrans(
                            $defaultShareAccount,
                            0,
                            $member->member_share_contr_monthly,
                            $shareDocNo,
                            $shareDescription,
                            $shareDatePaid,
                            $currentPeriod->period_name,
                            $sourceDescription
                        );

                        $this->updateSaccoAccountsTrans(
                            $member->company_account,
                            $member->member_share_contr_monthly,
                            0,
                            $shareDocNo,
                            $shareDescription,
                            $shareDatePaid,
                            $currentPeriod->period_name,
                            $sourceDescription
                        );
                    }
                }
            }

            if (count($errors) > 0) {
                return redirect()->route('proc.end.month.shares')->withErrors($errors)->withInput();
            } else {
                return redirect()->route('proc.end.month.shares')->with('success', 'End month processing completed successfully.');
            }
        }

        $companies = DB::table('sacco_company')
            ->join('sacco_department', 'sacco_company.company_id', '=', 'sacco_department.department_company_id')
            ->join('sacco_members', 'sacco_department.department_id', '=', 'sacco_members.member_dept')
            ->select('sacco_company.company_id', 'sacco_company.company_name', DB::raw('SUM(sacco_members.member_share_contr_monthly) AS tmember_share_contr_monthly'))
            ->where('sacco_members.member_deleted', '<>', 'Y')
            ->where('sacco_members.member_active', 'Y')
            ->where('sacco_members.member_date_joined', '<=', $monthlyCutOffDay)
            ->groupBy('sacco_company.company_id', 'sacco_company.company_name')
            ->get();

        foreach ($companies as $company) {
            $processed = DB::table('sacco_shares')
                ->join('sacco_members', 'sacco_shares.share_member_id', '=', 'sacco_members.member_id')
                ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
                ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
                ->where('sacco_shares.share_period', $currentPeriod->period_name)
                ->where('sacco_company.company_id', $company->company_id)
                ->where('sacco_shares.share_end_month_proc', 'Y')
                ->exists();

            $company->is_processed = $processed;
        }

        return view('shares.proc_end_month_shares', compact('companies', 'currentPeriod'));
    }

    public function listContribution(Request $request)
{
    // Get the active period
    $currentPeriod = DB::table('sacco_period')
        ->where('period_active', 'Y')
        ->where('period_deleted', '<>', 'Y')
        ->first();

    if (!$currentPeriod) {
        abort(500, 'No active period found.');
    }

    // Format the monthly cut-off date
    $cutOffDay = DB::table('sacco_defaults')
        ->where('default_name', 'monthly_cut_of_day')
        ->value('default_value') ?? 28;

    $periodDate = Carbon::createFromFormat('Ym', $currentPeriod->period_name);
    $monthlyCutOfDay = Carbon::parse($periodDate->format('Y-m') . '-' . str_pad($cutOffDay, 2, '0', STR_PAD_LEFT))->format('Y-m-d');

    // Search and sorting logic
    $orderby = $request->input('orderby', 'member_name');
    $sort_order = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';
    $pms_srch = trim($request->input('pms_srch'));

    $query = DB::table('sacco_department')
        ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
        ->join('sacco_members', 'sacco_department.department_id', '=', 'sacco_members.member_dept')
        ->leftJoin('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id')
        ->select('sacco_members.*', 'sacco_company.company_name', 'sacco_department.department_name', 'sacco_position.position_name')
        ->where('sacco_members.member_deleted', '<>', 'Y')
        ->where('sacco_members.member_active', 'Y')
        ->where('sacco_members.member_date_joined', '<=', $monthlyCutOfDay);

    if ($pms_srch && mb_strlen($pms_srch) >= 2) {
        $search = '%' . $pms_srch . '%';
        $query->where(function ($q) use ($search) {
            $q->where('sacco_department.department_name', 'like', $search)
                ->orWhere('sacco_position.position_name', 'like', $search)
                ->orWhere('sacco_company.company_name', 'like', $search)
                ->orWhere('sacco_members.member_name', 'like', $search)
                ->orWhere('sacco_members.member_sacco_id', 'like', $search)
                ->orWhere('sacco_members.member_national_id', 'like', $search)
                ->orWhere('sacco_members.member_email', 'like', $search);
        });
    }

    $members = $query->orderBy($orderby, $sort_order)->get();
    $memberIds = $members->pluck('member_id')->toArray();

    // Get loan types
    $loanTypes = DB::table('sacco_loan_types')
        ->where('loan_type_deleted', '<>', 'Y')
        ->orderBy('loan_type_name')
        ->get();

    // Get minimum billable amount
    $minAmount = DB::table('sacco_defaults')
        ->where('default_name', 'min_loan_amount_bill_able')
        ->value('default_value');

    if (!is_numeric($minAmount)) {
        $minAmount = 1;
    }

    // Batch fetch loan EMIs
    $emiMap = DB::table('sacco_loans')
    ->selectRaw('loan_member, loan_loan_type, SUM(loan_monthly_repayment_amount) as total')
    ->whereIn('loan_member', $memberIds)
    ->where('loan_amount', '>', 0)
    ->whereRaw('COALESCE(loan_loan_paid, 0) < loan_amount')
    ->where('loan_stoped', '<>', 'Y')
    ->where('loan_start_deduction_period', '<=', $currentPeriod->period_name)
    ->where('loan_taken_period', '<=', $currentPeriod->period_name)
    ->whereRaw('loan_amount - COALESCE(loan_loan_paid, 0) > ?', [$minAmount])
    ->groupBy('loan_member', 'loan_loan_type')
    ->get()
    ->groupBy('loan_member')
    ->map(function ($group) {
        return $group->pluck('total', 'loan_loan_type');
    });

    // Attach contributions to each member
    foreach ($members as $member) {
        $member->loan_contributions = [];
        $member->total_deduction = $member->member_share_contr_monthly + $member->member_fosa_contr_monthly;

        foreach ($loanTypes as $loanType) {
            $emi = $emiMap[$member->member_id][$loanType->loan_type_id] ?? 0;
            $member->loan_contributions[$loanType->loan_type_id] = $emi;
            $member->total_deduction += $emi;
        }
    }

    return view('contributions.list', [
        'members' => $members,
        'loanTypes' => $loanTypes,
        'currentPeriod' => $currentPeriod,
        'pms_srch' => $pms_srch,
        'orderby' => $orderby,
        'sort_order' => $sort_order,
    ]);
}



    // public function listContribution(Request $request)
    // {


    //     // Get the active period
    //     $currentPeriod = DB::table('sacco_period')
    //         ->where('period_active', 'Y')
    //         ->where('period_deleted', '<>', 'Y')
    //         ->first();

    //     $monthlyCutOfDay = DB::table('sacco_defaults')
    //         ->where('default_name', 'monthly_cut_of_day')
    //         ->value('default_value');

    //     if (!$monthlyCutOfDay) {
    //         $monthlyCutOfDay = 28; // Default to 28th of the month if not found
    //     }

    //     $periodDate = Carbon::createFromFormat('Ym', $currentPeriod->period_name);
    //     $monthlyCutOfDay = $periodDate->format('Y-m') . '-' . str_pad($monthlyCutOfDay, 2, '0', STR_PAD_LEFT);

    //     if (!Carbon::hasFormat($monthlyCutOfDay, 'Y-m-d')) {
    //         $monthlyCutOfDay = $periodDate->format('Y-m') . '-28';
    //     }

    //     // Search and sorting logic

    //     $orderby = $request->input('orderby', 'member_name');
    //     $sort_order = $request->input('sort_order', 'asc') == 'desc' ? 'desc' : 'asc';

    //     $query = DB::table('sacco_department')
    //         ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
    //         ->join('sacco_members', 'sacco_department.department_id', '=', 'sacco_members.member_dept')
    //         ->leftJoin('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id')
    //         ->select('sacco_members.*', 'sacco_company.company_name', 'sacco_department.department_name', 'sacco_position.position_name')
    //         ->where('sacco_members.member_deleted', '<>', 'Y')
    //         ->where('sacco_members.member_active', 'Y')
    //         ->where('sacco_members.member_date_joined', '<=', $monthlyCutOfDay);

    //     $pms_srch = trim($request->input('pms_srch'));


    //     if ($pms_srch && mb_strlen($pms_srch) >= 2) {
    //         $search = '%' . $pms_srch . '%';

    //         $query->where(function ($q) use ($search) {
    //             $q->where('sacco_department.department_name', 'like', $search)
    //                 ->orWhere('sacco_position.position_name', 'like', $search)
    //                 ->orWhere('sacco_company.company_name', 'like', $search)
    //                 ->orWhere('sacco_members.member_name', 'like', $search)
    //                 ->orWhere('sacco_members.member_sacco_id', 'like', $search)
    //                 ->orWhere('sacco_members.member_national_id', 'like', $search)
    //                 ->orWhere('sacco_members.member_email', 'like', $search);
    //         });
    //     }

    //     $members = $query->orderBy($orderby, $sort_order)->get();


    //     // // Fetch members and their contributions
    //     // $members = DB::table('sacco_department')
    //     //     ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
    //     //     ->join('sacco_members', 'sacco_department.department_id', '=', 'sacco_members.member_dept')
    //     //     ->leftJoin('sacco_position', 'sacco_members.member_position', '=', 'sacco_position.position_id')
    //     //     ->select('sacco_members.*', 'sacco_company.company_name', 'sacco_department.department_name', 'sacco_position.position_name')
    //     //     ->where('sacco_members.member_deleted', '<>', 'Y')
    //     //     ->where('sacco_members.member_active', 'Y')

    //     //     ->where(function ($query) use ($pms_srch) {
    //     //         $query->where('sacco_department.department_name', 'like', $pms_srch)
    //     //             ->orWhere('sacco_position.position_name', 'like', $pms_srch)
    //     //             ->orWhere('sacco_company.company_name', 'like', $pms_srch)
    //     //             ->orWhere('sacco_members.member_name', 'like', $pms_srch)
    //     //             ->orWhere('sacco_members.member_sacco_id', 'like', $pms_srch)
    //     //             ->orWhere('sacco_members.member_national_id', 'like', $pms_srch)
    //     //             ->orWhere('sacco_members.member_email', 'like', $pms_srch);
    //     //     })
    //     //     ->where('sacco_members.member_date_joined', '<=', $monthlyCutOfDay)
    //     //     ->orderBy($orderby, $sort_order)
    //     //     ->get();

    //     // Fetch loan types
    //     $loanTypes = DB::table('sacco_loan_types')
    //         ->where('loan_type_deleted', '<>', 'Y')
    //         ->orderBy('loan_type_name')
    //         ->get();

    //     // Fetch member loan payments
    //     foreach ($members as $member) {
    //         $member->loan_contributions = [];
    //         $member->total_deduction = $member->member_share_contr_monthly + $member->member_fosa_contr_monthly;

    //         foreach ($loanTypes as $loanType) {
    //             $loanPayments = $this->getMemberLoanPayments($loanType->loan_type_id, $member->member_id, $currentPeriod->period_name);
    //             $member->loan_contributions[$loanType->loan_type_id] = $loanPayments;
    //             $member->total_deduction += $loanPayments;
    //         }
    //     }

    //     $data = [
    //         'members' => $members,
    //         'loanTypes' => $loanTypes,
    //         'currentPeriod' => $currentPeriod,
    //         'pms_srch' => $pms_srch,
    //         'orderby' => $orderby,
    //         'sort_order' => $sort_order,
    //     ];

    //     return view('contributions.list', $data);
    // }

    private function getMemberLoanPayments($ln_type, $mem_no, $currentPeriod)
    {
        // Fetch the minimum loan amount billable from the defaults
        $min_loan_amount_bill_able = DB::table('sacco_defaults')
            ->where('default_name', 'min_loan_amount_bill_able')
            ->value('default_value');

        if (!is_numeric($min_loan_amount_bill_able)) {
            $min_loan_amount_bill_able = 1;
        }

        // Query to sum the loan monthly repayment amount
        $total_m_emi = DB::table('sacco_loans')
            ->where('loan_member', $mem_no)
            ->where('loan_loan_type', $ln_type)
            ->where('loan_amount', '>', 0)
            ->where('loan_loan_paid', '<', DB::raw('loan_amount'))
            ->where('loan_stoped', '<>', 'Y')
            ->where('loan_start_deduction_period', '<=', $this->currentPeriod->period_name)
            ->where('sacco_loans.loan_taken_period', '<=', $this->currentPeriod->period_name)
            ->whereRaw('loan_amount - loan_loan_paid > ?', [$min_loan_amount_bill_able])
            ->sum('loan_monthly_repayment_amount');




        return $total_m_emi;
    }

    public function reportsAccountsLedger(Request $request)
    {
        $period = $request->input('period', now()->format('Ym'));
        $pfrom = $request->input('pfrom', $period);
        $pto = $request->input('pto', $period);
        $main_account_code = $request->input('main_account_code', '');
        $sub_account_name = $request->input('sub_account_name', '');
        $accounts_trans_dat_date = $request->input('accounts_trans_dat_date', '');
        $accounts_trans_doc_no = $request->input('accounts_trans_doc_no', '');
        $accounts_trans_decription = $request->input('accounts_trans_decription', '');

        $transactionsQuery = DB::table('sacco_accounts_trans')
            ->join('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->select(
                'sacco_main_account.main_account_code',
                'sacco_sub_account.sub_account_code',
                'sacco_sub_account.sub_account_name',
                'sacco_accounts_trans.accounts_trans_period',
                'sacco_accounts_trans.accounts_trans_dat_date',
                'sacco_accounts_trans.accounts_trans_doc_no',
                'sacco_accounts_trans.accounts_trans_decription',
                'sacco_accounts_trans.accounts_trans_debit',
                'sacco_accounts_trans.accounts_trans_credit'
            )
            ->whereBetween('accounts_trans_period', [$pfrom, $pto])
            ->where('sacco_main_account.main_account_code', 'like', "%{$main_account_code}%")
            ->where('sacco_sub_account.sub_account_code', 'like', "%{$main_account_code}%")
            ->where('sacco_sub_account.sub_account_name', 'like', "%{$sub_account_name}%")
            ->where('sacco_accounts_trans.accounts_trans_dat_date', 'like', "%{$accounts_trans_dat_date}%")
            ->where('sacco_accounts_trans.accounts_trans_doc_no', 'like', "%{$accounts_trans_doc_no}%")
            ->where('sacco_accounts_trans.accounts_trans_decription', 'like', "%{$accounts_trans_decription}%")
            ->orderBy('sacco_accounts_trans.accounts_trans_id', 'asc')
            ->get();

        $total_debits = $transactionsQuery->sum('accounts_trans_debit');
        $total_credits = $transactionsQuery->sum('accounts_trans_credit');

        $data = [
            'transactions' => $transactionsQuery,
            'pfrom' => $pfrom,
            'pto' => $pto,
            'main_account_code' => $main_account_code,
            'sub_account_name' => $sub_account_name,
            'accounts_trans_dat_date' => $accounts_trans_dat_date,
            'accounts_trans_doc_no' => $accounts_trans_doc_no,
            'accounts_trans_decription' => $accounts_trans_decription,
            'total_debits' => $total_debits,
            'total_credits' => $total_credits,
            'period' => $period,
        ];

        return view('reports.accounts.ledger', $data);
    }

    public function transferShareToCapitalShares(Request $request)
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        $modifyMemberSharesJournalEntries = DB::table('sacco_defaults')
            ->where('default_name', 'modify_member_shares_journal_entries')
            ->value('default_value');

        $members = DB::table('sacco_members')
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->get();

        $errors = [];

        if ($request->isMethod('post')) {
            $sanitizedData = $request->all();
            $submitted = (int)$sanitizedData['submitted'];

            //dd($sanitizedData);

            for ($ix = 0; $ix < $submitted; $ix++) {

                if (!empty($sanitizedData["member_name"][$ix]) && $sanitizedData["amount"][$ix] > 0 && !empty($sanitizedData["member_namex1"][$ix]) && !empty($sanitizedData["share_date_paid"][$ix])) {
                    //dd($sanitizedData["member_name"][0]);
                    $fromMember = $this->getMemberFromString($sanitizedData["member_name"][$ix]);
                    $toMember = $this->getMemberFromString($sanitizedData["member_namex1"][$ix]);
                    $amount = $this->convertCurrency($sanitizedData["amount"][$ix]);
                    $shareDocNo = $sanitizedData["share_doc_no"][$ix];
                    $shareDescription = substr($sanitizedData["share_description"][$ix], 0, 20); // Truncate to 20 characters
                    $shareDatePaid = $sanitizedData["share_date_paid"][$ix];


                    if (empty($shareDescription)) {
                        $errors[] = "Error, missing description in row " . ($ix + 1);
                    }
                    if (empty($shareDocNo)) {
                        $errors[] = "Error, missing document number in row " . ($ix + 1);
                    }

                    if ($fromMember && $toMember && is_numeric($amount) && $amount > 0 && !empty($shareDescription) && !empty($shareDocNo)) {

                        $fullDescription = $shareDescription . " (transfer from {$fromMember->member_name} - {$fromMember->member_sacco_id} to {$toMember->member_name} - {$toMember->member_sacco_id})";
                        $fullDescription = substr($fullDescription, 0, 100); // Truncate to 100 characters
                        // dd($fullDescription);
                        $this->processShareTransfer($fromMember, $toMember, $amount, $shareDocNo, $fullDescription, $shareDatePaid, $currentPeriod->period_name);
                    } else {
                        $errors[] = "Error in row " . ($ix + 1);
                    }
                }
            }

            if (count($errors) > 0) {
                return redirect()->route('transfer.share.to.capital.shares')->withErrors($errors)->withInput();
            } else {
                return redirect()->route('transfer.share.to.capital.shares')->with('success', 'Transfer completed successfully.');
            }
        }

        $data = [
            'members' => $members,
            'currentPeriod' => $currentPeriod,
            'modifyMemberSharesJournalEntries' => $modifyMemberSharesJournalEntries
        ];

        return view('shares.transfer_share_to_capital_shares', $data);
    }

    private function getMemberFromString($memberString)
    {
        $parts = explode("- (", $memberString);
        $name = trim($parts[0]);
        $id = trim(substr($parts[1], 0, -1));

        return DB::table('sacco_members')
            ->where('member_name', $name)
            ->where('member_sacco_id', $id)
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->first();
    }

    private function convertCurrency($amount)
    {
        return preg_replace('/[^0-9]/', '', $amount);
    }

    private function processShareTransfer($fromMember, $toMember, $amount, $docNo, $description, $datePaid, $period)
    {
        $defaultShareAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_account')->value('default_value');
        $defaultShareCapitalAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_capital_account')->value('default_value');

        // Check if the values are numeric and not empty
        if (is_numeric($defaultShareAccount) || is_numeric($defaultShareCapitalAccount)) {
            // If either is numeric, return or throw an error
            return back()->withErrors([
                'error' => 'An error occurred: Default share account or capital account returned a numeric value.'
            ]);
        }

        DB::transaction(function () use ($fromMember, $toMember, $amount, $docNo, $description, $datePaid, $period, $defaultShareAccount, $defaultShareCapitalAccount) {
            $this->insertShareRecord($fromMember->member_id, $amount * -1, 'JOURNAL', $period, "$description ($toMember->member_name)", $docNo, $datePaid);
            $this->updateMemberTotalShares($fromMember->member_id, $amount * -1);

            $this->insertCapitalShareRecord($toMember->member_id, $amount, 'JOURNAL', $period, "$description ($fromMember->member_name)", $docNo, $datePaid);
            $this->updateMemberTotalShares($toMember->member_id, $amount);

            $this->updateSaccoAccountsTrans($defaultShareAccount, $amount, 0, $docNo, "$description ($toMember->member_name)", $datePaid, $period, "Member to Shares transfer");
            $this->updateSaccoAccountsTrans($defaultShareCapitalAccount, 0, $amount, $docNo, "$description ($fromMember->member_name)", $datePaid, $period, "Member to Shares transfer");
        });
    }


    // private function processShareTransfer($fromMember, $toMember, $amount, $docNo, $description, $datePaid, $period)
    // {
    //     $defaultShareAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_account')->value('default_value');
    //         $defaultShareCapitalAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_capital_account')->value('default_value');

    //     DB::transaction(function () use ($fromMember, $toMember, $amount, $docNo, $description, $datePaid, $period) {
    //         $this->insertShareRecord($fromMember->member_id, $amount * -1, 'JOURNAL', $period, "$description ($toMember->member_name)", $docNo, $datePaid);
    //         $this->updateMemberTotalShares($fromMember->member_id, $amount * -1);

    //         $this->insertCapitalShareRecord($toMember->member_id, $amount, 'JOURNAL', $period, "$description ($fromMember->member_name)", $docNo, $datePaid);
    //         $this->updateMemberTotalShares($toMember->member_id, $amount);

    //         $this->updateSaccoAccountsTrans($defaultShareAccount, $amount, 0, $docNo, "$description ($toMember->member_name)", $datePaid, $period, "Member to Shares transfer");
    //         $this->updateSaccoAccountsTrans($defaultShareCapitalAccount, 0, $amount, $docNo, "$description ($fromMember->member_name)", $datePaid, $period, "Member to Shares transfer");
    //     });
    // }

    private function insertShareRecord($memberId, $amount, $paidBy, $period, $description, $docNo, $datePaid)
    {

        try {
            DB::table('sacco_shares')->insert([
                'share_member_id' => $memberId,
                'share_amount_paying' => $amount,
                'share_paid_by' => $paidBy,
                'share_period' => $period,
                'share_description' => substr($description, 0, 100), // Truncate to 100 characters
                'share_doc_no' => $docNo,
                'share_date_paid' => $datePaid,
                'share_end_month_proc' => 'N',
                'share_by' => Auth::id(),
                'share_ip' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            dd("Error inserting share record: " . $e->getMessage(), [
                'memberId' => $memberId,
                'amount' => $amount,
                'paidBy' => $paidBy,
                'period' => $period,
                'description' => substr($description, 0, 100),
                'docNo' => $docNo,
                'datePaid' => $datePaid,
                'errorTrace' => $e->getTrace(),
            ]);
        }
    }

    private function insertCapitalShareRecord($memberId, $amount, $paidBy, $period, $description, $docNo, $datePaid)
    {

        DB::table('sacco_capital_shares')->insert([
            'share_capitalmember_id' => $memberId,
            'share_capitalamount_paying' => $amount,
            'share_capitalpaid_by' => $paidBy,
            'share_capitalperiod' => $period,
            'share_capitaldescription' => substr($description, 0, 100), // Truncate to 100 characters
            'share_capitaldoc_no' => $docNo,
            'share_capitaldate_paid' => $datePaid,
            'share_capitalend_month_proc' => 'N',
            'share_capitalby' => Auth::id(),
            'share_capitalip' => request()->ip(),
        ]);
    }

    //eports.members.status
    private function updateMemberTotalShares($memberId, $amount)
    {
        DB::table('sacco_members')->where('member_id', $memberId)->increment('member_total_share', $amount);
    }

    public function reportsMembersStatus(Request $request)
    {
        return view('reports.members.status');
    }

    public function reportsMembersStatusData(Request $request)
    {
        $status = $request->get('status', 'Y');
        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 5);

        $membersQuery = DB::table('sacco_members')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->select(
                'sacco_members.member_name',
                'sacco_company.company_name',
                'sacco_members.member_national_id',
                'sacco_members.member_sacco_id',
                'sacco_members.member_active',
                'sacco_members.member_id'
            )
            ->where('sacco_members.member_active', '=', $status)
            ->orderBy('sacco_members.member_name', 'asc')
            ->offset($offset)
            ->limit($limit);

        $members = $membersQuery->get();

        $loanTypes = DB::table('sacco_loan_types')->orderBy('loan_type_name', 'asc')->get();

        $membersData = collect();

        foreach ($members as $member) {
            $totalShares = DB::table('sacco_shares')
                ->where('share_member_id', $member->member_id)
                ->sum('share_amount_paying');

            $totalCapital = DB::table('sacco_capital_shares')
                ->where('share_capitalmember_id', $member->member_id)
                ->sum('share_capitalamount_paying');

            $loans = [];
            foreach ($loanTypes as $type) {
                $totalLoanAmount = DB::table('sacco_loans')
                    ->where('loan_member', $member->member_id)
                    ->where('loan_loan_type', $type->loan_type_id)
                    ->sum('loan_amount');

                $totalPayments = DB::table('sacco_loan_payments')
                    ->join('sacco_loans', 'sacco_loan_payments.loan_payments_loan_id', '=', 'sacco_loans.loan_id')
                    ->where('sacco_loans.loan_member', $member->member_id)
                    ->where('sacco_loans.loan_loan_type', $type->loan_type_id)
                    ->sum('sacco_loan_payments.loan_payments_amount');

                $loans[$type->loan_type_name] = $totalLoanAmount - $totalPayments;
            }

            $membersData->push([
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'company_name' => $member->company_name,
                'member_national_id' => $member->member_national_id,
                'member_sacco_id' => $member->member_sacco_id,
                'member_active' => $member->member_active,
                'total_shares' => $totalShares,
                'total_capital' => $totalCapital,
                'loans' => $loans
            ]);
        }

        return response()->json([
            'members' => $membersData,
            'loanTypes' => $loanTypes
        ]);
    }


    public function loansApply()
    {
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();

        $loanCategories = DB::table('sacco_loan_category')
            ->where('loan_category_deleted', '<>', 'Y')
            ->orderBy('loan_category_name')
            ->get();

        $maximumNoOfGuarantors = DB::table('sacco_defaults')
            ->where('default_name', 'maximum_no_of_guarantors')
            ->value('default_value');

        $memberLoans = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select('sacco_loans.*', 'sacco_loan_types.loan_type_name', DB::raw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) as loan_balance'))
            ->where('sacco_loans.loan_member', auth()->user()->id)
            ->where('sacco_loans.loan_amount', '>', DB::raw('sacco_loans.loan_loan_paid'))
            ->where('sacco_loans.loan_stoped', 'N')
            ->get();
        
        $max_guarantor_factor_self = (float) DB::table('sacco_defaults')
            ->where('default_name', 'max_guarantor_factor_self')
            ->value('default_value') ?? 1;
        
        $member = DB::table('sacco_members')
            ->where('member_id', auth()->user()->id)
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->first();
        
            $selfGuaranteeAvailable = max(0, ($member->member_total_share - $member->member_tied_shares_self)) * $max_guarantor_factor_self;

 
        return view('loans.apply', compact(
            'loanTypes',
            'loanCategories',
            'maximumNoOfGuarantors',
            'memberLoans',
            'selfGuaranteeAvailable'
        ));

    }





    public function submitLoanApplication(Request $request)
    {
        $logged_in_user = auth()->id();
        // Define validation rules
        $request->validate([
            'batch_trans_member_id' => 'required|exists:sacco_members,member_id',
            'batch_trans_member_name' => 'required|string',
            'batch_trans_loan_amount' => 'required|numeric|min:1',
            'batch_trans_loan_type' => 'required|exists:sacco_loan_types,loan_type_id',
            'batch_trans_loan_category' => 'required|exists:sacco_loan_category,loan_category_id',
            'batch_trans_loan_duration' => 'required|integer|min:1|max:100',
            'batch_trans_description' => 'required|string|max:50',
            'batch_trans_commission' => 'nullable|numeric',
            'batch_trans_loan_to_top_up' => 'nullable|integer|exists:sacco_loans,loan_id',
            'batch_trans_pay1' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
            'batch_trans_pay2' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:200',
        ], [
            'batch_trans_pay1.mimes' => 'File must be of type jpg, jpeg, png, gif.',
            'batch_trans_pay1.max' => 'File must be less than 200KB.',
            'batch_trans_pay2.mimes' => 'File must be of type jpg, jpeg, png, gif.',
            'batch_trans_pay2.max' => 'File must be less than 200KB.',
        ]);

        $data = $request->all();
        $nmsg = '';

        //  dd( $data);



        // Check if the current route matches 'loans.application.submit'
        if ($request->route()->getName() === 'loans.application.submit') {
            // Check if the member ID matches the logged-in user
            if ($request->input('batch_trans_member_id') != $logged_in_user) {
                return redirect()->back()->withErrors(['error' => 'Error: Wrong user trying to access the system.']);
            }
        }


        // Loan Category and Type validation
        $loanCategory = DB::table('sacco_loan_category')
            ->where('loan_category_id', $data['batch_trans_loan_category'])
            ->first();

        if (!$loanCategory) {
            $nmsg .= "Error, invalid loan category. ";
        }

        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $data['batch_trans_loan_type'])
            ->first();

        if (!$loanType) {
            $nmsg .= "Error, invalid loan type selected. ";
        }

        // Member validation

        $member = DB::table('sacco_members')
            ->where('member_id', $data['batch_trans_member_id'])
            ->where('member_active', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->first();

        if (!$member) {
            $nmsg .= "Error, invalid member taking loan, not found in the database. ";
        } elseif (strtotime($member->member_date_joined) > strtotime("-{$loanType->loan_type_qualification_period} months")) {
            $nmsg .= "Error, this member must be {$loanType->loan_type_qualification_period} months old in the sacco before taking this type of loan. ";
        }

        // Loan Amount validation
        $loanAmount = floatval($data['batch_trans_loan_amount']);
        if ($loanAmount < 1 || !is_numeric($loanAmount)) {
            $nmsg .= "Error, invalid loan amount entered. ";
        } elseif ($loanAmount > $loanType->loan_type_max_amount) {
            $nmsg .= "Error, loan taken cannot exceed {$loanType->loan_type_max_amount}. ";
        }

        // Commission validation
        $commission = !empty($data['batch_trans_commission']) ? floatval($data['batch_trans_commission']) : 0;
        if (!is_numeric($commission)) {
            $nmsg .= "Error, invalid loan commission amount entered. ";
        }

        // Check if member has exceeded maximum allowable loans
        $loan_individualize = DB::table('sacco_defaults')
            ->where('default_name', 'loan_individualize')
            ->value('default_value') ?? 'N';

        $member_cumm_loan_temp = DB::table('sacco_loans')
            ->where('loan_member', $member->member_id)
            ->where('loan_loan_type', $data['batch_trans_loan_type'])
            ->sum(DB::raw('loan_amount - loan_loan_paid')) ?? 0;



        if (($loanType->loan_type_share_factor * ($member->member_total_share + $member->member_total_share_capital) - $member_cumm_loan_temp) < $loanAmount && $loanType->loan_type_share_factor > 0) {
            if (empty($data['batch_trans_loan_to_top_up'])) {
                $nmsg .= "Error, member has exceeded his/her loan amount limit. ";
            }
        }
        // dd($data['batch_trans_loan_to_top_up']);
        if (!empty($data['batch_trans_loan_to_top_up'])) {


            $topupLoanId = $data['batch_trans_loan_to_top_up'];

            $topupLoan = DB::table('sacco_loans')
                ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
                ->where('loan_member', $member->member_id)
                ->where('loan_id', $topupLoanId)
                ->first();


            if (!$topupLoan) {
                $nmsg .= "Error, invalid TOP-UP loan. ";
            }

            if ($data['batch_trans_loan_amount'] < ($topupLoan->loan_amount - $topupLoan->loan_loan_paid)) {

                $nmsg .= "The loan taken must be more than the balance of the loan you want to top. ";
            } elseif ($topupLoan->loan_amount <= $topupLoan->loan_loan_paid) {
                $nmsg .= "Error, this loan cannot be topped up. ";
            } elseif (($topupLoan->loan_amount - $topupLoan->loan_loan_paid) >= $loanAmount) {
                $nmsg .= "Error, the amount in the new loan must be more than the loan balance. ";
            } else {
                $batch_trans_loan_to_top_up_amount_bal = $loanAmount - ($topupLoan->loan_amount - $topupLoan->loan_loan_paid);
                $batch_trans_loan_to_top_up_amount_loan_to_pay = ($topupLoan->loan_amount - $topupLoan->loan_loan_paid);
                $batch_trans_loan_to_top_up_id = $topupLoan->loan_id;
            }
        }




        // Guarantor validation
        $maximumNoOfGuarantors = DB::table('sacco_defaults')
            ->where('default_name', 'maximum_no_of_guarantors')
            ->value('default_value');

        $totalGuaranteed = 0;
        // Define the two default names to ensure
        $requiredDefaults = ['max_guarantor_factor', 'max_guarantor_factor_self'];

        foreach ($requiredDefaults as $defaultName) {
            $exists = DB::table('sacco_defaults')
                ->where('default_name', $defaultName)
                ->exists();

            if (!$exists) {
                DB::table('sacco_defaults')->insert([
                    'default_name' => $defaultName,
                    'default_value' => 1, // Default is always 1
                    'default_transdate' => now(),
                    'default_userid' => auth()->id(),
                    'default_ip' => request()->ip(),
                ]);
            }
        }

        // Safe to retrieve afterward
        $max_guarantor_factor = DB::table('sacco_defaults')
            ->where('default_name', 'max_guarantor_factor')
            ->value('default_value') ?? 1;

        $max_guarantor_factor_self = DB::table('sacco_defaults')
            ->where('default_name', 'max_guarantor_factor_self')
            ->value('default_value') ?? 1;


        $guarantors = [];
        for ($i = 0; $i < $maximumNoOfGuarantors; $i++) {
            $guarantorName = $data["guarantors_guarantor_name"][$i] ?? null;
            $guarantorAmount = !empty($data["guarantors_amount_guaranteed"][$i]) ? floatval($data["guarantors_amount_guaranteed"][$i]) : 0;

            if (!empty($guarantorName)) {
                $guarantor = DB::table('sacco_members')
                    ->where('member_name', explode(" - (", $guarantorName)[0])
                    ->where('member_sacco_id', trim(explode(" - (", $guarantorName)[1], ")"))
                    ->where('member_active', 'Y')
                    ->where('member_deleted', '<>', 'Y')
                    ->first();

                if (!$guarantor) {
                    $nmsg .= "Error, guarantor {$guarantorName} has no file. ";
                } elseif ($data['batch_trans_member_id'] != $guarantor->member_id) {
                    $totalGuarantorAmount = DB::table('sacco_loan_batch_guarantors_members')
                        ->where('guarantors_guarantor_id', $guarantor->member_id)
                        ->where('guarantors_approved', '<>', 'Y')
                        ->where('guarantors_deleted', '<>', 'Y')
                        ->sum('guarantors_amount_guaranteed') ?? 0;

                    // $nmsg .= "Error, guarantor {$guarantorName}, in row " . ($i + 1) . " has over guaranteed. ".($guarantor->member_total_share * $max_guarantor_factor - $guarantor->member_tied_shares)."-- ".($guarantorAmount);

                    if (($guarantor->member_total_share * $max_guarantor_factor - $guarantor->member_tied_shares) < ($guarantorAmount)) {
                        $nmsg .= "Error, guarantor {$guarantorName}, in row " . ($i + 1) . " has over guaranteed. ";
                    }
                } else {
                    $availableSelfGuaranteeLimit = ($guarantor->member_total_share - $guarantor->member_tied_shares_self) * $max_guarantor_factor_self;

                    if ($availableSelfGuaranteeLimit < $guarantorAmount) {
                        $nmsg .= "Error, {$guarantorName} has over guaranteed themselves. ";
                    }
                }

                $guarantors[] = [
                    'id' => $guarantor->member_id,
                    'amount' => $guarantorAmount
                ];
                $totalGuaranteed += $guarantorAmount;
            }
        }

        // Under-guarantee check
        $batch_trans_loan_guaranteed = $loanAmount * $loanType->loan_type_guaranteable_percent / 100;

        if ($loanType->loan_type_guaranteable_percent > 0) {
            if ($batch_trans_loan_guaranteed > $totalGuaranteed) {
                $nmsg .= "Error, this member has been under guaranteed. ";
            } else {
                $g_factor = $batch_trans_loan_guaranteed / $totalGuaranteed;
            }
        }

        if (empty($nmsg) && $loanType->loan_type_duration < $data['batch_trans_loan_duration']) {
            $nmsg .= "Error, invalid loan repayment period. ";
        }

        // Final validation before saving
        if (!empty($nmsg)) {
            return redirect()->back()->withErrors($nmsg)->withInput();
        }

        // Handle file uploads and get file paths
        $payslip1Path = null;
        if ($request->hasFile('batch_trans_pay1')) {
            $file = $request->file('batch_trans_pay1');
            $payslip1Path = $file->storeAs('uploads/payslips', Auth::user()->name . '_' . time() . '_1.' . $file->getClientOriginalExtension());
        }

        $payslip2Path = null;
        if ($request->hasFile('batch_trans_pay2')) {
            $file = $request->file('batch_trans_pay2');
            $payslip2Path = $file->storeAs('uploads/payslips', Auth::user()->name . '_' . time() . '_2.' . $file->getClientOriginalExtension());
        }

        //    dd( $payslip2Path);
        // Select the correct insurance calculation function based on the sacco_defaults value
        $loan_interest_insurance = DB::table('sacco_defaults')
            ->where('default_name', 'loan_interest_insurance')
            ->value('default_value');

        if (empty($loan_interest_insurance)) {
            $loan_interest_insurance = "calc_loan_interest_insurance";
        }

        $insuranceValues = $this->$loan_interest_insurance($data['batch_trans_loan_type'], $loanAmount, $data['batch_trans_loan_duration']);

        $insertData = [
            'batch_trans_batch_id' => Auth::user()->id,
            'batch_trans_loan_type' => $data['batch_trans_loan_type'],
            'batch_trans_loan_category' => $data['batch_trans_loan_category'],
            'batch_trans_loan_amount' => $loanAmount,
            'batch_trans_member_id' => $data['batch_trans_member_id'],
            'batch_trans_loan_duration' => $data['batch_trans_loan_duration'],
            'batch_trans_monthly_payment' => $insuranceValues[1],
            'batch_trans_monthly_payment_principal' => $insuranceValues[3],
            'batch_trans_doc_no' => 'N/A',
            'batch_trans_description' => $data['batch_trans_description'],
            'batch_trans_commission' => $commission,
            'batch_trans_loan_to_top_up' => $batch_trans_loan_to_top_up_id ?? 0,
            'batch_trans_insurance' => $insuranceValues[4],
            'batch_trans_expected_interest' => $insuranceValues[2],
            'batch_trans_loan_guaranteed' => $batch_trans_loan_guaranteed,
            'batch_trans_loan_to_top_up_amount' => $batch_trans_loan_to_top_up_amount_bal ?? 0,
            'batch_trans_by' => Auth::user()->id,
            'batch_trans_ip' => $request->ip(),
            'batch_trans_payslip1' => $payslip1Path,
            'batch_trans_payslip2' => $payslip2Path,
        ];

        // Generate the SQL query
        $query = DB::table('sacco_loan_batch_trans_members')->toSql();
        // dd($query, $insertData);

        // Execute the insertion
        $loanId = DB::table('sacco_loan_batch_trans_members')->insertGetId($insertData);

        // Save guarantors with prorated amounts
        foreach ($guarantors as $guarantor) {
            $proratedAmount = $guarantor['amount'] * $g_factor;
            DB::table('sacco_loan_batch_guarantors_members')->insert([
                'guarantors_loan_batch_trans_id' => $loanId,
                'guarantors_guarantor_id' => $guarantor['id'],
                'guarantors_amount_guaranteed' => $proratedAmount,
                'guarantors_by' => Auth::user()->id,
                'guarantors_ip' => $request->ip(),
            ]);
        }

        return redirect()->route('loans.apply')->with('success', 'Loan application submitted successfully.');
    }



    // Define the insurance calculation functions here
    private function calc_loan_interest_insurance($loan_type_Id_f, $loan_amount_f, $repayment_period_f)
    {
        $insu = $loan_amount_f * 0.5 / 100;

        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $loan_type_Id_f)
            ->first();

        if ($loanType->loan_type_insurable != "Y") {
            $insu = 0;
        }

        if ($loanType->loan_type_interest_type == "FIXED INTEREST") {
            $interest_amount_payable_f = round(($loan_amount_f + $insu) * $loanType->loan_type_interest / 100, 0);
            $emi = ceil(($loan_amount_f + $interest_amount_payable_f + $insu) / $repayment_period_f);
            $monthly_repayment_principal_f = ($loan_amount_f + $insu) / $repayment_period_f;
        } else {
            $loan_amount_f1 = $loan_amount_f + $insu;
            $interest_percent_f = $loanType->loan_type_interest / 12 / 100;

            $emi = ($loan_amount_f1 * $interest_percent_f) * pow(1 + $interest_percent_f, $repayment_period_f) / (pow(1 + $interest_percent_f, $repayment_period_f) - 1);
            $interest_amount_payable_f = ($emi * $repayment_period_f) - $loan_amount_f1;
            $monthly_repayment_principal_f = $emi - ($loan_amount_f1 * $interest_percent_f);
            $emi = ceil($emi);
            $monthly_repayment_principal_f = ceil($monthly_repayment_principal_f);
        }

        return ["", $emi, $interest_amount_payable_f, $monthly_repayment_principal_f, $insu];
    }

    private function calc_loan_interest_insurance_dhl($loan_type_Id_f, $loan_amount_f, $repayment_period_f)
    {
        // Implement the DHL calculation logic here
        // Return an array with calculated values as required
        $insu = 0;

        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $loan_type_Id_f)
            ->first();

        if ($loanType->loan_type_insurable != "Y") {
            $insu = 0;
        }

        if ($loanType->loan_type_interest_type == "FIXED INTEREST") {
            $interest_amount_payable_f = round(($loan_amount_f + $insu) * $loanType->loan_type_interest / 100, 0);
            $emi = ceil(($loan_amount_f + $interest_amount_payable_f + $insu) / $repayment_period_f);
            $monthly_repayment_principal_f = ($loan_amount_f + $insu) / $repayment_period_f;
        } else {
            $loan_amount_f1 = $loan_amount_f + $insu;
            $interest_percent_f = $loanType->loan_type_interest / 12 / 100;

            $emi = ($loan_amount_f1 / $repayment_period_f) + ($loan_amount_f1 * $interest_percent_f);
            $EMI = ceil($emi);
            $interest_amount_payable_f = $loan_amount_f1 * $interest_percent_f;
            $monthly_repayment_principal_f = $EMI - ($loan_amount_f1 * $interest_percent_f);
            $emi = $EMI;
            $monthly_repayment_principal_f = ceil($monthly_repayment_principal_f);
        }

        return ["", $emi, $interest_amount_payable_f, $monthly_repayment_principal_f, $insu];
    }

    private function calc_loan_interest_insurance_isave($loan_type_Id_f, $loan_amount_f, $repayment_period_f)
    {
        // Implement the ISAVE calculation logic here
        // Return an array with calculated values as required
        $insu = $loan_amount_f * 1 / 100;

        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $loan_type_Id_f)
            ->first();

        if ($loanType->loan_type_insurable != "Y") {
            $insu = 0;
        }

        if ($loanType->loan_type_interest_type == "FIXED INTEREST") {
            $interest_amount_payable_f = round(($loan_amount_f + $insu) * $loanType->loan_type_interest / 100, 0);
            $emi = ceil(($loan_amount_f + $interest_amount_payable_f + $insu) / $repayment_period_f);
            $monthly_repayment_principal_f = ($loan_amount_f + $insu) / $repayment_period_f;
        } else {
            $loan_amount_f1 = $loan_amount_f + $insu;
            $interest_percent_f = $loanType->loan_type_interest / 12 / 100;

            $emi = ($loan_amount_f1 * $interest_percent_f) * pow(1 + $interest_percent_f, $repayment_period_f) / (pow(1 + $interest_percent_f, $repayment_period_f) - 1);
            $interest_amount_payable_f = ($emi * $repayment_period_f) - $loan_amount_f1;
            $monthly_repayment_principal_f = $emi - ($loan_amount_f1 * $interest_percent_f);
            $emi = ceil($emi);
            $monthly_repayment_principal_f = ceil($monthly_repayment_principal_f);
        }

        return ["", $emi, $interest_amount_payable_f, $monthly_repayment_principal_f, $insu];
    }

    private function calc_loan_interest_insurance_plan($loan_type_Id_f, $loan_amount_f, $repayment_period_f)
    {
        // Implement the PLAN calculation logic here
        // Return an array with calculated values as required
        $insu = $loan_amount_f * 2 / 100;

        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $loan_type_Id_f)
            ->first();

        if ($loanType->loan_type_insurable != "Y") {
            $insu = 0;
        }

        if ($loanType->loan_type_interest_type == "FIXED INTEREST") {
            $interest_amount_payable_f = round(($loan_amount_f + $insu) * $loanType->loan_type_interest / 100, 0);
            $emi = ceil(($loan_amount_f + $interest_amount_payable_f + $insu) / $repayment_period_f);
            $monthly_repayment_principal_f = ($loan_amount_f + $insu) / $repayment_period_f;
        } else {
            $loan_amount_f1 = $loan_amount_f + $insu;
            $interest_percent_f = $loanType->loan_type_interest / 12 / 100;

            $emi = ($loan_amount_f1 * $interest_percent_f) * pow(1 + $interest_percent_f, $repayment_period_f) / (pow(1 + $interest_percent_f, $repayment_period_f) - 1);
            $interest_amount_payable_f = ($emi * $repayment_period_f) - $loan_amount_f1;
            $monthly_repayment_principal_f = $emi - ($loan_amount_f1 * $interest_percent_f);
            $emi = ceil($emi);
            $monthly_repayment_principal_f = ceil($monthly_repayment_principal_f);
        }

        return ["", $emi, $interest_amount_payable_f, $monthly_repayment_principal_f, $insu];
    }


    public function listGuaranteeRequests(Request $request)
    {
        $logged_in_user = Auth::id();
        $myIP = $request->ip();
        $transdate = now();

        // Handle deletion of guarantee
        if ($request->has('rid') && is_numeric($request->input('rid'))) {
            DB::table('sacco_loan_batch_guarantors_members')
                ->where('guarantors_loan_batch_trans_id', $request->input('rid'))
                ->where('guarantors_guarantor_id', $logged_in_user)
                ->update([
                    'guarantors_deleted' => 'Y',
                    'guarantors_deleted_by' => $logged_in_user,
                    'guarantors_deleted_on' => $transdate,
                    'guarantors_deleted_ip' => $myIP
                ]);
        }

        // Handle approval of guarantee
        if ($request->has('yid') && is_numeric($request->input('yid'))) {
            DB::table('sacco_loan_batch_guarantors_members')
                ->where('guarantors_loan_batch_trans_id', $request->input('yid'))
                ->where('guarantors_guarantor_id', $logged_in_user)
                ->update(['guarantors_approved' => 'Y']);
        }

        // Fetch loans pending guarantee approval
        $loans = DB::table('sacco_loan_category')
            ->join('sacco_loan_batch_trans_members', 'sacco_loan_category.loan_category_id', '=', 'sacco_loan_batch_trans_members.batch_trans_loan_category')
            ->join('sacco_loan_types', 'sacco_loan_batch_trans_members.batch_trans_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_members', 'sacco_loan_batch_trans_members.batch_trans_member_id', '=', 'sacco_members.member_id')
            ->where('batch_trans_updated', '<>', 'Y')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->whereIn('batch_trans_id', function ($query) use ($logged_in_user) {
                $query->select('guarantors_loan_batch_trans_id')
                    ->from('sacco_loan_batch_guarantors_members')
                    ->where('guarantors_guarantor_id', $logged_in_user)
                    ->where('guarantors_approved', '<>', 'Y')
                    ->where('guarantors_deleted', '<>', 'Y');
            })
            ->get();

        return view('loans.guarantee_requests', compact('loans'));
    }
    public function listLoansPendingApproval()
    {
        $member_id = Auth::user()->member_id;

        // Check for deletion request
        if (request()->has('did')) {
            $loanId = request()->query('did');

            $loan = DB::table('sacco_loan_batch_trans_members')
                ->where('batch_trans_member_id', $member_id)
                ->where('batch_trans_id', $loanId)
                ->where('batch_trans_deleted', '<>', 'Y')
                ->where('batch_trans_updated', '<>', 'Y')
                ->first();

            if ($loan) {
                DB::table('sacco_loan_batch_trans_members')
                    ->where('batch_trans_id', $loanId)
                    ->update([
                        'batch_trans_deleted' => 'Y',
                        'batch_trans_deleted_by' => $member_id,
                        'batch_trans_deleted_on' => now(),
                        'batch_trans_deleted_ip' => request()->ip(),
                    ]);
                return redirect()->route('loans.pending.approval')->with('success', 'Record successfully deleted.');
            }
        }

        $loans = DB::table('sacco_loan_batch_trans_members')
            ->join('sacco_loan_category', 'sacco_loan_batch_trans_members.batch_trans_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->join('sacco_loan_types', 'sacco_loan_batch_trans_members.batch_trans_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_members', 'sacco_loan_batch_trans_members.batch_trans_member_id', '=', 'sacco_members.member_id')
            ->where('batch_trans_member_id', $member_id)
            ->where('batch_trans_updated', '<>', 'Y')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->select('sacco_loan_batch_trans_members.*', 'sacco_loan_category.loan_category_name', 'sacco_loan_types.loan_type_name', 'sacco_members.member_name', 'sacco_members.member_sacco_id')
            ->get();

        return view('loans.pending_approval', compact('loans'));
    }



    public function adminListLoansPendingApproval(Request $request)
    {
        $member_id = Auth::user()->member_id;
        $logged_in_user = Auth::id();
        $transdate = now();
        $myIP = $request->ip();
        $nmsg = "";

        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();

        // Handling delete request
        if ($request->has('did')) {


            $this->deleteLoanBatchTransMember($request->query('did'), $logged_in_user, $transdate, $myIP);
        }

        // Handling update request
        if ($request->has('update') && is_numeric($request->query('update'))) {
            $this->updateLoanBatchTransMember($request->query('update'), $member_id, $logged_in_user, $transdate, $myIP, $currentPeriod->period_name);
        }

        // Fetching pending loans
        $loans = $this->getPendingLoans();



        return view('loans.self_applications_pending_approval', compact('loans', 'nmsg'));
    }

    // Function to delete a loan batch transaction member
    private function deleteLoanBatchTransMember($id, $logged_in_user, $transdate, $myIP)
    {
        $loan = DB::table('sacco_loan_batch_trans_members')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_deleted', '<>', 'Y')
            ->where('batch_trans_updated', '<>', 'Y')
            ->first();

        if ($loan) {
            DB::table('sacco_loan_batch_trans_members')
                ->where('batch_trans_id', $id)
                ->update([
                    'batch_trans_deleted' => 'Y',
                    'batch_trans_deleted_by' => $logged_in_user,
                    'batch_trans_deleted_on' => $transdate,
                    'batch_trans_deleted_ip' => $myIP
                ]);
        }
    }


    private function updateLoanBatchTransMember($id, $member_id, $logged_in_user, $transdate, $myIP, $currentPeriod)
    {
        $loan = DB::table('sacco_loan_batch_trans_members')
            ->join('sacco_loan_category', 'sacco_loan_batch_trans_members.batch_trans_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->join('sacco_loan_types', 'sacco_loan_batch_trans_members.batch_trans_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_members', 'sacco_loan_batch_trans_members.batch_trans_member_id', '=', 'sacco_members.member_id')
            ->where('batch_trans_id', $id)
            ->where('batch_trans_updated', '<>', 'Y')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->first();

        if ($loan) {
            $this->updateSelfCreatedLoans($loan, $member_id, $logged_in_user, $transdate, $myIP, $currentPeriod);
            DB::table('sacco_loan_batch_trans_members')
                ->where('batch_trans_id', $id)
                ->update(['batch_trans_updated' => 'Y']);
            // Set success message
            session()->flash('success', 'Successfully updated member loan');
        }
    }


    // Function to get pending loans
    private function getPendingLoans()
    {

        return DB::table('sacco_loan_batch_trans_members')
            ->join('sacco_loan_category', 'sacco_loan_batch_trans_members.batch_trans_loan_category', '=', 'sacco_loan_category.loan_category_id')
            ->join('sacco_loan_types', 'sacco_loan_batch_trans_members.batch_trans_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_members', 'sacco_loan_batch_trans_members.batch_trans_member_id', '=', 'sacco_members.member_id')
            ->where('batch_trans_updated', '<>', 'Y')
            ->where('batch_trans_deleted', '<>', 'Y')
            ->select('sacco_loan_batch_trans_members.*', 'sacco_loan_category.loan_category_name', 'sacco_loan_types.loan_type_name', 'sacco_members.member_name', 'sacco_members.member_sacco_id')
            ->get();
    }

    // Function to update self-created loans
    private function updateSelfCreatedLoans($loan, $member_id, $logged_in_user, $transdate, $myIP, $currentPeriod)
    {
        // Ensure all necessary default accounts are available
        $default_bank_account = $this->getDefaultAccount('default_bank_account');
        $default_insurance_account = $this->getDefaultAccount('default_insurance_account');
        $default_loan_commission_account = $this->getDefaultAccount('default_loan_commission_account');

        if (!$default_bank_account || !$default_insurance_account || !$default_loan_commission_account) {
            return redirect()->back()->withErrors(['error' => 'Missing default bank account, insurance account, or commission account']);
        }


        // Check for sufficient guarantors
        if ($loan->loan_type_guaranteable_percent > 0 && !$this->isSufficientlyGuaranteed($loan, $loan->batch_trans_loan_amount)) {
            return redirect()->back()->withErrors(['error' => 'This loan is not sufficiently guaranteed']);
        }

        // Process the loan
        $this->processLoan($loan, $default_bank_account, $default_insurance_account, $default_loan_commission_account, $member_id, $logged_in_user, $transdate, $myIP, $currentPeriod);
    }

    // Function to get default account
    private function getDefaultAccount($account_name)
    {
        return DB::table('sacco_defaults')
            ->where('default_name', $account_name)
            ->value('default_value');
    }

    // Function to check if the loan is sufficiently guaranteed
    private function isSufficientlyGuaranteed($loan, $loan_amount)
    {
        $guarantors = DB::table('sacco_loan_batch_trans_members')
            ->join('sacco_loan_batch_guarantors_members', 'sacco_loan_batch_trans_members.batch_trans_id', '=', 'sacco_loan_batch_guarantors_members.guarantors_loan_batch_trans_id')
            ->where('guarantors_deleted', '<>', 'Y')
            ->where('batch_trans_member_id', $loan->batch_trans_member_id)
            ->where('batch_trans_id', $loan->batch_trans_id)
            ->sum('guarantors_amount_guaranteed');

        return $guarantors >= ($loan_amount * $loan->loan_type_guaranteable_percent / 100);
    }

    // Function to process the loan
    private function processLoan($loan, $default_bank_account, $default_insurance_account, $default_loan_commission_account, $member_id, $logged_in_user, $transdate, $myIP, $currentPeriod)
    {
        $new_batch_no = "Self Applied Loan-" . $loan->batch_trans_id . "-" . $loan->member_name;
        $total_loan = $loan->batch_trans_loan_amount + $loan->batch_trans_insurance;

        DB::table('sacco_loans')->insert([
            'loan_member' => $loan->batch_trans_member_id,
            'loan_loan_type' => $loan->batch_trans_loan_type,
            'loan_loan_category' => $loan->batch_trans_loan_category,
            'loan_amount' => $total_loan,
            'loan_insurance' => $loan->batch_trans_insurance,
            'loan_commision' => $loan->batch_trans_commission,
            'loan_payment_period' => $loan->batch_trans_loan_duration,
            'loan_interest_payable' => $loan->batch_trans_expected_interest,
            'loan_monthly_repayment_amount' => $loan->batch_trans_monthly_payment,
            'loan_monthly_repayment_principal' => $loan->batch_trans_monthly_payment_principal,
            'loan_amount_guaranteed' => $loan->batch_trans_loan_guaranteed,
            'loan_loan_paid' => 0,
            'loan_doc_no' => $loan->batch_trans_doc_no,
            'loan_description' => $loan->batch_trans_description,
            'loan_batch_no' => $new_batch_no,
            'loan_start_deduction_period' => $currentPeriod,
            'loan_account_credited' => $default_bank_account,
            'loan_account_debited' => $loan->loan_type_acount,
            'loan_taken_period' => $currentPeriod,
            'loan_by' => $logged_in_user,
            'loan_ip' => $myIP
        ]);

        DB::table('sacco_members')
            ->where('member_id', $loan->batch_trans_member_id)
            ->increment('member_total_loan', $total_loan);

        $this->updateLedgerEntries($loan, $default_bank_account, $default_insurance_account, $default_loan_commission_account, $new_batch_no, $total_loan, $logged_in_user, $myIP, $transdate, $currentPeriod);

        if ($loan->batch_trans_loan_to_top_up_amount > 0 && $loan->batch_trans_loan_to_top_up > 0) {
            $this->processLoanTopUp($loan, $default_bank_account, $logged_in_user, $myIP, $transdate, $currentPeriod);
        }

        $this->updateMemberLoanGuarantors($loan, $logged_in_user, $myIP, $new_batch_no);
    }

    // Function to update ledger entries
    private function updateLedgerEntries($loan, $default_bank_account, $default_insurance_account, $default_loan_commission_account, $new_batch_no, $total_loan, $logged_in_user, $myIP, $transdate, $currentPeriod)
    {
        $this->updateSaccoAccountsTrans($default_bank_account, 0, $loan->batch_trans_loan_amount - $loan->batch_trans_commission, $new_batch_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Updates from Self Application Forms");

        $this->updateSaccoAccountsTrans($default_insurance_account, 0, $loan->batch_trans_insurance, $new_batch_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Updates from Self Application Forms");

        $this->updateSaccoAccountsTrans($default_loan_commission_account, 0, $loan->batch_trans_commission, $new_batch_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Updates from Self Application Forms");

        $this->updateSaccoAccountsTrans($loan->loan_type_acount, $total_loan, 0, $new_batch_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Updates from Self Application Forms");
    }

    private function processLoanTopUp($loan, $default_bank_account, $logged_in_user, $myIP, $transdate, $currentPeriod)
    {
        // Fetch guarantors before making any changes
        $guarantors = DB::table('sacco_members')
            ->join('sacco_loan_guarantors', 'sacco_members.member_id', '=', 'sacco_loan_guarantors.loan_guar_guarantor_id')
            ->where('loan_guar_deleted', '<>', 'Y')
            ->where('member_deleted', '<>', 'Y')
            ->where('loan_guar_loan_id', $loan->batch_trans_loan_to_top_up)
            ->get();

        // Check if guarantors are sufficient
        foreach ($guarantors as $guarantor) {
            $ld = $guarantor->loan_guar_amount_guaranteed - $guarantor->loan_guar_amount_freed;

            if ($loan->batch_trans_member_id == $guarantor->member_id && ($guarantor->member_tied_shares_self < $ld)) {
                return redirect()->back()->withErrors(['error' => 'Insufficient self-tied shares for guarantor: ' . $guarantor->member_name]);
            }

            if ($loan->batch_trans_member_id != $guarantor->member_id && ($guarantor->member_tied_shares < $ld)) {
                return redirect()->back()->withErrors(['error' => 'Insufficient tied shares for guarantor: ' . $guarantor->member_name]);
            }
        }

        // Proceed with loan top-up after validations
        DB::table('sacco_loans')
            ->where('loan_id', $loan->batch_trans_loan_to_top_up)
            ->increment('loan_loan_paid', $loan->batch_trans_loan_to_top_up_amount);

        $paidby = "LOAN TOP UP - LNo." . $loan->batch_trans_loan_to_top_up;

        DB::table('sacco_loan_payments')->insert([
            'loan_payments_amount' => $loan->batch_trans_loan_to_top_up_amount,
            'loan_payments_description' => $loan->batch_trans_description,
            'loan_payments_docno' => $loan->batch_trans_doc_no,
            'loan_payments_paid_in_by' => $paidby,
            'loan_payments_period' => $currentPeriod,
            'loan_payments_paid_on' => $transdate,
            'loan_payments_loan_id' => $loan->batch_trans_loan_to_top_up,
            'loan_payments_interest' => 0,
            'loan_payments_by' => $logged_in_user,
            'loan_payments_ip' => $myIP
        ]);

        DB::table('sacco_members')
            ->where('member_id', $loan->batch_trans_loan_to_top_up)
            ->decrement('member_total_loan', $loan->batch_trans_loan_to_top_up_amount);

        // Update guarantors after validations
        foreach ($guarantors as $guarantor) {
            $ld = $guarantor->loan_guar_amount_guaranteed - $guarantor->loan_guar_amount_freed;

            if ($loan->batch_trans_member_id == $guarantor->member_id) {
                DB::table('sacco_members')
                    ->where('member_id', $guarantor->member_id)
                    ->decrement('member_tied_shares_self', $ld);

                DB::table('sacco_loan_guarantors')
                    ->where('loan_guar_loan_id', $loan->batch_trans_loan_to_top_up)
                    ->where('loan_guar_guarantor_id', $guarantor->member_id)
                    ->update(['loan_guar_deleted' => 'Y']);
            } else {
                DB::table('sacco_members')
                    ->where('member_id', $guarantor->member_id)
                    ->decrement('member_tied_shares', $ld);
            }
        }

        DB::table('sacco_loan_guarantors')
            ->where('loan_guar_deleted', '<>', 'Y')
            ->where('loan_guar_loan_id', $loan->batch_trans_loan_to_top_up)
            ->update(['loan_guar_amount_freed' => DB::raw('loan_guar_amount_guaranteed')]);

        $this->updateSaccoAccountsTrans($loan->loan_type_acount, 0, $loan->batch_trans_loan_to_top_up_amount, $loan->batch_trans_doc_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Updates from Self Application Forms");

        $this->updateSaccoAccountsTrans($default_bank_account, $loan->batch_trans_loan_to_top_up_amount, 0, $loan->batch_trans_doc_no, $loan->batch_trans_description, $transdate, $currentPeriod, "Loan Updates from Self Application Forms");
    }


    // Function to update member loan guarantors
    private function updateMemberLoanGuarantors($loan, $logged_in_user, $myIP, $new_batch_no)
    {
        // Fetch the guarantors for the loan
        $guarantors = DB::table('sacco_loan_batch_trans_members')
            ->join('sacco_loan_batch_guarantors_members', 'sacco_loan_batch_trans_members.batch_trans_id', '=', 'sacco_loan_batch_guarantors_members.guarantors_loan_batch_trans_id')
            ->where('guarantors_deleted', '<>', 'Y')
            ->where('batch_trans_member_id', $loan->batch_trans_member_id)
            ->where('batch_trans_id', $loan->batch_trans_id)
            ->get();

        // Fetch the loan record
        $loan_record = DB::table('sacco_loans')
            ->where('loan_member', $loan->batch_trans_member_id)
            ->where('loan_loan_type', $loan->batch_trans_loan_type)
            ->where('loan_loan_category', $loan->batch_trans_loan_category)
            ->where('loan_amount', $loan->batch_trans_loan_amount)
            ->where('loan_insurance', $loan->batch_trans_insurance)
            ->where('loan_commision', $loan->batch_trans_commission)
            ->where('loan_payment_period', $loan->batch_trans_loan_duration)
            ->where('loan_batch_no', $new_batch_no)
            ->first();

        // Check if the loan record exists
        if (!$loan_record) {
            return redirect()->back()->withErrors(['error' => 'Loan record not found. Loan details: Member ID - ' . $loan->batch_trans_member_id . ', Loan Type - ' . $loan->batch_trans_loan_type . ', Loan Category - ' . $loan->batch_trans_loan_category . ', Loan Amount - ' . $loan->batch_trans_loan_amount . ', Insurance - ' . $loan->batch_trans_insurance . ', Commission - ' . $loan->batch_trans_commission . ', Payment Period - ' . $loan->batch_trans_loan_duration . ', Batch No - ' . $new_batch_no]);
        }

        foreach ($guarantors as $guarantor) {
            // Insert guarantor record
            DB::table('sacco_loan_guarantors')->insert([
                'loan_guar_loan_id' => $loan_record->loan_id,
                'loan_guar_guarantor_id' => $guarantor->guarantors_guarantor_id,
                'loan_guar_amount_guaranteed' => $guarantor->guarantors_amount_guaranteed,
                'loan_guar_description' => $guarantor->guarantors_description,
                'loan_guar_by' => $logged_in_user,
                'loan_guar_ip' => $myIP
            ]);

            // Determine the tied shares to update
            $tiedO = $guarantor->guarantors_amount_guaranteed;
            $tiedS = 0;

            if ($loan->batch_trans_member_id == $guarantor->guarantors_guarantor_id) {
                $tiedO = 0;
                $tiedS = $guarantor->guarantors_amount_guaranteed;
            }

            // Update member tied shares
            if ($tiedO > 0) {
                DB::table('sacco_members')
                    ->where('member_id', $guarantor->guarantors_guarantor_id)
                    ->increment('member_tied_shares', $tiedO);
            }

            if ($tiedS > 0) {
                DB::table('sacco_members')
                    ->where('member_id', $guarantor->guarantors_guarantor_id)
                    ->increment('member_tied_shares_self', $tiedS);
            }
        }
    }
    public function reportSasraLoans(Request $request, $status, $active = null)
    {
        $reportType = $active === 'active' ? ($status == "y" ? 'Outstanding Active' : 'Outstanding Inactive') : 'Fully Paid';

        $currentPeriod = now()->format('Ym');
        $startPeriod = $request->input('start_period', $currentPeriod);
        $endPeriod = $request->input('end_period', $currentPeriod);

        return view('reports.sasra.loans', [
            'status' => $status,
            'active' => $active,
            'reportType' => $reportType,
            'currentPeriod' => $currentPeriod,
            'startPeriod' => $startPeriod,
            'endPeriod' => $endPeriod
        ]);
    }

    public function fetchSasraLoansData(Request $request)
    {
        $status = $request->get('status');
        $active = $request->get('active');
        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 5);
        $startPeriod = $request->get('start_period', now()->format('Ym'));
        $endPeriod = $request->get('end_period', now()->format('Ym'));

        $opt_qty = ($status == "y") ? ">1" : "<=0";
        $opt = ($status == "y");

        // Fetch the min_loan_amount_bill_able value from sacco_defaults
        $minLoanAmountBillable = DB::table('sacco_defaults')
            ->where('default_name', 'min_loan_amount_bill_able')
            ->value('default_value');

        // If min_loan_amount_bill_able is not found, default to 0
        $minLoanAmountBillable = $minLoanAmountBillable ?? 0;

        // Fetch latest payment for each loan
        $latestPayments = DB::table('sacco_loan_payments')
            ->select('loan_payments_loan_id', DB::raw('MAX(loan_payments_id) as max_id'))
            ->groupBy('loan_payments_loan_id');

        $loans = DB::table('sacco_loans')
            ->join('sacco_members', 'loan_member', '=', 'member_id')
            ->join('sacco_department', 'member_dept', '=', 'department_id')
            ->join('sacco_company', 'department_company_id', '=', 'company_id')
            ->join('sacco_loan_types', 'loan_loan_type', '=', 'loan_type_id')
            ->join('sacco_loan_category', 'loan_loan_category', '=', 'loan_category_id')
            ->leftJoinSub($latestPayments, 'latest_payments', function ($join) {
                $join->on('loan_id', '=', 'latest_payments.loan_payments_loan_id');
            })
            ->leftJoin('sacco_loan_payments', 'sacco_loan_payments.loan_payments_id', '=', 'latest_payments.max_id')
            ->whereBetween('loan_taken_period', [$startPeriod, $endPeriod]);

        if ($opt) {
            // Outstanding loans
            $loans->whereRaw('loan_amount-loan_loan_paid > ?', [$minLoanAmountBillable]);
        } else {
            // Fully paid loans
            $loans->whereRaw('loan_amount-loan_loan_paid <= ?', [$minLoanAmountBillable]);
        }

        if ($active === 'active') {
            $loans->where('member_active', 'Y');
        }

        $loans = $loans->orderBy('loan_id', 'asc')->offset($offset)->limit($limit)->get();

        foreach ($loans as $loan) {
            $loan->effective_date = date('Y-m-d', strtotime($loan->loan_on . ' +' . $loan->loan_payment_period . ' months'));
            $loan->effective_date = date('Y-m-d', strtotime($loan->effective_date . '-1 days'));
            $loan->balance = $loan->loan_amount - $loan->loan_loan_paid;

            if ($loan->balance <= $minLoanAmountBillable) {
                $loan->arrears_months = 0; // No arrears if fully paid
            } else if ($loan->loan_payments_period) {
                $last_paid_date = substr($loan->loan_payments_period, 0, 4) . "-" . substr($loan->loan_payments_period, 4, 2) . "-28";
                $now = new DateTime(date('Y-m-d'));
                $ref = new DateTime($last_paid_date);
                $diff = $now->diff($ref);
                $loan->arrears_months = $diff->format('%y') * 12 + $diff->format('%m');
            } else {
                $loan->arrears_months = 0; // No arrears if no payments
            }

            // Concatenate loan_type_name with loan_id in parentheses
            $loan->loan_type_display = "{$loan->loan_type_name} ({$loan->loan_id})";
        }

        return response()->json(['loans' => $loans]);
    }

    public function reportSasraShareBalances()
    {
        return view('reports.sasra.share');
    }

    public function fetchSasraShareData(Request $request)
    {
        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 5);

        $sacco_shares = DB::table('sacco_members')
            ->join('sacco_department', 'member_dept', '=', 'department_id')
            ->join('sacco_company', 'department_company_id', '=', 'company_id')
            ->orderBy('member_name', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Process the type field based on member_position and status based on member_active
        foreach ($sacco_shares as $share) {
            $share->type = $share->member_position == 1 ? 'Member' : ($share->member_position == 2 ? 'Official' : 'N/A');
            $share->status = $share->member_active == 'Y' ? 'Active' : 'Inactive';
        }

        return response()->json(['sacco_shares' => $sacco_shares]);
    }





    // // Function to fetch all main accounts and their corresponding sub-accounts
    // private function report_sasra_getMainSubAccounts($a_type)
    // {
    //     return DB::table('sacco_sub_account')
    //         ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
    //         ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
    //         ->where(function($query) use ($a_type) {
    //             $query->where('sacco_main_account.main_account_type', 'like', $a_type . '%')
    //                   ->orWhere('sacco_main_account.main_account_type', 'like', $a_type . 's%');  // Explicit plural form
    //         })
    //         ->orderBy('sacco_main_account.main_account_code')
    //         ->orderBy('sacco_sub_account.sub_account_code')
    //         ->orderBy('sacco_sub_account.sub_account_name')
    //         ->select('sacco_main_account.main_account_code', 'sacco_sub_account.sub_account_code', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_id')
    //         ->get();
    // }

    // // Function to fetch debit and credit totals for a sub-account within a period
    // private function report_sasra_getTrialBalances($sub_account_id, $pfrom, $pto)
    // {
    //     return DB::table('sacco_accounts_trans')
    //         ->select(DB::raw('SUM(accounts_trans_debit) AS sub_total_debit'), DB::raw('SUM(accounts_trans_credit) AS sub_total_credit'))
    //         ->where('accounts_trans_sub_account', $sub_account_id)
    //         ->whereBetween('accounts_trans_period', [$pfrom, $pto])
    //         ->first();
    // }

    // // Main function to fetch trial balance data
    // private function report_sasra_getTrialBalanceData($start, $end)
    // {
    //     $accountTypes = ['INCOME', 'EXPENSE'];  // Use only INCOME and EXPENSE for Profit and Loss

    //     $accounts = [];
    //     foreach ($accountTypes as $type) {
    //         $mainSubAccounts = $this->report_sasra_getMainSubAccounts($type);
    //         foreach ($mainSubAccounts as $account) {
    //             $trialBalances = $this->report_sasra_getTrialBalances($account->sub_account_id, $start, $end);
    //             $dval = $trialBalances->sub_total_debit;
    //             $cval = $trialBalances->sub_total_credit;

    //             if ($dval < 0 && $cval >= 0) {
    //                 $cval = ($dval * -1) + $cval;
    //                 $dval = 0;
    //             }

    //             if ($cval < 0 && $dval >= 0) {
    //                 $dval = $dval + ($cval * -1);
    //                 $cval = 0;
    //             }

    //             if ($cval < 0 && $dval < 0) {
    //                 $dval = $cval * -1;
    //                 $cval = $dval * -1;
    //             }

    //             if ($dval > $cval) {
    //                 $dval = $dval - $cval;
    //                 $cval = 0;
    //             } else {
    //                 $cval = $cval - $dval;
    //                 $dval = 0;
    //             }

    //             $accounts[] = [
    //                 'main_account_code' => $account->main_account_code,
    //                 'sub_account_code' => $account->sub_account_code,
    //                 'sub_account_name' => $account->sub_account_name,
    //                 'adjusted_debit' => $dval,
    //                 'adjusted_credit' => $cval,
    //                 'main_account_type' => $type
    //             ];
    //         }
    //     }

    //     return collect($accounts);
    // }

    // // Function to display the report
    // public function reportSasraProfitAndLoss(Request $request)
    // {
    //     $start = $request->input('startPeriod', now()->startOfMonth()->format('Ym'));
    //     $end = $request->input('endPeriod', now()->format('Ym'));

    //     $accounts = $this->report_sasra_getTrialBalanceData($start, $end);

    //     return view('reports.sasra.profit_and_loss', [
    //         'accounts' => $accounts,
    //         'startPeriod' => $start,
    //         'endPeriod' => $end
    //     ]);
    // }




    // public function reportSasraLoanPerformance(Request $request, $version = null)
    // {
    //     $title = $version === 'insider_lending' ? 'SASRA - Insider Lending Report' : 'SASRA - Loan Performance Report';
    //     return view('reports.sasra.loan_performance', ['version' => $version, 'title' => $title]);
    // }

    // public function fetchLoanPerformanceData(Request $request)
    // {
    //     $PeriodNow = $this->currentPeriod->period_code ?? date('Ym');
    //     $IgnoreLoanBalanceBelow = $this->IgnoreLoanBalanceBelow;

    //     $offset = $request->input('offset', 0);
    //     $limit = $request->input('limit', 5);
    //     $search = $request->input('search');
    //     $version = $request->input('version');

    //     $loans = $this->getLoanData($PeriodNow, $IgnoreLoanBalanceBelow, $search, $offset, $limit, $version);

    //     foreach ($loans as $loan) {
    //         $loan->category = $this->categorizeLoan($loan, $PeriodNow);
    //     }

    //     return response()->json(['loans' => $loans]);
    // }

    // private function getLoanData($PeriodNow, $IgnoreLoanBalanceBelow, $search = null, $offset = 0, $limit = 5, $version = null)
    // {
    //     // First, get the loans with balances above the threshold
    //     $loansQuery = DB::table('sacco_loans')
    //         ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
    //         ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
    //         ->select(
    //             'sacco_loans.loan_id',
    //             'sacco_members.member_name',
    //             'sacco_members.member_national_id',
    //             'sacco_loan_types.loan_type_name',
    //             'sacco_loans.loan_taken_period',
    //             'sacco_loans.loan_taken_start_period',
    //             'sacco_loans.loan_amount',
    //             'sacco_loans.loan_loan_paid',
    //             DB::raw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) as OutstandingAmount')
    //         )
    //         ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$IgnoreLoanBalanceBelow])
    //         ->orderBy('sacco_loans.loan_id', 'desc');

    //     if ($version === 'insider_lending') {
    //         $loansQuery->where('sacco_members.member_position', '=', 2);
    //     }

    //     if ($search) {
    //         $loansQuery->where(function ($query) use ($search) {
    //             $query->where('sacco_members.member_name', 'LIKE', '%' . $search . '%')
    //                 ->orWhere('sacco_members.member_national_id', 'LIKE', '%' . $search . '%')
    //                 ->orWhere('sacco_loan_types.loan_type_name', 'LIKE', '%' . $search . '%');
    //         });
    //     }

    //     $loans = $loansQuery->distinct()->offset($offset)->limit($limit)->get();

    //     // Get the latest payment information for the fetched loans using subquery
    //     $loanIds = $loans->pluck('loan_id')->toArray();
    //     $lastPayments = DB::table('sacco_loan_payments')
    //         ->select('loan_payments_loan_id', 'loan_payments_period as last_paid')
    //         ->whereIn('loan_payments_loan_id', $loanIds)
    //         ->whereRaw('loan_payments_id IN (SELECT MAX(loan_payments_id) FROM sacco_loan_payments GROUP BY loan_payments_loan_id)')
    //         ->get()
    //         ->keyBy('loan_payments_loan_id');

    //     // Attach the last payment information to the loans
    //     foreach ($loans as $loan) {
    //         $loan->last_paid = $lastPayments->get($loan->loan_id)->last_paid ?? null;
    //     }

    //     return $loans;
    // }

    // private function categorizeLoan($loan, $PeriodNow)
    // {
    //     // Get the current year and month
    //     $periodNowYear = intval(substr($PeriodNow, 0, 4));
    //     $periodNowMonth = intval(substr($PeriodNow, 4, 2));

    //     // Determine the correct loan start period
    //     if (preg_match('/^\d{6}$/', $loan->loan_taken_start_period)) {
    //         $startPeriod = $loan->loan_taken_period;
    //     } else {
    //         $startPeriod = $loan->loan_taken_period;
    //     }

    //     // Extract year and month from the start period
    //     $loanTakenYear = intval(substr($startPeriod, 0, 4));
    //     $loanTakenMonth = intval(substr($startPeriod, 4, 2));

    //     // If the loan was taken in the current period, categorize as "Current"
    //     if ($loanTakenYear == $periodNowYear && $loanTakenMonth == $periodNowMonth) {
    //         return 'Current';
    //     }

    //     // Calculate the period difference in months
    //     $periodDifference = ($periodNowYear - $loanTakenYear) * 12 + ($periodNowMonth - $loanTakenMonth);

    //     // Categorize the loan based on the period difference
    //     if ($periodDifference < 2) {
    //         return 'Current';
    //     } elseif ($periodDifference < 4) {
    //         return 'Watch';
    //     } elseif ($periodDifference < 6) {
    //         return 'Substandard';
    //     } elseif ($periodDifference < 12) {
    //         return 'Doubtful';
    //     } else {
    //         return 'Loss';
    //     }
    // }




    private function access_rights_get_members()
    {
        return DB::table('sacco_members')
            ->select('member_id', 'member_name', 'member_phone_no')
            ->where('member_active', 'Y')
            ->where('member_deleted', '!=', 'Y')
            ->where('member_position', '2')
            ->orderBy('member_name')
            ->get();
    }

    private function access_rights_get_modules($rightsUserId = null)
    {
        $modules = DB::table('sacco_modules')
            ->select('sacco_modules.module_id', 'sacco_modules.module_name', 'sacco_userrights.rights_access')
            ->leftJoin('sacco_userrights', function ($join) use ($rightsUserId) {
                $join->on('sacco_modules.module_id', '=', 'sacco_userrights.rights_app')
                    ->where('sacco_userrights.rights_user', '=', $rightsUserId);
            })
            ->where('sacco_modules.module_active', 'Y')
            ->where('sacco_modules.module_deleted', '!=', 'Y')
            ->orderBy('sacco_modules.module_name')
            ->get();

        return $modules;
    }

    public function adminAccessRights(Request $request)
    {
        $members = $this->access_rights_get_members();

        $rightsUserId = $request->input('rights_user');
        $modules = $this->access_rights_get_modules($rightsUserId);

        return view('admin.access-rights', compact('members', 'modules', 'rightsUserId'));
    }


    public function user_rights_save(Request $request)
    {
        $moduleId = $request->input('module_id');
        $rightsUserId = $request->input('rights_user');
        $granted = $request->input('granted') == 'true';
        $bulkUpdate = $request->input('bulk') == 'true';

        if ($bulkUpdate) {
            $moduleIds = DB::table('sacco_modules')
                ->where('module_active', 'Y')
                ->where('module_deleted', '!=', 'Y')
                ->pluck('module_id');

            if ($granted) {
                foreach ($moduleIds as $id) {
                    DB::table('sacco_userrights')->updateOrInsert(
                        ['rights_user' => $rightsUserId, 'rights_app' => $id],
                        ['rights_access' => 'Y', 'rights_userid' => auth()->id(), 'rights_ip' => $request->ip()]
                    );
                }
            } else {
                DB::table('sacco_userrights')
                    ->where('rights_user', $rightsUserId)
                    ->whereIn('rights_app', $moduleIds)
                    ->delete();
            }

            return response()->json(['success' => true]);
        }

        if ($granted) {
            // Add or update the record
            DB::table('sacco_userrights')->updateOrInsert(
                ['rights_user' => $rightsUserId, 'rights_app' => $moduleId],
                ['rights_access' => 'Y', 'rights_userid' => auth()->id(), 'rights_ip' => $request->ip()]
            );
        } else {
            // Delete the record
            DB::table('sacco_userrights')->where([
                ['rights_user', '=', $rightsUserId],
                ['rights_app', '=', $moduleId]
            ])->delete();
        }

        return response()->json(['success' => true]);
    }

    public function user_rights_add_module(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'module_name' => 'required|unique:sacco_modules,module_name',
                'module_description' => 'required',
            ]);

            DB::table('sacco_modules')->insert([
                'module_name' => $request->input('module_name'),
                'module_description' => $request->input('module_description'),
                'module_active' => 'Y',
                'module_userid' => auth()->id(),
                'module_ip' => $request->ip(),
                'module_transdate' => now()
            ]);

            return redirect()->route('admin.access-rights.add.module')->with('success', 'Module added successfully.');
        }

        return view('admin.add-module');
    }


    protected function getOutstandingLoansQuery($ignoreLoanBalanceBelow)
    {
        return DB::table('sacco_loans')
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
            ->where(DB::raw('sacco_loans.loan_amount - sacco_loans.loan_loan_paid'), '>', $ignoreLoanBalanceBelow)
            ->orderBy('sacco_members.member_name');
    }

    protected function applySearchFilter($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sacco_members.member_name', 'like', "%{$search}%")
                ->orWhere('sacco_members.member_phone_no', 'like', "%{$search}%")
                ->orWhere('sacco_members.member_national_id', 'like', "%{$search}%");
        });
    }

    protected function getLoanCategory($loanPaymentsPeriod, $loanTakenPeriod, $currentPeriod)
    {
        // If no loan payment period is available, fall back to the loan taken period
        $period = $loanPaymentsPeriod ?? $loanTakenPeriod;

        if (!$period) {
            return 'Loss';
        }

        $lastPeriodDate = \DateTime::createFromFormat('Ym', $period);
        $currentDate = \DateTime::createFromFormat('Ym', $currentPeriod);

        $interval = $lastPeriodDate->diff($currentDate);
        $months = $interval->y * 12 + $interval->m;

        if ($months <= 2) {
            return 'Current';
        } elseif ($months <= 4) {
            return 'Watch';
        } elseif ($months <= 6) {
            return 'Substandard';
        } elseif ($months <= 9) {
            return 'Doubtful';
        } else {
            return 'Loss';
        }
    }
    public function reportSasraLoanPerformance(Request $request, $version = null)
    {
        // Get the current period in YYYYmm format
        $currentPeriod = date('Ym');
        // Get the minimum loan balance to be considered outstanding
        $ignoreLoanBalanceBelow = $this->IgnoreLoanBalanceBelow;

        // Get the query for outstanding loans
        $query = $this->getOutstandingLoansQuery($ignoreLoanBalanceBelow);

        // Apply search filters if any
        if ($request->has('search')) {
            $search = $request->input('search');
            $query = $this->applySearchFilter($query, $search);
        }

        // Get the offset and limit for pagination
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);

        // Get the loans
        $loans = $query->offset($offset)->limit($limit)->get();

        // Add loan category for each loan
        foreach ($loans as $loan) {
            $loan->loan_category = $this->getLoanCategory($loan->loan_payments_period, $loan->loan_taken_period, $currentPeriod);
            $loan->position = $loan->member_position == 2 ? 'Official' : 'Member';
        }

        // Check if the request is AJAX
        if ($request->ajax()) {
            return response()->json($loans);
        }

        // Return the initial view with the current period
        return view('reports.sasra.loan_performance', [
            'currentPeriod' => $currentPeriod,
            'version' => $version
        ]);
    }


    public function adminDefaults()
    {
        // Fetch all defaults from the sacco_defaults table
        $defaults = DB::table('sacco_defaults')->get();

        // Pass the defaults to the view
        return view('admin.defaults', compact('defaults'));
    }


    public function updateDefaults(Request $request)
    {
        $defaults = $request->input('defaults');

        foreach ($defaults as $id => $value) {
            DB::table('sacco_defaults')
                ->where('default_id', $id)
                ->update(['default_value' => $value]);
        }

        return redirect()->route('admin.defaults')->with('success', 'Default values updated successfully.');
    }
    public function storeDefault(Request $request)
    {
        $request->validate([
            'default_name' => 'required|string|max:250',
            'default_value' => 'required|string|max:250',
        ]);

        DB::table('sacco_defaults')->insert([
            'default_name' => $request->input('default_name'),
            'default_value' => $request->input('default_value'),
            'default_transdate' => now(),
            'default_userid' => auth()->id(),
            'default_ip' => $request->ip(),
        ]);

        return redirect()->route('admin.defaults')->with('success', 'New default added successfully.');
    }





    public function loansTypes()
    {
        // Fetch loan types that are not deleted
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();

        // Fetch sub-account details ordered by name
        $subAccountDetails = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
            ->select('sacco_sub_account.sub_account_id', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code')
            ->orderBy('sacco_sub_account.sub_account_name')
            ->get()
            ->keyBy('sub_account_id');

        return view('loans.types', compact('loanTypes', 'subAccountDetails'));
    }

    public function editLoanType($id)
    {
        // Fetch the loan type details
        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $id)
            ->where('loan_type_deleted', '<>', 'Y')
            ->first();

        if (!$loanType) {
            return redirect()->route('loans.types')->with('error', 'Loan type not found.');
        }

        // Fetch sub-account details ordered by name
        $subAccounts = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
            ->select('sacco_sub_account.sub_account_id', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code')
            ->orderBy('sacco_sub_account.sub_account_name')
            ->get();

        return view('loans.edit', compact('loanType', 'subAccounts'));
    }

    public function updateLoanType(Request $request, $id)
    {
        $request->validate([
            'loan_type_name' => 'required|string|max:250',
            'loan_type_interest' => 'required|numeric',
            'loan_type_interest_type' => 'required|string|max:100',
            'loan_type_duration' => 'required|integer',
            'loan_type_guaranteable_percent' => 'required|integer',
            'loan_type_code' => 'required|string|max:100',
            'loan_type_max_amount' => 'required|numeric',
            'loan_type_qualification_period' => 'required|integer',
            'loan_type_acount' => 'required|integer',
            'loan_type_int_account' => 'required|integer',
            'loan_type_comm_account' => 'required|integer',
            'loan_type_insurable' => 'required|string|max:1',
        ]);

        DB::table('sacco_loan_types')
            ->where('loan_type_id', $id)
            ->update([
                'loan_type_name' => $request->input('loan_type_name'),
                'loan_type_interest' => $request->input('loan_type_interest'),
                'loan_type_interest_type' => $request->input('loan_type_interest_type'),
                'loan_type_duration' => $request->input('loan_type_duration'),
                'loan_type_guaranteable_percent' => $request->input('loan_type_guaranteable_percent'),
                'loan_type_code' => $request->input('loan_type_code'),
                'loan_type_max_amount' => $request->input('loan_type_max_amount'),
                'loan_type_qualification_period' => $request->input('loan_type_qualification_period'),
                'loan_type_acount' => $request->input('loan_type_acount'),
                'loan_type_int_account' => $request->input('loan_type_int_account'),
                'loan_type_comm_account' => $request->input('loan_type_comm_account'),
                'loan_type_insurable' => $request->input('loan_type_insurable'),
            ]);

        return redirect()->route('loans.types')->with('success', 'Loan type updated successfully.');
    }

    public function createLoanType()
    {
        $subAccounts = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
            ->select('sacco_sub_account.sub_account_id', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code')
            ->orderBy('sacco_sub_account.sub_account_name')
            ->get();

        return view('loans.create', ['subAccounts' => $subAccounts]);
    }

    public function storeLoanType(Request $request)
    {
        $request->validate([
            'loan_type_name' => 'required|string|max:250',
            'loan_type_interest' => 'required|numeric',
            'loan_type_interest_type' => 'required|string|max:100',
            'loan_type_duration' => 'required|integer',
            'loan_type_guaranteable_percent' => 'required|integer',
            'loan_type_code' => 'required|string|max:100',
            'loan_type_max_amount' => 'required|numeric',
            'loan_type_qualification_period' => 'required|integer',
            'loan_type_acount' => 'required|integer',
            'loan_type_int_account' => 'required|integer',
            'loan_type_comm_account' => 'required|integer',
            'loan_type_insurable' => 'required|string|max:1',
        ]);

        DB::table('sacco_loan_types')->insert([
            'loan_type_name' => $request->loan_type_name,
            'loan_type_interest' => $request->loan_type_interest,
            'loan_type_interest_type' => $request->loan_type_interest_type,
            'loan_type_duration' => $request->loan_type_duration,
            'loan_type_guaranteable_percent' => $request->loan_type_guaranteable_percent,
            'loan_type_code' => $request->loan_type_code,
            'loan_type_max_amount' => $request->loan_type_max_amount,
            'loan_type_qualification_period' => $request->loan_type_qualification_period,
            'loan_type_acount' => $request->loan_type_acount,
            'loan_type_int_account' => $request->loan_type_int_account,
            'loan_type_comm_account' => $request->loan_type_comm_account,
            'loan_type_insurable' => $request->loan_type_insurable,
            'loan_type_by' => auth()->id(),
            'loan_type_ip' => $request->ip(),
        ]);

        return redirect()->route('loans.types')->with('success', 'Loan type added successfully.');
    }

    public function deleteLoanType($id)
    {
        $loanType = DB::table('sacco_loan_types')
            ->where('loan_type_id', $id)
            ->first();

        if (!$loanType) {
            return redirect()->route('loans.types')->with('error', 'Loan type not found.');
        }

        DB::table('sacco_loan_types')
            ->where('loan_type_id', $id)
            ->update([
                'loan_type_deleted' => 'Y',
                'loan_type_deleted_by' => auth()->id(),
                'loan_type_deleted_on' => now(),
                'loan_type_deleted_ip' => request()->ip(),
            ]);

        return redirect()->route('loans.types')->with('success', 'Loan type deleted successfully.');
    }


    public function loansTypesList()
    {
        // Fetch loan types that are not deleted
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', '<>', 'Y')
            ->orderBy('loan_type_name')
            ->get();

        // Fetch sub-account details
        $subAccountDetails = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
            ->select('sacco_sub_account.sub_account_id', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code')
            ->get()
            ->keyBy('sub_account_id');

        return view('loans.types_list', compact('loanTypes', 'subAccountDetails'));
    }


    public function loansCategories()
    {
        // Fetch loan categories that are not deleted
        $loanCategories = DB::table('sacco_loan_category')
            ->where('loan_category_deleted', '!=', 'Y')
            ->orderBy('loan_category_name')
            ->get();

        return view('loans.categories', compact('loanCategories'));
    }

    public function createLoanCategory()
    {
        return view('loans.create_category');
    }

    public function storeLoanCategory(Request $request)
    {
        $request->validate([
            'loan_category_name' => 'required|string|max:100',
        ]);

        DB::table('sacco_loan_category')->insert([
            'loan_category_name' => $request->loan_category_name,
            'loan_category_company_id' => auth()->user()->company_id,
            'loan_category_user_id' => auth()->id(),
            'loan_category_ip' => $request->ip(),
        ]);

        return redirect()->route('loans.categories')->with('success', 'Loan category added successfully.');
    }

    public function editLoanCategory($id)
    {
        // Fetch the loan category details
        $loanCategory = DB::table('sacco_loan_category')
            ->where('loan_category_id', $id)
            ->where('loan_category_deleted', '<>', 'Y')
            ->first();

        if (!$loanCategory) {
            return redirect()->route('loans.categories')->with('error', 'Loan category not found.');
        }

        return view('loans.edit_category', compact('loanCategory'));
    }

    public function updateLoanCategory(Request $request, $id)
    {
        $request->validate([
            'loan_category_name' => 'required|string|max:100',
        ]);

        DB::table('sacco_loan_category')
            ->where('loan_category_id', $id)
            ->update([
                'loan_category_name' => $request->input('loan_category_name'),
                'loan_category_user_id' => auth()->id(),
                'loan_category_ip' => $request->ip(),
            ]);

        return redirect()->route('loans.categories')->with('success', 'Loan category updated successfully.');
    }

    public function deleteLoanCategory($id)
    {
        $loanCategory = DB::table('sacco_loan_category')
            ->where('loan_category_id', $id)
            ->first();

        if (!$loanCategory) {
            return redirect()->route('loans.categories')->with('error', 'Loan category not found.');
        }

        DB::table('sacco_loan_category')
            ->where('loan_category_id', $id)
            ->update([
                'loan_category_deleted' => 'Y',
                'loan_category_deleted_by' => auth()->id(),
                'loan_category_deleted_on' => now(),
                'loan_category_deleted_ip' => request()->ip(),
            ]);

        return redirect()->route('loans.categories')->with('success', 'Loan category deleted successfully.');
    }




    public function accountsMain()
    {
        $mainAccounts = DB::table('sacco_main_account')
            ->where('main_account_deleted', '<>', 'Y')
            ->orderBy('main_account_name')
            ->get();

        return view('accounts.main.index', compact('mainAccounts'));
    }

    public function createMainAccount()
    {
        $accountTypes = [
            'ASSET - FIXED',
            'ASSETS - CURRENT',
            'CAPITAL',
            'EXPENSE',
            'INCOME',
            'LIABILITIES - SHORT',
        ];

        return view('accounts.main.create', compact('accountTypes'));
    }

    public function storeMainAccount(Request $request)
    {
        $request->validate([
            'main_account_name' => 'required|string|max:100|unique:sacco_main_account,main_account_name',
            'main_account_type' => 'required|string|max:100',
        ]);

        $mainAccountCode = $this->generateMainAccountCode($request->input('main_account_type'));

        DB::table('sacco_main_account')->insert([
            'main_account_name' => strtoupper($request->input('main_account_name')),
            'main_account_code' => $mainAccountCode,
            'main_account_type' => strtoupper($request->input('main_account_type')),
            'main_account_user_id' => auth()->id(),
            'main_account_ip' => $request->ip(),
        ]);

        return redirect()->route('accounts.main')->with('success', 'Main account added successfully.');
    }

    public function editMainAccount($id)
    {
        $mainAccount = DB::table('sacco_main_account')
            ->where('main_account_id', $id)
            ->where('main_account_deleted', '<>', 'Y')
            ->first();

        if (!$mainAccount) {
            return redirect()->route('accounts.main')->with('error', 'Main account not found.');
        }

        $accountTypes = [
            'ASSET - FIXED',
            'ASSETS - CURRENT',
            'CAPITAL',
            'EXPENSE',
            'INCOME',
            'LIABILITIES - SHORT',
        ];

        return view('accounts.main.edit', compact('mainAccount', 'accountTypes'));
    }

    public function updateMainAccount(Request $request, $id)
    {
        $request->validate([
            'main_account_name' => 'required|string|max:100|unique:sacco_main_account,main_account_name,' . $id . ',main_account_id',
            'main_account_type' => 'required|string|max:100',
        ]);

        DB::table('sacco_main_account')
            ->where('main_account_id', $id)
            ->update([
                'main_account_name' => strtoupper($request->input('main_account_name')),
                'main_account_type' => strtoupper($request->input('main_account_type')),
                'main_account_user_id' => auth()->id(),
                'main_account_ip' => $request->ip(),
            ]);

        return redirect()->route('accounts.main')->with('success', 'Main account updated successfully.');
    }

    private function generateMainAccountCode($type)
    {
        $firstChar = strtoupper($type[0]);
        $lastCode = DB::table('sacco_main_account')
            ->where('main_account_type', $type)
            ->max('main_account_code');

        $lastNumber = $lastCode ? (int)substr($lastCode, 1) : 0;
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

        return $firstChar . $newNumber;
    }






    public function accountsSub()
    {
        $subAccounts = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->select(
                'sacco_sub_account.sub_account_id',
                'sacco_sub_account.sub_account_name',
                'sacco_sub_account.sub_account_code',
                'sacco_sub_account.sub_account_main_account',
                'sacco_sub_account.sub_account_debit',
                'sacco_sub_account.sub_account_credit',
                'sacco_main_account.main_account_code'
            )
            ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
            ->orderBy('sacco_sub_account.sub_account_name')
            ->get();

        return view('accounts.sub.index', compact('subAccounts'));
    }

    // Method to handle adding a new sub account




    public function createSubAccount()
    {
        $mainAccounts = DB::table('sacco_main_account')
            ->where('main_account_deleted', '<>', 'Y')
            ->orderBy('main_account_name')
            ->get();

        return view('accounts.sub.create', compact('mainAccounts'));
    }



    public function editSubAccount($id)
    {
        $subAccount = DB::table('sacco_sub_account')->where('sub_account_id', $id)->where('sub_account_deleted', '<>', 'Y')->first();

        if (!$subAccount) {
            return redirect()->route('accounts.sub')->with('error', 'Sub-account not found.');
        }

        $mainAccounts = DB::table('sacco_main_account')
            ->where('main_account_deleted', '<>', 'Y')
            ->orderBy('main_account_name')
            ->get();

        return view('accounts.sub.edit', compact('subAccount', 'mainAccounts'));
    }

    public function storeSubAccount(Request $request)
    {
        $request->validate([
            'sub_account_name' => 'required|string|max:100|unique:sacco_sub_account,sub_account_name',
            'sub_account_code' => 'required|string|max:3',
            'sub_account_main_account' => 'required|integer',
        ]);

        // Check for the combination of main account and sub account code
        $existingSubAccount = DB::table('sacco_sub_account')
            ->where('sub_account_main_account', $request->sub_account_main_account)
            ->where('sub_account_code', $request->sub_account_code)
            ->first();

        if ($existingSubAccount) {
            return redirect()->back()->withErrors(['The combination of main account and sub account code already exists.'])->withInput();
        }

        DB::table('sacco_sub_account')->insert([
            'sub_account_name' => strtoupper($request->sub_account_name),
            'sub_account_code' => $request->sub_account_code,
            'sub_account_main_account' => $request->sub_account_main_account,
            'sub_account_debit' => 0,
            'sub_account_credit' => 0,
            'sub_account_user_id' => auth()->id(),
            'sub_account_ip' => $request->ip(),
        ]);

        return redirect()->route('accounts.sub')->with('success', 'Sub account added successfully.');
    }

    public function updateSubAccount(Request $request, $id)
    {
        $request->validate([
            'sub_account_name' => 'required|string|max:100|unique:sacco_sub_account,sub_account_name,' . $id . ',sub_account_id',
            'sub_account_code' => 'required|string|max:3',
            'sub_account_main_account' => 'required|integer',
        ]);

        // Check for the combination of main account and sub account code
        $existingSubAccount = DB::table('sacco_sub_account')
            ->where('sub_account_main_account', $request->sub_account_main_account)
            ->where('sub_account_code', $request->sub_account_code)
            ->where('sub_account_id', '<>', $id)
            ->first();

        if ($existingSubAccount) {
            return redirect()->back()->withErrors(['The combination of main account and sub account code already exists.'])->withInput();
        }

        DB::table('sacco_sub_account')
            ->where('sub_account_id', $id)
            ->update([
                'sub_account_name' => strtoupper($request->sub_account_name),
                'sub_account_code' => $request->sub_account_code,
                'sub_account_main_account' => $request->sub_account_main_account,
            ]);

        return redirect()->route('accounts.sub')->with('success', 'Sub account updated successfully.');
    }






    public function accountsTransfer()
    {

        return view('accounts.transfer');
    }



    // public function storeAccountsTransfer(Request $request)
    // {
    //     $entries = $request->except('_token');
    //     $totalDebit = 0;
    //     $totalCredit = 0;
    //     $currentPeriod = $this->currentPeriod->period_name;
    //    dd($request->all());
    //     $validEntries = [];

    //     foreach ($entries as $key => $value) {
    //         if (strpos($key, '.') !== false) {
    //             $index = explode('.', $key)[1];

    //             if (!empty($entries['accountsTransfer_account.' . $index])) {
    //                 $validEntries[$index] = [
    //                     'account' => $entries['accountsTransfer_account.' . $index],
    //                     'debit' => $entries['accountsTransfer_debit.' . $index],
    //                     'credit' => $entries['accountsTransfer_credit.' . $index],
    //                     'doc_no' => $entries['accountsTransfer_doc_no.' . $index],
    //                     'description' => $entries['accountsTransfer_description.' . $index],
    //                     'date' => $entries['accountsTransfer_date.' . $index],
    //                 ];
    //             }
    //         }
    //     }

    //     foreach ($validEntries as $index => $entry) {
    //         $validator = Validator::make($entry, [
    //             'account' => 'required|string',
    //             'debit' => 'nullable|numeric',
    //             'credit' => 'nullable|numeric',
    //             'doc_no' => 'required|string',
    //             'description' => 'required|string',
    //             'date' => 'required|date',
    //         ]);

    //         if ($validator->fails()) {
    //             return redirect()->back()->withErrors($validator)->withInput();
    //         }



    //         $totalDebit += $entry['debit'] ?? 0;
    //         $totalCredit += $entry['credit'] ?? 0;


    //     }



    //     if ($totalDebit !== $totalCredit) {
    //         return redirect()->back()->withErrors(['error' => 'Total debits and credits must be equal.'])->withInput();
    //     }

    //     dd($totalCredit." and debits are ".$totalCredit);

    //     foreach ($validEntries as $index => $entry) {
    //         $accountDetails = explode(' - ', $entry['account']);
    //         $accountCode = explode('/', $accountDetails[0]);

    //         $subAccount = DB::table('sacco_sub_account')
    //             ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
    //             ->where('sacco_main_account.main_account_code', $accountCode[0])
    //             ->where('sacco_sub_account.sub_account_code', $accountCode[1])
    //             ->select('sacco_sub_account.sub_account_id')
    //             ->first();

    //         if ($subAccount) {
    //             $subAccountId = $subAccount->sub_account_id;
    //             $this->updateSaccoAccountsTrans(
    //                 $subAccountId,
    //                 $entry['debit'],
    //                 $entry['credit'],
    //                 $entry['doc_no'],
    //                 $entry['description'],
    //                 $entry['date'],
    //                 $currentPeriod,
    //                 'Journal Transfer'
    //             );
    //         }
    //     }

    //     return redirect()->route('accounts.transfer')->with('success', 'Journal entries updated successfully.');
    // }


    public function storeAccountsTransfer(Request $request)
    {
        $data = $request->all();
        $totalDebit = 0;
        $totalCredit = 0;
        $filledRows = [];

        // Loop through the data to validate only filled rows and calculate totals
        foreach ($data as $key => $value) {
            if (strpos($key, 'accountsTransfer_account_') === 0) {
                $index = explode('_', $key)[2];
                $debit = floatval($data["accountsTransfer_debit_$index"]) ?? 0;
                $credit = floatval($data["accountsTransfer_credit_$index"]) ?? 0;

                if (!empty($value) || $debit > 0 || $credit > 0 || !empty($data["accountsTransfer_doc_no_$index"]) || !empty($data["accountsTransfer_description_$index"])) {
                    // Ensure no negative values
                    if ($debit < 0 || $credit < 0) {
                        return redirect()->back()->withErrors(['Debits and credits cannot be negative.'])->withInput();
                    }

                    // Ensure either debit or credit has a positive value
                    if ($debit == 0 && $credit == 0) {
                        continue;
                    }

                    $filledRows[] = $index;
                    $totalDebit += $debit;
                    $totalCredit += $credit;
                }
            }
        }

        // Validate filled rows
        foreach ($filledRows as $index) {
            $request->validate([
                "accountsTransfer_account_$index" => 'required|string',
                "accountsTransfer_doc_no_$index" => 'required|string',
                "accountsTransfer_description_$index" => 'required|string',
                "accountsTransfer_date_$index" => 'required|date',
            ]);
        }

        // Check if total debits equal total credits
        if ($totalDebit != $totalCredit) {
            return redirect()->back()->withErrors(['Total debits must equal total credits.'])->withInput();
        }

        // Proceed with storing the valid data
        foreach ($filledRows as $index) {
            $account = explode(' - ', $data["accountsTransfer_account_$index"])[0];
            list($mainAccountCode, $subAccountCode) = explode('/', $account);

            // Fetch the sub_account_id
            $subAccount = DB::table('sacco_sub_account')
                ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
                ->select('sacco_sub_account.sub_account_id')
                ->where('sacco_main_account.main_account_code', trim($mainAccountCode))
                ->where('sacco_sub_account.sub_account_code', trim($subAccountCode))
                ->where('sacco_sub_account.sub_account_deleted', '<>', 'Y')
                ->first();

            if (!$subAccount) {
                return redirect()->back()->withErrors(["Account $account not found."])->withInput();
            }

            $subAccountId = $subAccount->sub_account_id;
            $debit = floatval($data["accountsTransfer_debit_$index"]) ?? 0;
            $credit = floatval($data["accountsTransfer_credit_$index"]) ?? 0;

            $this->updateSaccoAccountsTrans(
                $subAccountId,
                $debit,
                $credit,
                $data["accountsTransfer_doc_no_$index"],
                $data["accountsTransfer_description_$index"],
                $data["accountsTransfer_date_$index"],
                $this->currentPeriod->period_name,
                'Journal Transfer'
            );
        }

        return redirect()->route('accounts.transfer')->with('success', 'Accounts transfer completed successfully.');
    }

    public function reportsAccountsTrialBalance(Request $request)
    {
        $currentPeriod = $this->currentPeriod->period_name;

        // Default start and end dates
        $startDate = $request->input('start_date', date('Y-m-01')); // Start of the current month
        $endDate = $request->input('end_date', date('Y-m-d')); // Today's date

        // Validate the dates
        if (!strtotime($startDate) || !strtotime($endDate)) {
            return redirect()->route('reports.accounts.trial-balance')
                ->withErrors(['date' => 'Invalid date format.']);
        }

        if ($startDate > $endDate) {
            return redirect()->route('reports.accounts.trial-balance')
                ->withErrors(['date' => 'Start date cannot be greater than end date.']);
        }

        // Determine the view and account types based on the route
        $view = 'reports.accounts.trial_balance'; // Default view
        $accountTypes = []; // Default: No filtering (include all account types)

        if ($request->route()->named('reports.accounts.profit-loss')) {
            $view = 'reports.accounts.profit_loss';
            $accountTypes = ['INCOME', 'EXPENSE']; // P&L includes only INCOME and EXPENSE
        } elseif ($request->route()->named('reports.accounts.balance-sheet')) {
            $view = 'reports.accounts.balance_sheet';
            $accountTypes = ['ASSETS', 'LIABILITIES', 'CAPITAL']; // Balance sheet types
        }

        // Fetch the Opening Balance (independent)
        $openingBalance = $this->fetchOpeningBalance($startDate, $accountTypes);

        // Fetch accounts and transactions for the given period
        $accounts = $this->fetchAccountsForTrialBalance($startDate, $endDate, $accountTypes);

        return view($view, [
            'accounts' => $accounts,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'currentPeriod' => $currentPeriod,
            'openingBalance' => $openingBalance, // Pass opening balance as independent value
        ]);
    }

    private function fetchOpeningBalance($startDate, $accountTypes = [])
    {
        // Build the query to fetch opening balance
        $query = DB::table('sacco_accounts_trans')
            ->where('accounts_trans_dat_date', '<', $startDate)
            ->select(
                DB::raw('SUM(accounts_trans_debit) as total_debit'),
                DB::raw('SUM(accounts_trans_credit) as total_credit')
            );

        // Filter by account types if provided
        if (!empty($accountTypes)) {
            $query->join('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
                ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
                ->where(function ($subQuery) use ($accountTypes) {
                    foreach ($accountTypes as $type) {
                        $subQuery->orWhere('sacco_main_account.main_account_type', 'LIKE', "%$type%");
                    }
                });
        }

        // Execute the query
        $openingData = $query->first();

        // Handle null data (no transactions)
        if (!$openingData) {
            return (object) [
                'balance' => 0,
                'type' => 'Debit', // Default to Debit if no data
            ];
        }

        // Calculate the net opening balance
        $totalDebit = $openingData->total_debit ?? 0;
        $totalCredit = $openingData->total_credit ?? 0;

        // Net Opening Balance (Debit - Credit)
        $openingBalance = $totalDebit - $totalCredit;

        // Return as a single independent value
        return (object) [
            'balance' => abs($openingBalance), // Always positive
            'type' => $openingBalance >= 0 ? 'Debit' : 'Credit', // Determine if Debit or Credit
        ];
    }

    private function fetchAccountsForTrialBalance($startDate, $endDate, $accountTypes = [])
    {
        $accounts = [];

        // Chunk through transactions for the given date range to avoid memory issues
        DB::table('sacco_accounts_trans')
            ->whereBetween('accounts_trans_dat_date', [$startDate, $endDate]) // Filter by date
            ->orderBy('accounts_trans_id', 'asc')
            ->chunk(10000, function ($transactions) use (&$accounts, $accountTypes) {
                foreach ($transactions as $transaction) {
                    $subAccountQuery = DB::table('sacco_sub_account')
                        ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
                        ->where('sacco_sub_account.sub_account_id', $transaction->accounts_trans_sub_account);

                    // Apply account type filtering using LIKE for partial matches
                    if (!empty($accountTypes)) {
                        $subAccountQuery->where(function ($query) use ($accountTypes) {
                            foreach ($accountTypes as $type) {
                                $query->orWhere('sacco_main_account.main_account_type', 'LIKE', "%$type%");
                            }
                        });
                    }

                    $subAccount = $subAccountQuery->select(
                        'sacco_sub_account.sub_account_id',
                        'sacco_sub_account.sub_account_name',
                        'sacco_sub_account.sub_account_code',
                        'sacco_main_account.main_account_code',
                        'sacco_main_account.main_account_type'
                    )->first();

                    if ($subAccount) {
                        $key = trim($subAccount->sub_account_id);

                        if (!isset($accounts[$key])) {
                            $accounts[$key] = (object) [
                                'sub_account_id' => trim($subAccount->sub_account_id),
                                'sub_account_name' => trim($subAccount->sub_account_name),
                                'sub_account_code' => trim($subAccount->sub_account_code),
                                'main_account_code' => trim($subAccount->main_account_code),
                                'main_account_type' => trim($subAccount->main_account_type),
                                'total_debit' => 0,
                                'total_credit' => 0,
                            ];
                        }

                        $accounts[$key]->total_debit += $transaction->accounts_trans_debit;
                        $accounts[$key]->total_credit += $transaction->accounts_trans_credit;
                    }
                }
            });

        // Handle empty accounts
        if (empty($accounts)) {
            return collect([]); // Return an empty collection
        }

        // Convert array to a collection
        $accountsCollection = collect($accounts);

        // Group the accounts by main account type
        return $accountsCollection->groupBy('main_account_type');
    }

    // public function reportsAccountsTrialBalance(Request $request)
    // {
    //     $currentPeriod = $this->currentPeriod->period_name;

    //     // Default start and end dates
    //     $startDate = $request->input('start_date', date('Y-m-01')); // Start of the current month
    //     $endDate = $request->input('end_date', date('Y-m-d')); // Today's date

    //     // Validate the dates
    //     if (!strtotime($startDate) || !strtotime($endDate)) {
    //         return redirect()->route('reports.accounts.trial-balance')
    //                          ->withErrors(['date' => 'Invalid date format.']);
    //     }

    //     if ($startDate > $endDate) {
    //         return redirect()->route('reports.accounts.trial-balance')
    //                          ->withErrors(['date' => 'Start date cannot be greater than end date.']);
    //     }

    //     $accounts = $this->fetchAccountsForTrialBalance($startDate, $endDate);

    //     // Determine the view based on the route name
    //     $view = 'reports.accounts.trial_balance'; // Default view
    //     if ($request->route()->named('reports.accounts.profit-loss')) {
    //         $view = 'reports.accounts.profit_loss';
    //     } elseif ($request->route()->named('reports.accounts.balance-sheet')) {
    //         $view = 'reports.accounts.balance_sheet';
    //     } elseif ($request->route()->named('reports.accounts.trial-balance-horizontal')) {
    //         $view = 'reports.accounts.trial_balance_horizontal';
    //     } elseif ($request->route()->named('reports.accounts.profit-loss-horizontal')) {
    //         $view = 'reports.accounts.profit_loss_horizontal';
    //     } elseif ($request->route()->named('reports.accounts.balance-sheet-horizontal')) {
    //         $view = 'reports.accounts.balance_sheet_horizontal';
    //     }

    //     return view($view, [
    //         'accounts' => $accounts,
    //         'startDate' => $startDate,
    //         'endDate' => $endDate,
    //         'currentPeriod' => $currentPeriod,
    //     ]);
    // }

    // private function fetchAccountsForTrialBalance($startDate, $endDate)
    // {
    //     $accounts = [];

    //     // Chunk through transactions to avoid memory issues
    //     DB::table('sacco_accounts_trans')
    //         ->whereBetween('accounts_trans_dat_date', [$startDate, $endDate]) // Filtering by accounts_trans_dat_date
    //         ->orderBy('accounts_trans_id', 'asc')
    //         ->chunk(10000, function ($transactions) use (&$accounts) {
    //             foreach ($transactions as $transaction) {
    //                 $subAccount = DB::table('sacco_sub_account')
    //                     ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
    //                     ->where('sacco_sub_account.sub_account_id', $transaction->accounts_trans_sub_account)
    //                     ->select(
    //                         'sacco_sub_account.sub_account_id',
    //                         'sacco_sub_account.sub_account_name',
    //                         'sacco_sub_account.sub_account_code',
    //                         'sacco_main_account.main_account_code',
    //                         'sacco_main_account.main_account_type'
    //                     )
    //                     ->first();

    //                 if ($subAccount) {
    //                     $key = trim($subAccount->sub_account_id);

    //                     if (!isset($accounts[$key])) {
    //                         $accounts[$key] = (object) [
    //                             'sub_account_id' => trim($subAccount->sub_account_id),
    //                             'sub_account_name' => trim($subAccount->sub_account_name),
    //                             'sub_account_code' => trim($subAccount->sub_account_code),
    //                             'main_account_code' => trim($subAccount->main_account_code),
    //                             'main_account_type' => trim($subAccount->main_account_type),
    //                             'total_debit' => 0,
    //                             'total_credit' => 0
    //                         ];
    //                     }

    //                     $accounts[$key]->total_debit += $transaction->accounts_trans_debit;
    //                     $accounts[$key]->total_credit += $transaction->accounts_trans_credit;
    //                 }
    //             }
    //         });

    //     // Convert array to a collection
    //     $accountsCollection = collect($accounts);

    //     // Group the accounts by main account type
    //     $groupedAccounts = $accountsCollection->groupBy('main_account_type');

    //     return $groupedAccounts;
    // }




    // public function reportsAccountsTrialBalance(Request $request)
    // {
    //     $currentPeriod = $this->currentPeriod->period_name;
    //     $startPeriod = $request->input('start_period', date('Ym', strtotime('-11 months')));
    //     $endPeriod = $request->input('end_period', date('Ym'));

    //     // Validate the periods
    //     if (!ctype_digit($startPeriod) || !ctype_digit($endPeriod)) {
    //         return redirect()->route('reports.accounts.trial-balance')
    //                          ->withErrors(['period' => 'Periods must be numeric and in the format YYYYmm.']);
    //     }

    //     if ($startPeriod > $endPeriod) {
    //         return redirect()->route('reports.accounts.trial-balance')
    //                          ->withErrors(['period' => 'Start period cannot be greater than end period.']);
    //     }

    //     if ($startPeriod < date('Ym', strtotime('-11 months', strtotime($endPeriod . '01')))) {
    //         return redirect()->route('reports.accounts.trial-balance')
    //                          ->withErrors(['period' => 'The selected period range should not exceed 12 months.']);
    //     }

    //     $accounts = $this->fetchAccountsForTrialBalance($startPeriod, $endPeriod);

    //     $view = 'reports.accounts.trial_balance'; // Default view
    //     if ($request->route()->named('reports.accounts.profit-loss')) {
    //         $view = 'reports.accounts.profit_loss';
    //     } elseif ($request->route()->named('reports.accounts.balance-sheet')) {
    //         $view = 'reports.accounts.balance_sheet';
    //     } elseif ($request->route()->named('reports.accounts.trial-balance-horizontal')) {
    //         $view = 'reports.accounts.trial_balance_horizontal';
    //     } elseif ($request->route()->named('reports.accounts.profit-loss-horizontal')) {
    //         $view = 'reports.accounts.profit_loss_horizontal';
    //     } elseif ($request->route()->named('reports.accounts.balance-sheet-horizontal')) {
    //         $view = 'reports.accounts.balance_sheet_horizontal';
    //     }

    //     return view($view, [
    //         'accounts' => $accounts,
    //         'startPeriod' => $startPeriod,
    //         'endPeriod' => $endPeriod,
    //         'currentPeriod' => $currentPeriod,
    //     ]);
    // }


    // private function fetchAccountsForTrialBalance($startPeriod, $endPeriod)
    // {
    //     $accounts = [];

    //     // Chunk through transactions to avoid memory issues
    //     DB::table('sacco_accounts_trans')
    //         ->whereBetween('accounts_trans_period', [$startPeriod, $endPeriod])
    //         ->orderBy('accounts_trans_id', 'asc')
    //         ->chunk(10000, function ($transactions) use (&$accounts) {
    //             foreach ($transactions as $transaction) {
    //                 $subAccount = DB::table('sacco_sub_account')
    //                     ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
    //                     ->where('sacco_sub_account.sub_account_id', $transaction->accounts_trans_sub_account)
    //                     ->select(
    //                         'sacco_sub_account.sub_account_id',
    //                         'sacco_sub_account.sub_account_name',
    //                         'sacco_sub_account.sub_account_code',
    //                         'sacco_main_account.main_account_code',
    //                         'sacco_main_account.main_account_type'
    //                     )
    //                     ->first();

    //                 if ($subAccount) {
    //                     $key = trim($subAccount->sub_account_id);

    //                     if (!isset($accounts[$key])) {
    //                         $accounts[$key] = (object) [
    //                             'sub_account_id' => trim($subAccount->sub_account_id),
    //                             'sub_account_name' => trim($subAccount->sub_account_name),
    //                             'sub_account_code' => trim($subAccount->sub_account_code),
    //                             'main_account_code' => trim($subAccount->main_account_code),
    //                             'main_account_type' => trim($subAccount->main_account_type),
    //                             'total_debit' => 0,
    //                             'total_credit' => 0
    //                         ];
    //                     }

    //                     $accounts[$key]->total_debit += $transaction->accounts_trans_debit;
    //                     $accounts[$key]->total_credit += $transaction->accounts_trans_credit;
    //                 }
    //             }
    //         });

    //     // Convert array to a collection
    //     $accountsCollection = collect($accounts);

    //     // Group the accounts by main account type
    //     $groupedAccounts = $accountsCollection->groupBy('main_account_type');

    //     return $groupedAccounts;
    // }



    public function adminBudget(Request $request)
    {
        $currentYear = date('Y');
        $year = $request->input('year', $currentYear);

        // Fetch budget data for the selected year
        $budgets = $this->adminBudget_fetchBudgets($year);

        // Fetch all sub accounts for creating budgets
        $subAccounts = $this->adminBudget_fetchSubAccounts();

        return view('admin.budget', [
            'year' => $year,
            'budgets' => $budgets,
            'subAccounts' => $subAccounts,
            'currentYear' => $currentYear
        ]);
    }

    private function adminBudget_fetchBudgets($year)
    {
        return DB::table('sacco_budget')
            ->join('sacco_sub_account', 'sacco_budget.budget_account_id', '=', 'sacco_sub_account.sub_account_id')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('budget_year', $year)
            ->select('sacco_budget.*', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code', 'sacco_main_account.main_account_name', 'sacco_main_account.main_account_type')
            ->get();
    }

    private function adminBudget_fetchSubAccounts()
    {
        return DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->select('sacco_sub_account.*', 'sacco_main_account.main_account_code', 'sacco_main_account.main_account_name', 'sacco_main_account.main_account_type')
            ->get();
    }

    public function adminBudget_store(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer',
            'budget.*.account_id' => 'required|integer',
            'budget.*.amount_debit' => 'required|numeric|min:0',
            'budget.*.amount_credit' => 'required|numeric|min:0',
        ]);

        foreach ($data['budget'] as $budget) {
            if ($budget['amount_debit'] > 0 && $budget['amount_credit'] > 0) {
                return back()->withErrors(['Both debit and credit cannot have values.']);
            }

            DB::table('sacco_budget')->updateOrInsert(
                [
                    'budget_year' => $data['year'],
                    'budget_account_id' => $budget['account_id']
                ],
                [
                    'budget_amount_debit' => $budget['amount_debit'],
                    'budget_amount_credit' => $budget['amount_credit'],
                    'budget_user_id' => auth()->id(),
                    'budget_ip' => $request->ip()
                ]
            );
        }

        return back()->with('success', 'Budget updated successfully.');
    }

    public function reportsAccountsBudgetVsActuals(Request $request)
    {
        $currentYear = date('Y');
        $year = $request->input('year', $currentYear);

        // Fetch budget data for the selected year
        $budgets = DB::table('sacco_budget')
            ->join('sacco_sub_account', 'sacco_budget.budget_account_id', '=', 'sacco_sub_account.sub_account_id')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('budget_year', $year)
            ->select('sacco_budget.*', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_name', 'sacco_main_account.main_account_code', 'sacco_main_account.main_account_type')
            ->get();

        // Fetch actuals data for the selected year
        $actualsData = DB::table('sacco_accounts_trans')
            ->select(
                'accounts_trans_sub_account',
                DB::raw('SUM(accounts_trans_debit) as actual_debit'),
                DB::raw('SUM(accounts_trans_credit) as actual_credit')
            )
            ->where('accounts_trans_period', 'like', "$year%")
            ->groupBy('accounts_trans_sub_account')
            ->get()
            ->keyBy('accounts_trans_sub_account');

        return view('admin.budget_vs_actuals', [
            'year' => $year,
            'budgets' => $budgets,
            'actualsData' => $actualsData,
            'currentYear' => $currentYear
        ]);
    }


    public function reportsAccountsTrialBalanceBudget(Request $request)
    {
        $currentYear = date('Y');
        $year = $request->input('year', $currentYear);

        // Fetch budget data for the selected year
        $budgets = $this->reportsAccountsTrialBalanceBudget_fetchBudgets($year);

        // Fetch actuals data for the selected year
        $actualsData = $this->reportsAccountsTrialBalanceBudget_fetchActuals($year);

        // Filter only INCOME and EXPENSE/EXPENSES accounts for P&L
        $incomeExpenseBudgets = $budgets->filter(function ($budget) {
            return in_array($budget->main_account_type, ['INCOME', 'EXPENSE', 'EXPENSES']);
        });

        return view('reports.accounts.profit_loss_budget', [
            'year' => $year,
            'budgets' => $incomeExpenseBudgets,
            'actualsData' => $actualsData,
            'currentYear' => $currentYear
        ]);
    }

    private function reportsAccountsTrialBalanceBudget_fetchBudgets($year)
    {
        return DB::table('sacco_budget')
            ->join('sacco_sub_account', 'sacco_budget.budget_account_id', '=', 'sacco_sub_account.sub_account_id')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('budget_year', $year)
            ->select('sacco_budget.*', 'sacco_sub_account.sub_account_name', 'sacco_sub_account.sub_account_code', 'sacco_main_account.main_account_code', 'sacco_main_account.main_account_name', 'sacco_main_account.main_account_type')
            ->get();
    }

    private function reportsAccountsTrialBalanceBudget_fetchActuals($year)
    {
        return DB::table('sacco_accounts_trans')
            ->select(
                'accounts_trans_sub_account',
                DB::raw('SUM(accounts_trans_debit) as actual_debit'),
                DB::raw('SUM(accounts_trans_credit) as actual_credit')
            )
            ->where('accounts_trans_period', 'like', "$year%")
            ->groupBy('accounts_trans_sub_account')
            ->get()
            ->keyBy('accounts_trans_sub_account');
    }




    public function showEndOfYearProcessingForm()
    {
        $currentYear = date('Y');

        $previousProcesses = DB::table('sacco_end_year_proc')
            ->join('sacco_members', 'sacco_end_year_proc.end_year_proc_by', '=', 'sacco_members.member_id')
            ->select('sacco_end_year_proc.*', 'sacco_members.member_name')
            ->orderBy('sacco_end_year_proc.end_year_proc_on', 'desc')
            ->limit(20)
            ->get();

        return view('admin.end_of_year_processing_form', [
            'currentYear' => $currentYear,
            'previousProcesses' => $previousProcesses
        ]);
    }

    public function endOfYearProcessing(Request $request)
    {
        $startPeriod = $request->input('start_period');
        $endPeriod = $request->input('end_period');

        // Validation
        if (!is_numeric($startPeriod) || strlen($startPeriod) != 6 || !is_numeric($endPeriod) || strlen($endPeriod) != 6) {
            return back()->withErrors(['Start and end periods must be numeric and 6 characters long.']);
        }

        // Check if start period is greater than end period
        if ($startPeriod > $endPeriod) {
            return back()->withErrors(['Start period must be less than or equal to end period.']);
        }

        // Fetch the latest processed period
        $latestProcessedPeriod = DB::table('sacco_end_year_proc')
            ->orderBy('end_year_proc_period', 'desc')
            ->value('end_year_proc_period');

        // Check if the selected periods are already processed or are in the past
        if ($latestProcessedPeriod && $startPeriod <= $latestProcessedPeriod) {
            return back()->withErrors(['The selected periods are already processed or are in the past.']);
        }

        // Retrieve the appropriation account from sacco_defaults
        $appropriationAccount = DB::table('sacco_defaults')
            ->where('default_name', 'appropriation_account')
            ->first();

        if (!$appropriationAccount) {
            return back()->withErrors(['Appropriation account not found.']);
        }

        // Process each month separately within the selected period range
        $currentPeriod = $startPeriod;
        while ($currentPeriod <= $endPeriod) {
            $totalIncome = DB::table('sacco_accounts_trans')
                ->join('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
                ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
                ->where('accounts_trans_period', $currentPeriod)
                ->whereIn('sacco_main_account.main_account_type', ['INCOME', 'INCOME - CURRENT'])
                ->sum('accounts_trans_credit');

            $totalExpenses = DB::table('sacco_accounts_trans')
                ->join('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
                ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
                ->where('accounts_trans_period', $currentPeriod)
                ->whereIn('sacco_main_account.main_account_type', ['EXPENSE', 'EXPENSES'])
                ->sum('accounts_trans_debit');

            $netProfitOrLoss = $totalIncome - $totalExpenses;

            // Close Temporary Accounts and Transfer to Appropriation Account
            $this->closeTemporaryAccounts($appropriationAccount->default_value, $currentPeriod, $netProfitOrLoss);

            // Move to the next month
            $year = substr($currentPeriod, 0, 4);
            $month = substr($currentPeriod, 4, 2);
            if ($month == 12) {
                $currentPeriod = ($year + 1) . '01';
            } else {
                $currentPeriod = $year . str_pad($month + 1, 2, '0', STR_PAD_LEFT);
            }
        }

        // Update sacco_end_year_proc table
        DB::table('sacco_end_year_proc')->insert([
            'end_year_proc_period' => $endPeriod,
            'end_year_proc_by' => auth()->id(),
            'end_year_proc_on' => now(),
            'end_year_proc_ip' => request()->ip(),
        ]);

        return back()->with('success', 'End of year processing completed successfully.');
    }

    private function closeTemporaryAccounts($appropriationAccountId, $period, $netProfitOrLoss)
    {
        $temporaryAccounts = DB::table('sacco_accounts_trans')
            ->join('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('accounts_trans_period', $period)
            ->whereIn('sacco_main_account.main_account_type', ['INCOME', 'INCOME - CURRENT', 'EXPENSE', 'EXPENSES'])
            ->select('sacco_accounts_trans.*', 'sacco_main_account.main_account_type')
            ->get();

        foreach ($temporaryAccounts as $account) {
            $debit = $account->accounts_trans_debit;
            $credit = $account->accounts_trans_credit;

            // Create a contra entry for the temporary account
            $this->updateSaccoAccountsTrans(
                $account->accounts_trans_sub_account,
                $credit,
                $debit,
                'ENDYEAR' . $period,
                'Closing Entry (' . $account->accounts_trans_id . ')',
                now(),
                $period,
                'End of Year Processing'
            );

            // Transfer the contra entry to the appropriation account
            $this->updateSaccoAccountsTrans(
                $appropriationAccountId,
                $debit,
                $credit,
                'ENDYEAR' . $period,
                'Transfer to Appropriation Account (' . $account->accounts_trans_id . ')',
                now(),
                $period,
                'End of Year Processing'
            );
        }
    }



    public function reportsAccountsAllTime(Request $request)
    {
        $routeName = \Route::currentRouteName();
        $title = '';

        switch ($routeName) {
            case 'reports.accounts.AllTimeAccountsFullTrialBalance':
                $title = 'All Time Trial Balance';
                $data = $this->reportsAccountsAllTime_fetchData(['ASSET - FIXED', 'ASSETS - CURRENT', 'CAPITAL', 'EXPENSE', 'EXPENSES', 'INCOME', 'INCOME - CURRENT', 'LIABILITIES - SHORT']);
                return view('reports.accounts.all_time_trial_balance', compact('data', 'title'));
            case 'reports.accounts.AllTimeAccountsFullProftAndLoss':
                $title = 'All Time Profit and Loss';
                $data = $this->reportsAccountsAllTime_fetchData(['EXPENSE', 'EXPENSES', 'INCOME', 'INCOME - CURRENT']);
                return view('reports.accounts.all_time_profit_and_loss', compact('data', 'title'));
            case 'reports.accounts.AllTimeAccountsFullBalanceSheet':
                $title = 'All Time Balance Sheet';
                $data = $this->reportsAccountsAllTime_fetchData(['ASSET - FIXED', 'ASSETS - CURRENT', 'CAPITAL', 'LIABILITY', 'LIABILITIES - SHORT']);
                return view('reports.accounts.all_time_balance_sheet', compact('data', 'title'));
            default:
                abort(404);
        }
    }

    private function reportsAccountsAllTime_fetchData(array $accountTypes)
    {
        return DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->whereIn('sacco_main_account.main_account_type', $accountTypes)
            ->select(
                'sacco_sub_account.sub_account_id',
                'sacco_sub_account.sub_account_name',
                'sacco_sub_account.sub_account_code',
                'sacco_sub_account.sub_account_debit',
                'sacco_sub_account.sub_account_credit',
                'sacco_main_account.main_account_name',
                'sacco_main_account.main_account_code',
                'sacco_main_account.main_account_type'
            )
            ->orderBy('sacco_main_account.main_account_name')
            ->orderBy('sacco_sub_account.sub_account_name')
            ->get();
    }







    public function reportsLoansIssued(Request $request)
    {
        // Retrieve search filters from the request
        $searchName = $request->input('search_name');
        $searchSaccoId = $request->input('search_sacco_id');
        $searchCompanyName = $request->input('search_company_name');
        $startPeriod = $request->input('start_period');
        $endPeriod = $request->input('end_period');

        // Query to fetch loan issued data
        $loansIssued = DB::table('sacco_loans as l')
            ->join('sacco_members as m', 'l.loan_member', '=', 'm.member_id')
            ->leftJoin('sacco_department as d', 'm.member_dept', '=', 'd.department_id')
            ->leftJoin('sacco_company as c', 'd.department_company_id', '=', 'c.company_id')
            ->select('l.loan_id', 'l.loan_amount', 'l.loan_taken_period', 'l.loan_start_deduction_period', 'l.loan_on', 'm.member_name', 'm.member_sacco_id', 'c.company_name')
            ->when($searchName, function ($query, $searchName) {
                return $query->where('m.member_name', 'like', "%$searchName%");
            })
            ->when($searchSaccoId, function ($query, $searchSaccoId) {
                return $query->where('m.member_sacco_id', 'like', "%$searchSaccoId%");
            })
            ->when($searchCompanyName, function ($query, $searchCompanyName) {
                return $query->where('c.company_name', 'like', "%$searchCompanyName%");
            })
            ->when($startPeriod, function ($query, $startPeriod) {
                return $query->where('l.loan_start_deduction_period', '>=', $startPeriod);
            })
            ->when($endPeriod, function ($query, $endPeriod) {
                return $query->where('l.loan_start_deduction_period', '<=', $endPeriod);
            })
            ->orderBy('l.loan_start_deduction_period', 'desc')
            ->orderBy('l.loan_on')
            ->get();

        // Return the data to the view
        return view('reports.loans.issued', ['loansIssued' => $loansIssued]);
    }


    public function getLoansIssued(Request $request)
    {
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 30);
        $search = $request->input('search', '');

        $query = DB::table('sacco_loans')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_loans.loan_id',
                'sacco_loans.loan_amount',
                'sacco_loans.loan_loan_paid',
                'sacco_loans.loan_on as loan_issued_date',
                'sacco_loans.loan_stoped as loan_status',
                'sacco_members.member_name',
                'sacco_members.member_phone_no',
                'sacco_members.member_national_id',
                'sacco_members.member_kra_pin',
                'sacco_loan_types.loan_type_name',
                'sacco_company.company_name',
                'sacco_loans.loan_taken_period'
            );

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sacco_members.member_name', 'like', "%$search%")
                    ->orWhere('sacco_members.member_phone_no', 'like', "%$search%")
                    ->orWhere('sacco_members.member_national_id', 'like', "%$search%")
                    ->orWhere('sacco_loan_types.loan_type_name', 'like', "%$search%")
                    ->orWhere('sacco_company.company_name', 'like', "%$search%");
            });
        }

        $issuedLoans = $query->orderBy('sacco_loans.loan_taken_period', 'desc')
            ->orderBy('sacco_loans.loan_id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json(['data' => $issuedLoans]);
    }

    public function downloadLoansIssuedReport(Request $request)
    {
        $search = $request->input('search', '');

        $query = DB::table('sacco_loans')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_loans.loan_id',
                'sacco_loans.loan_amount',
                'sacco_loans.loan_loan_paid',
                'sacco_loans.loan_on as loan_issued_date',
                'sacco_loans.loan_stoped as loan_status',
                'sacco_members.member_name',
                'sacco_members.member_phone_no',
                'sacco_members.member_national_id',
                'sacco_members.member_kra_pin',
                'sacco_loan_types.loan_type_name',
                'sacco_company.company_name',
                'sacco_loans.loan_taken_period'
            );

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sacco_members.member_name', 'like', "%$search%")
                    ->orWhere('sacco_members.member_phone_no', 'like', "%$search%")
                    ->orWhere('sacco_members.member_national_id', 'like', "%$search%")
                    ->orWhere('sacco_loan_types.loan_type_name', 'like', "%$search%")
                    ->orWhere('sacco_company.company_name', 'like', "%$search%");
            });
        }

        $issuedLoans = $query->get();

        $filename = 'issued_loans_report_' . date('Ymd') . '.csv';
        $handle = fopen($filename, 'w+');
        fputcsv($handle, [
            'Loan ID',
            'Member Name',
            'Phone Number',
            'National ID',
            'KRA PIN',
            'Company',
            'Loan Type',
            'Loan Amount',
            'Loan Paid',
            'Loan Balance',
            'Loan Taken Period',
            'Issued Date',
            'Status'
        ]);

        foreach ($issuedLoans as $loan) {
            $loanBalance = $loan->loan_amount - $loan->loan_loan_paid;
            fputcsv($handle, [
                $loan->loan_id,
                $loan->member_name,
                $loan->member_phone_no,
                $loan->member_national_id,
                $loan->member_kra_pin,
                $loan->company_name,
                $loan->loan_type_name,
                number_format($loan->loan_amount, 2),
                number_format($loan->loan_loan_paid, 2),
                number_format($loanBalance, 2),
                $loan->loan_taken_period,
                \Carbon\Carbon::parse($loan->loan_issued_date)->format('d-m-Y'),
                $loan->loan_status == 'N' ? 'Active' : 'Stopped'
            ]);
        }

        fclose($handle);

        return response()->download($filename)->deleteFileAfterSend(true);
    }



    public function reportsLoansRepayments(Request $request)
    {
        $startPeriod = $request->input('startPeriod', date('Ym', strtotime('-3 months')));
        $endPeriod = $request->input('endPeriod', date('Ym'));
        $searchName = $request->input('searchName', '');
        $searchCompany = $request->input('searchCompany', '');
        $searchLoanType = $request->input('searchLoanType', '');

        $query = DB::table('sacco_loan_payments')
            ->join('sacco_loans', 'sacco_loan_payments.loan_payments_loan_id', '=', 'sacco_loans.loan_id')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->join('sacco_loan_category', 'sacco_loans.loan_loan_category', '=', 'sacco_loan_category.loan_category_id') // Fixed join condition
            ->select('*'); // Select all fields

        // Apply search filters if any
        if (!empty($searchName) || !empty($searchCompany) || !empty($searchLoanType)) {
            if (!empty($searchName)) {
                $query->where('sacco_members.member_name', 'like', '%' . $searchName . '%');
            }
            if (!empty($searchCompany)) {
                $query->where('sacco_company.company_name', 'like', '%' . $searchCompany . '%');
            }
            if (!empty($searchLoanType)) {
                $query->where('sacco_loan_types.loan_type_name', 'like', '%' . $searchLoanType . '%');
            }
        } else {
            // Default to the last 3 months if no search filters are applied
            $query->whereBetween('sacco_loan_payments.loan_payments_period', [$startPeriod, $endPeriod]);
        }

        $loanRepayments = $query->orderBy('sacco_loan_payments.loan_payments_period', 'desc')
            ->orderBy('sacco_loan_payments.loan_payments_id', 'desc')
            ->get();

        return view('reports.loans.repayments', compact('startPeriod', 'endPeriod', 'searchName', 'searchCompany', 'searchLoanType', 'loanRepayments'));
    }




    // public function reportsLoansRepayments(Request $request)
    // {
    //     $startPeriod = $request->input('startPeriod', date('Ym', strtotime('-3 months')));
    //     $endPeriod = $request->input('endPeriod', date('Ym'));
    //     $searchName = $request->input('searchName', '');
    //     $searchCompany = $request->input('searchCompany', '');
    //     $searchLoanType = $request->input('searchLoanType', '');

    //     return view('reports.loans.repayments', compact('startPeriod', 'endPeriod', 'searchName', 'searchCompany', 'searchLoanType'));
    // }

    public function getLoansRepayments(Request $request)
    {
        $startPeriod = $request->input('startPeriod', date('Ym', strtotime('-3 months')));
        $endPeriod = $request->input('endPeriod', date('Ym'));
        $searchName = $request->input('searchName', '');
        $searchCompany = $request->input('searchCompany', '');
        $searchLoanType = $request->input('searchLoanType', '');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 30);

        $query = DB::table('sacco_loan_payments')
            ->join('sacco_loans', 'sacco_loan_payments.loan_payments_loan_id', '=', 'sacco_loans.loan_id')
            ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_loan_payments.loan_payments_id',
                'sacco_members.member_name',
                'sacco_members.member_phone_no',
                'sacco_members.member_sacco_id',
                'sacco_loan_types.loan_type_name',
                'sacco_loans.loan_amount',
                'sacco_loans.loan_loan_paid',
                'sacco_loan_payments.loan_payments_amount',
                'sacco_loan_payments.loan_payments_period',
                'sacco_loan_payments.loan_payments_paid_on',
                'sacco_loan_payments.loan_payments_docno'
            )
            ->whereBetween('sacco_loan_payments.loan_payments_period', [$startPeriod, $endPeriod])
            ->when(!empty($searchName), function ($query) use ($searchName) {
                return $query->where('sacco_members.member_name', 'like', '%' . $searchName . '%');
            })
            ->when(!empty($searchCompany), function ($query) use ($searchCompany) {
                return $query->where('sacco_company.company_name', 'like', '%' . $searchCompany . '%');
            })
            ->when(!empty($searchLoanType), function ($query) use ($searchLoanType) {
                return $query->where('sacco_loan_types.loan_type_name', 'like', '%' . $searchLoanType . '%');
            })
            ->orderBy('sacco_loan_payments.loan_payments_period', 'desc')
            ->orderBy('sacco_loan_payments.loan_payments_id', 'desc')
            ->offset($offset)
            ->limit($limit);

        $loanRepayments = $query->get();

        return response()->json(['data' => $loanRepayments]);
    }
}
