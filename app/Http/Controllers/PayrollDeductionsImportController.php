<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPayrollDeductionsImportJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PayrollDeductionsImportController extends Controller
{
    private const SESSION_KEY = 'payroll_deductions_import_preview';

    private const NATIONAL_ID_HEADER = 'ID NUMBER';

    private const PREFIX_DEPOSITS = 'DEPOSITS';
    private const PREFIX_CAPITAL  = 'CAPITAL';
    private const PREFIX_OTHERS   = 'OTHERS';
    private const PREFIX_LOAN     = 'LOAN';

    private array $identityColumns = [
        'EMPLOYEE CODE',
        'EMPLOYEE NAME',
        'PAYROLL NUMBER',
        'PAYROLL NO',
        'STAFF NUMBER',
        'STAFF NO',
        'MEMBER NUMBER',
        'MEMBER NO',
        'MEMBER CODE',
        'ID NUMBER',
        'NATIONAL ID',
        'ID NO',
    ];

    private array $totalColumns = [
        'TOTAL',
        'GRAND TOTAL',
    ];

    public function index()
    {
        $currentPeriod = $this->getActivePeriod();

        $companies = DB::table('sacco_company')
            ->where(function ($query) {
                $query->whereNull('company_deleted')
                    ->orWhere('company_deleted', '')
                    ->orWhere('company_deleted', '<>', 'Y');
            })
            ->orderBy('company_name')
            ->select('company_id', 'company_name', 'company_account')
            ->get();

        $preview = session(self::SESSION_KEY);

        return view('payroll_deductions_import.index', compact(
            'currentPeriod',
            'companies',
            'preview'
        ));
    }

    public function preview(Request $request)
    {
        @set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');

        $currentPeriod = $this->getActivePeriod();

        $validated = $request->validate([
            'company_id'   => ['required', 'integer', 'exists:sacco_company,company_id'],
            'period'       => ['nullable', 'digits:6'],
            'doc_no'       => ['required', 'string', 'max:100'],
            'payment_date' => ['required', 'date'],
            'import_file'  => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $period = !empty($validated['period'])
            ? $validated['period']
            : $currentPeriod->period_name;

        $file = $request->file('import_file');

        $filename = now()->format('YmdHis') . '_' . Str::random(12) . '.' . $file->getClientOriginalExtension();

        $storagePath = $file->storeAs(
            'payroll_deductions_imports',
            $filename,
            'local'
        );

        $result = $this->buildValidatedPayload(
            $storagePath,
            (int) $validated['company_id'],
            strtoupper(trim($validated['doc_no'])),
            $validated['payment_date'],
            $period
        );

        if (!$result['ok']) {
            Storage::disk('local')->delete($storagePath);
            session()->forget(self::SESSION_KEY);

            return redirect()
                ->route('payroll.deductions.import.index')
                ->withErrors($result['errors'])
                ->withInput();
        }

        session([
            self::SESSION_KEY => [
                'storage_path'  => $storagePath,
                'original_name' => $file->getClientOriginalName(),
                'company_id'    => (int) $validated['company_id'],
                'company_name'  => $result['company']->company_name,
                'doc_no'        => strtoupper(trim($validated['doc_no'])),
                'payment_date'  => $validated['payment_date'],
                'period'        => $period,
                'summary'       => $result['summary'],
                'columns'       => $result['columns'],
                'loan_columns'  => array_values($result['columns']['loans']),
            ],
        ]);

        return view('payroll_deductions_import.preview', [
            'currentPeriod' => $currentPeriod,
            'preview'       => session(self::SESSION_KEY),
            'summary'       => $result['summary'],
            'columns'       => $result['columns'],
            'loanColumns'   => $result['columns']['loans'],
            'sampleRows'    => array_slice($result['rows'], 0, 50),
            'warnings'      => $result['warnings'],
        ]);
    }

    public function process(Request $request)
    {
        $preview = session(self::SESSION_KEY);

        if (!$preview) {
            return redirect()
                ->route('payroll.deductions.import.index')
                ->withErrors(['No validated payroll import preview found. Please upload and preview the file again.']);
        }

        if (!Storage::disk('local')->exists($preview['storage_path'])) {
            session()->forget(self::SESSION_KEY);

            return redirect()
                ->route('payroll.deductions.import.index')
                ->withErrors(['The uploaded payroll file is no longer available. Please upload it again.']);
        }

        $result = $this->buildValidatedPayload(
            $preview['storage_path'],
            (int) $preview['company_id'],
            $preview['doc_no'],
            $preview['payment_date'],
            $preview['period']
        );

        if (!$result['ok']) {
            return redirect()
                ->route('payroll.deductions.import.index')
                ->withErrors($result['errors']);
        }

        ProcessPayrollDeductionsImportJob::dispatch(
            $preview['storage_path'],
            (int) $preview['company_id'],
            $preview['doc_no'],
            $preview['payment_date'],
            $preview['period'],
            Auth::id(),
            $request->ip()
        );

        session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('payroll.deductions.import.index')
            ->with('success', 'Payroll file validated successfully. Import has been sent to background processing.');
    }

    public function cancel()
    {
        $preview = session(self::SESSION_KEY);

        if ($preview && !empty($preview['storage_path'])) {
            Storage::disk('local')->delete($preview['storage_path']);
        }

        session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('payroll.deductions.import.index')
            ->with('success', 'Payroll import preview cancelled.');
    }

    public function sample()
    {
        $loanTypes = DB::table('sacco_loan_types')
            ->orderBy('loan_type_name')
            ->limit(5)
            ->pluck('loan_type_name')
            ->toArray();

        $fosaTypes = DB::table('sacco_fosa_types')
            ->orderBy('type_name')
            ->limit(2)
            ->pluck('type_name')
            ->toArray();

        if (empty($fosaTypes)) {
            $fosaTypes = ['Welfare'];
        }

        $headers = [
            'Employee Code',
            'Employee Name',
            'ID Number',
            'DEPOSITS - Monthly Deposit',
            'CAPITAL - Share Capital',
        ];

        foreach ($fosaTypes as $typeName) {
            $headers[] = 'OTHERS - ' . $typeName;
        }

        foreach ($loanTypes as $loanTypeName) {
            $headers[] = 'LOAN - ' . $loanTypeName;
        }

        $headers[] = 'Total';

        return response()->streamDownload(function () use ($headers, $fosaTypes, $loanTypes) {
            $out = fopen('php://output', 'w');

            fputcsv($out, $headers);

            $row = [
                'EMP001',
                'SAMPLE MEMBER',
                '12345678',
                1000,
                500,
            ];

            $rowTotal = 1500;

            foreach ($fosaTypes as $index => $typeName) {
                $amount = $index === 0 ? 300 : 0;
                $row[] = $amount;
                $rowTotal += $amount;
            }

            foreach ($loanTypes as $index => $loanTypeName) {
                $amount = $index === 0 ? 2000 : 0;
                $row[] = $amount;
                $rowTotal += $amount;
            }

            $row[] = $rowTotal;

            fputcsv($out, $row);
            fclose($out);
        }, 'payroll_deductions_import_sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function buildValidatedPayload(
        string $storagePath,
        int $companyId,
        string $docNo,
        string $paymentDate,
        string $period
    ): array {
        @set_time_limit(0);
    ini_set('max_execution_time', '0');
    ini_set('memory_limit', '512M');
        $errors = [];
        $warnings = [];

        $company = DB::table('sacco_company')
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereNull('company_deleted')
                    ->orWhere('company_deleted', '')
                    ->orWhere('company_deleted', '<>', 'Y');
            })
            ->first();

        if (!$company) {
            $errors[] = 'Selected institution/company was not found.';
        } elseif (empty($company->company_account)) {
            $errors[] = "Selected institution/company {$company->company_name} has no ledger account configured.";
        }

        if (!preg_match('/^\d{6}$/', (string) $period)) {
            $errors[] = 'Invalid period. Period must be in YYYYMM format.';
        }

        if (!$this->isValidDate($paymentDate)) {
            $errors[] = 'Invalid payment date.';
        }

        if (!Storage::disk('local')->exists($storagePath)) {
            $errors[] = 'Uploaded file not found.';
        }

        if (!empty($errors)) {
            return $this->validationResponse(false, $errors, $warnings);
        }

        $worksheet = $this->readWorksheet($storagePath);
        $headers = $worksheet['headers'];
        $sheetRows = $worksheet['rows'];

        if (empty($headers)) {
            return $this->validationResponse(false, [
                'Could not detect header row. The file must contain an ID Number column.'
            ], $warnings);
        }

        $headerLookup = $this->buildHeaderLookup($headers, $errors);

        $nationalIdHeader = $headerLookup[self::NATIONAL_ID_HEADER]
            ?? $headerLookup['NATIONAL ID']
            ?? $headerLookup['ID NO']
            ?? null;

        if (!$nationalIdHeader) {
            $errors[] = 'Missing required Excel column: ID Number.';
        }

        $columns = $this->classifyColumns($headers, $errors);

        if (!empty($errors)) {
            return $this->validationResponse(false, $errors, $warnings, $columns);
        }

        if ($this->documentAlreadyImported($docNo, $period)) {
            $errors[] = "Document number {$docNo} already has imported records in period {$period}. Import stopped to avoid duplication.";
        }

        $minLoanAmountBillable = DB::table('sacco_defaults')
            ->where('default_name', 'min_loan_amount_bill_able')
            ->value('default_value');

        if (!is_numeric($minLoanAmountBillable)) {
            $minLoanAmountBillable = 1;
        }

        $seenNationalIds = [];
        $validRows = [];

        $summary = [
            'period'               => $period,
            'doc_no'               => $docNo,
            'payment_date'         => $paymentDate,
            'company_id'           => $companyId,
            'company_name'         => $company->company_name ?? null,
            'valid_rows'           => 0,
            'total_deposits'       => 0,
            'total_capital'        => 0,
            'total_others'         => 0,
            'total_loans'          => 0,
            'grand_total'          => 0,
            'deposit_column_count' => count($columns['deposits']),
            'capital_column_count' => count($columns['capital']),
            'others_column_count'  => count($columns['others']),
            'loan_column_count'    => count($columns['loans']),
            'total_contribution'   => 0,
            'total_welfare'        => 0,
        ];

        foreach ($sheetRows as $row) {
            $excelRow = $row['_excel_row'] ?? '?';

            if ($this->isTotalsRow($row, $nationalIdHeader)) {
                continue;
            }

            $errors = array_merge(
                $errors,
                $this->validateUnknownColumns($row, $columns['unknown'], $excelRow)
            );

            $rowDeposits = $this->extractPostingAmounts($row, $columns['deposits'], $excelRow, $errors);
            $rowCapital  = $this->extractPostingAmounts($row, $columns['capital'], $excelRow, $errors);
            $rowOthers   = $this->extractPostingAmounts($row, $columns['others'], $excelRow, $errors);
            $rowLoans    = $this->extractPostingAmounts($row, $columns['loans'], $excelRow, $errors);

            $rowDepositTotal = $this->sumAmounts($rowDeposits);
            $rowCapitalTotal = $this->sumAmounts($rowCapital);
            $rowOthersTotal  = $this->sumAmounts($rowOthers);
            $rowLoanTotal    = $this->sumAmounts($rowLoans);
            $rowTotal        = $rowDepositTotal + $rowCapitalTotal + $rowOthersTotal + $rowLoanTotal;

            if ($rowTotal <= 0) {
                continue;
            }

            $nationalId = $this->normalizeNationalId($row[$nationalIdHeader] ?? '');

            if ($nationalId === '') {
                $errors[] = "Missing National ID in Excel row {$excelRow}.";
                continue;
            }

            if (isset($seenNationalIds[$nationalId])) {
                $errors[] = "Duplicate National ID {$nationalId} appears in Excel rows {$seenNationalIds[$nationalId]} and {$excelRow}.";
                continue;
            }

            $seenNationalIds[$nationalId] = $excelRow;

            $members = $this->findMembersByNationalId($nationalId);

            if ($members->count() === 0) {
                $errors[] = "No member found for National ID {$nationalId} in Excel row {$excelRow}.";
                continue;
            }

            if ($members->count() > 1) {
                $errors[] = "More than one member found for National ID {$nationalId} in Excel row {$excelRow}. Import stopped because member matching is ambiguous.";
                continue;
            }

            $member = $members->first();

            foreach ($rowLoans as $index => $loanPosting) {
              $targetLoan = $this->findTargetLoanForMemberAndType(
    (int) $member->member_id,
    (int) $loanPosting['loan_type_id'],
    $loanPosting['lookup_loan_type_ids'] ?? [(int) $loanPosting['loan_type_id']],
    $period,
    (float) $minLoanAmountBillable
);

                if (!$targetLoan) {
                    $errors[] = "No {$loanPosting['loan_type_name']} loan record found for {$member->member_name} / National ID {$nationalId} in Excel row {$excelRow}.";
                    continue;
                }

                $rowLoans[$index]['target_loan_id'] = $targetLoan->loan_id;
$rowLoans[$index]['target_loan_type_id'] = $targetLoan->loan_loan_type ?? null;
            }

            $this->validateDeclaredTotal($row, $columns['totals'], $rowTotal, $excelRow, $errors);

            $validRows[] = [
                'excel_row'          => $excelRow,
                'member_id'          => $member->member_id,
                'member_name'        => $member->member_name,
                'member_national_id' => $member->member_national_id,
                'member_active'      => $member->member_active ?? null,
                'member_deleted'     => $member->member_deleted ?? null,
                'deposits'           => $rowDeposits,
                'capital'            => $rowCapital,
                'others'             => $rowOthers,
                'loans'              => $rowLoans,
                'row_total'          => $rowTotal,

                'contribution_amount' => $rowDepositTotal,
                'welfare_amount'      => $rowOthersTotal,
                'loan_amounts'        => $rowLoans,
            ];

            $summary['total_deposits'] += $rowDepositTotal;
            $summary['total_capital']  += $rowCapitalTotal;
            $summary['total_others']   += $rowOthersTotal;
            $summary['total_loans']    += $rowLoanTotal;
            $summary['grand_total']    += $rowTotal;
        }

        $summary['valid_rows'] = count($validRows);
        $summary['total_contribution'] = $summary['total_deposits'];
        $summary['total_welfare'] = $summary['total_others'];

        if (empty($validRows)) {
            $errors[] = 'No valid payable rows found in the Excel file.';
        }

        $this->validateDefaultAccountsForTotals($summary, $errors);

        return [
            'ok'       => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
            'company'  => $company,
            'columns'  => $columns,
            'rows'     => $validRows,
            'summary'  => $summary,
        ];
    }

    private function classifyColumns(array $headers, array &$errors): array
{
    $columns = $this->emptyColumnClassification();

    /*
     * Loan types are loaded as-is.
     *
     * Important:
     * - Excel validation remains exact.
     * - If Excel says LOAN - NORMAL, NORMAL must exist.
     * - If Excel says LOAN - NORMAL LOAN, NORMAL LOAN must exist.
     * - We do NOT merge NORMAL and NORMAL LOAN at this stage.
     */
    $loanQuery = DB::table('sacco_loan_types')
        ->select(
            'loan_type_id',
            'loan_type_name',
            'loan_type_acount',
            'loan_type_int_account'
        );

    if (Schema::hasColumn('sacco_loan_types', 'loan_type_active')) {
        $loanQuery->addSelect('loan_type_active');
    }

    if (Schema::hasColumn('sacco_loan_types', 'loan_type_deleted')) {
        $loanQuery->addSelect('loan_type_deleted');
    }

    $loanTypes = $loanQuery
        ->orderBy('loan_type_name')
        ->get();

    $loanTypeMap = [];

    foreach ($loanTypes as $loanType) {
        $key = $this->normalizeImportName($loanType->loan_type_name);

        if ($key === '') {
            continue;
        }

        if (isset($loanTypeMap[$key])) {
            $errors[] = "Duplicate loan type after normalization: {$key}.";
            continue;
        }

        $loanTypeMap[$key] = $loanType;
    }

    $fosaQuery = DB::table('sacco_fosa_types')
        ->select('type_id', 'type_name');

    if (Schema::hasColumn('sacco_fosa_types', 'type_prefix')) {
        $fosaQuery->addSelect('type_prefix');
    }

    if (Schema::hasColumn('sacco_fosa_types', 'type_active')) {
        $fosaQuery->addSelect('type_active');
    }

    $fosaTypes = $fosaQuery
        ->orderBy('type_name')
        ->get();

    $fosaTypeMap = [];

    foreach ($fosaTypes as $fosaType) {
        $key = $this->normalizeImportName($fosaType->type_name);

        if ($key === '') {
            continue;
        }

        if (isset($fosaTypeMap[$key])) {
            $errors[] = "Duplicate Others/FOSA type after normalization: {$key}.";
            continue;
        }

        $fosaTypeMap[$key] = $fosaType;
    }

    foreach ($headers as $header) {
        $originalHeader = trim((string) $header);
        $normalizedHeader = $this->normalizeImportName($originalHeader);

        if ($normalizedHeader === '') {
            continue;
        }

        if (in_array($normalizedHeader, $this->identityColumns, true)) {
            $columns['identity'][$originalHeader] = [
                'excel_column' => $originalHeader,
                'normalized'   => $normalizedHeader,
            ];
            continue;
        }

        if (in_array($normalizedHeader, $this->totalColumns, true)) {
            $columns['totals'][$originalHeader] = [
                'excel_column' => $originalHeader,
                'normalized'   => $normalizedHeader,
            ];
            continue;
        }

        $parsed = $this->parsePrefixedHeader($normalizedHeader);

        if (!$parsed) {
            $columns['unknown'][$originalHeader] = [
                'excel_column' => $originalHeader,
                'normalized'   => $normalizedHeader,
            ];
            continue;
        }

        [$prefix, $itemName] = $parsed;

        if ($prefix === self::PREFIX_DEPOSITS) {
            $columns['deposits'][$originalHeader] = [
                'excel_column' => $originalHeader,
                'prefix'       => $prefix,
                'item_name'    => $itemName,
            ];
            continue;
        }

        if ($prefix === self::PREFIX_CAPITAL) {
            $columns['capital'][$originalHeader] = [
                'excel_column' => $originalHeader,
                'prefix'       => $prefix,
                'item_name'    => $itemName,
            ];
            continue;
        }

        if ($prefix === self::PREFIX_OTHERS) {
            if (!isset($fosaTypeMap[$itemName])) {
                $errors[] = "Others/FOSA type missing in sacco_fosa_types: {$itemName}. Excel column: {$originalHeader}";
                continue;
            }

            $columns['others'][$originalHeader] = [
                'excel_column'   => $originalHeader,
                'prefix'         => $prefix,
                'item_name'      => $itemName,
                'fosa_type_id'   => $fosaTypeMap[$itemName]->type_id,
                'fosa_type_name' => $fosaTypeMap[$itemName]->type_name,
            ];
            continue;
        }

        if ($prefix === self::PREFIX_LOAN) {
            /*
             * Level 1 validation:
             * The Excel loan name must exist exactly after normalisation.
             *
             * Example:
             * LOAN - NORMAL must match NORMAL.
             * LOAN - NORMAL LOAN must match NORMAL LOAN.
             */
            if (!isset($loanTypeMap[$itemName])) {
                $errors[] = "Loan type missing in sacco_loan_types: {$itemName}. Excel column: {$originalHeader}";
                continue;
            }

            $loanType = $loanTypeMap[$itemName];

            /*
             * Level 2 lookup:
             * Used later when checking the member's actual loan.
             *
             * Example:
             * Excel matched NORMAL, but member loan may be stored as NORMAL LOAN.
             */
            $lookupLoanTypeIds = $this->getLoanTypeLookupIdsForImport(
                $loanTypes,
                $loanType->loan_type_name,
                (int) $loanType->loan_type_id
            );

            if (empty($loanType->loan_type_acount)) {
                $errors[] = "Loan principal account missing for loan type: {$loanType->loan_type_name}";
            }

            if (empty($loanType->loan_type_int_account)) {
                $errors[] = "Loan interest account missing for loan type: {$loanType->loan_type_name}";
            }

            $columns['loans'][$originalHeader] = [
                'excel_column'           => $originalHeader,
                'prefix'                 => $prefix,
                'item_name'              => $itemName,

                /*
                 * This remains the exact Excel-matched loan type.
                 * It is the primary type and should remain the accounting/config reference.
                 */
                'loan_type_id'           => (int) $loanType->loan_type_id,
                'loan_type_name'         => $loanType->loan_type_name,

                /*
                 * These are only used to find the member's actual loan.
                 * They allow NORMAL to also search NORMAL LOAN / NORMAL LOANS,
                 * and NORMAL LOAN to also search NORMAL.
                 */
                'lookup_loan_type_ids'   => $lookupLoanTypeIds,

                'loan_principal_account' => $loanType->loan_type_acount,
                'loan_interest_account'  => $loanType->loan_type_int_account,
            ];

            continue;
        }
    }

    return $columns;
}

    private function parsePrefixedHeader(string $normalizedHeader): ?array
    {
        if (!preg_match('/^(DEPOSITS|CAPITAL|OTHERS|LOAN)\s*[-:]\s*(.+)$/', $normalizedHeader, $matches)) {
            return null;
        }

        $prefix = $this->normalizeImportName($matches[1]);
        $itemName = $this->normalizeImportName($matches[2]);

        if ($itemName === '') {
            return null;
        }

        return [$prefix, $itemName];
    }

    private function extractPostingAmounts(array $row, array $columns, $excelRow, array &$errors): array
    {
        $postings = [];

        foreach ($columns as $originalHeader => $column) {
            [$ok, $amount] = $this->parseAmount($row[$originalHeader] ?? null);

            if (!$ok) {
                $errors[] = "Invalid amount under {$originalHeader} in Excel row {$excelRow}.";
                continue;
            }

            if ($amount < 0) {
                $errors[] = "Negative amount under {$originalHeader} in Excel row {$excelRow}.";
                continue;
            }

            if ($amount <= 0) {
                continue;
            }

            $postings[] = array_merge($column, [
                'amount' => round($amount, 2),
            ]);
        }

        return $postings;
    }

    private function validateUnknownColumns(array $row, array $unknownColumns, $excelRow): array
    {
        $errors = [];

        foreach ($unknownColumns as $originalHeader => $column) {
            $raw = trim((string) ($row[$originalHeader] ?? ''));

            if ($raw === '') {
                continue;
            }

            [$ok, $amount] = $this->parseAmount($raw);

            if ($ok && $amount != 0.0) {
                $errors[] = "Unmapped financial column found: {$originalHeader} in Excel row {$excelRow}. Use prefix DEPOSITS, CAPITAL, OTHERS, or LOAN.";
            }
        }

        return $errors;
    }

    private function validateDeclaredTotal(array $row, array $totalColumns, float $calculatedTotal, $excelRow, array &$errors): void
    {
        if (empty($totalColumns)) {
            return;
        }

        $firstTotalColumn = array_key_first($totalColumns);

        if (!$firstTotalColumn) {
            return;
        }

        [$ok, $declaredTotal] = $this->parseAmount($row[$firstTotalColumn] ?? null);

        if ($ok && abs($declaredTotal - $calculatedTotal) > 0.05) {
            $errors[] = "Row total mismatch in Excel row {$excelRow}. Sheet total is {$declaredTotal}, calculated total is {$calculatedTotal}.";
        }
    }

    private function validateDefaultAccountsForTotals(array $summary, array &$errors): void
    {
        if (($summary['total_deposits'] ?? 0) > 0) {
            $this->validateDefaultAccount('default_share_account', 'Deposits default account', $errors);
        }

        if (($summary['total_capital'] ?? 0) > 0) {
            $this->validateDefaultAccount('default_share_capital_account', 'Capital default account', $errors);
        }

        if (($summary['total_others'] ?? 0) > 0) {
            $this->validateDefaultAccount('default_fosa_account', 'Others/FOSA default account', $errors);
        }
    }

    private function validateDefaultAccount(string $defaultName, string $label, array &$errors): void
    {
        $value = DB::table('sacco_defaults')
            ->where('default_name', $defaultName)
            ->value('default_value');

        if (!is_numeric($value) || (int) $value <= 0) {
            $errors[] = "Missing or invalid {$label}: {$defaultName}.";
            return;
        }

        $exists = DB::table('sacco_sub_account')
            ->where('sub_account_id', (int) $value)
            ->where(function ($query) {
                $query->whereNull('sub_account_deleted')
                    ->orWhere('sub_account_deleted', '')
                    ->orWhere('sub_account_deleted', '<>', 'Y');
            })
            ->exists();

        if (!$exists) {
            $errors[] = "{$label} points to an invalid sub-account ID: {$value}.";
        }
    }

    private function readWorksheet(string $storagePath): array
    {
        $absolutePath = Storage::disk('local')->path($storagePath);

        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheet(0);

        // formatData = false prevents IDs like 21976606 from becoming 21,976,606.00.
        $rawRows = $sheet->toArray(null, true, false, true);

        $headerRowNumber = null;
        $headersByColumn = [];

        foreach ($rawRows as $rowNumber => $row) {
            $normalizedValues = array_map(fn ($value) => $this->normalizeImportName($value), $row);

            if (
                in_array(self::NATIONAL_ID_HEADER, $normalizedValues, true) ||
                in_array('NATIONAL ID', $normalizedValues, true) ||
                in_array('ID NO', $normalizedValues, true)
            ) {
                $headerRowNumber = $rowNumber;

                foreach ($row as $column => $header) {
                    $header = trim((string) $header);

                    if ($header !== '') {
                        $headersByColumn[$column] = $header;
                    }
                }

                break;
            }
        }

        if (!$headerRowNumber || empty($headersByColumn)) {
            return [
                'headers' => [],
                'rows'    => [],
            ];
        }

        $rows = [];

        foreach ($rawRows as $rowNumber => $row) {
            if ($rowNumber <= $headerRowNumber) {
                continue;
            }

            $assoc = [
                '_excel_row' => $rowNumber,
            ];

            $hasAnyValue = false;

            foreach ($headersByColumn as $column => $header) {
                $value = $row[$column] ?? null;

                if (trim((string) $value) !== '') {
                    $hasAnyValue = true;
                }

                $assoc[$header] = $value;
            }

            if ($hasAnyValue) {
                $rows[] = $assoc;
            }
        }

        return [
            'headers' => array_values($headersByColumn),
            'rows'    => $rows,
        ];
    }

    private function buildHeaderLookup(array $headers, array &$errors): array
    {
        $lookup = [];

        foreach ($headers as $header) {
            $normalized = $this->normalizeImportName($header);

            if ($normalized === '') {
                continue;
            }

            if (isset($lookup[$normalized])) {
                $errors[] = "Duplicate Excel column header found after normalization: {$normalized}";
                continue;
            }

            $lookup[$normalized] = $header;
        }

        return $lookup;
    }

    /**
     * Payroll import must match members by National ID.
     * Members do NOT need to be active; inactive/dormant members can still receive payroll postings.
     * Soft-deleted members are still excluded.
     */
    private function findMembersByNationalId(string $nationalId)
    {
        return DB::table('sacco_members')
            ->whereRaw(
                "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(member_national_id), ' ', ''), '-', ''), ',', ''), '.00', ''), '.0', '')) = ?",
                [$nationalId]
            )
            ->where(function ($query) {
                $query->whereNull('member_deleted')
                    ->orWhere('member_deleted', '')
                    ->orWhere('member_deleted', '<>', 'Y');
            })
            ->get();
    }

    private function findTargetLoanForMemberAndType(
    int $memberId,
    int $primaryLoanTypeId,
    array $lookupLoanTypeIds,
    string $period,
    float $minLoanAmountBillable
) {
    $threshold = max(1, (float) $minLoanAmountBillable);

    $lookupLoanTypeIds = array_values(array_unique(array_filter(array_map('intval', $lookupLoanTypeIds))));

    if (empty($lookupLoanTypeIds)) {
        $lookupLoanTypeIds = [$primaryLoanTypeId];
    }

    if (!in_array($primaryLoanTypeId, $lookupLoanTypeIds, true)) {
        array_unshift($lookupLoanTypeIds, $primaryLoanTypeId);
    }

    $fallbackLoanTypeIds = array_values(array_diff($lookupLoanTypeIds, [$primaryLoanTypeId]));

    /*
     * 1. First try the exact Excel-matched loan type.
     *
     * Example:
     * Excel says LOAN - NORMAL.
     * First search member loans under NORMAL only.
     */
    $loan = $this->findLoanByTypeIds(
        $memberId,
        [$primaryLoanTypeId],
        $period,
        $threshold,
        true
    );

    if ($loan) {
        return $loan;
    }

    /*
     * 2. If exact type is not found, try naming variants.
     *
     * Example:
     * Excel says NORMAL, but member loan is stored as NORMAL LOAN.
     */
    if (!empty($fallbackLoanTypeIds)) {
        $loan = $this->findLoanByTypeIds(
            $memberId,
            $fallbackLoanTypeIds,
            $period,
            $threshold,
            true
        );

        if ($loan) {
            return $loan;
        }
    }

    /*
     * 3. Existing fallback behaviour:
     * If no outstanding exact loan is found, try latest exact loan.
     */
    $loan = $this->findLoanByTypeIds(
        $memberId,
        [$primaryLoanTypeId],
        $period,
        $threshold,
        false
    );

    if ($loan) {
        return $loan;
    }

    /*
     * 4. Last fallback:
     * Try latest variant loan.
     */
    if (!empty($fallbackLoanTypeIds)) {
        return $this->findLoanByTypeIds(
            $memberId,
            $fallbackLoanTypeIds,
            $period,
            $threshold,
            false
        );
    }

    return null;
}
   private function baseLoanQuery(int $memberId, array $loanTypeIds)
{
    $loanTypeIds = array_values(array_unique(array_filter(array_map('intval', $loanTypeIds))));

    return DB::table('sacco_loans')
        ->where('loan_member', $memberId)
        ->whereIn('loan_loan_type', $loanTypeIds)
        ->where('loan_amount', '>', 0)
        ->where(function ($query) {
            $query->whereNull('loan_stoped')
                ->orWhere('loan_stoped', '')
                ->orWhere('loan_stoped', '<>', 'Y');
        });
}

    private function applyLoanOrdering($query): void
    {
        if (Schema::hasColumn('sacco_loans', 'loan_taken_period')) {
            $query->orderBy('loan_taken_period', 'desc');
        }

        if (Schema::hasColumn('sacco_loans', 'loan_on')) {
            $query->orderBy('loan_on', 'desc');
        }

        $query->orderBy('loan_id', 'desc');
    }

    private function documentAlreadyImported(string $docNo, string $period): bool
    {
        $shareExists = DB::table('sacco_shares')
            ->where('share_period', $period)
            ->where('share_doc_no', $docNo)
            ->where('share_end_month_proc', 'Y')
            ->exists();

        $capitalExists = DB::table('sacco_capital_shares')
            ->where('share_capitalperiod', $period)
            ->where('share_capitaldoc_no', $docNo)
            ->where('share_capitalend_month_proc', 'Y')
            ->exists();

        $fosaExists = DB::table('sacco_fosas')
            ->where('fosa_period', $period)
            ->where('fosa_doc_no', $docNo)
            ->where('fosa_end_month_proc', 'Y')
            ->exists();

        $loanExists = DB::table('sacco_loan_payments')
            ->where('loan_payments_period', $period)
            ->where('loan_payments_docno', $docNo)
            ->where('loan_end_month_proc', 'Y')
            ->exists();

        return $shareExists || $capitalExists || $fosaExists || $loanExists;
    }

    private function getActivePeriod()
    {
        $currentPeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->where(function ($query) {
                $query->whereNull('period_deleted')
                    ->orWhere('period_deleted', '')
                    ->orWhere('period_deleted', '<>', 'Y');
            })
            ->first();

        abort_if(!$currentPeriod, 500, 'No active period found.');

        return $currentPeriod;
    }

    private function parseAmount($value): array
    {
        $raw = trim((string) $value);

        if ($raw === '' || $raw === '-') {
            return [true, 0.0];
        }

        $clean = str_replace([',', ' '], '', $raw);
        $clean = preg_replace('/[^\d.\-]/', '', $clean);

        if ($clean === '' || $clean === '-' || !is_numeric($clean)) {
            return [false, 0.0];
        }

        return [true, (float) $clean];
    }

    private function normalizeImportName($value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return strtoupper($value);
    }

    private function normalizeNationalId($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return strtoupper(number_format((float) $value, 0, '', ''));
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = str_replace([',', ' ', "\t", "\r", "\n", '-'], '', $value);

        if (preg_match('/^\d+\.0+$/', $value)) {
            $value = preg_replace('/\.0+$/', '', $value);
        }

        if (is_numeric($value)) {
            $value = number_format((float) $value, 0, '', '');
        }

        $value = preg_replace('/[^A-Za-z0-9]/', '', $value);

        return strtoupper($value);
    }

    private function isTotalsRow(array $row, ?string $nationalIdHeader): bool
    {
        $nationalId = $nationalIdHeader ? $this->normalizeNationalId($row[$nationalIdHeader] ?? '') : '';

        if ($nationalId !== '') {
            return false;
        }

        foreach ($row as $value) {
            $normalized = $this->normalizeImportName($value);

            if ($normalized === 'TOTAL' || $normalized === 'GRAND TOTAL') {
                return true;
            }
        }

        return false;
    }

    private function isValidDate(string $date): bool
    {
        try {
            Carbon::parse($date);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function sumAmounts(array $items): float
    {
        return round(array_sum(array_map(fn ($item) => (float) ($item['amount'] ?? 0), $items)), 2);
    }

    private function emptyColumnClassification(): array
    {
        return [
            'identity' => [],
            'totals'   => [],
            'deposits' => [],
            'capital'  => [],
            'others'   => [],
            'loans'    => [],
            'unknown'  => [],
        ];
    }

    private function validationResponse(bool $ok, array $errors, array $warnings = [], ?array $columns = null): array
    {
        return [
            'ok'       => $ok,
            'errors'   => $errors,
            'warnings' => $warnings,
            'company'  => null,
            'columns'  => $columns ?: $this->emptyColumnClassification(),
            'rows'     => [],
            'summary'  => [
                'valid_rows'           => 0,
                'total_deposits'       => 0,
                'total_capital'        => 0,
                'total_others'         => 0,
                'total_loans'          => 0,
                'grand_total'          => 0,
                'total_contribution'   => 0,
                'total_welfare'        => 0,
                'deposit_column_count' => 0,
                'capital_column_count' => 0,
                'others_column_count'  => 0,
                'loan_column_count'    => 0,
            ],
        ];
    }

   private function findLoanByTypeIds(
    int $memberId,
    array $loanTypeIds,
    string $period,
    float $threshold,
    bool $outstandingOnly
) {
    $loanTypeIds = array_values(array_unique(array_filter(array_map('intval', $loanTypeIds))));

    if (empty($loanTypeIds)) {
        return null;
    }

    $query = $this->baseLoanQuery($memberId, $loanTypeIds)
        ->where(function ($query) use ($period) {
            $query->whereNull('loan_taken_period')
                ->orWhere('loan_taken_period', '')
                ->orWhere('loan_taken_period', '<=', $period);
        });

    if ($outstandingOnly) {
        $query->where(function ($query) use ($period) {
            $query->whereNull('loan_start_deduction_period')
                ->orWhere('loan_start_deduction_period', '')
                ->orWhere('loan_start_deduction_period', '<=', $period);
        });

        $query->whereRaw(
            '(COALESCE(loan_amount, 0) - COALESCE(loan_loan_paid, 0)) > ?',
            [$threshold]
        );
    }

    /*
     * Very important:
     * Respect alias priority before date ordering.
     *
     * Example fallback order:
     * EMERGENCY LOAN
     * EMERGENCY LOANS
     * EMERGENCY TOP UP
     * EMERGENCY LOAN TOP UP
     */
    $placeholders = implode(',', array_fill(0, count($loanTypeIds), '?'));

    $query->orderByRaw(
        "FIELD(loan_loan_type, {$placeholders}) ASC",
        $loanTypeIds
    );

    $this->applyLoanOrdering($query);

    return $query->first();
}
private function getLoanTypeLookupIdsForImport($loanTypes, string $exactLoanTypeName, int $primaryLoanTypeId): array
{
    $orderedNames = $this->loanTypeNameVariants($exactLoanTypeName);

    $nameToIds = [];

    foreach ($loanTypes as $loanType) {
        $dbName = $this->normalizeLoanTypeComparableName($loanType->loan_type_name ?? '');

        if ($dbName === '') {
            continue;
        }

        if (!isset($nameToIds[$dbName])) {
            $nameToIds[$dbName] = [];
        }

        $nameToIds[$dbName][] = (int) $loanType->loan_type_id;
    }

    $ids = [$primaryLoanTypeId];

    foreach ($orderedNames as $name) {
        if (!isset($nameToIds[$name])) {
            continue;
        }

        foreach ($nameToIds[$name] as $id) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

private function loanTypeNameVariants(string $loanTypeName): array
{
    $name = $this->normalizeLoanTypeComparableName($loanTypeName);

    if ($name === '') {
        return [];
    }

    /*
     * Remove loan/top-up suffixes to get the clean base name.
     *
     * Examples:
     * EMERGENCY LOAN TOP UP => EMERGENCY
     * EMERGENCY TOP UP      => EMERGENCY
     * EMERGENCY LOAN        => EMERGENCY
     * UWEZO LOAN TOP-UP     => UWEZO
     */
    $baseName = $name;

    $baseName = preg_replace('/\s+LOANS?\s+TOP\s+UP$/', '', $baseName);
    $baseName = preg_replace('/\s+LOANS?\s+TOPUP$/', '', $baseName);
    $baseName = preg_replace('/\s+TOP\s+UP$/', '', $baseName);
    $baseName = preg_replace('/\s+TOPUP$/', '', $baseName);
    $baseName = preg_replace('/\s+LOANS?$/', '', $baseName);

    $baseName = trim($baseName);

    if ($baseName === '') {
        $baseName = $name;
    }

    /*
     * Ordered fallback list.
     * The order matters.
     */
    $variants = [
        $baseName,
        $baseName . ' LOAN',
        $baseName . ' LOANS',
    ];

    /*
     * Controlled top-up fallback.
     * Add NORMAL here only if NORMAL should also repay NORMAL LOAN TOP UP.
     */
    $topUpAllowed = [
        'EMERGENCY',
        'SCHOOL FEES',
        'UWEZO',
        'KARIBISHA',
    ];

    if (in_array($baseName, $topUpAllowed, true)) {
        $variants[] = $baseName . ' TOP UP';
        $variants[] = $baseName . ' TOPUP';
        $variants[] = $baseName . ' LOAN TOP UP';
        $variants[] = $baseName . ' LOAN TOPUP';
        $variants[] = $baseName . ' LOANS TOP UP';
        $variants[] = $baseName . ' LOANS TOPUP';
    }

    return array_values(array_unique(array_filter($variants)));
}

private function normalizeLoanTypeComparableName($value): string
{
    $value = strtoupper(trim((string) $value));

    /*
     * NORMAL-LOAN, NORMAL_LOAN, NORMAL   LOAN
     * all become NORMAL LOAN.
     */
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim($value);
}
}