<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

class KassMigrationController extends Controller
{
    private static $didTruncate = false;
    // Tracks whether we have ever seen this member before (across all processed files)
    // Key format: ADM|COMP or NAME|COMP fallback
    private array $seenMembersGlobal = [];

    // Tracks whether we have skipped the first JAN for a member (only when member is already known)
    private array $skippedFirstJanForKnownMember = [];

    private array $seenFirstJanuary = [];

    // Opening balance snapshots (per member|company|year)
    private array $openingSnapshots = [];

    // Holds a “maybe-opening” JAN row until we see what follows.
    private array $pendingJanuary = []; // key: memberYearKey => ['mapped'=>..., 'headerNorm'=>..., 'headerRaw'=>..., 'row'=>..., 'transactionIndexes'=>..., 'sourceFile'=>...]



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



    private function resolveLoanName(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
    int $yearCol,
    int $headerRow
): string {

    // Scan rows ABOVE the YEAR header (header band)
    $startRow = 1;
    $endRow   = $headerRow - 1;

    if ($endRow < $startRow) {
        throw new \RuntimeException("No header band available to resolve loan name.");
    }

    // Scan ONLY columns belonging to this loan block
    // (YEAR → PERIOD, max +6 columns for safety)
    $startCol = $yearCol;
    $endCol   = $yearCol + 6;

    for ($row = $startRow; $row <= $endRow; $row++) {
        for ($col = $startCol; $col <= $endCol; $col++) {

            $cell = $sheet->getCellByColumnAndRow($col, $row);
            $val  = trim((string) $cell->getValue());

            if ($val === '') {
                continue;
            }

            // Reject numeric / dates
            if (is_numeric($val)) {
                continue;
            }

            $upper = strtoupper($val);

            // Reject known headers & noise
            if (in_array($upper, [
                'YEAR','MONTH','DR','CR','INT','INTEREST','PERIOD',
                'NAME','ADM NO','COMP',
                'PAYROLL','RECOVERIES','OVER/UNDER'
            ])) {
                continue;
            }

            // Reject months
            if (in_array($upper, [
                'JAN','FEB','MAR','APR','MAY','JUN',
                'JUL','AUG','SEP','OCT','NOV','DEC'
            ])) {
                continue;
            }

            // ✅ THIS IS A LOAN NAME
            return preg_replace('/\s+/', ' ', $upper);
        }
    }

    // ❌ HARD FAIL — NO FALLBACK
    throw new \RuntimeException(
        "Loan name could not be resolved above YEAR column {$yearCol}"
    );
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

        $this->pendingJanuary   = [];
        $this->openingSnapshots = [];
        $this->seenFirstJanuary = [];


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

        $headerCarry = [];
        foreach ($headerRaw as $i => $label) {
            $label = trim((string)$label);
            if ($label !== '') {
                $headerCarry[$i] = $label;
            }
        }


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

            // =====================================================
            // 🔁 BACKFILL ADM WHEN IT APPEARS (CRITICAL FIX)
            // =====================================================
            $this->safeBackfillAdmFromName($mapped);



            $memberKey = $this->memberKey($mapped);

            // =====================================================
            // 🔁 REAL-TIME ADM BACKFILL (NAME → ADM)
            // =====================================================



            if (empty($mapped['adm_no']) && !empty($mapped['name'])) {

                $resolvedAdm = $this->resolveAdmFromStaging($mapped);

                if (!empty($resolvedAdm)) {

                    // Update current row in memory
                    $mapped['adm_no'] = $resolvedAdm;

                   $this->safeBackfillAdmFromName($mapped);
                }
            }



            if ($memberKey === null) {
                $this->logIssue($sourceFile, 'ANOMALY_MISSING_MEMBER_KEY', [
                    'row' => $mapped,
                ]);
                continue;
            }


            // // Skip row if identification is still missing
            // if (empty($mapped['name']) || empty($mapped['adm_no'])) {
            //     $this->logIssue($sourceFile, 'ANOMALY_MISSING_KEY_FIELDS', [
            //         'row'          => $mapped,
            //         '_carry_name'  => $carryName,
            //         '_carry_adm'   => $carryAdm,
            //         '_carry_comp'  => $carryComp
            //     ]);
            //     continue;
            // }

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
            // if ($month === 'JAN') {

            //     // $memberYearKey = $mapped['adm_no'] . "|" . $mapped['comp'] . "|" . $year;

            //     $memberYearKey = $memberKey . "|" . $year;

            //     if (!isset($this->seenFirstJanuary[$memberYearKey])) {

            //         // mark as seen
            //         $this->seenFirstJanuary[$memberYearKey] = true;

            //         // skip this first JAN row
            //         continue;
            //     }
            // }

            // -----------------------------------------
            // OPENING JANUARY DETECTION
            // -----------------------------------------
            $memberYearKey = $this->memberYearKey($mapped, $year);

            // -------------------------
            // CONSECUTIVE-JAN LOGIC
            // -------------------------
            if ($month === 'JAN') {

                // If we already have a pending JAN for this member+year,
                // then we have TWO consecutive JANs -> the FIRST is opening.
                if (isset($this->pendingJanuary[$memberYearKey])) {

                    $pending = $this->pendingJanuary[$memberYearKey];

                    // 1) First JAN is opening snapshot (do NOT insert its transactions)
                    $this->captureOpeningSnapshot(
                        $memberYearKey,
                        $pending['mapped'],
                        $pending['headerNorm'],
                        $pending['row'],
                        $year   // ✅ PASS THE YEAR YOU JUST CLEANED
                    );


                    // 2) Clear pending, and continue with current JAN as a normal transactional row
                    unset($this->pendingJanuary[$memberYearKey]);

                    // fall through to normal transaction insert for current row
                } else {

                    // Put this JAN on hold until we see what the next row month is
                    $this->pendingJanuary[$memberYearKey] = [
                        'mapped'             => $mapped,
                        'headerRaw'          => $headerRaw,
                        'headerNorm'         => $headerNorm,
                        'row'                => $row,
                        'transactionIndexes' => $transactionIndexes,
                        'rowIndex'           => $i,
                        'sourceFile'         => $sourceFile,
                        'year'               => $year, // ✅ store resolved year
                    ];


                    // Do not process this row yet
                    continue;
                }
            } else {

                // If a pending JAN exists but the next month is NOT JAN,
                // then that first JAN was NOT opening; it was transactional.
                if (isset($this->pendingJanuary[$memberYearKey])) {

                    $pending = $this->pendingJanuary[$memberYearKey];
                    unset($this->pendingJanuary[$memberYearKey]);

                    // replay the pending JAN as a normal transactional row
                    $pendingYear = (int)($pending['year'] ?? 0);
                    if ($pendingYear > 0) {
                        $this->insertContributionTransactionsForRow(
                            $pending['mapped'],
                            $pendingYear,   // ✅ correct year
                            'JAN',
                            $pending['row'],
                            $pending['headerRaw'],
                            $pending['headerNorm'],
                            $pending['transactionIndexes'],
                            $headerCarry,
                            $sourceFile,
                            $pending['rowIndex']
                        );
                    }
                }
            }


            // ---------------------------------------------------------
            // 6) Extract real transaction columns
            // ---------------------------------------------------------
            foreach ($transactionIndexes as $colIndex) {

                if (!array_key_exists($colIndex, $row) || !array_key_exists($colIndex, $headerRaw)) {
                    continue;
                }

                $rawHeaderLabel = trim((string)($headerRaw[$colIndex] ?? ''));

                if ($rawHeaderLabel !== '') {
                    // Update carry
                    $headerCarry[$colIndex] = $rawHeaderLabel;
                } else {
                    // Use carried header if available
                    $rawHeaderLabel = $headerCarry[$colIndex] ?? '';
                }

                $rawCellValue   = $row[$colIndex];

                $amount = $this->num($rawCellValue);

                // Skip empty or zero or invalid numeric
                if ($amount === null || $amount == 0) {
                    continue;
                }

                // Choose transaction label
                $rawType = $this->resolveRawType(
                    $rawHeaderLabel,
                    $headerNorm[$colIndex] ?? null
                );


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

        // Flush any pending JANs (they were transactional, not opening)
        foreach ($this->pendingJanuary as $memberYearKey => $pending) {

            $mapped = $pending['mapped'];

            $year = (int)($pending['year'] ?? 0);
            if ($year <= 0) {
                continue;
            }


            $this->insertContributionTransactionsForRow(
                $mapped,
                $year,
                'JAN',
                $pending['row'],
                $pending['headerRaw'],
                $pending['headerNorm'],
                $pending['transactionIndexes'],
                $headerCarry,
                $sourceFile,
                $pending['rowIndex']
            );

            unset($this->pendingJanuary[$memberYearKey]);
        }



        if (!empty($this->openingSnapshots)) {
            $this->reconcileOpeningBalances($sourceFile);
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
        $lastLoanState = [];

        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        $rows  = $sheet->rangeToArray("A1:{$highestCol}{$highestRow}", null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        /* =========================================================
     * 1) Locate header row
     * ========================================================= */
        $headerRow = null;
        $labelRow  = null;
        $start     = 0;

        foreach ($clean as $i => $row) {
            if (!$this->rowHasValues($row)) continue;

            $upper = array_map(
                fn($v) => strtoupper(trim((string)$v)),
                $row
            );

            if (in_array('NAME', $upper)) {
                $headerRow = $row;
                $labelRow  = $i > 0 ? $clean[$i - 1] : null;
                $start     = $i + 1;
                break;
            }
        }

        if (!$headerRow) {
            $this->logFileEvent($sourceFile, 'LOAN_HEADER_NOT_FOUND', 'NAME header missing');
            return;
        }

        /* =========================================================
     * 2) Resolve base columns
     * ========================================================= */
        $upper = array_map(fn($v) => strtoupper(trim((string)$v)), $headerRow);

        $idxName = array_search('NAME', $upper);
        $idxAdm  = array_search('ADM NO', $upper);
        if ($idxAdm === false) $idxAdm = array_search('ADMNO', $upper);
        $idxComp = array_search('COMP', $upper);
        if ($idxComp === false) $idxComp = array_search('COMPANY', $upper);

        if ($idxName === false || $idxAdm === false || $idxComp === false) {
            $this->logFileEvent($sourceFile, 'LOAN_HEADER_KEYS_MISSING', 'NAME / ADM / COMP missing');
            return;
        }

        /* =========================================================
     * 3) Detect loan blocks
     * ========================================================= */
        $loanBlocks = $this->findLoanBlocks($headerRow, $labelRow);
        if (empty($loanBlocks)) return;

        $carryName = $carryAdm = $carryComp = null;

        /* =========================================================
     * 4) Iterate rows
     * ========================================================= */
        for ($i = $start; $i < count($clean); $i++) {

            $row = $clean[$i];
            if (!$this->rowHasValues($row)) continue;

            // Carry identity
            if (trim((string)$row[$idxName]) !== '') $carryName = $row[$idxName];
            if (trim((string)$row[$idxAdm])  !== '') $carryAdm  = $row[$idxAdm];
            if (trim((string)$row[$idxComp]) !== '') $carryComp = $row[$idxComp];

            if (!$carryName || !$carryComp) continue;

            $memberKey = $this->memberKey([
                'name' => $carryName,
                'adm_no' => $carryAdm,
                'comp' => $carryComp,
            ]);

            if (!$memberKey) continue;

            foreach ($loanBlocks as $block) {

                $label = $block['label'];
                $cols  = $block['cols'];

                $rawYear  = $row[$cols['year']]  ?? null;
                $rawMonth = $row[$cols['month']] ?? null;

                if (trim((string)$rawYear) === '' && trim((string)$rawMonth) === '') continue;

                $year  = $this->cleanYear($rawYear, $sourceFile, []);
                $month = $this->cleanMonth($rawMonth, $sourceFile, []);
                if ($year === null || $month === null) continue;

                // -------------------------------------------------
                // Build loanKey + previous state FIRST (once per block)
                // -------------------------------------------------
                $loanKey = $memberKey . '|' . $label;

                $prev = $lastLoanState[$loanKey] ?? [
                    'balance'   => null,
                    'principal' => null,
                    'zero_lock' => false,   // <-- only lock after DERIVED_ZERO
                ];

                // Period index (needed by zero-check logic)
                $periodVal = isset($cols['period'])
                    ? $this->validPeriodIndex($row[$cols['period']] ?? null)
                    : null;

                // -------------------------------------------------
                // DR extraction: use displayed value first.
                // Only derive when displayed DR is BLANK (null) AND formula qualifies AND not locked.

                // -------------------------------------------------
                $dr       = null;
                $drSource = 'RAW';
                $hasFormula = false;

                if (isset($cols['dr'])) {

                    $drCell = $sheet->getCellByColumnAndRow($cols['dr'] + 1, $i + 1);

                    $rawDisplayed = $drCell->getFormattedValue();
                    $dr = $this->num($rawDisplayed);

                    // We only consider formula-based derivation if not locked by a prior derived-zero.
                    if (!$prev['zero_lock'] && $drCell->isFormula()) {

                        // Your strict detector (optional); you can relax to just isFormula() if needed
                        $hasFormula = $this->formulaIsSimpleArithmetic($drCell);

                        // Only derive when displayed DR is BLANK (null) AND formula qualifies AND not locked.

                        if (
                            $hasFormula &&
                            $dr === null &&
                            $prev['balance'] !== null &&
                            $prev['principal'] !== null
                        ) {
                            $dr = $prev['balance'] - $prev['principal'];
                            $drSource = 'DERIVED';
                        }
                    }
                }

                // -------------------------------------------------
                // CR + INT (use formatted value for consistency)
                // -------------------------------------------------
                $cr = null;
                if (isset($cols['cr'])) {
                    $crCell = $sheet->getCellByColumnAndRow($cols['cr'] + 1, $i + 1);
                    $cr = $this->num($crCell->getFormattedValue());
                }

                $int = null;
                if (isset($cols['int'])) {
                    $intCell = $sheet->getCellByColumnAndRow($cols['int'] + 1, $i + 1);
                    $int = $this->num($intCell->getFormattedValue());
                }

                // -------------------------------------------------
                // DERIVED ZERO CHECKPOINT (only if formula + abrupt zero condition)
                // IMPORTANT: Only lock after inserting DERIVED_ZERO.
                // -------------------------------------------------
                if (
                    $hasFormula &&
                    $this->isAbruptZeroLoanCheckpoint(
                        $prev['balance'],
                        $prev['principal'],
                        $dr,
                        $periodVal
                    )
                ) {
                    $derivedPrincipal = $this->resolveBalanceVariance(
                        $prev['balance'],
                        $prev['principal']
                    );

                    DB::table('kass_staging_loans')->insert([
                        'raw_name'            => $carryName,
                        'adm_no'              => $carryAdm,
                        'company'             => $carryComp,
                        'loan_type'           => $label,
                        'year'                => $year,
                        'month'               => $month,
                        'outstanding_balance' => 0.0,
                        'principal_paid'      => $derivedPrincipal,
                        'interest_paid'       => 0.0,
                        'period_index'        => null,
                        'source_file'         => $sourceFile,
                        'raw_row_json'        => json_encode([
                            'system'     => 'DERIVED_ZERO',
                            'prev_bal'   => $prev['balance'],
                            'prev_princ' => $prev['principal'],
                        ]),
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                    // lock this loan block going forward (prevents re-trigger loops)
                    $lastLoanState[$loanKey] = [
                        'balance'   => 0.0,
                        'principal' => $derivedPrincipal,
                        'zero_lock' => true,
                    ];

                    continue;
                }

                // -------------------------------------------------
                // NORMAL ROW INSERT
                // -------------------------------------------------
                if ($dr === null && $cr === null && $int === null) {
                    continue;
                }

                DB::table('kass_staging_loans')->insert([
                    'raw_name'            => $carryName,
                    'adm_no'              => $carryAdm,
                    'company'             => $carryComp,
                    'loan_type'           => $label,
                    'year'                => $year,
                    'month'               => $month,
                    'outstanding_balance' => $dr,
                    'principal_paid'      => $cr,
                    'interest_paid'       => $int,
                    'period_index'        => $periodVal,
                    'source_file'         => $sourceFile,
                    'raw_row_json'        => json_encode([
                        'raw_dr'     => $dr,
                        'dr_source'  => $drSource,
                        'raw_cr'     => $cr,
                        'raw_int'    => $int,
                        'raw_period' => $periodVal,
                    ]),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                // Update state (DO NOT lock just because drSource == DERIVED)
                $lastLoanState[$loanKey] = [
                    'balance'   => $dr,
                    'principal' => $cr,
                    'zero_lock' => $prev['zero_lock'] ?? false,
                ];
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
            // $label = 'LOAN_' . ($loanNo + 1);
           

            // Detect loan label from row ABOVE header
$label = null;

if ($labelRow !== null) {

    // Scan across THIS loan block header band (YEAR → PERIOD)
    $scanUntil = min($i + 7, count($labelRow) - 1);

    for ($k = $i; $k <= $scanUntil; $k++) {

        $raw = strtoupper(trim((string) ($labelRow[$k] ?? '')));

        if ($raw === '') {
            continue;
        }

        // Ignore headers / noise
        if (in_array($raw, [
            'YEAR','MONTH','DR','CR','INT','INTEREST','PERIOD',
            'NAME','ADM NO','COMP',
            'PAYROLL','RECOVERIES','OVER/UNDER',
            'JAN','FEB','MAR','APR','MAY','JUN',
            'JUL','AUG','SEP','OCT','NOV','DEC'
        ])) {
            continue;
        }

        // Found a valid loan name
        $label = $raw;
        break;
    }
}

// ❌ NO FALLBACK — FAIL HARD
if ($label === null) {
    throw new \RuntimeException(
        "Loan name could not be resolved for loan block starting at column {$i}"
    );
}



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
    protected function validPeriodIndex($value)
    {
        if ($value === null) return null;

        // Trim outside spaces
        $value = trim($value);

        // If contains letters or symbols (except spaces), reject
        if (preg_match('/[^0-9 ]/', $value)) {
            return null;
        }

        // Remove spaces only
        $clean = str_replace(' ', '', $value);

        if ($clean === '') return null;

        $num = intval($clean);

        return ($num >= 1 && $num <= 72) ? $num : null;
    }
    private function memberKey(array $mapped): ?string
    {
        $adm  = $this->normalizeWhitespace((string)($mapped['adm_no'] ?? ''));
        $name = $this->normalizeWhitespace((string)($mapped['name'] ?? ''));
        $comp = $this->normalizeWhitespace((string)($mapped['comp'] ?? ''));

        $adm  = strtoupper($adm);
        $comp = strtoupper($comp);

        // 1) ALWAYS prefer ADM (members can move companies)
        if ($adm !== '') {
            return "ADM={$adm}";
        }

        // 2) fallback: name-based key, but DO NOT sort tokens
        if ($name !== '' && $comp !== '') {
            $canon = $this->canonicalPersonName($name);
            $parts = $this->nameParts($canon);

            // enforce "always use two names"
            if (count($parts) >= 2) {
                $firstTwo = $parts[0] . ' ' . $parts[1];
                return "NAME={$firstTwo}::COMP={$comp}";
            }
        }

        return null;
    }


    /**
     * Canonical person-name for identity matching in staging:
     * - remove punctuation
     * - lower/upper normalize
     * - split tokens
     * - sort tokens
     * This makes "MOSES NJENGA" and "MOSES NJENGA MUNYIRI" share a stable base,
     * and reduces splits from ordering/spacing.
     */
    private function canonicalPersonName(string $name): string
    {
        $name = strtoupper((string)$name);

        // remove punctuation but keep spaces
        $name = preg_replace('/[^A-Z0-9 ]+/', ' ', $name);

        // collapse multiple spaces + trim
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name;
    }

    private function normalizeWhitespace(string $s): string
    {
        $s = str_replace("\xC2\xA0", ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function nameParts(string $name): array
    {
        $canon = $this->canonicalPersonName($this->normalizeWhitespace($name));
        if ($canon === '') return [];
        return array_values(array_filter(explode(' ', $canon)));
    }

    /**
     * Build fallback name candidates in correct priority:
     * 1) FULL name exactly (all tokens)
     * 2) FIRST + SECOND
     * 3) FIRST + THIRD (if exists)
     */
    private function buildNameCandidates(string $rawName): array
    {
        $parts = $this->nameParts($rawName);
        if (count($parts) < 2) return [];

        $candidates = [];

        // 1) full name first (DO NOT split before trying it)
        $candidates[] = implode(' ', $parts);

        // 2) always enforce at least two names
        $first = $parts[0];
        $second = $parts[1];
        $candidates[] = $first . ' ' . $second;

        // 3) first + third (first name fixed)
        if (isset($parts[2])) {
            $third = $parts[2];
            $candidates[] = $first . ' ' . $third;
        }

        // unique, preserve order
        $out = [];
        foreach ($candidates as $c) {
            $c = $this->normalizeWhitespace($c);
            if ($c !== '' && !in_array($c, $out, true)) $out[] = $c;
        }

        return $out;
    }



    /**
     * Detects an abrupt zero-balance row at the end of an active loan repayment.
     *
     * RULE (ALL must be true):
     * 1) Current outstanding balance == 0
     * 2) Previous outstanding balance != 0
     * 3) Previous row had principal (CR present, numeric)
     * 4) No new-loan / top-up indicator on current row (period_index is null)
     *
     * If true → this zero must be USED for arithmetic continuity.
     */
    /**
     * Detects a zero-balance row that MUST be used for arithmetic continuity.
     *
     * RULE:
     * - current balance == 0
     * - expected balance (prev_balance - prev_principal) != 0
     * - no new-loan / top-up indicator (period_index is null)
     *
     * Formula check is done OUTSIDE this function.
     */
    private function isAbruptZeroLoanCheckpoint(
        ?float $prevBalance,
        ?float $prevPrincipal,
        ?float $currentBalance,
        $currentPeriodIndex
    ): bool {

        // Must have a current balance
        if ($currentBalance === null) {
            return false;
        }

        // Only zero balances
        if (abs($currentBalance) > 0.00001) {
            return false;
        }

        // Must have prior state
        if ($prevBalance === null || $prevPrincipal === null) {
            return false;
        }

        // Expected balance must NOT be zero
        $expectedBalance = $prevBalance - $prevPrincipal;
        if (abs($expectedBalance) <= 0.00001) {
            return false;
        }

        // Must NOT be a new loan / top-up
        if ($currentPeriodIndex !== null) {
            return false;
        }

        return true;
    }


    /**
     * Resolve balance mismatch when loan hits zero or collapses via formula.
     * Returns derived principal adjustment or null if no action needed.
     */
    /**
     * Computes derived principal needed to reconcile to a zero balance.
     * Assumes current balance is zero and expected balance is non-zero.
     */
    private function resolveBalanceVariance(
        float $prevBalance,
        float $prevPrincipal
    ): float {
        return $prevBalance - $prevPrincipal;
    }

    private function formulaIsSimpleArithmetic(Cell $cell): bool
    {
        if (!$cell->isFormula()) {
            return false;
        }

        $formula = strtoupper($cell->getValue());

        // Count cell references
        preg_match_all('/\b[A-Z]{1,3}[0-9]{1,7}\b/', $formula, $refs);
        $refCount = count(array_unique($refs[0]));

        // Count operators
        preg_match_all('/[+\-*\/]/', $formula, $ops);
        $opCount = count($ops[0]);

        // ACCEPT simple arithmetic like =E12-F12
        return ($refCount >= 2 && $opCount >= 1);
    }

    private function isOpeningJanuaryRow(string $memberKey, int $year, string $month): bool
    {
        if ($month !== 'JAN') {
            return false;
        }

        $key = $memberKey . '|' . $year;

        if (!isset($this->seenFirstJanuary[$key])) {
            // first JAN seen → tentatively opening
            $this->seenFirstJanuary[$key] = 1;
            return true;
        }

        // second JAN → first was opening, this one is transactional
        return false;
    }

    private function captureOpeningSnapshot(
        string $memberYearKey,
        array $mapped,
        array $headerNorm,
        array $row,
        int $year
    ): void {

        $runBalIndex = $this->getRunBalIndexAndKey($headerNorm)['index'];
        $runBal = ($runBalIndex !== null) ? $this->num($row[$runBalIndex] ?? null) : null;

        $capital = 0.0;

        foreach ($headerNorm as $i => $col) {
            $colNorm = strtoupper(trim($col));

            // cover: capital, n_capital (and any header normalized with capital)
            if (str_contains($colNorm, 'CAPITAL')) {
                $capital += (float)($this->num($row[$i] ?? null) ?? 0);
            }
        }

        $this->openingSnapshots[$memberYearKey] = [
            'year'          => $year,
            'share_run_bal' => $runBal,
            'capital'       => $capital,
            'mapped'        => $mapped,
        ];
    }


    private function reconcileOpeningBalances(string $sourceFile): void
    {
        foreach ($this->openingSnapshots as $memberYearKey => $snap) {

            $mapped = $snap['mapped'];

            // ✅ ALWAYS trust snapshot year
            $year = (int)($snap['year'] ?? 0);

            // Defensive fallback (rare)
            if ($year <= 0 && preg_match('/::YEAR=(\d{4})$/', $memberYearKey, $m)) {
                $year = (int)$m[1];
            }

            if ($year <= 0) {
                $this->logIssue($sourceFile, 'OPENING_SNAPSHOT_MISSING_YEAR', [
                    'key'  => $memberYearKey,
                    'snap' => $snap,
                ]);
                continue;
            }



            // // If year wasn’t in mapped for opening row, parse from key as fallback
            // if ($year <= 0 && preg_match('/::YEAR=(\d{4})$/', $memberYearKey, $m)) {
            //     $year = (int)$m[1];
            // }

            // Member scoping

            $comp = trim((string)($mapped['comp'] ?? ''));


            $adm  = trim((string)($mapped['adm_no'] ?? ''));
            $name = trim((string)($mapped['name'] ?? ''));

            $memberScope = DB::table('kass_staging_contributions')
                ->when($adm !== '', function ($q) use ($adm) {

                    // 1️⃣ ADM MATCH — FINAL, NO NAMES
                    $q->where('adm_no', $adm);
                }, function ($q) use ($name, $comp) {

                    // 2️⃣ NAME MATCH — ONLY IF ADM IS EMPTY
                    $q->where('company', $comp);

                    $candidates = $this->buildNameCandidates($name);

                    if (empty($candidates)) {
                        // force no matches if name is unusable
                        $q->whereRaw('1 = 0');
                        return;
                    }

                    // IMPORTANT: ordered ORs, NOT token loops
                    $q->where(function ($qq) use ($candidates) {
                        foreach ($candidates as $cand) {
                            $qq->orWhereRaw(
                                "UPPER(TRIM(REGEXP_REPLACE(raw_name, '[[:space:]]+', ' '))) = ?",
                                [$cand]
                            );
                        }
                    });
                });



            // -------------------------
            // SHARES opening RUN BAL recon (CR/DR)
            // -------------------------
            if ($snap['share_run_bal'] !== null) {

                $historicalNet = (clone $memberScope)
                    ->where('year', '<', $year)
                    ->whereIn('raw_type', ['CR', 'DR'])
                    ->sum(DB::raw("
                    CASE 
                        WHEN raw_type = 'CR' THEN amount
                        WHEN raw_type = 'DR' THEN -amount
                        ELSE 0
                    END
                "));

                $delta = (float)$snap['share_run_bal'] - (float)$historicalNet;

                // If opening is higher, we add CR to equalise.
                if ($delta > 1) {
                    DB::table('kass_staging_contributions')->insert([
                        'raw_name'     => $name,
                        'adm_no'       => $adm,
                        'company'      => $comp,
                        'year'         => $year,
                        'month'        => 'JAN',
                        'raw_type'     => 'CR',
                        'amount'       => $delta,
                        'source_file'  => $sourceFile,
                        'raw_row_json' => json_encode([
                            'system'   => 'OPENING_SHARE_RECON',
                            'opening'  => $snap['share_run_bal'],
                            'history'  => $historicalNet,
                            'delta'    => $delta,
                        ]),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                } elseif ($delta < -1) {

                    // History is HIGHER than opening RUN BAL
                    // Therefore we must DEBIT to reduce it

                    DB::table('kass_staging_contributions')->insert([
                        'raw_name'     => $name,
                        'adm_no'       => $adm,
                        'company'      => $comp,
                        'year'         => $year,
                        'month'        => 'JAN',
                        'raw_type'     => 'DR',
                        'amount'       => abs($delta),
                        'source_file'  => $sourceFile,
                        'raw_row_json' => json_encode([
                            'system'   => 'OPENING_SHARE_RECON',
                            'opening'  => $snap['share_run_bal'],
                            'history'  => $historicalNet,
                            'delta'    => $delta,
                            'direction' => 'DOWNWARD_ADJUSTMENT'
                        ]),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

            // -------------------------
            // CAPITAL opening recon (CAPITAL + N/CAPITAL treated as CAPITAL)
            // -------------------------
            if ($snap['capital'] > 0) {

                $historicalCapital = (clone $memberScope)
                    ->where('year', '<', $year)
                    ->where('raw_type', 'CAPITAL')
                    ->sum('amount');

                $deltaCap = (float)$snap['capital'] - (float)$historicalCapital;

                if ($deltaCap > 1) {
                    DB::table('kass_staging_contributions')->insert([
                        'raw_name'     => $name,
                        'adm_no'       => $adm,
                        'company'      => $comp,
                        'year'         => $year,
                        'month'        => 'JAN',
                        'raw_type'     => 'CAPITAL',
                        'amount'       => $deltaCap,
                        'source_file'  => $sourceFile,
                        'raw_row_json' => json_encode([
                            'system'  => 'OPENING_CAPITAL_RECON',
                            'opening' => $snap['capital'],
                            'history' => $historicalCapital,
                            'delta'   => $deltaCap,
                        ]),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                } elseif ($deltaCap < -1) {
                    $this->logIssue($sourceFile, 'OPENING_CAPITAL_LOWER_THAN_HISTORY', [
                        'member'  => $mapped,
                        'opening' => $snap['capital'],
                        'history' => $historicalCapital,
                        'delta'   => $deltaCap,
                    ]);
                }
            }
        }
    }

    private function memberYearKey(array $mapped, int $year): string
    {
        $adm  = strtoupper(trim((string)($mapped['adm_no'] ?? '')));
        $name = strtoupper(trim((string)($mapped['name'] ?? '')));
        $comp = strtoupper(trim((string)($mapped['comp'] ?? '')));

        if ($adm !== '') {
            return "ADM={$adm}::COMP={$comp}::YEAR={$year}";
        }


        $canon = $this->canonicalPersonName($name);
        return "NAME={$canon}::COMP={$comp}::YEAR={$year}";
    }


    private function normalizeContributionType(string $rawType): string
    {
        $t = strtoupper(trim($rawType));

        // Merge all capital variants
        if (str_contains($t, 'CAPITAL')) {
            return 'CAPITAL';
        }

        // Keep DR/CR as-is
        if ($t === 'DR' || $t === 'CR') {
            return $t;
        }

        return $t;
    }
    private function insertContributionTransactionsForRow(
        array $mapped,
        int $year,
        string $month,
        array $row,
        array $headerRaw,
        array $headerNorm,
        array $transactionIndexes,
        array &$headerCarry,
        string $sourceFile,
        int $rowIndex
    ): void {

        foreach ($transactionIndexes as $colIndex) {

            if (!array_key_exists($colIndex, $row) || !array_key_exists($colIndex, $headerRaw)) {
                continue;
            }

            $rawHeaderLabel = trim((string)($headerRaw[$colIndex] ?? ''));

            if ($rawHeaderLabel !== '') {
                $headerCarry[$colIndex] = $rawHeaderLabel;
            } else {
                $rawHeaderLabel = $headerCarry[$colIndex] ?? '';
            }

            $amount = $this->num($row[$colIndex] ?? null);

            if ($amount === null || $amount == 0) {
                continue;
            }
            //$normalizedKey = $headerNorm[$colIndex] ?? 'unknown';
            $rawType = $this->resolveRawType(
                $rawHeaderLabel,
                $headerNorm[$colIndex] ?? null
            );




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
        }
    }
    private function resolveRawType(string $rawHeaderLabel, ?string $normalizedKey): string
    {
        $rawType = trim($rawHeaderLabel) !== ''
            ? strtoupper($rawHeaderLabel)
            : strtoupper($normalizedKey ?? 'unknown');

        return $this->normalizeContributionType($rawType);
    }


    private function getTwoNameCombinations(string $name): array
    {
        $tokens = explode(' ', $this->normalizeName($name));

        if (count($tokens) < 2) {
            return [];
        }

        $pairs = [];

        for ($i = 0; $i < count($tokens); $i++) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                $pairs[] = $tokens[$i] . ' ' . $tokens[$j];
            }
        }

        return array_unique($pairs);
    }

    private function normalizeName(string $name): string
    {
        $name = strtoupper($name);
        $name = preg_replace('/[^A-Z\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    private function resolveAdmFromStaging(array $mapped): ?string
    {
        $admNo   = trim((string)($mapped['adm_no'] ?? ''));
        $nameRaw = trim((string)($mapped['name'] ?? ''));
        $comp    = trim((string)($mapped['comp'] ?? ''));

        if ($admNo !== '') {
            return $admNo; // ADM already known
        }

        if ($nameRaw === '' || $comp === '') {
            return null;
        }

        $full = $this->canonicalPersonName($nameRaw);

        // 1) FULL NAME FIRST (exact canonical match)
        $found = DB::table('kass_staging_contributions')
            ->where('company', $comp)
            ->whereNotNull('adm_no')
            ->whereRaw(
                "UPPER(TRIM(REGEXP_REPLACE(raw_name, '[[:space:]]+', ' '))) = ?",
                [$full]
            )
            ->value('adm_no');

        if (!empty($found)) {
            return (string)$found;
        }

        // 2) TWO-NAME FALLBACK (still scoped to company)
        foreach ($this->buildNameCandidates($nameRaw) as $cand) {

            $parts = explode(' ', $cand);
            if (count($parts) < 2) {
                continue;
            }

            $a = $parts[0];
            $b = $parts[1];

            $found = DB::table('kass_staging_contributions')
                ->where('company', $comp)
                ->whereNotNull('adm_no')
                ->whereRaw("UPPER(raw_name) LIKE ? AND UPPER(raw_name) LIKE ?", ["%$a%", "%$b%"])
                ->value('adm_no');

            if (!empty($found)) {
                return (string)$found;
            }
        }

        return null;
    }
    /**
     * Safely backfill ADM number for rows missing adm_no,
     * using FIRST + SECOND name tokens only.
     *
     * Returns true if an update was attempted, false if skipped.
     */
    private function safeBackfillAdmFromName(array $mapped): bool
    {
        if (empty($mapped['adm_no']) || empty($mapped['name']) || empty($mapped['comp'])) {
            return false;
        }

        $parts = explode(' ', $this->canonicalPersonName($mapped['name']));
        $parts = array_values(array_filter($parts));

        // ❗ Never backfill on single-token names
        if (count($parts) < 2) {
            return false;
        }

        $first  = $parts[0];
        $second = $parts[1];

        DB::table('kass_staging_contributions')
            ->where('company', $mapped['comp'])
            ->whereNull('adm_no')
            ->whereRaw(
                "UPPER(raw_name) LIKE ? AND UPPER(raw_name) LIKE ?",
                ["%{$first}%", "%{$second}%"]
            )
            ->update([
                'adm_no'     => $mapped['adm_no'],
                'updated_at' => now(),
            ]);

        return true;
    }
}
