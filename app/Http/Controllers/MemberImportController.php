<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MemberImportController extends Controller
{
    public function showImportForm()
    {
        return view('import.members');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        // Read CSV safely (PHP 8.4-safe: specify escape)
        $raw = array_map(function ($line) {
            return str_getcsv($line, ',', '"', '\\');
        }, file($path));

        if (empty($raw) || count($raw) < 2) {
            return back()->with('error', 'CSV is empty or missing data rows.');
        }

        // Normalize header
        $header = array_map(function ($h) {
            $h = trim($h ?? '');
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip BOM
            $h = preg_replace('/\s+/', '_', $h);
            return strtolower($h);
        }, array_shift($raw));

        // Only the subset of columns you wanted
        $columns = [
            "member_name",
            "member_date_joined",
            "member_dept",
            "member_sacco_id",
            "member_national_id",
            "member_postal_address",
            "member_phone_no",
            "member_gender",
            "member_email",
            "member_share_contr_monthly",
            "member_fosa_contr_monthly",
            "member_total_share",
            "member_total_fosa",
            "member_total_loan",
            "member_total_share_capital",
            "member_tied_shares",
            "member_tied_shares_self",
            "member_active",
            "member_position"
        ];

        $zeroDefaults = [
            "member_share_contr_monthly",
            "member_fosa_contr_monthly",
            "member_total_share",
            "member_total_fosa",
            "member_total_loan",
            "member_total_share_capital",
            "member_tied_shares",
            "member_tied_shares_self",
        ];

        $rowsToInsert = [];
        $imported = 0;
        $chunkSize = 1000;

        $flush = function () use (&$rowsToInsert, &$imported) {
            if (!empty($rowsToInsert)) {
                DB::table('sacco_members')->insert($rowsToInsert);
                $imported += count($rowsToInsert);
                $rowsToInsert = [];
            }
        };

        foreach ($raw as $i => $row) {
            $assoc = [];
            foreach ($header as $idx => $key) {
                $assoc[$key] = isset($row[$idx]) ? trim($row[$idx]) : null;
            }

            $payload = array_fill_keys($columns, null);

            foreach ($columns as $col) {
                if (array_key_exists($col, $assoc)) {
                    $payload[$col] = $assoc[$col];
                }
            }

            // ---- Clean & Normalize ----

            // Names → uppercase, collapse spaces
            if (!empty($payload['member_name'])) {
                $payload['member_name'] = strtoupper(trim(preg_replace('/\s+/', ' ', $payload['member_name'])));
            }

            // Phone normalization
            if (!empty($payload['member_phone_no'])) {
                $rawPhone = preg_replace('/\s+/', '', $payload['member_phone_no']); // remove spaces
                if (preg_match('/^(07|01)\d{8}$/', $rawPhone)) {
                    $payload['member_phone_no'] = '254' . substr($rawPhone, 1); // 07... → 2547...
                } elseif (preg_match('/^254\d{9}$/', $rawPhone)) {
                    $payload['member_phone_no'] = $rawPhone; // already good
                } else {
                    $payload['member_phone_no'] = null; // invalid → null
                }
            }

            // Preserve or generate sacco id
            if (empty($payload['member_sacco_id'])) {
                $seq = $imported + count($rowsToInsert) + 1;
                $payload['member_sacco_id'] = 'MGSL-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
            }

            // Defaults
            if ($payload['member_active'] === '' || $payload['member_active'] === null) {
                $payload['member_active'] = 'Y';
            }
            foreach ($zeroDefaults as $k) {
                if ($payload[$k] === '' || $payload[$k] === null) {
                    $payload[$k] = 0;
                }
            }
            if (empty($payload['member_position'])) $payload['member_position'] = 1;
            if (empty($payload['member_dept']))     $payload['member_dept'] = 1;

            // Dates
            if (!empty($payload['member_date_joined'])) {
                try {
                    $payload['member_date_joined'] = Carbon::parse($payload['member_date_joined'])->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $payload['member_date_joined'] = Carbon::now()->format('Y-m-d H:i:s');
                }
            } else {
                $payload['member_date_joined'] = Carbon::now()->format('Y-m-d H:i:s');
            }

            // Convert empty strings → null
            foreach ($payload as $k => $v) {
                if ($v === '') $payload[$k] = null;
            }

            $rowsToInsert[] = $payload;
            if (count($rowsToInsert) >= $chunkSize) {
                $flush();
            }
        }

        $flush();

        return back()->with('success', "{$imported} members imported successfully.");
    }

    public function showSharesImportForm()
    {
        return view('import.shares');
    }

    public function importShares(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        // Read CSV (PHP 8.4-safe)
        $raw = array_map(function ($line) {
            return str_getcsv($line, ',', '"', '\\');
        }, file($path));
        if (empty($raw) || count($raw) < 2) {
            return back()->with('error', 'CSV is empty or missing data rows.');
        }

        // Normalize header
        $header = array_map(function ($h) {
            $h = trim($h ?? '');
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip BOM
            $h = preg_replace('/\s+/', '_', $h);
            return strtolower($h);
        }, array_shift($raw));

        // Expected columns (case-insensitive). Extra are ignored.
        // CSV can have member_name OR member_sacco_id (or both).
        $expected = [
            'member_name',
            'member_sacco_id',
            'share_amount_paying',
            'share_paid_by',
            'share_period',
            'share_description',
            'share_doc_no',
            'share_date_paid',
            'share_by',
            'share_ip',
            'share_transdate',
            'share_end_month_proc'
        ];

        // Build member lookups
        $members = DB::table('sacco_members')->select('member_id', 'member_name', 'member_sacco_id')->get();
        $nameToId = [];
        $codeToId = [];
        foreach ($members as $m) {
            if (!empty($m->member_name))     $nameToId[strtoupper(trim(preg_replace('/\s+/', ' ', $m->member_name)))] = (int)$m->member_id;
            if (!empty($m->member_sacco_id)) $codeToId[strtoupper(trim($m->member_sacco_id))] = (int)$m->member_id;
        }

        $inserted = 0;
        $updated  = 0;
        $skipped  = [];

        foreach ($raw as $i => $row) {
            $assoc = [];
            foreach ($header as $idx => $key) {
                $assoc[$key] = isset($row[$idx]) ? trim($row[$idx]) : null;
            }

            // Resolve member_id (prefer sacco code)
            $memberId = null;
            if (!empty($assoc['member_sacco_id'])) {
                $memberId = $codeToId[strtoupper($assoc['member_sacco_id'])] ?? null;
            }
            if (!$memberId && !empty($assoc['member_name'])) {
                $nm = strtoupper(trim(preg_replace('/\s+/', ' ', $assoc['member_name'])));
                $memberId = $nameToId[$nm] ?? null;
            }
            if (!$memberId) {
                $skipped[] = $assoc['member_name'] ?? $assoc['member_sacco_id'] ?? 'row ' . ($i + 2);
                continue;
            }

            // Amount
            $amountRaw = $assoc['share_amount_paying'] ?? null;
            $amountNum = null;
            if ($amountRaw !== null && $amountRaw !== '') {
                $amountSan = preg_replace('/[^\d.\-]/', '', $amountRaw); // remove KES, commas
                if ($amountSan !== '' && is_numeric($amountSan)) {
                    $amountNum = (float)$amountSan;
                }
            }
            if ($amountNum === null || $amountNum == 0.0) continue;

            // Period YYYYMM (fallback to date)
            $period = $assoc['share_period'] ?? '';
            $period = preg_replace('/\D/', '', $period);
            if (strlen($period) !== 6) {
                try {
                    $dt = !empty($assoc['share_date_paid']) ? \Carbon\Carbon::parse($assoc['share_date_paid']) : null;
                    if ($dt) $period = $dt->format('Ym');
                } catch (\Throwable $e) {
                    $period = '';
                }
            }
            if (strlen($period) !== 6) {
                $skipped[] = ($assoc['member_name'] ?? 'member') . ' (invalid period: ' . ($assoc['share_period'] ?? 'N/A') . ')';
                continue;
            }

            // Date paid (YYYY-MM-DD). If missing, 1st of period.
            $datePaid = $assoc['share_date_paid'] ?? null;
            if ($datePaid) {
                try {
                    $datePaid = \Carbon\Carbon::parse($datePaid)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $datePaid = null;
                }
            }
            if (!$datePaid) {
                $datePaid = \Carbon\Carbon::createFromFormat('Ym', $period)->startOfMonth()->format('Y-m-d');
            }

            // Other fields
            $paidBy  = !empty($assoc['share_paid_by']) ? strtoupper(trim(preg_replace('/\s+/', ' ', $assoc['share_paid_by']))) : (!empty($assoc['member_name']) ? strtoupper(trim($assoc['member_name'])) : 'SYSTEM');
            $desc    = $assoc['share_description'] ?? ("Savings contribution for {$period}");
            $docNo   = $assoc['share_doc_no'] ?? null;
            $byUser  = ($assoc['share_by'] ?? null) ?: (auth()->id() ?? 1);
            $ipAddr  = ($assoc['share_ip'] ?? null) ?: $request->ip();
            $transAt = $assoc['share_transdate'] ?? null;
            try {
                $transAt = $transAt ? \Carbon\Carbon::parse($transAt)->format('Y-m-d H:i:s') : \Carbon\Carbon::now()->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $transAt = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
            }
            $endProc = ($assoc['share_end_month_proc'] ?? 'N') ?: 'N';

            // Upsert key: (member, period, doc_no) — prevents duplicates on re-import
            $keys = ['share_member_id' => $memberId, 'share_period' => $period, 'share_doc_no' => $docNo];

            $data = [
                'share_member_id'     => $memberId,
                'share_amount_paying' => $amountNum,
                'share_paid_by'       => $paidBy,
                'share_period'        => $period,
                'share_description'   => $desc,
                'share_doc_no'        => $docNo,
                'share_date_paid'     => $datePaid,
                'share_by'            => $byUser,
                'share_ip'            => $ipAddr,
                'share_transdate'     => $transAt,
                'share_end_month_proc' => $endProc,
            ];

            $exists = DB::table('sacco_shares')->where($keys)->exists();
            DB::table('sacco_shares')->updateOrInsert($keys, $data);
            $exists ? $updated++ : $inserted++;
        }

        $msg = "{$inserted} deposits inserted, {$updated} updated.";
        if (!empty($skipped)) {
            $msg .= " Skipped " . count($skipped) . " rows (unmatched member or invalid period).";
            session()->flash('skipped_rows', $skipped);
        }

        return back()->with('success', $msg);
    }

    public function showLoansTakenImportForm()
    {
        return view('import.loans-taken');
    }

    public function importLoansTaken(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->getRealPath();

        // Read CSV (PHP 8.4-safe: specify escape)
        $raw = array_map(function ($line) {
            return str_getcsv($line, ',', '"', '\\');
        }, file($path));

        if (empty($raw) || count($raw) < 2) {
            return back()->with('error', 'CSV is empty or missing data rows.');
        }

        // Normalize header
        $header = array_map(function ($h) {
            $h = trim($h ?? '');
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip BOM
            $h = preg_replace('/\s+/', '_', $h);
            return strtolower($h);
        }, array_shift($raw));

        // Build member lookups from DB
        $members = DB::table('sacco_members')->select('member_id', 'member_name', 'member_sacco_id')->get();
        $nameToId = [];
        $codeToId = [];
        foreach ($members as $m) {
            if (!empty($m->member_name)) {
                $key = strtoupper(trim(preg_replace('/\s+/', ' ', $m->member_name)));
                $nameToId[$key] = (int) $m->member_id;
            }
            if (!empty($m->member_sacco_id)) {
                $codeToId[strtoupper(trim($m->member_sacco_id))] = (int) $m->member_id;
            }
        }

        // Helpers
        $toNum = function ($v) {
            if ($v === null || $v === '') return null;
            $s = preg_replace('/[^\d.\-]/', '', (string)$v);
            return ($s === '' || !is_numeric($s)) ? null : (float)$s;
        };
        $toDate = function ($v, $fmt = 'Y-m-d') {
            if (!$v) return null;
            try {
                return \Carbon\Carbon::parse($v)->format($fmt);
            } catch (\Throwable $e) {
                return null;
            }
        };
        $toPeriod = function ($v) {
            // Expect YYYYMM; if not, try date -> YYYYMM
            if (!$v) return null;
            $digits = preg_replace('/\D/', '', (string)$v);
            if (strlen($digits) === 6) return $digits;
            try {
                return \Carbon\Carbon::parse($v)->format('Ym');
            } catch (\Throwable $e) {
                return null;
            }
        };
        $toMonths = function ($v) {
            // If already months, return int; if days, convert/round; min 1 when >0
            if ($v === null || $v === '') return null;
            $n = (float) preg_replace('/[^\d.\-]/', '', (string)$v);
            if ($n <= 0) return null;
            // If looks like days (> 31), treat as days -> months
            if ($n > 31) {
                $m = (int) round($n / 30.0);
                return max($m, 1);
            }
            // else assume months already
            return (int) round($n);
        };

        $inserted = 0;
        $updated  = 0;
        $skipped  = [];

        foreach ($raw as $i => $row) {
            $assoc = [];
            foreach ($header as $idx => $key) {
                $assoc[$key] = isset($row[$idx]) ? trim($row[$idx]) : null;
            }

            // ---- Resolve member_id
            // Prefer sacco code if present; else by uppercased name
            $memberId = null;

            if (!empty($assoc['member_sacco_id'])) {
                $memberId = $codeToId[strtoupper($assoc['member_sacco_id'])] ?? null;
            }
            if (!$memberId) {
                // Our CSV has loan_member as the name string
                $nm = $assoc['loan_member'] ?? $assoc['member_name'] ?? null;
                if ($nm) {
                    $nmKey = strtoupper(trim(preg_replace('/\s+/', ' ', $nm)));
                    $memberId = $nameToId[$nmKey] ?? null;
                }
            }
            if (!$memberId) {
                $skipped[] = $assoc['loan_member'] ?? $assoc['member_name'] ?? ('row ' . ($i + 2));
                continue;
            }

            // ---- Pull/normalize values from CSV
            $loanAmount   = $toNum($assoc['loan_amount'] ?? null); // principal taken (we exported this)
            $interestPay  = $toNum($assoc['loan_interest_payable'] ?? null); // likely 0 from the CSV
            $interestEarn = $toNum($assoc['interest_earned'] ?? null);       // if present; else null

            // loan_monthly_repayment_amount per your rule = amount + interest (total)
            $monthlyRepayAmount = $toNum($assoc['loan_monthly_repayment_amount'] ?? null);
            if ($monthlyRepayAmount === null) {
                $monthlyRepayAmount = (float) ($loanAmount ?? 0) + (float) ($interestEarn ?? 0);
            }

            // payment period months (if days were given, convert/round)
            $loanPaymentPeriod = $toMonths($assoc['loan_payment_period'] ?? null);

            // periods (YYYYMM)
            $takenPeriod  = $toPeriod($assoc['loan_taken_period'] ?? null);
            $startPeriod  = $toPeriod($assoc['loan_start_deduction_period'] ?? null);
            $takenStart   = $toPeriod($assoc['loan_taken_start_period'] ?? null);
            if (!$takenStart) $takenStart = $startPeriod; // mirror

            // doc number & dates
            $docNo        = $assoc['loan_doc_no'] ?? null;
            $loanOn       = $toDate($assoc['loan_on'] ?? null, 'Y-m-d');

            // Defaults required by you
            $data = [
                'loan_member'                      => $memberId,
                'loan_loan_type'                   => 1,
                'loan_loan_category'               => 1,
                'loan_amount'                      => $loanAmount ?? 0,
                'loan_insurance'                   => 0,
                'loan_commision'                   => 0,
                'loan_taken_period'                => $takenPeriod,
                'loan_payment_period'              => $loanPaymentPeriod,                 // months
                'loan_interest_payable'            => $interestPay ?? 0,
                'loan_monthly_repayment_amount'    => $monthlyRepayAmount ?? 0,
                'loan_monthly_repayment_principal' => 0,
                'loan_amount_guaranteed'           => 0,
                'loan_loan_paid'                   => 0,
                'loan_doc_no'                      => $docNo,
                'loan_description'                 => $assoc['loan_description'] ?? 'Imported loan',
                'loan_batch_no'                    => $assoc['loan_batch_no'] ?? 'IMPORT',
                'loan_start_deduction_period'      => $startPeriod,
                 
                'loan_account_credited'            => 0,
                'loan_account_debited'             => 0,
                'loan_on'                          => $loanOn,
                'loan_by'                          => auth()->id() ?? 1,
                'loan_ip'                          => $request->ip(),
                'loan_stoped'                      => 0,
                'loan_email_sent'                  => 0,
                'loan_stopped_on'                  => null,
                'loan_stopped_by'                  => null,
                'loan_taken_start_period'          => $takenStart,
            ];

            // ---- Upsert key: choose fields that uniquely identify a loan record
            // This combo avoids duplicates on re-import:
            $keys = [
                'loan_member'       => $memberId,
                'loan_taken_period' => $data['loan_taken_period'],
                'loan_amount'       => $data['loan_amount'],
                'loan_doc_no'       => $data['loan_doc_no'],
            ];

            $exists = DB::table('sacco_loans')->where($keys)->exists();
            DB::table('sacco_loans')->updateOrInsert($keys, $data);
            $exists ? $updated++ : $inserted++;
        }

        $msg = "{$inserted} loans inserted, {$updated} updated.";
        if (!empty($skipped)) {
            $msg .= " Skipped " . count($skipped) . " rows (unmatched member).";
            session()->flash('skipped_rows', $skipped);
        }

        return back()->with('success', $msg);
    }
 

}
