<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KassMigrationController extends Controller
{
    public function index()
    {
        return view('kass.index');
    }

    // Upload one CSV (Savings or Loans)
    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls',
        ]);

        $path = $request->file('file')->store('kass_uploads');

        return back()->with('success', "File uploaded: $path")->with('path', $path);
    }

    // Upload a ZIP containing many sheets
    public function uploadBatch(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|mimes:zip',
        ]);

        $zipPath = $request->file('zip_file')->store('kass_zips');

        // Extract ZIP
        $extractTo = storage_path('app/kass_batch_' . time());
        mkdir($extractTo);

        $zip = new \ZipArchive;

        if ($zip->open(storage_path('app/' . $zipPath)) === TRUE) {
            $zip->extractTo($extractTo);
            $zip->close();
        }

        return back()->with('success', "Batch extracted. Ready for processing.")
                     ->with('folder', $extractTo);
    }

    // Parse selected file OR batch folder
    public function process(Request $request)
    {
        $file = $request->input('file_path');
        $folder = $request->input('folder_path');

        if ($file) {
            $this->parseFile($file);
        }

        if ($folder) {
            foreach (glob($folder.'/*.csv') as $csv) {
                $this->parseFile($csv);
            }
        }

        return back()->with('success', 'Processing complete.');
    }

    private function parseFile($filePath)
    {
        $rows = array_map('str_getcsv', file(storage_path('app/'.$filePath)));
        $header = array_shift($rows);

        // Determine if this is savings or loans
        $isSavings = $this->isSavingsSheet($header);
        $isLoans = !$isSavings;

        foreach ($rows as $row) {
            $data = array_combine($header, $row);

            if ($isSavings) {
                $this->insertSavings($data, $filePath);
            } else {
                $this->insertLoan($data, $filePath);
            }
        }
    }

    private function isSavingsSheet($header)
    {
        return in_array('SHARES/DEPOST', $header) || in_array('MEMB', $header);
    }

    private function insertSavings($row, $source)
    {
        DB::table('kass_staging_contributions')->insert([
            'raw_name' => $row['NAME'] ?? null,
            'member_identifier' => $row['PFNO'] ?? null,
            'adm_no' => $row['ADM NO'] ?? null,
            'company' => $row['COMP'] ?? null,
            'year' => $row['YEAR'] ?? null,
            'month' => $row['MONTH'] ?? null,
            'raw_type' => $this->detectContributionType($row),
            'amount' => $this->extractAmount($row),
            'source_file' => $source,
            'notes' => null,
            'created_at' => now(),
        ]);
    }

    private function detectContributionType($row)
    {
        $types = ['CAPITAL', 'MEMB', 'WELFARE', 'NEW WEL', 'KASICO'];
        foreach ($types as $t) {
            if (!empty($row[$t])) {
                return $t;
            }
        }
        return 'UNKNOWN';
    }

    private function extractAmount($row)
    {
        foreach ($row as $k => $v) {
            if (is_numeric($v)) {
                return $v;
            }
        }
        return 0;
    }

    private function insertLoan($row, $source)
    {
        DB::table('kass_staging_loans')->insert([
            'raw_name' => $row['NAME'] ?? null,
            'member_identifier' => $row['PFNO'] ?? null,
            'adm_no' => $row['ADM NO'] ?? null,
            'company' => $row['COMP'] ?? null,
            'loan_type' => $this->detectLoanType($row),
            'year' => $row['YEAR'] ?? null,
            'month' => $row['MONTH'] ?? null,
            'principal_disbursed' => $row['CR'] ?? null,
            'repayment_amount' => $row['DR'] ?? null,
            'interest_amount' => $row['INT'] ?? null,
            'period_index' => $row['PERIOD'] ?? null,
            'source_file' => $source,
            'created_at' => now(),
        ]);
    }

    private function detectLoanType($row)
    {
        if (isset($row['LOAN 1'])) return 'LOAN_1';
        if (isset($row['LOAN 2'])) return 'LOAN_2';
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
