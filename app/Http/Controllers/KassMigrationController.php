<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KassMigrationController extends Controller
{
    /**
     * How many data rows to process per request when using processAll()
     */
    protected int $chunkSize = 500;

    public function __construct()
    {
        $this->middleware('auth');

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
     |  PROCESS A SINGLE FILE (manual button) – FULL IMPORT
     |  (Can still timeout on very huge files – use processAll()
     |   for safe, chunked processing)
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
     |   PROCESS ALL FILES – SAFE, CHUNKED (NO TIMEOUTS)
     |
     |   Uses a JSON tracker at storage/app/kass_chunk_state.json
     |   and processes only $chunkSize rows per request.
     |
     |   Your Blade button + auto-refresh still works:
     |
     |   <form action="{{ route('kass.process.all') }}" method="GET">
     |       <button class="btn btn-lg btn-success w-100 my-3">
     |           🚀 Import ALL Uploaded Excel Files into Staging
     |       </button>
     |   </form>
     |
     ===========================================================*/
    public function processAll()
{
    $files = collect(Storage::files('kass_uploads'))
        ->filter(fn($f) => preg_match('/\.(xls|xlsx)$/i', $f))
        ->values();

    if ($files->isEmpty()) {
        $this->resetChunkState();
        return view('kass.process_done', [
            'message'   => "🎉 All files processed!",
            'remaining' => 0,
            'next'      => false
        ]);
    }

    // Load state (file + row pointer + carry vars)
    $state = $this->loadChunkState();

    // If no file selected or file deleted, pick the first
    if (!$state['file'] || !Storage::exists($state['file'])) {
        $state = [
            'file'         => $files[0],
            'row'          => 0,
            'carry_name'   => null,
            'carry_adm'    => null,
            'carry_comp'   => null,
            'opening_done' => [],
        ];
    }

    $file = $state['file'];

    // Convert XLS → XLSX automatically
    $converted = $this->convertXlsToXlsx($file);

    if (!$converted) {
        $this->logFileEvent($file, "XLS_CONVERT_FAIL", "Conversion failed, skipping.");
        Storage::delete($file);
        $this->resetChunkState();

        return view('kass.process_step', [
            'message'   => "❌ Failed converting {$file}. Skipped.",
            'remaining' => max(count($files) - 1, 0),
            'next'      => true
        ]);
    }

    // Process ONE CHUNK using your full SACCO parser
    $result = $this->processExcelChunkedContrib($converted, $state);

    if ($result['finished']) {

        Storage::delete($file);
        $this->resetChunkState();

        $remaining = max(count($files) - 1, 0);

        return view('kass.process_step', [
            'message'   => "✅ Finished & removed " . basename($file),
            'remaining' => $remaining,
            'next'      => $remaining > 0
        ]);
    }

    // Save updated chunk state and continue
    $this->saveChunkState($result['state']);

    return view('kass.process_step', [
        'message'   => "⏳ Importing " . basename($file) . 
                       " — rows {$state['row']} → {$result['state']['row']}",
        'remaining' => "Processing current + " . max(count($files) - 1, 0),
        'next'      => true
    ]);
}

    private function importShareOrLoanSheet($filePath)
    {
        $file = storage_path("app/{$filePath}");

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);

        // Load only SHAREMPA or LOANMPA
        $reader->setLoadSheetsOnly(['SHAREMPA', 'LOANMPA']);

        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getActiveSheet();

        $rowCount = $sheet->getHighestDataRow();
        $chunkSize = 200;

        for ($start = 2; $start <= $rowCount; $start += $chunkSize) {

            $end = $start + $chunkSize - 1;
            if ($end > $rowCount) $end = $rowCount;

            $rows = [];

            for ($row = $start; $row <= $end; $row++) {
                $name = trim($sheet->getCell("A{$row}")->getValue());

                if ($name === null || $name === "") continue;

                $rows[] = [
                    'name'   => $sheet->getCell("A{$row}")->getValue(),
                    'adm'    => $sheet->getCell("B{$row}")->getValue(),
                    'comp'   => $sheet->getCell("C{$row}")->getValue(),
                    'year'   => $sheet->getCell("D{$row}")->getValue(),
                    'month'  => $sheet->getCell("E{$row}")->getValue(),
                    'memb'   => $sheet->getCell("F{$row}")->getValue(),
                    'dr'     => $sheet->getCell("G{$row}")->getValue(),
                    'cr'     => $sheet->getCell("H{$row}")->getValue(),
                    'bal'    => $sheet->getCell("I{$row}")->getValue(),
                    'file'   => $filePath,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            if (!empty($rows)) {
                DB::table('kass_staging_shares')->insert($rows);
            }
        }
    }


    /*===========================================================
     |
     |   EXCEL HANDLING – FULL (used by manual process())
     |
     |   Contributions only. LOANMPA is **ignored** as requested.
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

        // LOANMPA – intentionally ignored for now
        /*
        if (isset($sheets['LOANMPA'])) {
            $this->processLoanSheet($sheets['LOANMPA'], $filePath);
        } else {
            $this->logFileEvent($filePath, "MISSING_LOANMPA", "LOANMPA sheet not found.");
        }
        */
    }

    /* If someone uploads CSV/TXT – log and ignore (no fatal error) */
    private function processCsv(string $filePath): void
    {
        $this->logFileEvent($filePath, 'CSV_IGNORED', 'CSV/TXT not supported for structured import.');
    }

    /*===========================================================
     |
     |   SHAREMPA — Contributions Sheet (FULL RUN)
     |
     |   (Your original logic — unchanged)
     |
     ===========================================================*/
    private function processContributionSheet($sheet, string $sourceFile): void
    {
        $rows  = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

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
                $this->logIssue(
                    $sourceFile,
                    'ANOMALY_MISSING_KEY_FIELDS',
                    array_merge($mapped, [
                        '_carry_name' => $carryName,
                        '_carry_adm'  => $carryAdm,
                        '_carry_comp' => $carryComp
                    ])
                );

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
     |   CHUNKED CONTRIBUTIONS PROCESSOR (for processAll)
     |
     |   - Uses same logic as processContributionSheet(),
     |     but only handles a slice of rows per request and
     |     persists carry + opening_done state in JSON.
     |
     ===========================================================*/
    private function processExcelChunkedContrib(string $filePath, array $state): array
    {
        $fullPath    = storage_path("app/{$filePath}");
        $spreadsheet = IOFactory::load($fullPath);

        // Collect sheets
        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[strtoupper(trim($sheet->getTitle()))] = $sheet;
        }

        // SHAREMPA (or best match)
        $share = $this->findSharempaSheet($sheets);

        if (!$share) {
            $this->logFileEvent($filePath, "MISSING_SHAREMPA", "No sheet resembling SHAREMPA (chunked).");
            // Treat as finished
            return [
                'finished' => true,
                'state'    => $state,
            ];
        }

        $sheet = $share['sheet'];

        $rows  = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        // Header detection
        [$headerRaw, $start] = $this->extractHeader($clean);
        $headerNorm          = $this->normalizeHeaders($headerRaw);

        //  Locate columns
        $monthIndex = $this->getMonthIndex($headerNorm);
        $runBalInfo = $this->getRunBalIndexAndKey($headerNorm);
        $runBalIndex = $runBalInfo['index'];  // can be null
        $runBalKey   = $runBalInfo['key'];    // normalized key, e.g. "run_bal"

        $transactionIndexes = $this->getTransactionColumnIndexes($headerNorm, $monthIndex, $runBalIndex);

        // Restore state
        $maxRow      = count($clean);
        $currentRow  = $state['row'] ?? 0;

        if ($currentRow < $start) {
            $currentRow = $start;
        }

        $carryName   = $state['carry_name'] ?? null;
        $carryAdm    = $state['carry_adm'] ?? null;
        $carryComp   = $state['carry_comp'] ?? null;
        $openingDone = [];

        if (!empty($state['opening_done']) && is_array($state['opening_done'])) {
            foreach ($state['opening_done'] as $k) {
                $openingDone[$k] = true;
            }
        }

        $limitRow = $currentRow + $this->chunkSize;

        // === Main chunk loop ===
        for ($i = $currentRow; $i < $maxRow && $i < $limitRow; $i++) {
            $row = $clean[$i];

            if (!$this->rowHasValues($row)) {
                continue;
            }

            $mapped = $this->mapRow($headerNorm, $row);

            if ($this->looksLikeHeaderRow($mapped)) {
                $this->logIssue($filePath, 'HEADER_ROW_IN_DATA_CHUNK', $mapped);
                continue;
            }

            // Carry-down
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
                $this->logIssue(
                    $filePath,
                    'ANOMALY_MISSING_KEY_FIELDS_CHUNK',
                    array_merge($mapped, [
                        '_carry_name' => $carryName,
                        '_carry_adm'  => $carryAdm,
                        '_carry_comp' => $carryComp
                    ])
                );
                continue;
            }

            $year  = $this->cleanYear($mapped['year'] ?? null, $filePath, $mapped);
            $month = $this->cleanMonth($mapped['month'] ?? null, $filePath, $mapped);

            $openingKey = ($carryComp ?? '') . '|' . ($carryAdm ?? '') . '|' . (string) $year;

            $dr = $this->num($mapped['dr'] ?? null);
            $cr = $this->num($mapped['cr'] ?? null);

            $runBal = null;
            if ($runBalKey !== null && array_key_exists($runBalKey, $mapped)) {
                $runBal = $this->num($mapped[$runBalKey]);
            }

            // Opening balance
            if ($runBal !== null && !isset($openingDone[$openingKey])) {
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
                            'month'             => $month,
                            'raw_type'          => 'OPENING_BALANCE',
                            'amount'            => $openingAmount,
                            'source_file'       => $filePath,
                            'raw_row_json'      => json_encode($mapped),
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    } catch (\Exception $e) {
                        $this->logIssue(
                            $filePath,
                            'DB_INSERT_ERROR_OPENING_BALANCE_CHUNK: ' . $e->getMessage(),
                            array_merge($mapped, ['_opening_amount' => $openingAmount])
                        );
                    }
                }

                $openingDone[$openingKey] = true;
            }

            // Transactions
            foreach ($transactionIndexes as $colIndex) {
                if (!array_key_exists($colIndex, $row) || !array_key_exists($colIndex, $headerRaw)) {
                    continue;
                }

                $rawHeaderLabel = trim((string) $headerRaw[$colIndex]);
                $rawCellValue   = $row[$colIndex];

                $amount = $this->num($rawCellValue);

                if ($amount === null || $amount == 0) {
                    continue;
                }

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
                        'raw_type'          => $rawType,
                        'amount'            => $amount,
                        'source_file'       => $filePath,
                        'raw_row_json'      => json_encode($mapped),
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                } catch (\Exception $e) {
                    $this->logIssue(
                        $filePath,
                        'DB_INSERT_ERROR_TRANSACTION_CHUNK: ' . $e->getMessage(),
                        array_merge($mapped, [
                            '_col_index' => $colIndex,
                            '_raw_type'  => $rawType,
                            '_amount'    => $amount,
                        ])
                    );
                }
            }
        }

        $finished = ($i >= $maxRow);

        $newState = [
            'file'         => $filePath,
            'row'          => $finished ? $maxRow : $i,
            'carry_name'   => $carryName,
            'carry_adm'    => $carryAdm,
            'carry_comp'   => $carryComp,
            'opening_done' => array_keys($openingDone),
        ];

        return [
            'finished' => $finished,
            'state'    => $newState,
        ];
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
        $candidates = [];

        foreach ($sheets as $title => $sheetObj) {

            // Clean sheet title
            $clean = strtoupper(trim(str_replace("\xC2\xA0", ' ', $title)));

            // Remove inner spaces to allow "SHARE MPA"
            $noSpace = str_replace(' ', '', $clean);

            // 1️⃣ STRICT MATCH
            if ($clean === 'SHAREMPA' || $noSpace === 'SHAREMPA') {
                return [
                    'sheet' => $sheetObj,
                    'match' => "STRICT: $title"
                ];
            }

            // 2️⃣ VARIANTS using symbols
            $variant = str_replace([' ', '_', '-', '.'], '', $clean);
            if ($variant === 'SHAREMPA') {
                $candidates["VARIANT:$title"] = $sheetObj;
            }

            // 3️⃣ PREFIX / SUFFIX
            if (str_starts_with($clean, 'SHAREMPA') || str_ends_with($clean, 'SHAREMPA')) {
                $candidates["PREFIX_SUFFIX:$title"] = $sheetObj;
            }

            // 4️⃣ CONTAINS BOTH WORDS
            if (str_contains($clean, 'SHARE') && str_contains($clean, 'MPA')) {

                $posShare = strpos($clean, 'SHARE');
                $posMpa   = strpos($clean, 'MPA');

                if ($posShare !== false && $posMpa !== false && $posShare < $posMpa) {
                    $candidates["CONTAINS:$title"] = $sheetObj;
                }
            }
        }

        // Resolve multiple candidates
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

        return $score >= 2;
    }

    private function getMonthIndex(array $headerNorm): ?int
    {
        foreach ($headerNorm as $i => $col) {
            if ($col === 'month') {
                return $i;
            }
        }
        return null;
    }

    private function getRunBalIndexAndKey(array $headerNorm): array
    {
        foreach ($headerNorm as $i => $col) {
            if (str_contains($col, 'run') && str_contains($col, 'bal')) {
                return ['index' => $i, 'key' => $col];
            }
        }

        return ['index' => null, 'key' => null];
    }

    private function getTransactionColumnIndexes(array $headerNorm, ?int $monthIndex, ?int $runBalIndex): array
    {
        $indexes = [];
        $max     = count($headerNorm);

        for ($i = 0; $i < $max; $i++) {

            $key = $headerNorm[$i];

            if (in_array($key, ['name', 'adm_no', 'comp', 'company', 'pfno', 'year', 'month', 'period'])) {
                continue;
            }

            if ($monthIndex !== null && $i <= $monthIndex) {
                continue;
            }

            if ($runBalIndex !== null && $i >= $runBalIndex) {
                continue;
            }

            $indexes[] = $i;
        }

        return $indexes;
    }

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

    private function num($v)
    {
        if ($v === null || $v === '') {
            return null;
        }

        $v = str_replace([',', ' '], '', (string) $v);

        return is_numeric($v) ? (float) $v : null;
    }

    /*===========================================================
     |
     |   LOGGING HELPERS
     |
     ===========================================================*/

    private function logFileEvent(string $file, string $type, string $note): void
    {
        $line = "[" . now() . "] FILE: {$file} | TYPE: {$type} | NOTE: {$note}\n";
        Storage::append('logs/kass_missing_sheets.log', $line);
    }

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
     |  Legacy tracker-based endpoint – unchanged
     |  (still uses full processExcel, so may timeout on big files)
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

    /*===========================================================
     |  CHUNK STATE HELPERS (for processAll)
     ===========================================================*/
    private function getChunkStatePath(): string
    {
        return storage_path('app/kass_chunk_state.json');
    }

    private function loadChunkState(): array
    {
        $path = $this->getChunkStatePath();

        if (!file_exists($path)) {
            return [
                'file'         => null,
                'row'          => 0,
                'carry_name'   => null,
                'carry_adm'    => null,
                'carry_comp'   => null,
                'opening_done' => [],
            ];
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true) ?: [];

        return array_merge([
            'file'         => null,
            'row'          => 0,
            'carry_name'   => null,
            'carry_adm'    => null,
            'carry_comp'   => null,
            'opening_done' => [],
        ], $data);
    }

    private function saveChunkState(array $state): void
    {
        $path = $this->getChunkStatePath();
        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function resetChunkState(): void
    {
        $path = $this->getChunkStatePath();
        if (file_exists($path)) {
            unlink($path);
        }
    }


    private function convertXlsToXlsx($filePath)
    {
        $fullPath = storage_path("app/{$filePath}");

        // If file is already xlsx, return as-is
        if (!str_ends_with(strtolower($filePath), '.xls')) {
            return $filePath;
        }

        try {
            // Load XLS
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
            $spreadsheet = $reader->load($fullPath);

            // Convert path
            $newFilePath = str_replace('.xls', '.xlsx', $filePath);
            $newFullPath = storage_path("app/{$newFilePath}");

            // Save XLSX
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($newFullPath);

            return $newFilePath;
        } catch (\Exception $e) {
            \Log::error("XLS Conversion Failed: {$e->getMessage()}");
            return null;
        }
    }
}
