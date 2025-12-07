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
        if ($zip->open(storage_path("app/{$zipPath}")) === TRUE) {
            $zip->extractTo($extractTo);
            $zip->close();
        }

        return back()->with('success', "ZIP extracted successfully.");
    }

    /*===========================================================
     |  PROCESS A SINGLE FILE
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
            return back()->with('error', "Error: " . $e->getMessage());
        }

        return back()->with('success', "File processed.");
    }

    /*===========================================================
     |
     |   PROCESS ALL FILES IN FOLDER
     |
     ===========================================================*/
  public function processAll()
{
    // Only pick Excel files
    $files = array_values(array_filter(Storage::files('kass_uploads'), function ($file) {
        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['xls', 'xlsx']);
    }));

    $remaining = count($files);

    if ($remaining === 0) {
        return view('kass.auto', [
            'message' => "🎉 All files processed! No remaining files.",
            'remaining' => 0,
            'next' => false
        ]);
    }

    // Process only first file
    $file = $files[0];

    try {
        $this->processExcel($file);
        Storage::delete($file);

        return view('kass.auto', [
            'message' => "✅ Processed: {$file}",
            'remaining' => $remaining - 1,
            'next' => true
        ]);
    }
    catch (\Exception $e) {

        $this->logMissingSheet($file, "PROCESS_ERROR", $e->getMessage());

        Storage::delete($file);

        return view('kass.auto', [
            'message' => "❌ Failed on {$file}: " . $e->getMessage(),
            'remaining' => $remaining - 1,
            'next' => true
        ]);
    }
}


    /*===========================================================
     |
     |   EXCEL HANDLING
     |
     |   Valid sheets ONLY:
     |      SHAREMPA => contributions (shares)
     |      LOANMPA  => loans
     |
     ===========================================================*/
    private function processExcel(string $filePath)
    {
        $spreadsheet = IOFactory::load(storage_path("app/{$filePath}"));

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[strtoupper(trim($sheet->getTitle()))] = $sheet;
        }

        // SHAREMPA
        if (isset($sheets['SHAREMPA'])) {
            $this->processContributionSheet($sheets['SHAREMPA'], $filePath);
        } else {
            $this->logMissingSheet($filePath, "CONTRIBUTION", "SHAREMPA");
        }

        // LOANMPA
        if (isset($sheets['LOANMPA'])) {
            $this->processLoanSheet($sheets['LOANMPA'], $filePath);
        } else {
            $this->logMissingSheet($filePath, "LOAN", "LOANMPA");
        }
    }

    /*===========================================================
     |
     |   SHAREMPA  —  Contributions Sheet
     |
     |   Fixes include:
     |   - Carry down NAME / ADM NO / COMPANY
     |   - MEMB = membership fee
     |   - DR/CR = deposit movement
     |   - raw JSON stored
     |
     ===========================================================*/
    private function processContributionSheet($sheet, string $sourceFile)
    {
        $rows = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        // detect header row
        [$headerRaw, $start] = $this->extractHeader($clean);
        $header = $this->normalizeHeaders($headerRaw);

        $carryName = null;
        $carryAdm  = null;
        $carryComp = null;

        for ($i = $start; $i < count($clean); $i++) {

            $row = $clean[$i];
            if (!$this->rowHasValues($row)) continue;

            $mapped = $this->mapRow($header, $row);

            // === CARRY-DOWN FIX ===
            if (!empty($mapped['name'])) $carryName = $mapped['name'];
            else $mapped['name'] = $carryName;

            if (!empty($mapped['adm_no'])) $carryAdm = $mapped['adm_no'];
            else $mapped['adm_no'] = $carryAdm;

            if (!empty($mapped['comp'])) $carryComp = $mapped['comp'];
            else $mapped['comp'] = $carryComp;

            // detect amount
            $memb = $this->num($mapped['memb'] ?? null);
            $dr   = $this->num($mapped['dr'] ?? null);
            $cr   = $this->num($mapped['cr'] ?? null);

            $amount = $dr ?? $memb ?? $cr ?? 0;

            DB::table('kass_staging_contributions')->insert([
                'raw_name'          => $mapped['name'],
                'member_identifier' => $mapped['pfno'] ?? null,
                'adm_no'            => $mapped['adm_no'],
                'company'           => $mapped['comp'],
                'year'              => $mapped['year'] ?? null,
                'month'             => $mapped['month'] ?? null,
                'raw_type'          => $this->detectContributionType($mapped),
                'amount'            => $amount,
                'source_file'       => $sourceFile,
                'raw_row_json'      => json_encode($mapped),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    private function num($v)
    {
        if ($v === null || $v === '') return null;
        $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? floatval($v) : null;
    }

    private function detectContributionType($row): string
    {
        if ($this->num($row['memb'] ?? null)) return "MEMBERSHIP";
        if ($this->num($row['dr'] ?? null)) return "DEPOSIT_DR";
        if ($this->num($row['cr'] ?? null)) return "DEPOSIT_CR";
        return "UNKNOWN";
    }

    /*===========================================================
     |
     |   LOANMPA — Loan Sheet
     |
     |   Note:
     |   - Carry down NAME / ADM / COMPANY same as contributions
     |   - We only store raw details for future processing
     |
     ===========================================================*/
    private function processLoanSheet($sheet, string $sourceFile)
    {
        $rows  = $sheet->toArray(null, true, true, true);
        $clean = array_map(fn($r) => array_values($r), $rows);

        [$headerRaw, $start] = $this->extractHeader($clean);
        $header = $this->normalizeHeaders($headerRaw);

        $carryName = null;
        $carryAdm  = null;
        $carryComp = null;

        for ($i = $start; $i < count($clean); $i++) {

            $row = $clean[$i];
            if (!$this->rowHasValues($row)) continue;

            $mapped = $this->mapRow($header, $row);

            // CARRY DOWN
            if (!empty($mapped['name'])) $carryName = $mapped['name'];
            else $mapped['name'] = $carryName;

            if (!empty($mapped['adm_no'])) $carryAdm = $mapped['adm_no'];
            else $mapped['adm_no'] = $carryAdm;

            if (!empty($mapped['comp'])) $carryComp = $mapped['comp'];
            else $mapped['comp'] = $carryComp;

            DB::table('kass_staging_loans')->insert([
                'raw_name'            => $mapped['name'],
                'member_identifier'   => $mapped['pfno'] ?? null,
                'adm_no'              => $mapped['adm_no'],
                'company'             => $mapped['comp'],
                'loan_type'           => 'LOANMPA',
                'year'                => $mapped['year'] ?? null,
                'month'               => $mapped['month'] ?? null,
                'principal_disbursed' => $this->num($mapped['cr'] ?? null),
                'repayment_amount'    => $this->num($mapped['dr'] ?? null),
                'interest_amount'     => $this->num($mapped['int'] ?? null),
                'period_index'        => $mapped['period'] ?? null,
                'source_file'         => $sourceFile,
                'raw_row_json'        => json_encode($mapped),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    /*===========================================================
     |
     |   GENERIC HELPERS
     |
     ===========================================================*/
    private function extractHeader(array $rows)
    {
        foreach ($rows as $i => $row) {
            $upper = array_map('strtoupper', $row);
            if (in_array('NAME', $upper)) {
                return [$row, $i + 1];
            }
        }
        throw new \Exception("Header row not found.");
    }

    private function rowHasValues($row)
    {
        foreach ($row as $x) {
            if (trim((string)$x) !== "") return true;
        }
        return false;
    }

    private function normalizeHeaders($row)
    {
        return array_map(
            fn($h) => strtolower(str_replace([' ', '/', '.', '-', "\t"], '_', trim($h))),
            $row
        );
    }

    private function mapRow($header, $row)
    {
        $out = [];
        foreach ($header as $i => $col) {
            $out[$col] = $row[$i] ?? null;
        }
        return $out;
    }

    private function logMissingSheet(string $file, string $type, string $note)
    {
        $line = "[" . now() . "] {$file} missing {$type} → {$note}\n";
        Storage::append('logs/kass_missing_sheets.log', $line);
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

    public function processNext()
{
    $trackerPath = storage_path('app/kass_tracker.json');

    // Load tracker file or initialize
    if (!file_exists($trackerPath)) {
        file_put_contents($trackerPath, json_encode([
            'last_file' => null,
            'status' => 'IDLE'
        ], JSON_PRETTY_PRINT));
    }

    $tracker = json_decode(file_get_contents($trackerPath), true);

    // Get all Excel files
    $files = array_values(array_filter(Storage::files('kass_uploads'), function($file) {
        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['xls', 'xlsx']);
    }));

    if (empty($files)) {
        return "No XLS/XLSX files found.";
    }

    // If last_file exists, pick the next
    $index = 0;

    if ($tracker['last_file']) {
        $index = array_search($tracker['last_file'], $files);
        if ($index === false) $index = 0;
        else $index++;
    }

    // If we've reached end of list
    if ($index >= count($files)) {
        return "All files processed.";
    }

    $fileToProcess = $files[$index];

    // Update tracker BEFORE processing
    $tracker['last_file'] = $fileToProcess;
    $tracker['status'] = "PROCESSING";
    file_put_contents($trackerPath, json_encode($tracker, JSON_PRETTY_PRINT));

    try {
        $this->processExcel($fileToProcess);

        // Mark success
        $tracker['status'] = "SUCCESS";
        file_put_contents($trackerPath, json_encode($tracker, JSON_PRETTY_PRINT));

        return "Processed: {$fileToProcess}";

    } catch (\Exception $e) {

        // Mark failure
        $tracker['status'] = "FAILED: " . $e->getMessage();
        file_put_contents($trackerPath, json_encode($tracker, JSON_PRETTY_PRINT));

        return "FAILED: " . $e->getMessage();
    }
}

}
