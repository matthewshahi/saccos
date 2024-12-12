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
    // Retrieve search input with default values
    $searchInstitution = $request->input('search_institution', '');
    $searchLoanType = $request->input('search_loan_type', '');
    $cutOffDate = $this->getCutOffDate();
    $periodName = $this->currentPeriod->period_name;
    $minLoanAmountBillable = $this->getDefaultAccountValue('min_loan_amount_bill_able') ?? 1;

    try {
        // Build the query with joins and aggregate calculation
        $query = DB::table('sacco_company')
            ->join('sacco_department', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->join('sacco_members', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_loans', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_company.company_name',
                'sacco_loan_types.loan_type_name',
                'sacco_company.company_id',
                'sacco_loan_types.loan_type_id', // Include loan_type_id here
                DB::raw('SUM(sacco_loans.loan_monthly_repayment_amount) AS lr_m_payment')
            )
            ->where([
                ['sacco_members.member_deleted', '!=', 'Y'],
                ['sacco_members.member_active', '=', 'Y'],
                ['sacco_members.member_date_joined', '<=', $cutOffDate],
                ['sacco_company.company_account', '>', 0],
                ['sacco_loans.loan_amount', '>', 0],
                ['sacco_loans.loan_stoped', '!=', 'Y'],
                ['sacco_loans.loan_taken_period', '<=', $periodName],
                ['sacco_loans.loan_start_deduction_period', '<=', $periodName]
            ])
            ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$minLoanAmountBillable]);

        // Apply search filters if specified
        if (!empty($searchInstitution)) {
            $query->where('sacco_company.company_name', 'like', '%' . $searchInstitution . '%');
        }
        if (!empty($searchLoanType)) {
            $query->where('sacco_loan_types.loan_type_name', 'like', '%' . $searchLoanType . '%');
        }

        // Group, order, and get results
        $companies = $query->groupBy(
            'sacco_company.company_name',
            'sacco_loan_types.loan_type_name',
            'sacco_company.company_id',
            'sacco_loan_types.loan_type_id' // Group by loan_type_id to match selection
        )
        ->orderBy('sacco_company.company_name', 'asc')
        ->get();

        // Return the view with retrieved data
        return view('loans.end_month_processing', [
            'companies' => $companies,
            'currentPeriod' => $this->currentPeriod,
            'searchInstitution' => $searchInstitution,
            'searchLoanType' => $searchLoanType
        ]);

    } catch (\Exception $e) {
        // Log the error and redirect back with an error message
        Log::error('Error retrieving end month loan data: ' . $e->getMessage());
        return redirect()->back()->withErrors(['error' => 'Failed to retrieve loan data. Please try again later.']);
    }
}
    
//     public function endMonthLoans(Request $request)
// {
//     // Retrieve search input
//     $searchInstitution = $request->input('search_institution');
//     $searchLoanType = $request->input('search_loan_type');

//     // Build the query
//     $query = DB::table('sacco_company')
//         ->join('sacco_department', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
//         ->join('sacco_members', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
//         ->join('sacco_loans', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
//         ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
//         ->select(
//             'sacco_company.company_name',
//             'sacco_loan_types.loan_type_name',
//             'sacco_company.company_id',
//             DB::raw('SUM(sacco_loans.loan_monthly_repayment_amount) AS lr_m_payment')
//         )
//         ->where('sacco_members.member_deleted', '!=', 'Y')
//         ->where('sacco_members.member_active', 'Y')
//         ->where('sacco_members.member_date_joined', '<=', $this->getCutOffDate())
//         ->where('sacco_company.company_account', '>', 0)
//         ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$this->getDefaultAccountValue('min_loan_amount_bill_able') ?? 1])
//         ->where('sacco_loans.loan_amount', '>', 0)
//         ->where('sacco_loans.loan_stoped', '!=', 'Y')
//         ->where('sacco_loans.loan_taken_period', '<=', $this->currentPeriod->period_name) // Existing condition
//         ->where('sacco_loans.loan_start_deduction_period', '<=', $this->currentPeriod->period_name); // Added condition

//     // Apply search filters
//     if ($searchInstitution) {
//         $query->where('sacco_company.company_name', 'like', '%' . $searchInstitution . '%');
//     }
//     if ($searchLoanType) {
//         $query->where('sacco_loan_types.loan_type_name', 'like', '%' . $searchLoanType . '%');
//     }

//     // Get results
//     $companies = $query->groupBy(
//         'sacco_company.company_name',
//         'sacco_loan_types.loan_type_name',
//         'sacco_company.company_id'
//     )
//     ->orderBy('sacco_company.company_name', 'asc')
//     ->get();

//     // Return the view with search data
//     return view('loans.end_month_processing', [
//         'companies' => $companies,
//         'currentPeriod' => $this->currentPeriod,
//         'searchInstitution' => $searchInstitution,
//         'searchLoanType' => $searchLoanType
//     ]);
// }

    // Process end-month loan payments
    // public function processEndMonthLoans(Request $request)
    // {
    //     $submittedCount = $request->input('submitted');
    //     $errors = [];

    //     DB::beginTransaction();

    //     try {
    //         for ($i = 0; $i < $submittedCount; $i++) {
    //             $companyId = $request->input("update{$i}");
    //             $loanDocNo = $request->input("loan_doc_no{$i}");
    //             $loanDatePaid = $request->input("loan_date_paid{$i}");
    //             $loanTypeId = $request->input("loan_type_e{$i}");

    //             if (is_numeric($companyId) && is_numeric($loanTypeId)) {
    //                 // Validate or set default for loan date paid
    //                 $loanDatePaid = $loanDatePaid ?? Carbon::now()->toDateString();

    //                 // Perform monthly repayment update for the specific loan type and company
    //                 $this->updateMonthlyRepayments($companyId, $loanTypeId, $loanDocNo, $loanDatePaid);
    //             }
    //         }

    //         DB::commit();
    //         return redirect()->route('proc.end.month.loans')->with('success', 'End-month loan processing completed successfully.');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('End-month loan processing failed: ' . $e->getMessage());
    //         return redirect()->route('proc.end.month.loans')->with('error', 'End-month loan processing failed: ' . $e->getMessage());
    //     }
    // }

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

    
    // public function processEndMonthLoans(Request $request)
    // {
    //     $submittedCount = $request->input('submitted');
    //     $errors = [];
    //     $validRows = [];
    //     $period = $this->currentPeriod->period_name;
    
    //     // **Step 1: Validation Loop**
    //     for ($i = 0; $i < $submittedCount; $i++) {
    //         $companyId = $request->input("update{$i}");
            
    //         if (!$companyId) {
    //             continue; // Skip if not selected
    //         }
    
    //         $loanTypeId = $request->input("loan_type_e{$i}");
    //         $loanDocNo = $request->input("loan_doc_no{$i}");
    //         $loanDatePaid = $request->input("loan_date_paid{$i}");
    
    //         // Check if all required fields are filled
    //         if (empty($loanDocNo) || empty($loanDatePaid)) {
    //             $errors[] = "Row " . ($i + 1) . ": Missing document number or payment date for company ID $companyId and loan type $loanTypeId.";
    //             continue;
    //         }
    
    //         // Check if loanDatePaid is a valid date
    //         if (!$this->isValidDate($loanDatePaid)) {
    //             $errors[] = "Row " . ($i + 1) . ": Invalid payment date format for company ID $companyId and loan type $loanTypeId.";
    //             continue;
    //         }
    
    //         // Check if end-month processing has already been done for this entry
    //         if ($this->checkEndMonthProcessing($companyId, $loanTypeId, $period)) {
    //             $errors[] = "Row " . ($i + 1) . ": End-month processing for company ID $companyId, loan type $loanTypeId, and period $period has already been done.";
    //             continue;
    //         }
    
    //         // If all checks pass, add to validRows for processing
    //         $validRows[] = [
    //             'companyId' => $companyId,
    //             'loanTypeId' => $loanTypeId,
    //             'loanDocNo' => $loanDocNo,
    //             'loanDatePaid' => $loanDatePaid,
    //             'period' => $period
    //         ];
    //     }
    
    //     // If there are validation errors, redirect back without processing
    //     if (!empty($errors)) {
    //         return redirect()->route('proc.end.month.loans')->withErrors($errors);
    //     }
      
    //     // **Step 2: Processing Loop** (only if validation passed for all rows)
    //     DB::beginTransaction();
    
    //     try {
    //         foreach ($validRows as $row) {
    //             $this->defaultProcEndMonthLoanUpdate(
    //                 $row['companyId'],
    //                 $row['loanTypeId'],
    //                 $row['loanDocNo'],
    //                 $row['loanDatePaid'],
    //                 $row['period']
    //             );
    //         }
    
    //         DB::commit();
    //         return redirect()->route('proc.end.month.loans')->with('success', 'End-month loan processing completed successfully.');
    //     } catch (\Exception $e) {
    //         // Rollback if an error occurs
    //         DB::rollBack();
    //         Log::error('End-month loan processing failed: ' . $e->getMessage());
    //         return redirect()->route('proc.end.month.loans')->with('error', 'End-month loan processing failed: ' . $e->getMessage());
    //     }
    // }
    public function processEndMonthLoans(Request $request)
{
    $submittedCount = $request->input('submitted');
    $errors = [];
    $validRows = [];
    $period = $this->currentPeriod->period_name;

    // **Step 1: Validation Loop**
    for ($i = 0; $i < $submittedCount; $i++) {
        $companyId = $request->input("update{$i}");

        if (!$companyId) {
            continue; // Skip if not selected
        }

        $loanTypeId = $request->input("loan_type_e{$i}");
        $loanDocNo = $request->input("loan_doc_no{$i}");
        $loanDatePaid = $request->input("loan_date_paid{$i}");

        // Check if all required fields are filled
        if (empty($loanTypeId) || empty($loanDocNo) || empty($loanDatePaid)) {
            $errors[] = "Row " . ($i + 1) . ": Missing loan type, document number, or payment date for company ID $companyId.";
            continue;
        }

        // Check if loanDatePaid is a valid date
        if (!$this->isValidDate($loanDatePaid)) {
            $errors[] = "Row " . ($i + 1) . ": Invalid payment date format for company ID $companyId and loan type $loanTypeId.";
            continue;
        }

        // Check if end-month processing has already been done for this entry
        if ($this->checkEndMonthProcessing($companyId, $loanTypeId, $period)) {
            $errors[] = "Row " . ($i + 1) . ": End-month processing for company ID $companyId, loan type $loanTypeId, and period $period has already been done.";
            continue;
        }

        // If all checks pass, add to validRows for processing
        $validRows[] = [
            'companyId' => $companyId,
            'loanTypeId' => $loanTypeId,
            'loanDocNo' => $loanDocNo,
            'loanDatePaid' => $loanDatePaid,
            'period' => $period
        ];
    }

    // If there are validation errors, redirect back without processing
    if (!empty($errors)) {
        return redirect()->route('proc.end.month.loans')->withErrors($errors);
    }

    // **Step 2: Processing Loop** (only if validation passed for all rows)
    DB::beginTransaction();

    try {
        foreach ($validRows as $row) {
            $this->defaultProcEndMonthLoanUpdate(
                $row['companyId'],
                $row['loanTypeId'],
                $row['loanDocNo'],
                $row['loanDatePaid'],
                $row['period']
            );
        }

        DB::commit();
        return redirect()->route('proc.end.month.loans')->with('success', 'End-month loan processing completed successfully.');
    } catch (\Exception $e) {
        // Rollback if an error occurs
        DB::rollBack();
        Log::error('End-month loan processing failed: ' . $e->getMessage());
        return redirect()->route('proc.end.month.loans')->with('error', 'End-month loan processing failed: ' . $e->getMessage());
    }
}
    /**
     * Helper function to validate date format.
     *
     * @param string $date
     * @return bool
     */
    private function isValidDate($date)
    {
        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

public function checkEndMonthProcessing($companyId, $loanTypeId, $period)
{
    $minLoanAmountBillable = $this->getDefaultAccountValue('min_loan_amount_bill_able') ?? 1;

    // Query to check if end-month processing has been completed for this loan type, period, and company
    $existingPayments = DB::table('sacco_loan_payments')
        ->join('sacco_loans', 'sacco_loan_payments.loan_payments_loan_id', '=', 'sacco_loans.loan_id')
        ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
        ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
        ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
        ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
        ->where('sacco_company.company_id', $companyId)
        ->where('sacco_loan_payments.loan_end_month_proc', 'Y')
        ->where('sacco_loan_payments.loan_payments_period', $period)
        ->where('sacco_loan_types.loan_type_id', $loanTypeId)
        ->exists();

    return $existingPayments; // returns true if processing has been done, false otherwise
}
// private function defaultProcEndMonthLoanUpdate($companyId, $loanTypeId, $loanDocNo, $loanDatePaid)
// {
//     $period = $this->currentPeriod->period_name;
//     $minLoanAmountBillable = $this->getDefaultAccountValue('min_loan_amount_bill_able') ?? 1;
//     $cutOffDate = $this->getCutOffDate();

 
//     // Step 1: Fetch eligible loans with additional conditions
//     $loans = DB::table('sacco_loans')
//         ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
//         ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
//         ->where('sacco_department.department_company_id', $companyId)
//         ->where('sacco_loans.loan_loan_type', $loanTypeId)
//         ->where('sacco_members.member_active', 'Y')
//         ->where('sacco_members.member_deleted', '!=', 'Y')
//         ->where('sacco_members.member_date_joined', '<=', $cutOffDate)
//         ->where('sacco_company.company_account', '>', 0)
//         ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$minLoanAmountBillable])
//         ->where('sacco_loans.loan_amount', '>', 0)
//         ->where('sacco_loans.loan_stoped', '!=', 'Y')
//         ->where('sacco_loans.loan_taken_period', '<=', $period)
//         ->where('sacco_loans.loan_start_deduction_period', '<=', $period)
//         ->select(
//             'sacco_loans.loan_id',
//             'sacco_loans.loan_monthly_repayment_amount',
//             'sacco_loans.loan_loan_paid',
//             'sacco_loans.loan_amount',
//             'sacco_members.member_id',
//             'sacco_loan_types.loan_type_int_account',
//             'sacco_loan_types.loan_type_acount',
//             'sacco_company.company_account'
//         )
//         ->get();
//         dd($loanDocNo);

//         dd($loans);

       
//     foreach ($loans as $loan) {
//         $principalPayment = $loan->loan_monthly_repayment_amount;
//         $interest = 0;

//         // Calculate interest for fixed or variable types
//         $loanInterestType = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->value('interest_type');
//         $loanInterestRate = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->value('interest_rate');

//         if ($loanInterestType == "FIXED INTEREST") {
//             $interest = $principalPayment - ($principalPayment * 100 / ($loanInterestRate + 100));
//         } else {
//             $interest = ($loan->loan_amount - $loan->loan_loan_paid) * $loanInterestRate / 12 / 100;
//         }

//         $principalPaid = $principalPayment - $interest;

//         // Step 2: Insert record in sacco_loan_payments
//         DB::table('sacco_loan_payments')->insert([
//             'loan_payments_amount' => $principalPaid,
//             'loan_payments_interest' => $interest,
//             'loan_payments_docno' => $loanDocNo,
//             'loan_payments_paid_on' => $loanDatePaid,
//             'loan_payments_loan_id' => $loan->loan_id,
//             'loan_payments_period' => $period,
//             'loan_payments_by' => auth()->id(),
//             'loan_payments_ip' => request()->ip(),
//             'loan_end_month_proc' => 'Y'
//         ]);

//         // Step 3: Update loan and member balances
//         DB::table('sacco_loans')
//             ->where('loan_id', $loan->loan_id)
//             ->increment('loan_loan_paid', $principalPaid);

//         DB::table('sacco_members')
//             ->where('member_id', $loan->member_id)
//             ->decrement('member_total_loan', $principalPaid);

//         // Step 4: Adjust guarantor shares
//         $this->updateGuarantorShares($loan->loan_id, $principalPaid);

//         // Step 5: Record accounting transactions
//         $this->recordAccountingTransactions($loan, $companyId, $principalPaid, $interest, $loanDocNo, $period, $loanDatePaid);


//     }
// }
// private function defaultProcEndMonthLoanUpdate($companyId, $loanTypeId, $loanDocNo, $loanDatePaid)
// {
//     $period = $this->currentPeriod->period_name;
//     $minLoanAmountBillable = $this->getDefaultAccountValue('min_loan_amount_bill_able') ?? 1;
//     $cutOffDate = $this->getCutOffDate();

//     // Step 1: Fetch eligible loans with additional conditions
//     $loans = DB::table('sacco_loans')
//         ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
//         ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
//         ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
//         ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
//         ->where('sacco_department.department_company_id', $companyId)
//         ->where('sacco_loans.loan_loan_type', $loanTypeId)
//         ->where('sacco_members.member_active', 'Y')
//         ->where('sacco_members.member_deleted', '!=', 'Y')
//         ->where('sacco_members.member_date_joined', '<=', $cutOffDate)
//         ->where('sacco_company.company_account', '>', 0)
//         ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$minLoanAmountBillable])
//         ->where('sacco_loans.loan_amount', '>', 0)
//         ->where('sacco_loans.loan_stoped', '!=', 'Y')
//         ->where('sacco_loans.loan_taken_period', '<=', $period)
//         ->where('sacco_loans.loan_start_deduction_period', '<=', $period)
//         ->select(
//             'sacco_loans.loan_id',
//             'sacco_loans.loan_monthly_repayment_amount',
//             'sacco_loans.loan_loan_paid',
//             'sacco_loans.loan_amount',
//             'sacco_members.member_id',
//             'sacco_loan_types.loan_type_int_account',
//             'sacco_loan_types.loan_type_acount',
//             'sacco_company.company_account'
//         )
//         ->get();

//         // dd("finished step1");

        

//     foreach ($loans as $loan) {
//         $principalPayment = $loan->loan_monthly_repayment_amount;
//         $interest = 0;


//         // Calculate interest for fixed or variable types
//         $loanInterestType = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->value('interest_type');
//         $loanInterestRate = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->value('interest_rate');

//         if ($loanInterestType == "FIXED INTEREST") {
//             $interest = $principalPayment - ($principalPayment * 100 / ($loanInterestRate + 100));
//         } else {
//             $interest = ($loan->loan_amount - $loan->loan_loan_paid) * $loanInterestRate / 12 / 100;
//         }

//         $principalPaid = $principalPayment - $interest;
        
//         // Step 2: Insert record in sacco_loan_payments
//         DB::table('sacco_loan_payments')->insert([
//             'loan_payments_amount' => $principalPaid,
//             'loan_payments_interest' => $interest,
//             'loan_payments_docno' => $loanDocNo,
//             'loan_payments_paid_on' => $loanDatePaid,
//             'loan_payments_loan_id' => $loan->loan_id,
//             'loan_payments_period' => $period,
//             'loan_payments_by' => auth()->id(),
//             'loan_payments_ip' => request()->ip(),
//             'loan_end_month_proc' => 'Y'
//         ]);
        

//         // Step 3: Update loan and member balances
//         DB::table('sacco_loans')
//             ->where('loan_id', $loan->loan_id)
//             ->increment('loan_loan_paid', $principalPaid);

         
//         DB::table('sacco_members')
//             ->where('member_id', $loan->member_id)
//             ->decrement('member_total_loan', $principalPaid);
//             dd("finished members");
//         // Step 4: Adjust guarantor shares
//         $this->updateGuarantorShares($loan->loan_id, $principalPaid);
    
//         // Step 5: Record accounting transactions
//         $this->recordAccountingTransactions($loan, $companyId, $principalPaid, $interest, $loanDocNo, $period, $loanDatePaid);
      
//     }
// }



private function defaultProcEndMonthLoanUpdate($companyId, $loanTypeId, $loanDocNo, $loanDatePaid)
{
    $period = $this->currentPeriod->period_name;
    $minLoanAmountBillable = $this->getDefaultAccountValue('min_loan_amount_bill_able') ?? 1;
    $cutOffDate = $this->getCutOffDate();

    // Step 1: Fetch eligible loans with additional conditions
    $loans = DB::table('sacco_loans')
        ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
        ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
        ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
        ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
        ->where('sacco_department.department_company_id', $companyId)
        ->where('sacco_loans.loan_loan_type', $loanTypeId)
        ->where('sacco_members.member_active', 'Y')
        ->where('sacco_members.member_deleted', '!=', 'Y')
        ->where('sacco_members.member_date_joined', '<=', $cutOffDate)
        ->where('sacco_company.company_account', '>', 0)
        ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$minLoanAmountBillable])
        ->where('sacco_loans.loan_amount', '>', 0)
        ->where('sacco_loans.loan_stoped', '!=', 'Y')
        ->where('sacco_loans.loan_taken_period', '<=', $period)
        ->where('sacco_loans.loan_start_deduction_period', '<=', $period)
        ->select(
            'sacco_loans.loan_id',
            'sacco_loans.loan_monthly_repayment_amount',
            'sacco_loans.loan_loan_paid',
            'sacco_loans.loan_amount',
            'sacco_members.member_id',
            'sacco_loan_types.loan_type_int_account',
            'sacco_loan_types.loan_type_acount',
            'sacco_company.company_account',
            'sacco_loan_types.loan_type_id'
        )
        ->get();

    foreach ($loans as $loan) {
        $principalPayment = $loan->loan_monthly_repayment_amount;
        $interest = 0;

        // Calculate interest for fixed or variable types
        $loanInterestType = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->value('loan_type_interest_type');
        $loanInterestRate = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->value('loan_type_interest');

        if ($loanInterestType == "FIXED INTEREST") {
            $interest = $principalPayment - ($principalPayment * 100 / ($loanInterestRate + 100));
        } else {
            $interest = ($loan->loan_amount - $loan->loan_loan_paid) * $loanInterestRate / 12 / 100;
        }

        $principalPaid = $principalPayment - $interest;

        // Dynamic description format
        $loanDescription = "Payroll {$period} - Member ID: {$loan->member_id}";
        
        // Step 2: Insert record in sacco_loan_payments
        try {
            DB::table('sacco_loan_payments')->insert([
                'loan_payments_amount' => $principalPaid,
                'loan_payments_interest' => $interest,
                'loan_payments_docno' => $loanDocNo,
                'loan_payments_paid_on' => $loanDatePaid,
                'loan_payments_loan_id' => $loan->loan_id,
                'loan_payments_period' => $period,
                'loan_payments_by' => auth()->id(),
                'loan_payments_ip' => request()->ip(),
                'loan_end_month_proc' => 'Y',
                'loan_payments_description' => $loanDescription,
                'loan_payments_paid_in_by' => "End Month Proc"
            ]);
        } catch (\Exception $e) {
            dd("Error inserting loan payment for loan ID {$loan->loan_id}: " . $e->getMessage());
        }

        // Step 3: Update loan and member balances
        try {
            DB::table('sacco_loans')
                ->where('loan_id', $loan->loan_id)
                ->increment('loan_loan_paid', $principalPaid);

            DB::table('sacco_members')
                ->where('member_id', $loan->member_id)
                ->decrement('member_total_loan', $principalPaid);
        } catch (\Exception $e) {
            dd("Error updating balances for loan ID {$loan->loan_id}: " . $e->getMessage());
        }

        // Step 4: Adjust guarantor shares
        try {
            $this->updateGuarantorShares($loan->loan_id, $principalPaid);
        } catch (\Exception $e) {
            dd("Error updating guarantor shares for loan ID {$loan->loan_id}: " . $e->getMessage());
        }

        // Step 5: Record accounting transactions
        try {
            $this->recordAccountingTransactions($loan, $companyId, $principalPaid, $interest, $loanDocNo, $period, $loanDatePaid);
        } catch (\Exception $e) {
            dd("Error recording accounting transactions for loan ID {$loan->loan_id}: " . $e->getMessage());
        }
    }
}
// private function defaultProcEndMonthLoanUpdate($companyId, $loanTypeId, $loanDocNo, $loanDatePaid)
// {
//     $period = $this->currentPeriod->period_name;
//     $minLoanAmountBillable = $this->getDefaultAccountValue('min_loan_amount_bill_able') ?? 1;
//     $cutOffDate = $this->getCutOffDate();

//     // Step 1: Fetch eligible loans with additional conditions
//     $loans = DB::table('sacco_loans')
//         ->join('sacco_members', 'sacco_loans.loan_member', '=', 'sacco_members.member_id')
//         ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
//         ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
//         ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
//         ->where('sacco_department.department_company_id', $companyId)
//         ->where('sacco_loans.loan_loan_type', $loanTypeId)
//         ->where('sacco_members.member_active', 'Y')
//         ->where('sacco_members.member_deleted', '!=', 'Y')
//         ->where('sacco_members.member_date_joined', '<=', $cutOffDate)
//         ->where('sacco_company.company_account', '>', 0)
//         ->whereRaw('(sacco_loans.loan_amount - sacco_loans.loan_loan_paid) > ?', [$minLoanAmountBillable])
//         ->where('sacco_loans.loan_amount', '>', 0)
//         ->where('sacco_loans.loan_stoped', '!=', 'Y')
//         ->where('sacco_loans.loan_taken_period', '<=', $period)
//         ->where('sacco_loans.loan_start_deduction_period', '<=', $period)
//         ->select(
//             'sacco_loans.loan_id',
//             'sacco_loans.loan_monthly_repayment_amount',
//             'sacco_loans.loan_loan_paid',
//             'sacco_loans.loan_amount',
//             'sacco_members.member_id',
//             'sacco_loan_types.loan_type_int_account',
//             'sacco_loan_types.loan_type_acount',
//             'sacco_company.company_account'
//         )
//         ->get();

//     if ($loans->isEmpty()) {
//         dd("No eligible loans found for company ID: $companyId, loanTypeId: $loanTypeId");
//     }

//     foreach ($loans as $loan) {
//         try {
//             $principalPayment = $loan->loan_monthly_repayment_amount;
//             $interest = 0;

//             // Calculate interest for fixed or variable types
//             $loanInterestType = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->value('interest_type');
//             $loanInterestRate = DB::table('sacco_loan_types')->where('loan_type_id', $loanTypeId)->value('interest_rate');

//             if ($loanInterestType == "FIXED INTEREST") {
//                 $interest = $principalPayment - ($principalPayment * 100 / ($loanInterestRate + 100));
//             } else {
//                 $interest = ($loan->loan_amount - $loan->loan_loan_paid) * $loanInterestRate / 12 / 100;
//             }

//             $principalPaid = $principalPayment - $interest;

//             // Step 2: Insert record in sacco_loan_payments
//             DB::table('sacco_loan_payments')->insert([
//                 'loan_payments_amount' => $principalPaid,
//                 'loan_payments_interest' => $interest,
//                 'loan_payments_docno' => $loanDocNo,
//                 'loan_payments_paid_on' => $loanDatePaid,
//                 'loan_payments_loan_id' => $loan->loan_id,
//                 'loan_payments_period' => $period,
//                 'loan_payments_by' => auth()->id(),
//                 'loan_payments_ip' => request()->ip(),
//                 'loan_end_month_proc' => 'Y'
//             ]);

//             // Step 3: Update loan and member balances
//             DB::table('sacco_loans')
//                 ->where('loan_id', $loan->loan_id)
//                 ->increment('loan_loan_paid', $principalPaid);

//             DB::table('sacco_members')
//                 ->where('member_id', $loan->member_id)
//                 ->decrement('member_total_loan', $principalPaid);

//             // Step 4: Adjust guarantor shares
//             $this->updateGuarantorShares($loan->loan_id, $principalPaid);

//             // Step 5: Record accounting transactions
//             $this->recordAccountingTransactions($loan, $companyId, $principalPaid, $interest, $loanDocNo, $period, $loanDatePaid);
//         } catch (\Exception $e) {
//             dd("Error processing loan ID {$loan->loan_id}: " . $e->getMessage());
//         }
//     }
// }
// private function updateGuarantorShares($loanId, $principalPaid)
// {
//     // Retrieve the loan's total amount guaranteed
//     $loan = DB::table('sacco_loans')
//         ->where('loan_id', $loanId)
//         ->select('loan_amount_guaranteed')
//         ->first();

//     if (!$loan) {
//         throw new \Exception("Loan not found for ID {$loanId}");
//     }

//     $totalLoanGuaranteed = $loan->loan_amount_guaranteed;

//     // Check if the loan amount guaranteed is valid to avoid division by zero
//     if ($totalLoanGuaranteed <= 0) {
//         throw new \Exception("Invalid loan amount guaranteed for loan ID {$loanId}");
//     }

//     // Retrieve all guarantors for the specified loan
//     $guarantors = DB::table('sacco_loan_guarantors')
//         ->join('sacco_members', 'sacco_loan_guarantors.loan_guar_guarantor_id', '=', 'sacco_members.member_id')
//         ->where('sacco_loan_guarantors.loan_guar_loan_id', $loanId)
//         ->where('sacco_loan_guarantors.loan_guar_deleted', '!=', 'Y')
//         ->select('sacco_loan_guarantors.*', 'sacco_members.member_tied_shares', 'sacco_members.member_tied_shares_self')
//         ->get();

//     foreach ($guarantors as $guarantor) {
//         // Calculate the amount to free based on the prorated share of the guarantee
//         $guaranteedShare = $guarantor->loan_guar_amount_guaranteed;
//         $amountToFree = ($guaranteedShare / $totalLoanGuaranteed) * $principalPaid;

//         // Check if the guarantor is self-guaranteeing or guaranteeing someone else
//         if ($guarantor->loan_guar_guarantor_id == $loanId) {
//             // Self-guaranteeing, decrement member_tied_shares_self only
//             DB::table('sacco_members')
//                 ->where('member_id', $guarantor->loan_guar_guarantor_id)
//                 ->decrement('member_tied_shares_self', $amountToFree);
//         } else {
//             // Guaranteeing someone else, decrement member_tied_shares only
//             DB::table('sacco_members')
//                 ->where('member_id', $guarantor->loan_guar_guarantor_id)
//                 ->decrement('member_tied_shares', $amountToFree);
//         }

//         // Update loan_guar_amount_freed in sacco_loan_guarantors table
//         DB::table('sacco_loan_guarantors')
//             ->where('loan_guar_id', $guarantor->loan_guar_id)
//             ->increment('loan_guar_amount_freed', $amountToFree);
//     }
// }
private function updateGuarantorShares($loanId, $principalPaid)
{
    // Retrieve the loan's total amount guaranteed and loan member
    $loan = DB::table('sacco_loans')
        ->where('loan_id', $loanId)
        ->select('loan_amount_guaranteed', 'loan_member')
        ->first();

    if (!$loan) {
        throw new \Exception("Loan not found for ID {$loanId}");
    }

    $totalLoanGuaranteed = $loan->loan_amount_guaranteed;

    // Check if the loan amount guaranteed is valid to avoid division by zero
    if ($totalLoanGuaranteed <= 0) {
        throw new \Exception("Invalid loan amount guaranteed for loan ID {$loanId}");
    }

    // Retrieve all guarantors for the specified loan
    $guarantors = DB::table('sacco_loan_guarantors')
        ->join('sacco_members', 'sacco_loan_guarantors.loan_guar_guarantor_id', '=', 'sacco_members.member_id')
        ->where('sacco_loan_guarantors.loan_guar_loan_id', $loanId)
        ->where('sacco_loan_guarantors.loan_guar_deleted', '!=', 'Y')
        ->select(
            'sacco_loan_guarantors.loan_guar_id',
            'sacco_loan_guarantors.loan_guar_guarantor_id',
            'sacco_loan_guarantors.loan_guar_amount_guaranteed',
            'sacco_loan_guarantors.loan_guar_amount_freed',
            'sacco_members.member_tied_shares',
            'sacco_members.member_tied_shares_self'
        )
        ->get();

    foreach ($guarantors as $guarantor) {
        // Calculate the amount to free based on the prorated share of the guarantee
        $guaranteedShare = $guarantor->loan_guar_amount_guaranteed;
        $amountToFree = ($guaranteedShare / $totalLoanGuaranteed) * $principalPaid;

        // Check if the guarantor is self-guaranteeing or guaranteeing someone else
        if ($guarantor->loan_guar_guarantor_id == $loan->loan_member) {
            // Self-guaranteeing, decrement member_tied_shares_self only
            DB::table('sacco_members')
                ->where('member_id', $guarantor->loan_guar_guarantor_id)
                ->decrement('member_tied_shares_self', $amountToFree);
        } else {
            // Guaranteeing someone else, decrement member_tied_shares only
            DB::table('sacco_members')
                ->where('member_id', $guarantor->loan_guar_guarantor_id)
                ->decrement('member_tied_shares', $amountToFree);
        }

        // Update loan_guar_amount_freed in sacco_loan_guarantors table
        DB::table('sacco_loan_guarantors')
            ->where('loan_guar_id', $guarantor->loan_guar_id)
            ->increment('loan_guar_amount_freed', $amountToFree);

        Log::info("Freed amount for guarantor ID {$guarantor->loan_guar_guarantor_id}: $amountToFree");
    }
}
private function recordAccountingTransactions($loan, $companyId, $principalPaid, $interest, $loanDocNo, $period, $loanDatePaid)
{

    // Define the loan description based on the period and member ID
    $loanDescription = "Payroll $period - Member ID: " . $loan->member_id;

    // Fetch the specific accounts for principal, interest, and the company
    $principalAccount = $loan->loan_type_acount; // Loan principal account
    $interestAccount = $loan->loan_type_int_account; // Loan interest account
    $companyAccount = $loan->company_account; // Company account
    $loan_type_id = $loan->loan_type_id; 

    // Ensure all accounts are valid
    if (!$principalAccount || !$interestAccount || !$companyAccount) {
        throw new \Exception("Account information is incomplete for loan processing.");
    }

    // Step 1: Credit the principal account for the loan with the principal paid amount
    $this->updateSaccoAccountsTrans([
        'sub_account' => $principalAccount,
        'debit' => 0,
        'credit' => $principalPaid,
        'doc_no' => $loanDocNo,
        'description' => $loanDescription,
        'date' => $loanDatePaid,
        'period' => $period,
        'member_id' => $loan->member_id, // Adding member_id for updateSaccoAccountsTrans
        'loan_type_id' => $loan->loan_type_id, // Adding loan_type_id for updateSaccoAccountsTrans
        'company_id' => $companyId, // Adding company_id for updateSaccoAccountsTrans
    ]);

    // Step 2: Credit the interest account for the loan with the calculated interest
    $this->updateSaccoAccountsTrans([
        'sub_account' => $interestAccount,
        'debit' => 0,
        'credit' => $interest,
        'doc_no' => $loanDocNo,
        'description' => $loanDescription,
        'date' => $loanDatePaid,
        'period' => $period,
        'member_id' => $loan->member_id,
        'loan_type_id' => $loan->loan_type_id,
        'company_id' => $companyId,
    ]);

    // Step 3: Debit the company's account for the total of principal + interest
    $this->updateSaccoAccountsTrans([
        'sub_account' => $companyAccount,
        'debit' => $principalPaid + $interest,
        'credit' => 0,
        'doc_no' => $loanDocNo,
        'description' => $loanDescription,
        'date' => $loanDatePaid,
        'period' => $period,
        'member_id' => $loan->member_id,
        'loan_type_id' => $loan->loan_type_id,
        'company_id' => $companyId,
    ]);
}
// private function recordAccountingTransactions($loan, $companyId, $principalPaid, $interest, $loanDocNo, $period, $loanDatePaid)
// {
//     // Define the loan description based on the period and member ID
//     $loanDescription = "Payroll $period - Member ID: " . $loan->member_id;

//     // Fetch the specific accounts for principal, interest, and the company
//     $principalAccount = $loan->loan_type_acount; // Loan principal account
//     $interestAccount = $loan->loan_type_int_account; // Loan interest account
//     $companyAccount = $loan->company_account; // Company account

//     // Ensure all accounts are valid
//     if (!$principalAccount || !$interestAccount || !$companyAccount) {
//         throw new \Exception("Account information is incomplete for loan processing.");
//     }

//     // Step 1: Credit the principal account for the loan with the principal paid amount
//     $this->updateSaccoAccountsTrans([
//         'sub_account' => $principalAccount,
//         'debit' => 0,
//         'credit' => $principalPaid,
//         'doc_no' => $loanDocNo,
//         'description' => $loanDescription,
//         'date' => $loanDatePaid,
//         'period' => $period,
//     ]);

//     // Step 2: Credit the interest account for the loan with the calculated interest
//     $this->updateSaccoAccountsTrans([
//         'sub_account' => $interestAccount,
//         'debit' => 0,
//         'credit' => $interest,
//         'doc_no' => $loanDocNo,
//         'description' => $loanDescription,
//         'date' => $loanDatePaid,
//         'period' => $period,
//     ]);

//     // Step 3: Debit the company's account for the total of principal + interest
//     $this->updateSaccoAccountsTrans([
//         'sub_account' => $companyAccount,
//         'debit' => $principalPaid + $interest,
//         'credit' => 0,
//         'doc_no' => $loanDocNo,
//         'description' => $loanDescription,
//         'date' => $loanDatePaid,
//         'period' => $period,
//     ]);
// }

private function updateSaccoAccountsTrans(array $data)
{
  
    try {
        $userId = auth()->id(); // Get the logged-in user ID
        $userIp = request()->ip(); // Get the IP address

        // Fetch additional details for the description
        $member = DB::table('sacco_members')
            ->select('member_name')
            ->where('member_id', $data['member_id'])
            ->first();

        if (!$member) {
            dd("Error: Member not found for ID {$data['member_id']}");
        }

        $loanType = DB::table('sacco_loan_types')
            ->select('loan_type_name')
            ->where('loan_type_id', $data['loan_type_id'])
            ->first();

        if (!$loanType) {
            dd("Error: Loan type not found for ID {$data['loan_type_id']}");
        }

        $company = DB::table('sacco_company')
            ->select('company_name')
            ->where('company_id', $data['company_id'])
            ->first();

        if (!$company) {
            dd("Error: Company not found for ID {$data['company_id']}");
        }

        // Construct enhanced description
        $description = "Payroll {$data['period']} - Member ID: {$data['member_id']} - " .
                       "Member Name: {$member->member_name} - Loan Type: {$loanType->loan_type_name} - " .
                       "Company: {$company->company_name}";

        // Check if a similar transaction already exists in `sacco_accounts_trans`
        $existingTrans = DB::table('sacco_accounts_trans')
            ->where('accounts_trans_sub_account', $data['sub_account'])
            ->where('accounts_trans_period', $data['period'])
            ->where('accounts_trans_doc_no', $data['doc_no'])
            ->where('accounts_trans_decription', $description)
            ->where('accounts_trans_dat_date', $data['date'])
            ->where('accounts_trans_user_id', $userId)
            ->first();

        if ($existingTrans) {
            // Update existing transaction if it exists
            DB::table('sacco_accounts_trans')
                ->where('accounts_trans_id', $existingTrans->accounts_trans_id)
                ->update([
                    'accounts_trans_debit' => $existingTrans->accounts_trans_debit + $data['debit'],
                    'accounts_trans_credit' => $existingTrans->accounts_trans_credit + $data['credit'],
                    'updated_at' => now(),
                ]);
        } else {
            // Insert a new transaction if it doesn't exist
            DB::table('sacco_accounts_trans')->insert([
                'accounts_trans_sub_account' => $data['sub_account'],
                'accounts_trans_period' => $data['period'],
                'accounts_trans_debit' => $data['debit'],
                'accounts_trans_credit' => $data['credit'],
                'accounts_trans_doc_no' => $data['doc_no'],
                'accounts_trans_decription' => $description,
                'accounts_trans_dat_date' => $data['date'],
                'accounts_trans_user_id' => $userId,
                'accounts_trans_ip' => $userIp,
                'accounts_trans_transdate' => now()
            ]);
        }

        // Update the `sacco_sub_account` balance for debit and credit separately
        DB::table('sacco_sub_account')
            ->where('sub_account_id', $data['sub_account'])
            ->increment('sub_account_debit', $data['debit']);

        DB::table('sacco_sub_account')
            ->where('sub_account_id', $data['sub_account'])
            ->increment('sub_account_credit', $data['credit']);

        // Fetch the `main_account_id` linked to the `sub_account_id`
        $mainAccount = DB::table('sacco_sub_account')
            ->join('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_sub_account.sub_account_id', $data['sub_account'])
            ->select('sacco_main_account.main_account_id')
            ->first();

        if (!$mainAccount) {
            dd("Error: Main account not found for sub-account ID {$data['sub_account']}");
        }

        // Update the main account with the debit and credit values separately
        DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccount->main_account_id)
            ->increment('main_account_debit', $data['debit']);

        DB::table('sacco_main_account')
            ->where('main_account_id', $mainAccount->main_account_id)
            ->increment('main_account_credit', $data['credit']);
    } catch (\Exception $e) {
        dd("Error recording accounting transactions: " . $e->getMessage());
    }
}
}