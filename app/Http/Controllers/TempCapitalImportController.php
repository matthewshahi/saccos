<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class TempCapitalImportController extends Controller
{
    public function importCapital(Request $request)
    {
        // Validate that a file is uploaded
        $request->validate([
            'capital_file' => 'required|file|mimes:xls,xlsx',
        ]);

        // Load the uploaded Excel file
        $file = $request->file('capital_file');
        $data = Excel::toArray([], $file)[0]; // Load data using Laravel Excel

        // Check if data exists
        if (empty($data) || count($data) <= 1) {
            return response()->json(['success' => false, 'message' => 'No valid data found in the file.']);
        }

        // Skip the header row
        $rows = array_slice($data, 1);
        $todayDate = Carbon::now()->format('Y-m-d');
        $userIp = $request->ip();

        // Process each row
        foreach ($rows as $row) {
            if (!isset($row[0], $row[1])) {
                continue; // Skip invalid rows
            }

            $saccoId = trim($row[0]); // Sacco ID
            $capital = floatval($row[1]); // Capital amount

            // Fetch member_id based on member_sacco_id
            $member = DB::table('sacco_members')
                ->where('member_sacco_id', $saccoId)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => "Member with Sacco ID {$saccoId} not found.",
                ]);
            }

            // Insert into sacco_capital_shares table
            DB::table('sacco_capital_shares')->insert([
                'share_capitalmember_id' => $member->member_id,
                'share_capitalamount_paying' => $capital,
                'share_capitalperiod' => '202212', // Static period
                'share_capitaldescription' => 'Imported data',
                'share_capitaldoc_no' => 'Imported data',
                'share_capitaldate_paid' => $todayDate,
                'share_capitalip' => $userIp,
                'share_capitaltransdate' => $todayDate,
            ]);

            // Update sacco_members table to set member_total_share_capital
            DB::table('sacco_members')
                ->where('member_id', $member->member_id)
                ->update([
                    'member_total_share_capital' => $capital,
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Capital shares imported and member total share capital updated successfully.',
        ]);
    }
}