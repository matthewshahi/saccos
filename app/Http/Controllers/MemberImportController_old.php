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
        $email = strtolower(str_replace(' ', '.', $memberName)) . '@noemail.com';

        // Check if the `member_sacco_id` already exists
        $existingMember = DB::table('sacco_members')->where('member_sacco_id', $memberSaccoId)->exists();

        if ($existingMember) {
            logger()->warning("Member with Sacco ID $memberSaccoId already exists. Skipping...");
            return; // Skip if member already exists
        }

        // Insert member data into `sacco_members`
        DB::table('sacco_members')->insert([
            'member_sacco_id' => $memberSaccoId,
            'member_name' => strtoupper(trim($memberName)),
            'member_date_joined' => $dateJoined,
            'member_email' => $email,
            'member_dept' => 1, // Hardcoded
            'member_total_share_capital' => $capitalAmount,
            'member_transdate' => now(),
        ]);

        // Fetch `member_id` based on `member_sacco_id`
        $memberId = DB::table('sacco_members')->where('member_sacco_id', $memberSaccoId)->value('member_id');

        // Insert capital into `sacco_capital_shares`
        if ($capitalAmount > 0) {
            DB::table('sacco_capital_shares')->insert([
                'share_capitalmember_id' => $memberId,
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
                    'share_member_id' => $memberId,
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
        $this->updateLedgers($memberId, $capitalAmount, $row);
    }

    /**
     * Update ledgers for shares and capital.
     */
    private function updateLedgers($memberId, $capitalAmount, $row)
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
                'accounts_trans_member_id' => $memberId,
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

    public function updatefLedgers()
    {
        // Fetch all shares and capital contributions
        $shares = DB::table('sacco_shares')->get();
        $capitalContributions = DB::table('sacco_capital_shares')->get();
    
        $skippedShares = [];
        $skippedCapital = [];
    
        // Process Shares
        foreach ($shares as $share) {
            $member = DB::table('sacco_members')->where('member_id', $share->share_member_id)->first();
    
            if ($member) {
                // Show member being processed
                echo "<br>" . $member->member_name . " (Member ID: " . $member->member_id . ") | " . $share->share_amount_paying ;    
                // Create ledger entries
                DB::table('sacco_accounts_trans')->insert([
                    [
                        'accounts_trans_member_id' => $member->member_id,
                        'accounts_trans_sub_account' => 12, // Credit member shares
                        'accounts_trans_period' => $share->share_period,
                        'accounts_trans_debit' => 0,
                        'accounts_trans_credit' => $share->share_amount_paying,
                        'accounts_trans_doc_no' => 'SHARE-' . $share->share_period,
                        'accounts_trans_decription' => 'Share Contribution for ' . $member->member_name,
                        'accounts_trans_dat_date' => $share->share_date_paid,
                        'accounts_trans_transdate' => $share->share_transdate,
                    ],
                    [
                        'accounts_trans_member_id' => $member->member_id,
                        'accounts_trans_sub_account' => 31, // Debit Adom account
                        'accounts_trans_period' => $share->share_period,
                        'accounts_trans_debit' => $share->share_amount_paying,
                        'accounts_trans_credit' => 0,
                        'accounts_trans_doc_no' => 'SHARE-' . $share->share_period,
                        'accounts_trans_decription' => 'Share Contribution for ' . $member->member_name,
                        'accounts_trans_dat_date' => $share->share_date_paid,
                        'accounts_trans_transdate' => $share->share_transdate,
                    ]
                ]);
    
                // Update sub-accounts
                DB::table('sacco_sub_account')->where('sub_account_id', 12)->increment('sub_account_credit', $share->share_amount_paying);
                DB::table('sacco_sub_account')->where('sub_account_id', 31)->increment('sub_account_debit', $share->share_amount_paying);
            } else {
                // Log skipped record
                $skippedShares[] = [
                    'share_id' => $share->share_id,
                    'member_id' => $share->share_member_id,
                    'amount' => $share->share_amount_paying
                ];
                logger()->warning("Skipped share record for Member ID: {$share->share_member_id}, Share ID: {$share->share_id}");
            }
        }
    
        // Process Capital Contributions
        foreach ($capitalContributions as $capital) {
            $member = DB::table('sacco_members')->where('member_id', $capital->share_capitalmember_id)->first();
    
            if ($member) {
                // Show member being processed
                echo "Processing Capital Contribution for: " . $member->member_name . " (Member ID: " . $member->member_id . ")\n";
    
                // Create ledger entries
                DB::table('sacco_accounts_trans')->insert([
                    [
                        'accounts_trans_member_id' => $member->member_id,
                        'accounts_trans_sub_account' => 44, // Credit member capital
                        'accounts_trans_period' => $capital->share_capitalperiod,
                        'accounts_trans_debit' => 0,
                        'accounts_trans_credit' => $capital->share_capitalamount_paying,
                        'accounts_trans_doc_no' => 'CAPITAL-' . $capital->share_capitalperiod,
                        'accounts_trans_decription' => 'Capital Contribution for ' . $member->member_name,
                        'accounts_trans_dat_date' => $capital->share_capitaldate_paid,
                        'accounts_trans_transdate' => $capital->share_capitaltransdate,
                    ],
                    [
                        'accounts_trans_member_id' => $member->member_id,
                        'accounts_trans_sub_account' => 31, // Debit Adom account
                        'accounts_trans_period' => $capital->share_capitalperiod,
                        'accounts_trans_debit' => $capital->share_capitalamount_paying,
                        'accounts_trans_credit' => 0,
                        'accounts_trans_doc_no' => 'CAPITAL-' . $capital->share_capitalperiod,
                        'accounts_trans_decription' => 'Capital Contribution for ' . $member->member_name,
                        'accounts_trans_dat_date' => $capital->share_capitaldate_paid,
                        'accounts_trans_transdate' => $capital->share_capitaltransdate,
                    ]
                ]);
    
                // Update sub-accounts
                DB::table('sacco_sub_account')->where('sub_account_id', 44)->increment('sub_account_credit', $capital->share_capitalamount_paying);
                DB::table('sacco_sub_account')->where('sub_account_id', 31)->increment('sub_account_debit', $capital->share_capitalamount_paying);
            } else {
                // Log skipped record
                $skippedCapital[] = [
                    'capital_id' => $capital->share_capitalid,
                    'member_id' => $capital->share_capitalmember_id,
                    'amount' => $capital->share_capitalamount_paying
                ];
                logger()->warning("Skipped capital record for Member ID: {$capital->share_capitalmember_id}, Capital ID: {$capital->share_capitalid}");
            }
        }
    
        // Log summary of skipped records
        logger()->info('Skipped Shares:', $skippedShares);
        logger()->info('Skipped Capital Contributions:', $skippedCapital);
    
        // Update Member Totals
        $members = DB::table('sacco_members')->get();
        foreach ($members as $member) {
            $totalShares = DB::table('sacco_shares')->where('share_member_id', $member->member_id)->sum('share_amount_paying');
            $totalCapital = DB::table('sacco_capital_shares')->where('share_capitalmember_id', $member->member_id)->sum('share_capitalamount_paying');
    
            DB::table('sacco_members')->where('member_id', $member->member_id)->update([
                'member_total_share' => $totalShares,
                'member_total_share_capital' => $totalCapital,
            ]);
        }
    
        // Update Main Accounts
        $totalDebit = DB::table('sacco_accounts_trans')->sum('accounts_trans_debit');
        $totalCredit = DB::table('sacco_accounts_trans')->sum('accounts_trans_credit');
    
        DB::table('sacco_main_account')->where('main_account_id', 10)->update(['main_account_debit' => $totalDebit]);
        DB::table('sacco_main_account')->where('main_account_id', 5)->update(['main_account_credit' => $totalCredit]);
    
        return response()->json(['status' => 'success', 'message' => 'Ledgers updated successfully!']);
    }
}