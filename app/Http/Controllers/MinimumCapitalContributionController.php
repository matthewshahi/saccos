<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class MinimumCapitalContributionController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);
        $postingDate = $this->resolvePostingDate($request);
        $setupError = null;
        $defaults = null;
        $rows = collect();

        $summary = [
            'minimum_capital' => 0,
            'share_account' => null,
            'share_account_name' => null,
            'capital_account' => null,
            'capital_account_name' => null,
            'eligible_members' => 0,
            'total_current_shares' => 0,
            'total_current_capital' => 0,
            'total_missing_capital' => 0,
            'total_transferable' => 0,
            'members_fully_fixed' => 0,
            'members_partially_fixed' => 0,
        ];

        try {
            $defaults = $this->getCapitalDefaults($request);
            $rows = $this->getPreviewRows($defaults['min_capital_contribution']);

            $summary = [
                'minimum_capital' => $defaults['min_capital_contribution'],
                'share_account' => $defaults['default_share_account'],
                'share_account_name' => $defaults['default_share_account_name'],
                'capital_account' => $defaults['default_share_capital_account'],
                'capital_account_name' => $defaults['default_share_capital_account_name'],
                'eligible_members' => $rows->count(),
                'total_current_shares' => round((float) $rows->sum('current_share_balance'), 2),
                'total_current_capital' => round((float) $rows->sum('current_capital_balance'), 2),
                'total_missing_capital' => round((float) $rows->sum('missing_capital'), 2),
                'total_transferable' => round((float) $rows->sum('transfer_amount'), 2),
                'members_fully_fixed' => $rows->filter(function ($row) {
                    return (float) $row->transfer_amount >= (float) $row->missing_capital;
                })->count(),
                'members_partially_fixed' => $rows->filter(function ($row) {
                    return (float) $row->transfer_amount > 0
                        && (float) $row->transfer_amount < (float) $row->missing_capital;
                })->count(),
            ];
        } catch (Throwable $e) {
            $setupError = $e->getMessage();
        }

        return view('capital.minimum.index', compact(
            'rows',
            'summary',
            'period',
            'postingDate',
            'defaults',
            'setupError'
        ));
    }

    public function process(Request $request)
    {
        $period = $this->resolvePeriod($request);
        $postingDate = $this->resolvePostingDate($request);
        $userId = Auth::id() ?: 1;
        $ip = $request->ip();

        try {
            $defaults = $this->getCapitalDefaults($request);
        } catch (Throwable $e) {
            return redirect()
                ->route('minimum_capital.index', [
                    'period' => $period,
                    'posting_date' => substr($postingDate, 0, 10),
                ])
                ->with('error', $e->getMessage());
        }

        $batchNo = 'MINCAP-' . now()->format('Ymd-His');
        $processed = 0;
        $skipped = 0;
        $totalTransferred = 0.00;

        try {
            DB::transaction(function () use (
                $defaults,
                $period,
                $postingDate,
                $userId,
                $ip,
                $batchNo,
                &$processed,
                &$skipped,
                &$totalTransferred
            ) {
                $members = DB::table('sacco_members')
                    ->where('member_active', 'Y')
                    ->where('member_deleted', 'N')
                    ->whereRaw('COALESCE(member_total_share_capital, 0) < ?', [
                        $defaults['min_capital_contribution']
                    ])
                    ->whereRaw('COALESCE(member_total_share, 0) > 0')
                    ->orderBy('member_id')
                    ->lockForUpdate()
                    ->get([
                        'member_id',
                        'member_name',
                        'member_sacco_id',
                        'member_national_id',
                        'member_total_share',
                        'member_total_share_capital',
                    ]);

                foreach ($members as $member) {
                    $currentShares = max(0, round((float) $member->member_total_share, 2));
                    $currentCapital = max(0, round((float) $member->member_total_share_capital, 2));

                    $missingCapital = max(
                        0,
                        round($defaults['min_capital_contribution'] - $currentCapital, 2)
                    );

                    $transferAmount = round(min($missingCapital, $currentShares), 2);

                    if ($missingCapital <= 0 || $currentShares <= 0 || $transferAmount <= 0) {
                        $skipped++;
                        continue;
                    }

                    $docNo = $batchNo . '-' . $member->member_id;
                    $memberLabel = trim(($member->member_name ?? '') . ' / ' . ($member->member_sacco_id ?? ''));

                    DB::table('sacco_shares')->insert([
                        'share_member_id' => $member->member_id,
                        'share_amount_paying' => -1 * $transferAmount,
                        'share_paid_by' => 'INTERNAL TRANSFER',
                        'share_period' => $period,
                        'share_description' => 'Minimum capital transfer to share capital',
                        'share_doc_no' => $docNo,
                        'share_date_paid' => $postingDate,
                        'share_by' => $userId,
                        'share_ip' => $ip,
                        'share_end_month_proc' => 'N',
                    ]);

                    DB::table('sacco_capital_shares')->insert([
                        'share_capitalmember_id' => $member->member_id,
                        'share_capitalamount_paying' => $transferAmount,
                        'share_capitalpaid_by' => 'INTERNAL TRANSFER',
                        'share_capitalperiod' => $period,
                        'share_capitaldescription' => 'Minimum capital transfer from shares',
                        'share_capitaldoc_no' => $docNo,
                        'share_capitaldate_paid' => $postingDate,
                        'share_capitalby' => $userId,
                        'share_capitalip' => $ip,
                        'share_capitalend_month_proc' => 'N',
                    ]);

                    DB::table('sacco_members')
                        ->where('member_id', $member->member_id)
                        ->update([
                            'member_total_share' => round(max(0, $currentShares - $transferAmount), 2),
                            'member_total_share_capital' => round($currentCapital + $transferAmount, 2),
                        ]);

                    DB::table('sacco_accounts_trans')->insert([
                        [
                            'accounts_trans_sub_account' => $defaults['default_share_account'],
                            'accounts_trans_period' => $period,
                            'accounts_trans_debit' => $transferAmount,
                            'accounts_trans_credit' => 0,
                            'accounts_trans_doc_no' => $docNo,
                            'accounts_trans_decription' => 'Minimum capital transfer from member shares: ' . $memberLabel,
                            'accounts_trans_source' => 'MINIMUM_CAPITAL_TRANSFER',
                            'accounts_trans_dat_date' => $postingDate,
                            'accounts_trans_user_id' => $userId,
                            'accounts_trans_ip' => $ip,
                            'accounts_trans_member_id' => $member->member_id,
                            'accounts_trans_app_name' => 'SACCO',
                            'accounts_trans_cash_in_already' => 'N',
                            'accounts_trans_reconsiled' => 'N',
                            'accounts_trans_payment_type' => 'INTERNAL TRANSFER',
                        ],
                        [
                            'accounts_trans_sub_account' => $defaults['default_share_capital_account'],
                            'accounts_trans_period' => $period,
                            'accounts_trans_debit' => 0,
                            'accounts_trans_credit' => $transferAmount,
                            'accounts_trans_doc_no' => $docNo,
                            'accounts_trans_decription' => 'Minimum capital transfer to share capital: ' . $memberLabel,
                            'accounts_trans_source' => 'MINIMUM_CAPITAL_TRANSFER',
                            'accounts_trans_dat_date' => $postingDate,
                            'accounts_trans_user_id' => $userId,
                            'accounts_trans_ip' => $ip,
                            'accounts_trans_member_id' => $member->member_id,
                            'accounts_trans_app_name' => 'SACCO',
                            'accounts_trans_cash_in_already' => 'N',
                            'accounts_trans_reconsiled' => 'N',
                            'accounts_trans_payment_type' => 'INTERNAL TRANSFER',
                        ],
                    ]);

                    $processed++;
                    $totalTransferred = round($totalTransferred + $transferAmount, 2);
                }
            }, 3);

            return redirect()
                ->route('minimum_capital.index', [
                    'period' => $period,
                    'posting_date' => substr($postingDate, 0, 10),
                ])
                ->with(
                    'success',
                    'Transfer completed. Members processed: '
                    . $processed
                    . '. Members skipped: '
                    . $skipped
                    . '. Total transferred: KES '
                    . number_format($totalTransferred, 2)
                    . '.'
                );
        } catch (Throwable $e) {
            Log::error('Minimum capital contribution transfer failed', [
                'message' => $e->getMessage(),
                'period' => $period,
                'posting_date' => $postingDate,
                'user_id' => $userId,
                'ip' => $ip,
            ]);

            return redirect()
                ->route('minimum_capital.index', [
                    'period' => $period,
                    'posting_date' => substr($postingDate, 0, 10),
                ])
                ->with('error', 'Transfer failed: ' . $e->getMessage());
        }
    }

    private function getPreviewRows(float $minimumCapital)
    {
        return DB::table('sacco_members')
            ->where('member_active', 'Y')
            ->where('member_deleted', 'N')
            ->whereRaw('COALESCE(member_total_share_capital, 0) < ?', [$minimumCapital])
            ->whereRaw('COALESCE(member_total_share, 0) > 0')
            ->select([
                'member_id',
                'member_name',
                'member_sacco_id',
                'member_national_id',
                'member_active',
                'member_deleted',
            ])
            ->selectRaw('? AS required_capital', [$minimumCapital])
            ->selectRaw('ROUND(COALESCE(member_total_share, 0), 2) AS current_share_balance')
            ->selectRaw('ROUND(COALESCE(member_total_share_capital, 0), 2) AS current_capital_balance')
            ->selectRaw(
                'ROUND(GREATEST(0, ? - COALESCE(member_total_share_capital, 0)), 2) AS missing_capital',
                [$minimumCapital]
            )
            ->selectRaw(
                'ROUND(LEAST(GREATEST(0, ? - COALESCE(member_total_share_capital, 0)), GREATEST(0, COALESCE(member_total_share, 0))), 2) AS transfer_amount',
                [$minimumCapital]
            )
            ->selectRaw(
                'ROUND(GREATEST(0, COALESCE(member_total_share, 0) - LEAST(GREATEST(0, ? - COALESCE(member_total_share_capital, 0)), GREATEST(0, COALESCE(member_total_share, 0)))), 2) AS share_balance_after',
                [$minimumCapital]
            )
            ->selectRaw(
                'ROUND(COALESCE(member_total_share_capital, 0) + LEAST(GREATEST(0, ? - COALESCE(member_total_share_capital, 0)), GREATEST(0, COALESCE(member_total_share, 0))), 2) AS capital_balance_after',
                [$minimumCapital]
            )
            ->orderBy('member_name')
            ->get();
    }

    private function getCapitalDefaults(Request $request): array
    {
        $minimumCapital = $this->ensureMinimumCapitalDefault($request);

        $shareAccountValue = $this->getDefaultValue('default_share_account');
        $capitalAccountValue = $this->getDefaultValue('default_share_capital_account');

        if ($shareAccountValue === null || trim((string) $shareAccountValue) === '' || (int) $shareAccountValue <= 0) {
            throw new RuntimeException('The member shares ledger is not configured. Create the correct member shares sub account, then link it under default_share_account in defaults.');
        }

        if ($capitalAccountValue === null || trim((string) $capitalAccountValue) === '' || (int) $capitalAccountValue <= 0) {
            throw new RuntimeException('The share capital ledger is not configured. Create the correct share capital sub account, then link it under default_share_capital_account in defaults.');
        }

        $shareAccount = (int) $shareAccountValue;
        $capitalAccount = (int) $capitalAccountValue;

        $shareAccountName = $this->getSubAccountName($shareAccount);
        $capitalAccountName = $this->getSubAccountName($capitalAccount);

        if (!$shareAccountName) {
            throw new RuntimeException('The member shares ledger account ID ' . $shareAccount . ' was not found. Create the account and link it correctly under default_share_account.');
        }

        if (!$capitalAccountName) {
            throw new RuntimeException('The share capital ledger account ID ' . $capitalAccount . ' was not found. Create the account and link it correctly under default_share_capital_account.');
        }

        return [
            'min_capital_contribution' => $minimumCapital,
            'default_share_account' => $shareAccount,
            'default_share_account_name' => $shareAccountName,
            'default_share_capital_account' => $capitalAccount,
            'default_share_capital_account_name' => $capitalAccountName,
        ];
    }

    private function ensureMinimumCapitalDefault(Request $request): float
    {
        $row = DB::table('sacco_defaults')
            ->where('default_name', 'min_capital_contribution')
            ->first();

        $userId = Auth::id() ?: 1;
        $ip = $request->ip();

        if (!$row) {
            DB::table('sacco_defaults')->insert([
                'default_name' => 'min_capital_contribution',
                'default_value' => '5000',
                'default_userid' => $userId,
                'default_ip' => $ip,
            ]);

            return 5000.00;
        }

        $value = round((float) $row->default_value, 2);

        if ($row->default_value === null || trim((string) $row->default_value) === '' || $value <= 0) {
            DB::table('sacco_defaults')
                ->where('default_id', $row->default_id)
                ->update([
                    'default_value' => '5000',
                    'default_userid' => $userId,
                    'default_ip' => $ip,
                ]);

            return 5000.00;
        }

        return $value;
    }

    private function getDefaultValue(string $name)
    {
        return DB::table('sacco_defaults')
            ->where('default_name', $name)
            ->value('default_value');
    }

    private function getSubAccountName(int $accountId): ?string
    {
        $info = $this->getSubAccountTableInfo();

        if (!$info) {
            throw new RuntimeException('The sub account table was not found. Create the correct ledger accounts and link default_share_account and default_share_capital_account.');
        }

        $row = DB::table($info['table'])
            ->where($info['id_column'], $accountId)
            ->first();

        if (!$row) {
            return null;
        }

        $nameColumn = $info['name_column'];

        return trim((string) ($row->$nameColumn ?? '')) ?: ('Account ID ' . $accountId);
    }

    private function getSubAccountTableInfo(): ?array
    {
        $tables = ['sacco_sub_account', 'sacco_sub_accounts'];
        $idColumns = ['sub_account_id', 'sub_id', 'account_id'];
        $nameColumns = ['sub_account_name', 'sub_name', 'account_name', 'name'];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $idColumn = null;
            $nameColumn = null;

            foreach ($idColumns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $idColumn = $column;
                    break;
                }
            }

            foreach ($nameColumns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $nameColumn = $column;
                    break;
                }
            }

            if ($idColumn && $nameColumn) {
                return [
                    'table' => $table,
                    'id_column' => $idColumn,
                    'name_column' => $nameColumn,
                ];
            }
        }

        return null;
    }

    private function resolvePeriod(Request $request): string
    {
        $period = trim((string) $request->input('period', now()->format('Ym')));

        if (!preg_match('/^\d{6}$/', $period)) {
            throw new RuntimeException('Invalid period. Use YYYYMM format, for example 202604.');
        }

        return $period;
    }

    private function resolvePostingDate(Request $request): string
    {
        $postingDate = trim((string) $request->input('posting_date', now()->format('Y-m-d')));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postingDate)) {
            throw new RuntimeException('Invalid posting date. Use YYYY-MM-DD format.');
        }

        return $postingDate . ' ' . now()->format('H:i:s');
    }
}