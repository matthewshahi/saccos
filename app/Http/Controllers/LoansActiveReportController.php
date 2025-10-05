<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\CheckUserRights;

 use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class LoansActiveReportController extends Controller
{
    public function index(Request $request)
    {
        // ===== Permissions =====
        $canEdit = CheckUserRights::userHasRight('LoansReportEdit'); // Edit rights only; view is handled by route middleware

        // ===== Filters =====
        $term        = trim((string)$request->get('q', ''));
        $loanType    = $request->get('loan_type');
        $companyId   = $request->get('company_id');
        $perPage     = (int)($request->get('per_page', 25));
        $perPage     = ($perPage > 0 && $perPage <= 200) ? $perPage : 25;

        // ===== Ensure minimum balance threshold exists =====
        $minThreshold = DB::table('sacco_defaults')
            ->where('default_name', 'threshold_amount')
            ->value('default_value');

        if (is_null($minThreshold)) {
            DB::table('sacco_defaults')->updateOrInsert(
                ['default_name' => 'threshold_amount'],
                [
                    'default_value'      => 0,
                    'default_userid'     => auth()->id() ?? 1,
                    'default_ip'         => request()->ip(),
                    'default_transdate'  => now(),
                ]
            );
            $minThreshold = 0.0;
        } else {
            $minThreshold = (float) $minThreshold;
        }

        // ===== Get global loan calculation method =====
        $loanCalcMethod = DB::table('sacco_defaults')
            ->where('default_name', 'loan_calc_method')
            ->value('default_value');
        $loanCalcMethod = $loanCalcMethod ? strtolower(trim($loanCalcMethod)) : null; // null => both editable

        // ===== Base query =====
        $base = DB::table('sacco_loans as L')
            ->join('sacco_members as M', 'M.member_id', '=', 'L.loan_member')
            ->leftJoin('sacco_department as D', 'D.department_id', '=', 'M.member_dept')
            ->leftJoin('sacco_company as C', 'C.company_id', '=', 'D.department_company_id')
            ->leftJoin('sacco_loan_types as LT', 'LT.loan_type_id', '=', 'L.loan_loan_type')
            ->where('M.member_deleted', 'N')
            ->where('M.member_active', 'Y')
            ->where(function ($q) {
                $q->whereNull('L.loan_stoped')->orWhere('L.loan_stoped', 'N');
            })
            ->select([
    'L.loan_id',
    'L.loan_member',
    'L.loan_loan_type',
    'L.loan_loan_category',
    'L.loan_amount',
    'L.loan_insurance',
    'L.loan_commision',
    'L.loan_taken_period',
    'L.loan_payment_period',
    'L.loan_interest_payable',
    'L.loan_monthly_repayment_amount',
    'L.loan_monthly_repayment_principal',
    'L.loan_amount_guaranteed',
    'L.loan_loan_paid',
    'L.loan_doc_no',
    'L.loan_description',
    'L.loan_batch_no',
    'L.loan_start_deduction_period',
    'L.loan_old_loan_id',
    'L.loan_account_credited',
    'L.loan_account_debited',
    'L.loan_on',
    'L.loan_by',
    'L.loan_ip',
    'M.member_name',
    'M.member_phone_no',
    'M.member_sacco_id',
    'C.company_id',
    'C.company_name',
    'LT.loan_type_name',
    DB::raw('COALESCE(LT.loan_type_interest_type, "FIXED INTEREST") as interest_method'),
    DB::raw('COALESCE(LT.loan_type_interest, 0) as annual_rate'),
            ]);

        // ===== Filters =====
        if ($term !== '') {
            $like = '%' . $term . '%';
            $base->where(function ($q) use ($like) {
                $q->where('M.member_name', 'like', $like)
                    ->orWhere('M.member_phone_no', 'like', $like)
                    ->orWhere('C.company_name', 'like', $like)
                    ->orWhere('L.loan_doc_no', 'like', $like)
                    ->orWhere('L.loan_description', 'like', $like)
                    ->orWhere('L.loan_loan_category', 'like', $like);
            });
        }

        if (!empty($loanType)) $base->where('L.loan_loan_type', $loanType);
        if (!empty($companyId)) $base->where('C.company_id', $companyId);

        // ===== Current balances =====
        $query = DB::query()->fromSub($base, 'X')
            ->select([
                'X.*',
                DB::raw('(COALESCE(X.loan_amount,0) - COALESCE(X.loan_loan_paid,0)) as current_balance')
            ])
            ->whereRaw('(COALESCE(X.loan_amount,0) - COALESCE(X.loan_loan_paid,0)) >= ?', [$minThreshold]);

        // ===== Totals =====
        $totals = (array) DB::query()->fromSub($base, 'T')
    ->selectRaw('
        COUNT(*) as count_rows,
        SUM(COALESCE(T.loan_monthly_repayment_principal,0)) as sum_monthly_principal,
        SUM(COALESCE(T.loan_monthly_repayment_amount,0)) as sum_monthly_amount,
        SUM(COALESCE(T.loan_amount,0)) as sum_loan_amount,
        SUM((COALESCE(T.loan_amount,0) - COALESCE(T.loan_loan_paid,0))) as sum_current_balance
    ')
    ->whereRaw('(COALESCE(T.loan_amount,0) - COALESCE(T.loan_loan_paid,0)) >= ?', [$minThreshold])
    ->first();

        // ===== Pagination =====
        $records = $query->orderBy('X.loan_on', 'desc')->paginate($perPage)->appends($request->query());

        // ===== Dropdowns =====
        $companies = DB::table('sacco_company')->select('company_id','company_name')->orderBy('company_name')->get();
        $loanTypes = DB::table('sacco_loan_types')->select('loan_type_id','loan_type_name')->orderBy('loan_type_name')->get();

        return view('reports.loans.active.index', compact(
            'records','totals','companies','loanTypes',
            'term','loanType','companyId','perPage',
            'minThreshold','loanCalcMethod','canEdit'
        ));
    }

    // ================================
    // PATCH: Update Monthly Principal
    // ================================
    public function updateMonthlyPrincipal(Request $request, $loanId)
{
    $principal = (float) $request->input('principal');

    if ($principal <= 0) {
        return response()->json([
            'ok' => false,
            'message' => 'Principal must be greater than zero.'
        ], 422);
    }

    // 🔹 1. Get or create active period
    $activePeriod = DB::table('sacco_period')
        ->where('period_active', 'Y')
        ->value('period_name');

    if (!$activePeriod) {
        // Auto-create current period (format YYYYmm)
        $activePeriod = now()->format('Ym');
        DB::table('sacco_period')->update(['period_active' => 'N']); // deactivate others

        DB::table('sacco_period')->insert([
            'period_name'       => $activePeriod,
            'period_active'     => 'Y',
            'period_user_id'    => Auth::id() ?? 1,
            'period_transdate'  => now(),
            'period_ip'         => $request->ip(),
            'period_deleted'    => 'N',
        ]);
    }

    // 🔹 2. Fetch loan & type details
    $loan = DB::table('sacco_loans as L')
        ->leftJoin('sacco_loan_types as LT', 'LT.loan_type_id', '=', 'L.loan_loan_type')
        ->where('L.loan_id', $loanId)
        ->select([
            'L.loan_id',
            'L.loan_amount',
            'L.loan_loan_paid',
            DB::raw('COALESCE(LT.loan_type_interest_type, "FIXED INTEREST") as interest_method'),
            DB::raw('COALESCE(LT.loan_type_interest, 0) as annual_rate')
        ])
        ->first();

    if (!$loan) {
        return response()->json(['ok' => false, 'message' => 'Loan not found.'], 404);
    }

    // 🔹 3. Calculate balance
    $loanAmount  = (float) ($loan->loan_amount ?? 0);
    $loanPaid    = (float) ($loan->loan_loan_paid ?? 0);
    $balance     = max(0, $loanAmount - $loanPaid);

    // 🔹 4. Prevent overpayment
    if ($principal > $balance) {
        return response()->json([
            'ok' => false,
            'message' => 'Principal cannot exceed current balance (' . number_format($balance, 2) . ').'
        ], 422);
    }

    // 🔹 5. Interest logic
    $method = strtoupper(trim($loan->interest_method));
    $annualRatePct = (float) $loan->annual_rate;
    $interestComponent = 0.0;

    if ($method === 'REDUCING BALANCE' || $method === 'REDUCING') {

        // Check if payment already made in this active period
        $existingPayment = DB::table('sacco_loan_payments')
            ->where('loan_payments_loan_id', $loanId)
            ->where('loan_payments_period', $activePeriod)
            ->where('loan_payments_amount', '>', 0)
            ->first();

        $monthlyRate = $annualRatePct / 12 / 100;

        if ($existingPayment && $existingPayment->loan_payments_interest > 0) {
            $interestComponent = 0.0; // already applied → skip
        } else {
            $interestComponent = round($balance * $monthlyRate, 2); // calculate for this period
        }

    } else {
        // 🔹 FIXED interest — directly on the principal
        $interestComponent = round($principal * ($annualRatePct / 100), 2);
    }

    $newMonthlyAmount = round($principal + $interestComponent, 2);

    // 🔹 6. Update loan
    DB::table('sacco_loans')
        ->where('loan_id', $loanId)
        ->update([
            'loan_monthly_repayment_principal' => $principal,
            'loan_monthly_repayment_amount'    => $newMonthlyAmount,
            'loan_by'                          => Auth::id(),
            'loan_ip'                          => $request->ip(),
            'loan_on'                          => now(),
        ]);

    // 🔹 7. Respond
    return response()->json([
        'ok'                 => true,
        'principal'          => $principal,
        'amount'             => $newMonthlyAmount,
        'interest_component' => $interestComponent,
        'interest_method'    => $method,
        'loan_balance'       => $balance,
        'annual_rate'        => $annualRatePct,
        'active_period'      => $activePeriod,
    ]);
}
    // ================================
    // PATCH: Update Monthly Amount
    // ================================
    public function updateMonthlyAmount(Request $request, $loanId)
    {
        $amount = (float) $request->input('amount');
        if ($amount <= 0) {
            return response()->json(['ok'=>false,'message'=>'Amount must be positive.'], 422);
        }

        $exists = DB::table('sacco_loans')->where('loan_id', $loanId)->exists();
        if (!$exists) return response()->json(['ok'=>false,'message'=>'Loan not found'], 404);

        DB::table('sacco_loans')->where('loan_id', $loanId)->update([
            'loan_monthly_repayment_amount' => $amount,
            // 'loan_updated_by'               => Auth::id(),
            // 'loan_updated_ip'               => request()->ip(),
            // 'loan_updated_on'               => now(),
        ]);

        return response()->json(['ok'=>true,'amount'=>$amount]);
    }

   

public function export(Request $request, $format)
{
    $term        = trim((string)$request->get('q', ''));
    $loanType    = $request->get('loan_type');
    $companyId   = $request->get('company_id');
    $minThreshold = (float) DB::table('sacco_defaults')->where('default_name', 'threshold_amount')->value('default_value') ?? 0;

    // same base query used in index()
    $base = DB::table('sacco_loans as L')
        ->join('sacco_members as M', 'M.member_id', '=', 'L.loan_member')
        ->leftJoin('sacco_department as D', 'D.department_id', '=', 'M.member_dept')
        ->leftJoin('sacco_company as C', 'C.company_id', '=', 'D.department_company_id')
        ->leftJoin('sacco_loan_types as LT', 'LT.loan_type_id', '=', 'L.loan_loan_type')
        ->select([
            'L.loan_id',
            'M.member_name',
            'M.member_phone_no',
            'C.company_name',
            'LT.loan_type_name',
            'L.loan_amount',
            DB::raw('(L.loan_amount - COALESCE(L.loan_loan_paid,0)) as current_balance'),
            'L.loan_monthly_repayment_principal',
            'L.loan_monthly_repayment_amount',
            DB::raw('COALESCE(LT.loan_type_interest,0) as annual_rate'),
            DB::raw('COALESCE(LT.loan_type_interest_type,"FIXED INTEREST") as interest_method')
        ])
        ->where(function($q){
            $q->whereNull('L.loan_stoped')->orWhere('L.loan_stoped','N');
        });

    if ($term) {
        $like = "%$term%";
        $base->where(function($q) use ($like) {
            $q->where('M.member_name','like',$like)
              ->orWhere('M.member_phone_no','like',$like)
              ->orWhere('C.company_name','like',$like)
              ->orWhere('L.loan_doc_no','like',$like)
              ->orWhere('L.loan_description','like',$like);
        });
    }

    if ($loanType) $base->where('L.loan_loan_type',$loanType);
    if ($companyId) $base->where('C.company_id',$companyId);

    $records = $base
        // ->havingRaw('(L.loan_amount - COALESCE(L.loan_loan_paid,0)) >= ?', [$minThreshold])
        ->whereRaw('(COALESCE(L.loan_amount,0) - COALESCE(L.loan_loan_paid,0)) >= ?', [$minThreshold])
        ->orderBy('L.loan_on','desc')
        ->get();

    // ✅ Excel export
    if ($format === 'xlsx') {
        return Excel::download(new \App\Exports\LoansExport($records), 'Active_Loans_'.now()->format('Ymd_His').'.xlsx');
    }

    // ✅ PDF export
    if ($format === 'pdf') {
        $pdf = Pdf::loadView('reports.loans.active.export-pdf', compact('records'));
        return $pdf->download('Active_Loans_'.now()->format('Ymd_His').'.pdf');
    }

    abort(400, 'Invalid export format');
}
}