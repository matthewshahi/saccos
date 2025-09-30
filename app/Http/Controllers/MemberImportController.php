<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberImportController extends Controller
{
    public function showImportForm()
    {
        return view('members.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv' => ['required','file','mimes:csv,txt'],
        ]);

        $path = $request->file('csv')->getRealPath();

        // Detect delimiter
        $fh = fopen($path, 'r');
        if (!$fh) return back()->withErrors(['csv' => 'Failed to open uploaded file.']);
        $firstLine = fgets($fh);
        if ($firstLine === false) { fclose($fh); return back()->withErrors(['csv' => 'CSV appears empty.']); }
        $counts = [','=>substr_count($firstLine,','), ';'=>substr_count($firstLine,';'), "\t"=>substr_count($firstLine,"\t")];
        arsort($counts);
        $delimiter = array_key_first($counts) ?? ',';
        rewind($fh);

        // Read header
        $header = fgetcsv($fh, 0, $delimiter);
        if (!$header) { fclose($fh); return back()->withErrors(['csv' => 'CSV header missing.']); }

        $normalizeHeader = function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // BOM
            $h = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $h); // zero-width
            $h = mb_strtolower(trim($h));
            $h = str_replace(['_', '-'], ' ', $h);
            return preg_replace('/\s+/', ' ', $h);
        };
        $normHeaders = array_map($normalizeHeader, $header);
        $map = [];
        foreach ($normHeaders as $i => $col) $map[$col] = $i;

        if (!isset($map['name'])) {
            fclose($fh);
            return back()->withErrors(['csv' => 'CSV must contain a "name" column']);
        }
        $nameIndex = $map['name'];

        // Helpers
        $normalizeName = fn(?string $n) => trim(preg_replace('/\s+/', ' ', $n ?? ''));
        $likeEscape = fn(string $s) => str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $s);
        $tokens = function (string $n) {
            $n = Str::lower($n);
            $n = preg_replace("/[^a-z'\s]/", ' ', $n);
            $n = trim(preg_replace('/\s+/', ' ', $n));
            return array_values(array_filter(explode(' ', $n), fn($t)=>$t!==''));
        };
        $twoCombos = function (array $parts) {
            $out = [];
            $c = count($parts);
            if ($c < 2) return $out;
            for ($i=0; $i<$c; $i++) for ($j=$i+1; $j<$c; $j++) {
                $pair = [$parts[$i], $parts[$j]];
                sort($pair);
                $out[] = implode(' ', $pair);
            }
            return array_values(array_unique($out));
        };

        // Random generators (ensure low collision probability; retry if clashes)
        $uniqueSaccoId = function () {
            return 'ROAM'.random_int(100000, 999999);
        };
        $uniqueNationalId = function () {
            return 'ID'.random_int(10000000, 99999999);
        };
        $emailFromName = function (string $name) {
            // e.g., "John Peter Mwangi" -> "john.mwangi123@roam.com"
            $parts = array_values(array_filter(explode(' ', Str::lower($name)), fn($t)=>$t!==''));
            $local = '';
            if (count($parts) >= 2) $local = $parts[0].'.'.$parts[count($parts)-1];
            elseif (count($parts) === 1) $local = $parts[0];
            else $local = 'member';
            $local = preg_replace('/[^a-z0-9\.]+/','',$local);
            return $local.random_int(100,999).'@roam.com';
        };

        // Import loop
        $total=0; $matched=0; $inserted=0;
        $newMembersPreview = [];
        $seenTokenKeys = [];

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $total++;

            $name = $normalizeName($row[$nameIndex] ?? '');
            if ($name === '') continue;

            $ts = $tokens($name);
            if (!$ts) continue;
            $tokenKey = implode(' ', array_unique($ts));
            if (isset($seenTokenKeys[$tokenKey])) continue;

            // 1) all-token AND search
            $q = DB::table('sacco_members');
            foreach ($ts as $t) $q->where('member_name','LIKE','%'.$likeEscape($t).'%');
            if ($q->exists()) { $matched++; $seenTokenKeys[$tokenKey]=true; continue; }

            // 2) any 2-token AND search (for 3+ tokens)
            if (count($ts) >= 3) {
                $pairs = $twoCombos($ts);
                $hit = false;
                foreach ($pairs as $pair) {
                    [$a,$b] = explode(' ', $pair, 2);
                    $q2 = DB::table('sacco_members')
                        ->where('member_name','LIKE','%'.$likeEscape($a).'%')
                        ->where('member_name','LIKE','%'.$likeEscape($b).'%');
                    if ($q2->exists()) { $matched++; $hit=true; break; }
                }
                if ($hit) { $seenTokenKeys[$tokenKey]=true; continue; }
            }

            // 3) Insert new member with requested columns/defaults
            // Ensure uniqueness for sacco_id & national id
            $saccoId = $uniqueSaccoId();
            while (DB::table('sacco_members')->where('member_sacco_id', $saccoId)->exists()) {
                $saccoId = $uniqueSaccoId();
            }
            $natId = $uniqueNationalId();
            while (DB::table('sacco_members')->where('member_national_id', $natId)->exists()) {
                $natId = $uniqueNationalId();
            }
            $email = $emailFromName($name);
            while (DB::table('sacco_members')->where('member_email', $email)->exists()) {
                $email = $emailFromName($name);
            }

            DB::table('sacco_members')->insert([
                'member_name'                    => $name,
                'member_date_joined'             => '2025-07-01', // July 01 2025 (ISO)
                'member_dept'                    => 1,
                'member_sacco_id'                => $saccoId,          // ROAM###### (random)
                'member_national_id'             => $natId,            // ID######## (random)
                'member_postal_address'          => 'None',
                'member_phone_no'                => '0722000000',
                'member_gender'                  => 'M',
                'member_email'                   => $email,            // randomised @roam.com
                'member_share_contr_monthly'     => 0,
                'member_fosa_contr_monthly'      => 0,
                'member_total_share'             => 0,
                'member_total_fosa'              => 0,
                'member_total_loan'              => 0,
                'member_total_share_capital'     => 0,
                'member_tied_shares'             => 0,
                'member_tied_shares_self'        => 0,
                'member_active'                  => 'Y',
                'member_position'                => 1,
            ]);

            $inserted++;
            $newMembersPreview[] = [
                'member_name' => $name,
                'member_email' => $email,
                'member_sacco_id' => $saccoId,
                'member_national_id' => $natId,
            ];
            $seenTokenKeys[$tokenKey]=true;
        }

        fclose($fh);

        return redirect()
            ->route('members.import.form')
            ->with('summary', [
                'total_rows' => $total,
                'matched'    => $matched,
                'inserted'   => $inserted,
                'new_members_preview' => array_slice($newMembersPreview, 0, 200),
            ]);
    }


    public function patch(Request $request)
{
    $request->validate([
        'csv' => ['required','file','mimes:csv,txt'],
    ]);

    $path = $request->file('csv')->getRealPath();

    // --- Detect delimiter
    $fh = fopen($path, 'r');
    if (!$fh) return back()->withErrors(['csv' => 'Failed to open uploaded file.']);
    $first = fgets($fh);
    if ($first === false) { fclose($fh); return back()->withErrors(['csv' => 'CSV appears empty.']); }
    $counts = [','=>substr_count($first,','), ';'=>substr_count($first,';'), "\t"=>substr_count($first,"\t")];
    arsort($counts);
    $delimiter = array_key_first($counts) ?? ',';
    rewind($fh);

    // --- Read header
    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header) { fclose($fh); return back()->withErrors(['csv' => 'CSV header missing.']); }

    $norm = function ($h) {
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);                 // BOM
        $h = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $h);  // zero-width
        $h = mb_strtolower(trim($h));
        $h = preg_replace('/\s+/', ' ', $h);
        $h = str_replace(['_', '-'], ' ', $h);
        return $h;
    };
    $H = array_map($norm, $header);
    $map = []; foreach ($H as $i => $c) $map[$c] = $i;

    // Resolve columns
    $colOrNull = function(array $alts, array $map) {
        foreach ($alts as $a) if (isset($map[$a])) return $map[$a];
        return null;
    };

    $idxName  = $colOrNull(['name'], $map) ?? 0;
    $idxPhone = $colOrNull(['phone number','phone','mobile'], $map) ?? 1;
    $idxNatId = $colOrNull(['identification number','id number','national id','national identification number'], $map) ?? 2;
    $idxSacco = $colOrNull(['sacco number','sacco no','member sacco id','sacco'], $map) ?? 3;
    $idxDob   = $colOrNull(['date of birth','dob','birth date'], $map) ?? 4;

    // --- Helpers
    $normalizeName = fn(?string $n) => trim(preg_replace('/\s+/', ' ', $n ?? ''));
    $likeEscape = fn(string $s) => str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $s);
    $tokens = function (string $n) {
        $n = \Illuminate\Support\Str::lower($n);
        $n = preg_replace("/[^a-z'\s]/", ' ', $n);
        $n = trim(preg_replace('/\s+/', ' ', $n));
        return array_values(array_filter(explode(' ', $n), fn($t)=>$t!==''));
    };
    $twoCombos = function (array $parts) {
        $out = []; $c = count($parts);
        for ($i=0; $i<$c; $i++) for ($j=$i+1; $j<$c; $j++) {
            $pair = [$parts[$i], $parts[$j]]; sort($pair);
            $out[] = implode(' ', $pair);
        }
        return array_values(array_unique($out));
    };
    $normalizePhoneKE = function (?string $p) {
        $p = (string)$p;
        $p = preg_replace('/\s+/', '', $p);
        $p = str_replace(['+','-','(',')'], '', $p);
        if (preg_match('/^254(\d{9})$/', $p, $m)) return '0'.$m[1];
        if (preg_match('/^(0[17]\d{8})$/', $p)) return $p;
        if (preg_match('/(\d{9})$/', $p, $m)) return '0'.$m[1];
        return $p ?: null;
    };
    $parseDOB = function (?string $s) {
        $s = trim((string)$s);
        if ($s === '') return null;
        $s = str_replace(['.','-'], '/', $s);
        try {
            $dt = \Carbon\Carbon::createFromFormat('d/m/Y', $s);
        } catch (\Exception $e1) {
            try { $dt = \Carbon\Carbon::createFromFormat('d/m/y', $s); }
            catch (\Exception $e2) {
                try { $dt = \Carbon\Carbon::parse($s); }
                catch (\Exception $e3) { return null; }
            }
        }
        return $dt ? $dt->format('Y-m-d') : null;
    };

    // --- Loop
    $total=0; $matched=0; $updated=0; $inserted=0; $updatesPreview=[]; $newMembersPreview=[];
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        $total++;
        $get = fn($idx) => array_key_exists($idx, $row) ? $row[$idx] : null;

        $nameRaw = $get($idxName);
        $name = $normalizeName($nameRaw);
        if ($name === '') continue;

        $ts = $tokens($name);
        if (!$ts) continue;

        // Try match by tokens
        $q = \DB::table('sacco_members');
        foreach ($ts as $t) $q->where('member_name','LIKE','%'.$likeEscape($t).'%');
        $member = $q->first();

        if (!$member && count($ts) >= 3) {
            foreach ($twoCombos($ts) as $pair) {
                [$a,$b] = explode(' ', $pair, 2);
                $q2 = \DB::table('sacco_members')
                    ->where('member_name','LIKE','%'.$likeEscape($a).'%')
                    ->where('member_name','LIKE','%'.$likeEscape($b).'%');
                $m2 = $q2->first();
                if ($m2) { $member = $m2; break; }
            }
        }

        $valSacco = trim((string)$get($idxSacco));
        $valNatId = trim((string)$get($idxNatId));
        $valPhone = $normalizePhoneKE($get($idxPhone));
        $valDob   = $parseDOB($get($idxDob));

        if ($member) {
            // --- UPDATE flow
            $matched++;
            $updates = [];
            if ($valSacco !== '') {
                $exists = DB::table('sacco_members')
                    ->where('member_sacco_id', $valSacco)
                    ->where('member_id', '!=', $member->member_id)
                    ->exists();
                if (!$exists) $updates['member_sacco_id'] = $valSacco;
            }
            if ($valNatId !== '') $updates['member_national_id'] = $valNatId;
            if (!empty($valPhone)) $updates['member_phone_no'] = $valPhone;
            if (!empty($valDob))   $updates['member_dob']      = $valDob;

            if (!empty($updates)) {
                \DB::table('sacco_members')->where('member_id', $member->member_id)->update($updates);
                $updated++;
                if (count($updatesPreview) < 200) {
                    $updatesPreview[] = array_merge(['name' => $member->member_name], $updates);
                }
            }
        } else {
            // --- INSERT flow
            $saccoId = !empty($valSacco) ? $valSacco : 'ROAM'.random_int(100000, 999999);
            while (DB::table('sacco_members')->where('member_sacco_id', $saccoId)->exists()) {
                $saccoId = 'ROAM'.random_int(100000, 999999);
            }

            $natId = !empty($valNatId) ? $valNatId : 'ID'.random_int(10000000, 99999999);
            while (DB::table('sacco_members')->where('member_national_id', $natId)->exists()) {
                $natId = 'ID'.random_int(10000000, 99999999);
            }

            $email = strtolower(str_replace(' ', '.', $name)).random_int(100,999).'@roam.com';
            while (DB::table('sacco_members')->where('member_email', $email)->exists()) {
                $email = strtolower(str_replace(' ', '.', $name)).random_int(100,999).'@roam.com';
            }

            DB::table('sacco_members')->insert([
                'member_name'        => $name,
                'member_sacco_id'    => $saccoId,
                'member_national_id' => $natId,
                'member_phone_no'    => $valPhone ?: '0722000000',
                'member_dob'         => $valDob,
                'member_email'       => $email,
                'member_date_joined' => now()->format('Y-m-d'),
                'member_dept'        => 1,
                'member_active'      => 'Y',
                'member_position'    => 1,
            ]);

            $inserted++;
            if (count($newMembersPreview) < 200) {
                $newMembersPreview[] = [
                    'name' => $name,
                    'member_sacco_id' => $saccoId,
                    'member_national_id' => $natId,
                    'member_phone_no' => $valPhone,
                    'member_dob' => $valDob,
                ];
            }
        }
    }
    fclose($fh);

    return redirect()
        ->route('members.patch.form')
        ->with('summary', [
            'total_rows'       => $total,
            'matched_updated'  => $updated,
            'new_inserted'     => $inserted,
            'updates_preview'  => $updatesPreview,
            'new_members_preview' => $newMembersPreview,
        ]);
}


public function showPatchForm()
{
    // Simply returns the Blade view we created earlier (resources/views/members/patch.blade.php)
    return view('members.patch');
}
}