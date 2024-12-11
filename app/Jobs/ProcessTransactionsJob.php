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

class ProcessTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Fetch unprocessed transactions (limit to 100 records)
        $stkTransactions = DB::table('stk_push_responses')
            ->where('processed', 'N')
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        $c2bTransactions = DB::table('c2b_payments')
            ->where('processed', 'No')
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        // Process STK transactions
        foreach ($stkTransactions as $transaction) {
            $this->processTransaction($transaction, 'stk_push_responses');
        }

        // Process C2B transactions
        foreach ($c2bTransactions as $transaction) {
            $this->processTransaction($transaction, 'c2b_payments');
        }
    }

    private function processTransaction($transaction, $table)
    {
        try {
            // Determine if transaction is for shares or loans
            $reference = strtoupper($transaction->unique_number ?? $transaction->bill_ref_number);

            if (str_starts_with($reference, 'SH')) {
                $this->processShares($reference, $transaction);
            } elseif (str_starts_with($reference, 'LN')) {
                $this->processLoans($reference, $transaction);
            } else {
                Log::error("Unknown transaction type for reference: $reference");
                return;
            }

            // Mark transaction as processed
            DB::table($table)
                ->where('id', $transaction->id)
                ->update([
                    'processed' => 'Yes',
                    'processed_date' => Carbon::now(),
                ]);

        } catch (\Exception $e) {
            Log::error("Failed to process transaction ID {$transaction->id}: {$e->getMessage()}");
        }
    }

    private function processShares($reference, $transaction)
    {
        $memberId = ltrim($reference, 'SH');
        $defaultMpesaIn = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');

        $defaultMpesaShareDeposits = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_share_deposits')
            ->value('default_value');

        if (!$defaultMpesaIn || !$defaultMpesaShareDeposits) {
            Log::error("Missing default accounts for processing shares: Member ID {$memberId}");
            return;
        }

        // Update shares
        DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->increment('member_total_share', $transaction->amount);

        // Update ledger entries
        $this->updateSaccoAccountsTrans($defaultMpesaIn, 0, $transaction->amount, $transaction->transaction_id, "Shares Deposit - Member $memberId", $transaction->transaction_date);
        $this->updateSaccoAccountsTrans($defaultMpesaShareDeposits, $transaction->amount, 0, $transaction->transaction_id, "Shares Deposit - Member $memberId", $transaction->transaction_date);
    }

    private function processLoans($reference, $transaction)
    {
        $loanId = ltrim($reference, 'LN');
        $defaultMpesaIn = DB::table('sacco_defaults')
            ->where('default_name', 'default_mpesa_in_account')
            ->value('default_value');

        if (!$defaultMpesaIn) {
            Log::error("Missing default account for processing loan payments: Loan ID {$loanId}");
            return;
        }

        // Update loans
        DB::table('sacco_loans')
            ->where('loan_id', $loanId)
            ->increment('loan_loan_paid', $transaction->amount);

        // Release guarantors if applicable
        $this->releaseGuarantors($loanId, $transaction->amount);

        // Update ledger entries
        $this->updateSaccoAccountsTrans($defaultMpesaIn, 0, $transaction->amount, $transaction->transaction_id, "Loan Payment - Loan $loanId", $transaction->transaction_date);
    }

    private function updateSaccoAccountsTrans($account, $debit, $credit, $docNo, $description, $date)
    {
        DB::table('sacco_accounts_trans')->insert([
            'account_id' => $account,
            'debit' => $debit,
            'credit' => $credit,
            'doc_no' => $docNo,
            'description' => $description,
            'trans_date' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function releaseGuarantors($loanId, $amount)
    {
        $guarantors = DB::table('sacco_loan_guarantors')
            ->where('loan_id', $loanId)
            ->where('loan_guar_amount_freed', '<', DB::raw('loan_guar_amount'))
            ->get();

        foreach ($guarantors as $guarantor) {
            $releaseAmount = min($amount, $guarantor->loan_guar_amount - $guarantor->loan_guar_amount_freed);
            $amount -= $releaseAmount;

            DB::table('sacco_loan_guarantors')
                ->where('id', $guarantor->id)
                ->increment('loan_guar_amount_freed', $releaseAmount);

            if ($amount <= 0) {
                break;
            }
        }
    }
}