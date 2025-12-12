<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KassMigrationController extends Controller
{
    private static $didTruncate = false;

    public function __construct()
    {


        // Allow long-running migrations (30 minutes)
        ini_set('max_execution_time', '1800');     // Script run time
        ini_set('max_input_time', '1800');         // Upload parsing time
        ini_set('request_terminate_timeout', '1800'); // FPM/Apache timeout if supported

        // Increase memory
        ini_set('memory_limit', '2048M');

        // Increase upload limits (Excel, ZIP, CSV, etc.)
        ini_set('upload_max_filesize', '500M');
        ini_set('post_max_size', '500M');

        // Avoid partial uploads
        ini_set('max_file_uploads', '50');

        // Increase socket timeout (CSV/Excel reads)
        ini_set('default_socket_timeout', '1800');
    }



    /*===========================================================
     |
     |   MAIN SCREEN
     |
     ===========================================================*/
    public function index()
    {
        $files = collect(Storage::files('kass_uploads'))
            ->sortByDesc(fn($f) => Storage::lastModified($f))
            ->take(20)
            ->map(fn($f) => [
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
    
    // ============================================================
    // 2️⃣ FIND FILES IN UPLOADS FOLDER
    // ============================================================
    $files = array_values(array_filter(Storage::files('kass_uploads'), function ($file) {
        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['xls', 'xlsx']);
    }));

    $remaining = count($files);

    // ============================================================
    // 3️⃣ NO FILES LEFT
    // ============================================================
    if ($remaining === 0) {
        return view('kass.auto', [
            'message'   => "🎉 All files processed! No remaining files.",
            'remaining' => 0,
            'next'      => false,
        ]);
    }

    // ============================================================
    // 4️⃣ ALWAYS PROCESS JUST ONE FILE
    // ============================================================
    $file = $files[0];

    if (!Storage::exists($file)) {
        return view('kass.auto', [
            'message'   => "⚠️ Skipped missing file: {$file}",
            'remaining' => $remaining - 1,
            'next'      => true,
        ]);
    }

    // ============================================================
    // 5️⃣ PROCESS FILE
    // ============================================================
    try {
        $this->processExcel($file);

        // Delete after success
        Storage::delete($file);

        $this->logFileEvent(
            $file,
            'FILE_PROCESSED',
            'File imported and deleted from kass_uploads.'
        );

        return view('kass.auto', [
            'message'   => "✅ Processed: {$file}",
            'remaining' => $remaining - 1,
            'next'      => true,
        ]);

    } catch (\Throwable $e) {

        // Log failure and remove bad file
        $this->logFileEvent($file, 'PROCESS_ERROR', $e->getMessage());
        Storage::delete($file);

        return view('kass.auto', [
            'message'   => "❌ Failed on {$file}: " . $e->getMessage(),
            'remaining' => $remaining - 1,
            'next'      => true,
        ]);
    }
}



//    public function processAll()
// {
//     try {

//         // ===============================
//         // STEP 1: LIST FILES
//         // ===============================
//         $files = array_values(array_filter(
//             Storage::files('kass_uploads'),
//             fn($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['xls', 'xlsx'])
//         ));

//         if (empty($files)) {
//             dd('✅ NO FILES FOUND IN kass_uploads');
//         }

//         $file     = $files[0];
//         $fullPath = storage_path("app/{$file}");

//         // ===============================
//         // STEP 2: FILE EXISTS?
//         // ===============================
//         if (!file_exists($fullPath)) {
//             dd('❌ FILE NOT FOUND AT PHP LEVEL', $fullPath);
//         }

//         // ===============================
//         // STEP 3: LOAD EXCEL
//         // ===============================
//         try {
//             $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
//         } catch (\Throwable $e) {
//             dd(
//                 '🔥 PHPSPREADSHEET LOAD FAILED',
//                 $e->getMessage(),
//                 $e->getFile(),
//                 $e->getLine()
//             );
//         }

//         // ===============================
//         // STEP 4: MAP SHEETS
//         // ===============================
//         $sheets = [];
//         foreach ($spreadsheet->getAllSheets() as $sheet) {
//             $sheets[strtoupper(trim($sheet->getTitle()))] = $sheet;
//         }

//         // Just for context (does NOT stop execution)
//         // If you want to see them once, uncomment:
//         // dd('✅ SHEETS FOUND', array_keys($sheets));

//         // ===============================
//         // STEP 5: FIND SHARE / SAVINGS SHEET
//         // ===============================
//         try {
//             $share = $this->findSharempaSheet($sheets);
//         } catch (\Throwable $e) {
//             dd(
//                 '🔥 CRASH INSIDE findSharempaSheet()',
//                 $e->getMessage(),
//                 $e->getFile(),
//                 $e->getLine()
//             );
//         }

//         if (!$share) {
//             dd('❌ NO SHARE / SAVINGS SHEET FOUND', array_keys($sheets));
//         }

//         // ===============================
//         // STEP 6: FIND LOAN SHEET
//         // ===============================
//         $loanSheet = null;
//         $loanMatch = null;

//         // STRICT: LOANMPA
//         if (isset($sheets['LOANMPA'])) {
//             $loanSheet = $sheets['LOANMPA'];
//             $loanMatch = 'STRICT: LOANMPA';
//         }

//         // FALLBACK: any title containing LOAN
//         if (!$loanSheet) {
//             foreach ($sheets as $title => $sheetObj) {
//                 if (str_contains(strtoupper($title), 'LOAN')) {
//                     $loanSheet = $sheetObj;
//                     $loanMatch = "CONTAINS: {$title}";
//                     break;
//                 }
//             }
//         }

//         if (!$loanSheet) {
//             // Not fatal for debugging, but let’s see it clearly
//             dd('❌ NO LOAN SHEET FOUND', array_keys($sheets));
//         }

//         // ===============================
//         // STEP 7: TEST CONTRIBUTIONS PROCESS
//         // ===============================
//         try {
//             $this->processContributionSheet($share['sheet'], $file);
//         } catch (\Throwable $e) {
//             dd(
//                 '🔥 CRASH INSIDE processContributionSheet()',
//                 $e->getMessage(),
//                 $e->getFile(),
//                 $e->getLine()
//             );
//         }

//         // ===============================
//         // STEP 8: TEST LOANS PROCESS
//         // ===============================
//         try {
//             $this->processLoanSheet($loanSheet, $file);
//         } catch (\Throwable $e) {
//             dd(
//                 '🔥 CRASH INSIDE processLoanSheet()',
//                 $e->getMessage(),
//                 $e->getFile(),
//                 $e->getLine()
//             );
//         }

//         // ===============================
//         // STEP 9: IF WE REACH HERE, ALL GOOD
//         // ===============================
//         dd('✅ FULL FILE PROCESSED SUCCESSFULLY', $file);

//     } catch (\Throwable $e) {
//         dd(
//             '🔥 OUTER FATAL ERROR',
//             $e->getMessage(),
//             $e->getFile(),
//             $e->getLine()
//         );
//     }
// }



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
        $share = $this->findSharempaSheet($sheets);

        if ($share) {
            $this->logFileEvent($filePath, "SHAREMPA_MATCH", "Matched using: " . $share['match']);
            $this->processContributionSheet($share['sheet'], $filePath);
        } else {
            $this->logFileEvent($filePath, "MISSING_SHAREMPA", "No sheet resembling SHAREMPA was found.");
        }


        // ✅ SMART LOAN SHEET DETECTION (LOANMPA → fallback to any tab containing LOAN)
$loanSheet = null;
$loanMatch = null;

// 1️⃣ Strict priority: LOANMPA
if (isset($sheets['LOANMPA'])) {
    $loanSheet = $sheets['LOANMPA'];
    $loanMatch = 'STRICT: LOANMPA';
}

// 2️⃣ Fallback: any tab that CONTAINS the word "LOAN"
if (!$loanSheet) {
    foreach ($sheets as $title => $sheetObj) {
        if (str_contains(strtoupper($title), 'LOAN')) {
            $loanSheet = $sheetObj;
            $loanMatch = "CONTAINS: $title";
            break; // ✅ take first safe match only
        }
    }
}

// 3️⃣ Final routing
if ($loanSheet) {
    $this->logFileEvent($filePath, "LOAN_MATCH", "Matched using: {$loanMatch}");
    $this->processLoanSheet($loanSheet, $filePath);
} else {
    $this->logFileEvent($filePath, "MISSING_LOAN_SHEET", "No sheet containing LOAN was found.");
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
    $highestRow = $sheet->getHighestDataRow();
    $highestCol = $sheet->getHighestDataColumn();

    $rows = $sheet->rangeToArray("A1:{$highestCol}{$highestRow}", null, true, true, true);

    // Convert rows to 0-based index arrays
    $clean = array_map(fn($r) => array_values($r), $rows);

    // ---------------------------------------------------------
    // 1) Detect header row (row containing NAME)
    // ---------------------------------------------------------
    [$headerRaw, $start] = $this->extractHeader($clean);
    $headerNorm          = $this->normalizeHeaders($headerRaw);

    if (!in_array('month', $headerNorm)) {
        $this->logFileEvent($sourceFile, 'INVALID_HEADER', 'MONTH column missing.');
    }

    if (!in_array('year', $headerNorm)) {
        $this->logFileEvent($sourceFile, 'INVALID_HEADER', 'YEAR column missing.');
    }

    // ---------------------------------------------------------
    // 2) Locate MONTH and RUN BAL columns
    // ---------------------------------------------------------
    $monthIndex  = $this->getMonthIndex($headerNorm);
    $runBalInfo  = $this->getRunBalIndexAndKey($headerNorm);
    $runBalIndex = $runBalInfo['index'];

    // ---------------------------------------------------------
    // 3) Transaction columns = strictly between MONTH and RUN BAL
    // ---------------------------------------------------------
    $transactionIndexes = $this->getTransactionColumnIndexes($headerNorm, $monthIndex, $runBalIndex);

    // ---------------------------------------------------------
    // 4) Carry-down fields
    // ---------------------------------------------------------
    $carryName = null;
    $carryAdm  = null;
    $carryComp = null;

    // Track first-January rows per MEMBER + COMPANY + YEAR
    $this->seenFirstJanuary = $this->seenFirstJanuary ?? [];

    // ---------------------------------------------------------
    // 5) Process each data row
    // ---------------------------------------------------------
    for ($i = $start; $i < count($clean); $i++) {

        $row = $clean[$i];

        if (!$this->rowHasValues($row)) {
            continue;
        }

        // Map row by normalized header keys
        $mapped = $this->mapRow($headerNorm, $row);

        // Skip accidental duplicated header rows inside data
        if ($this->looksLikeHeaderRow($mapped)) {
            $this->logIssue($sourceFile, 'HEADER_ROW_IN_DATA', $mapped);
            continue;
        }

        // -----------------------------------------
        // Carry NAME, ADM NO, COMPANY downward
        // -----------------------------------------
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

        // Skip row if identification is still missing
        if (empty($mapped['name']) || empty($mapped['adm_no'])) {
            $this->logIssue($sourceFile, 'ANOMALY_MISSING_KEY_FIELDS', [
                'row'          => $mapped,
                '_carry_name'  => $carryName,
                '_carry_adm'   => $carryAdm,
                '_carry_comp'  => $carryComp
            ]);
            continue;
        }

        // -----------------------------------------
        // Clean year / month
        // -----------------------------------------
        $year  = $this->cleanYear($mapped['year']  ?? null, $sourceFile, $mapped);
        $month = $this->cleanMonth($mapped['month'] ?? null, $sourceFile, $mapped);

        // -----------------------------------------
        // Skip any row missing a valid YEAR or MONTH
        // -----------------------------------------
        if ($year === null || $month === null) {
            $this->logIssue($sourceFile, 'SKIPPED_INVALID_PERIOD', $mapped);
            continue;
        }

        // -----------------------------------------
        // FIRST-JANUARY SKIP RULE  
        // Skip the FIRST January for each MEMBER+COMPANY+YEAR
        // -----------------------------------------
        if ($month === 'JAN') {

            $memberYearKey = $mapped['adm_no'] . "|" . $mapped['comp'] . "|" . $year;

            if (!isset($this->seenFirstJanuary[$memberYearKey])) {

                // mark as seen
                $this->seenFirstJanuary[$memberYearKey] = true;

                // skip this first JAN row
                continue;
            }
        }

        // ---------------------------------------------------------
        // 6) Extract real transaction columns
        // ---------------------------------------------------------
        foreach ($transactionIndexes as $colIndex) {

            if (!array_key_exists($colIndex, $row) || !array_key_exists($colIndex, $headerRaw)) {
                continue;
            }

            $rawHeaderLabel = trim((string) $headerRaw[$colIndex]);
            $rawCellValue   = $row[$colIndex];

            $amount = $this->num($rawCellValue);

            // Skip empty or zero or invalid numeric
            if ($amount === null || $amount == 0) {
                continue;
            }

            // Choose transaction label
            $normalizedKey = $headerNorm[$colIndex] ?? 'unknown';
            $rawType       = $rawHeaderLabel !== '' 
                                ? strtoupper($rawHeaderLabel)
                                : strtoupper($normalizedKey);

            // ---------------------------------------------------------
            // INSERT REAL TRANSACTION ROW
            // ---------------------------------------------------------
            try {
                DB::table('kass_staging_contributions')->insert([
                    'raw_name'          => $mapped['name'],
                    'member_identifier' => $mapped['pfno'] ?? null,
                    'adm_no'            => $mapped['adm_no'],
                    'company'           => $mapped['comp'],
                    'year'              => $year,
                    'month'             => $month,
                    'raw_type'          => $rawType,
                    'amount'            => $amount,
                    'source_file'       => $sourceFile,
                    'raw_row_json'      => json_encode($mapped),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            } catch (\Exception $e) {
                $this->logIssue($sourceFile, 'DB_INSERT_ERROR_TRANSACTION', [
                    'error'     => $e->getMessage(),
                    'row_index' => $i,
                    '_col'      => $colIndex,
                    '_raw_type' => $rawType,
                    '_amount'   => $amount
                ]);
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
        $highestRow = $sheet->getHighestDataRow();
$highestCol = $sheet->getHighestDataColumn();

$rows = $sheet
    ->rangeToArray("A1:{$highestCol}{$highestRow}", null, true, true, true);

$clean = array_map(fn($r) => array_values($r), $rows);

        

        // 1) Find header row with NAME and the label row above it
        $headerRow   = null;
        $labelRow    = null;
        $start       = 0;

        foreach ($clean as $i => $row) {

            if (!$this->rowHasValues($row)) {
                continue;
            }

            $trimmed = array_map(function ($v) {
                return trim(str_replace("\xC2\xA0", ' ', (string) $v));
            }, $row);

            $upper = array_map('strtoupper', $trimmed);

            if (in_array('NAME', $upper)) {
                $headerRow = $trimmed;
                $start     = $i + 1;
                $labelRow  = $i > 0 ? $clean[$i - 1] : null;
                break;
            }
        }

        if ($headerRow === null) {
            $this->logFileEvent($sourceFile, 'LOAN_HEADER_NOT_FOUND', 'Could not find header row with NAME.');
            return;
        }

        // 2) Base columns: NAME / ADM NO / COMP
        $upper = array_map('strtoupper', $headerRow);

        $idxName = array_search('NAME', $upper);
        $idxAdm  = array_search('ADM NO', $upper);
        if ($idxAdm === false) {
            $idxAdm = array_search('ADMNO', $upper);
        }
        $idxComp = array_search('COMP', $upper);
        if ($idxComp === false) {
            $idxComp = array_search('COMPANY', $upper);
        }

        if ($idxName === false || $idxAdm === false || $idxComp === false) {
            $this->logFileEvent($sourceFile, 'LOAN_HEADER_MISSING_KEYS', 'Missing NAME / ADM NO / COMP.');
            return;
        }

        // 3) Detect loan blocks and their labels (NORMAL, NORMAL A, EMERGENCY, ...)
        $loanBlocks = $this->findLoanBlocks($headerRow, $labelRow);

        if (empty($loanBlocks)) {
            $this->logFileEvent($sourceFile, 'NO_LOAN_BLOCKS_FOUND', 'No YEAR/MONTH/DR/INT/PERIOD blocks found.');
            return;
        }

        // 4) Carry-down state
        $carryName = null;
        $carryAdm  = null;
        $carryComp = null;

        // Per-member + loan-type metadata (PERIOD carried down)
        // Key = adm_no|company, then [loan_label] => period
        $currentMemberKey = null;
        $periodCarry      = [];

        for ($i = $start; $i < count($clean); $i++) {

            $row = $clean[$i];
            if (!$this->rowHasValues($row)) {
                continue;
            }

            // ----- Carry NAME / ADM / COMP -----
            $name = $row[$idxName] ?? null;
            if (trim((string) $name) !== '') {
                $carryName = $name;
            } else {
                $name = $carryName;
            }

            $adm = $row[$idxAdm] ?? null;
            if (trim((string) $adm) !== '') {
                $carryAdm = $adm;
            } else {
                $adm = $carryAdm;
            }

            $comp = $row[$idxComp] ?? null;
            if (trim((string) $comp) !== '') {
                $carryComp = $comp;
            } else {
                $comp = $carryComp;
            }

            if (empty($name) || empty($adm)) {
                $this->logIssue($sourceFile, 'MISSING_NAME_OR_ADM_AFTER_CARRY_LOAN', [
                    'row_index' => $i,
                    'row'       => $row,
                ]);
                continue;
            }

            $memberKey = trim((string) $adm) . '|' . trim((string) $comp);
            if ($memberKey === '|') {
    $this->logIssue($sourceFile, 'INVALID_MEMBER_KEY_LOAN', [
        'row_index' => $i,
        'adm'       => $adm,
        'company'   => $comp,
    ]);
    continue;
}


            if ($memberKey !== $currentMemberKey) {
                $currentMemberKey      = $memberKey;
                $periodCarry[$memberKey] = [];
            }

            // ---------- For each LOAN BLOCK (NORMAL, NORMAL A, EMERGENCY, ...) ----------
            foreach ($loanBlocks as $block) {

                
                $label = $block['label']; // e.g. NORMAL / NORMAL A / EMERGENCY
                $cols  = $block['cols'];

                // ✅ HARD SAFETY CHECK — prevent undefined index crashes
if (!isset($cols['year'], $cols['month'])) {
    $this->logIssue($sourceFile, 'LOAN_BLOCK_MISSING_YEAR_OR_MONTH', [
        'loan_label' => $label,
        'cols'       => $cols,
    ]);
    continue;
}



                // YEAR & MONTH for this block / this row
                $rawYear  = $row[$cols['year']]  ?? null;
                $rawMonth = $row[$cols['month']] ?? null;

                $yearStr  = trim((string) $rawYear);
                $monthStr = trim((string) $rawMonth);

                // No year+month → no record for this loan on this row (summary rows, blank months)
                if ($yearStr === '' && $monthStr === '') {
                    continue;
                }

                // Clean year/month
                $year  = $this->cleanYear($rawYear, $sourceFile, []);
                $month = $this->cleanMonth($rawMonth, $sourceFile, []);

                // Skip rows where MONTH has value but YEAR is missing
                if ($year === null && $month !== null) {
                    continue;
                }

                // Skip invalid rows entirely
                if ($year === null || $month === null) {
                    continue;
                }


                 

                // DR / CR / INT / PERIOD may or may not exist in this block
                $rawDr = null;
                if ($cols['dr'] !== null && array_key_exists($cols['dr'], $row)) {
                    $rawDr = $row[$cols['dr']];
                }

                $rawCr = null;
                if ($cols['cr'] !== null && array_key_exists($cols['cr'], $row)) {
                    $rawCr = $row[$cols['cr']];
                }

                $rawInt = null;
                if ($cols['int'] !== null && array_key_exists($cols['int'], $row)) {
                    $rawInt = $row[$cols['int']];
                }

                $rawPeriod = null;
                if ($cols['period'] !== null && array_key_exists($cols['period'], $row)) {
                    $rawPeriod = $row[$cols['period']];
                }

                $dr  = $this->num($rawDr);   // can be null
                $cr  = $this->num($rawCr);   // can be null
                $int = $this->num($rawInt);  // can be null

                // ---------------------- PERIOD HANDLING (NEW RULES) ----------------------

// Clean PERIOD value
$cleanPeriod = $this->cleanLoanPeriod($rawPeriod);

// Was there principal payment?
$hasPrincipal = ($cr !== null && $cr > 0);

$previousPeriod = $periodCarry[$memberKey][$label] ?? null;

// NEW RULE:
// 1) If PERIOD is a valid numeric → update and treat as new cycle if previous null
// 2) If PERIOD invalid but CR>0 → carry previousPeriod
// 3) If PERIOD invalid and CR==0 → leave period null
if ($cleanPeriod !== null) {

    // Update
    $periodCarry[$memberKey][$label] = $cleanPeriod;
    $periodVal = $cleanPeriod;

} else {

    if ($hasPrincipal) {
        // Carry previous period
        $periodVal = $previousPeriod;
    } else {
        // No principal → no processing this month → period stays null
        $periodVal = null;
    }
}

 // ============================================================
// SKIP ALL ZERO / BLANK LOAN ROWS
// ============================================================

$isEmptyDr  = ($dr === null || abs($dr) < 0.00001);
$isEmptyCr  = ($cr === null || abs($cr) < 0.00001);
$isEmptyInt = ($int === null || abs($int) < 0.00001);

$hasRealActivity = (!$isEmptyCr || !$isEmptyInt);

if (!$hasRealActivity) {
    continue; // Completely skip this block/month
}


                // --------- INSERT ROW (even if DR/CR/INT are null) ---------
                try {
                    DB::table('kass_staging_loans')->insert([
                        'raw_name'            => $name,
                        'member_identifier'   => null, // no PFNO in this LOANS sheet
                        'adm_no'              => $adm,
                        'company'             => $comp,
                        'loan_type'           => $label,        // EXACT loan name: NORMAL, NORMAL A, EMERGENCY, ...
                        'year'                => $year,
                        'month'               => $month,
                        'outstanding_balance' => $dr,   // DR = loan balance
                        'principal_paid'      => $cr,   // CR = monthly principal
                        'interest_paid'       => $int,  // INT = interest for that month

                        'period_index'        => $periodVal,
                        'source_file'         => $sourceFile,
                        'raw_row_json'        => json_encode([
                            'loan_label' => $label,
                            'raw_year'   => $rawYear,
                            'raw_month'  => $rawMonth,
                            'raw_dr'     => $rawDr,
                            'raw_cr'     => $rawCr,
                            'raw_int'    => $rawInt,
                            'raw_period' => $rawPeriod,
                        ]),
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                } catch (\Exception $e) {
                    $this->logIssue(
                        $sourceFile,
                        'DB_INSERT_ERROR_LOAN_BLOCK: ' . $e->getMessage(),
                        [
                            'row_index' => $i,
                            'loan_type' => $label,
                            'adm_no'    => $adm,
                            'company'   => $comp,
                        ]
                    );
                }
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

            // Clean header row values
            $trimmed = array_map(function ($v) {
                return trim(str_replace("\xC2\xA0", ' ', (string)$v));
            }, $row);

            // Detect row containing NAME
            $upper = array_map('strtoupper', $trimmed);
            if (!in_array('NAME', $upper)) {
                continue;
            }

            // 🔥 IMPORTANT: DO NOT REMOVE ANY COLUMNS
            return [$trimmed, $i + 1];
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

        foreach ($row as $i => $h) {

            $clean = trim(str_replace("\xC2\xA0", ' ', (string)$h));

            if ($clean === '') {
                // keep placeholder key so indexes never shift
                $norm[] = "_col{$i}";
                continue;
            }

            $clean = strtolower(str_replace([' ', '/', '.', '-', "\t"], '_', $clean));

            $norm[] = $clean;
        }

        return $norm;
    }



    private function findSharempaSheet(array $sheets)
{
    // =========================================================
    // 1️⃣ PHASE ONE — YOUR ORIGINAL ROBUST SHAREMPA LOGIC
    // =========================================================
    $candidates = [];

    foreach ($sheets as $title => $sheetObj) {

        $clean   = strtoupper(trim(str_replace("\xC2\xA0", ' ', $title)));
        $noSpace = str_replace(' ', '', $clean);

        // STRICT MATCH
        if ($clean === 'SHAREMPA' || $noSpace === 'SHAREMPA') {
            return [
                'sheet' => $sheetObj,
                'match' => "STRICT: $title"
            ];
        }

        // VARIANTS
        $variant = str_replace([' ', '_', '-', '.'], '', $clean);
        if ($variant === 'SHAREMPA') {
            $candidates["VARIANT:$title"] = $sheetObj;
        }

        // PREFIX / SUFFIX
        if (str_starts_with($clean, 'SHAREMPA') || str_ends_with($clean, 'SHAREMPA')) {
            $candidates["PREFIX_SUFFIX:$title"] = $sheetObj;
        }

        // CONTAINS BOTH WORDS IN CORRECT ORDER
        if (str_contains($clean, 'SHARE') && str_contains($clean, 'MPA')) {

            $posShare = strpos($clean, 'SHARE');
            $posMpa   = strpos($clean, 'MPA');

            if ($posShare !== false && $posMpa !== false && $posShare < $posMpa) {
                $candidates["CONTAINS:$title"] = $sheetObj;
            }
        }
    }

    if (!empty($candidates)) {

        $best = null;
        $bestDist = 999;

        foreach ($candidates as $label => $sheetObj) {

            $dist = levenshtein(
                str_replace(['VARIANT:', 'PREFIX_SUFFIX:', 'CONTAINS:'], '', $label),
                'SHAREMPA'
            );

            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = [
                    'sheet' => $sheetObj,
                    'match' => "LEVENSHTEIN:$label"
                ];
            }
        }

        return $best;
    }

    // =========================================================
    // 2️⃣ PHASE TWO — FALLBACK TO SHARES
    // =========================================================
    foreach ($sheets as $title => $sheetObj) {

        $clean = strtoupper(trim(str_replace("\xC2\xA0", ' ', $title)));

        if (
            $clean === 'SHARES' ||
            str_starts_with($clean, 'SHARES') ||
            str_contains($clean, 'SHARES')
        ) {
            return [
                'sheet' => $sheetObj,
                'match' => "FALLBACK_SHARES: $title"
            ];
        }
    }

    // =========================================================
    // 3️⃣ PHASE THREE — LAST RESORT SAVINGS
    // =========================================================
    foreach ($sheets as $title => $sheetObj) {

        $clean = strtoupper(trim(str_replace("\xC2\xA0", ' ', $title)));

        if (
            $clean === 'SAVINGS' ||
            str_starts_with($clean, 'SAVINGS') ||
            str_contains($clean, 'SAVINGS')
        ) {
            return [
                'sheet' => $sheetObj,
                'match' => "FALLBACK_SAVINGS: $title"
            ];
        }
    }

    // =========================================================
    // ❌ NOTHING MATCHED
    // =========================================================
    return null;
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
            'name',
            'adm_no',
            'comp',
            'company',
            'year',
            'month',
            'memb',
            'dr',
            'cr',
            'run_bal',
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

    // ✅ REMOVE commas before numeric check
    $clean = str_replace(',', '', $value);

    if (is_numeric($clean)) {
        return (int) $clean;
    }

    $this->logIssue($file, 'INVALID_YEAR_VALUE', array_merge($row, [
        '_year_raw' => $value
    ]));

    return null;
}


    /* Month cleaner – uppercase string, logs weird ones (but still stores) */
    private function cleanMonth($value, string $file, array $row)
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $month = strtoupper($value);

        // Correct Kass MPA month format
        $valid = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        // Accept JUNE → JUN, JULY → JUL
        if ($month === 'JUNE') $month = 'JUN';
        if ($month === 'JULY') $month = 'JUL';

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

    /**
     * Detect repeating loan blocks inside LOANMPA header row.
     * Each block must match:
     *   YEAR | MONTH | DR | CR | INT | PERIOD | MONTHLY
     */
    /**
     * Detect loan blocks inside LOANMPA.
     * Handles REAL structure of your files:
     *
     *   NORMAL
     *   YEAR | MONTH | DR | CR | INT | PERIOD
     *
     * Loan label ALWAYS comes from row above header row.
     */
    private function findLoanBlocks(array $headerRow, ?array $labelRow = null): array
    {
        // Clean header row
        $clean = [];
        foreach ($headerRow as $v) {
            $clean[] = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $v)));
        }

        $blocks = [];
        $max = count($clean);
        $loanNo = 0;

        for ($i = 0; $i < $max; $i++) {

            // Look for YEAR
            if ($clean[$i] !== 'YEAR') {
                continue;
            }

            // COLUMN 1 → MONTH
            if (!isset($clean[$i + 1]) || $clean[$i + 1] !== 'MONTH') {
                continue;
            }

            // DR, CR, INT, PERIOD must follow (MONTHLY NOT REQUIRED)
            $cols = [
                'year'   => $i,
                'month'  => $i + 1,
                'dr'     => null,
                'cr'     => null,
                'int'    => null,
                'period' => null,
            ];

            // Scan next 6 columns for DR/CR/INT/PERIOD
            for ($j = $i + 2; $j <= $i + 7 && $j < $max; $j++) {

                $h = $clean[$j];

                if ($h === 'YEAR') {
                    break;
                }

                if ($h === 'DR')     $cols['dr']     = $j;
                if ($h === 'CR')     $cols['cr']     = $j;
                if ($h === 'INT')    $cols['int']    = $j;
                if ($h === 'PERIOD') $cols['period'] = $j;
            }

            // If DR/CR/INT/PERIOD missing, skip (this was a payroll block)
            if ($cols['dr'] === null && $cols['cr'] === null && $cols['int'] === null) {
                continue;
            }

            // Detect loan label from row ABOVE header
            $label = 'LOAN_' . ($loanNo + 1);

            if ($labelRow !== null) {
                $raw = $labelRow[$i] ?? '';
                if (trim($raw) === '') {
                    $raw = $labelRow[$i + 1] ?? '';
                }

                if (trim($raw) !== '') {
                    $label = strtoupper(trim($raw));
                }
            }

            $loanNo++;

            $blocks[] = [
                'loan_no' => $loanNo,
                'label'   => $label,
                'cols'    => $cols,
            ];
        }

        return $blocks;
    }
private function cleanLoanPeriod($value)
{
    if ($value === null) {
        return null;
    }

    // Convert to string and trim
    $str = trim((string)$value);
 
    // Remove commas and spaces
    $str = str_replace([',', ' '], '', $str);

    if ($str === '') {
        return null;
    }

    // STRICT: must be digits only (0–9)
    if (!ctype_digit($str)) {
        return null; // ignore names, narratives, weird text
    }

    return (int)$str;
}

}
