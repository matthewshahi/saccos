<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MemberImportController extends Controller
{
    /**
     * Show the member import form.
     */
    public function showForm()
    {
        return view('import.members'); // Ensure you have this view
    }

    /**
     * Handle the CSV import.
     */
    public function import(Request $request)
    {
        // Validate the uploaded file
        $validator = Validator::make($request->all(), [
            'import_file' => 'required|mimes:csv,txt|max:2048', // Only allow CSV files up to 2MB
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Open the uploaded file
        $file = $request->file('import_file');
        $fileHandle = fopen($file, 'r');

        if (!$fileHandle) {
            return redirect()->back()->withErrors(['error' => 'Failed to open the uploaded file.']);
        }

        try {
            $header = fgetcsv($fileHandle); // Read and skip the header row
            while (($row = fgetcsv($fileHandle)) !== false) {
                $this->processRow($row);
            }

            fclose($fileHandle);

            return redirect()->route('import.members.form')->with('success', 'Members imported successfully!');
        } catch (\Exception $e) {
            fclose($fileHandle);
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Process each row of the CSV file.
     */
    private function processRow(array $row)
    {
        // Extract row data
        $memberSaccoId = trim($row[0]);
        $memberName = trim($row[1]);
        $capitalAmount = (float)trim($row[3]);
        $rawDate = trim($row[2]);

        // Parse British date format (d/m/Y)
        try {
            $dateJoined = Carbon::createFromFormat('d/m/Y', $rawDate)->format('Y-m-d');
        } catch (\Exception $e) {
            logger()->warning("Invalid date format for member: $memberSaccoId, raw date: $rawDate. Skipping...");
            return; // Skip this row if date is invalid
        }

        // Generate email from member name
        $email = strtolower(str_replace(' ', '.', $memberName)) . '@adomsacco.com';

        // Check if the `member_sacco_id` already exists
        $existingMember = DB::table('sacco_members')->where('member_sacco_id', $memberSaccoId)->exists();

        if ($existingMember) {
            logger()->warning("Member with Sacco ID $memberSaccoId already exists. Skipping...");
            return; // Skip if member already exists
        }

        // Insert member data into `sacco_members`
        DB::table('sacco_members')->insert([
            'member_sacco_id' => $memberSaccoId,
            'member_name' => $memberName,
            'member_date_joined' => $dateJoined,
            'member_email' => $email,
            'member_dept' => 1, // Hardcoded
            'member_total_share_capital' => $capitalAmount,
            'member_transdate' => now(),
        ]);

        // Insert capital into `sacco_capital_shares`
        if ($capitalAmount > 0) {
            DB::table('sacco_capital_shares')->insert([
                'share_capitalmember_id' => $memberSaccoId,
                'share_capitalamount_paying' => $capitalAmount,
                'share_capitalperiod' => date('Ym'), // Default to current month
                'share_capitaldescription' => 'Initial Capital Deposit',
                'share_capitaldoc_no' => date('Ym'),
                'share_capitaldate_paid' => now(),
                'share_capitaltransdate' => now(),
            ]);
        }

        // Process share contributions from the CSV (columns 4 onward)
        foreach ($row as $key => $shareAmount) {
            if ($key > 3 && $shareAmount > 0) {
                // Calculate share period
                $monthOffset = $key - 3;
                $sharePeriod = Carbon::create(2024, 2, 1)->addMonths($monthOffset)->format('Ym');

                DB::table('sacco_shares')->insert([
                    'share_member_id' => $memberSaccoId,
                    'share_amount_paying' => $shareAmount,
                    'share_period' => $sharePeriod,
                    'share_description' => 'Monthly Share Contribution',
                    'share_doc_no' => $sharePeriod,
                    'share_date_paid' => now(),
                    'share_transdate' => now(),
                ]);
            }
        }

        // Update ledgers for shares and capital
        $this->updateLedgers($memberSaccoId, $capitalAmount, $row);
    }

    /**
     * Update ledgers for shares and capital.
     */
    private function updateLedgers($memberSaccoId, $capitalAmount, $row)
    {
        $ledgerEntries = [];

        // Add capital entries
        if ($capitalAmount > 0) {
            $ledgerEntries[] = [
                'debit' => 0,
                'credit' => $capitalAmount,
                'sub_account' => 44,
                'description' => 'Capital Credit'
            ];
            $ledgerEntries[] = [
                'debit' => $capitalAmount,
                'credit' => 0,
                'sub_account' => 31,
                'description' => 'Capital Debit'
            ];
        }

        // Add share entries
        foreach ($row as $key => $shareAmount) {
            if ($key > 3 && $shareAmount > 0) {
                $sharePeriod = Carbon::create(2024, 2, 1)->addMonths($key - 3)->format('Ym');
                $ledgerEntries[] = [
                    'debit' => 0,
                    'credit' => $shareAmount,
                    'sub_account' => 12,
                    'description' => 'Share Credit'
                ];
                $ledgerEntries[] = [
                    'debit' => $shareAmount,
                    'credit' => 0,
                    'sub_account' => 31,
                    'description' => 'Share Debit'
                ];
            }
        }

        // Insert ledger entries into `sacco_accounts_trans`
        foreach ($ledgerEntries as $entry) {
            DB::table('sacco_accounts_trans')->insert([
                'accounts_trans_member_id' => $memberSaccoId,
                'accounts_trans_sub_account' => $entry['sub_account'],
                'accounts_trans_period' => date('Ym'),
                'accounts_trans_debit' => $entry['debit'],
                'accounts_trans_credit' => $entry['credit'],
                'accounts_trans_doc_no' => date('Ym'),
                'accounts_trans_decription' => $entry['description'],
                'accounts_trans_dat_date' => now(),
                'accounts_trans_transdate' => now(),
            ]);

            DB::table('sacco_sub_account')
                ->where('sub_account_id', $entry['sub_account'])
                ->update([
                    'sub_account_debit' => DB::raw('sub_account_debit + ' . $entry['debit']),
                    'sub_account_credit' => DB::raw('sub_account_credit + ' . $entry['credit']),
                ]);
        }

        // Update main accounts
        $debitTotal = collect($ledgerEntries)->sum('debit');
        $creditTotal = collect($ledgerEntries)->sum('credit');

        if ($debitTotal > 0) {
            DB::table('sacco_main_account')
                ->where('main_account_id', 10) // Debit
                ->increment('main_account_debit', $debitTotal);
        }

        if ($creditTotal > 0) {
            DB::table('sacco_main_account')
                ->where('main_account_id', 5) // Credit
                ->increment('main_account_credit', $creditTotal);
        }
    }
}