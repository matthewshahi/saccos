<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KassMigrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /*===========================================================
     |
     |   MAIN SCREEN
     |
     ===========================================================*/
    public function index()
    {
        $files = collect(Storage::files('kass_uploads'))
            ->sortByDesc(fn ($f) => Storage::lastModified($f))
            ->take(20)
            ->map(fn ($f) => [
                'name' => basename($f),
                'path' => $f,
                'time' => date('Y-m-d H:i:s', Storage::lastModified($f)),
            ]);

        return view('kass.index', compact('files'));
    }

    /*===========================================================
     |  UPLOAD SINGLE FILE
     ===========================================================*/
    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx',
        ]);

        $request->file('file')->store('kass_uploads');

        return back()->with('success', "File uploaded successfully.");
    }

    /*===========================================================
     |  UPLOAD ZIP OF FILES
     ===========================================================*/
    public function uploadBatch(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|mimes:zip',
        ]);

        $zipPath   = $request->file('zip_file')->store('kass_zips');
        $extractTo = storage_path('app/kass_batch_' . time());
        mkdir($extractTo, 0775, true);

        $zip = new \ZipArchive;
        if ($zip->open(storage_path("app/{$zipPath}")) === true) {
            $zip->extractTo($extractTo);
            $zip->close();
        }

        return back()->with('success', "ZIP extracted successfully.");
    }

    /*===========================================================
     |  PROCESS A SINGLE FILE (manual button)
     ===========================================================*/
    public function process(Request $request)
    {
        $file = $request->input('file_path');

        if (!$file || !Storage::exists($file)) {
            return back()->with('error', 'File not found.');
        }

        try {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($ext, ['xls', 'xlsx'])) {
                $this->processExcel($file);
            } else {
                $this->processCsv($file);
            }

        } catch (\Exception $e) {
            $this->logFileEvent($file, 'PROCESS_ERROR', $e->getMessage());
            return back()->with('error', "Error: " . $e->getMessage());
        }

        return back()->with('success', "File processed.");
    }

    /*===========================================================
     |
     |   PROCESS ALL FILES – ONE PER HIT (used by auto-refresh)
     |
     ===========================================================*/
    public function processAll()
    {
        // Only pick Excel files
        $files = array_values(array_filter(Storage::files('kass_uploads'), function ($file) {
            return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['xls', 'xlsx']);
        }));

        $remaining = count($files);

        // Nothing left
        if ($remaining === 0) {
            return view('kass.auto', [
                'message'   => "🎉 All files processed! No remaining files.",
                'remaining' => 0,
                'next'      => false,
            ]);
        }

        // Always process only the FIRST file
        $file = $files[0];

        try {
            $this->processExcel($file);
            Storage::delete($file);

            $this->logFileEvent($file, 'FILE_PROCESSED', 'File imported and deleted from kass_uploads.');

            return view('kass.auto', [
                'message'   => "✅ Processed: {$file}",
                'remaining' => $remaining - 1,
                'next'      => true,
            ]);
        } catch (\Exception $e) {

            // Log at file level
            $this->logFileEvent($file, 'PROCESS_ERROR', $e->getMessage());

            // Delete problematic file so we don’t loop forever
            Storage::delete($file);

            return view('kass.auto', [
                'message'   => "❌ Failed on {$file}: " . $e->getMessage(),
                'remaining' => $remaining - 1,
                'next'      => true,
            ]);
        }
    }

    /*===========================================================
     |
     |   EXCEL HANDLING
     |
     |   Valid sheets ONLY:
     |      SHAREMPA => contributions (shares)
     |      LOANMPA  => loans
     |
     ===========================================================*/
    private function processExcel(string $filePath): void
    {
        $fullPath    = storage_path("app/{$filePath}");
        $spreadsheet = IOFactory::load($fullPath);

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[strtoupper(trim($sheet->getTitle()))] = $sheet;
        }

        // SHAREMPA
        if (isset($sheets['SHAREMPA'])) {
            $this->processContributionSheet($sheets['SHAREMPA'], $filePath);
        } else {
            $this->logFileEvent($filePath, "MISSING_SHAREMPA", "SHAREMPA sheet not found.");
        }

        // LOANMPA
        if (isset($sheets['LOANMPA'])) {
            $this->processLoanSheet($sheets['LOANMPA'], $filePath);
        } else {
            $this->logFileEvent($filePath, "MISSING_LOANMPA", "LOANMPA sheet not found.");
        }
    }

    /* If someone uploads CSV/TXT – log and ignore (no fatal error) */
    private function processCsv(string $filePath): void
    {
        $this->logFileEvent($filePath, 'CSV_IGNORED', 'CSV/TXT not supported for structured import.');
    }

    /*===========================================================
     |
     |   SHAREMPA — Contributions Sheet
     |
     |   New rules:
     |   - Only transaction columns between MONTH and RUN BAL / RUNBAL
     |   - Each non-zero numeric transaction => separate row
     |   - raw_type = original column label (e.g. "CAPITAL", "NEW WEL", "DR", "CR")
     |   - Amount stored exactly as numeric (no sign manipulation)
     |   - Opening balance:
     |        opening = run_bal + dr - cr
     |        stored once per (company + adm_no + year)
     |        month = month where RUN BAL first appears
     |   - RUN BAL never stored as a transaction row
     |   - Empty / zero values skipped
     |
     ===========================================================*/
    private function processContributionSheet($sheet, string $sourceFile): void
    {
        $rows  = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn ($r) => array_values($r), $rows);

        // 1) Detect header row (the one that contains "NAME")
        [$headerRaw, $start] = $this->extractHeader($clean);
        $headerNorm          = $this->normalizeHeaders($headerRaw);

        if (!in_array('month', $headerNorm)) {
    $this->logFileEvent($sourceFile, 'INVALID_HEADER', 'MONTH column missing (possibly blank column after NAME).');
}

if (!in_array('year', $headerNorm)) {
    $this->logFileEvent($sourceFile, 'INVALID_HEADER', 'YEAR column misaligned.');
}


        // 2) Locate critical columns by normalized name
        $monthIndex = $this->getMonthIndex($headerNorm);
        $runBalInfo = $this->getRunBalIndexAndKey($headerNorm);
        $runBalIndex = $runBalInfo['index'];  // can be null
        $runBalKey   = $runBalInfo['key'];    // normalized key, e.g. "run_bal"

        if ($monthIndex === null) {
            $this->logFileEvent($sourceFile, 'MISSING_MONTH_COLUMN', 'No MONTH column detected in SHAREMPA.');
        }
        if ($runBalIndex === null) {
            $this->logFileEvent($sourceFile, 'MISSING_RUN_BAL_COLUMN', 'No RUN BAL / RUNBAL column detected in SHAREMPA.');
        }

        // 3) Determine which header indexes are "transaction" columns.
        //    Rule: strictly between MONTH and RUN BAL.
        $transactionIndexes = $this->getTransactionColumnIndexes($headerNorm, $monthIndex, $runBalIndex);

        // 4) Track opening balance per member + year so we only insert once
        //    Key: company|adm_no|year
        $openingDone = [];

        // 5) Carry-down buffers
        $carryName = null;
        $carryAdm  = null;
        $carryComp = null;

        for ($i = $start; $i < count($clean); $i++) {
            $row = $clean[$i];

            if (!$this->rowHasValues($row)) {
                continue;
            }

            // Map row by normalized header keys (name, adm_no, comp, year, month, dr, cr, run_bal, etc.)
            $mapped = $this->mapRow($headerNorm, $row);

            // --- Skip duplicated header rows inside data ---
            if ($this->looksLikeHeaderRow($mapped)) {
                $this->logIssue($sourceFile, 'HEADER_ROW_IN_DATA', $mapped);
                continue;
            }

            // ---- Carry down NAME / ADM / COMPANY (per Excel pattern) ----
            if (!empty($mapped['name'])) {
                $carryName = $mapped['name'];
            } else {
                $mapped['name'] = $carryName;
            }

            if (!empty($mapped['adm_no'])) {
                $carryAdm = $mapped['adm_no'];
            } else {
                $mapped['adm_no'] = $carryAdm;
            }

            if (!empty($mapped['comp'])) {
                $carryComp = $mapped['comp'];
            } else {
                $mapped['comp'] = $carryComp;
            }

            // If still missing key identifiers – log and skip row
            if (empty($mapped['name']) || empty($mapped['adm_no'])) {
                $this->logIssue($sourceFile, 'MISSING_NAME_OR_ADM_AFTER_CARRY', $mapped);
                continue;
            }

            // Clean year / month (month stored as uppercase JAN, FEB, ...)
            $year  = $this->cleanYear($mapped['year'] ?? null, $sourceFile, $mapped);
            $month = $this->cleanMonth($mapped['month'] ?? null, $sourceFile, $mapped);

            // Key for opening-balance control: company + adm + year
            $openingKey = ($carryComp ?? '') . '|' . ($carryAdm ?? '') . '|' . (string) $year;

            // Numeric DR/CR for this row (used for both transactions & opening calc)
            $dr = $this->num($mapped['dr'] ?? null);
            $cr = $this->num($mapped['cr'] ?? null);

            // Numeric RUN BAL for this row (if column exists)
            $runBal = null;
            if ($runBalKey !== null && array_key_exists($runBalKey, $mapped)) {
                $runBal = $this->num($mapped[$runBalKey]);
            }

            // ================== OPENING BALANCE ==================
            //
            // Rule:
            //   opening = run_bal + dr - cr
            //
            // - Only computed the FIRST time we see a numeric RUN BAL
            //   for a (company, adm_no, year) combination.
            // - Stored under the same YEAR + MONTH as that row.
            // - Amount stored exactly as computed (can be + / -).
            // - If result is 0 or year is null → skip.
            // =====================================================
            if ($runBal !== null && !isset($openingDone[$openingKey])) {

                // Use 0 when dr/cr are null
                $drOpening = $dr ?? 0;
                $crOpening = $cr ?? 0;

                $openingAmount = $runBal + $drOpening - $crOpening;

                if ($openingAmount != 0 && $year !== null) {
                    try {
                        DB::table('kass_staging_contributions')->insert([
                            'raw_name'          => $mapped['name'],
                            'member_identifier' => $mapped['pfno'] ?? null,
                            'adm_no'            => $mapped['adm_no'],
                            'company'           => $mapped['comp'],
                            'year'              => $year,
                            'month'             => $month,                  // month where RUN BAL first appears
                            'raw_type'          => 'OPENING_BALANCE',
                            'amount'            => $openingAmount,
                            'source_file'       => $sourceFile,
                            'raw_row_json'      => json_encode($mapped),
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    } catch (\Exception $e) {
                        $this->logIssue(
                            $sourceFile,
                            'DB_INSERT_ERROR_OPENING_BALANCE: ' . $e->getMessage(),
                            array_merge($mapped, ['_opening_amount' => $openingAmount])
                        );
                    }
                }

                // Mark opening as done for this member+year
                $openingDone[$openingKey] = true;
            }

            // ================== TRANSACTION ROWS ==================
            //
            // Now we loop over all transaction columns between MONTH and RUN BAL.
            // For each numeric, non-zero value we insert a row with:
            //   raw_type = original column label (as it appears in Excel)
            //   amount   = numeric value (positive or negative, we don't flip sign)
            //
            // Empty values or zero => skipped entirely.
            // ======================================================
            foreach ($transactionIndexes as $colIndex) {

                // Safety: ensure we have header & row cell for this index
                if (!array_key_exists($colIndex, $row) || !array_key_exists($colIndex, $headerRaw)) {
                    continue;
                }

                $rawHeaderLabel = trim((string) $headerRaw[$colIndex]);  // e.g. "CAPITAL", "NEW WEL", "DR", "CR"
                $rawCellValue   = $row[$colIndex];

                // Clean to numeric (remove commas, spaces, but keep minus sign if any)
                $amount = $this->num($rawCellValue);

                // Skip if not numeric or zero (avoid bloating DB with junk rows)
                if ($amount === null || $amount == 0) {
                    continue;
                }

                // If the header label is blank, fall back to normalized key name
                $normalizedKey = $headerNorm[$colIndex] ?? 'unknown';
                $rawType       = $rawHeaderLabel !== '' ? strtoupper($rawHeaderLabel) : strtoupper($normalizedKey);

                try {
                    DB::table('kass_staging_contributions')->insert([
                        'raw_name'          => $mapped['name'],
                        'member_identifier' => $mapped['pfno'] ?? null,
                        'adm_no'            => $mapped['adm_no'],
                        'company'           => $mapped['comp'],
                        'year'              => $year,
                        'month'             => $month,
                        'raw_type'          => $rawType,          // EXACTLY the transaction type as per column
                        'amount'            => $amount,           // store numeric as-is (can be + or -)
                        'source_file'       => $sourceFile,
                        'raw_row_json'      => json_encode($mapped),
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                } catch (\Exception $e) {
                    $this->logIssue(
                        $sourceFile,
                        'DB_INSERT_ERROR_TRANSACTION: ' . $e->getMessage(),
                        array_merge($mapped, [
                            '_col_index' => $colIndex,
                            '_raw_type'  => $rawType,
                            '_amount'    => $amount,
                        ])
                    );
                    continue;
                }
            }
        }
    }

    /*===========================================================
     |
     |   LOANMPA — Loan Sheet
     |
     |   (Left mostly unchanged; loans logic is simpler)
     |
     ===========================================================*/
    private function processLoanSheet($sheet, string $sourceFile): void
    {
        $rows  = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn ($r) => array_values($r), $rows);

        [$headerRaw, $start] = $this->extractHeader($clean);
        $header = $this->normalizeHeaders($headerRaw);

        $carryName = null;
        $carryAdm  = null;
        $carryComp = null;

        for ($i = $start; $i < count($clean); $i++) {
            $row = $clean[$i];

            if (!$this->rowHasValues($row)) {
                continue;
            }

            $mapped = $this->mapRow($header, $row);

            // Skip header-like rows
            if ($this->looksLikeHeaderRow($mapped)) {
                $this->logIssue($sourceFile, 'HEADER_ROW_IN_DATA_LOAN', $mapped);
                continue;
            }

            // Carry down
            if (!empty($mapped['name'])) {
                $carryName = $mapped['name'];
            } else {
                $mapped['name'] = $carryName;
            }

            if (!empty($mapped['adm_no'])) {
                $carryAdm = $mapped['adm_no'];
            } else {
                $mapped['adm_no'] = $carryAdm;
            }

            if (!empty($mapped['comp'])) {
                $carryComp = $mapped['comp'];
            } else {
                $mapped['comp'] = $carryComp;
            }

            if (empty($mapped['name']) || empty($mapped['adm_no'])) {
                $this->logIssue($sourceFile, 'MISSING_NAME_OR_ADM_AFTER_CARRY_LOAN', $mapped);
                continue;
            }

            $year  = $this->cleanYear($mapped['year'] ?? null, $sourceFile, $mapped);
            $month = $this->cleanMonth($mapped['month'] ?? null, $sourceFile, $mapped);

            $principal = $this->num($mapped['cr'] ?? null);
            $repay     = $this->num($mapped['dr'] ?? null);
            $interest  = $this->num($mapped['int'] ?? null);

            try {
                DB::table('kass_staging_loans')->insert([
                    'raw_name'            => $mapped['name'],
                    'member_identifier'   => $mapped['pfno'] ?? null,
                    'adm_no'              => $mapped['adm_no'],
                    'company'             => $mapped['comp'],
                    'loan_type'           => 'LOANMPA',
                    'year'                => $year,
                    'month'               => $month,
                    'principal_disbursed' => $principal,
                    'repayment_amount'    => $repay,
                    'interest_amount'     => $interest,
                    'period_index'        => $mapped['period'] ?? null,
                    'source_file'         => $sourceFile,
                    'raw_row_json'        => json_encode($mapped),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            } catch (\Exception $e) {
                $this->logIssue($sourceFile, 'DB_INSERT_ERROR_LOAN: ' . $e->getMessage(), $mapped);
                continue;
            }
        }
    }

    /*===========================================================
     |
     |   GENERIC HELPERS
     |
     ===========================================================*/

  private function extractHeader(array $rows): array
{
    foreach ($rows as $i => $row) {

        if (!$this->rowHasValues($row)) {
            continue;
        }

        // Force-remove trailing & inner empty columns
        $trimmed = array_map(function ($v) {
            return trim(str_replace("\xC2\xA0", ' ', (string)$v));
        }, $row);

        // Identify header rows by NAME
        $upper = array_map(fn($v) => strtoupper($v), $trimmed);

        if (in_array('NAME', $upper)) {

            // 🎯 FIX BLANK COLUMNS HERE
            $cleanHeader = [];
            foreach ($trimmed as $colVal) {
                if ($colVal === '') continue; // <-- REMOVE BLANK COLUMNS
                $cleanHeader[] = $colVal;
            }

            return [$cleanHeader, $i + 1];
        }
    }

    throw new \Exception("Header row not found.");
}



    private function rowHasValues($row): bool
    {
        foreach ($row as $x) {
            if (trim((string) $x) !== '') {
                return true;
            }
        }
        return false;
    }

   private function normalizeHeaders($row): array
{
    $norm = [];

    foreach ($row as $h) {

        $clean = trim(str_replace("\xC2\xA0", ' ', (string)$h));

        if ($clean === '') continue; // <-- Skip blanks completely

        $clean = strtolower(str_replace([' ', '/', '.', '-', "\t"], '_', $clean));

        $norm[] = $clean;
    }

    return $norm;
}



    private function mapRow($header, $row): array
    {
        $out = [];
        foreach ($header as $i => $col) {
            $out[$col] = $row[$i] ?? null;
        }
        return $out;
    }

    /* Header-like row detection inside data region */
    private function looksLikeHeaderRow(array $mapped): bool
    {
        $candidates = [
            'name', 'adm_no', 'comp', 'company',
            'year', 'month', 'memb', 'dr', 'cr', 'run_bal',
        ];

        $score = 0;

        foreach ($candidates as $key) {
            if (!isset($mapped[$key])) {
                continue;
            }
            $val = strtoupper(trim((string) $mapped[$key]));
            if (in_array($val, ['NAME', 'ADM NO', 'COMP', 'COMPANY', 'YEAR', 'MONTH', 'MEMB', 'DR', 'CR', 'RUN BAL', 'RUNBAL'])) {
                $score++;
            }
        }

        // If several columns look like headers → treat as header row
        return $score >= 2;
    }

    /* Locate MONTH column index */
    private function getMonthIndex(array $headerNorm): ?int
    {
        foreach ($headerNorm as $i => $col) {
            if ($col === 'month') {
                return $i;
            }
        }
        return null;
    }

    /* Locate RUN BAL / RUNBAL column index & key */
    private function getRunBalIndexAndKey(array $headerNorm): array
    {
        foreach ($headerNorm as $i => $col) {
            // Accept any normalized header that combines "run" and "bal"
            if (str_contains($col, 'run') && str_contains($col, 'bal')) {
                return ['index' => $i, 'key' => $col];
            }
        }

        return ['index' => null, 'key' => null];
    }

    /**
     * Transaction columns = those STRICTLY between MONTH and RUN BAL.
     * - If monthIndex is null → we just consider all columns except obvious non-data ones.
     * - If runBalIndex is null → we consider columns after MONTH up to end.
     */
    private function getTransactionColumnIndexes(array $headerNorm, ?int $monthIndex, ?int $runBalIndex): array
    {
        $indexes = [];
        $max     = count($headerNorm);

        for ($i = 0; $i < $max; $i++) {

            // Skip columns that are clearly structural
            $key = $headerNorm[$i];

            if (in_array($key, ['name', 'adm_no', 'comp', 'company', 'pfno', 'year', 'month', 'period'])) {
                continue;
            }

            // If we know where MONTH is, enforce "after month"
            if ($monthIndex !== null && $i <= $monthIndex) {
                continue;
            }

            // If we know where RUN BAL is, enforce "before run bal"
            if ($runBalIndex !== null && $i >= $runBalIndex) {
                continue;
            }

            $indexes[] = $i;
        }

        return $indexes;
    }

    /* Year cleaner – converts to int or null, logs bad values */
    private function cleanYear($value, string $file, array $row)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $this->logIssue($file, 'INVALID_YEAR_VALUE', array_merge($row, ['_year_raw' => $value]));
        return null;
    }

    /* Month cleaner – uppercase string, logs weird ones (but still stores) */
    private function cleanMonth($value, string $file, array $row)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $month = strtoupper($value);
        $valid = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUNE', 'JULY', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        if (!in_array($month, $valid)) {
            $this->logIssue($file, 'UNEXPECTED_MONTH_VALUE', array_merge($row, ['_month_raw' => $value]));
        }

        return $month;
    }

    /**
     * Numeric normaliser:
     * - Removes commas and spaces.
     * - Keeps minus sign if present.
     * - Returns float or null if not numeric.
     */
    private function num($v)
    {
        if ($v === null || $v === '') {
            return null;
        }

        // Convert e.g. " 12,000 " → "12000"
        $v = str_replace([',', ' '], '', (string) $v);

        // After cleaning, must be numeric to be valid
        return is_numeric($v) ? (float) $v : null;
    }

    /*===========================================================
     |
     |   LOGGING HELPERS
     |
     ===========================================================*/

    // File-level events (missing sheets, process errors, etc.)
    private function logFileEvent(string $file, string $type, string $note): void
    {
        $line = "[" . now() . "] FILE: {$file} | TYPE: {$type} | NOTE: {$note}\n";
        Storage::append('logs/kass_missing_sheets.log', $line);
    }

    // Row-level issues (bad data, DB insert failures, misaligned rows, etc.)
    private function logIssue(string $file, string $message, array $row = []): void
    {
        $line = "[" . now() . "] FILE: {$file} | ISSUE: {$message} | ROW: " . json_encode($row) . "\n";
        Storage::append('logs/kass_migration_issues.log', $line);
    }

    /*===========================================================
     | STAGING SCREEN
     ===========================================================*/
    public function staging()
    {
        return view('kass.staging', [
            'contrib' => DB::table('kass_staging_contributions')->paginate(50),
            'loans'   => DB::table('kass_staging_loans')->paginate(50),
        ]);
    }

    public function clearStaging()
    {
        DB::table('kass_staging_contributions')->truncate();
        DB::table('kass_staging_loans')->truncate();

        return back()->with('success', 'Staging cleared.');
    }

    /*===========================================================
     |  Tracker-based one-by-one endpoint (optional)
     ===========================================================*/
    public function processNext()
    {
        $trackerPath = storage_path('app/kass_tracker.json');

        if (!file_exists($trackerPath)) {
            file_put_contents($trackerPath, json_encode([
                'last_file' => null,
                'status'    => 'IDLE',
            ], JSON_PRETTY_PRINT));
        }

        $tracker = json_decode(file_get_contents($trackerPath), true);

        $files = array_values(array_filter(Storage::files('kass_uploads'), function ($file) {
            return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['xls', 'xlsx']);
        }));

        if (empty($files)) {
            return "No XLS/XLSX files found.";
        }

        $index = 0;

        if (!empty($tracker['last_file'])) {
            $index = array_search($tracker['last_file'], $files);
            $index = ($index === false) ? 0 : $index + 1;
        }

        if ($index >= count($files)) {
            return "All files processed.";
        }

        $fileToProcess        = $files[$index];
        $tracker['last_file'] = $fileToProcess;
        $tracker['status']    = "PROCESSING";
        file_put_contents($trackerPath, json_encode($tracker, JSON_PRETTY_PRINT));

        try {
            $this->processExcel($fileToProcess);
            $tracker['status'] = "SUCCESS";
            file_put_contents($trackerPath, json_encode($tracker, JSON_PRETTY_PRINT));
            Storage::delete($fileToProcess);

            return "Processed: {$fileToProcess}";
        } catch (\Exception $e) {
            $tracker['status'] = "FAILED: " . $e->getMessage();
            file_put_contents($trackerPath, json_encode($tracker, JSON_PRETTY_PRINT));
            $this->logFileEvent($fileToProcess, 'PROCESS_ERROR_TRACKER', $e->getMessage());
            Storage::delete($fileToProcess);

            return "FAILED: " . $e->getMessage();
        }
    }
}
