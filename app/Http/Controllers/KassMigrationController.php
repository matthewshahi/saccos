<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KassMigrationController extends Controller
{
    public function index()
    {
        $files = collect(Storage::files('kass_uploads'))
            ->sortByDesc(fn($f) => Storage::lastModified($f))
            ->take(10)
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

        return back()
            ->with('success', "File uploaded successfully.")
            ->with('path', $path);
    }

    /**
     * Upload ZIP
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

        return back()
            ->with('success', 'ZIP extracted.')
            ->with('folder', $extractTo);
    }

    /**
     * Main process entry
     */
    public function process(Request $request)
    {
        $filePath = $request->input('file_path');

        if (!$filePath || !Storage::exists($filePath)) {
            return back()->with('error', 'File not found or missing path.');
        }

        try {

            // Detect file type
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (in_array($extension, ['xls', 'xlsx'])) {
                $this->processExcel($filePath);
            } else {
                $this->parseCsv($filePath);
            }

        } catch (\Exception $e) {
            return back()->with('error', "Error: " . $e->getMessage());
        }

        return back()->with('success', 'Processing complete.');
    }

    /**
     * PROCESS EXCEL FILE (supports multi-sheet)
     */
    private function processExcel($filePath)
    {
        $fullPath = storage_path('app/' . $filePath);

        $spreadsheet = IOFactory::load($fullPath);

        // --- SHEET 1: Contributions ---
        $sheet1 = $spreadsheet->getSheet(0);
        $this->processExcelSheet($sheet1, $filePath, "contribution");

        // --- SHEET 2: Loans (if exists) ---
        if ($spreadsheet->getSheetCount() > 1) {
            $sheet2 = $spreadsheet->getSheet(1);
            $this->processExcelSheet($sheet2, $filePath, "loan");
        }
    }

    /**
     * Convert Excel sheet to array and run same logic as CSV importer
     */
    private function processExcelSheet($sheet, $sourceFile, $type)
    {
        $rows = $sheet->toArray(null, true, true, true);

        // convert keys A, B, C → 0,1,2
        $cleanRows = array_map(fn($r) => array_values($r), $rows);

        // Find first non-empty row as header
        $headerRaw = null;
        $startIndex = 0;

        foreach ($cleanRows as $i => $row) {
            if ($this->rowHasValues($row)) {
                $headerRaw = $row;
                $startIndex = $i + 1;
                break;
            }
        }

        if (!$headerRaw) {
            throw new \Exception("No valid header found in Excel sheet.");
        }

        $header = $this->normalizeHeaders($headerRaw);

        for ($i = $startIndex; $i < count($cleanRows); $i++) {
            $row = $cleanRows[$i];
            if (!$this->rowHasValues($row)) continue;

            $data = $this->mapRow($header, $row);

            if ($type === "contribution") {
                $this->insertSavings($data, $sourceFile);
            } else {
                $this->insertLoan($data, $sourceFile);
            }
        }
    }

    private function rowHasValues($row)
    {
        foreach ($row as $cell) {
            if (trim($cell) !== "") return true;
        }
        return false;
    }

    /**
     * PROCESS CSV
     */
    private function parseCsv($filePath)
    {
        $fullPath = storage_path('app/' . $filePath);

        $rows = array_map('str_getcsv', file($fullPath));

        if (!$rows || count($rows) < 2) {
            throw new \Exception("Invalid or empty CSV file.");
        }

        // Find first row with actual headers
        $headerRaw = null;
        $startIndex = 0;

        foreach ($rows as $i => $row) {
            if ($this->rowHasValues($row)) {
                $headerRaw = $row;
                $startIndex = $i + 1;
                break;
            }
        }

        $header = $this->normalizeHeaders($headerRaw);

        $isSavings = $this->isSavingsSheet($header);

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!$this->rowHasValues($row)) continue;

            $data = $this->mapRow($header, $row);

            if ($isSavings) {
                $this->insertSavings($data, $filePath);
            } else {
                $this->insertLoan($data, $filePath);
            }
        }
    }

    private function normalizeHeaders($header)
    {
        return array_map(fn($h) => strtolower(str_replace([' ', '/', '-', '.', "\t"], '_', trim($h))), $header);
    }

    private function mapRow($header, $row)
    {
        $assoc = [];
        foreach ($header as $i => $col) {
            $assoc[$col] = $row[$i] ?? null;
        }
        return $assoc;
    }

    private function isSavingsSheet($header)
    {
        $cols = ['capital', 'memb', 'welfare', 'wel_reg', 'new_wel', 'kasico'];
        return count(array_intersect($cols, $header)) > 0;
    }

    private function insertSavings($row, $source)
    {
        DB::table('kass_staging_contributions')->insert([
            'raw_name'          => $row['name'] ?? null,
            'member_identifier' => $row['pfno'] ?? null,
            'adm_no'            => $row['adm_no'] ?? null,
            'company'           => $row['comp'] ?? null,
            'year'              => $row['year'] ?? null,
            'month'             => $row['month'] ?? null,
            'raw_type'          => $this->detectContributionType($row),
            'amount'            => $this->extractAmount($row),
            'source_file'       => $source,
            'created_at'        => now(),
            'updated_at'        => now()
        ]);
    }

    private function detectContributionType($row)
    {
        foreach (['capital', 'memb', 'welfare', 'new_wel', 'wel_reg', 'kasico'] as $f) {
            if (!empty($row[$f])) return strtoupper($f);
        }
        return 'UNKNOWN';
    }

    private function extractAmount($row)
    {
        foreach ($row as $v) {
            if (is_numeric($v)) return floatval($v);
        }
        return 0;
    }

    private function insertLoan($row, $source)
    {
        DB::table('kass_staging_loans')->insert([
            'raw_name'          => $row['name'] ?? null,
            'member_identifier' => $row['pfno'] ?? null,
            'adm_no'            => $row['adm_no'] ?? null,
            'company'           => $row['comp'] ?? null,
            'loan_type'         => $this->detectLoanType($row),
            'year'              => $row['year'] ?? null,
            'month'             => $row['month'] ?? null,
            'principal_disbursed' => $row['cr'] ?? 0,
            'repayment_amount'    => $row['dr'] ?? 0,
            'interest_amount'     => $row['int'] ?? 0,
            'period_index'        => $row['period'] ?? null,
            'source_file'         => $source,
            'created_at'          => now(),
            'updated_at'          => now()
        ]);
    }

    private function detectLoanType($row)
    {
        if (!empty($row['loan_1'])) return 'LOAN_1';
        if (!empty($row['loan_2'])) return 'LOAN_2';
        return 'UNKNOWN';
    }

    public function staging()
    {
        $contrib = DB::table('kass_staging_contributions')->paginate(50);
        $loans = DB::table('kass_staging_loans')->paginate(50);

        return view('kass.staging', compact('contrib', 'loans'));
    }

    public function clearStaging()
    {
        DB::table('kass_staging_contributions')->truncate();
        DB::table('kass_staging_loans')->truncate();

        return back()->with('success', 'Staging tables cleared.');
    }
}
s