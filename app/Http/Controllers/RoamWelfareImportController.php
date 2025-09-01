<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoamWelfareImportController extends Controller
{
    public function showImportForm()
    {
        return view('import.roam_welfare');
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
        $skipped = 0;
        $skippedRows = [];

        foreach ($data as $index => $row) {
            $rowData = array_combine($header, $row);

            // Only process rows with "WELFARE" in the narration
            if (!isset($rowData['NARRATION']) || stripos($rowData['NARRATION'], 'WELFARE') === false) {
                continue;
            }

            $memberName = $rowData['NAME'] ?? '';
            $memberId = $this->findMemberIdByApproxName($memberName);

            if (!$memberId) {
                $skipped++;
                $skippedRows[] = 'row ' . ($index + 2);
                continue;
            }

            $amount = (int) ($rowData['AMOUNT'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            // Parse and sanitize date
            try {
                $datePaid = Carbon::parse($rowData['DATE'])->format('Y-m-d');
                $period = Carbon::parse($rowData['DATE'])->format('Ym');
            } catch (\Exception $e) {
                $datePaid = now()->format('Y-m-d');
                $period = now()->format('Ym');
            }

            DB::table('sacco_fosas')->insert([
                'fosa_member_id' => $memberId,
                'fosa_amount_paying' => $amount,
                'fosa_paid_by' => 'SYSTEM',
                'fosa_period' => $period,
                'fosa_description' => $rowData['NARRATION'],
                'fosa_doc_no' => null,
                'fosa_date_paid' => $datePaid,
                'fosa_by' => 1,
                'fosa_ip' => $request->ip(),
                'fosa_transdate' => now(),
                'fosa_end_month_proc' => 'N',
            ]);

            $imported++;
        }

        $message = "$imported welfare payments imported.";
        if ($skipped > 0) {
            $message .= " $skipped rows skipped due to unmatched members:<br>" . implode(', ', $skippedRows);
        }

        return back()->with('success', $message);
    }

    /**
     * Smart member name matcher: trims, capitalizes, handles reversed names
     */
    private function findMemberIdByApproxName($name)
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($name)));
        $parts = explode(' ', $normalized);

        if (count($parts) >= 2) {
            $firstLast = $parts[0] . ' ' . $parts[1];
            $lastFirst = $parts[1] . ' ' . $parts[0];
        } else {
            $firstLast = $normalized;
            $lastFirst = $normalized;
        }

        return DB::table('sacco_members')
            ->select('member_id')
            ->whereRaw("REPLACE(UPPER(TRIM(member_name)), '  ', ' ') = ?", [$normalized])
            ->orWhereRaw("UPPER(member_name) LIKE ?", ["%$normalized%"])
            ->orWhereRaw("UPPER(member_name) LIKE ?", ["%$firstLast%"])
            ->orWhereRaw("UPPER(member_name) LIKE ?", ["%$lastFirst%"])
            ->value('member_id');
    }
}