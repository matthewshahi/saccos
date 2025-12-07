<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ------------------------------------------------------------
 * KASS SACCO – BASIC MIGRATION CONTROLLER (CLEAN + COMPLETE)
 * ------------------------------------------------------------
 *
 * This controller:
 *   ✔ Uploads files (CSV/XLS/XLSX)
 *   ✔ Processes a single file
 *   ✔ Processes all files in kass_uploads/
 *   ✔ Reads Sheet1 (Contributions) and Sheet2 (Loans)
 *   ✔ Extracts header dynamically
 *   ✔ Maps rows into staging tables
 *   ✔ Stores raw JSON for later processing
 *
 * NOTE: This is the "stable base version".
 *       You can now open a new thread and ask for STRICT
 *       SHAREMPA/LOANMPA formatting improvements.
 */
class KassMigrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /*--------------------------------------------------------------
     | INDEX PAGE (view uploads)
     --------------------------------------------------------------*/
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

    /*--------------------------------------------------------------
     | UPLOAD SINGLE FILE
     --------------------------------------------------------------*/
    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx',
        ]);

        $request->file('file')->store('kass_uploads');

        return back()->with('success', "File uploaded successfully.");
    }

    /*--------------------------------------------------------------
     | UPLOAD ZIP OF MANY FILES
     --------------------------------------------------------------*/
    public function uploadBatch(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|mimes:zip',
        ]);

        $zipPath = $request->file('zip_file')->store('kass_zips');
        $extractTo = storage_path('app/kass_batch_' . time());
        mkdir($extractTo);

        $zip = new \ZipArchive;
        if ($zip->open(storage_path('app/' . $zipPath)) === TRUE) {
            $zip->extractTo($extractTo);
            $zip->close();
        }

        return back()->with('success', "ZIP extracted. Files ready for processing.");
    }

    /*--------------------------------------------------------------
     | PROCESS A SELECTED FILE
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
            return back()->with('error', "Processing error: {$e->getMessage()}");
        }

        return back()->with('success', "Import completed.");
    }

    /*--------------------------------------------------------------
     | PROCESS ALL UPLOADED FILES
     --------------------------------------------------------------*/
    public function processAll()
    {
        $files = Storage::files('kass_uploads');

        if (empty($files)) {
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
            }
        }

        return back()->with('success', 'Batch import completed.')
                     ->with('summary', $summary);
    }

    /*--------------------------------------------------------------
     | PROCESS EXCEL (Sheet 1 = contributions, Sheet 2 = loans)
     --------------------------------------------------------------*/
    private function processExcel($filePath)
    {
        $fullPath = storage_path("app/{$filePath}");
        $spreadsheet = IOFactory::load($fullPath);

        // Sheet 1 = Contributions
        if ($spreadsheet->getSheetCount() >= 1) {
            $this->processContributionSheet($spreadsheet->getSheet(0), $filePath);
        }

        // Sheet 2 = Loans
        if ($spreadsheet->getSheetCount() >= 2) {
            $this->processLoanSheet($spreadsheet->getSheet(1), $filePath);
        }
    }

    /*--------------------------------------------------------------
     | EXTRACT HEADER (must contain NAME)
     --------------------------------------------------------------*/
    private function extractHeader($rows)
    {
        foreach ($rows as $i => $row) {
            $upper = array_map('strtoupper', $row);

            if ($this->rowHasValues($row) && in_array('NAME', $upper)) {
                return [$row, $i + 1];
            }
        }

        // fallback: first non-empty row
        foreach ($rows as $i => $row) {
            if ($this->rowHasValues($row)) {
                return [$row, $i + 1];
            }
        }

        throw new \Exception("Header not found in sheet.");
    }

    /*--------------------------------------------------------------
     | PROCESS CONTRIBUTIONS SHEET
     --------------------------------------------------------------*/
    private function processContributionSheet($sheet, $sourceFile)
    {
        $rows = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        [$headerRaw, $startIndex] = $this->extractHeader($clean);
        $header = $this->normalizeHeaders($headerRaw);

        foreach (array_slice($clean, $startIndex) as $row) {

            if (!$this->rowHasValues($row)) continue;

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
                'updated_at'        => now()
            ]);
        }
    }

    /*--------------------------------------------------------------
     | PROCESS LOAN SHEET
     --------------------------------------------------------------*/
    private function processLoanSheet($sheet, $sourceFile)
    {
        $rows = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        [$headerRaw, $startIndex] = $this->extractHeader($clean);
        $header = $this->normalizeHeaders($headerRaw);

        foreach (array_slice($clean, $startIndex) as $row) {

            if (!$this->rowHasValues($row)) continue;

            $data = $this->mapRow($header, $row);

            DB::table('kass_staging_loans')->insert([
                'raw_name'             => $data['name'] ?? null,
                'member_identifier'    => $data['pfno'] ?? null,
                'adm_no'               => $data['adm_no'] ?? null,
                'company'              => $data['comp'] ?? null,
                'loan_type'            => $this->detectLoanType($header),
                'year'                 => $data['year'] ?? null,
                'month'                => $data['month'] ?? null,
                'principal_disbursed'  => $data['cr'] ?? null,
                'repayment_amount'     => $data['dr'] ?? null,
                'interest_amount'      => $data['int'] ?? null,
                'period_index'         => $data['period'] ?? null,
                'source_file'          => $sourceFile,
                'raw_row_json'         => json_encode($data),
                'created_at'           => now(),
                'updated_at'           => now()
            ]);
        }
    }

    /*--------------------------------------------------------------
     | CSV PROCESSOR (simple fallback)
     --------------------------------------------------------------*/
    private function processCsv($filePath)
    {
        $fullPath = storage_path("app/{$filePath}");
        $rows = array_map('str_getcsv', file($fullPath));

        if (!$rows || count($rows) < 2) {
            throw new \Exception("CSV file appears empty.");
        }

        [$headerRaw, $startIndex] = $this->extractHeader($rows);
        $header = $this->normalizeHeaders($headerRaw);

        foreach (array_slice($rows, $startIndex) as $row) {

            if (!$this->rowHasValues($row)) continue;

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
                'updated_at'        => now()
            ]);
        }
    }

    /*--------------------------------------------------------------
     | HELPERS
     --------------------------------------------------------------*/
    private function rowHasValues($row)
    {
        foreach ($row as $cell) {
            if (trim((string)$cell) !== "") return true;
        }
        return false;
    }

    private function normalizeHeaders($header)
    {
        return array_map(fn($h) =>
            strtolower(str_replace([' ', '/', '-', '.', "\t"], '_', trim($h))),
            $header
        );
    }

    private function mapRow($header, $row)
    {
        $mapped = [];
        foreach ($header as $i => $col) {
            $mapped[$col] = $row[$i] ?? null;
        }
        return $mapped;
    }

    private function detectContributionType($row)
    {
        $fields = ['capital', 'n_capital', 'kasico', 'new_wel', 'welfare', 'wel_reg', 'memb'];

        foreach ($fields as $f) {
            if (isset($row[$f]) && is_numeric($row[$f]) && floatval($row[$f]) != 0) {
                return strtoupper($f);
            }
        }
        return 'UNKNOWN';
    }

    private function extractNumericAmount($row)
    {
        foreach ($row as $v) {
            if (is_numeric($v)) return floatval($v);
        }
        return 0;
    }

    private function detectLoanType($header)
    {
        $str = implode(',', $header);

        if (str_contains($str, 'loan_1')) return 'LOAN_1';
        if (str_contains($str, 'loan_2')) return 'LOAN_2';
        if (str_contains($str, 'loan_3')) return 'LOAN_3';

        return 'UNKNOWN';
    }

    /*--------------------------------------------------------------
     | VIEW STAGING TABLES
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
