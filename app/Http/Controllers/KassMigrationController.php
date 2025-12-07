<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KassMigrationController extends Controller
{
    public function index()
    {
        $files = collect(Storage::files('kass_uploads'))
        ->sortByDesc(function ($f) {
            return Storage::lastModified($f);
        })
        ->take(10)   // latest 10
        ->map(function ($f) {
            return [
                'name' => basename($f),
                'path' => $f,
                'time' => date('Y-m-d H:i:s', Storage::lastModified($f)),
            ];
        });

    return view('kass.index', compact('files'));

   
    }

    /**
     * Upload Single CSV File
     */
    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $path = $request->file('file')->store('kass_uploads');

        return back()->with('success', "File uploaded successfully.")
                     ->with('path', $path);
    }

    /**
     * Upload ZIP containing many files
     */
    public function uploadBatch(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|mimes:zip',
        ]);

        $zipPath = $request->file('zip_file')->store('kass_zips');

        // Extract
        $extractTo = storage_path('app/kass_batch_' . time());
        mkdir($extractTo);

        $zip = new \ZipArchive;
        if ($zip->open(storage_path('app/' . $zipPath)) === TRUE) {
            $zip->extractTo($extractTo);
            $zip->close();
        }

        return back()->with('success', 'ZIP extracted.')
                     ->with('folder', $extractTo);
    }

    /**
     * PROCESS FILE — MAIN ENTRY
     */
    public function process(Request $request)
    {
        $filePath = $request->input('file_path');

        if (!$filePath || !Storage::exists($filePath)) {
            return back()->with('error', 'File not found or missing path.');
        }

        try {
            $this->parseFile($filePath);
        } catch (\Exception $e) {
            return back()->with('error', "Error: " . $e->getMessage());
        }

        return back()->with('success', 'Processing complete.');
    }

    /**
     * Parse CSV file and insert into staging tables
     */
    private function parseFile($filePath)
    {
        $fullPath = storage_path('app/' . $filePath);

        $rows = array_map('str_getcsv', file($fullPath));
        if (!$rows || count($rows) < 2) {
            throw new \Exception("Invalid or empty CSV file.");
        }

        $headerRaw = array_shift($rows);
        $header = $this->normalizeHeaders($headerRaw);

        $isSavings = $this->isSavingsSheet($header);
        $isLoans = !$isSavings;

        foreach ($rows as $row) {
            $data = $this->mapRow($header, $row);

            if ($isSavings) {
                $this->insertSavings($data, $filePath);
            } else {
                $this->insertLoan($data, $filePath);
            }
        }
    }

    /**
     * Normalize Header Keys
     */
    private function normalizeHeaders($header)
    {
        return array_map(function ($h) {
            return strtolower(str_replace([' ', '/', '-', '.', "\t"], '_', trim($h)));
        }, $header);
    }

    /**
     * Convert row array into associative array using normalized headers
     */
    private function mapRow($header, $row)
    {
        $assoc = [];
        foreach ($header as $i => $col) {
            $assoc[$col] = $row[$i] ?? null;
        }
        return $assoc;
    }

    /**
     * SAVINGS DETECTOR
     */
    private function isSavingsSheet($header)
    {
        $possibleSavingsCols = [
            'capital', 'memb', 'welfare', 'wel_reg', 'new_wel', 'kasico'
        ];

        foreach ($possibleSavingsCols as $col) {
            if (in_array($col, $header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * INSERT SAVINGS ROW
     */
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

    /**
     * Determine Savings Category
     */
    private function detectContributionType($row)
    {
        $fields = ['capital', 'memb', 'welfare', 'new_wel', 'wel_reg', 'kasico'];

        foreach ($fields as $f) {
            if (isset($row[$f]) && $row[$f] !== "" && $row[$f] != 0) {
                return strtoupper($f);
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Extract numeric amount from a row
     */
    private function extractAmount($row)
    {
        foreach ($row as $v) {
            if (is_numeric($v)) return floatval($v);
        }
        return 0;
    }

    /**
     * INSERT LOAN ROW
     */
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

    /**
     * Detect Loan Type
     */
    private function detectLoanType($row)
    {
        if (isset($row['loan_1'])) return 'LOAN_1';
        if (isset($row['loan_2'])) return 'LOAN_2';
        return 'UNKNOWN';
    }

    /**
     * View Data in Staging Tables
     */
    public function staging()
    {
        $contrib = DB::table('kass_staging_contributions')->paginate(50);
        $loans = DB::table('kass_staging_loans')->paginate(50);

        return view('kass.staging', compact('contrib', 'loans'));
    }

    /**
     * Clear staging tables
     */
    public function clearStaging()
    {
        DB::table('kass_staging_contributions')->truncate();
        DB::table('kass_staging_loans')->truncate();

        return back()->with('success', 'Staging tables cleared.');
    }
}
