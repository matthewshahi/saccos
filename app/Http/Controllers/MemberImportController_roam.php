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
        $data = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', array_shift($data));

        $imported = 0;
        foreach ($data as $row) {
            $rowData = array_combine($header, $row);

            // Clean member name
            $memberName = isset($rowData['member_name']) ? strtoupper(preg_replace('/\s+/', ' ', trim($rowData['member_name']))) : null;

            // Normalize phone number
            $rawPhone = preg_replace('/\s+/', '', $rowData['member_phone_no'] ?? '');
            if (preg_match('/^(07|01)\d{8}$/', $rawPhone)) {
                $memberPhone = '254' . substr($rawPhone, 1);
            } elseif (preg_match('/^254\d{9}$/', $rawPhone)) {
                $memberPhone = $rawPhone;
            } else {
                $memberPhone = $rawPhone; // Use as-is
            }

            // Generate sacco ID: 0001, 0002, etc.
            $generatedSaccoId = str_pad($imported + 1, 4, '0', STR_PAD_LEFT);

            // Insert into DB
            DB::table('sacco_members')->insert([
                'member_name' => $memberName,
                'member_national_id' => $rowData['member_national_id'] ?? null,
                'member_phone_no' => $memberPhone,
                'member_dob' => $rowData['member_dob'] ?? null,
                'member_sacco_id' => $generatedSaccoId,

                'member_active' => 'Y',
                'member_transdate' => $rowData['member_transdate'] ?? now(),
                'member_date_joined' => $rowData['member_date_joined'] ?? now(),

                'member_dept' => 1,
                'member_postal_address' => $rowData['member_postal_address'] ?? null,
                'member_gender' => $rowData['member_gender'] ?? null,
                'member_email' => $rowData['member_email'] ?? null,

                'member_share_contr_monthly' => $rowData['member_share_contr_monthly'] ?? 0,
                'member_fosa_contr_monthly' => $rowData['member_fosa_contr_monthly'] ?? 0,
                'member_total_share' => $rowData['member_total_share'] ?? 0,
                'member_total_fosa' => $rowData['member_total_fosa'] ?? 0,
                'member_total_loan' => $rowData['member_total_loan'] ?? 0,
                'member_total_share_capital' => $rowData['member_total_share_capital'] ?? 0,
                'member_tied_shares' => $rowData['member_tied_shares'] ?? 0,
                'member_tied_shares_self' => $rowData['member_tied_shares_self'] ?? 0,

                'member_position' => 1,
            ]);

            $imported++;
        }

        return redirect()->back()->with('success', "$imported members imported successfully.");
    }


    public function importNextOfKin(Request $request)
{
    $request->validate([
        'csv_file' => 'required|file|mimes:csv,txt',
    ]);

    $file = $request->file('csv_file');
    $data = array_map('str_getcsv', file($file->getRealPath()));
    $header = array_map('trim', array_shift($data));

    $inserted = 0;
    $skipped = 0;

    foreach ($data as $row) {
        $rowData = array_combine($header, $row);

        $memberName = strtoupper(preg_replace('/\s+/', ' ', trim($rowData['member_name'] ?? '')));
        $kinName = strtoupper(preg_replace('/\s+/', ' ', trim($rowData['kin_name'] ?? '')));
        $kinPhone = preg_replace('/\s+/', '', $rowData['kin_phone'] ?? '');

        // Match member
        $member = DB::table('sacco_members')->where('member_name', $memberName)->first();

        if ($member) {
            DB::table('sacco_next_of_kin')->insert([
                'kin_member_id'    => $member->member_id,
                'kin_names'        => $kinName,
                'kin_address'      => $kinPhone,
                'kin_national_id'  => null,
                'kin_percent'      => '100',
                'kin_relationship' => 1,
                'kin_user_id'      => null,
                'kin_ip'           => request()->ip(),
                'kin_transdate'    => now(),
            ]);
            $inserted++;
        } else {
            $skipped++;
        }
    }

    return redirect()->back()->with('success', "$inserted next of kin imported. $skipped skipped (no matching member).");
}

public function showImportTransactionsForm()
    {
        return view('import.transactions');
    }

    public function importSavingsAndShares(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $data = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', array_shift($data));

        $imported = 0;
        $skipped = 0;

        foreach ($data as $row) {
            $rowData = array_combine($header, $row);

            $memberName = $rowData['NAME'] ?? '';
            $memberId = $this->findMemberIdByApproxName($memberName);

            if (!$memberId) {
                $skipped++;
                continue;
            }

            $savings = (int)($rowData['savings'] ?? 0);
            $shares = (int)($rowData['shares'] ?? 0);
 

try {
    $datePaid = Carbon::parse($rowData['DATE']);
    if ($datePaid->year < 2000 || $datePaid->year > now()->year + 1) {
        $datePaid = now();
    }
} catch (\Exception $e) {
    $datePaid = now();
}

            if ($savings > 0) {
                DB::table('sacco_shares')->insert([
                    'share_member_id' => $memberId,
                    'share_amount_paying' => $savings,
                    'share_paid_by' => 'SYSTEM',
                    'share_period' => now()->format('Ym'),
                    'share_description' => $rowData['NARRATION'] ?? 'CSV IMPORT',
                    'share_doc_no' => null,
                    'share_date_paid' => $datePaid,
                    'share_by' => 1,
                    'share_ip' => $request->ip(),
                    'share_transdate' => now(),
                    'share_end_month_proc' => 'N',
                ]);
            }

            if ($shares > 0) {
                DB::table('sacco_capital_shares')->insert([
                    'share_capitalmember_id' => $memberId,
                    'share_capitalamount_paying' => $shares,
                    'share_capitalpaid_by' => 'SYSTEM',
                    'share_capitalperiod' => now()->format('Ym'),
                    'share_capitaldescription' => $rowData['NARRATION'] ?? 'CSV IMPORT',
                    'share_capitaldoc_no' => null,
                    'share_capitaldate_paid' => $datePaid,
                    'share_capitalby' => 1,
                    'share_capitalip' => $request->ip(),
                    'share_capitaltransdate' => now(),
                    'share_capitalend_month_proc' => 'N',
                ]);
            }

            $imported++;
        }

        return redirect()->back()->with('success', "$imported rows imported. $skipped skipped (no matching member).");
    }

    private function findMemberIdByApproxName($name)
    {
        $cleanedInput = strtoupper(preg_replace('/\s+/', ' ', trim($name)));
        $inputParts = explode(' ', $cleanedInput);

        $members = DB::table('sacco_members')->select('member_id', 'member_name')->get();

        foreach ($members as $member) {
            $cleanedMember = strtoupper(preg_replace('/\s+/', ' ', trim($member->member_name)));
            $memberParts = explode(' ', $cleanedMember);

            $matched = true;
            foreach ($inputParts as $part) {
                if (!in_array($part, $memberParts)) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                return $member->member_id;
            }
        }

        return null;
    }

}