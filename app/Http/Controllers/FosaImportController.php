<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FosaImportController extends Controller
{
    /** Show upload form */
    public function showForm()
    {
        return view('fosa.transactions.import');
    }

    /** POST: upload + preview */
    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:8192',
        ]);

        // Save file to storage/app/temp/xxxx.csv
        $path = $request->file('csv_file')->store('temp');

        // Parse CSV
        $rows   = array_map('str_getcsv', file(storage_path("app/{$path}"), FILE_SKIP_EMPTY_LINES));
        $header = array_map(function ($h) {
            // strip UTF-8 BOM and normalize
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            return strtolower(trim($h));
        }, array_shift($rows) ?: []);

        $expected = [
            'member_no','national_id','amount','type','description',
            'doc_no','period','date_paid','fosa_paid_by','fosa_type'
        ];

        if ($header !== $expected) {
            Storage::delete($path);
            return back()->with('error', 'Invalid CSV header. Please use the official template.');
        }

        // ✅ Limit check
if (count($rows) > 500) {
    Storage::delete($path);
    return back()->with('error', 'Too many rows in CSV. Please split your file into batches of 500 or fewer.');
}

        $data = [];
        foreach ($rows as $i => $row) {
            // Normalize row size
            if (count($row) < count($expected)) {
                return back()->with('error', "Row ".($i+2)." has fewer columns than expected.");
            }
            $row = array_map('trim', array_slice($row, 0, count($expected)));
            $data[] = array_combine($expected, $row);
        }

        // Enforce identification consistency across the whole file
        $hasOnlyMember = $hasOnlyNational = $hasBoth = false;
        foreach ($data as $i => $r) {
            $mn  = $r['member_no'] ?? '';
            $nid = $r['national_id'] ?? '';
            if ($mn === '' && $nid === '') {
                return back()->with('error', "Row ".($i+2).": Either member_no or national_id is required.");
            }
            if ($mn !== '' && $nid !== '') $hasBoth = true;
            elseif ($mn !== '' && $nid === '') $hasOnlyMember = true;
            elseif ($mn === '' && $nid !== '') $hasOnlyNational = true;
        }
        // If the file mixes the identification modes, block it.
        if ( ($hasBoth && ($hasOnlyMember || $hasOnlyNational)) || ($hasOnlyMember && $hasOnlyNational) ) {
            Storage::delete($path);
            return back()->with('error', 'Mixed identification across rows. Use either ALL member_no, or ALL national_id, or BOTH on EVERY row.');
        }

        return view('fosa.transactions.import_preview', [
            'data'     => $data,
            'csv_path' => $path, // keep the relative "temp/xxx.csv"
        ]);
    }

    /** GET: re-show preview by file name after an error (no upload) */
    public function showPreview($csv)
    {
        $path = "temp/{$csv}";
        if (!Storage::exists($path)) {
            return redirect()->route('fosa.transactions.import')
                ->with('error', 'CSV file not found. Please re-upload.');
        }

        $rows   = array_map('str_getcsv', file(storage_path("app/{$path}"), FILE_SKIP_EMPTY_LINES));
        $header = array_shift($rows);

        $expected = [
            'member_no','national_id','amount','type','description',
            'doc_no','period','date_paid','fosa_paid_by','fosa_type'
        ];

        $data = [];
        foreach ($rows as $row) {
            $row = array_map('trim', array_slice($row, 0, count($expected)));
            $data[] = array_combine($expected, $row);
        }

        return view('fosa.transactions.import_preview', [
            'data'     => $data,
            'csv_path' => $path,
        ]);
    }

    /** POST: process + insert */
    public function process(Request $request)
    {
        $request->validate([
            'csv_path'  => 'required|string',
            'ledger_id' => 'required|integer|exists:sacco_sub_account,sub_account_id',
        ]);

        $path = $request->csv_path; // e.g. temp/xxxx.csv
        if (!Storage::exists($path)) {
            return redirect()->route('fosa.transactions.import')
                ->with('error', 'CSV file is missing. Please re-upload.');
        }

        $csv = array_map('str_getcsv', file(storage_path("app/{$path}"), FILE_SKIP_EMPTY_LINES));
        $header = array_map('trim', array_shift($csv) ?: []);

        $expected = [
            'member_no','national_id','amount','type','description',
            'doc_no','period','date_paid','fosa_paid_by','fosa_type'
        ];
        if ($header !== $expected) {
            return redirect()->route('fosa.transactions.import')
                ->with('error', 'CSV header changed. Please re-upload.');
        }

        $defaultFosa = DB::table('sacco_defaults')
            ->where('default_name', 'default_fosa_account')
            ->value('default_value');

        if (!$defaultFosa) {
            return back()->with('error', 'Default FOSA sub-account is not configured.');
        }

        DB::beginTransaction();

        try {
            foreach ($csv as $i => $row) {
                $row = array_map('trim', array_slice($row, 0, count($expected)));
                [
                    $member_no, $national_id, $amount, $type, $description,
                    $doc_no, $period, $date_paid, $fosa_paid_by, $fosa_type
                ] = $row;

                // 🔹 Clean up quotes/spaces around identifiers
$member_no    = trim($member_no, " \t\n\r\0\x0B\"'");
$national_id  = trim($national_id, " \t\n\r\0\x0B\"'");
$amount       = trim($amount, " \t\n\r\0\x0B\"'");
$type         = trim($type, " \t\n\r\0\x0B\"'");
$description  = trim($description, " \t\n\r\0\x0B\"'");
$doc_no       = trim($doc_no, " \t\n\r\0\x0B\"'");
$period       = trim($period, " \t\n\r\0\x0B\"'");
$date_paid    = trim($date_paid, " \t\n\r\0\x0B\"'");
$fosa_paid_by = trim($fosa_paid_by, " \t\n\r\0\x0B\"'");
$fosa_type    = trim($fosa_type, " \t\n\r\0\x0B\"'");


                // --- Row validations
                if (!is_numeric($amount) || $amount <= 0) {
                    throw new \Exception("Row ".($i+2).": Invalid amount for Doc No {$doc_no}.");
                }
                if (!preg_match('/^\d{6}$/', $period)) {
                    throw new \Exception("Row ".($i+2).": Period must be YYYYMM for Doc No {$doc_no}.");
                }

                try {
                    $date_paid = Carbon::parse($date_paid)->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    throw new \Exception("Row ".($i+2).": Invalid date_paid for Doc No {$doc_no}. Use YYYY-MM-DD HH:mm");
                }

                // --- Member lookup (strict rules)
              // --- Member lookup (flexible: use whichever is available)
$member = null;

if (!empty($member_no) && !empty($national_id)) {
    // Try match both first
    $member = DB::table('sacco_members')
        ->where('member_sacco_id', $member_no)
        ->where('member_national_id', $national_id)
        ->first();

    // If no match, fallback to whichever exists
    if (!$member) {
        $member = DB::table('sacco_members')
            ->where(function ($q) use ($member_no, $national_id) {
                if (!empty($member_no)) {
                    $q->orWhere('member_sacco_id', $member_no);
                }
                if (!empty($national_id)) {
                    $q->orWhere('member_national_id', $national_id);
                }
            })
            ->first();
    }
} elseif (!empty($member_no)) {
    $member = DB::table('sacco_members')->where('member_sacco_id', $member_no)->first();
} elseif (!empty($national_id)) {
    $member = DB::table('sacco_members')->where('member_national_id', $national_id)->first();
}

if (!$member) {
    throw new \Exception("Row ".($i+2).": Member not found (Doc No {$doc_no}).");
}

                // --- Amount sign by type
                $amount = (float) $amount;
                $isDeposit = strtolower($type) === 'deposit';
                $signedAmount = $isDeposit ? $amount : -abs($amount);

                // --- Map fosa type name → id (optional)
                $fosaTypeId = null;
                if ($fosa_type !== '') {
                    $fosaTypeId = DB::table('sacco_fosa_types')
                        ->where('type_name', $fosa_type)
                        ->value('type_id');
                 $description .= " [FOSA Type: {$fosa_type}]";
                }

                // --- Insert sacco_fosas
                $fosaId = DB::table('sacco_fosas')->insertGetId([
                    'fosa_member_id'      => $member->member_id,
                    'fosa_type_id'        => $fosaTypeId,
                    'fosa_amount_paying'  => $signedAmount,
                    'fosa_paid_by'        => $fosa_paid_by,
                    'fosa_period'         => $period,
                    'fosa_description'    => $description,
                    'fosa_doc_no'         => $doc_no,
                    'fosa_date_paid'      => $date_paid,
                    'fosa_by'             => Auth::id(),
                    'fosa_ip'             => $request->ip(),
                    'fosa_transdate'      => now(),
                    'fosa_end_month_proc' => 'N',
                ]);

                // --- Keep member_total_fosa in sync
                if ($isDeposit) {
                    DB::table('sacco_members')
                        ->where('member_id', $member->member_id)
                        ->increment('member_total_fosa', $amount);
                } else {
                    DB::table('sacco_members')
                        ->where('member_id', $member->member_id)
                        ->decrement('member_total_fosa', $amount);
                }

                // --- Ledger postings (2 legs)
                $entry = [
                    'accounts_trans_period'     => $period,
                    'accounts_trans_doc_no'     => $doc_no,
                    'accounts_trans_decription' => "FOSA Import #{$fosaId}: {$description} (Member: {$member->member_name})",
                    'accounts_trans_source'     => "FOSA:{$fosaId}",
                    'accounts_trans_dat_date'   => $date_paid,
                    'accounts_trans_user_id'    => Auth::id(),
                    'accounts_trans_ip'         => $request->ip(),
                    'accounts_trans_member_id'  => $member->member_id,
                    'accounts_trans_app_name'   => 'FOSA Import',
                ];

                if ($isDeposit) {
                    // Debit counter, Credit FOSA
                    DB::table('sacco_accounts_trans')->insert([
                        array_merge($entry, [
                            'accounts_trans_sub_account' => $request->ledger_id,
                            'accounts_trans_debit'       => $amount,
                            'accounts_trans_credit'      => 0,
                        ]),
                        array_merge($entry, [
                            'accounts_trans_sub_account' => $defaultFosa,
                            'accounts_trans_debit'       => 0,
                            'accounts_trans_credit'      => $amount,
                        ]),
                    ]);
                } else {
                    // Debit FOSA, Credit counter
                    DB::table('sacco_accounts_trans')->insert([
                        array_merge($entry, [
                            'accounts_trans_sub_account' => $defaultFosa,
                            'accounts_trans_debit'       => abs($amount),
                            'accounts_trans_credit'      => 0,
                        ]),
                        array_merge($entry, [
                            'accounts_trans_sub_account' => $request->ledger_id,
                            'accounts_trans_debit'       => 0,
                            'accounts_trans_credit'      => abs($amount),
                        ]),
                    ]);
                }
            }

            DB::commit();
            Storage::delete($path);

            return redirect()->route('fosa.transactions.index')
                ->with('success', 'CSV imported successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            // Send the user back to the preview page for the SAME file (GET),
            // and show the specific error. Use basename so we don’t inject a slash.
            return redirect()
                ->route('fosa.transactions.import.preview.show', ['csv' => basename($path)])
                ->with('error', $e->getMessage());
        }
    }
}