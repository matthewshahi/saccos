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
     * Upload CSV or Excel
     */
    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx',
        ]);

        $path = $request->file('file')->store('kass_uploads');

        return back()->with('success', "File uploaded successfully.");
    }

    /**
     * Upload ZIP file
     */
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

        return back()->with('success', "ZIP extracted. Files are ready.");
    }

    /**
     * PROCESS SELECTED FILE
     */
    public function process(Request $request)
    {
        $filePath = $request->input('file_path');

        if (!$filePath || !Storage::exists($filePath)) {
            return back()->with('error', 'File not found.');
        }

        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (in_array($extension, ['xls', 'xlsx'])) {
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
     * PROCESS EXCEL FILE (Sheet1: contributions, Sheet2: loans)
     */
    private function processExcel($filePath)
    {
        $fullPath = storage_path("app/{$filePath}");
        $spreadsheet = IOFactory::load($fullPath);

        // SHEET 1 = CONTRIBUTIONS
        if ($spreadsheet->getSheetCount() >= 1) {
            $this->processContributionSheet($spreadsheet->getSheet(0), $filePath);
        }

        // SHEET 2 = LOANS
        if ($spreadsheet->getSheetCount() >= 2) {
            $this->processLoanSheet($spreadsheet->getSheet(1), $filePath);
        }
    }

    /**
     * Extract header row
     */
    private function extractHeader($rows)
    {
        foreach ($rows as $i => $row) {

            $upper = array_map('strtoupper', $row);

            if ($this->rowHasValues($row) && in_array('NAME', $upper)) {
                return [$row, $i + 1];
            }
        }

        // fallback (first non-empty row)
        foreach ($rows as $i => $row) {
            if ($this->rowHasValues($row)) {
                return [$row, $i + 1];
            }
        }

        throw new \Exception("Header not found.");
    }

    /**
     * PROCESS SHEET 1 = CONTRIBUTIONS
     */
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

    /**
     * PROCESS SHEET 2 = LOANS
     */
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
                'raw_name'          => $data['name'] ?? null,
                'member_identifier' => $data['pfno'] ?? null,
                'adm_no'            => $data['adm_no'] ?? null,
                'company'           => $data['comp'] ?? null,
                'loan_type'         => $this->detectLoanType($header),
                'year'              => $data['year'] ?? null,
                'month'             => $data['month'] ?? null,
                'principal_disbursed' => $data['cr'] ?? null,
                'repayment_amount'    => $data['dr'] ?? null,
                'interest_amount'     => $data['int'] ?? null,
                'period_index'        => $data['period'] ?? null,
                'source_file'         => $sourceFile,
                'raw_row_json'        => json_encode($data),
                'created_at'          => now(),
                'updated_at'          => now()
            ]);
        }
    }

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

    /**
     * Detect savings category
     */
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

    /**
     * Extract first numeric value in row
     */
    private function extractNumericAmount($row)
    {
        foreach ($row as $v) {
            if (is_numeric($v)) return floatval($v);
        }
        return 0;
    }

    /**
     * Loan type detector
     */
    private function detectLoanType($header)
    {
        $str = implode(',', $header);

        if (str_contains($str, 'loan_1')) return 'LOAN_1';
        if (str_contains($str, 'loan_2')) return 'LOAN_2';
        if (str_contains($str, 'loan_3')) return 'LOAN_3';

        return 'UNKNOWN';
    }

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
    public function processAll()
{
    $files = Storage::files('kass_uploads');

    if (count($files) === 0) {
        return back()->with('error', 'No files found in kass_uploads.');
    }

    $summary = [];

    foreach ($files as $filePath) {
        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (in_array($extension, ['xls', 'xlsx'])) {
                $this->processExcel($filePath);
            } else {
                $this->parseCsv($filePath);
            }

            $summary[] = [
                'file' => $filePath,
                'status' => 'IMPORTED'
            ];

        } catch (\Exception $e) {
            $summary[] = [
                'file' => $filePath,
                'status' => 'FAILED: ' . $e->getMessage()
            ];
        }
    }

    return back()->with('success', 'Batch import completed.')
                 ->with('summary', $summary);
}

}
