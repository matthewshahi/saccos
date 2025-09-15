<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ProcessTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        Log::info('Starting ProcessTransactionsJob at: ' . now());

        $defaultMpesaIn = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');
        $defaultShareAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_share_account')
            ->value('default_value');

        if (!$defaultMpesaIn || !$defaultShareAccount) {
            Log::error("Missing default accounts for processing transactions.");
            return;
        }

        // Fetch unprocessed C2B transactions (limit to 100 records)
        $c2bTransactions = DB::table('c2b_payments')
            ->where('processed', 'No')
            ->orderBy('created_at', 'asc')
            ->limit(60)
            ->get();
        Log::info('Fetched C2B Transactions', ['transactions' => $c2bTransactions->toArray()]);

        // Process C2B transactions
        foreach ($c2bTransactions as $transaction) {
            try {
                Log::info("Processing C2B transaction ID: {$transaction->id}");
                $this->processTransaction($transaction);
            } catch (\Exception $e) {
                Log::error("Failed to process transaction ID {$transaction->id}: {$e->getMessage()}");
            }
        }

        Log::info('Finished ProcessTransactionsJob at: ' . now());
    }

    private function processTransaction($transaction)
    {
        // Clean and normalize the reference
        $reference = strtoupper(trim(str_replace(' ', '', $transaction->bill_ref_number)));
        Log::info("Normalized transaction reference: $reference");

        if (str_starts_with($reference, 'SH')) {
            Log::info("Identified as a Share transaction for reference: $reference");
            $this->processShares($reference, $transaction);
        } elseif (str_starts_with($reference, 'LN')) {
            Log::info("Identified as a Loan transaction for reference: $reference");
            $this->processLoans($reference, $transaction);
        } 
         else {
    Log::info("Trying FOSA fallback for reference: $reference");
    $this->processFallbackTransaction($transaction->bill_ref_number, $transaction);
}

        // Mark transaction as processed
        DB::table('c2b_payments')
            ->where('id', $transaction->id)
            ->update([
                'processed' => 'Yes',
                'processed_date' => Carbon::now(),
            ]);
        Log::info("Transaction ID {$transaction->id} marked as processed.");
    }

    private function processShares($reference, $transaction)
    {
        $memberId = ltrim($reference, 'SH');
        Log::info("Processing shares for Member ID: $memberId");

        // Validate default accounts
        $defaultMpesaIn = DB::table('sacco_defaults')->where('default_name', 'default_mpesa_in_account')->value('default_value');
        $defaultShareAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_account')->value('default_value');

        if (!$defaultMpesaIn || !$defaultShareAccount) {
            Log::error("Missing default accounts for processing shares: Member ID {$memberId}");
            return;
        }

        // Update member's shares
        DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->increment('member_total_share', $transaction->transaction_amount);
        Log::info("Updated shares for Member ID: $memberId by Amount: {$transaction->transaction_amount}");

        // Insert into sacco_shares
        $currentPeriod = $this->getCurrentPeriod();
        $description = "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";
        $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

        DB::table('sacco_shares')->insert([
            'share_member_id' => $memberId,
            'share_amount_paying' => $transaction->transaction_amount,
            'share_paid_by' => 'mPesa',
            'share_period' => $currentPeriod->period_name,
            'share_description' => $description,
            'share_doc_no' => $docNo,
            'share_date_paid' => Carbon::now(),
            'share_end_month_proc' => 'N',
            'share_by' => auth()->id() ?? null,
            'share_ip' => request()->ip() ?? '127.0.0.1',
        ]);

        // Update ledger entries
        $this->updateSaccoAccountsTrans($defaultMpesaIn, $transaction->transaction_amount, 0, $docNo, "Shares Deposit - $description", $transaction->transaction_time);
        $this->updateSaccoAccountsTrans($defaultShareAccount, 0, $transaction->transaction_amount, $docNo, "Shares Deposit - $description", $transaction->transaction_time);
    }

    private function processLoans($reference, $transaction)
{
    $loanId = ltrim($reference, 'LN');
    Log::info("Processing loan payment for Loan ID: $loanId");

    $defaultMpesaIn = DB::table('sacco_defaults')->where('default_name', 'default_mpesa_in_account')->value('default_value');

    $loanDetails = DB::table('sacco_loans')
        ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
        ->select('sacco_loans.*', 'sacco_loan_types.loan_type_acount', 'sacco_loan_types.loan_type_int_account', 'sacco_loan_types.loan_type_interest_type', 'sacco_loan_types.loan_type_interest')
        ->where('sacco_loans.loan_id', $loanId)
        ->first();

    if (!$defaultMpesaIn || !$loanDetails) {
        Log::error("Missing default accounts or loan details for Loan ID {$loanId}");
        return;
    }

    // Calculate principal and interest
    $principalPayment = $transaction->transaction_amount;
    $interest = 0;
    $currentPeriod = $this->getCurrentPeriod();

    // Check if there are any payments for this loan in the current period with interest
    $existingPayment = DB::table('sacco_loan_payments')
        ->where('loan_payments_loan_id', $loanId)
        ->where('loan_payments_period', $currentPeriod->period_name)
        ->where('loan_payments_interest', '>', 0)
        ->exists();

    if ($loanDetails->loan_type_interest_type === "FIXED INTEREST") {
        // Always charge interest for fixed interest
        $interest = $principalPayment - ($principalPayment * 100 / ($loanDetails->loan_type_interest + 100));
    } elseif (!$existingPayment) {
        // Charge interest for reducing balance only if no interest has been paid in the current period
        $interest = ($loanDetails->loan_amount - $loanDetails->loan_loan_paid) * $loanDetails->loan_type_interest / 12 / 100;
    } else {
        Log::info("Interest skipped for reducing balance loan ID: $loanId as interest has already been paid in the current period.");
    }

    $principalPaid = $principalPayment - $interest;

    // Update loan balances
    DB::table('sacco_loans')
        ->where('loan_id', $loanId)
        ->increment('loan_loan_paid', $principalPaid);
    Log::info("Updated loan balance for Loan ID: $loanId by Principal: $principalPaid, Interest: $interest");

    // Insert into sacco_loan_payments
    $description = "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";
    $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

    DB::table('sacco_loan_payments')->insert([
        'loan_payments_amount' => $principalPaid,
        'loan_payments_interest' => $interest,
        'loan_payments_docno' => $docNo,
        'loan_payments_paid_on' => Carbon::now(),
        'loan_payments_loan_id' => $loanId,
        'loan_payments_period' => $currentPeriod->period_name,
        'loan_payments_description' => "Loan Payment - $description",
        'loan_payments_paid_in_by' => "MPesa",
        'loan_payments_ip' => request()->ip() ?? '127.0.0.1',
    ]);

    // Release guarantors
    $this->releaseGuarantors($loanId, $principalPaid);

    // Update ledger entries
    $this->updateSaccoAccountsTrans($defaultMpesaIn, $principalPaid + $interest, 0, $docNo, "Loan Payment - $description", $transaction->transaction_time);
    $this->updateSaccoAccountsTrans($loanDetails->loan_type_acount, 0, $principalPaid, $docNo, "Loan Principal - $description", $transaction->transaction_time);
    $this->updateSaccoAccountsTrans($loanDetails->loan_type_int_account, 0, $interest, $docNo, "Loan Interest - $description", $transaction->transaction_time);
}

    private function updateSaccoAccountsTrans($account, $debit, $credit, $docNo, $description, $date)
    {
        DB::table('sacco_accounts_trans')->insert([
            'accounts_trans_sub_account' => $account,
            'accounts_trans_period' => $this->getCurrentPeriod()->period_name ?? 'Unknown Period', // Current period or default
            'accounts_trans_debit' => $debit,
            'accounts_trans_credit' => $credit,
            'accounts_trans_doc_no' => $docNo,
            'accounts_trans_decription' => $description,
            'accounts_trans_source' => 'MPesa', // Source of the transaction
            'accounts_trans_dat_date' => $date,
            'accounts_trans_transdate' => now(),
            'accounts_trans_user_id' => 999,
            'accounts_trans_ip' => request()->ip() ?? '127.0.0.1', // Client IP or fallback to localhost
            'accounts_trans_app_name' => 'mpesa', // Static value for app name
        ]);
        Log::info("Ledger entry updated for Sub Account: $account, Debit: $debit, Credit: $credit, Description: $description");
    }

    private function releaseGuarantors($loanId, $amount)
    {
        $loan = DB::table('sacco_loans')->where('loan_id', $loanId)->select('loan_amount_guaranteed', 'loan_member')->first();

        if (!$loan || $loan->loan_amount_guaranteed <= 0) {
            Log::error("Invalid loan guarantee details for Loan ID {$loanId}");
            return;
        }

        $totalLoanGuaranteed = $loan->loan_amount_guaranteed;
        $guarantors = DB::table('sacco_loan_guarantors')->where('loan_guar_loan_id', $loanId)->where('loan_guar_deleted', '!=', 'Y')->get();

        foreach ($guarantors as $guarantor) {
            $amountToFree = ($guarantor->loan_guar_amount_guaranteed / $totalLoanGuaranteed) * $amount;

            if ($guarantor->loan_guar_guarantor_id == $loan->loan_member) {
                DB::table('sacco_members')->where('member_id', $guarantor->loan_guar_guarantor_id)->decrement('member_tied_shares_self', $amountToFree);
            } else {
                DB::table('sacco_members')->where('member_id', $guarantor->loan_guar_guarantor_id)->decrement('member_tied_shares', $amountToFree);
            }

            DB::table('sacco_loan_guarantors')->where('loan_guar_id', $guarantor->loan_guar_id)->increment('loan_guar_amount_freed', $amountToFree);
        }
    }

    private function getCurrentPeriod()
    {
        return DB::table('sacco_period')->where('period_active', 'Y')->where('period_deleted', '<>', 'Y')->first();
    }

 
private function processFallbackTransaction($reference, $transaction)
{
    $id = $transaction->id;
    $parts = preg_split('/\s+/', trim($reference), 2);
    $idPart = $parts[0] ?? '';
    $descPart = strtolower($parts[1] ?? '');
    $cleanedId = preg_replace('/\D/', '', $idPart);

    if (!is_numeric($cleanedId) || strlen($cleanedId) < 6) {
        Log::warning("Fallback: Invalid ID format '$reference'");
        return;
    }

    $members = DB::table('sacco_members')->where('member_national_id', $cleanedId)->get();
    if ($members->count() !== 1) {
        Log::warning("Fallback: Found {$members->count()} matches for ID '$cleanedId'. Skipping.");
        return;
    }

    $member = $members->first();
    $memberId = $member->member_id;
    $period = (object)['period_name' => now()->format('Ym')];
    $amount = $transaction->transaction_amount;
    $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";
    
    $senderName = $transaction->first_name ?: $transaction->msisdn;
$description = ($descPart ?: 'Mpesa Deposit') 
             . " - $senderName - Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";
             
             $now = Carbon::now();
    $ip = request()->ip() ?? '127.0.0.1';
    $userId = auth()->id() ?? 999;

    $mpesaAccount = DB::table('sacco_defaults')->where('default_name', 'default_mpesa_in_account')->value('default_value');
    $fosaAccount = DB::table('sacco_defaults')->where('default_name', 'default_fosa_account')->value('default_value');
    $shareAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_account')->value('default_value');
    $capitalAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_capital_account')->value('default_value');

    if (!$mpesaAccount || !$period) {
        Log::error("Fallback: Missing required defaults (mpesa_in/period)");
        return;
    }
if (Str::contains($descPart, ['share', 'shares', 'deposit', 'deposits'])) {
     
        DB::table('sacco_shares')->insert([
            'share_member_id' => $memberId,
            'share_amount_paying' => $amount,
            'share_paid_by' => 'MPesa',
            'share_period' => $period->period_name,
            'share_description' => $description,
            'share_doc_no' => $docNo,
            'share_date_paid' => $now,
            'share_end_month_proc' => 'N',
            'share_by' => $userId,
            'share_ip' => $ip,
            'share_transdate' => $now,
        ]);

        DB::table('sacco_members')->where('member_id', $memberId)->increment('member_total_share', $amount);

        $this->updateSaccoAccountsTrans($mpesaAccount, $amount, 0, $docNo, "Share Deposit - $description", $transaction->transaction_time);
        $this->updateSaccoAccountsTrans($shareAccount, 0, $amount, $docNo, "Share Deposit - $description", $transaction->transaction_time);

        Log::info("SHARE fallback: Completed for Member ID: $memberId");
    } elseif (Str::contains($descPart, 'capital')) {
        DB::table('sacco_capital_shares')->insert([
            'share_capitalmember_id' => $memberId,
            'share_capitalamount_paying' => $amount,
            'share_capitalpaid_by' => 'MPesa',
            'share_capitalperiod' => $period->period_name,
            'share_capitaldescription' => $description,
            'share_capitaldoc_no' => $docNo,
            'share_capitaldate_paid' => $now,
            'share_capitalend_month_proc' => 'N',
            'share_capitalby' => $userId,
            'share_capitalip' => $ip,
            'share_capitaltransdate' => $now,
        ]);

        DB::table('sacco_members')->where('member_id', $memberId)->increment('member_total_share_capital', $amount);

        $this->updateSaccoAccountsTrans($mpesaAccount, $amount, 0, $docNo, "Capital Deposit - $description", $transaction->transaction_time);
        $this->updateSaccoAccountsTrans($capitalAccount, 0, $amount, $docNo, "Capital Deposit - $description", $transaction->transaction_time);

        Log::info("CAPITAL fallback: Completed for Member ID: $memberId");
    } else {
        DB::table('sacco_fosas')->insert([
            'fosa_member_id' => $memberId,
            'fosa_amount_paying' => $amount,
            'fosa_paid_by' => 'MPesa',
            'fosa_period' => $period->period_name,
            'fosa_description' => $description,
            'fosa_doc_no' => $docNo,
            'fosa_date_paid' => $now,
            'fosa_end_month_proc' => 'N',
            'fosa_by' => $userId,
            'fosa_ip' => $ip,
            'fosa_transdate' => $now,
        ]);

        DB::table('sacco_members')->where('member_id', $memberId)->increment('member_total_fosa', $amount);

        $this->updateSaccoAccountsTrans($mpesaAccount, $amount, 0, $docNo, "FOSA Deposit - $description", $transaction->transaction_time);
        $this->updateSaccoAccountsTrans($fosaAccount, 0, $amount, $docNo, "FOSA Deposit - $description", $transaction->transaction_time);

        Log::info("FOSA fallback: Completed for Member ID: $memberId");
    }

    Log::info("Transaction fallback processing complete for reference: $reference");
    DB::table('c2b_payments')->where('id', $id)->update([
    'processed' => 'Yes',
    'processed_date' => now(),
]);
}
}