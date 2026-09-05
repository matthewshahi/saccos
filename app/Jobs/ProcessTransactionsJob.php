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
use App\Services\BulkSms\BulkSmsOutboxService;
 

class ProcessTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private array $smartAllocationBreakdown = [];

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
    */

        $updated = DB::table('c2b_payments')
            ->where('id', $transaction->id)
            ->where('picked', 'No')
            ->update([
                'picked' => 'Yes',
            ]);

        if (!$updated) {
            Log::warning(
                "C2B transaction already picked by another job.",
                [
                    'transaction_id' => $transaction->id,
                ]
            );

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Reset Allocation Breakdown
    |--------------------------------------------------------------------------
    */

        $this->smartAllocationBreakdown = [];

        /*
    |--------------------------------------------------------------------------
    | Normalize Reference
    |--------------------------------------------------------------------------
    */

        $reference = strtoupper(
            trim(
                str_replace(
                    ' ',
                    '',
                    (string) ($transaction->bill_ref_number ?? '')
                )
            )
        );

        Log::info(
            "Normalized transaction reference: {$reference}",
            [
                'transaction_id' => $transaction->id,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                'amount' => $transaction->transaction_amount ?? null,
            ]
        );

        /*
    |--------------------------------------------------------------------------
    | Receiver Acknowledgement
    |--------------------------------------------------------------------------
    |
    | This is queued ONCE immediately after the M-PESA transaction
    | has been claimed.
    |
    | It only confirms receipt of money.
    |
    */

        $this->queueMpesaReceiverSms(
            $transaction
        );

        /*
    |--------------------------------------------------------------------------
    | Management Notification State
    |--------------------------------------------------------------------------
    |
    | Smart allocation currently handles its own management notification.
    |
    | Explicit SH/LN/CA/RF/FOSA transactions are handled here.
    |
    */

        $managerNotificationHandled = false;
        $managerMember = null;
        $allocationLabel = 'M-PESA payment';

        $posted = false;

        try {

            /*
        |--------------------------------------------------------------------------
        | Operator Payment
        |--------------------------------------------------------------------------
        */

            if (
                preg_match(
                    '/^OP[A-Z]{2}-\d+(-\d+)?$/',
                    $reference
                )
            ) {

                $originalOperatorReference = $reference;

                Log::info(
                    "Operator payment reference detected.",
                    [
                        'original_reference' => $reference,
                        'transaction_id' => $transaction->id,
                        'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    ]
                );

                $operatorOk = $this->processOperatorTransaction(
                    $reference,
                    $transaction
                );

                if (!$operatorOk) {

                    Log::warning(
                        "Operator transaction failed before SACCO routing.",
                        [
                            'transaction_id' => $transaction->id ?? null,
                            'reference' => $originalOperatorReference,
                            'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                        ]
                    );

                    /*
                 * Operator reference failed before a final SACCO
                 * destination could be posted.
                 */

                    $this->queueMpesaManagerSms(
                        transaction: $transaction,
                        allocated: false,
                        member: null
                    );

                    $this->queueMpesaManagerEmail(
                        transaction: $transaction,
                        allocated: false,
                        member: null,
                        failureReason: 'The operator payment reference could not be safely routed to a SACCO allocation destination.'
                    );

                    return;
                }

                Log::info(
                    "Operator payment reference rewritten.",
                    [
                        'original_reference' => $originalOperatorReference,
                        'rewritten_reference' => $reference,
                        'transaction_id' => $transaction->id,
                        'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Detect Existing FOSA Prefix
        |--------------------------------------------------------------------------
        |
        | Must happen AFTER operator reference rewriting.
        |
        */

            $referencePrefix = strtoupper(
                substr(
                    $reference,
                    0,
                    2
                )
            );

            $isFosaReference = DB::table('sacco_fosa_types')
                ->whereRaw(
                    'UPPER(type_prefix) = ?',
                    [$referencePrefix]
                )
                ->where('type_active', 'Y')
                ->exists();

            /*
        |--------------------------------------------------------------------------
        | Route Transaction
        |--------------------------------------------------------------------------
        */

            if (str_starts_with($reference, 'SH')) {

                /*
            |--------------------------------------------------------------------------
            | Explicit Shares
            |--------------------------------------------------------------------------
            */

                $memberId = (int) substr(
                    $reference,
                    2
                );

                $allocationLabel = 'Shares';

                if ($memberId > 0) {
                    $managerMember = DB::table('sacco_members')
                        ->where(
                            'member_id',
                            $memberId
                        )
                        ->first();
                }

                Log::info(
                    "Identified as a Share transaction.",
                    [
                        'reference' => $reference,
                        'member_id' => $memberId ?: null,
                        'transaction_id' => $transaction->id ?? null,
                    ]
                );

                $posted = $this->processShares(
                    $reference,
                    $transaction
                );

                if ($posted) {
                    $this->smartAllocationBreakdown = [
                        [
                            'type' => 'shares',
                            'label' => 'Shares',
                            'reference' => $reference,
                            'amount' => round(
                                (float) ($transaction->transaction_amount ?? 0),
                                2
                            ),
                        ],
                    ];
                }
            } elseif (str_starts_with($reference, 'LN')) {

                /*
            |--------------------------------------------------------------------------
            | Explicit Loan
            |--------------------------------------------------------------------------
            */

                $loanId = (int) substr(
                    $reference,
                    2
                );

                $allocationLabel = $loanId > 0
                    ? "Loan LN{$loanId}"
                    : 'Loan';

                if ($loanId > 0) {

                    $loanMemberId = DB::table('sacco_loans')
                        ->where(
                            'loan_id',
                            $loanId
                        )
                        ->value('loan_member');

                    if ($loanMemberId) {
                        $managerMember = DB::table('sacco_members')
                            ->where(
                                'member_id',
                                $loanMemberId
                            )
                            ->first();
                    }
                }

                Log::info(
                    "Identified as a Loan transaction.",
                    [
                        'reference' => $reference,
                        'loan_id' => $loanId ?: null,
                        'member_id' => $managerMember->member_id ?? null,
                        'transaction_id' => $transaction->id ?? null,
                    ]
                );

                $posted = $this->processLoans(
                    $reference,
                    $transaction
                );

                if ($posted) {
                    $this->smartAllocationBreakdown = [
                        [
                            'type' => 'loan',
                            'label' => $allocationLabel,
                            'reference' => $reference,
                            'amount' => round(
                                (float) ($transaction->transaction_amount ?? 0),
                                2
                            ),
                        ],
                    ];
                }
            } elseif (str_starts_with($reference, 'CA')) {

                /*
            |--------------------------------------------------------------------------
            | Explicit Capital
            |--------------------------------------------------------------------------
            */

                $memberId = (int) substr(
                    $reference,
                    2
                );

                $allocationLabel = 'Capital';

                if ($memberId > 0) {
                    $managerMember = DB::table('sacco_members')
                        ->where(
                            'member_id',
                            $memberId
                        )
                        ->first();
                }

                Log::info(
                    "Identified as a Capital Shares transaction.",
                    [
                        'reference' => $reference,
                        'member_id' => $memberId ?: null,
                        'transaction_id' => $transaction->id ?? null,
                    ]
                );

                $posted = $this->processCapital(
                    $reference,
                    $transaction
                );

                if ($posted) {
                    $this->smartAllocationBreakdown = [
                        [
                            'type' => 'capital',
                            'label' => 'Capital',
                            'reference' => $reference,
                            'amount' => round(
                                (float) ($transaction->transaction_amount ?? 0),
                                2
                            ),
                        ],
                    ];
                }
            } elseif (str_starts_with($reference, 'RF')) {

                /*
            |--------------------------------------------------------------------------
            | Explicit Registration Fee
            |--------------------------------------------------------------------------
            */

                $memberId = (int) substr(
                    $reference,
                    2
                );

                $allocationLabel = 'Registration Fee';

                if ($memberId > 0) {
                    $managerMember = DB::table('sacco_members')
                        ->where(
                            'member_id',
                            $memberId
                        )
                        ->first();
                }

                Log::info(
                    "Identified as a Registration Fee transaction.",
                    [
                        'reference' => $reference,
                        'member_id' => $memberId ?: null,
                        'transaction_id' => $transaction->id ?? null,
                    ]
                );

                $period = $this->getCurrentPeriod();

                $now = Carbon::now();

                $docNo =
                    "Paybill {$transaction->business_shortcode} - {$transaction->transaction_id}";

                $ip = request()->ip()
                    ?? '127.0.0.1';

                $userId = auth()->id()
                    ?? 999;

                $desc =
                    "Mpesa By {$transaction->first_name} - {$transaction->bill_ref_number}";

                $mpesaAccount = DB::table('sacco_defaults')
                    ->where(
                        'default_name',
                        'default_mpesa_in_account'
                    )
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

                if ($posted) {
                    $this->smartAllocationBreakdown = [
                        [
                            'type' => 'registration_fee',
                            'label' => 'Registration Fee',
                            'reference' => $reference,
                            'amount' => round(
                                (float) ($transaction->transaction_amount ?? 0),
                                2
                            ),
                        ],
                    ];
                }
            } elseif ($isFosaReference) {

                /*
            |--------------------------------------------------------------------------
            | Explicit FOSA
            |--------------------------------------------------------------------------
            */

                $allocationLabel = "FOSA {$referencePrefix}";

                $memberIdPart = preg_replace(
                    '/\D+/',
                    '',
                    substr(
                        $reference,
                        2
                    )
                );

                if (
                    $memberIdPart !== null
                    && $memberIdPart !== ''
                    && is_numeric($memberIdPart)
                ) {
                    $managerMember = DB::table('sacco_members')
                        ->where(
                            'member_id',
                            (int) $memberIdPart
                        )
                        ->first();
                }

                Log::info(
                    "Identified as explicit FOSA transaction.",
                    [
                        'reference' => $reference,
                        'prefix' => $referencePrefix,
                        'member_id' => $managerMember->member_id ?? null,
                        'transaction_id' => $transaction->id ?? null,
                        'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    ]
                );

                $posted = $this->processFallbackTransaction(
                    $reference,
                    $transaction
                );

                if ($posted) {
                    $this->smartAllocationBreakdown = [
                        [
                            'type' => 'fosa',
                            'label' => $allocationLabel,
                            'reference' => $reference,
                            'amount' => round(
                                (float) ($transaction->transaction_amount ?? 0),
                                2
                            ),
                        ],
                    ];
                }
            } else {

                /*
            |--------------------------------------------------------------------------
            | Smart Allocation
            |--------------------------------------------------------------------------
            |
            | processSmartAllocation() already handles its own
            | management success/failure notification.
            |
            */

                $managerNotificationHandled = true;

                Log::info(
                    "No explicit SACCO/FOSA prefix found. Trying smart allocation.",
                    [
                        'reference' => $reference,
                        'prefix' => $referencePrefix,
                        'transaction_id' => $transaction->id ?? null,
                        'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    ]
                );

                $posted = $this->processSmartAllocation(
                    $reference,
                    $transaction
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Explicit Failure Notification
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Successful explicit payments are NOT notified here anymore.
        |
        | Their success notification is queued only AFTER c2b_payments
        | has been marked processed below.
        |
        */

            if (
                !$managerNotificationHandled
                && !$posted
            ) {

                $this->smartAllocationBreakdown = [];

                $this->queueMpesaManagerSms(
                    transaction: $transaction,
                    allocated: false,
                    member: $managerMember
                );

                $this->queueMpesaManagerEmail(
                    transaction: $transaction,
                    allocated: false,
                    member: $managerMember,
                    failureReason: "{$allocationLabel} payment could not be safely posted."
                );
            }
        } catch (\Throwable $e) {

            /*
        |--------------------------------------------------------------------------
        | Explicit Processing Exception
        |--------------------------------------------------------------------------
        */

            if (!$managerNotificationHandled) {

                $this->smartAllocationBreakdown = [];

                $this->queueMpesaManagerSms(
                    transaction: $transaction,
                    allocated: false,
                    member: $managerMember
                );

                $this->queueMpesaManagerEmail(
                    transaction: $transaction,
                    allocated: false,
                    member: $managerMember,
                    failureReason: "{$allocationLabel} processing failed due to a system error: "
                        . $e->getMessage()
                );
            }

            Log::error(
                "C2B transaction processing failed.",
                [
                    'transaction_id' => $transaction->id ?? null,
                    'reference' => $reference,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    'allocation_label' => $allocationLabel,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            throw $e;
        }

        /*
    |--------------------------------------------------------------------------
    | Do Not Mark Failed Payment Processed
    |--------------------------------------------------------------------------
    */

        if (!$posted) {

            Log::warning(
                "C2B transaction was picked but not posted.",
                [
                    'transaction_id' => $transaction->id,
                    'reference' => $reference,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    'amount' => $transaction->transaction_amount ?? null,
                ]
            );

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Mark C2B Transaction Processed
    |--------------------------------------------------------------------------
    |
    | Money has successfully reached its destination.
    |
    | Do this BEFORE sending an explicit success management notification.
    |
    */

        DB::table('c2b_payments')
            ->where(
                'id',
                $transaction->id
            )
            ->update([
                'processed' => 'Yes',
                'processed_date' => Carbon::now(),
            ]);

        Log::info(
            "Transaction ID {$transaction->id} marked as processed.",
            [
                'reference' => $reference,
                'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                'amount' => $transaction->transaction_amount ?? null,
            ]
        );

        /*
    |--------------------------------------------------------------------------
    | Explicit Success Management Notification
    |--------------------------------------------------------------------------
    |
    | Smart allocation has already handled its own notification.
    |
    | Explicit payments reach this point only AFTER:
    |
    | 1. destination posting succeeded
    | 2. c2b_payments was marked processed
    |
    */

        if (!$managerNotificationHandled) {

            $this->queueMpesaManagerSms(
                transaction: $transaction,
                allocated: true,
                member: $managerMember
            );

            $this->queueMpesaManagerEmail(
                transaction: $transaction,
                allocated: true,
                member: $managerMember
            );
        }
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
                'sacco_loan_types.loan_type_interest',
                'sacco_loan_types.loan_type_duration'
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

        /*
         * Interest repeat rule:
         * - Fixed-interest loans keep existing behaviour.
         * - Non-fixed one-month loans must not use YYYYMM as the interest window.
         *   They use a rolling one-month window from the current payment date.
         * - Longer non-fixed loans keep the existing YYYYMM period check.
         */
        $loanDuration = (int) ($loanDetails->loan_type_duration ?? 0);
        $isOneMonthNonFixedLoan = (
            $loanDetails->loan_type_interest_type !== "FIXED INTEREST"
            && $loanDuration <= 1
        );

        if ($isOneMonthNonFixedLoan) {
            $interestWindowStart = Carbon::parse($transaction->transaction_time ?? now())->subMonth();

            $existingPayment = DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loanId)
                ->where('loan_payments_interest', '>', 0)
                ->where('loan_payments_paid_on', '>=', $interestWindowStart->toDateString())
                ->exists();
        } else {
            $existingPayment = DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loanId)
                ->where('loan_payments_period', $currentPeriod)
                ->where('loan_payments_interest', '>', 0)
                ->exists();
        }

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

    // private function processSmartAllocation($reference, $transaction): bool
    // {
    //     /*
    // |--------------------------------------------------------------------------
    // | Smart M-PESA Allocation
    // |--------------------------------------------------------------------------
    // */

    //     $this->smartAllocationBreakdown = [];

    //     $member = $this->resolveMemberFromUnspecifiedReference(
    //         $reference,
    //         $transaction
    //     );

    //     /*
    // |--------------------------------------------------------------------------
    // | Member Could Not Be Identified
    // |--------------------------------------------------------------------------
    // */

    //     if (!$member) {
    //         Log::warning(
    //             "Smart allocation failed: member could not be resolved.",
    //             [
    //                 'reference' => $reference,
    //                 'transaction_id' => $transaction->id ?? null,
    //                 'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //             ]
    //         );

    //         $this->queueMpesaManagerSms(
    //             transaction: $transaction,
    //             allocated: false,
    //             member: null
    //         );

    //         $this->queueMpesaManagerEmail(
    //             transaction: $transaction,
    //             allocated: false,
    //             member: null,
    //             failureReason: 'Member could not be identified from the payment reference or M-PESA phone.'
    //         );

    //         return false;
    //     }

    //     /*
    // |--------------------------------------------------------------------------
    // | Validate Amount
    // |--------------------------------------------------------------------------
    // */

    //     $amount = (float) ($transaction->transaction_amount ?? 0);

    //     if ($amount <= 0) {
    //         Log::warning(
    //             "Smart allocation failed: invalid transaction amount.",
    //             [
    //                 'member_id' => $member->member_id ?? null,
    //                 'amount' => $amount,
    //                 'transaction_id' => $transaction->id ?? null,
    //                 'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //             ]
    //         );

    //         $this->queueMpesaManagerSms(
    //             transaction: $transaction,
    //             allocated: false,
    //             member: $member
    //         );

    //         $this->queueMpesaManagerEmail(
    //             transaction: $transaction,
    //             allocated: false,
    //             member: $member,
    //             failureReason: 'The M-PESA transaction amount was invalid or zero.'
    //         );

    //         return false;
    //     }

    //     /*
    // |--------------------------------------------------------------------------
    // | Load Priorities
    // |--------------------------------------------------------------------------
    // */

    //     $priorities = $this->getMpesaAllocationPriorities();

    //     if ($priorities->isEmpty()) {
    //         Log::warning(
    //             "Smart allocation priorities missing. Falling back to existing fallback processor.",
    //             [
    //                 'member_id' => $member->member_id,
    //                 'reference' => $reference,
    //                 'transaction_id' => $transaction->id ?? null,
    //                 'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //             ]
    //         );

    //         $posted = $this->processFallbackTransaction(
    //             $reference,
    //             $transaction
    //         );

    //         $this->queueMpesaManagerSms(
    //             transaction: $transaction,
    //             allocated: $posted,
    //             member: $member
    //         );

    //         $this->queueMpesaManagerEmail(
    //             transaction: $transaction,
    //             allocated: $posted,
    //             member: $member,
    //             failureReason: $posted
    //                 ? null
    //                 : 'The fallback processor could not safely post the payment.'
    //         );

    //         return $posted;
    //     }

    //     /*
    // |--------------------------------------------------------------------------
    // | Begin Smart Allocation
    // |--------------------------------------------------------------------------
    // */

    //     DB::beginTransaction();

    //     try {
    //         $remainingAmount = $amount;
    //         $postedAnything = false;

    //         foreach ($priorities as $priority) {

    //             if ($remainingAmount <= 0.00001) {
    //                 break;
    //             }

    //             $key = strtoupper(
    //                 trim(
    //                     (string) ($priority->priority_key ?? '')
    //                 )
    //             );

    //             if ($key === '') {
    //                 continue;
    //             }

    //             $allocated = 0.0;

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Run Appropriate Allocator
    //         |--------------------------------------------------------------------------
    //         */

    //             if (Str::contains($key, ['LOAN', 'LOANS'])) {

    //                 $allocated = $this->allocateSmartLoans(
    //                     $member,
    //                     $remainingAmount,
    //                     $transaction,
    //                     $priority
    //                 );
    //             } elseif (Str::contains($key, [
    //                 'SHARE',
    //                 'SHARES',
    //                 'DEPOSIT',
    //                 'DEPOSITS'
    //             ])) {

    //                 $allocated = $this->allocateSmartShares(
    //                     $member,
    //                     $remainingAmount,
    //                     $transaction,
    //                     $priority
    //                 );
    //             } elseif (Str::contains($key, ['CAPITAL'])) {

    //                 $allocated = $this->allocateSmartCapital(
    //                     $member,
    //                     $remainingAmount,
    //                     $transaction,
    //                     $priority
    //                 );
    //             } elseif (Str::contains($key, [
    //                 'REG',
    //                 'REGISTRATION',
    //                 'MEMBERSHIP'
    //             ])) {

    //                 $allocated = $this->allocateSmartRegistrationFee(
    //                     $member,
    //                     $remainingAmount,
    //                     $transaction,
    //                     $priority
    //                 );
    //             } elseif (Str::contains($key, [
    //                 'FOSA',
    //                 'SAVING',
    //                 'SAVINGS'
    //             ])) {

    //                 $allocated = $this->allocateSmartFosa(
    //                     $member,
    //                     $remainingAmount,
    //                     $transaction,
    //                     $priority
    //                 );
    //             }

    //             $allocated = round(
    //                 (float) $allocated,
    //                 2
    //             );

    //             if ($allocated <= 0) {
    //                 continue;
    //             }

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Safety Check
    //         |--------------------------------------------------------------------------
    //         */

    //             if ($allocated > $remainingAmount) {

    //                 Log::warning(
    //                     "Smart allocation rejected: allocator returned more than remaining amount.",
    //                     [
    //                         'member_id' => $member->member_id,
    //                         'priority_key' => $key,
    //                         'allocated' => $allocated,
    //                         'remaining_before' => $remainingAmount,
    //                         'transaction_id' => $transaction->id ?? null,
    //                         'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //                     ]
    //                 );

    //                 DB::rollBack();
    //                 $this->smartAllocationBreakdown = [];

    //                 $this->queueMpesaManagerSms(
    //                     transaction: $transaction,
    //                     allocated: false,
    //                     member: $member
    //                 );

    //                 return false;
    //             }

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Reduce Remaining Balance
    //         |--------------------------------------------------------------------------
    //         */

    //             $remainingAmount = round(
    //                 $remainingAmount - $allocated,
    //                 2
    //             );

    //             $postedAnything = true;

    //             Log::info(
    //                 "Smart allocation priority posted.",
    //                 [
    //                     'member_id' => $member->member_id,
    //                     'priority_key' => $key,
    //                     'allocated' => $allocated,
    //                     'remaining' => $remainingAmount,
    //                     'transaction_id' => $transaction->id ?? null,
    //                     'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //                 ]
    //             );
    //         }

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Unallocated Balance
    //     |--------------------------------------------------------------------------
    //     |
    //     | Entire database transaction is rolled back.
    //     |
    //     */

    //         if ($remainingAmount > 0.00001) {

    //             Log::warning(
    //                 "Smart allocation incomplete: transaction has unallocated balance.",
    //                 [
    //                     'member_id' => $member->member_id,
    //                     'original_amount' => $amount,
    //                     'allocated_amount' => round(
    //                         $amount - $remainingAmount,
    //                         2
    //                     ),
    //                     'unallocated_amount' => $remainingAmount,
    //                     'reference' => $reference,
    //                     'transaction_id' => $transaction->id ?? null,
    //                     'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //                 ]
    //             );

    //             DB::rollBack();

    //             $this->smartAllocationBreakdown = [];

    //             $this->queueMpesaManagerSms(
    //                 transaction: $transaction,
    //                 allocated: false,
    //                 member: $member
    //             );

    //             return false;
    //         }

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Nothing Was Posted
    //     |--------------------------------------------------------------------------
    //     */

    //         if (!$postedAnything) {

    //             Log::warning(
    //                 "Smart allocation did not find any payable destination.",
    //                 [
    //                     'member_id' => $member->member_id,
    //                     'original_amount' => $amount,
    //                     'reference' => $reference,
    //                     'transaction_id' => $transaction->id ?? null,
    //                     'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //                 ]
    //             );

    //             DB::rollBack();
    //             $this->smartAllocationBreakdown = [];

    //             $this->queueMpesaManagerSms(
    //                 transaction: $transaction,
    //                 allocated: false,
    //                 member: $member
    //             );

    //             return false;
    //         }

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Allocation Fully Successful
    //     |--------------------------------------------------------------------------
    //     */

    //         DB::commit();

    //         Log::info(
    //             "Smart allocation fully completed.",
    //             [
    //                 'member_id' => $member->member_id,
    //                 'original_amount' => $amount,
    //                 'allocated_amount' => $amount,
    //                 'reference' => $reference,
    //                 'transaction_id' => $transaction->id ?? null,
    //                 'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //             ]
    //         );

    //         $this->queueMpesaManagerSms(
    //             transaction: $transaction,
    //             allocated: true,
    //             member: $member
    //         );

    //         return true;
    //     } catch (\Throwable $e) {

    //         DB::rollBack();
    //         $this->smartAllocationBreakdown = [];

    //         Log::error(
    //             "Smart allocation failed with exception.",
    //             [
    //                 'member_id' => $member->member_id ?? null,
    //                 'reference' => $reference,
    //                 'amount' => $amount,
    //                 'transaction_id' => $transaction->id ?? null,
    //                 'mpesa_transaction_id' => $transaction->transaction_id ?? null,
    //                 'error' => $e->getMessage(),
    //                 'file' => $e->getFile(),
    //                 'line' => $e->getLine(),
    //             ]
    //         );

    //         /*
    //      * Accounting has already been rolled back.
    //      * SMS failure cannot affect the M-PESA transaction state.
    //      */
    //         $this->queueMpesaManagerSms(
    //             transaction: $transaction,
    //             allocated: false,
    //             member: $member
    //         );

    //         throw $e;
    //     }
    // }

    private function processSmartAllocation($reference, $transaction): bool
    {
        /*
    |--------------------------------------------------------------------------
    | Smart M-PESA Allocation
    |--------------------------------------------------------------------------
    */

        $this->smartAllocationBreakdown = [];

        $member = $this->resolveMemberFromUnspecifiedReference(
            $reference,
            $transaction
        );

        /*
    |--------------------------------------------------------------------------
    | Member Could Not Be Identified
    |--------------------------------------------------------------------------
    */

        if (!$member) {
            Log::warning(
                "Smart allocation failed: member could not be resolved.",
                [
                    'reference' => $reference,
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]
            );

            $this->queueMpesaManagerSms(
                transaction: $transaction,
                allocated: false,
                member: null
            );

            $this->queueMpesaManagerEmail(
                transaction: $transaction,
                allocated: false,
                member: null,
                failureReason: 'Member could not be identified from the payment reference or M-PESA phone.'
            );

            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | Validate Amount
    |--------------------------------------------------------------------------
    */

        $amount = (float) ($transaction->transaction_amount ?? 0);

        if ($amount <= 0) {
            Log::warning(
                "Smart allocation failed: invalid transaction amount.",
                [
                    'member_id' => $member->member_id ?? null,
                    'amount' => $amount,
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]
            );

            $this->queueMpesaManagerSms(
                transaction: $transaction,
                allocated: false,
                member: $member
            );

            $this->queueMpesaManagerEmail(
                transaction: $transaction,
                allocated: false,
                member: $member,
                failureReason: 'The M-PESA transaction amount was invalid or zero.'
            );

            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | Load Priorities
    |--------------------------------------------------------------------------
    */

        $priorities = $this->getMpesaAllocationPriorities();

        if ($priorities->isEmpty()) {
            Log::warning(
                "Smart allocation priorities missing. Falling back to existing fallback processor.",
                [
                    'member_id' => $member->member_id,
                    'reference' => $reference,
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]
            );

            $posted = $this->processFallbackTransaction(
                $reference,
                $transaction
            );

            $this->queueMpesaManagerSms(
                transaction: $transaction,
                allocated: $posted,
                member: $member
            );

            $this->queueMpesaManagerEmail(
                transaction: $transaction,
                allocated: $posted,
                member: $member,
                failureReason: $posted
                    ? null
                    : 'The fallback processor could not safely post the payment.'
            );

            return $posted;
        }

        /*
    |--------------------------------------------------------------------------
    | Begin Smart Allocation
    |--------------------------------------------------------------------------
    */

        DB::beginTransaction();

        try {
            $remainingAmount = $amount;
            $postedAnything = false;

            foreach ($priorities as $priority) {

                if ($remainingAmount <= 0.00001) {
                    break;
                }

                $key = strtoupper(
                    trim(
                        (string) ($priority->priority_key ?? '')
                    )
                );

                if ($key === '') {
                    continue;
                }

                $allocated = 0.0;

                /*
            |--------------------------------------------------------------------------
            | Run Appropriate Allocator
            |--------------------------------------------------------------------------
            */

                if (Str::contains($key, ['LOAN', 'LOANS'])) {

                    $allocated = $this->allocateSmartLoans(
                        $member,
                        $remainingAmount,
                        $transaction,
                        $priority
                    );
                } elseif (Str::contains($key, [
                    'SHARE',
                    'SHARES',
                    'DEPOSIT',
                    'DEPOSITS'
                ])) {

                    $allocated = $this->allocateSmartShares(
                        $member,
                        $remainingAmount,
                        $transaction,
                        $priority
                    );
                } elseif (Str::contains($key, ['CAPITAL'])) {

                    $allocated = $this->allocateSmartCapital(
                        $member,
                        $remainingAmount,
                        $transaction,
                        $priority
                    );
                } elseif (Str::contains($key, [
                    'REG',
                    'REGISTRATION',
                    'MEMBERSHIP'
                ])) {

                    $allocated = $this->allocateSmartRegistrationFee(
                        $member,
                        $remainingAmount,
                        $transaction,
                        $priority
                    );
                } elseif (Str::contains($key, [
                    'FOSA',
                    'SAVING',
                    'SAVINGS'
                ])) {

                    $allocated = $this->allocateSmartFosa(
                        $member,
                        $remainingAmount,
                        $transaction,
                        $priority
                    );
                }

                $allocated = round(
                    (float) $allocated,
                    2
                );

                if ($allocated <= 0) {
                    continue;
                }

                /*
            |--------------------------------------------------------------------------
            | Safety Check
            |--------------------------------------------------------------------------
            */

                if ($allocated > $remainingAmount) {

                    Log::warning(
                        "Smart allocation rejected: allocator returned more than remaining amount.",
                        [
                            'member_id' => $member->member_id,
                            'priority_key' => $key,
                            'allocated' => $allocated,
                            'remaining_before' => $remainingAmount,
                            'transaction_id' => $transaction->id ?? null,
                            'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                        ]
                    );

                    DB::rollBack();

                    $this->smartAllocationBreakdown = [];

                    $this->queueMpesaManagerSms(
                        transaction: $transaction,
                        allocated: false,
                        member: $member
                    );

                    $this->queueMpesaManagerEmail(
                        transaction: $transaction,
                        allocated: false,
                        member: $member,
                        failureReason: 'Smart allocation attempted to allocate more than the remaining M-PESA amount.'
                    );

                    return false;
                }

                /*
            |--------------------------------------------------------------------------
            | Reduce Remaining Balance
            |--------------------------------------------------------------------------
            */

                $remainingAmount = round(
                    $remainingAmount - $allocated,
                    2
                );

                $postedAnything = true;

                Log::info(
                    "Smart allocation priority posted.",
                    [
                        'member_id' => $member->member_id,
                        'priority_key' => $key,
                        'allocated' => $allocated,
                        'remaining' => $remainingAmount,
                        'transaction_id' => $transaction->id ?? null,
                        'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Unallocated Balance
        |--------------------------------------------------------------------------
        |
        | Entire database transaction is rolled back.
        |
        */

            if ($remainingAmount > 0.00001) {

                Log::warning(
                    "Smart allocation incomplete: transaction has unallocated balance.",
                    [
                        'member_id' => $member->member_id,
                        'original_amount' => $amount,
                        'allocated_amount' => round(
                            $amount - $remainingAmount,
                            2
                        ),
                        'unallocated_amount' => $remainingAmount,
                        'reference' => $reference,
                        'transaction_id' => $transaction->id ?? null,
                        'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    ]
                );

                DB::rollBack();

                $this->smartAllocationBreakdown = [];

                $this->queueMpesaManagerSms(
                    transaction: $transaction,
                    allocated: false,
                    member: $member
                );

                $this->queueMpesaManagerEmail(
                    transaction: $transaction,
                    allocated: false,
                    member: $member,
                    failureReason: 'The full M-PESA amount could not be safely allocated according to the configured priorities.'
                );

                return false;
            }

            /*
        |--------------------------------------------------------------------------
        | Nothing Was Posted
        |--------------------------------------------------------------------------
        */

            if (!$postedAnything) {

                Log::warning(
                    "Smart allocation did not find any payable destination.",
                    [
                        'member_id' => $member->member_id,
                        'original_amount' => $amount,
                        'reference' => $reference,
                        'transaction_id' => $transaction->id ?? null,
                        'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    ]
                );

                DB::rollBack();

                $this->smartAllocationBreakdown = [];

                $this->queueMpesaManagerSms(
                    transaction: $transaction,
                    allocated: false,
                    member: $member
                );

                $this->queueMpesaManagerEmail(
                    transaction: $transaction,
                    allocated: false,
                    member: $member,
                    failureReason: 'No payable smart-allocation destination was found for this member.'
                );

                return false;
            }

            /*
        |--------------------------------------------------------------------------
        | Allocation Fully Successful
        |--------------------------------------------------------------------------
        */

            DB::commit();

            Log::info(
                "Smart allocation fully completed.",
                [
                    'member_id' => $member->member_id,
                    'original_amount' => $amount,
                    'allocated_amount' => $amount,
                    'allocation_breakdown' => $this->smartAllocationBreakdown,
                    'reference' => $reference,
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]
            );

            /*
         * SMS is optional depending on SEND_SECONDARY_AUTO_ALERTS_SMS.
         */
            $this->queueMpesaManagerSms(
                transaction: $transaction,
                allocated: true,
                member: $member
            );

            /*
         * Management email is always queued when manager emails
         * have been configured.
         *
         * IMPORTANT:
         * The database allocation has already committed, so the
         * allocation breakdown now represents real posted entries.
         */
            $this->queueMpesaManagerEmail(
                transaction: $transaction,
                allocated: true,
                member: $member
            );

            return true;
        } catch (\Throwable $e) {

            /*
        |--------------------------------------------------------------------------
        | Roll Back Accounting
        |--------------------------------------------------------------------------
        */

            DB::rollBack();

            /*
         * Any allocations captured in memory were rolled back as well,
         * so do not expose them as successful allocations.
         */
            $this->smartAllocationBreakdown = [];

            Log::error(
                "Smart allocation failed with exception.",
                [
                    'member_id' => $member->member_id ?? null,
                    'reference' => $reference,
                    'amount' => $amount,
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            /*
         * Notification failures themselves are caught inside the
         * notification helper functions and therefore cannot affect
         * the accounting rollback above.
         */

            $this->queueMpesaManagerSms(
                transaction: $transaction,
                allocated: false,
                member: $member
            );

            $this->queueMpesaManagerEmail(
                transaction: $transaction,
                allocated: false,
                member: $member,
                failureReason: 'Smart allocation failed due to a system processing error: '
                    . $e->getMessage()
            );

            throw $e;
        }
    }

    private function resolveMemberFromUnspecifiedReference(
        $reference,
        $transaction
    ): ?object {
        /*
    |--------------------------------------------------------------------------
    | Resolve Member for an Unspecified / Smart Allocation Reference
    |--------------------------------------------------------------------------
    |
    | Resolution order:
    |
    | 1. Exact SACCO member number
    | 2. Exact national ID
    | 3. M-PESA payer phone hash
    |
    | A valid reference remains authoritative.
    | Phone hash is only the fallback when the reference cannot identify
    | a member.
    |--------------------------------------------------------------------------
    */

        $rawReference = strtoupper(
            trim((string) $reference)
        );

        /*
    |--------------------------------------------------------------------------
    | 1. Try the supplied reference first
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | 266
    |
    | can identify member_sacco_id = 266.
    |
    | We deliberately do NOT strip arbitrary letters from the reference and
    | turn something such as ABC266XYZ into 266.
    |--------------------------------------------------------------------------
    */

        if ($rawReference !== '') {
            $members = DB::table('sacco_members')
                ->where(function ($query) use ($rawReference) {

                    /*
                 * SACCO/member/account number.
                 */
                    $query->whereRaw(
                        'UPPER(TRIM(member_sacco_id)) = ?',
                        [$rawReference]
                    );

                    /*
                 * Kenyan national IDs are numeric.
                 *
                 * Only consider national ID when the whole reference itself
                 * is numeric.
                 */
                    if (preg_match('/^\d+$/', $rawReference)) {
                        $query->orWhere(
                            'member_national_id',
                            $rawReference
                        );
                    }
                })
                ->get()
                ->unique('member_id')
                ->values();

            if ($members->count() === 1) {
                $member = $members->first();

                Log::info(
                    'Smart allocation member resolved from payment reference.',
                    [
                        'member_id' => $member->member_id,
                        'reference' => $rawReference,
                        'transaction_id' =>
                        $transaction->id ?? null,
                        'mpesa_transaction_id' =>
                        $transaction->transaction_id ?? null,
                    ]
                );

                return $member;
            }

            /*
         * If the supplied reference itself identifies more than one different
         * member, do NOT guess using the payer phone.
         */
            if ($members->count() > 1) {
                Log::warning(
                    'Smart allocation member resolution ambiguous from reference.',
                    [
                        'reference' => $rawReference,
                        'matches' => $members->count(),
                        'transaction_id' =>
                        $transaction->id ?? null,
                        'mpesa_transaction_id' =>
                        $transaction->transaction_id ?? null,
                    ]
                );

                return null;
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 2. Reference did not identify anybody — try M-PESA payer phone
    |--------------------------------------------------------------------------
    |
    | Examples of references reaching here:
    |
    | loan
    | payment
    | pay
    | blank / invalid member reference
    |
    | Incoming M-PESA MSISDN may already be:
    |
    | ad652902ac9acb...
    |
    | OR may still be:
    |
    | 254722400737
    |
    | Both cases are handled.
    |--------------------------------------------------------------------------
    */

        return $this->resolveMemberFromMpesaMsisdn(
            $transaction
        );
    }

    private function resolveMemberFromMpesaMsisdn(
        $transaction
    ): ?object {
        $incomingMsisdn = trim(
            (string) (
                $transaction->msisdn
                ?? ''
            )
        );

        if ($incomingMsisdn === '') {
            Log::warning(
                'Smart allocation phone resolution failed: M-PESA MSISDN is empty.',
                [
                    'transaction_id' =>
                    $transaction->id ?? null,
                    'mpesa_transaction_id' =>
                    $transaction->transaction_id ?? null,
                ]
            );

            return null;
        }

        /*
    |--------------------------------------------------------------------------
    | Safaricom may already have supplied SHA-256
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | ad652902ac9acb49443e5ef71d640fc94777c558b5f6c25172249470d5062dfb
    |--------------------------------------------------------------------------
    */

        if (
            preg_match(
                '/^[a-f0-9]{64}$/i',
                $incomingMsisdn
            )
        ) {
            $phoneHash = strtolower(
                $incomingMsisdn
            );

            $source = 'SAFARICOM_HASH';
        } else {
            /*
        |--------------------------------------------------------------------------
        | Safaricom supplied plaintext MSISDN
        |--------------------------------------------------------------------------
        */

            $normalizedPhone =
                $this->normalizeKenyanMobileForMpesaHash(
                    $incomingMsisdn
                );

            if ($normalizedPhone === null) {
                Log::warning(
                    'Smart allocation phone resolution failed: invalid Kenyan M-PESA MSISDN.',
                    [
                        'transaction_id' =>
                        $transaction->id ?? null,
                        'mpesa_transaction_id' =>
                        $transaction->transaction_id ?? null,
                    ]
                );

                return null;
            }

            /*
         * Example:
         *
         * normalized:
         * 254722400737
         *
         * SHA-256:
         * ad652902ac9acb...
         */
            $phoneHash = hash(
                'sha256',
                $normalizedPhone
            );

            $source = 'PLAINTEXT_MSISDN_HASHED';
        }

        /*
    |--------------------------------------------------------------------------
    | Match against our pre-generated member hash
    |--------------------------------------------------------------------------
    */

        $members = DB::table('sacco_members')
            ->whereRaw(
                'LOWER(TRIM(member_phone_hash)) = ?',
                [$phoneHash]
            )
            ->get();

        /*
     * One and only one member must match.
     *
     * Shared/duplicate numbers are not safe for automatic allocation.
     */
        if ($members->count() !== 1) {
            Log::warning(
                'Smart allocation phone hash did not identify exactly one member.',
                [
                    'matches' => $members->count(),
                    'hash_source' => $source,
                    'transaction_id' =>
                    $transaction->id ?? null,
                    'mpesa_transaction_id' =>
                    $transaction->transaction_id ?? null,
                ]
            );

            return null;
        }

        $member = $members->first();

        Log::info(
            'Smart allocation member resolved from M-PESA phone hash.',
            [
                'member_id' => $member->member_id,
                'hash_source' => $source,
                'transaction_id' =>
                $transaction->id ?? null,
                'mpesa_transaction_id' =>
                $transaction->transaction_id ?? null,
            ]
        );

        return $member;
    }



    private function normalizeKenyanMobileForMpesaHash(
        ?string $phone
    ): ?string {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        /*
     * Reject alphabetic garbage rather than silently repairing it.
     */
        if (preg_match('/[a-z]/i', $phone)) {
            return null;
        }

        /*
     * Remove harmless formatting:
     *
     * +254 722 400 737
     * 0722-400-737
     * 722 400 737
     */
        $digits = preg_replace(
            '/\D+/',
            '',
            $phone
        );

        if (
            $digits === null
            || $digits === ''
        ) {
            return null;
        }

        /*
     * 00254722400737
     * ->
     * 254722400737
     */
        if (str_starts_with($digits, '00254')) {
            $digits = substr(
                $digits,
                2
            );
        }

        /*
     * 2540722400737
     * ->
     * 254722400737
     */
        if (str_starts_with($digits, '2540')) {
            $digits =
                '254'
                . substr(
                    $digits,
                    4
                );
        }

        /*
     * 0722400737
     * ->
     * 254722400737
     */ elseif (
            strlen($digits) === 10
            && str_starts_with(
                $digits,
                '0'
            )
        ) {
            $digits =
                '254'
                . substr(
                    $digits,
                    1
                );
        }

        /*
     * 722400737
     * ->
     * 254722400737
     */ elseif (
            strlen($digits) === 9
            && in_array(
                substr($digits, 0, 1),
                ['7', '1'],
                true
            )
        ) {
            $digits =
                '254'
                . $digits;
        }

        /*
     * Accept Kenyan mobile numbers only.
     */
        if (
            !preg_match(
                '/^254(?:7\d{8}|1\d{8})$/',
                $digits
            )
        ) {
            return null;
        }

        /*
     * IMPORTANT:
     *
     * Return without "+" because this is exactly what Safaricom's
     * SHA-256 value is based upon.
     */
        return $digits;
    }
    private function getMpesaAllocationPriorities()
    {
        $priorities = DB::table('sacco_mpesa_allocation_priorities')
            ->where(function ($query) {
                $query->where('priority_active', 'Y')
                    ->orWhere('priority_active', 1);
            })
            ->orderBy('priority_order', 'asc')
            ->orderBy('priority_id', 'asc')
            ->get();

        if ($priorities->isNotEmpty()) {
            return $priorities;
        }

        DB::transaction(function () {
            $order = 1;

            $loanTypes = DB::table('sacco_loan_types')
                ->where(function ($query) {
                    $query->whereNull('loan_type_deleted')
                        ->orWhere('loan_type_deleted', '!=', 'Y');
                })
                ->where(function ($query) {
                    $query->where('loan_type_active', 1)
                        ->orWhere('loan_type_active', 'Y');
                })
                ->whereRaw("LOWER(COALESCE(loan_type_name, '')) NOT LIKE ?", ['%test%'])
                ->orderByRaw('COALESCE(loan_type_duration, 999999) ASC')
                ->orderBy('loan_type_id', 'asc')
                ->get();

            foreach ($loanTypes as $loanType) {
                DB::table('sacco_mpesa_allocation_priorities')->updateOrInsert(
                    ['priority_key' => 'LOAN_TYPE_' . $loanType->loan_type_id],
                    [
                        'priority_type' => 'LOAN_TYPE',
                        'priority_source_table' => 'sacco_loan_types',
                        'priority_source_id' => $loanType->loan_type_id,
                        'priority_label' => $loanType->loan_type_name,
                        'priority_order' => $order++,
                        'priority_active' => 'Y',
                        'priority_notes' => 'Auto-created smart allocation priority: loan types ordered shortest to longest repayment duration.',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::table('sacco_mpesa_allocation_priorities')->updateOrInsert(
                ['priority_key' => 'SHARES'],
                [
                    'priority_type' => 'SYSTEM',
                    'priority_source_table' => 'sacco_shares',
                    'priority_source_id' => 0,
                    'priority_label' => 'Shares',
                    'priority_order' => $order++,
                    'priority_active' => 'Y',
                    'priority_notes' => 'Auto-created smart allocation priority. Shares absorbs remaining balance.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('sacco_mpesa_allocation_priorities')->updateOrInsert(
                ['priority_key' => 'FOSA'],
                [
                    'priority_type' => 'SYSTEM',
                    'priority_source_table' => 'sacco_fosas',
                    'priority_source_id' => 0,
                    'priority_label' => 'FOSA Savings',
                    'priority_order' => $order++,
                    'priority_active' => 'Y',
                    'priority_notes' => 'Auto-created smart allocation priority. Used only if reached.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });

        return DB::table('sacco_mpesa_allocation_priorities')
            ->where(function ($query) {
                $query->where('priority_active', 'Y')
                    ->orWhere('priority_active', 1);
            })
            ->orderBy('priority_order', 'asc')
            ->orderBy('priority_id', 'asc')
            ->get();
    }

    private function allocateSmartLoans($member, float $remainingAmount, $transaction, $priority = null): float
    {
        $memberId = $member->member_id ?? null;
        $period   = $this->getCurrentPeriod();

        if (!$memberId || $remainingAmount <= 0) {
            return 0.0;
        }

        $loanQuery = DB::table('sacco_loans')
            ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
            ->where('sacco_loans.loan_member', $memberId)
            ->whereRaw('(COALESCE(sacco_loans.loan_amount, 0) - COALESCE(sacco_loans.loan_loan_paid, 0)) > 0')
            ->select('sacco_loans.*', 'sacco_loan_types.loan_type_duration');

        if (
            $priority
            && isset($priority->priority_source_table, $priority->priority_source_id)
            && $priority->priority_source_table === 'sacco_loan_types'
            && (int) $priority->priority_source_id > 0
        ) {
            $loanQuery->where('sacco_loans.loan_loan_type', (int) $priority->priority_source_id);
        }

        $loans = $loanQuery
            ->orderByRaw('COALESCE(sacco_loan_types.loan_type_duration, 999999) ASC')
            ->orderBy('sacco_loans.loan_id', 'asc')
            ->get();

        if ($loans->isEmpty()) {
            return 0.0;
        }

        $allocatedTotal = 0.0;

        foreach ($loans as $loan) {
            if ($remainingAmount <= 0) {
                break;
            }

            $monthlyRemainingDue = $this->getSmartLoanMonthlyRemainingDue($loan, $period);

            if ($monthlyRemainingDue <= 0) {
                continue;
            }

            $amountToLoan = min($remainingAmount, $monthlyRemainingDue);

            if ($amountToLoan <= 0) {
                continue;
            }

            $loanTransaction = clone $transaction;
            $loanTransaction->transaction_amount = $amountToLoan;
            $loanTransaction->bill_ref_number = "SMART-LN{$loan->loan_id}-{$transaction->bill_ref_number}";

            $posted = $this->processLoans('LN' . $loan->loan_id, $loanTransaction);

            if (!$posted) {
                continue;
            }

            $allocatedTotal = round($allocatedTotal + $amountToLoan, 2);
            $this->smartAllocationBreakdown[] = [
                'type' => 'loan',
                'label' => 'Loan LN' . $loan->loan_id,
                'reference' => 'LN' . $loan->loan_id,
                'amount' => round($amountToLoan, 2),
            ];

            $remainingAmount = round($remainingAmount - $amountToLoan, 2);

            Log::info("Smart loan allocation posted.", [
                'member_id' => $memberId,
                'loan_id' => $loan->loan_id,
                'loan_type' => $loan->loan_loan_type ?? null,
                'amount' => $amountToLoan,
                'allocated_total' => $allocatedTotal,
                'remaining_after_loan' => $remainingAmount,
                'period' => $period,
                'priority_key' => $priority->priority_key ?? null,
                'transaction_id' => $transaction->id ?? null,
            ]);
        }

        return $allocatedTotal;
    }

    private function allocateSmartShares($member, float $remainingAmount, $transaction, $priority): float
    {
        $memberId = $member->member_id ?? null;

        if (!$memberId || $remainingAmount <= 0) {
            return 0.0;
        }

        $amountToPost = $remainingAmount;

        $shareTransaction = clone $transaction;
        $shareTransaction->transaction_amount = $amountToPost;
        $shareTransaction->bill_ref_number = "SMART-SH{$memberId}-{$transaction->bill_ref_number}";

        $posted = $this->processShares('SH' . $memberId, $shareTransaction);

        if (!$posted) {
            return 0.0;
        }

        $this->smartAllocationBreakdown[] = [
            'type' => 'shares',
            'label' => 'Shares',
            'reference' => 'SH' . $memberId,
            'amount' => round($amountToPost, 2),
        ];


        Log::info("Smart shares allocation posted.", [
            'member_id' => $memberId,
            'amount' => $amountToPost,
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

        $this->smartAllocationBreakdown[] = [
            'type' => 'capital',
            'label' => 'Capital',
            'reference' => 'CA' . $memberId,
            'amount' => round($amountToPost, 2),
        ];

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

        $this->smartAllocationBreakdown[] = [
            'type' => 'registration_fee',
            'label' => 'Registration Fee',
            'reference' => 'RF' . $memberId,
            'amount' => round($amountToPost, 2),
        ];

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

        $amountToPost = $remainingAmount;

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

        $this->smartAllocationBreakdown[] = [
            'type' => 'fosa',
            'label' => 'FOSA Savings',
            'reference' => 'FOSA',
            'amount' => round($amountToPost, 2),
        ];
        Log::info("Smart FOSA allocation posted.", [
            'member_id' => $memberId,
            'amount' => $amountToPost,
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

    private function getSmartLoanMonthlyRemainingDue($loan, string $period): float
    {
        $loanId = $loan->loan_id ?? null;

        if (!$loanId) {
            return 0.0;
        }

        /*
     * Smart allocation rule:
     * Do not allocate using full outstanding loan balance.
     *
     * The expected amount is ONLY:
     * sacco_loans.loan_monthly_repayment_amount
     *
     * If missing, NULL, non-numeric or zero, skip this loan.
     */

        if (
            !isset($loan->loan_monthly_repayment_amount)
            || !is_numeric($loan->loan_monthly_repayment_amount)
            || (float) $loan->loan_monthly_repayment_amount <= 0
        ) {
            Log::info("Smart loan allocation skipped: loan_monthly_repayment_amount is missing or zero.", [
                'loan_id' => $loanId,
                'loan_monthly_repayment_amount' => $loan->loan_monthly_repayment_amount ?? null,
                'period' => $period,
            ]);

            return 0.0;
        }

        $expectedMonthlyDue = (float) $loan->loan_monthly_repayment_amount;

        $loanDuration = (int) ($loan->loan_type_duration ?? 0);

        if ($loanDuration <= 1) {
            /*
         * One-month smart allocation:
         * Use rolling one-month window, not YYYYMM.
         */
            $windowStart = Carbon::now()->subMonth()->toDateString();

            $alreadyPaidThisPeriod = (float) DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loanId)
                ->where('loan_payments_paid_on', '>=', $windowStart)
                ->selectRaw('COALESCE(SUM(
                CASE WHEN COALESCE(loan_payments_amount, 0) > 0 THEN loan_payments_amount ELSE 0 END
                + CASE WHEN COALESCE(loan_payments_interest, 0) > 0 THEN loan_payments_interest ELSE 0 END
            ), 0) as paid_total')
                ->value('paid_total');
        } else {
            $alreadyPaidThisPeriod = (float) DB::table('sacco_loan_payments')
                ->where('loan_payments_loan_id', $loanId)
                ->where('loan_payments_period', $period)
                ->selectRaw('COALESCE(SUM(
                COALESCE(loan_payments_amount, 0)
                + COALESCE(loan_payments_interest, 0)
            ), 0) as paid_total')
                ->value('paid_total');
        }

        return max(
            0.0,
            $expectedMonthlyDue - $alreadyPaidThisPeriod
        );
    }

    private function queueMpesaSms(
        ?string $phone,
        string $message,
        string $subject,
        string $requestReference,
        ?int $memberId = null,
        ?string $recipientName = null,
        array $meta = []
    ): void {
        if (!$phone || trim($message) === '') {
            return;
        }

        try {
            app(\App\Services\BulkSms\BulkSmsOutboxService::class)
                ->queue([
                    'member_id' => $memberId,
                    'recipient_name' => $recipientName,
                    'phone' => $phone,
                    'subject' => $subject,
                    'message' => trim($message),
                    'request_reference' => $requestReference,
                    'meta' => $meta,
                ]);
        } catch (\Throwable $e) {
            Log::error('Failed to queue M-PESA SMS.', [
                'phone' => $phone,
                'subject' => $subject,
                'request_reference' => $requestReference,
                'error' => $e->getMessage(),
            ]);
        }
    }
    private function queueMpesaReceiverSms(
        $transaction
    ): void {
        $phone = trim(
            (string) ($transaction->msisdn ?? '')
        );

        if ($phone === '') {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Hashed MSISDN Cannot Receive SMS
    |--------------------------------------------------------------------------
    |
    | Some Safaricom C2B records contain SHA-256 instead of the plaintext
    | payer phone number.
    |
    | Never pass that hash into the SMS outbox as though it were a phone.
    |
    */

        if (preg_match('/^[a-f0-9]{64}$/i', $phone)) {

            Log::info(
                'M-PESA receiver SMS skipped because incoming MSISDN is hashed.',
                [
                    'transaction_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $transaction->transaction_id ?? null,
                ]
            );

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Transaction Details
    |--------------------------------------------------------------------------
    */

        $amount = number_format(
            (float) ($transaction->transaction_amount ?? 0),
            2
        );

        $mpesaRef = trim(
            (string) ($transaction->transaction_id ?? '')
        );

        $payerName = trim(
            (string) ($transaction->first_name ?? '')
        );

        /*
    |--------------------------------------------------------------------------
    | Receiver Acknowledgement
    |--------------------------------------------------------------------------
    |
    | This acknowledges that M-PESA money was received.
    |
    | It does NOT claim that SACCO allocation succeeded.
    |
    */

        $message =
            "KES {$amount} received via M-PESA. Ref {$mpesaRef}. Thank you.";

        $this->queueMpesaSms(
            phone: $phone,
            message: $message,
            subject: 'M-PESA Payment Received',
            requestReference: 'MPESA-RECEIPT-'
                . ($transaction->id ?? $mpesaRef),
            memberId: null,
            recipientName: $payerName !== ''
                ? $payerName
                : 'M-PESA Customer',
            meta: [
                'source' => 'mpesa_payment_received',
                'c2b_payment_id' => $transaction->id ?? null,
                'mpesa_transaction_id' => $mpesaRef,
                'amount' => (float) ($transaction->transaction_amount ?? 0),
                'bill_reference' => $transaction->bill_ref_number ?? null,
            ]
        );
    }

    private function queueMpesaManagerSms(
        $transaction,
        bool $allocated,
        ?object $member = null
    ): void {
        /*
    |--------------------------------------------------------------------------
    | Secondary Management SMS Enabled?
    |--------------------------------------------------------------------------
    */

        $smsEnabled = strtoupper(
            trim(
                (string) DB::table('sacco_defaults')
                    ->where('default_name', 'SEND_SECONDARY_AUTO_ALERTS_SMS')
                    ->value('default_value')
            )
        );

        if ($smsEnabled !== 'Y') {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Management Phone Numbers
    |--------------------------------------------------------------------------
    |
    | Supports:
    |
    | One row:
    | 0722400737,254724275446
    |
    | OR multiple rows:
    | 0722400737
    | 254724275446
    |
    | Every number is normalized to:
    | 254XXXXXXXXX
    |
    */

        $phones = DB::table('sacco_defaults')
            ->where('default_name', 'SACCO_MANAGER_ALERT_PHONE_NUMBERS')
            ->pluck('default_value')
            ->flatMap(function ($value) {
                return explode(',', (string) $value);
            })
            ->map(function ($phone) {
                return trim((string) $phone);
            })
            ->filter(function ($phone) {
                return $phone !== '';
            })
            ->map(function ($phone) {

                /*
             * Reuse existing Kenyan mobile normalizer.
             *
             * Examples:
             *
             * 0722400737
             * -> 254722400737
             *
             * +254 724 275446
             * -> 254724275446
             *
             * 724275446
             * -> 254724275446
             */

                return $this->normalizeKenyanMobileForMpesaHash(
                    $phone
                );
            })
            ->filter(function ($phone) {
                return $phone !== null && $phone !== '';
            })
            ->unique()
            ->values()
            ->all();

        if (empty($phones)) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Transaction Details
    |--------------------------------------------------------------------------
    */

        $amount = number_format(
            (float) ($transaction->transaction_amount ?? 0),
            2
        );

        $mpesaRef = trim(
            (string) ($transaction->transaction_id ?? '')
        );

        $source = trim(
            (string) ($transaction->first_name ?? '')
        );

        $memberAccount = trim(
            (string) ($member->member_sacco_id ?? '')
        );

        /*
    |--------------------------------------------------------------------------
    | Build SMS
    |--------------------------------------------------------------------------
    */

        if ($allocated) {

            $message = "M-PESA KES {$amount} Ref {$mpesaRef}";

            if ($source !== '') {
                $message .= " from {$source}";
            }

            if ($memberAccount !== '') {
                $message .= " allocated to member {$memberAccount}";
            } else {
                $message .= " successfully allocated";
            }

            $message .= ". See SACCO system.";

            $subject = 'M-PESA Auto Allocation Successful';
            $status = 'allocated';
        } else {

            $message = "ALERT: M-PESA KES {$amount} Ref {$mpesaRef}";

            if ($source !== '') {
                $message .= " from {$source}";
            }

            if ($memberAccount !== '') {
                $message .= " for member {$memberAccount}";
            }

            $message .= " could not be auto allocated. Held for review.";

            $subject = 'M-PESA Auto Allocation Failed';
            $status = 'failed';
        }

        /*
    |--------------------------------------------------------------------------
    | Queue One SMS Per Manager
    |--------------------------------------------------------------------------
    */

        foreach ($phones as $index => $phone) {

            $this->queueMpesaSms(
                phone: $phone,
                message: $message,
                subject: $subject,
                requestReference: 'MPESA-MGR-'
                    . strtoupper($status)
                    . '-'
                    . ($transaction->id ?? $mpesaRef)
                    . '-'
                    . ($index + 1),
                memberId: isset($member->member_id)
                    ? (int) $member->member_id
                    : null,
                recipientName: 'SACCO Manager',
                meta: [
                    'source' => 'mpesa_management_alert',
                    'status' => $status,
                    'c2b_payment_id' => $transaction->id ?? null,
                    'mpesa_transaction_id' => $mpesaRef,
                    'amount' => (float) ($transaction->transaction_amount ?? 0),
                    'member_id' => $member->member_id ?? null,
                    'member_account' => $memberAccount ?: null,
                    'bill_reference' => $transaction->bill_ref_number ?? null,
                ]
            );
        }
    }

    private function queueMpesaEmail(
        ?string $email,
        string $message,
        string $subject,
        string $relatedDoc,
        ?int $memberId = null,
        ?string $recipientName = null,
        array $meta = []
    ): void {
        $email = trim((string) $email);
        $message = trim($message);

        if (
            $email === ''
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $message === ''
        ) {
            return;
        }

        try {
            DB::table('sacco_system_notifications')->insert([
                'notif_recipient_name' => $recipientName,
                'notif_recipient_email' => $email,
                'notif_recipient_phone' => null,

                'notif_subject' => $subject,
                'notif_message' => $message,

                'notif_status' => 'unread',

                'notif_member_id' => $memberId,
                'notif_related_doc' => $relatedDoc,

                'notif_type' => 'system',

                'notif_created_by' => auth()->id() ?? 999,
                'notif_ip' => request()->ip() ?? '127.0.0.1',

                'notif_meta' => !empty($meta)
                    ? json_encode(
                        $meta,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    )
                    : null,

                'notif_created_at' => now('Africa/Nairobi'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to queue M-PESA email notification.', [
                'email' => $email,
                'subject' => $subject,
                'related_doc' => $relatedDoc,
                'error' => $e->getMessage(),
            ]);
        }
    }
    private function queueMpesaManagerEmail(
    $transaction,
    bool $allocated,
    ?object $member = null,
    ?string $failureReason = null
): void {
    /*
    |--------------------------------------------------------------------------
    | Management Email Addresses
    |--------------------------------------------------------------------------
    |
    | Supports:
    |
    | - Multiple sacco_defaults rows
    | - Multiple comma-separated emails inside any row
    |
    | Each valid email is queued separately.
    |
    */

    $emails = DB::table('sacco_defaults')
        ->where('default_name', 'SACCO_MANAGER_ALERT_EMAILS')
        ->pluck('default_value')
        ->flatMap(function ($value) {
            return explode(',', (string) $value);
        })
        ->map(function ($email) {
            return trim((string) $email);
        })
        ->filter(function ($email) {
            return (
                $email !== ''
                && filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            );
        })
        ->unique()
        ->values()
        ->all();

    if (empty($emails)) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Details
    |--------------------------------------------------------------------------
    */

    $amount = (float) (
        $transaction->transaction_amount ?? 0
    );

    $mpesaRef = trim(
        (string) (
            $transaction->transaction_id ?? ''
        )
    );

    $billReference = trim(
        (string) (
            $transaction->bill_ref_number ?? ''
        )
    );

    $source = trim(
        (string) (
            $transaction->first_name ?? ''
        )
    );

    $memberName = trim(
        (string) (
            $member->member_name ?? ''
        )
    );

    $memberAccount = trim(
        (string) (
            $member->member_sacco_id ?? ''
        )
    );

    $relatedDoc = 'MPESA-' . (
        $mpesaRef !== ''
            ? $mpesaRef
            : ($transaction->id ?? 'UNKNOWN')
    );

    /*
    |--------------------------------------------------------------------------
    | Successful Allocation
    |--------------------------------------------------------------------------
    */

    if ($allocated) {

        $subject = 'M-PESA Payment Successfully Allocated';

        $message =
            "An M-PESA payment of KES "
            . number_format($amount, 2)
            . " has been received and successfully allocated.\n\n";

        $message .=
            "M-PESA reference: {$mpesaRef}\n";

        if ($billReference !== '') {
            $message .=
                "Payment reference: {$billReference}\n";
        }

        if ($source !== '') {
            $message .=
                "Received from: {$source}\n";
        }

        if ($memberName !== '') {
            $message .=
                "Member: {$memberName}\n";
        }

        if ($memberAccount !== '') {
            $message .=
                "Member account: {$memberAccount}\n";
        }

        /*
        |--------------------------------------------------------------------------
        | Allocation
        |--------------------------------------------------------------------------
        */

        if (!empty($this->smartAllocationBreakdown)) {

            $message .= "\nAllocation:\n";

            $totalAllocated = 0.0;

            foreach (
                $this->smartAllocationBreakdown
                as $allocation
            ) {

                $label = trim(
                    (string) (
                        $allocation['label']
                        ?? 'Allocation'
                    )
                );

                $allocationAmount = (float) (
                    $allocation['amount']
                    ?? 0
                );

                $totalAllocated +=
                    $allocationAmount;

                $message .=
                    "• {$label} — KES "
                    . number_format(
                        $allocationAmount,
                        2
                    )
                    . "\n";
            }

            $unallocatedBalance = max(
                0,
                round(
                    $amount - $totalAllocated,
                    2
                )
            );

            $message .=
                "Total allocated: KES "
                . number_format(
                    $totalAllocated,
                    2
                )
                . "\n";

            $message .=
                "Unallocated balance: KES "
                . number_format(
                    $unallocatedBalance,
                    2
                )
                . "\n";
        }

        $message .=
            "\nView the SACCO system for full transaction details.";

        $status = 'allocated';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Failed Allocation
        |--------------------------------------------------------------------------
        */

        $subject = 'M-PESA Payment Requires Review';

        $message =
            "An M-PESA payment of KES "
            . number_format($amount, 2)
            . " has been received but could not be automatically allocated. "
            . "The payment is being held for review.\n\n";

        $message .=
            "M-PESA reference: {$mpesaRef}\n";

        if ($billReference !== '') {
            $message .=
                "Payment reference: {$billReference}\n";
        }

        if ($source !== '') {
            $message .=
                "Received from: {$source}\n";
        }

        if ($memberName !== '') {
            $message .=
                "Member: {$memberName}\n";
        }

        if ($memberAccount !== '') {
            $message .=
                "Member account: {$memberAccount}\n";
        }

        if (
            $failureReason !== null
            && trim($failureReason) !== ''
        ) {
            $message .=
                "Reason: "
                . trim($failureReason)
                . "\n";
        }

        $message .=
            "\nNo automatic reassignment has been made. "
            . "Please review the transaction in the SACCO system.";

        $status = 'failed';
    }

    /*
    |--------------------------------------------------------------------------
    | Queue One Email Per Manager
    |--------------------------------------------------------------------------
    */

    foreach ($emails as $email) {

        $this->queueMpesaEmail(
            email: $email,
            message: $message,
            subject: $subject,
            relatedDoc: $relatedDoc,
            memberId: isset($member->member_id)
                ? (int) $member->member_id
                : null,

            /*
             * The main email template uses this column to generate:
             *
             * Dear SACCO Manager,
             *
             * Therefore the message itself MUST NOT contain another greeting.
             */
            recipientName: 'SACCO Manager',

            meta: [
                'source' =>
                    'mpesa_management_alert',

                'status' =>
                    $status,

                'c2b_payment_id' =>
                    $transaction->id ?? null,

                'mpesa_transaction_id' =>
                    $mpesaRef,

                'amount' =>
                    $amount,

                'bill_reference' =>
                    $billReference ?: null,

                'source_name' =>
                    $source ?: null,

                'member_id' =>
                    $member->member_id ?? null,

                'member_name' =>
                    $memberName ?: null,

                'member_account' =>
                    $memberAccount ?: null,

                'allocation_breakdown' =>
                    $allocated
                        ? $this->smartAllocationBreakdown
                        : [],

                'failure_reason' =>
                    $failureReason,
            ]
        );
    }
}
}
