<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KassMigrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /*--------------------------------------------------------------
     | INDEX
     --------------------------------------------------------------*/
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

    /*--------------------------------------------------------------
     | UPLOADS
     --------------------------------------------------------------*/
    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx',
        ]);

        $request->file('file')->store('kass_uploads');

        return back()->with('success', "File uploaded successfully.");
    }

    public function uploadBatch(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|mimes:zip',
        ]);

        $zipPath   = $request->file('zip_file')->store('kass_zips');
        $extractTo = storage_path('app/kass_batch_' . time());
        mkdir($extractTo, 0775, true);

        $zip = new \ZipArchive;
        if ($zip->open(storage_path('app/' . $zipPath)) === true) {
            $zip->extractTo($extractTo);
            $zip->close();
        }

        return back()->with('success', "ZIP extracted. Files are ready.");
    }

    /*--------------------------------------------------------------
     | PROCESS SINGLE FILE (from UI "Start Import into Staging")
     --------------------------------------------------------------*/
    public function process(Request $request)
    {
        $filePath = $request->input('file_path');

        if (!$filePath || !Storage::exists($filePath)) {
            return back()->with('error', 'File not found.');
        }

        try {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (in_array($ext, ['xls', 'xlsx'])) {
                $this->processExcel($filePath);
            } else {
                $this->processCsv($filePath);
            }
        } catch (\Exception $e) {
            return back()->with('error', "Processing error: " . $e->getMessage());
        }

        return back()->with('success', "Processing complete.");
    }

    /*--------------------------------------------------------------
     | PROCESS ALL FILES BUTTON (imports everything in kass_uploads)
     --------------------------------------------------------------*/
    public function processAll()
    {
        $files = Storage::files('kass_uploads');

        if (count($files) === 0) {
            return back()->with('error', 'No files found in kass_uploads.');
        }

        $summary = [];

        foreach ($files as $filePath) {
            try {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                if (in_array($ext, ['xls', 'xlsx'])) {
                    $this->processExcel($filePath);
                } else {
                    $this->processCsv($filePath);
                }

                $summary[] = ['file' => $filePath, 'status' => 'IMPORTED'];
            } catch (\Exception $e) {
                $summary[] = ['file' => $filePath, 'status' => 'FAILED: ' . $e->getMessage()];
                Log::error("KASS MIGRATION FAILED for {$filePath}: " . $e->getMessage());
            }
        }

        return back()
            ->with('success', 'Batch import completed.')
            ->with('summary', $summary);
    }

    /*--------------------------------------------------------------
     | EXCEL HANDLING
     |  - SHAREMPA  → contributions
     |  - LOANMPA   → loans      (ONLY this loan sheet name is valid)
     |  - Missing sheets are logged
     --------------------------------------------------------------*/
    private function processExcel(string $filePath): void
    {
        $fullPath    = storage_path("app/{$filePath}");
        $spreadsheet = IOFactory::load($fullPath);

        $shareIndex = $this->findSheetIndex($spreadsheet, 'SHAREMPA');
        $loanIndex  = $this->findSheetIndex($spreadsheet, 'LOANMPA');

        // Contributions
        if ($shareIndex !== null) {
            $this->processContributionSheet($spreadsheet->getSheet($shareIndex), $filePath);
        } else {
            $this->logMissingSheet($filePath, 'SHAREMPA');
        }

        // Loans
        if ($loanIndex !== null) {
            $this->processLoanSheet($spreadsheet->getSheet($loanIndex), $filePath);
        } else {
            $this->logMissingSheet($filePath, 'LOANMPA');
        }
    }

    private function findSheetIndex($spreadsheet, string $targetName): ?int
    {
        $names = $spreadsheet->getSheetNames();
        foreach ($names as $index => $name) {
            if (strtoupper(trim($name)) === strtoupper($targetName)) {
                return $index;
            }
        }

        return null;
    }

    private function logMissingSheet(string $filePath, string $sheetName): void
    {
        $line = sprintf(
            "[%s] File '%s' is missing sheet '%s'\n",
            now()->toDateTimeString(),
            $filePath,
            $sheetName
        );

        Storage::append('logs/kass_missing_sheets.log', $line);
        Log::warning(trim($line));
    }

    /*--------------------------------------------------------------
     | CONTRIBUTIONS (SHAREMPA)
     |  - carry down NAME/ADM NO/COMP
     |  - MEMB = membership fee
     |  - DR/CR = deposit debits/credits
     |  - amount = DR (if present) else MEMB else CR
     --------------------------------------------------------------*/
    private function processContributionSheet($sheet, string $sourceFile): void
{
    $highestRow = $sheet->getHighestRow();

    $lastName = null;
    $lastAdm  = null;
    $lastComp = null;

    for ($row = 1; $row <= $highestRow; $row++) {

        // Read exact columns (A..J)
        $data = $sheet->rangeToArray("A{$row}:J{$row}", null, true, true, true)[0];

        // Skip rows with no money and no dates and no name
        if ($this->rowIsEmpty($data)) continue;

        // Extract + carry-forward
        $name = trim($data['B'] ?? '');
        $adm  = trim($data['C'] ?? '');
        $comp = trim($data['D'] ?? '');

        if ($name !== '') $lastName = $name;
        if ($adm  !== '') $lastAdm  = $adm;
        if ($comp !== '') $lastComp = $comp;

        $year  = trim($data['E'] ?? '');
        $month = trim($data['F'] ?? '');

        // Money fields
        $memb = $this->numOrNull($data['G'] ?? null);
        $dr   = $this->numOrNull($data['H'] ?? null);
        $cr   = $this->numOrNull($data['I'] ?? null);
        $run  = $this->numOrNull($data['J'] ?? null);

        // Ignore useless rows
        if ($year === '' && $month === '' && $memb === null && $dr === null && $cr === null) {
            continue;
        }

        // Determine amount (priority: DR → MEMB → CR)
        $amount = $dr ?? $memb ?? $cr ?? 0;

        DB::table('kass_staging_contributions')->insert([
            'raw_name'          => $lastName,
            'member_identifier' => null,
            'adm_no'            => $lastAdm,
            'company'           => $lastComp,
            'year'              => $year,
            'month'             => $month,
            'raw_type'          => $this->detectContributionTypeSmart($memb, $dr, $cr),
            'amount'            => $amount,
            'source_file'       => $sourceFile,
            'raw_row_json'      => json_encode($data),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }
}

private function rowIsEmpty($data): bool
{
    foreach ($data as $v) {
        if (trim((string)$v) !== '') return false;
    }
    return true;
}


    private function hasAnyMoney(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $this->numOrNull($row[$key]) !== null) {
                return true;
            }
        }
        return false;
    }

    private function numOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // handle "20,000" style
        $clean = str_replace([',', ' '], '', (string)$value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function detectContributionTypeSmart($membership, $dr, $cr): string
    {
        if ($membership !== null && $membership != 0) {
            return 'MEMBERSHIP';
        }

        if ($dr !== null && $dr != 0 && ($cr === null || $cr == 0)) {
            return 'DEPOSIT_DR';
        }

        if ($cr !== null && $cr != 0 && ($dr === null || $dr == 0)) {
            return 'DEPOSIT_CR';
        }

        if ($dr !== null && $cr !== null && ($dr != 0 || $cr != 0)) {
            return 'DR_CR_MIXED';
        }

        return 'UNKNOWN';
    }

    /*--------------------------------------------------------------
     | LOANS (LOANMPA)
     |  - carry down NAME / ADM NO / COMP like contributions
     |  - leave loan maths for later processing
     --------------------------------------------------------------*/
    private function processLoanSheet($sheet, string $sourceFile): void
    {
        $rows  = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn ($r) => array_values($r), $rows);

        [$headerRaw, $startIndex] = $this->extractHeader($clean);
        $header = $this->normalizeHeaders($headerRaw);

        $lastName = null;
        $lastAdm  = null;
        $lastComp = null;

        for ($i = $startIndex; $i < count($clean); $i++) {
            $row = $clean[$i];
            if (!$this->rowHasValues($row)) {
                continue;
            }

            $data = $this->mapRow($header, $row);

            if (!empty(trim((string)($data['name'] ?? '')))) {
                $lastName = $data['name'];
            } else {
                $data['name'] = $lastName;
            }

            if (!empty(trim((string)($data['adm_no'] ?? '')))) {
                $lastAdm = $data['adm_no'];
            } else {
                $data['adm_no'] = $lastAdm;
            }

            if (!empty(trim((string)($data['comp'] ?? '')))) {
                $lastComp = $data['comp'];
            } else {
                $data['comp'] = $lastComp;
            }

            $year  = $data['year']  ?? null;
            $month = $data['month'] ?? null;

            if (empty($year) && empty($month)) {
                // many junk rows; skip those with no timing *and* no amounts
                if (!$this->hasAnyMoney($data, ['dr', 'cr', 'int'])) {
                    continue;
                }
            }

            DB::table('kass_staging_loans')->insert([
                'raw_name'            => $data['name'] ?? null,
                'member_identifier'   => $data['pfno'] ?? null,
                'adm_no'              => $data['adm_no'] ?? null,
                'company'             => $data['comp'] ?? null,
                'loan_type'           => $this->detectLoanTypeFromHeaders($header),
                'year'                => $year,
                'month'               => $month,
                'principal_disbursed' => $this->numOrNull($data['cr'] ?? null),
                'repayment_amount'    => $this->numOrNull($data['dr'] ?? null),
                'interest_amount'     => $this->numOrNull($data['int'] ?? null),
                'period_index'        => $data['period'] ?? null,
                'source_file'         => $sourceFile,
                'raw_row_json'        => json_encode($data),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    private function detectLoanTypeFromHeaders(array $header): string
    {
        $joined = implode(',', $header);

        if (str_contains($joined, 'loan_1')) return 'LOAN_1';
        if (str_contains($joined, 'loan_2')) return 'LOAN_2';
        if (str_contains($joined, 'loan_3')) return 'LOAN_3';

        return 'UNKNOWN';
    }

    /*--------------------------------------------------------------
     | CSV FALLBACK (if you ever have CSVs)
     --------------------------------------------------------------*/
    private function processCsv(string $filePath): void
    {
        $fullPath = storage_path('app/' . $filePath);
        $rows     = array_map('str_getcsv', file($fullPath));

        if (!$rows || count($rows) < 2) {
            throw new \Exception("Invalid or empty CSV file.");
        }

        $headerRaw  = null;
        $startIndex = 0;

        foreach ($rows as $i => $row) {
            if ($this->rowHasValues($row)) {
                $headerRaw  = $row;
                $startIndex = $i + 1;
                break;
            }
        }

        if (!$headerRaw) {
            throw new \Exception("Header not found in CSV.");
        }

        $header    = $this->normalizeHeaders($headerRaw);
        $isSavings = $this->isSavingsHeader($header);

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!$this->rowHasValues($row)) {
                continue;
            }

            $data = $this->mapRow($header, $row);

            if ($isSavings) {
                DB::table('kass_staging_contributions')->insert([
                    'raw_name'          => $data['name'] ?? null,
                    'member_identifier' => $data['pfno'] ?? null,
                    'adm_no'            => $data['adm_no'] ?? null,
                    'company'           => $data['comp'] ?? null,
                    'year'              => $data['year'] ?? null,
                    'month'             => $data['month'] ?? null,
                    'raw_type'          => $this->detectContributionType($data),
                    'amount'            => $this->extractNumericAmount($data),
                    'source_file'       => $filePath,
                    'raw_row_json'      => json_encode($data),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            } else {
                DB::table('kass_staging_loans')->insert([
                    'raw_name'            => $data['name'] ?? null,
                    'member_identifier'   => $data['pfno'] ?? null,
                    'adm_no'              => $data['adm_no'] ?? null,
                    'company'             => $data['comp'] ?? null,
                    'loan_type'           => $this->detectLoanTypeFromHeaders($header),
                    'year'                => $data['year'] ?? null,
                    'month'               => $data['month'] ?? null,
                    'principal_disbursed' => $this->numOrNull($data['cr'] ?? null),
                    'repayment_amount'    => $this->numOrNull($data['dr'] ?? null),
                    'interest_amount'     => $this->numOrNull($data['int'] ?? null),
                    'period_index'        => $data['period'] ?? null,
                    'source_file'         => $filePath,
                    'raw_row_json'        => json_encode($data),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }
    }

    private function isSavingsHeader(array $header): bool
    {
        $cols = ['capital', 'memb', 'welfare', 'wel_reg', 'new_wel', 'kasico'];
        return count(array_intersect($cols, $header)) > 0;
    }

    private function detectContributionType(array $row): string
    {
        $fields = ['capital', 'n_capital', 'kasico', 'new_wel', 'welfare', 'wel_reg', 'memb', 'membership'];

        foreach ($fields as $f) {
            if (isset($row[$f]) && is_numeric($row[$f]) && floatval($row[$f]) != 0) {
                return strtoupper($f);
            }
        }

        return 'UNKNOWN';
    }

    private function extractNumericAmount(array $row): float
    {
        foreach ($row as $v) {
            if (is_numeric($v)) {
                return floatval($v);
            }
        }

        return 0;
    }

    /*--------------------------------------------------------------
     | COMMON HELPERS
     --------------------------------------------------------------*/
    private function extractHeader(array $rows): array
    {
        foreach ($rows as $i => $row) {
            $upper = array_map('strtoupper', $row);
            if ($this->rowHasValues($row) && in_array('NAME', $upper)) {
                return [$row, $i + 1];
            }
        }

        foreach ($rows as $i => $row) {
            if ($this->rowHasValues($row)) {
                return [$row, $i + 1];
            }
        }

        throw new \Exception("Header not found.");
    }

    private function rowHasValues(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }
        return false;
    }

    private function normalizeHeaders($header): array
    {
        return array_map(
            fn ($h) => strtolower(str_replace([' ', '/', '-', '.', "\t"], '_', trim($h))),
            $header
        );
    }

    private function mapRow(array $header, array $row): array
    {
        $mapped = [];
        foreach ($header as $i => $col) {
            $mapped[$col] = $row[$i] ?? null;
        }
        return $mapped;
    }

    /*--------------------------------------------------------------
     | STAGING VIEWS / CLEAR
     --------------------------------------------------------------*/
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
}
