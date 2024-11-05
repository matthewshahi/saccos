<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LoanEndMonthController extends Controller
{
    private $currentPeriod;

    public function __construct()
    {
        $this->middleware('auth');
        // Set the current period only once
        $this->currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where('period_deleted', '<>', 'Y')
            ->first();
    }

    // Display the end-month loan processing page
    
    public function endMonthLoans(Request $request)
{
    // Fetch the cut-off date and minimum loan amount billable
    $cutOffDate = $this->getCutOffDate();
    $minLoanAmountBillable = $this->getDefaultAccountValue('min_loan_amount_bill_able') ?? 1;

    $query = DB::table('sacco_company')
        ->join('sacco_department', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
        ->join('sacco_members', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
        ->join('sacco_loans', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
        ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
        ->select(
            'sacco_company.company_name',
            'sacco_loan_types.loan_type_name',
            'sacco_company.company_id',
            DB::raw('SUM(sacco_loans.loan_monthly_repayment_amount) AS lr_m_payment')
        )
        ->where('sacco_members.member_deleted', '!=', 'Y')
        ->where('sacco_members.member_active', 'Y')
        ->where('sacco_members.member_date_joined', '<=', $cutOffDate)
        ->where('sacco_company.company_account', '>', 0)
        ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$minLoanAmountBillable])
        ->where('sacco_loans.loan_amount', '>', 0)
        ->where('sacco_loans.loan_stoped', '!=', 'Y');

    // Apply filters for search
    if ($request->filled('search_institution')) {
        $query->where('sacco_company.company_name', 'like', '%' . $request->input('search_institution') . '%');
    }

    if ($request->filled('search_loan_type')) {
        $query->where('sacco_loan_types.loan_type_name', 'like', '%' . $request->input('search_loan_type') . '%');
    }

    $companies = $query->groupBy('sacco_company.company_name', 'sacco_loan_types.loan_type_name', 'sacco_company.company_id')
        ->orderBy('sacco_company.company_name', 'asc')
        ->get();

    if ($companies->isEmpty()) {
        return redirect()->route('proc.end.month.loans')->with('error', 'No loans to process.');
    }

    return view('loans.end_month_processing', [
        'companies' => $companies,
        'currentPeriod' => $this->currentPeriod
    ]);
}

    // Process end-month loan payments
    public function processEndMonthLoans(Request $request)
    {
        $submittedCount = $request->input('submitted');
        $errors = [];

        DB::beginTransaction();

        try {
            for ($i = 0; $i < $submittedCount; $i++) {
                $companyId = $request->input("update{$i}");
                $loanDocNo = $request->input("loan_doc_no{$i}");
                $loanDatePaid = $request->input("loan_date_paid{$i}");
                $loanTypeId = $request->input("loan_type_e{$i}");

                if (is_numeric($companyId) && is_numeric($loanTypeId)) {
                    // Validate or set default for loan date paid
                    $loanDatePaid = $loanDatePaid ?? Carbon::now()->toDateString();

                    // Perform monthly repayment update for the specific loan type and company
                    $this->updateMonthlyRepayments($companyId, $loanTypeId, $loanDocNo, $loanDatePaid);
                }
            }

            DB::commit();
            return redirect()->route('proc.end.month.loans')->with('success', 'End-month loan processing completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('End-month loan processing failed: ' . $e->getMessage());
            return redirect()->route('proc.end.month.loans')->with('error', 'End-month loan processing failed: ' . $e->getMessage());
        }
    }

    // Retrieve the cut-off date from defaults, using today's date if it doesn't exist
    private function getCutOffDate()
    {
        $cutOffDate = DB::table('sacco_defaults')
            ->where('default_name', 'cut_off_date')
            ->value('default_value');

        // Return valid cut-off date or today's date
        return $cutOffDate ? Carbon::parse($cutOffDate)->toDateString() : Carbon::now()->toDateString();
    }

    // Retrieve default account values based on the account name
    private function getDefaultAccountValue($accountName)
    {
        return DB::table('sacco_defaults')
            ->where('default_name', $accountName)
            ->value('default_value');
    }

    // Update monthly loan repayments for specified loans
    private function updateMonthlyRepayments($companyId, $loanTypeId, $loanDocNo, $loanDatePaid)
    {
        $loans = DB::table('sacco_loans')
            ->join('sacco_members', 'sacco_members.member_id', '=', 'sacco_loans.loan_member')
            ->where('sacco_members.member_active', 'Y')
            ->where('sacco_loans.loan_loan_type', $loanTypeId)
            ->where('sacco_members.member_dept', $companyId)
            ->whereRaw('(loan_amount - loan_loan_paid) > 0')
            ->select('loan_id', 'loan_monthly_repayment_amount')
            ->get();

        foreach ($loans as $loan) {
            // Update loan balance
            DB::table('sacco_loans')
                ->where('loan_id', $loan->loan_id)
                ->increment('loan_loan_paid', $loan->loan_monthly_repayment_amount);

            // Log repayment in sacco_loan_payments
            DB::table('sacco_loan_payments')->insert([
                'loan_payments_amount' => $loan->loan_monthly_repayment_amount,
                'loan_payments_docno' => $loanDocNo,
                'loan_payments_paid_on' => $loanDatePaid,
                'loan_payments_loan_id' => $loan->loan_id,
                'loan_payments_by' => auth()->id(),
                'loan_payments_ip' => request()->ip(),
                'loan_payments_period' => $this->currentPeriod->period_name
            ]);
        }
    }
}