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

        return back()->with('success', "File uploaded successfully.");
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

        return back()->with('success', 'ZIP extracted.');
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
                $this->parseCsv($filePath);
            }

        } catch (\Exception $e) {
            return back()->with('error', "Error: " . $e->getMessage());
        }

        return back()->with('success', 'Processing complete.');
    }

    /**
     * PROCESS EXCEL FILE (sheet1 = contributions, sheet2 = loans)
     */
    private function processExcel($filePath)
    {
        $fullPath = storage_path('app/' . $filePath);
        $spreadsheet = IOFactory::load($fullPath);
        $sheetCount = $spreadsheet->getSheetCount();

        if ($sheetCount >= 1) {
            $this->processContributionSheet($spreadsheet->getSheet(0), $filePath);
        }

        if ($sheetCount >= 2) {
            $this->processLoanSheet($spreadsheet->getSheet(1), $filePath);
        }
    }

    /**
     * Extract real header row
     */
    private function extractHeader($rows)
    {
        foreach ($rows as $i => $row) {
            if ($this->rowHasValues($row) && in_array('NAME', array_map('strtoupper', $row))) {
                return [$row, $i + 1];
            }
        }

        // fallback
        foreach ($rows as $i => $row) {
            if ($this->rowHasValues($row)) {
                return [$row, $i + 1];
            }
        }

        throw new \Exception("No valid header found.");
    }

    /**
     * PROCESS CONTRIBUTIONS SHEET
     */
    private function processContributionSheet($sheet, $sourceFile)
    {
        $rows = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        [$headerRaw, $startIndex] = $this->extractHeader($clean);
        $header = $this->normalizeHeaders($headerRaw);

        for ($i = $startIndex; $i < count($clean); $i++) {

            $row = $clean[$i];
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
                'created_at'        => now(),
                'updated_at'        => now()
            ]);
        }
    }

    /**
     * PROCESS LOANS SHEET
     */
    private function processLoanSheet($sheet, $sourceFile)
    {
        $rows = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        [$headerRaw, $startIndex] = $this->extractHeader($clean);
        $header = $this->normalizeHeaders($headerRaw);

        for ($i = $startIndex; $i < count($clean); $i++) {

            $row = $clean[$i];
            if (!$this->rowHasValues($row)) continue;

            $data = $this->mapRow($header, $row);

            DB::table('kass_staging_loans')->insert([
                'raw_name'          => $data['name'] ?? null,
                'member_identifier' => $data['pfno'] ?? null,
                'adm_no'            => $data['adm_no'] ?? null,
                'company'           => $data['comp'] ?? null,
                'loan_type'         => $this->detectLoanTypeFromHeaders($header),
                'year'              => $data['year'] ?? null,
                'month'             => $data['month'] ?? null,
                'principal_disbursed' => $data['cr'] ?? 0,
                'repayment_amount'    => $data['dr'] ?? 0,
                'interest_amount'     => $data['int'] ?? 0,
                'period_index'        => $data['period'] ?? null,
                'source_file'         => $sourceFile,
                'created_at'          => now(),
                'updated_at'          => now()
            ]);
        }
    }

    private function rowHasValues($row)
    {
        foreach ($row as $cell) {
            if (trim($cell) !== "") return true;
        }
        return false;
    }

    private function normalizeHeaders($header)
    {
        return array_map(
            fn($h) => strtolower(str_replace([' ', '/', '-', '.', "\t"], '_', trim($h))),
            $header
        );
    }

    private function mapRow($header, $row)
    {
        $assoc = [];
        foreach ($header as $i => $col) {
            $assoc[$col] = $row[$i] ?? null;
        }
        return $assoc;
    }

    /**
     * Detect contribution category
     */
    private function detectContributionType($row)
    {
        $fields = [
            'capital', 'n_capital', 'kasico',
            'new_wel', 'welfare', 'wel_reg',
            'memb', 'membership'
        ];

        foreach ($fields as $f) {
            if (isset($row[$f]) && is_numeric($row[$f]) && $row[$f] > 0) {
                return strtoupper($f);
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Extract numeric amount from contributions
     */
    private function extractNumericAmount($row)
    {
        foreach ($row as $v) {
            if (is_numeric($v)) return floatval($v);
        }
        return 0;
    }

    /**
     * Loan type detection
     */
    private function detectLoanTypeFromHeaders($header)
    {
        $joined = implode(',', $header);

        if (str_contains($joined, 'loan_1')) return 'LOAN_1';
        if (str_contains($joined, 'loan_2')) return 'LOAN_2';
        if (str_contains($joined, 'loan_3')) return 'LOAN_3';

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
