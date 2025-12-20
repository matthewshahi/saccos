<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KassContributionsImportController extends Controller
{
    /**
     * Normalised month order
     */
    private array $monthOrder = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];

    /**
     * Track last month seen per member (sequence-based)
     */
    private array $lastMonthPerMember = [];

    /**
     * Track last rowIndex for JAN per member (so when we see a consecutive JAN, we can tag the previous row as OPENING_SNAPSHOT)
     */
    private array $lastJanRowIndexPerMember = [];

    /**
     * MAIN ENTRY: Upload + import contributions only (fresh)
     */
    public function index()
{
    return view('kass.import.contributions');
}

public function run()
{
    // Folder where ALL contribution files live
    $folder = 'kass_uploads';

    // Get all Excel files
    $files = collect(Storage::files($folder))
        ->filter(fn ($f) => preg_match('/\.(xls|xlsx|csv)$/i', $f))
        ->values();

    if ($files->isEmpty()) {
        return response()->json([
            'status' => 'error',
            'message' => 'No contribution files found in ' . $folder
        ]);
    }

    // Fresh contributions-only import
    DB::table('kass_staging_contributions')->truncate();

    // Reset trackers for THIS RUN
    $this->lastMonthPerMember = [];
    $this->lastJanRowIndexPerMember = [];

    $summary = [
        'files_processed' => 0,
        'rows_imported'   => 0,
        'runbal_rows'     => 0,
        'txn_rows'        => 0,
    ];

    foreach ($files as $filePath) {

        $summary['files_processed']++;

        // THIS calls the SAME per-file logic you already have
        $result = $this->importSingleFile($filePath);

        $summary['rows_imported'] += $result['rows'] ?? 0;
        $summary['runbal_rows']   += $result['runbals'] ?? 0;
        $summary['txn_rows']      += $result['txns'] ?? 0;
    }

    return response()->json([
        'status'  => 'ok',
        'message' => 'Contributions import completed',
        'summary' => $summary
    ]);
}
private function importSingleFile(string $path): array
{
    $fullPath = Storage::path($path);
    $spreadsheet = IOFactory::load($fullPath);

    $rows = 0;
    $runbals = 0;
    $txns = 0;

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {

        // 🔽 EVERYTHING INSIDE HERE
        // 🔽 is EXACTLY the sheet + row logic you already have
        // 🔽 do NOT change it

        // increment counters when inserting
        // $rows++
        // $runbals++
        // $txns++
    }

    return [
        'rows'    => $rows,
        'runbals' => $runbals,
        'txns'    => $txns,
    ];
}


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx,csv',
        ]);

        // 1) Store upload
        $path = $request->file('file')->store('kass_uploads');
        $sourceFile = $path;

        // 2) Fresh contributions-only staging import
        //    (We DO NOT touch loans or loan staging)
        DB::table('kass_staging_contributions')->truncate();

        // Reset trackers for this run
        $this->lastMonthPerMember = [];
        $this->lastJanRowIndexPerMember = [];

        // 3) Read spreadsheet
        $fullPath = Storage::path($path);
        $spreadsheet = IOFactory::load($fullPath);

        $importedRows = 0;
        $importedRunBals = 0;
        $importedTxns = 0;
        $openingTaggedRows = 0;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetName = (string)$sheet->getTitle();
            $highestRow = $sheet->getHighestDataRow();
            $highestCol = $sheet->getHighestDataColumn();
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

            // 4) Find header row
            //    We need a row that contains NAME and MONTH and (YEAR or RUN BAL)
            $headerRowIndex = $this->detectHeaderRow($sheet, $highestRow, $highestColIndex);
            if ($headerRowIndex === null) {
                continue; // skip sheets with no recognizable contribution layout
            }

            // 5) Build header maps
            $headerRaw = [];
            $headerNorm = [];
            $headerCarry = [];

            for ($c = 1; $c <= $highestColIndex; $c++) {
                $val = trim((string)$sheet->getCellByColumnAndRow($c, $headerRowIndex)->getFormattedValue());
                $headerRaw[$c] = $val;
                $headerNorm[$c] = $this->normHeader($val);
            }

            // 6) Detect required columns
            $idxName  = $this->findHeaderIndex($headerNorm, ['NAME']);
            $idxAdm   = $this->findHeaderIndex($headerNorm, ['ADM NO','ADM','MEMBER NO','MEMB NO','MEMBER']);
            $idxComp  = $this->findHeaderIndex($headerNorm, ['COMP','COMPANY']);
            $idxYear  = $this->findHeaderIndex($headerNorm, ['YEAR']);
            $idxMonth = $this->findHeaderIndex($headerNorm, ['MONTH']);
            $idxRunBal = $this->findHeaderIndex($headerNorm, ['RUN BAL','RUNBAL','RUNNING BAL','RUNNING BALANCE','RUN BALANCE','BAL','BALANCE']);

            // Minimal requirement: NAME + MONTH + YEAR + RUN_BAL
            // (If your file omits YEAR but the file name contains year, we can fallback; but we keep this strict to avoid bad imports.)
            if ($idxName === null || $idxMonth === null || $idxYear === null || $idxRunBal === null) {
                continue;
            }

            // 7) Determine transaction columns (all contribution channels)
            $transactionIndexes = $this->getTransactionColumnIndexes(
                $headerNorm,
                $idxName, $idxAdm, $idxComp, $idxYear, $idxMonth, $idxRunBal
            );

            // 8) Iterate data rows (top-down)
            for ($r = $headerRowIndex + 1; $r <= $highestRow; $r++) {

                // Read key cells
                $rawName = trim((string)$sheet->getCellByColumnAndRow($idxName, $r)->getFormattedValue());
                $rawMonth = trim((string)$sheet->getCellByColumnAndRow($idxMonth, $r)->getFormattedValue());
                $rawYear = trim((string)$sheet->getCellByColumnAndRow($idxYear, $r)->getFormattedValue());

                // Skip empty lines
                if ($rawName === '' && $rawMonth === '' && $rawYear === '') {
                    continue;
                }

                $year = $this->safeInt($rawYear);
                $month = $this->safeMonth($rawMonth);

                if ($year === null || $month === null) {
                    continue;
                }

                $adm = $idxAdm ? trim((string)$sheet->getCellByColumnAndRow($idxAdm, $r)->getFormattedValue()) : null;
                $comp = $idxComp ? trim((string)$sheet->getCellByColumnAndRow($idxComp, $r)->getFormattedValue()) : null;

                // Normalize identity
                $mapped = $this->mapMemberIdentity($rawName, $adm, $comp, $sourceFile);

                // MemberKey for sequence logic (strictly same member)
                $memberKey = $this->memberKey($mapped);

                // Read entire row to array for per-column extraction + carrydown
                $row = [];
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $row[$c] = $sheet->getCellByColumnAndRow($c, $r)->getFormattedValue();
                }

                // ------------------------------------------------------------
                // RULE: ONLY SPECIAL CASE WHEN JAN IS CONSECUTIVE (JAN then JAN)
                // ------------------------------------------------------------
                $prevMonth = $this->lastMonthPerMember[$memberKey] ?? null;
                $isConsecutiveJanuary = ($prevMonth === 'JAN' && $month === 'JAN');

                // If current row is JAN, track it; if we later see another JAN consecutively, we will tag THIS previous JAN row as opening snapshot.
                if ($month === 'JAN') {
                    // If this JAN is consecutive, tag the previous JAN rowIndex as OPENING_SNAPSHOT
                    if ($isConsecutiveJanuary) {
                        $prevJanRowIndex = $this->lastJanRowIndexPerMember[$memberKey] ?? null;
                        if ($prevJanRowIndex !== null) {
                            $openingTaggedRows += $this->tagOpeningSnapshotRow(
                                $mapped,
                                $year,
                                $prevJanRowIndex,
                                $sourceFile,
                                $sheetName
                            );
                        }
                    }
                    // store current JAN row index as "last JAN row index"
                    $this->lastJanRowIndexPerMember[$memberKey] = $r;
                }

                // Update last month seen (sequence)
                $this->lastMonthPerMember[$memberKey] = $month;

                // ------------------------------------------------------------
                // 1) Insert RUN_BAL (once per row, observational)
                // ------------------------------------------------------------
                $runBal = $this->num($row[$idxRunBal] ?? null);
                if ($runBal !== null) {
                    DB::table('kass_staging_contributions')->insert([
                        'raw_name'     => $mapped['name'],
                        'adm_no'       => $mapped['adm_no'],
                        'company'      => $mapped['comp'],
                        'year'         => (int)$year,
                        'month'        => $month,
                        'raw_type'     => 'RUN_BAL',
                        'amount'       => $runBal,
                        'source_file'  => $sourceFile,
                        'raw_row_json' => json_encode([
                            'system'       => 'LEGACY_OBSERVED_RUN_BAL',
                            'sheet'        => $sheetName,
                            'row_index'    => $r,
                            'is_consecutive_jan' => $isConsecutiveJanuary,
                        ]),
                        'notes'        => $this->notesKey($sheetName, $r),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    $importedRunBals++;
                }

                // ------------------------------------------------------------
                // 2) Insert all contribution transactions for that row
                //    (supports multiple columns per month: CR, DR, CAPITAL, etc.)
                // ------------------------------------------------------------
                foreach ($transactionIndexes as $colIndex) {

                    // Carry-down header label (for merged header sheets)
                    $rawHeaderLabel = trim((string)($headerRaw[$colIndex] ?? ''));
                    if ($rawHeaderLabel !== '') {
                        $headerCarry[$colIndex] = $rawHeaderLabel;
                    } else {
                        $rawHeaderLabel = $headerCarry[$colIndex] ?? '';
                    }

                    $type = $this->mapContributionType($rawHeaderLabel);
                    if ($type === null) {
                        continue;
                    }

                    $amount = $this->num($row[$colIndex] ?? null);
                    if ($amount === null || abs($amount) < 0.00001) {
                        continue;
                    }

                    DB::table('kass_staging_contributions')->insert([
                        'raw_name'     => $mapped['name'],
                        'adm_no'       => $mapped['adm_no'],
                        'company'      => $mapped['comp'],
                        'year'         => (int)$year,
                        'month'        => $month,
                        'raw_type'     => $type,       // e.g., CR, DR, CAPITAL, WELFARE, etc.
                        'amount'       => $amount,
                        'source_file'  => $sourceFile,
                        'raw_row_json' => json_encode([
                            'system'     => 'EXCEL_TRANSACTION',
                            'sheet'      => $sheetName,
                            'row_index'  => $r,
                            'col_index'  => $colIndex,
                            'col_label'  => $rawHeaderLabel,
                            'is_consecutive_jan' => $isConsecutiveJanuary,
                        ]),
                        'notes'        => $this->notesKey($sheetName, $r),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    $importedTxns++;
                }

                $importedRows++;
            }
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Contributions import completed (fresh).',
            'source_file' => $sourceFile,
            'imported_data_rows' => $importedRows,
            'inserted_run_bal_rows' => $importedRunBals,
            'inserted_transaction_rows' => $importedTxns,
            'opening_snapshot_tagged_rows' => $openingTaggedRows,
        ]);
    }

    /**
     * Tag a specific "row" (by notesKey) as OPENING_SNAPSHOT when we detect consecutive JAN.
     * This does NOT delete or skip anything.
     * It only labels the first JAN row as an opening snapshot.
     */
    private function tagOpeningSnapshotRow(array $mapped, int $year, int $rowIndex, string $sourceFile, string $sheetName): int
    {
        // We tag ALL staging entries generated from that Excel row (RUN_BAL + txns)
        $notes = $this->notesKey($sheetName, $rowIndex);

        // Update the rows from that rowIndex to system OPENING_SNAPSHOT
        // (We keep everything; we just label it so downstream logic can treat it appropriately.)
        return DB::table('kass_staging_contributions')
            ->where('adm_no', $mapped['adm_no'])
            ->where('company', $mapped['comp'])
            ->where('year', $year)
            ->where('notes', $notes)
            ->where('source_file', $sourceFile)
            ->update([
                'raw_row_json' => DB::raw("JSON_SET(COALESCE(raw_row_json, '{}'), '$.system', 'OPENING_SNAPSHOT', '$.opening_reason', 'CONSECUTIVE_JAN_DETECTED')"),
                'updated_at'   => now(),
            ]);
    }

    /**
     * Detect header row by scanning top rows for required tokens.
     */
    private function detectHeaderRow($sheet, int $highestRow, int $highestColIndex): ?int
    {
        $scanLimit = min($highestRow, 40);
        for ($r = 1; $r <= $scanLimit; $r++) {
            $rowText = '';
            for ($c = 1; $c <= $highestColIndex; $c++) {
                $rowText .= ' ' . strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getFormattedValue()));
            }
            // Must include NAME and MONTH and YEAR
            if (str_contains($rowText, 'NAME') && str_contains($rowText, 'MONTH') && str_contains($rowText, 'YEAR')) {
                return $r;
            }
        }
        return null;
    }

    private function notesKey(string $sheetName, int $rowIndex): string
    {
        return 'SHEET:' . $sheetName . '|ROW:' . $rowIndex;
    }

    /**
     * Normalize headers.
     */
    private function normHeader(string $h): string
    {
        $h = strtoupper(trim($h));
        $h = preg_replace('/\s+/', ' ', $h);
        return $h;
    }

    /**
     * Find a header index by candidates.
     */
    private function findHeaderIndex(array $headerNorm, array $candidates): ?int
    {
        foreach ($headerNorm as $idx => $val) {
            foreach ($candidates as $c) {
                if ($val === strtoupper($c)) {
                    return (int)$idx;
                }
            }
        }
        // Also allow "contains" match for messy headers
        foreach ($headerNorm as $idx => $val) {
            foreach ($candidates as $c) {
                $c = strtoupper($c);
                if ($c !== '' && str_contains($val, $c)) {
                    return (int)$idx;
                }
            }
        }
        return null;
    }

    /**
     * Determine which columns represent contribution channels.
     * We exclude identity + period + runbal columns and keep the rest with usable headers.
     */
    private function getTransactionColumnIndexes(
        array $headerNorm,
        ?int $idxName, ?int $idxAdm, ?int $idxComp, ?int $idxYear, ?int $idxMonth, ?int $idxRunBal
    ): array {
        $exclude = array_filter([$idxName, $idxAdm, $idxComp, $idxYear, $idxMonth, $idxRunBal], fn($v) => $v !== null);

        $out = [];
        foreach ($headerNorm as $idx => $h) {
            if (in_array($idx, $exclude, true)) {
                continue;
            }
            if (trim($h) === '') {
                continue;
            }
            // Keep columns that look like contribution channels
            // (CR, DR, CAPITAL, WELFARE, MEMB, etc.)
            $out[] = (int)$idx;
        }
        return $out;
    }

    /**
     * Map member identity.
     * If company missing, derive from filename prefix (e.g., kass_uploads/ADTEL 2015.xls -> ADTEL).
     */
    private function mapMemberIdentity(string $rawName, ?string $adm, ?string $comp, string $sourceFile): array
    {
        $name = strtoupper(trim($rawName));
        $admNo = $adm !== null ? trim((string)$adm) : null;
        $company = $comp !== null ? strtoupper(trim((string)$comp)) : null;

        if ($company === null || $company === '') {
            $company = $this->companyFromFilename($sourceFile) ?: 'UNKNOWN';
        }

        // Normalize ADM (digits only when possible, but keep original if mixed)
        if ($admNo !== null) {
            $admNo = trim($admNo);
            $admNo = preg_replace('/\s+/', '', $admNo);
        }

        return [
            'name'   => $name,
            'adm_no' => $admNo ?: null,
            'comp'   => $company,
        ];
    }

    private function memberKey(array $mapped): string
    {
        if (!empty($mapped['adm_no'])) {
            return 'ADM|' . $mapped['adm_no'] . '|' . $mapped['comp'];
        }
        return 'NAME|' . $mapped['name'] . '|' . $mapped['comp'];
    }

    private function companyFromFilename(string $sourceFile): ?string
    {
        // kass_uploads/ADTEL 2015.xls -> ADTEL
        $base = trim((string)basename($sourceFile));
        $base = preg_replace('/\.[^.]+$/', '', $base);
        // Strip trailing year and anything after it
        $base = preg_replace('/\s+[0-9]{4}.*$/', '', $base);
        $base = strtoupper(trim($base));
        return $base !== '' ? $base : null;
    }

    /**
     * Map contribution column header into raw_type.
     * Keep this comprehensive and stable.
     */
    private function mapContributionType(string $headerLabel): ?string
    {
        $h = strtoupper(trim($headerLabel));
        $h = preg_replace('/\s+/', ' ', $h);

        if ($h === '') return null;

        // Core deposit movement
        if ($h === 'CR' || str_contains($h, ' CREDIT')) return 'CR';
        if ($h === 'DR' || str_contains($h, ' DEBIT')) return 'DR';

        // Running balance is stored separately as RUN_BAL
        if (str_contains($h, 'RUN') && str_contains($h, 'BAL')) return null;

        // Capital variants
        if ($h === 'CAPITAL' || str_contains($h, 'CAPITAL')) return 'CAPITAL';
        if (str_contains($h, 'N/CAP') || str_contains($h, 'N/CAPITAL') || str_contains($h, 'NEW CAPITAL')) return 'N_CAPITAL';

        // Member / registration / welfare variations (keep as-is for reporting)
        if (str_contains($h, 'WELFARE')) return 'WELFARE';
        if (str_contains($h, 'WEL/REG') || (str_contains($h, 'WEL') && str_contains($h, 'REG'))) return 'WEL_REG';
        if ($h === 'MEMB' || str_contains($h, 'MEMB')) return 'MEMB';
        if (str_contains($h, 'REG')) return 'REG';

        // If it’s some other known SACCO column you want to preserve, keep it:
        // Default: preserve header as a sanitized type token (audit-friendly).
        // This ensures we do not “lose” columns you didn’t list yet.
        $type = preg_replace('/[^A-Z0-9]+/', '_', $h);
        $type = trim($type, '_');
        if ($type === '') return null;

        // Hard cap for DB safety
        if (strlen($type) > 50) {
            $type = substr($type, 0, 50);
        }

        return $type;
    }

    private function safeInt($val): ?int
    {
        $v = trim((string)$val);
        if ($v === '') return null;
        if (!preg_match('/^\d{4}$/', $v)) return null;
        return (int)$v;
    }

    private function safeMonth($val): ?string
    {
        $v = strtoupper(trim((string)$val));
        $v = preg_replace('/\s+/', '', $v);

        // Handle common month formats
        $map = [
            'JAN' => 'JAN', 'JANUARY' => 'JAN',
            'FEB' => 'FEB', 'FEBRUARY' => 'FEB',
            'MAR' => 'MAR', 'MARCH' => 'MAR',
            'APR' => 'APR', 'APRIL' => 'APR',
            'MAY' => 'MAY',
            'JUN' => 'JUN', 'JUNE' => 'JUN',
            'JUL' => 'JUL', 'JULY' => 'JUL',
            'AUG' => 'AUG', 'AUGUST' => 'AUG',
            'SEP' => 'SEP', 'SEPT' => 'SEP', 'SEPTEMBER' => 'SEP',
            'OCT' => 'OCT', 'OCTOBER' => 'OCT',
            'NOV' => 'NOV', 'NOVEMBER' => 'NOV',
            'DEC' => 'DEC', 'DECEMBER' => 'DEC',
        ];

        return $map[$v] ?? null;
    }

    /**
     * Numeric parser (handles commas, blanks, etc.)
     */
    private function num($val): ?float
    {
        if ($val === null) return null;

        $v = trim((string)$val);
        if ($v === '') return null;

        // remove commas
        $v = str_replace(',', '', $v);

        // If it’s not numeric, return null
        if (!is_numeric($v)) {
            return null;
        }

        return (float)$v;
    }
}
