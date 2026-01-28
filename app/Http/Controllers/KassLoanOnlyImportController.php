<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class KassLoanOnlyImportController extends Controller
{
    public function __construct()
{
    set_time_limit(0);
    ini_set('max_execution_time', '0');
    ini_set('memory_limit', '16G'); // or '12G', '16G'
}


    public function run()
    {
        set_time_limit(0);
    ini_set('memory_limit', '16G'); // keep consistent

        $files = array_values(array_filter(
            Storage::files('kass_uploads'),
            fn($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['xls', 'xlsx'])
        ));

        foreach ($files as $file) {
            $this->processFile($file);
        }

        return response()->json([
            'status' => 'OK',
            'files_processed' => count($files)
        ]);
    }

    protected function processFile(string $file)
    {
        $fullPath = storage_path("app/{$file}");
        // $spreadsheet = IOFactory::load($fullPath);
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(false); // MUST be false (do not rely on cached results)
        $spreadsheet = $reader->load($fullPath);



        foreach ($spreadsheet->getAllSheets() as $sheet) {

            $title = strtoupper(trim($sheet->getTitle()));
            if (!str_contains($title, 'LOAN')) {
                continue;
            }

            $this->processLoanSheet($sheet, $file);
        }
    }

    // Add this helper


    protected function processLoanSheet($sheet, string $sourceFile)
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        $rows = $sheet->rangeToArray(
            "A1:{$highestCol}{$highestRow}",
            null,
            true,   // calculate formulas
            false,  // do not format
            false   // no cell refs; numeric indexes align with column offsets
        );


        // $rows = array_map(fn ($r) => array_values($r), $rows);

        $headerIndex = null;
        $labelRow    = null;

        foreach ($rows as $i => $row) {
            $upper = array_map(fn($v) => strtoupper(trim((string)$v)), $row);
            if (in_array('NAME', $upper, true)) {
                $headerIndex = $i;
                $labelRow = $i > 0 ? $rows[$i - 1] : null;
                break;
            }
        }

        if ($headerIndex === null) return;

        $headerRow = array_map(fn($v) => strtoupper(trim((string)$v)), $rows[$headerIndex]);

        $loanBlocks = $this->findLoanBlocks($headerRow, $labelRow);
        if (empty($loanBlocks)) return;

        $idxName = array_search('NAME', $headerRow, true);
        $idxAdm  = array_search('ADM NO', $headerRow, true);
        $idxComp = array_search('COMP', $headerRow, true);

        $carryName = $carryAdm = $carryComp = null;

        // OPTIONAL re-run safety (recommended)
        DB::table('kass_staging_loans')->where('source_file', $sourceFile)->delete();

        $buffer = [];
        $bufferSize = 1000;

        for ($r = $headerIndex + 1; $r < count($rows); $r++) {

            $row = $rows[$r];
            if ($this->rowIsEmpty($row)) continue;
            $rowNumber = $r + 1; // $rows index 0 == Excel row 1


            if (!empty(trim((string)($row[$idxName] ?? '')))) $carryName = trim((string)$row[$idxName]);
            if (!empty(trim((string)($row[$idxAdm] ?? ''))))  $carryAdm  = trim((string)$row[$idxAdm]);
            if (!empty(trim((string)($row[$idxComp] ?? '')))) $carryComp = trim((string)$row[$idxComp]);

            // Require ADM too
            if (!$carryName || !$carryComp || !$carryAdm) continue;

            foreach ($loanBlocks as $block) {

                $c = $block['cols'];

                $year  = $this->cleanYear($row[$c['year']] ?? null);
$month = $this->cleanMonth($row[$c['month']] ?? null);

if (!$year || !$month) continue;

$dr  = ($c['dr'] !== null)  ? $this->num($row[$c['dr']] ?? null)  : null;
$cr  = ($c['cr'] !== null)  ? $this->num($row[$c['cr']] ?? null)  : null;
$int = ($c['int'] !== null) ? $this->num($row[$c['int']] ?? null) : null;

$period = ($c['period'] !== null)
    ? $this->cleanPeriod($row[$c['period']] ?? null)
    : null;


                // Skip null OR all-zero rows
                if (
                    $this->isZeroOrNull($dr) &&
                    $this->isZeroOrNull($cr) &&
                    $this->isZeroOrNull($int)
                ) {
                    continue;
                }

                $buffer[] = [
                    'raw_name'            => $carryName,
                    'adm_no'              => $carryAdm,
                    'company'             => $carryComp,
                    'loan_type'           => $block['label'],
                    'year'                => $year,
                    'month'               => $month,
                    'outstanding_balance' => $dr,
                    'principal_paid'      => $cr,
                    'interest_paid'       => $int,
                    'period_index'        => $period,
                    'source_file'         => $sourceFile,
                    'raw_row_json'        => json_encode([
                        'raw_dr'     => $dr,
                        'raw_cr'     => $cr,
                        'raw_int'    => $int,
                        'raw_period' => $period,
                        'sheet_row'  => $r + 1,
                    ]),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];

                if (count($buffer) >= $bufferSize) {
                    DB::table('kass_staging_loans')->insert($buffer);
                    $buffer = [];
                }
            }
        }

        if (!empty($buffer)) {
            DB::table('kass_staging_loans')->insert($buffer);
        }
    }



    protected function findLoanBlocks(array $header, ?array $labelRow): array
    {
        $blocks = [];
        $max = count($header);

        // Small normalizer (handles NBSP + spacing)
        $norm = function ($v): string {
            $s = (string)$v;
            $s = str_replace("\xC2\xA0", ' ', $s); // NBSP
            $s = preg_replace('/\s+/', ' ', $s);
            return strtoupper(trim($s));
        };

        // Normalize header once so YEAR/MONTH/DR/CR/INT/PERIOD match reliably
        $header = array_map($norm, $header);

        // Normalize label row once
        $labelRowNorm = $labelRow ? array_map($norm, $labelRow) : null;

        for ($i = 0; $i < $max; $i++) {

            if ($header[$i] !== 'YEAR') continue;
            if (($header[$i + 1] ?? '') !== 'MONTH') continue;

            $cols = [
                'year'   => $i,
                'month'  => $i + 1,
                'dr'     => null,
                'cr'     => null,
                'int'    => null,
                'period' => null,
            ];

            for ($j = $i + 2; $j <= $i + 7 && $j < $max; $j++) {
                if ($header[$j] === 'DR')     $cols['dr'] = $j;
                if ($header[$j] === 'CR')     $cols['cr'] = $j;
                if ($header[$j] === 'INT')    $cols['int'] = $j;
                if ($header[$j] === 'PERIOD') $cols['period'] = $j;
            }

            if ($cols['dr'] === null && $cols['cr'] === null && $cols['int'] === null) {
                continue;
            }

            // -----------------------------
            // FIX: label can be above ANY of the header columns:
            // YEAR, MONTH, DR, CR, INT, PERIOD (and allow slight +/-1 shift)
            // -----------------------------
            $label = null;

            if ($labelRowNorm) {

                $reserved = ['YEAR', 'MONTH', 'DR', 'CR', 'INT', 'PERIOD'];

                // Candidate columns: EXACT header columns for this block
                $candidateCols = array_values(array_unique(array_filter([
                    $cols['year'],
                    $cols['month'],
                    $cols['dr'],
                    $cols['cr'],
                    $cols['int'],
                    $cols['period'],
                ], fn($v) => $v !== null)));

                // Pass 1: exact positions
                foreach ($candidateCols as $k) {
                    if ($k < 0 || $k >= count($labelRowNorm)) continue;
                    $v = $labelRowNorm[$k] ?? '';
                    if ($v !== '' && !in_array($v, $reserved, true)) {
                        $label = $v;
                        break;
                    }
                }

                // Pass 2: allow +/-1 column shift
                if (!$label) {
                    foreach ($candidateCols as $k) {
                        foreach ([$k - 1, $k + 1] as $kk) {
                            if ($kk < 0 || $kk >= count($labelRowNorm)) continue;
                            $v = $labelRowNorm[$kk] ?? '';
                            if ($v !== '' && !in_array($v, $reserved, true)) {
                                $label = $v;
                                break 2;
                            }
                        }
                    }
                }
            }

            if (!$label) {
                throw new \RuntimeException("Loan label not found at column {$i}");
            }

            $blocks[] = [
                'label' => $label,
                'cols'  => $cols
            ];
        }

        return $blocks;
    }

    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string)$v) !== '') return false;
        }
        return true;
    }


    protected function num($v)
{
    if ($v === null) return null;

    $s = trim((string)$v);
    if ($s === '') return null;

    // Handle (123) style negatives / (0)
    if (preg_match('/^\(([\d,\.]+)\)$/', $s, $m)) {
        $s = $m[1]; // treat (0) as 0; if you want negative, use "-{$m[1]}"
    }

    $s = str_replace([',', ' '], '', $s);
    return is_numeric($s) ? (float)$s : null;
}


    protected function cleanYear($v): ?int
    {
        if ($v === null) return null;
        $v = str_replace(',', '', trim((string)$v));
        return ctype_digit($v) ? (int)$v : null;
    }

    protected function cleanMonth($v): ?string
    {
        if ($v === null) return null;

        $m = strtoupper(trim((string)$v));
        $m = str_replace("\xC2\xA0", ' ', $m);
        $m = preg_replace('/\s+/', ' ', $m);

        $map = [
            'JAN' => 'JAN',
            'JANUARY' => 'JAN',
            'FEB' => 'FEB',
            'FEBRUARY' => 'FEB',
            'MAR' => 'MAR',
            'MARCH' => 'MAR',
            'APR' => 'APR',
            'APRIL' => 'APR',
            'MAY' => 'MAY',
            'JUN' => 'JUN',
            'JUNE' => 'JUN',
            'JUL' => 'JUL',
            'JULY' => 'JUL',
            'AUG' => 'AUG',
            'AUGUST' => 'AUG',
            'SEP' => 'SEP',
            'SEPT' => 'SEP',
            'SEPTEMBER' => 'SEP',
            'OCT' => 'OCT',
            'OCTOBER' => 'OCT',
            'NOV' => 'NOV',
            'NOVEMBER' => 'NOV',
            'DEC' => 'DEC',
            'DECEMBER' => 'DEC',
        ];

        return $map[$m] ?? null;
    }


    protected function cleanPeriod($v): ?int
    {
        if ($v === null) return null;
        $v = trim((string)$v);
        return ctype_digit($v) ? (int)$v : null;
    }

    protected function isZeroOrNull($v): bool
    {
        return $v === null || $v === '' || (is_numeric($v) && (float)$v == 0.0);
    }

    protected function cellNumber($sheet, int $rowIndex1Based, int $colIndex0Based): ?float
    {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex0Based + 1);
        $cell = $sheet->getCell($colLetter . $rowIndex1Based);

        $v = $cell->getValue();

        if ($cell->isFormula()) {
            try {
                $calc = $cell->getCalculatedValue();
                if ($calc !== null && $calc !== '') {
                    $v = $calc;
                }
            } catch (\Throwable $e) {
                // keep raw $v
            }
        }

        return $this->num($v);
    }

    protected function cellScalar($sheet, int $rowIndex1Based, int $colIndex0Based)
    {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex0Based + 1);
        $cell = $sheet->getCell($colLetter . $rowIndex1Based);

        $v = $cell->getValue();

        if ($cell->isFormula()) {
            try {
                $calc = $cell->getCalculatedValue();
                if ($calc !== null && $calc !== '') {
                    $v = $calc;
                }
            } catch (\Throwable $e) {
                // keep raw $v
            }
        }

        return $v;
    }
}
