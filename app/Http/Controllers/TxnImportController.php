<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TxnImportController extends Controller
{
    public function showForm()
    {
        return view('transactions.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv' => ['required','file','mimes:csv,txt'],
        ]);

        $path = $request->file('csv')->getRealPath();

        // 1) Detect delimiter
        $fh = fopen($path, 'r');
        if (!$fh) return back()->withErrors(['csv' => 'Failed to open uploaded file.']);

        $first = fgets($fh);
        if ($first === false) { fclose($fh); return back()->withErrors(['csv' => 'CSV appears empty.']); }
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $delimiter = array_key_first($counts) ?? ',';
        rewind($fh);

        // 2) Read header
        $header = fgetcsv($fh, 0, $delimiter);
        if (!$header) { fclose($fh); return back()->withErrors(['csv' => 'CSV header missing.']); }

        // Normalize headers, but we will also support positional fallback
        $norm = function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);                 // BOM
            $h = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $h);  // zero-width
            $h = mb_strtolower(trim($h));
            $h = str_replace(['_', '-'], ' ', $h);
            $h = preg_replace('/\s+/', ' ', $h);
            return $h;
        };
        $H = array_map($norm, $header);
        $map = []; foreach ($H as $i => $c) $map[$c] = $i;

        $col = function(array $alts) use ($map) {
            foreach ($alts as $a) if (isset($map[$a])) return $map[$a];
            return null;
        };

        $idxName     = $col(['name'])     ?? 0;
        $idxDate     = $col(['date'])     ?? 1;
        $idxAmount   = $col(['amount'])   ?? 2;
        $idxNotes    = $col(['notes','narration','description']) ?? 3;
        $idxCategory = $col(['category','type']) ?? 4;

        // 3) Truncate destination tables (as requested)
        // DB::table('sacco_fosas')->truncate();
        // DB::table('sacco_shares')->truncate();
        // DB::table('sacco_capital_shares')->truncate();

        // 4) Helpers (SMART member matching)
        $normalizeName = fn(?string $n) => trim(preg_replace('/\s+/', ' ', $n ?? ''));
        $likeEscape = fn(string $s) => str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $s);

        $tokens = function (string $n) {
            $n = Str::lower($n);
            $n = preg_replace("/[^a-z'\s]/", ' ', $n);
            $n = trim(preg_replace('/\s+/', ' ', $n));
            return array_values(array_filter(explode(' ', $n), fn($t)=>$t!==''));
        };

        $twoCombos = function (array $parts) {
            $out = []; $c = count($parts);
            for ($i=0; $i<$c; $i++) for ($j=$i+1; $j<$c; $j++) {
                $pair = [$parts[$i], $parts[$j]];
                sort($pair);
                $out[] = implode(' ', $pair);
            }
            return array_values(array_unique($out));
        };

        $parseDate = function ($s) {
            $s = trim((string)$s);
            if ($s === '') return null;
            $s2 = str_replace(['.','-'], '/', $s);
            // Prefer day-first
            try { $dt = Carbon::createFromFormat('d/m/Y', $s2); }
            catch (\Exception $e1) {
                try { $dt = Carbon::createFromFormat('d/m/y', $s2); }
                catch (\Exception $e2) {
                    try { $dt = Carbon::parse($s); }
                    catch (\Exception $e3) { return null; }
                }
            }
            return $dt;
        };

        $periodYYYYmm = function (?Carbon $dt) {
            return $dt ? $dt->format('Ym') : null; // e.g. 202507
        };

        // Cache for member resolution across many rows
        $memberCache = [];

        $resolveMemberId = function (string $name) use ($tokens, $likeEscape, $twoCombos, &$memberCache) {
            $key = strtolower(trim($name));
            if (isset($memberCache[$key])) return $memberCache[$key];

            $ts = $tokens($name);
            if (!$ts) { $memberCache[$key] = null; return null; }

            // 1) All tokens AND
            $q = DB::table('sacco_members');
            foreach ($ts as $t) $q->where('member_name','LIKE','%'.$likeEscape($t).'%');
            $m = $q->select('member_id')->first();
            if ($m) { $memberCache[$key] = $m->member_id; return $m->member_id; }

            // 2) Any two-token AND for 3+ parts
            if (count($ts) >= 3) {
                foreach ($twoCombos($ts) as $pair) {
                    [$a,$b] = explode(' ', $pair, 2);
                    $q2 = DB::table('sacco_members')
                        ->where('member_name','LIKE','%'.$likeEscape($a).'%')
                        ->where('member_name','LIKE','%'.$likeEscape($b).'%');
                    $m2 = $q2->select('member_id')->first();
                    if ($m2) { $memberCache[$key] = $m2->member_id; return $m2->member_id; }
                }
            }

            $memberCache[$key] = null;
            return null;
        };

        // 5) Main loop
        $total = 0;
        $insFosa = 0; $insShares = 0; $insCapital = 0;
        $unmatched = [];

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $total++;

            // Guard: short rows
            $get = fn($i) => array_key_exists($i, $row) ? $row[$i] : null;

            $rawName   = $get($idxName);
            $rawDate   = $get($idxDate);
            $rawAmount = $get($idxAmount);
            $rawNotes  = $get($idxNotes);
            $rawCat    = $get($idxCategory);

            $name   = $normalizeName($rawName);
            if ($name === '') continue;

            // parse date
            $dt = $parseDate($rawDate);
            if (!$dt) {
                // If no valid date, skip row (or you could default to today if you prefer)
                $unmatched[] = $name.' (invalid date: '.$rawDate.')';
                continue;
            }

            // parse amount
            $amount = is_numeric($rawAmount) ? (float)$rawAmount : (float)preg_replace('/[^\d\.]/', '', (string)$rawAmount);
            if ($amount <= 0) continue;

            // map category
            $catU = strtoupper(trim((string)$rawCat));
            if (strpos($catU, 'DEPOSIT') !== false) {
                $category = 'Deposits';
            } elseif (strpos($catU, 'CAPITAL') !== false) {
                $category = 'Capital Shares';
            } elseif (strpos($catU, 'FOSA') !== false) {
                $category = 'FOSA';
            } else {
                // Unknown category → treat as FOSA (per earlier rule "anything else FOSA")
                $category = 'FOSA';
            }

            // resolve member_id
            $memberId = $resolveMemberId($name);
            if (!$memberId) {
                $unmatched[] = $name;
                continue;
            }

            // Common field values
            $period = $periodYYYYmm($dt);      // YYYYmm
            $dateYmd = $dt->format('Y-m-d');   // YYYY-mm-dd
            $notes = trim((string)$rawNotes);

            if ($category === 'FOSA') {
                DB::table('sacco_fosas')->insert([
                    'fosa_member_id'      => $memberId,
                    'fosa_amount_paying'  => $amount,
                    'fosa_paid_by'        => 'imported',
                    'fosa_period'         => $period,
                    'fosa_description'    => $notes,
                    'fosa_doc_no'         => 'imported',
                    'fosa_date_paid'      => $dateYmd,
                    'fosa_by'             => 1,
                    'fosa_ip'             => null,
                    'fosa_transdate'      => $dateYmd,
                    'fosa_end_month_proc' => 'N',
                ]);
                $insFosa++;
            } elseif ($category === 'Deposits') {
                DB::table('sacco_shares')->insert([
                    'share_member_id'      => $memberId,
                    'share_amount_paying'  => $amount,
                    'share_paid_by'        => 'imported',
                    'share_period'         => $period,
                    'share_description'    => $notes,
                    'share_doc_no'         => 'imported',
                    'share_date_paid'      => $dateYmd,
                    'share_by'             => 1,
                    'share_ip'             => null,
                    'share_transdate'      => $dateYmd,
                    'share_end_month_proc' => 'N',
                ]);
                $insShares++;
            } else { // Capital Shares
                DB::table('sacco_capital_shares')->insert([
                    'share_capitalmember_id'      => $memberId,
                    'share_capitalamount_paying'  => $amount,
                    'share_capitalpaid_by'        => 'imported',
                    'share_capitalperiod'         => $period,
                    'share_capitaldescription'    => $notes,
                    'share_capitaldoc_no'         => 'imported',
                    'share_capitaldate_paid'      => $dateYmd,
                    'share_capitalby'             => 1,
                    'share_capitalip'             => null,
                    'share_capitaltransdate'      => $dateYmd,
                    'share_capitalend_month_proc' => 'N',
                ]);
                $insCapital++;
            }
        }

        fclose($fh);

        return redirect()
            ->route('transactions.import.form')
            ->with('summary', [
                'total_rows'       => $total,
                'fosa_inserted'    => $insFosa,
                'shares_inserted'  => $insShares,
                'capital_inserted' => $insCapital,
                'unmatched'        => $unmatched,
            ]);
    }
}