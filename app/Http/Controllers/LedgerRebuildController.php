<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerRebuildController extends Controller
{
    /**
     * Full ledger rebuild with strict account verification.
     * Stops immediately if any required default account is missing or null.
     */
    public function rebuild(Request $request)
    {
        $userId = auth()->id() ?? 999;
        $ip = $request->ip();

        try {
            Log::info("Ledger rebuild initiated by user {$userId}");

            // ✅ Step 1: Pre-check all required default accounts
         

            $requiredDefaults = [
    'default_mpesa_in_account',   // ✅ renamed
    'default_bank_account',
    'default_share_account',
    'default_loan_account',
    'default_share_capital_account',
    'default_fosa_account',
];

            $missing = [];
            foreach ($requiredDefaults as $key) {
                $val = DB::table('sacco_defaults')
                    ->where('default_name', $key)
                    ->value('default_value');
                if (empty($val) || $val == 0) {
                    $missing[] = $key;
                }
            }

            if (count($missing) > 0) {
                $msg = "❌ Ledger rebuild aborted. Missing or unset default accounts: " . implode(', ', $missing);
                Log::error($msg);
                return response()->json(['error' => $msg], 400);
            }

            // ✅ Step 2: Truncate sacco_accounts_trans
            DB::statement('TRUNCATE TABLE sacco_accounts_trans');
            Log::info("Truncated sacco_accounts_trans");

            // ✅ Step 3: Reset sub & main account balances
            DB::table('sacco_sub_account')->update([
                'sub_account_debit' => 0,
                'sub_account_credit' => 0,
            ]);
            DB::table('sacco_main_account')->update([
                'main_account_debit' => 0,
                'main_account_credit' => 0,
            ]);
            Log::info("Reset sub_account and main_account balances to zero");

            // ✅ Step 4: Begin rebuild
            $mappings = [
                [
                    'table' => 'sacco_shares',
                    'amount_col' => 'share_amount_paying',
                    'doc_col' => 'share_doc_no',
                    'desc_col' => 'share_description',
                    'member_col' => 'share_member_id',
                    'period_col' => 'share_period',
                    'date_col' => 'share_date_paid',
                    'target_account' => 'default_share_account',
                ],
                [
                    'table' => 'sacco_fosas',
                    'amount_col' => 'fosa_amount_paying',
                    'doc_col' => 'fosa_doc_no',
                    'desc_col' => 'fosa_description',
                    'member_col' => 'fosa_member_id',
                    'period_col' => 'fosa_period',
                    'date_col' => 'fosa_date_paid',
                    'target_account' => 'default_fosa_account',
                ],
                [
                    'table' => 'sacco_loan_payments',
                    'amount_col' => 'loan_payments_amount',
                    'doc_col' => 'loan_payments_docno',
                    'desc_col' => 'loan_payments_description',
                    'member_col' => 'loan_payments_paid_in_by',
                    'period_col' => 'loan_payments_period',
                    'date_col' => 'loan_payments_paid_on',
                    'target_account' => 'default_loan_account',
                ],
                [
                    'table' => 'sacco_capital_shares',
                    'amount_col' => 'share_capitalamount_paying',
                    'doc_col' => 'share_capitaldoc_no',
                    'desc_col' => 'share_capitaldescription',
                    'member_col' => 'share_capitalmember_id',
                    'period_col' => 'share_capitalperiod',
                    'date_col' => 'share_capitaldate_paid',
                    'target_account' => 'default_share_capital_account',
                ],
            ];

            $inserted = 0;

            foreach ($mappings as $map) {
                $records = DB::table($map['table'])
                    ->whereNotNull($map['doc_col'])
                    ->get();

                foreach ($records as $rec) {
                    $docNo = $rec->{$map['doc_col']};
                    $amount = (float) $rec->{$map['amount_col']};
                    $desc = $rec->{$map['desc_col']} ?? 'Ledger entry';
                    $period = $rec->{$map['period_col']} ?? date('Ym');
                    $memberId = $rec->{$map['member_col']} ?? null;
                    $transDate = $rec->{$map['date_col']} ?? now();

                    if ($amount <= 0 || !$docNo) continue;

                    // Determine source account (M-Pesa or Bank)
                    $isMpesa = stripos($docNo, 'Paybill') !== false;
                    $debitKey = $isMpesa ? 'default_mpesa_account' : 'default_bank_account';
                    $creditKey = $map['target_account'];

                    // Fetch mapped sub accounts
                    $debitAcc = DB::table('sacco_defaults')->where('default_name', $debitKey)->value('default_value');
                    $creditAcc = DB::table('sacco_defaults')->where('default_name', $creditKey)->value('default_value');

                    // Double safety: Skip if missing mapping
                    if (empty($debitAcc) || empty($creditAcc)) {
                        Log::warning("SKIPPED {$docNo}: missing sub account ({$debitKey}={$debitAcc}, {$creditKey}={$creditAcc})");
                        continue;
                    }

                    // Insert debit & credit entries
                    DB::table('sacco_accounts_trans')->insert([
                        [
                            'accounts_trans_sub_account' => $debitAcc,
                            'accounts_trans_period' => $period,
                            'accounts_trans_debit' => $amount,
                            'accounts_trans_credit' => 0,
                            'accounts_trans_doc_no' => $docNo,
                            'accounts_trans_decription' => $desc,
                            'accounts_trans_source' => $map['table'],
                            'accounts_trans_dat_date' => $transDate,
                            'accounts_trans_transdate' => now(),
                            'accounts_trans_user_id' => $userId,
                            'accounts_trans_ip' => $ip,
                            'accounts_trans_member_id' => $memberId,
                            'accounts_trans_app_name' => 'LedgerSync',
                            'accounts_trans_payment_type' => $isMpesa ? 'MPESA' : 'BANK',
                        ],
                        [
                            'accounts_trans_sub_account' => $creditAcc,
                            'accounts_trans_period' => $period,
                            'accounts_trans_debit' => 0,
                            'accounts_trans_credit' => $amount,
                            'accounts_trans_doc_no' => $docNo,
                            'accounts_trans_decription' => $desc,
                            'accounts_trans_source' => $map['table'],
                            'accounts_trans_dat_date' => $transDate,
                            'accounts_trans_transdate' => now(),
                            'accounts_trans_user_id' => $userId,
                            'accounts_trans_ip' => $ip,
                            'accounts_trans_member_id' => $memberId,
                            'accounts_trans_app_name' => 'LedgerSync',
                            'accounts_trans_payment_type' => $isMpesa ? 'MPESA' : 'BANK',
                        ]
                    ]);

                    $inserted++;
                }
            }

            Log::info("✅ Ledger rebuild completed successfully — {$inserted} entries posted.");
            return response()->json([
                'message' => "Ledger rebuild completed successfully.",
                'entries_inserted' => $inserted
            ]);

        } catch (\Exception $e) {
            Log::error('Ledger rebuild failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}