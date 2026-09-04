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
            Log::error("Missing default Mpesa accounts for processing transactions.");
            return;
        }

        // Fetch unprocessed C2B transactions (limit to 100 records)
        $c2bTransactions = DB::table('c2b_payments')
            ->where('processed', 'No')
            ->where('picked', 'No')
            ->orderBy('created_at', 'asc')
            ->limit(1)
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

    // private function processTransaction($transaction)
    // {




    //     $updated = DB::table('c2b_payments')
    //         ->where('id', $transaction->id)
    //         ->where('picked', 'No')
    //         ->update(['picked' => 'Yes']);

    //     if (!$updated) {
    //         // Another job already claimed this one
    //         return;
    //     }


    //     // Clean and normalize the reference
    //     $reference = strtoupper(trim(str_replace(' ', '', $transaction->bill_ref_number)));
    //     Log::info("Normalized transaction reference: $reference");




    //     // =====================================================
    //     // 1. CHECK IF OPERATOR PAYMENT (OPxxx-...)
    //     // =====================================================
    //     if (preg_match('/^OP[A-Z]{2}-\d+(-\d+)?$/', $reference)) {
    //         $this->processOperatorTransaction($reference, $transaction);
    //     }






    //     if (str_starts_with($reference, 'SH')) {
    //         Log::info("Identified as a Share transaction for reference: $reference");
    //         $this->processShares($reference, $transaction);
    //     } elseif (str_starts_with($reference, 'LN')) {
    //         Log::info("Identified as a Loan transaction for reference: $reference");
    //         $this->processLoans($reference, $transaction);
    //     } elseif (str_starts_with($reference, 'CA')) {
    //         Log::info("Identified as a Capital Shares transaction for reference: $reference");
    //         $this->processCapital($reference, $transaction);
    //     } elseif (str_starts_with($reference, 'RF')) {
    //         Log::info("Identified as a Registration Fee transaction for reference: $reference");

    //         $memberId = ltrim($reference, 'RF'); // strip "RF" prefix
    //         $period   = $this->getCurrentPeriod();
    //         $now      = Carbon::now();
    //         $docNo    = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";
    //         $ip       = request()->ip() ?? '127.0.0.1';
    //         $userId   = auth()->id() ?? 999;
    //         $desc     = "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";

    //         $mpesaAccount = DB::table('sacco_defaults')
    //             ->where('default_name', 'default_mpesa_in_account')
    //             ->value('default_value');

    //         $this->processRegistrationFee($memberId, $transaction, $desc, $docNo, $period, $now, $userId, $ip, $mpesaAccount);
    //     } else {
    //         Log::info("Trying FOSA/fallback for reference: $reference");
    //         $this->processFallbackTransaction($reference, $transaction);
    //     }

    //     // ✅ Mark transaction as processed ONCE here
    //     DB::table('c2b_payments')
    //         ->where('id', $transaction->id)
    //         ->update([
    //             'processed' => 'Yes',
    //             'processed_date' => Carbon::now(),
    //         ]);

    //     Log::info("Transaction ID {$transaction->id} marked as processed.");
    // }

    private function processTransaction($transaction)
    {
        /*
    |--------------------------------------------------------------------------
    | Claim Transaction
    |--------------------------------------------------------------------------
    | Prevent another queue worker from processing the same C2B transaction.
    */

        $updated = DB::table('c2b_payments')
            ->where('id', $transaction->id)
            ->where('picked', 'No')
            ->update([
                'picked' => 'Yes',
            ]);

        if (!$updated) {
            Log::warning("C2B transaction already picked by another job.", [
                'transaction_id' => $transaction->id,
            ]);

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Normalize Reference
    |--------------------------------------------------------------------------
    */

        $reference = strtoupper(trim(str_replace(' ', '', (string) $transaction->bill_ref_number)));

        Log::info("Normalized transaction reference: {$reference}", [
            'transaction_id' => $transaction->id,
            'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            'amount' => $transaction->transaction_amount ?? null,
        ]);

        /*
    |--------------------------------------------------------------------------
    | Operator Payment
    |--------------------------------------------------------------------------
    | Operator references are only rewritten here.
    | Actual SACCO posting continues in the normal routing below.
    */

        if (preg_match('/^OP[A-Z]{2}-\d+(-\d+)?$/', $reference)) {
            Log::info("Operator payment reference detected.", [
                'original_reference' => $reference,
                'transaction_id' => $transaction->id,
            ]);

            $operatorOk = $this->processOperatorTransaction($reference, $transaction);

            if (!$operatorOk) {
                Log::warning("Operator transaction failed before SACCO routing.", [
                    'transaction_id' => $transaction->id ?? null,
                    'reference' => $reference,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]);

                return;
            }

            Log::info("Operator payment reference rewritten.", [
                'rewritten_reference' => $reference,
                'transaction_id' => $transaction->id,
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Route Transaction
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | Direct references remain authoritative.
    |
    | LN{id} means the member specifically chose that loan.
    | Therefore the full amount goes to that loan through processLoans().
    */

        // $posted = false;

        // if (str_starts_with($reference, 'SH')) {
        //     Log::info("Identified as a Share transaction for reference: {$reference}");

        //     $result = $this->processShares($reference, $transaction);

        //     // Legacy-safe: old processShares() may still return void/null.
        //     $posted = ($result !== false);
        // } elseif (str_starts_with($reference, 'LN')) {
        //     Log::info("Identified as a Loan transaction for reference: {$reference}");

        //     // Full amount goes to the specified loan.
        //     // Do not cap direct LN payments here.
        //     $posted = $this->processLoans($reference, $transaction);
        // } elseif (str_starts_with($reference, 'CA')) {
        //     Log::info("Identified as a Capital Shares transaction for reference: {$reference}");

        //     $result = $this->processCapital($reference, $transaction);

        //     // Legacy-safe: old processCapital() may still return void/null.
        //     $posted = ($result !== false);
        // } elseif (str_starts_with($reference, 'RF')) {
        //     Log::info("Identified as a Registration Fee transaction for reference: {$reference}");

        //     $memberId = ltrim($reference, 'RF');
        //     $period   = $this->getCurrentPeriod();
        //     $now      = Carbon::now();
        //     $docNo    = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";
        //     $ip       = request()->ip() ?? '127.0.0.1';
        //     $userId   = auth()->id() ?? 999;
        //     $desc     = "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";

        //     $mpesaAccount = DB::table('sacco_defaults')
        //         ->where('default_name', 'default_mpesa_in_account')
        //         ->value('default_value');

        //     $result = $this->processRegistrationFee(
        //         $memberId,
        //         $transaction,
        //         $desc,
        //         $docNo,
        //         $period,
        //         $now,
        //         $userId,
        //         $ip,
        //         $mpesaAccount
        //     );

        //     // Legacy-safe: old processRegistrationFee() may still return void/null.
        //     $posted = ($result !== false);
        // } else {
        //     Log::info("Trying FOSA/fallback for reference: {$reference}");

        //     $result = $this->processFallbackTransaction($reference, $transaction);

        //     // Temporary legacy-safe handling.
        //     // Next step: make processFallbackTransaction() return true/false properly.
        //     $posted = ($result !== false);
        // }
        $posted = false;

        if (str_starts_with($reference, 'SH')) {
            Log::info("Identified as a Share transaction for reference: {$reference}");
            $posted = $this->processShares($reference, $transaction);
        } elseif (str_starts_with($reference, 'LN')) {
            Log::info("Identified as a Loan transaction for reference: {$reference}");

            // Direct LN{id}: full amount goes to selected loan.
            // No smart-allocation cap here.
            $posted = $this->processLoans($reference, $transaction);
        } elseif (str_starts_with($reference, 'CA')) {
            Log::info("Identified as a Capital Shares transaction for reference: {$reference}");
            $posted = $this->processCapital($reference, $transaction);
        } elseif (str_starts_with($reference, 'RF')) {
            Log::info("Identified as a Registration Fee transaction for reference: {$reference}");

            $memberId = ltrim($reference, 'RF');
            $period   = $this->getCurrentPeriod();
            $now      = Carbon::now();
            $docNo    = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";
            $ip       = request()->ip() ?? '127.0.0.1';
            $userId   = auth()->id() ?? 999;
            $desc     = "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";

            $mpesaAccount = DB::table('sacco_defaults')
                ->where('default_name', 'default_mpesa_in_account')
                ->value('default_value');

            $posted = $this->processRegistrationFee(
                $memberId,
                $transaction,
                $desc,
                $docNo,
                $period,
                $now,
                $userId,
                $ip,
                $mpesaAccount
            );
        } else {
            Log::info("Trying smart allocation for unspecified payment reference: {$reference}");
            $posted = $this->processSmartAllocation($reference, $transaction);
        }

        /*
    |--------------------------------------------------------------------------
    | Final Processed Mark
    |--------------------------------------------------------------------------
    | Only mark processed if a processor accepted the transaction.
    */

        if (!$posted) {
            Log::warning("C2B transaction was picked but not posted.", [
                'transaction_id' => $transaction->id,
                'reference' => $reference,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                'amount' => $transaction->transaction_amount ?? null,
            ]);

            return;
        }

        DB::table('c2b_payments')
            ->where('id', $transaction->id)
            ->update([
                'processed' => 'Yes',
                'processed_date' => Carbon::now(),
            ]);

        Log::info("Transaction ID {$transaction->id} marked as processed.", [
            'reference' => $reference,
            'mpesa_transaction_id' => $transaction->transaction_id ?? null,
        ]);
    }

    private function processShares($reference, $transaction): bool
    {
        $memberId = ltrim($reference, 'SH');

        Log::info("Processing shares for Member ID: {$memberId}", [
            'reference' => $reference,
            'transaction_id' => $transaction->id ?? null,
        ]);

        $amount = (float) ($transaction->transaction_amount ?? 0);

        if (empty($memberId) || !is_numeric($memberId)) {
            Log::error("Invalid share reference.", [
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        if ($amount <= 0) {
            Log::error("Invalid share payment amount.", [
                'member_id' => $memberId,
                'amount' => $amount,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        // Validate default accounts
        $defaultMpesaIn = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');

        $defaultShareAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_share_account')
            ->value('default_value');

        if (!$defaultMpesaIn || !$defaultShareAccount) {
            Log::error("Missing default accounts for processing shares.", [
                'member_id' => $memberId,
                'default_mpesa_in_account' => $defaultMpesaIn,
                'default_share_account' => $defaultShareAccount,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        try {
            $currentPeriod = $this->getCurrentPeriod();
            $description = "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";
            $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";
            $now = Carbon::now();

            // 1. Update member's shares
            $affected = DB::table('sacco_members')
                ->where('member_id', $memberId)
                ->increment('member_total_share', $amount);

            if ($affected === 0) {
                throw new \Exception("No sacco_members row updated for Member ID {$memberId}");
            }

            Log::info("Updated shares for Member ID: {$memberId}", [
                'amount' => $amount,
                'transaction_id' => $transaction->id ?? null,
            ]);

            // 2. Insert into sacco_shares
            DB::table('sacco_shares')->insert([
                'share_member_id'      => $memberId,
                'share_amount_paying'  => $amount,
                'share_paid_by'        => 'MPesa',
                'share_period'         => $currentPeriod,
                'share_description'    => $description,
                'share_doc_no'         => $docNo,
                'share_date_paid'      => $now,
                'share_end_month_proc' => 'N',
                'share_by'             => auth()->id() ?? null,
                'share_ip'             => request()->ip() ?? '127.0.0.1',
                'share_transdate'      => $now,
            ]);

            Log::info("Inserted sacco_shares record.", [
                'member_id' => $memberId,
                'amount' => $amount,
                'period' => $currentPeriod,
                'transaction_id' => $transaction->id ?? null,
            ]);

            // 3. Update ledger entries
            $this->updateSaccoAccountsTrans(
                $defaultMpesaIn,
                $amount,
                0,
                $docNo,
                "Shares Deposit - {$description}",
                $transaction->transaction_time
            );

            $this->updateSaccoAccountsTrans(
                $defaultShareAccount,
                0,
                $amount,
                $docNo,
                "Shares Deposit - {$description}",
                $transaction->transaction_time
            );

            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("DB error in processShares()", [
                'member_id' => $memberId,
                'error'    => $e->getMessage(),
                'sql'      => method_exists($e, 'getSql') ? $e->getSql() : null,
                'bindings' => method_exists($e, 'getBindings') ? $e->getBindings() : null,
                'transaction_id' => $transaction->id ?? null,
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error("processShares failed.", [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id ?? null,
            ]);

            throw $e;
        }
    }

    // private function processShares($reference, $transaction)
    // {
    //     $memberId = ltrim($reference, 'SH');
    //     Log::info("Processing shares for Member ID: $memberId");

    //     // Validate default accounts
    //     $defaultMpesaIn = DB::table('sacco_defaults')
    //         ->where('default_name', 'default_mpesa_in_account')
    //         ->value('default_value');
    //     $defaultShareAccount = DB::table('sacco_defaults')
    //         ->where('default_name', 'default_share_account')
    //         ->value('default_value');

    //     if (!$defaultMpesaIn || !$defaultShareAccount) {
    //         Log::error("Missing default accounts for processing shares: Member ID {$memberId}");
    //         return;
    //     }

    //     try {
    //         // 1️⃣ Update member's shares
    //         $affected = DB::table('sacco_members')
    //             ->where('member_id', $memberId)
    //             ->increment('member_total_share', $transaction->transaction_amount);

    //         if ($affected === 0) {
    //             throw new \Exception("No sacco_members row updated for Member ID {$memberId}");
    //         }
    //         Log::info("Updated shares for Member ID: $memberId by Amount: {$transaction->transaction_amount}");

    //         // 2️⃣ Insert into sacco_shares
    //         $currentPeriod = $this->getCurrentPeriod();
    //         $description = "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";
    //         $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

    //         DB::table('sacco_shares')->insert([
    //             'share_member_id'      => $memberId,
    //             'share_amount_paying'  => $transaction->transaction_amount,
    //             'share_paid_by'        => 'MPesa',
    //             'share_period'         =>  $this->getCurrentPeriod(),
    //             'share_description'    => $description,
    //             'share_doc_no'         => $docNo,
    //             'share_date_paid'      => Carbon::now(),
    //             'share_end_month_proc' => 'N',
    //             'share_by'             => auth()->id() ?? null,
    //             'share_ip'             => request()->ip() ?? '127.0.0.1',
    //             'share_transdate'      => Carbon::now(), // ✅ ensure column exists
    //         ]);

    //         Log::info("Inserted sacco_shares record for Member ID: $memberId, Amount: {$transaction->transaction_amount}");

    //         // 3️⃣ Update ledger entries
    //         $this->updateSaccoAccountsTrans(
    //             $defaultMpesaIn,
    //             $transaction->transaction_amount,
    //             0,
    //             $docNo,
    //             "Shares Deposit - $description",
    //             $transaction->transaction_time
    //         );
    //         $this->updateSaccoAccountsTrans(
    //             $defaultShareAccount,
    //             0,
    //             $transaction->transaction_amount,
    //             $docNo,
    //             "Shares Deposit - $description",
    //             $transaction->transaction_time
    //         );
    //     } catch (\Illuminate\Database\QueryException $e) {
    //         // Logs SQL error message + bindings
    //         Log::error("DB error in processShares()", [
    //             'memberId' => $memberId,
    //             'error'    => $e->getMessage(),
    //             'sql'      => $e->getSql(),
    //             'bindings' => $e->getBindings(),
    //         ]);
    //         throw $e; // rethrow so job fails visibly
    //     } catch (\Exception $e) {
    //         Log::error("processShares failed: " . $e->getMessage(), ['memberId' => $memberId]);
    //         throw $e;
    //     }
    // }
    // private function processLoans($reference, $transaction)
    // {
    //     // $loanId = ltrim($reference, 'LN');
    //     $loanId = (int) substr($reference, 2);

    //     Log::info("Processing loan payment for Loan ID: $loanId");

    //     $defaultMpesaIn = DB::table('sacco_defaults')->where('default_name', 'default_mpesa_in_account')->value('default_value');

    //     $loanDetails = DB::table('sacco_loans')
    //         ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
    //         ->select('sacco_loans.*', 'sacco_loan_types.loan_type_acount', 'sacco_loan_types.loan_type_int_account', 'sacco_loan_types.loan_type_interest_type', 'sacco_loan_types.loan_type_interest')
    //         ->where('sacco_loans.loan_id', $loanId)
    //         ->first();

    //     if (!$defaultMpesaIn || !$loanDetails) {
    //         Log::error("Missing default accounts or loan details for Loan ID {$loanId}");
    //         return;
    //     }

    //     // Calculate principal and interest
    //     $principalPayment = $transaction->transaction_amount;
    //     $interest = 0;
    //     $currentPeriod = $this->getCurrentPeriod();

    //     // Check if there are any payments for this loan in the current period with interest
    //     $existingPayment = DB::table('sacco_loan_payments')
    //         ->where('loan_payments_loan_id', $loanId)
    //         ->where('loan_payments_period',  $this->getCurrentPeriod())
    //         ->where('loan_payments_interest', '>', 0)
    //         ->exists();

    //     if ($loanDetails->loan_type_interest_type === "FIXED INTEREST") {
    //         // Always charge interest for fixed interest
    //         $interest = $principalPayment - ($principalPayment * 100 / ($loanDetails->loan_type_interest + 100));
    //     } elseif (!$existingPayment) {
    //         // Charge interest for reducing balance only if no interest has been paid in the current period
    //         $interest = ($loanDetails->loan_amount - $loanDetails->loan_loan_paid) * $loanDetails->loan_type_interest / 12 / 100;
    //     } else {
    //         Log::info("Interest skipped for reducing balance loan ID: $loanId as interest has already been paid in the current period.");
    //     }

    //     $principalPaid = $principalPayment - $interest;

    //     // Update loan balances
    //     DB::table('sacco_loans')
    //         ->where('loan_id', $loanId)
    //         ->increment('loan_loan_paid', $principalPaid);
    //     Log::info("Updated loan balance for Loan ID: $loanId by Principal: $principalPaid, Interest: $interest");

    //     // Insert into sacco_loan_payments
    //     $description = "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";
    //     $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

    //     DB::table('sacco_loan_payments')->insert([
    //         'loan_payments_amount' => $principalPaid,
    //         'loan_payments_interest' => $interest,
    //         'loan_payments_docno' => $docNo,
    //         'loan_payments_paid_on' => Carbon::now(),
    //         'loan_payments_loan_id' => $loanId,
    //         'loan_payments_period' =>  $this->getCurrentPeriod(),
    //         'loan_payments_description' => "Loan Payment - $description",
    //         'loan_payments_paid_in_by' => "MPesa",
    //         'loan_payments_ip' => request()->ip() ?? '127.0.0.1',
    //     ]);

    //     // Release guarantors
    //     $this->releaseGuarantors($loanId, $principalPaid);

    //     // Update ledger entries
    //     $this->updateSaccoAccountsTrans($defaultMpesaIn, $principalPaid + $interest, 0, $docNo, "Loan Payment - $description", $transaction->transaction_time);
    //     $this->updateSaccoAccountsTrans($loanDetails->loan_type_acount, 0, $principalPaid, $docNo, "Loan Principal - $description", $transaction->transaction_time);
    //     $this->updateSaccoAccountsTrans($loanDetails->loan_type_int_account, 0, $interest, $docNo, "Loan Interest - $description", $transaction->transaction_time);
    // }
    private function processLoans($reference, $transaction): bool
    {
        // $loanId = ltrim($reference, 'LN');
        $loanId = (int) substr($reference, 2);

        Log::info("Processing loan payment for Loan ID: $loanId");

        $defaultMpesaIn = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');

        $loanDetails = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->select(
                'sacco_loans.*',
                'sacco_loan_types.loan_type_acount',
                'sacco_loan_types.loan_type_int_account',
                'sacco_loan_types.loan_type_interest_type',
                'sacco_loan_types.loan_type_interest'
            )
            ->where('sacco_loans.loan_id', $loanId)
            ->first();

        if (!$defaultMpesaIn || !$loanDetails) {
            Log::error("Missing default accounts or loan details for Loan ID {$loanId}");
            return false;
        }

        // Calculate principal and interest
        $principalPayment = (float) $transaction->transaction_amount;
        $interest = 0;
        $currentPeriod = $this->getCurrentPeriod();

        // Check if interest has already been paid for this loan in this period
        $existingPayment = DB::table('sacco_loan_payments')
            ->where('loan_payments_loan_id', $loanId)
            ->where('loan_payments_period', $currentPeriod)
            ->where('loan_payments_interest', '>', 0)
            ->exists();

        if ($loanDetails->loan_type_interest_type === "FIXED INTEREST") {
            // Preserve existing behaviour: fixed interest is backed out of the full payment amount
            $interest = $principalPayment - ($principalPayment * 100 / ($loanDetails->loan_type_interest + 100));
        } elseif (!$existingPayment) {
            // Preserve existing behaviour: reducing balance interest is charged once per period
            $interest = ($loanDetails->loan_amount - $loanDetails->loan_loan_paid)
                * $loanDetails->loan_type_interest
                / 12
                / 100;
        } else {
            Log::info("Interest skipped for reducing balance loan ID: $loanId as interest has already been paid in the current period.");
        }

        // Preserve existing behaviour:
        // For specified LN payments, the full member-paid amount belongs to this loan.
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
            'loan_payments_period' => $currentPeriod,
            'loan_payments_description' => "Loan Payment - $description",
            'loan_payments_paid_in_by' => "MPesa",
            'loan_payments_ip' => request()->ip() ?? '127.0.0.1',
        ]);

        // Release guarantors
        $this->releaseGuarantors($loanId, $principalPaid);

        // Update ledger entries
        $this->updateSaccoAccountsTrans(
            $defaultMpesaIn,
            $principalPaid + $interest,
            0,
            $docNo,
            "Loan Payment - $description",
            $transaction->transaction_time
        );

        $this->updateSaccoAccountsTrans(
            $loanDetails->loan_type_acount,
            0,
            $principalPaid,
            $docNo,
            "Loan Principal - $description",
            $transaction->transaction_time
        );

        $this->updateSaccoAccountsTrans(
            $loanDetails->loan_type_int_account,
            0,
            $interest,
            $docNo,
            "Loan Interest - $description",
            $transaction->transaction_time
        );

        return true;
    }

    private function updateSaccoAccountsTrans($account, $debit, $credit, $docNo, $description, $date)
    {
        DB::table('sacco_accounts_trans')->insert([
            'accounts_trans_sub_account' => $account,
            'accounts_trans_period' => $this->getCurrentPeriod() ?? 'Unknown Period', // Current period or default
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

    private function getCurrentPeriod(): string
    {
        return now()->format('Ym'); // e.g. "202509"
    }


    // private function processFallbackTransaction($reference, $transaction)
    // {
    //     $id = $transaction->id;
    //     $parts = preg_split('/\s+/', trim($reference), 2);
    //     $idPart   = $parts[0] ?? '';
    //     $descPart = strtolower($parts[1] ?? '');

    //     // Remove non-numeric
    //     $cleanedId = preg_replace('/\D/', '', $idPart);
    //     if (empty($cleanedId)) {
    //         Log::warning("Fallback: No numeric ID found in reference '$reference'. Exiting.");
    //         return;
    //     }

    //     // Detect prefix usage
    //     $prefix = strtoupper(substr($idPart, 0, 2));
    //     Log::info("Fallback prefix detected: {$prefix} from idPart={$idPart}");

    //     // ✅ Check for known prefixes (hardcoded + sacco_fosa_types)
    //     $isKnownPrefix = in_array($prefix, ['SH', 'LN', 'CA']) ||
    //         DB::table('sacco_fosa_types')
    //         ->whereRaw('UPPER(type_prefix) = ?', [$prefix])
    //         ->where('type_active', 'Y')
    //         ->exists();

    //     Log::info("Fallback: prefix {$prefix}, isKnownPrefix=" . ($isKnownPrefix ? 'YES' : 'NO'));

    //     if ($isKnownPrefix) {
    //         // ✅ Always use member_id lookup for known prefixes
    //         $members = DB::table('sacco_members')
    //             ->where('member_id', $cleanedId)
    //             ->get();
    //         Log::info("Fallback: Using member_id lookup with prefix {$prefix}, value {$cleanedId}");
    //     } else {
    //         // 🔎 Fallback to national_id lookup if prefix is unknown
    //         $members = DB::table('sacco_members')
    //             ->where('member_national_id', $cleanedId)
    //             ->get();
    //         Log::info("Fallback: Using national_id lookup, value {$cleanedId}");
    //     }



    //     // ✅ Handle case where no match or multiple matches
    //     if ($members->count() !== 1) {
    //         Log::warning("Fallback: Found {$members->count()} matches for ID '$cleanedId'. Skipping.");
    //         return;
    //     }

    //     $member   = $members->first();
    //     $memberId = $member->member_id;
    //     // $period = (object)['period_name' => now()->format('Ym')];
    //     $period = $this->getCurrentPeriod(); // returns "202509"
    //     $amount = $transaction->transaction_amount;
    //     $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

    //     $senderName = $transaction->first_name ?: $transaction->msisdn;
    //     $description = ($descPart ?: 'Mpesa Deposit')
    //         . " - $senderName - Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

    //     $now = Carbon::now();
    //     $ip = request()->ip() ?? '127.0.0.1';
    //     $userId = auth()->id() ?? 999;

    //     $mpesaAccount = DB::table('sacco_defaults')->where('default_name', 'default_mpesa_in_account')->value('default_value');
    //     $fosaAccount = DB::table('sacco_defaults')->where('default_name', 'default_fosa_account')->value('default_value');
    //     $shareAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_account')->value('default_value');
    //     $capitalAccount = DB::table('sacco_defaults')->where('default_name', 'default_share_capital_account')->value('default_value');

    //     if (!$mpesaAccount || !$period) {
    //         Log::error("Fallback: Missing required defaults (mpesa_in/period)");
    //         return;
    //     }
    //     if (Str::contains($descPart, ['share', 'shares', 'deposit', 'deposits'])) {

    //         DB::table('sacco_shares')->insert([
    //             'share_member_id' => $memberId,
    //             'share_amount_paying' => $amount,
    //             'share_paid_by' => 'MPesa',
    //             'share_period' => $period,
    //             'share_description' => $description,
    //             'share_doc_no' => $docNo,
    //             'share_date_paid' => $now,
    //             'share_end_month_proc' => 'N',
    //             'share_by' => $userId,
    //             'share_ip' => $ip,
    //             'share_transdate' => $now,
    //         ]);

    //         DB::table('sacco_members')->where('member_id', $memberId)->increment('member_total_share', $amount);

    //         $this->updateSaccoAccountsTrans($mpesaAccount, $amount, 0, $docNo, "Share Deposit - $description", $transaction->transaction_time);
    //         $this->updateSaccoAccountsTrans($shareAccount, 0, $amount, $docNo, "Share Deposit - $description", $transaction->transaction_time);

    //         Log::info("SHARE fallback: Completed for Member ID: $memberId");
    //     } elseif (Str::startsWith(strtoupper($reference), 'CA') || Str::contains($descPart, 'capital')) {
    //         DB::table('sacco_capital_shares')->insert([
    //             'share_capitalmember_id'   => $memberId,
    //             'share_capitalamount_paying' => $amount,
    //             'share_capitalpaid_by'     => 'MPesa',
    //             'share_capitalperiod'      => $period,
    //             'share_capitaldescription' => $description,
    //             'share_capitaldoc_no'      => $docNo,
    //             'share_capitaldate_paid'   => $now,
    //             'share_capitalend_month_proc' => 'N',
    //             'share_capitalby'          => $userId,
    //             'share_capitalip'          => $ip,
    //             'share_capitaltransdate'   => $now,
    //         ]);

    //         DB::table('sacco_members')
    //             ->where('member_id', $memberId)
    //             ->increment('member_total_share_capital', $amount);

    //         $this->updateSaccoAccountsTrans($mpesaAccount, $amount, 0, $docNo, "Capital Deposit - $description", $transaction->transaction_time);
    //         $this->updateSaccoAccountsTrans($capitalAccount, 0, $amount, $docNo, "Capital Deposit - $description", $transaction->transaction_time);

    //         Log::info("CAPITAL fallback: Completed for Member ID: $memberId, Amount: $amount");
    //     } else {
    //         $prefix = strtoupper(substr($reference, 0, 2)); // first 2 letters
    //         $fosaType = DB::table('sacco_fosa_types')
    //             ->where('type_prefix', $prefix)
    //             ->where('type_active', 'Y')
    //             ->first();

    //         if ($fosaType) {
    //             $fosaDesc   = $fosaType->type_name;
    //             $fosaPrefix = $fosaType->type_prefix;
    //         } else {
    //             $fosaDesc   = "FOSA Deposit";
    //             $fosaPrefix = "FO";
    //         }

    //         // Build description with FOSA type info
    //         $fullDescription = "{$fosaDesc} - $description";

    //         DB::table('sacco_fosas')->insert([
    //             'fosa_member_id'     => $memberId,
    //             'fosa_amount_paying' => $amount,
    //             'fosa_paid_by'       => 'MPesa',
    //             'fosa_period'        => $period,
    //             'fosa_description'   => $fullDescription,
    //             'fosa_doc_no'        => $docNo,
    //             'fosa_date_paid'     => $now,
    //             'fosa_end_month_proc' => 'N',
    //             'fosa_by'            => $userId,
    //             'fosa_ip'            => $ip,
    //             'fosa_transdate'     => $now,
    //             // 'fosa_type_id'    => $fosaType->type_id ?? null, // uncomment if schema supports it
    //         ]);

    //         DB::table('sacco_members')
    //             ->where('member_id', $memberId)
    //             ->increment('member_total_fosa', $amount);

    //         $this->updateSaccoAccountsTrans(
    //             $mpesaAccount,
    //             $amount,
    //             0,
    //             $docNo,
    //             $fullDescription,
    //             $transaction->transaction_time
    //         );

    //         $this->updateSaccoAccountsTrans(
    //             $fosaAccount,
    //             0,
    //             $amount,
    //             $docNo,
    //             $fullDescription,
    //             $transaction->transaction_time
    //         );

    //         Log::info("{$fosaDesc} fallback: Completed for Member ID: $memberId, Prefix: $fosaPrefix, Amount: $amount");
    //     }

    //     Log::info("Transaction fallback processing complete for reference: $reference");

    //     DB::table('c2b_payments')
    //         ->where('id', $id)
    //         ->update([
    //             'processed'      => 'Yes',
    //             'processed_date' => now(),
    //         ]);
    // }


    private function processFallbackTransaction($reference, $transaction): bool
    {
        /*
    |--------------------------------------------------------------------------
    | Existing Fallback Processor
    |--------------------------------------------------------------------------
    |
    | This method is still the old fallback processor.
    | It does NOT do smart allocation yet.
    |
    | Surgical changes only:
    | - Return true when money was posted.
    | - Return false when nothing was posted.
    | - Do not mark c2b_payments as processed here.
    | - Let processTransaction() remain the only place that marks processed.
    |
    */

        $parts = preg_split('/\s+/', trim((string) $reference), 2);
        $idPart   = $parts[0] ?? '';
        $descPart = strtolower($parts[1] ?? '');

        // Remove non-numeric characters
        $cleanedId = preg_replace('/\D/', '', $idPart);

        if (empty($cleanedId)) {
            Log::warning("Fallback: No numeric ID found in reference '{$reference}'. Exiting.", [
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            ]);

            return false;
        }

        // Detect prefix usage
        $prefix = strtoupper(substr($idPart, 0, 2));

        Log::info("Fallback prefix detected: {$prefix} from idPart={$idPart}", [
            'transaction_id' => $transaction->id ?? null,
        ]);

        // Check for known prefixes: hardcoded + active FOSA type prefixes
        $isKnownPrefix = in_array($prefix, ['SH', 'LN', 'CA']) ||
            DB::table('sacco_fosa_types')
            ->whereRaw('UPPER(type_prefix) = ?', [$prefix])
            ->where('type_active', 'Y')
            ->exists();

        Log::info("Fallback prefix check completed.", [
            'prefix' => $prefix,
            'is_known_prefix' => $isKnownPrefix ? 'YES' : 'NO',
            'transaction_id' => $transaction->id ?? null,
        ]);

        if ($isKnownPrefix) {
            // Known prefixes use member_id lookup
            $members = DB::table('sacco_members')
                ->where('member_id', $cleanedId)
                ->get();

            Log::info("Fallback: Using member_id lookup.", [
                'prefix' => $prefix,
                'value' => $cleanedId,
                'transaction_id' => $transaction->id ?? null,
            ]);
        } else {
            // Unknown prefix falls back to national ID lookup
            $members = DB::table('sacco_members')
                ->where('member_national_id', $cleanedId)
                ->get();

            Log::info("Fallback: Using national_id lookup.", [
                'value' => $cleanedId,
                'transaction_id' => $transaction->id ?? null,
            ]);
        }

        // Must identify exactly one member
        if ($members->count() !== 1) {
            Log::warning("Fallback: Member lookup did not return exactly one match. Skipping.", [
                'matches' => $members->count(),
                'cleaned_id' => $cleanedId,
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            ]);

            return false;
        }

        $member   = $members->first();
        $memberId = $member->member_id;

        $period = $this->getCurrentPeriod();
        $amount = (float) ($transaction->transaction_amount ?? 0);

        if ($amount <= 0) {
            Log::warning("Fallback: Invalid transaction amount. Skipping.", [
                'member_id' => $memberId,
                'amount' => $amount,
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

        $senderName = $transaction->first_name ?: $transaction->msisdn;

        $description = ($descPart ?: 'Mpesa Deposit')
            . " - {$senderName} - Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

        $now    = Carbon::now();
        $ip     = request()->ip() ?? '127.0.0.1';
        $userId = auth()->id() ?? 999;

        $mpesaAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');

        $fosaAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_fosa_account')
            ->value('default_value');

        $shareAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_share_account')
            ->value('default_value');

        $capitalAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_share_capital_account')
            ->value('default_value');

        if (!$mpesaAccount || !$period) {
            Log::error("Fallback: Missing required defaults.", [
                'mpesa_account' => $mpesaAccount,
                'period' => $period,
                'member_id' => $memberId,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | Existing Fallback Routing
    |--------------------------------------------------------------------------
    |
    | Keep the existing fallback destination logic unchanged:
    | - share/deposit words go to shares
    | - CA/capital goes to capital
    | - otherwise FOSA
    |
    */

        if (Str::contains($descPart, ['share', 'shares', 'deposit', 'deposits'])) {
            if (!$shareAccount) {
                Log::error("Fallback SHARE failed: Missing default_share_account.", [
                    'member_id' => $memberId,
                    'transaction_id' => $transaction->id ?? null,
                ]);

                return false;
            }

            DB::table('sacco_shares')->insert([
                'share_member_id'      => $memberId,
                'share_amount_paying'  => $amount,
                'share_paid_by'        => 'MPesa',
                'share_period'         => $period,
                'share_description'    => $description,
                'share_doc_no'         => $docNo,
                'share_date_paid'      => $now,
                'share_end_month_proc' => 'N',
                'share_by'             => $userId,
                'share_ip'             => $ip,
                'share_transdate'      => $now,
            ]);

            DB::table('sacco_members')
                ->where('member_id', $memberId)
                ->increment('member_total_share', $amount);

            $this->updateSaccoAccountsTrans(
                $mpesaAccount,
                $amount,
                0,
                $docNo,
                "Share Deposit - {$description}",
                $transaction->transaction_time
            );

            $this->updateSaccoAccountsTrans(
                $shareAccount,
                0,
                $amount,
                $docNo,
                "Share Deposit - {$description}",
                $transaction->transaction_time
            );

            Log::info("SHARE fallback completed.", [
                'member_id' => $memberId,
                'amount' => $amount,
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return true;
        }

        if (Str::startsWith(strtoupper($reference), 'CA') || Str::contains($descPart, 'capital')) {
            if (!$capitalAccount) {
                Log::error("Fallback CAPITAL failed: Missing default_share_capital_account.", [
                    'member_id' => $memberId,
                    'transaction_id' => $transaction->id ?? null,
                ]);

                return false;
            }

            DB::table('sacco_capital_shares')->insert([
                'share_capitalmember_id'       => $memberId,
                'share_capitalamount_paying'   => $amount,
                'share_capitalpaid_by'         => 'MPesa',
                'share_capitalperiod'          => $period,
                'share_capitaldescription'     => $description,
                'share_capitaldoc_no'          => $docNo,
                'share_capitaldate_paid'       => $now,
                'share_capitalend_month_proc'  => 'N',
                'share_capitalby'              => $userId,
                'share_capitalip'              => $ip,
                'share_capitaltransdate'       => $now,
            ]);

            DB::table('sacco_members')
                ->where('member_id', $memberId)
                ->increment('member_total_share_capital', $amount);

            $this->updateSaccoAccountsTrans(
                $mpesaAccount,
                $amount,
                0,
                $docNo,
                "Capital Deposit - {$description}",
                $transaction->transaction_time
            );

            $this->updateSaccoAccountsTrans(
                $capitalAccount,
                0,
                $amount,
                $docNo,
                "Capital Deposit - {$description}",
                $transaction->transaction_time
            );

            Log::info("CAPITAL fallback completed.", [
                'member_id' => $memberId,
                'amount' => $amount,
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return true;
        }

        /*
    |--------------------------------------------------------------------------
    | Default FOSA Fallback
    |--------------------------------------------------------------------------
    */

        if (!$fosaAccount) {
            Log::error("Fallback FOSA failed: Missing default_fosa_account.", [
                'member_id' => $memberId,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        $fosaType = DB::table('sacco_fosa_types')
            ->whereRaw('UPPER(type_prefix) = ?', [$prefix])
            ->where('type_active', 'Y')
            ->first();

        if ($fosaType) {
            $fosaDesc   = $fosaType->type_name;
            $fosaPrefix = $fosaType->type_prefix;
        } else {
            $fosaDesc   = "FOSA Deposit";
            $fosaPrefix = "FO";
        }

        $fullDescription = "{$fosaDesc} - {$description}";

        DB::table('sacco_fosas')->insert([
            'fosa_member_id'      => $memberId,
            'fosa_amount_paying'  => $amount,
            'fosa_paid_by'        => 'MPesa',
            'fosa_period'         => $period,
            'fosa_description'    => $fullDescription,
            'fosa_doc_no'         => $docNo,
            'fosa_date_paid'      => $now,
            'fosa_end_month_proc' => 'N',
            'fosa_by'             => $userId,
            'fosa_ip'             => $ip,
            'fosa_transdate'      => $now,
            // 'fosa_type_id'     => $fosaType->type_id ?? null, // enable only if schema supports it
        ]);

        DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->increment('member_total_fosa', $amount);

        $this->updateSaccoAccountsTrans(
            $mpesaAccount,
            $amount,
            0,
            $docNo,
            $fullDescription,
            $transaction->transaction_time
        );

        $this->updateSaccoAccountsTrans(
            $fosaAccount,
            0,
            $amount,
            $docNo,
            $fullDescription,
            $transaction->transaction_time
        );

        Log::info("{$fosaDesc} fallback completed.", [
            'member_id' => $memberId,
            'prefix' => $fosaPrefix,
            'amount' => $amount,
            'reference' => $reference,
            'transaction_id' => $transaction->id ?? null,
        ]);

        return true;
    }
    // private function processCapital($reference, $transaction)
    // {
    //     $memberId = ltrim($reference, 'CA');
    //     Log::info("Processing capital shares for Member ID: $memberId");

    //     $defaultMpesaIn    = DB::table('sacco_defaults')->where('default_name', 'default_mpesa_in_account')->value('default_value');
    //     $defaultCapitalAcc = DB::table('sacco_defaults')->where('default_name', 'default_share_capital_account')->value('default_value');

    //     if (!$defaultMpesaIn || !$defaultCapitalAcc) {
    //         Log::error("Missing default accounts for processing capital shares: Member ID {$memberId}");
    //         return;
    //     }

    //     $currentPeriod = $this->getCurrentPeriod();
    //     $description   = "Capital Deposit - Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";
    //     $docNo         = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

    //     // Insert into sacco_capital_shares
    //     DB::table('sacco_capital_shares')->insert([
    //         'share_capitalmember_id'     => $memberId,
    //         'share_capitalamount_paying' => $transaction->transaction_amount,
    //         'share_capitalpaid_by'       => 'MPesa',
    //         'share_capitalperiod'        =>  $this->getCurrentPeriod(),
    //         'share_capitaldescription'   => $description,
    //         'share_capitaldoc_no'        => $docNo,
    //         'share_capitaldate_paid'     => Carbon::now(),
    //         'share_capitalend_month_proc' => 'N',
    //         'share_capitalby'            => auth()->id() ?? null,
    //         'share_capitalip'            => request()->ip() ?? '127.0.0.1',
    //         'share_capitaltransdate'     => Carbon::now(),
    //     ]);

    //     // Update member totals
    //     DB::table('sacco_members')
    //         ->where('member_id', $memberId)
    //         ->increment('member_total_share_capital', $transaction->transaction_amount);

    //     // Ledger updates
    //     $this->updateSaccoAccountsTrans($defaultMpesaIn, $transaction->transaction_amount, 0, $docNo, $description, $transaction->transaction_time);
    //     $this->updateSaccoAccountsTrans($defaultCapitalAcc, 0, $transaction->transaction_amount, $docNo, $description, $transaction->transaction_time);

    //     Log::info("Capital shares processed for Member ID: $memberId, Amount: {$transaction->transaction_amount}");
    // }

    private function processCapital($reference, $transaction): bool
    {
        $memberId = ltrim($reference, 'CA');

        Log::info("Processing capital shares for Member ID: {$memberId}", [
            'reference' => $reference,
            'transaction_id' => $transaction->id ?? null,
        ]);

        $amount = (float) ($transaction->transaction_amount ?? 0);

        if (empty($memberId) || !is_numeric($memberId)) {
            Log::error("Invalid capital shares reference.", [
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        if ($amount <= 0) {
            Log::error("Invalid capital shares payment amount.", [
                'member_id' => $memberId,
                'amount' => $amount,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        $defaultMpesaIn = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');

        $defaultCapitalAcc = DB::table('sacco_defaults')
            ->where('default_name', 'default_share_capital_account')
            ->value('default_value');

        if (!$defaultMpesaIn || !$defaultCapitalAcc) {
            Log::error("Missing default accounts for processing capital shares.", [
                'member_id' => $memberId,
                'default_mpesa_in_account' => $defaultMpesaIn,
                'default_share_capital_account' => $defaultCapitalAcc,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        $currentPeriod = $this->getCurrentPeriod();
        $now = Carbon::now();

        $description = "Capital Deposit - Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";
        $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

        DB::table('sacco_capital_shares')->insert([
            'share_capitalmember_id'      => $memberId,
            'share_capitalamount_paying'  => $amount,
            'share_capitalpaid_by'        => 'MPesa',
            'share_capitalperiod'         => $currentPeriod,
            'share_capitaldescription'    => $description,
            'share_capitaldoc_no'         => $docNo,
            'share_capitaldate_paid'      => $now,
            'share_capitalend_month_proc' => 'N',
            'share_capitalby'             => auth()->id() ?? null,
            'share_capitalip'             => request()->ip() ?? '127.0.0.1',
            'share_capitaltransdate'      => $now,
        ]);

        DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->increment('member_total_share_capital', $amount);

        $this->updateSaccoAccountsTrans(
            $defaultMpesaIn,
            $amount,
            0,
            $docNo,
            $description,
            $transaction->transaction_time
        );

        $this->updateSaccoAccountsTrans(
            $defaultCapitalAcc,
            0,
            $amount,
            $docNo,
            $description,
            $transaction->transaction_time
        );

        Log::info("Capital shares processed.", [
            'member_id' => $memberId,
            'amount' => $amount,
            'period' => $currentPeriod,
            'transaction_id' => $transaction->id ?? null,
        ]);

        return true;
    }

    // private function processRegistrationFee($memberId, $transaction, $description, $docNo, $period, $now, $userId, $ip, $mpesaAccount)
    // {
    //     $amount = $transaction->transaction_amount;

    //     // Insert into sacco_registration_fees
    //     DB::table('sacco_registration_fees')->insert([
    //         'regfee_member_id'      => $memberId,
    //         'regfee_amount'         => $amount,
    //         'regfee_doc_no'         => $docNo,
    //         'regfee_description'    => "Registration Fee - $description",
    //         'regfee_date_paid'      => $now->toDateString(),
    //         'regfee_paid_by'        => $userId,
    //         'regfee_ip'             => $ip,
    //         'regfee_by'             => $userId,
    //         'regfee_created_ip'     => $ip,
    //         'regfee_transdate'      => $now,
    //         'regfee_end_month_proc' => $period,
    //         'created_at'            => $now,
    //         'updated_at'            => $now,
    //     ]);



    //     // Ledger update (using default_member_ship_fee_account)
    //     $regFeeAccount = DB::table('sacco_defaults')
    //         ->where('default_name', 'default_member_ship_fee_account')
    //         ->value('default_value');

    //     if ($regFeeAccount) {
    //         $this->updateSaccoAccountsTrans(
    //             $mpesaAccount,
    //             $amount,
    //             0,
    //             $docNo,
    //             "Registration Fee - $description",
    //             $transaction->transaction_time
    //         );

    //         $this->updateSaccoAccountsTrans(
    //             $regFeeAccount,
    //             0,
    //             $amount,
    //             $docNo,
    //             "Registration Fee - $description",
    //             $transaction->transaction_time
    //         );
    //     }

    //     Log::info("REGISTRATION FEE processed for Member ID: $memberId, Amount: $amount");
    // }
    private function processRegistrationFee($memberId, $transaction, $description, $docNo, $period, $now, $userId, $ip, $mpesaAccount): bool
    {
        $amount = (float) ($transaction->transaction_amount ?? 0);

        Log::info("Processing registration fee.", [
            'member_id' => $memberId,
            'amount' => $amount,
            'period' => $period,
            'transaction_id' => $transaction->id ?? null,
        ]);

        if (empty($memberId) || !is_numeric($memberId)) {
            Log::error("Invalid registration fee member ID.", [
                'member_id' => $memberId,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        if ($amount <= 0) {
            Log::error("Invalid registration fee amount.", [
                'member_id' => $memberId,
                'amount' => $amount,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        if (!$mpesaAccount) {
            Log::error("Registration fee failed: Missing default_mpesa_in_account.", [
                'member_id' => $memberId,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        $regFeeAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_member_ship_fee_account')
            ->value('default_value');

        if (!$regFeeAccount) {
            Log::error("Registration fee failed: Missing default_member_ship_fee_account.", [
                'member_id' => $memberId,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return false;
        }

        DB::table('sacco_registration_fees')->insert([
            'regfee_member_id'      => $memberId,
            'regfee_amount'         => $amount,
            'regfee_doc_no'         => $docNo,
            'regfee_description'    => "Registration Fee - $description",
            'regfee_date_paid'      => $now->toDateString(),
            'regfee_paid_by'        => $userId,
            'regfee_ip'             => $ip,
            'regfee_by'             => $userId,
            'regfee_created_ip'     => $ip,
            'regfee_transdate'      => $now,
            'regfee_end_month_proc' => $period,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        $this->updateSaccoAccountsTrans(
            $mpesaAccount,
            $amount,
            0,
            $docNo,
            "Registration Fee - $description",
            $transaction->transaction_time
        );

        $this->updateSaccoAccountsTrans(
            $regFeeAccount,
            0,
            $amount,
            $docNo,
            "Registration Fee - $description",
            $transaction->transaction_time
        );

        Log::info("REGISTRATION FEE processed successfully.", [
            'member_id' => $memberId,
            'amount' => $amount,
            'period' => $period,
            'transaction_id' => $transaction->id ?? null,
        ]);

        return true;
    }
    // private function processOperatorTransaction(string &$reference, $transaction)
    // {
    //     // Example formats:
    //     // OPSH-15
    //     // OPCA-22
    //     // OPLN-15-26 (loan)
    //     // OPRF-19
    //     // OPDT-33   (fallback)
    //     // OPOT-33   (fallback)
    //     // OPPN-33   (fallback)

    //     $parts = explode('-', $reference);

    //     $prefix = strtoupper($parts[0] ?? null);   // OPSH, OPLN, OPDT...
    //     $opId   = $parts[1] ?? null;               // operator ID
    //     $loanId = $parts[2] ?? null;               // only for OPLN

    //     if (!$prefix || !$opId || !is_numeric($opId)) {
    //         return $this->failTransaction($transaction->id, "Invalid operator reference: $reference");
    //     }

    //     // Load operator
    //     $operator = DB::table('sacco_operators')
    //         ->where('operator_id', $opId)
    //         ->first();

    //     if (!$operator) {
    //         return $this->failTransaction($transaction->id, "Operator ID {$opId} not found");
    //     }

    //     // ALWAYS record operator deposits
    //     DB::table('sacco_matatus_collections')->insert([
    //         'coll_operator_id' => $operator->operator_id,
    //         'coll_vehicle_id'  => $operator->operator_vehicle_id,
    //         'coll_amount'      => $transaction->transaction_amount,
    //         'coll_type'        => strtolower($prefix),
    //         'coll_period'      => $this->getCurrentPeriod(),
    //         'coll_description' => "$prefix Payment from Operator {$operator->operator_name}",
    //         'coll_ip'          => request()->ip(),
    //         'coll_transdate'   => now(),
    //     ]);

    //     // =====================================================
    //     // REWRITE OPERATOR PREFIX TO SACCO PREFIX
    //     // =====================================================

    //     switch ($prefix) {

    //         // Operator Share → SH<member_id>
    //         case "OPSH":
    //             $reference = "SH" . $operator->operator_member_id;
    //             break;

    //         // Operator Capital → CA<member_id>
    //         case "OPCA":
    //             $reference = "CA" . $operator->operator_member_id;
    //             break;

    //         // Operator Registration Fee → RF<member_id>
    //         case "OPRF":
    //             $reference = "RF" . $operator->operator_member_id;
    //             break;

    //         // Operator Loan → LN<loan_id>
    //         case "OPLN":
    //             if (!$loanId) {
    //                 return $this->failTransaction($transaction->id, "OPLN missing loan ID in $reference");
    //             }
    //             $reference = "LN" . $loanId; // SACCO routing will process this
    //             break;

    //         // All other OP prefixes go to fallback:
    //         // OPDT-xx (daily target)
    //         // OPOT-xx (other)
    //         // OPPN-xx (penalty)
    //         default:
    //             // Remove OP → e.g. OPDT-15 → DT15
    //             $core = substr($prefix, 2);
    //             $reference = $core . $opId;
    //             break;
    //     }

    //     // IMPORTANT:
    //     // Do NOT process anything here.
    //     // Main SACCO routing will process rewritten $reference.
    // }
    // private function processOperatorTransaction(string &$reference, $transaction)
    // {
    //     $parts = explode('-', $reference);

    //     $prefix = strtoupper($parts[0] ?? null);
    //     $opId   = $parts[1] ?? null;
    //     $loanId = $parts[2] ?? null;

    //     if (!$prefix || !$opId || !is_numeric($opId)) {
    //         return $this->failTransaction($transaction->id, "Invalid operator reference: $reference");
    //     }

    //     // 1. Load operator from correct table
    //     $operator = DB::table('sacco_matatus_operators')
    //         ->where('id', $opId)
    //         ->first();

    //     if (!$operator) {
    //         return $this->failTransaction($transaction->id, "Operator ID {$opId} not found");
    //     }

    //     // 2. Get latest vehicle assignment (optional)
    //     $assignment = DB::table('sacco_matatus_operator_vehicle_assignments')
    //         ->where('v_assignment_operator_id', $opId)
    //         ->orderBy('v_assignment_start_date', 'desc')
    //         ->first();

    //     $vehicleId = $assignment->v_assignment_vehicle_id ?? null;

    //     // 3. Record operator payment (name used in description)
    //     // DB::table('sacco_matatus_collections')->insert([
    //     //     'coll_operator_id' => $opId,
    //     //     'coll_vehicle_id'  => $vehicleId,
    //     //     'coll_amount'      => $transaction->transaction_amount,
    //     //     'coll_type'        => strtolower($prefix),
    //     //     'coll_period'      => $this->getCurrentPeriod(),
    //     //     'coll_description' => "Payment by Operator {$operator->full_name}",
    //     //     'coll_ip'          => request()->ip(),
    //     //     'coll_transdate'   => now(),
    //     //     'created_at'       => now(),
    //     //     'updated_at'       => now(),
    //     // ]);

    //     DB::table('sacco_matatus_collections')->insert([
    //         'coll_operator_id' => $opId,
    //         'coll_vehicle_id'  => $vehicleId,
    //         'coll_amount'      => $transaction->transaction_amount,
    //         'coll_reference'   => $reference,  // 🔥 correct column name
    //         'coll_notes'       => "Payment by Operator {$operator->full_name}",
    //         'coll_type'        => $this->mapOperatorType($prefix),  // explained below
    //         'coll_mode'        => 'mpesa',
    //         'coll_date'        => now()->toDateString(),       // 🔥 correct date column
    //         'created_at'       => now(),
    //         'updated_at'       => now(),
    //     ]);

    //     // 4. Rewrite reference → SACCO internal format (NO NAME IN REFERENCE)
    //     switch ($prefix) {

    //         case "OPSH": // operator paying shares
    //             $reference = "SH" . $operator->introduced_by_member_id;
    //             break;

    //         case "OPCA": // capital shares
    //             $reference = "CA" . $operator->introduced_by_member_id;
    //             break;

    //         case "OPRF": // registration fee
    //             $reference = "RF" . $operator->introduced_by_member_id;
    //             break;

    //         case "OPLN": // loan
    //             if (!$loanId) {
    //                 return $this->failTransaction($transaction->id, "OPLN missing loan ID in $reference");
    //             }
    //             $reference = "LN" . $loanId; // stays numeric
    //             break;

    //         default:
    //             // fallback e.g. OPDT-1 → DT1
    //             $core = substr($prefix, 2);
    //             $reference = $core . $opId;
    //             break;
    //     }

    //     // SACCO routing will now process rewritten $reference normally.
    // }
    private function processOperatorTransaction(string &$reference, $transaction): bool
    {
        $parts = explode('-', $reference);

        $prefix = strtoupper($parts[0] ?? '');
        $opId   = $parts[1] ?? null;
        $loanId = $parts[2] ?? null;

        if (!$prefix || !$opId || !is_numeric($opId)) {
            Log::warning("Invalid operator reference.", [
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            ]);

            return false;
        }

        $operator = DB::table('sacco_matatus_operators')
            ->where('id', $opId)
            ->first();

        if (!$operator) {
            Log::warning("Operator not found.", [
                'operator_id' => $opId,
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            ]);

            return false;
        }

        $assignment = DB::table('sacco_matatus_operator_vehicle_assignments')
            ->where('v_assignment_operator_id', $opId)
            ->orderBy('v_assignment_start_date', 'desc')
            ->first();

        $vehicleId = $assignment->v_assignment_vehicle_id ?? null;

        DB::table('sacco_matatus_collections')->insert([
            'coll_operator_id' => $opId,
            'coll_vehicle_id'  => $vehicleId,
            'coll_amount'      => $transaction->transaction_amount,
            'coll_reference'   => $reference,
            'coll_notes'       => "Payment by Operator {$operator->full_name}",
            'coll_type'        => $this->mapOperatorType($prefix),
            'coll_mode'        => 'mpesa',
            'coll_date'        => now()->toDateString(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        switch ($prefix) {
            case "OPSH":
                if (empty($operator->introduced_by_member_id)) {
                    Log::warning("Operator share payment failed: introduced_by_member_id missing.", [
                        'operator_id' => $opId,
                        'reference' => $reference,
                        'transaction_id' => $transaction->id ?? null,
                    ]);

                    return false;
                }

                $reference = "SH" . $operator->introduced_by_member_id;
                break;

            case "OPCA":
                if (empty($operator->introduced_by_member_id)) {
                    Log::warning("Operator capital payment failed: introduced_by_member_id missing.", [
                        'operator_id' => $opId,
                        'reference' => $reference,
                        'transaction_id' => $transaction->id ?? null,
                    ]);

                    return false;
                }

                $reference = "CA" . $operator->introduced_by_member_id;
                break;

            case "OPRF":
                if (empty($operator->introduced_by_member_id)) {
                    Log::warning("Operator registration fee payment failed: introduced_by_member_id missing.", [
                        'operator_id' => $opId,
                        'reference' => $reference,
                        'transaction_id' => $transaction->id ?? null,
                    ]);

                    return false;
                }

                $reference = "RF" . $operator->introduced_by_member_id;
                break;

            case "OPLN":
                if (!$loanId || !is_numeric($loanId)) {
                    Log::warning("Operator loan payment failed: OPLN missing valid loan ID.", [
                        'operator_id' => $opId,
                        'reference' => $reference,
                        'transaction_id' => $transaction->id ?? null,
                    ]);

                    return false;
                }

                $reference = "LN" . $loanId;
                break;

            default:
                // Fallback e.g. OPDT-1 becomes DT1
                $core = substr($prefix, 2);
                $reference = $core . $opId;
                break;
        }

        Log::info("Operator transaction recorded and reference rewritten.", [
            'operator_id' => $opId,
            'rewritten_reference' => $reference,
            'transaction_id' => $transaction->id ?? null,
        ]);

        return true;
    }
    private function mapOperatorType($prefix)
    {
        switch ($prefix) {
            case 'OPSH':
                return 'share';
            case 'OPCA':
                return 'share';
            case 'OPLN':
                return 'loan_repayment';
            case 'OPRF':
                return 'deposit';
            case 'OPDT':
                return 'daily_target';
            case 'OPOT':
                return 'other';
            case 'OPPN':
                return 'penalty';
            default:
                return 'other';
        }
    }

    private function processSmartAllocation($reference, $transaction): bool
    {
        /*
    |--------------------------------------------------------------------------
    | Smart M-PESA Allocation
    |--------------------------------------------------------------------------
    |
    | This runs only for unspecified references.
    |
    | Direct references are already handled before this:
    | SH{id}, LN{id}, CA{id}, RF{id}, OP...
    |
    | Therefore:
    | - Direct LN{id} still pays the selected loan in full.
    | - Smart loan allocation is capped only here.
    */

        $member = $this->resolveMemberFromUnspecifiedReference($reference, $transaction);

        if (!$member) {
            Log::warning("Smart allocation failed: member could not be resolved.", [
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            ]);

            return false;
        }

        $amount = (float) ($transaction->transaction_amount ?? 0);

        if ($amount <= 0) {
            Log::warning("Smart allocation failed: invalid transaction amount.", [
                'member_id' => $member->member_id ?? null,
                'amount' => $amount,
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            ]);

            return false;
        }

        $priorities = $this->getMpesaAllocationPriorities();

        if ($priorities->isEmpty()) {
            Log::warning("Smart allocation priorities missing. Falling back to existing fallback processor.", [
                'member_id' => $member->member_id,
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            ]);

            return $this->processFallbackTransaction($reference, $transaction);
        }

        DB::beginTransaction();

        try {
            $remainingAmount = $amount;
            $postedAnything = false;

            foreach ($priorities as $priority) {
                if ($remainingAmount <= 0.00001) {
                    break;
                }

                $key = strtoupper(trim((string) ($priority->priority_key ?? '')));

                if ($key === '') {
                    continue;
                }

                $allocated = 0.0;

                if (Str::contains($key, ['LOAN', 'LOANS'])) {
                    $allocated = $this->allocateSmartLoans($member, $remainingAmount, $transaction);
                } elseif (Str::contains($key, ['SHARE', 'SHARES', 'DEPOSIT', 'DEPOSITS'])) {
                    $allocated = $this->allocateSmartShares($member, $remainingAmount, $transaction, $priority);
                } elseif (Str::contains($key, ['CAPITAL'])) {
                    $allocated = $this->allocateSmartCapital($member, $remainingAmount, $transaction, $priority);
                } elseif (Str::contains($key, ['REG', 'REGISTRATION', 'MEMBERSHIP'])) {
                    $allocated = $this->allocateSmartRegistrationFee($member, $remainingAmount, $transaction, $priority);
                } elseif (Str::contains($key, ['FOSA', 'SAVING', 'SAVINGS'])) {
                    $allocated = $this->allocateSmartFosa($member, $remainingAmount, $transaction, $priority);
                }

                $allocated = round((float) $allocated, 2);

                if ($allocated <= 0) {
                    continue;
                }

                if ($allocated > $remainingAmount) {
                    Log::warning("Smart allocation rejected: allocator returned more than remaining amount.", [
                        'member_id' => $member->member_id,
                        'priority_key' => $key,
                        'allocated' => $allocated,
                        'remaining_before' => $remainingAmount,
                        'transaction_id' => $transaction->id ?? null,
                        'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    ]);

                    DB::rollBack();

                    return false;
                }

                $remainingAmount = round($remainingAmount - $allocated, 2);
                $postedAnything = true;

                Log::info("Smart allocation priority posted.", [
                    'member_id' => $member->member_id,
                    'priority_key' => $key,
                    'allocated' => $allocated,
                    'remaining' => $remainingAmount,
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]);
            }

            if ($remainingAmount > 0.00001) {
                Log::warning("Smart allocation incomplete: transaction has unallocated balance.", [
                    'member_id' => $member->member_id,
                    'original_amount' => $amount,
                    'allocated_amount' => round($amount - $remainingAmount, 2),
                    'unallocated_amount' => $remainingAmount,
                    'reference' => $reference,
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]);

                DB::rollBack();

                return false;
            }

            if (!$postedAnything) {
                Log::warning("Smart allocation did not find any payable destination.", [
                    'member_id' => $member->member_id,
                    'original_amount' => $amount,
                    'reference' => $reference,
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]);

                DB::rollBack();

                return false;
            }

            DB::commit();

            Log::info("Smart allocation fully completed.", [
                'member_id' => $member->member_id,
                'original_amount' => $amount,
                'allocated_amount' => $amount,
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("Smart allocation failed with exception.", [
                'member_id' => $member->member_id ?? null,
                'reference' => $reference,
                'amount' => $amount,
                'transaction_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }
    private function resolveMemberFromUnspecifiedReference($reference, $transaction): ?object
    {
        $rawReference = trim((string) $reference);

        $cleanedId = preg_replace('/\D/', '', $rawReference);

        if (empty($cleanedId)) {
            Log::warning("Smart allocation member resolve failed: no numeric identifier.", [
                'reference' => $reference,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return null;
        }

        /*
     * Existing fallback behaviour:
     * - Known prefixes use member_id.
     * - Unknown references use national ID.
     */
        $prefix = strtoupper(substr($rawReference, 0, 2));

        $isKnownPrefix = in_array($prefix, ['SH', 'LN', 'CA', 'RF']) ||
            DB::table('sacco_fosa_types')
            ->whereRaw('UPPER(type_prefix) = ?', [$prefix])
            ->where('type_active', 'Y')
            ->exists();

        if ($isKnownPrefix) {
            $members = DB::table('sacco_members')
                ->where('member_id', $cleanedId)
                ->get();
        } else {
            $members = DB::table('sacco_members')
                ->where('member_national_id', $cleanedId)
                ->get();
        }

        if ($members->count() !== 1) {
            Log::warning("Smart allocation member resolve failed: expected exactly one member.", [
                'reference' => $reference,
                'cleaned_id' => $cleanedId,
                'matches' => $members->count(),
                'transaction_id' => $transaction->id ?? null,
            ]);

            return null;
        }

        return $members->first();
    }
    private function getMpesaAllocationPriorities()
    {
        return DB::table('sacco_mpesa_allocation_priorities')
            ->where(function ($query) {
                $query->where('priority_active', 'Y')
                    ->orWhere('priority_active', 1);
            })
            ->orderBy('priority_order', 'asc')
            ->get();
    }
    private function allocateSmartLoans($member, float $remainingAmount, $transaction): float
    {
        $memberId = $member->member_id ?? null;
        $period   = $this->getCurrentPeriod();

        if (!$memberId || $remainingAmount <= 0) {
            return 0.0;
        }

        $loans = DB::table('sacco_loans')
            ->where('loan_member', $memberId)
            ->whereRaw('(COALESCE(loan_amount, 0) - COALESCE(loan_loan_paid, 0)) > 0')
            ->orderBy('loan_id', 'asc')
            ->get();

        if ($loans->isEmpty()) {
            Log::info("Smart loan allocation skipped: no outstanding loans.", [
                'member_id' => $memberId,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return 0.0;
        }

        $allocatedTotal = 0.0;

        foreach ($loans as $loan) {
            if ($remainingAmount <= 0) {
                break;
            }

            $monthlyRemainingDue = $this->getLoanMonthlyRemainingDue($loan, $period);

            if ($monthlyRemainingDue <= 0) {
                continue;
            }

            $loanOutstanding = max(
                0,
                (float) ($loan->loan_amount ?? 0) - (float) ($loan->loan_loan_paid ?? 0)
            );

            if ($loanOutstanding <= 0) {
                continue;
            }

            $amountToLoan = min(
                $remainingAmount,
                $monthlyRemainingDue,
                $loanOutstanding
            );

            if ($amountToLoan <= 0) {
                continue;
            }

            $loanTransaction = clone $transaction;
            $loanTransaction->transaction_amount = $amountToLoan;
            $loanTransaction->bill_ref_number = "SMART-LN{$loan->loan_id}-{$transaction->bill_ref_number}";

            $posted = $this->processLoans('LN' . $loan->loan_id, $loanTransaction);

            if (!$posted) {
                Log::warning("Smart loan allocation failed while posting.", [
                    'member_id' => $memberId,
                    'loan_id' => $loan->loan_id,
                    'amount' => $amountToLoan,
                    'transaction_id' => $transaction->id ?? null,
                ]);

                continue;
            }

            $allocatedTotal += $amountToLoan;
            $remainingAmount -= $amountToLoan;

            Log::info("Smart loan allocation posted.", [
                'member_id' => $memberId,
                'loan_id' => $loan->loan_id,
                'amount' => $amountToLoan,
                'allocated_total' => $allocatedTotal,
                'remaining_after_loan' => $remainingAmount,
                'period' => $period,
                'transaction_id' => $transaction->id ?? null,
            ]);
        }

        return $allocatedTotal;
    }

    private function allocateSmartShares($member, float $remainingAmount, $transaction, $priority): float
    {
        $memberId = $member->member_id ?? null;
        $period   = $this->getCurrentPeriod();

        if (!$memberId || $remainingAmount <= 0) {
            return 0.0;
        }

        /*
     * Monthly share cap:
     * priority amount first, otherwise min_share_contribution default.
     * This prevents shares from swallowing all excess unless configured.
     */
        $targetAmount = $this->getPriorityConfiguredAmount($priority, [
            'min_share_contribution',
            'default_share_contribution',
            'default_monthly_share_contribution',
        ]);

        if ($targetAmount <= 0) {
            Log::info("Smart shares skipped: no monthly share target configured.", [
                'member_id' => $memberId,
                'priority_key' => $priority->priority_key ?? null,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return 0.0;
        }

        $alreadyPaid = (float) DB::table('sacco_shares')
            ->where('share_member_id', $memberId)
            ->where('share_period', $period)
            ->sum('share_amount_paying');

        $due = max(0, $targetAmount - $alreadyPaid);

        if ($due <= 0) {
            return 0.0;
        }

        $amountToPost = min($remainingAmount, $due);

        $shareTransaction = clone $transaction;
        $shareTransaction->transaction_amount = $amountToPost;
        $shareTransaction->bill_ref_number = "SMART-SH{$memberId}-{$transaction->bill_ref_number}";

        $posted = $this->processShares('SH' . $memberId, $shareTransaction);

        if (!$posted) {
            return 0.0;
        }

        Log::info("Smart shares allocation posted.", [
            'member_id' => $memberId,
            'amount' => $amountToPost,
            'target_amount' => $targetAmount,
            'already_paid' => $alreadyPaid,
            'period' => $period,
            'transaction_id' => $transaction->id ?? null,
        ]);

        return $amountToPost;
    }

    private function allocateSmartCapital($member, float $remainingAmount, $transaction, $priority): float
    {
        $memberId = $member->member_id ?? null;
        $period   = $this->getCurrentPeriod();

        if (!$memberId || $remainingAmount <= 0) {
            return 0.0;
        }

        /*
     * Capital cap:
     * priority amount first, otherwise min_capital_contribution default.
     */
        $targetAmount = $this->getPriorityConfiguredAmount($priority, [
            'min_capital_contribution',
            'default_capital_contribution',
            'default_monthly_capital_contribution',
        ]);

        if ($targetAmount <= 0) {
            Log::info("Smart capital skipped: no capital target configured.", [
                'member_id' => $memberId,
                'priority_key' => $priority->priority_key ?? null,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return 0.0;
        }

        $alreadyPaid = (float) DB::table('sacco_capital_shares')
            ->where('share_capitalmember_id', $memberId)
            ->where('share_capitalperiod', $period)
            ->sum('share_capitalamount_paying');

        $due = max(0, $targetAmount - $alreadyPaid);

        if ($due <= 0) {
            return 0.0;
        }

        $amountToPost = min($remainingAmount, $due);

        $capitalTransaction = clone $transaction;
        $capitalTransaction->transaction_amount = $amountToPost;
        $capitalTransaction->bill_ref_number = "SMART-CA{$memberId}-{$transaction->bill_ref_number}";

        $posted = $this->processCapital('CA' . $memberId, $capitalTransaction);

        if (!$posted) {
            return 0.0;
        }

        Log::info("Smart capital allocation posted.", [
            'member_id' => $memberId,
            'amount' => $amountToPost,
            'target_amount' => $targetAmount,
            'already_paid' => $alreadyPaid,
            'period' => $period,
            'transaction_id' => $transaction->id ?? null,
        ]);

        return $amountToPost;
    }

    private function allocateSmartRegistrationFee($member, float $remainingAmount, $transaction, $priority): float
    {
        $memberId = $member->member_id ?? null;

        if (!$memberId || $remainingAmount <= 0) {
            return 0.0;
        }

        /*
     * Registration fee is one-time.
     * If already paid, skip.
     */
        $alreadyPaid = (float) DB::table('sacco_registration_fees')
            ->where('regfee_member_id', $memberId)
            ->sum('regfee_amount');

        if ($alreadyPaid > 0) {
            return 0.0;
        }

        $targetAmount = $this->getPriorityConfiguredAmount($priority, [
            'default_member_registration_fee',
            'default_registration_fee_amount',
            'member_registration_fee',
            'registration_fee',
            'membership_fee',
            'default_member_ship_fee_amount',
        ]);

        if ($targetAmount <= 0) {
            Log::info("Smart registration fee skipped: no registration fee amount configured.", [
                'member_id' => $memberId,
                'priority_key' => $priority->priority_key ?? null,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return 0.0;
        }

        $amountToPost = min($remainingAmount, $targetAmount);

        if ($amountToPost <= 0) {
            return 0.0;
        }

        $feeTransaction = clone $transaction;
        $feeTransaction->transaction_amount = $amountToPost;
        $feeTransaction->bill_ref_number = "SMART-RF{$memberId}-{$transaction->bill_ref_number}";

        $period = $this->getCurrentPeriod();
        $now    = Carbon::now();
        $docNo  = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";
        $ip     = request()->ip() ?? '127.0.0.1';
        $userId = auth()->id() ?? 999;
        $desc   = "Mpesa By {$transaction->first_name} - {$feeTransaction->bill_ref_number}";

        $mpesaAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');

        $posted = $this->processRegistrationFee(
            $memberId,
            $feeTransaction,
            $desc,
            $docNo,
            $period,
            $now,
            $userId,
            $ip,
            $mpesaAccount
        );

        if (!$posted) {
            return 0.0;
        }

        Log::info("Smart registration fee allocation posted.", [
            'member_id' => $memberId,
            'amount' => $amountToPost,
            'target_amount' => $targetAmount,
            'transaction_id' => $transaction->id ?? null,
        ]);

        return $amountToPost;
    }

    private function allocateSmartFosa($member, float $remainingAmount, $transaction, $priority): float
    {
        $memberId = $member->member_id ?? null;
        $period   = $this->getCurrentPeriod();

        if (!$memberId || $remainingAmount <= 0) {
            return 0.0;
        }

        /*
     * FOSA cap:
     * priority amount first, otherwise min_fosa_contribution default.
     * This prevents FOSA from swallowing all excess unless configured.
     */
        $targetAmount = $this->getPriorityConfiguredAmount($priority, [
            'min_fosa_contribution',
            'default_fosa_contribution',
            'default_monthly_fosa_contribution',
        ]);

        if ($targetAmount <= 0) {
            Log::info("Smart FOSA skipped: no FOSA target configured.", [
                'member_id' => $memberId,
                'priority_key' => $priority->priority_key ?? null,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return 0.0;
        }

        $alreadyPaid = (float) DB::table('sacco_fosas')
            ->where('fosa_member_id', $memberId)
            ->where('fosa_period', $period)
            ->sum('fosa_amount_paying');

        $due = max(0, $targetAmount - $alreadyPaid);

        if ($due <= 0) {
            return 0.0;
        }

        $amountToPost = min($remainingAmount, $due);

        $mpesaAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');

        $fosaAccount = DB::table('sacco_defaults')
            ->where('default_name', 'default_fosa_account')
            ->value('default_value');

        if (!$mpesaAccount || !$fosaAccount) {
            Log::error("Smart FOSA allocation failed: missing ledger defaults.", [
                'member_id' => $memberId,
                'default_mpesa_in_account' => $mpesaAccount,
                'default_fosa_account' => $fosaAccount,
                'transaction_id' => $transaction->id ?? null,
            ]);

            return 0.0;
        }

        $now = Carbon::now();
        $ip = request()->ip() ?? '127.0.0.1';
        $userId = auth()->id() ?? 999;

        $docNo = "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";
        $senderName = $transaction->first_name ?: $transaction->msisdn;

        $description = "Smart FOSA Deposit - {$senderName} - Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

        DB::table('sacco_fosas')->insert([
            'fosa_member_id'      => $memberId,
            'fosa_amount_paying'  => $amountToPost,
            'fosa_paid_by'        => 'MPesa',
            'fosa_period'         => $period,
            'fosa_description'    => $description,
            'fosa_doc_no'         => $docNo,
            'fosa_date_paid'      => $now,
            'fosa_end_month_proc' => 'N',
            'fosa_by'             => $userId,
            'fosa_ip'             => $ip,
            'fosa_transdate'      => $now,
        ]);

        DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->increment('member_total_fosa', $amountToPost);

        $this->updateSaccoAccountsTrans(
            $mpesaAccount,
            $amountToPost,
            0,
            $docNo,
            $description,
            $transaction->transaction_time
        );

        $this->updateSaccoAccountsTrans(
            $fosaAccount,
            0,
            $amountToPost,
            $docNo,
            $description,
            $transaction->transaction_time
        );

        Log::info("Smart FOSA allocation posted.", [
            'member_id' => $memberId,
            'amount' => $amountToPost,
            'target_amount' => $targetAmount,
            'already_paid' => $alreadyPaid,
            'period' => $period,
            'transaction_id' => $transaction->id ?? null,
        ]);

        return $amountToPost;
    }
    private function getPriorityConfiguredAmount($priority, array $defaultNames = []): float
    {
        $candidateColumns = [
            'priority_amount',
            'priority_limit_amount',
            'priority_monthly_amount',
            'priority_target_amount',
            'priority_cap_amount',
            'amount',
            'monthly_amount',
            'target_amount',
            'cap_amount',
        ];

        foreach ($candidateColumns as $column) {
            if (isset($priority->{$column}) && is_numeric($priority->{$column})) {
                $value = (float) $priority->{$column};

                if ($value > 0) {
                    return $value;
                }
            }
        }

        foreach ($defaultNames as $defaultName) {
            $value = DB::table('sacco_defaults')
                ->where('default_name', $defaultName)
                ->value('default_value');

            if (is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return 0.0;
    }
}
