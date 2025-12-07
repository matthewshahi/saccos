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

    /**
     * Main screen
     */
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

    /**
     * Upload single CSV/XLS/XLSX
     */
    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx',
        ]);

        $request->file('file')->store('kass_uploads');

        return back()->with('success', "File uploaded successfully.");
    }

    /**
     * Upload ZIP of many files
     */
    public function uploadBatch(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|mimes:zip',
        ]);

        $zipPath   = $request->file('zip_file')->store('kass_zips');
        $extractTo = storage_path('app/kass_batch_' . time());
        mkdir($extractTo);

        $zip = new \ZipArchive;
        if ($zip->open(storage_path('app/' . $zipPath)) === true) {
            $zip->extractTo($extractTo);
            $zip->close();
        }

        return back()->with('success', "ZIP extracted. Files are ready.");
    }

    /**
     * Process a single selected file
     */
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

    /**
     * Process ALL files in kass_uploads
     */
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

                $summary[] = [
                    'file'   => $filePath,
                    'status' => 'IMPORTED',
                ];

            } catch (\Exception $e) {
                $summary[] = [
                    'file'   => $filePath,
                    'status' => 'FAILED: ' . $e->getMessage(),
                ];
                $this->logError("FAILED IMPORT for {$filePath}: " . $e->getMessage());
            }
        }

        return back()
            ->with('success', 'Batch import completed.')
            ->with('summary', $summary);
    }

    /**
     * STRICT Excel processing using sheet names:
     *   - SHAREMPA => contributions
     *   - LOANMPA  => loans
     */
    private function processExcel(string $filePath): void
    {
        $fullPath    = storage_path("app/{$filePath}");
        $spreadsheet = IOFactory::load($fullPath);

        // Build map of SHEET_NAME => sheet object (uppercased + trimmed)
        $sheetsByName = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title                      = strtoupper(trim($sheet->getTitle()));
            $sheetsByName[$title] = $sheet;
        }

        // --- CONTRIBUTIONS: SHAREMPA ---
        if (isset($sheetsByName['SHAREMPA'])) {
            $this->processContributionSheet($sheetsByName['SHAREMPA'], $filePath);
        } else {
            $this->logMissingSheet($filePath, 'CONTRIBUTION', 'SHAREMPA');
        }

        // --- LOANS: LOANMPA ---
        if (isset($sheetsByName['LOANMPA'])) {
            $this->processLoanSheet($sheetsByName['LOANMPA'], $filePath);
        } else {
            $this->logMissingSheet($filePath, 'LOAN', 'LOANMPA');
        }
    }

    /**
     * Very basic CSV handler (treat as contributions only for now)
     */
    private function processCsv(string $filePath): void
    {
        $fullPath = storage_path("app/{$filePath}");

        if (!file_exists($fullPath)) {
            throw new \Exception("CSV file missing on disk.");
        }

        $rows = array_map('str_getcsv', file($fullPath));

        if (!$rows || count($rows) < 2) {
            $this->logMissingSheet($filePath, 'CSV', 'HEADER/ROWS');
            return;
        }

        [$headerRaw, $startIndex] = $this->extractHeader($rows);
        $header = $this->normalizeHeaders($headerRaw);

        foreach (array_slice($rows, $startIndex) as $row) {
            if (!$this->rowHasValues($row)) {
                continue;
            }

            $data = $this->mapRow($header, $row);

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
        }
    }

    /**
     * Extract header row (must contain 'NAME')
     */
    private function extractHeader(array $rows): array
    {
        foreach ($rows as $i => $row) {
            $upper = array_map('strtoupper', $row);
            if ($this->rowHasValues($row) && in_array('NAME', $upper)) {
                return [$row, $i + 1];
            }
        }

        // fallback to first non-empty row
        foreach ($rows as $i => $row) {
            if ($this->rowHasValues($row)) {
                return [$row, $i + 1];
            }
        }

        throw new \Exception("Header not found.");
    }

    /**
     * Contributions sheet (SHAREMPA)
     */
    private function processContributionSheet($sheet, string $sourceFile): void
    {
        $rows  = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        [$headerRaw, $startIndex] = $this->extractHeader($clean);
        $header                   = $this->normalizeHeaders($headerRaw);

        foreach (array_slice($clean, $startIndex) as $row) {
            if (!$this->rowHasValues($row)) {
                continue;
            }

            $data = $this->mapRow($header, $row);

            DB::table('kass_staging_contributions')->insert([
                'raw_name'          => $data['name'] ?? null,
                'member_identifier' => $data['pfno'] ?? null,
                'adm_no'            => $data['adm_no'] ?? null,
                'company'           => $data['comp'] ?? null,
                'year'              => $data['year'] ?? null,
                'month'             => $data['month'] ?? null,
                'raw_type'          => $this->detectContributionType($data),
                'amount'            => $this->extractNumericAmount($data),
                'source_file'       => $sourceFile,
                'raw_row_json'      => json_encode($data),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    /**
     * Loans sheet (LOANMPA)
     */
    private function processLoanSheet($sheet, string $sourceFile): void
    {
        $rows  = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        [$headerRaw, $startIndex] = $this->extractHeader($clean);
        $header                   = $this->normalizeHeaders($headerRaw);

        foreach (array_slice($clean, $startIndex) as $row) {
            if (!$this->rowHasValues($row)) {
                continue;
            }

            $data = $this->mapRow($header, $row);

            DB::table('kass_staging_loans')->insert([
                'raw_name'          => $data['name'] ?? null,
                'member_identifier' => $data['pfno'] ?? null,
                'adm_no'            => $data['adm_no'] ?? null,
                'company'           => $data['comp'] ?? null,
                // for now loan_type = sheet name (only LOANMPA)
                'loan_type'         => 'LOANMPA',
                'year'              => $data['year'] ?? null,
                'month'             => $data['month'] ?? null,
                'principal_disbursed' => $data['cr'] ?? null,
                'repayment_amount'    => $data['dr'] ?? null,
                'interest_amount'     => $data['int'] ?? null,
                'period_index'        => $data['period'] ?? null,
                'source_file'         => $sourceFile,
                'raw_row_json'        => json_encode($data),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    private function rowHasValues($row): bool
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
            fn($h) => strtolower(str_replace([' ', '/', '-', '.', "\t"], '_', trim($h))),
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

    /**
     * Contribution category
     */
    private function detectContributionType(array $row): string
    {
        $fields = ['capital', 'n_capital', 'kasico', 'new_wel', 'welfare', 'wel_reg', 'memb'];

        foreach ($fields as $f) {
            if (isset($row[$f]) && is_numeric($row[$f]) && floatval($row[$f]) != 0) {
                return strtoupper($f);
            }
        }
        return 'UNKNOWN';
    }

    /**
     * First numeric value in row
     */
    private function extractNumericAmount(array $row): float
    {
        foreach ($row as $v) {
            if (is_numeric($v)) {
                return floatval($v);
            }
        }
        return 0.0;
    }

    /**
     * Simple file-based logging for missing sheets / errors
     */
    private function logMissingSheet(string $filePath, string $type, string $expected): void
    {
        $line = sprintf(
            "[%s] MISSING %s SHEET in file '%s'. Expected sheet name: %s\n",
            now()->toDateTimeString(),
            strtoupper($type),
            $filePath,
            $expected
        );

        file_put_contents(
            storage_path('logs/kass_migration_missing_sheets.log'),
            $line,
            FILE_APPEND
        );
    }

    private function logError(string $message): void
    {
        $line = sprintf(
            "[%s] ERROR: %s\n",
            now()->toDateTimeString(),
            $message
        );

        file_put_contents(
            storage_path('logs/kass_migration_missing_sheets.log'),
            $line,
            FILE_APPEND
        );
    }

    /**
     * Staging views
     */
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
