<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\LedgerExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportLedgerController extends Controller
{
    public function reportsAccountsLedger(Request $request)
    {
        $startPeriod = $request->input('start_period', '000000');
        $endPeriod   = $request->input('end_period', '999999');

        $startDate = \Carbon\Carbon::parse(
            $request->input('start_date', now()->startOfMonth())
        )->startOfDay();

        $endDate = \Carbon\Carbon::parse(
            $request->input('end_date', now())
        )->endOfDay();

        $search         = $request->input('search', '');
        $orderField     = $request->input('order_field', 'accounts_trans_period');
        $orderDirection = $request->input('order_direction', 'asc');

        // Base query (shared)
        $baseQuery = $this->buildLedgerBaseQuery($startPeriod, $endPeriod, $startDate, $endDate, $search);

        // Totals (clone so we don't mutate the base query)
        $totals = (clone $baseQuery)
            ->selectRaw('
                SUM(sacco_accounts_trans.accounts_trans_debit)  as total_debit,
                SUM(sacco_accounts_trans.accounts_trans_credit) as total_credit
            ')
            ->first();

        // Opening balance (everything before start period + start date, same search)
        $openingBalanceQuery = $this->buildOpeningBalanceQuery($startPeriod, $startDate, $search);

        $openingBalanceData = $openingBalanceQuery
            ->selectRaw('
                SUM(sacco_accounts_trans.accounts_trans_debit)  as total_debit,
                SUM(sacco_accounts_trans.accounts_trans_credit) as total_credit
            ')
            ->first();

        $openingBalanceDebit  = $openingBalanceData->total_debit ?? 0;
        $openingBalanceCredit = $openingBalanceData->total_credit ?? 0;

        $openingBalance = (object) [
            'balance' => abs($openingBalanceDebit - $openingBalanceCredit),
            'type'    => $openingBalanceDebit >= $openingBalanceCredit ? 'Debit' : 'Credit',
        ];

        // Paginated transactions for screen
        $transactionsQuery = (clone $baseQuery)
            ->select(
                'sacco_accounts_trans.*',
                'sacco_sub_account.sub_account_code',
                'sacco_sub_account.sub_account_name',
                'sacco_main_account.main_account_code'
            )
            ->orderBy($orderField, $orderDirection);

        $transactions = $transactionsQuery->paginate(2000)->withQueryString();

        // Active period
        $activePeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->value('period_name');

        return view('reports.accounts.accounts_ledger', compact(
            'transactions',
            'activePeriod',
            'totals',
            'openingBalance'
        ));
    }

    public function updateTransaction(Request $request, $id)
    {
        $request->validate([
            'accounts_trans_cash_in_already'       => 'nullable|string|in:Y,N',
            'accounts_trans_reconsiled'            => 'nullable|string|in:Y,N',
            'accounts_trans_payment_type'          => 'nullable|string|max:255',
            'accounts_trans_reconsiled_comments'   => 'nullable|string|max:1000',
        ]);

        $transaction = DB::table('sacco_accounts_trans')->where('accounts_trans_id', $id)->first();

        if (!$transaction) {
            return redirect()->back()->withErrors(['error' => 'Transaction not found.']);
        }

        $period = DB::table('sacco_period')
            ->where('period_name', $transaction->accounts_trans_period)
            ->where('period_active', 'Y')
            ->first();

        if (!$period) {
            return redirect()->back()->withErrors(['error' => 'This transaction cannot be updated because the period is closed.']);
        }

        DB::table('sacco_accounts_trans')->where('accounts_trans_id', $id)->update([
            'accounts_trans_cash_in_already'      => $request->input('accounts_trans_cash_in_already'),
            'accounts_trans_reconsiled'           => $request->input('accounts_trans_reconsiled'),
            'accounts_trans_payment_type'         => $request->input('accounts_trans_payment_type'),
            'accounts_trans_reconsiled_comments'  => $request->input('accounts_trans_reconsiled_comments'),
            'accounts_trans_reconsiled_transdate' => now(),
        ]);

        // IMPORTANT: preserve start_date/end_date too (you added date filters)
        return redirect()->route('reports.accounts.ledger', [
            'start_period'     => $request->input('start_period', '000000'),
            'end_period'       => $request->input('end_period', '999999'),
            'start_date'       => $request->input('start_date'),
            'end_date'         => $request->input('end_date'),
            'search'           => $request->input('search', ''),
            'order_field'      => $request->input('order_field', 'accounts_trans_period'),
            'order_direction'  => $request->input('order_direction', 'asc'),
        ])->with('success', 'Transaction updated successfully.');
    }

    /**
     * ✅ Export full dataset (NOT paginated)
     */
    public function export(Request $request)
    {
        $startPeriod = $request->input('start_period', '000000');
        $endPeriod   = $request->input('end_period', '999999');

        $startDate = \Carbon\Carbon::parse(
            $request->input('start_date', now()->startOfMonth())
        )->startOfDay();

        $endDate = \Carbon\Carbon::parse(
            $request->input('end_date', now())
        )->endOfDay();

        $search         = $request->input('search', '');
        $orderField     = $request->input('order_field', 'accounts_trans_period');
        $orderDirection = $request->input('order_direction', 'asc');

        $query = $this->buildLedgerBaseQuery($startPeriod, $endPeriod, $startDate, $endDate, $search)
            ->select(
                'sacco_accounts_trans.*',
                'sacco_sub_account.sub_account_code',
                'sacco_sub_account.sub_account_name',
                'sacco_main_account.main_account_code'
            )
            ->orderBy($orderField, $orderDirection);

        return Excel::download(new LedgerExport($query), 'accounts_ledger.xlsx');
    }

    /**
     * Shared ledger query (period + date + search)
     */
    private function buildLedgerBaseQuery($startPeriod, $endPeriod, $startDate, $endDate, $search)
    {
        $q = DB::table('sacco_accounts_trans')
            ->leftJoin('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->whereBetween('sacco_accounts_trans.accounts_trans_period', [$startPeriod, $endPeriod])
            ->whereBetween('sacco_accounts_trans.accounts_trans_dat_date', [$startDate, $endDate]);

        if (!empty($search)) {
            $q->where(function ($w) use ($search) {
                $w->where('sacco_sub_account.sub_account_name', 'LIKE', "%$search%")
                    ->orWhere('sacco_main_account.main_account_code', 'LIKE', "%$search%")
                    ->orWhere('sacco_sub_account.sub_account_code', 'LIKE', "%$search%")
                    ->orWhere(DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code)"), 'LIKE', "%$search%")
                    ->orWhere('sacco_accounts_trans.accounts_trans_doc_no', 'LIKE', "%$search%")
                    ->orWhere('sacco_accounts_trans.accounts_trans_decription', 'LIKE', "%$search%");
            });
        }

        return $q;
    }

    /**
     * Opening balance query (before start period AND before start date) + same search
     */
    private function buildOpeningBalanceQuery($startPeriod, $startDate, $search)
    {
        $q = DB::table('sacco_accounts_trans')
            ->leftJoin('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_accounts_trans.accounts_trans_period', '<', $startPeriod)
            ->where('sacco_accounts_trans.accounts_trans_dat_date', '<', $startDate);

        if (!empty($search)) {
            $q->where(function ($w) use ($search) {
                $w->where('sacco_sub_account.sub_account_name', 'LIKE', "%$search%")
                    ->orWhere('sacco_main_account.main_account_code', 'LIKE', "%$search%")
                    ->orWhere('sacco_sub_account.sub_account_code', 'LIKE', "%$search%")
                    ->orWhere(DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code)"), 'LIKE', "%$search%")
                    ->orWhere('sacco_accounts_trans.accounts_trans_doc_no', 'LIKE', "%$search%")
                    ->orWhere('sacco_accounts_trans.accounts_trans_decription', 'LIKE', "%$search%");
            });
        }

        return $q;
    }
}
