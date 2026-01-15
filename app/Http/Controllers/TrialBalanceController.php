<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrialBalanceController extends Controller
{
    /**
     * Main Trial Balance view
     */
    public function index(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        $records = $this->getAccountsData(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        return view('reports.accounts.trial_balance', [
            'records'  => $records,
            'period'   => $filters['period'],
            'dateFrom' => $filters['dateFrom']->format('Y-m-d'),
            'dateTo'   => $filters['dateTo']->format('Y-m-d'),
        ]);
    }

    /**
     * Balance Sheet
     */
    public function balanceSheet(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        $records = $this->getAccountsData(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        // Normalize type casing once (defensive)
        $records->transform(function ($r) {
            $r->main_account_type = strtoupper(trim((string) $r->main_account_type));
            return $r;
        });

        // Broad grouping (handles "ASSETS - CURRENT", "LIABILITIES - SHORT", etc.)
        $assets = $records->filter(function ($r) {
            return str_starts_with($r->main_account_type, 'ASSET') || str_starts_with($r->main_account_type, 'ASSETS');
        });

        $liabilities = $records->filter(function ($r) {
            return str_starts_with($r->main_account_type, 'LIABILITY') || str_starts_with($r->main_account_type, 'LIABILITIES');
        });

        $capital = $records->filter(fn($r) => str_starts_with($r->main_account_type, 'CAPITAL'));

        // For retained earnings we need income & expense too
        $income   = $records->filter(fn($r) => str_contains($r->main_account_type, 'INCOME'));
        $expenses = $records->filter(fn($r) => str_contains($r->main_account_type, 'EXPENSE'));

        // Compute section balances from RAW debit/credit
        $totalAssets      = $assets->sum(fn($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
        $totalLiabilities = $liabilities->sum(fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
        $totalCapital     = $capital->sum(fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0));

        // Net Profit/Loss
        $totalIncome   = $income->sum(fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
        $totalExpenses = $expenses->sum(fn($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
        $netProfit     = $totalIncome - $totalExpenses; // +profit, -loss

        // Retained earnings line: credit for profit, debit for loss
        $retainedEarnings = (object) [
            'main_account_code' => '',
            'sub_account_code'  => '',
            'sub_account_name'  => $netProfit >= 0 ? 'Retained Earnings (Profit)' : 'Accumulated Loss',
            'debit'             => $netProfit < 0 ? abs($netProfit) : 0,
            'credit'            => $netProfit >= 0 ? abs($netProfit) : 0,
            'main_account_type' => 'CAPITAL',
        ];

        // Add retained earnings to capital display
        $capital = $capital->values()->push($retainedEarnings);

        // Capital including retained earnings (note: adding netProfit is correct here)
        $totalCapitalAdjusted = $totalCapital + $netProfit;
        $totalRight           = $totalLiabilities + $totalCapitalAdjusted;

        // Ensure placeholders if empty (for view stability)
        if ($assets->isEmpty()) {
            $assets = collect([(object) [
                'sub_account_name'  => 'No Asset Records',
                'main_account_code' => '',
                'sub_account_code'  => '',
                'debit'             => 0,
                'credit'            => 0,
                'main_account_type' => 'ASSET',
            ]]);
        }

        if ($liabilities->isEmpty()) {
            $liabilities = collect([(object) [
                'sub_account_name'  => 'No Liability Records',
                'main_account_code' => '',
                'sub_account_code'  => '',
                'debit'             => 0,
                'credit'            => 0,
                'main_account_type' => 'LIABILITY',
            ]]);
        }

        if ($capital->isEmpty()) {
            $capital = collect([(object) [
                'sub_account_name'  => 'No Capital Records',
                'main_account_code' => '',
                'sub_account_code'  => '',
                'debit'             => 0,
                'credit'            => 0,
                'main_account_type' => 'CAPITAL',
            ]]);
        }

        return view('reports.accounts.balance_sheet', [
            'period'           => $filters['period'],
            'dateFrom'         => $filters['dateFrom']->format('Y-m-d'),
            'dateTo'           => $filters['dateTo']->format('Y-m-d'),
            'assets'           => $assets->values(),
            'liabilities'      => $liabilities->values(),
            'capital'          => $capital->values(),
            'totalAssets'      => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalCapital'     => $totalCapitalAdjusted,
            'totalRight'       => $totalRight,
            'netProfit'        => $netProfit,
        ]);
    }

    /**
     * Profit & Loss
     */
    public function profitLoss(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        $records = $this->getAccountsData(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        // Normalize type casing (defensive)
        $records->transform(function ($r) {
            $r->main_account_type = strtoupper(trim((string) $r->main_account_type));
            return $r;
        });

        $income   = $records->filter(fn($r) => str_contains($r->main_account_type, 'INCOME'))->values();
        $expenses = $records->filter(fn($r) => str_contains($r->main_account_type, 'EXPENSE'))->values();

        $totalIncome   = $income->sum(fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
        $totalExpenses = $expenses->sum(fn($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
        $netProfit     = $totalIncome - $totalExpenses;

        if ($income->isEmpty()) {
            $income = collect([(object) [
                'sub_account_name'  => 'No Income Records',
                'main_account_code' => '',
                'sub_account_code'  => '',
                'debit'             => 0,
                'credit'            => 0,
                'main_account_type' => 'INCOME',
            ]]);
        }

        if ($expenses->isEmpty()) {
            $expenses = collect([(object) [
                'sub_account_name'  => 'No Expense Records',
                'main_account_code' => '',
                'sub_account_code'  => '',
                'debit'             => 0,
                'credit'            => 0,
                'main_account_type' => 'EXPENSE',
            ]]);
        }

        return view('reports.accounts.profit_loss', [
            'period'        => $filters['period'],
            'dateFrom'      => $filters['dateFrom']->format('Y-m-d'),
            'dateTo'        => $filters['dateTo']->format('Y-m-d'),
            'income'        => $income,
            'expenses'      => $expenses,
            'totalIncome'   => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netProfit'     => $netProfit,
        ]);
    }

    // ----------------------------------------------------------------
    // PRIVATE SHARED FUNCTIONS
    // ----------------------------------------------------------------

    /**
     * Prepare validated filter dates/period.
     */
    private function prepareFilters(Request $request): array
    {
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        // Validate period format (YYYYMM)
        if (!empty($period) && !preg_match('/^\d{6}$/', $period)) {
            return ['error' => back()->with('error', 'Invalid period format. Use YYYYmm (e.g. 202510).')->withInput()];
        }

        // Default to current month if no dates provided
        if (empty($dateFrom) || empty($dateTo)) {
            $now = Carbon::now();
            $dateFrom = $now->copy()->startOfMonth()->startOfDay();
            $dateTo   = $now->copy()->endOfMonth()->endOfDay(); // includes 23:59:59
        } else {
            $dateFrom = Carbon::parse($dateFrom)->startOfDay();
            $dateTo   = Carbon::parse($dateTo)->endOfDay(); // includes 23:59:59
        }

        // Validate range
        if ($dateFrom->gt($dateTo)) {
            return ['error' => back()->with('error', 'Start date cannot be after end date.')->withInput()];
        }

        // 24 months max
        if ($dateFrom->diffInDays($dateTo) > 732 || $dateFrom->diffInMonths($dateTo) > 24) {
            return ['error' => back()->with('error', 'Date range cannot exceed 24 months (2 years).')->withInput()];
        }

        return compact('period', 'dateFrom', 'dateTo');
    }

    /**
     * Core query logic shared by Trial Balance / Balance Sheet / P&L.
     *
     * Fixes:
     * - Returns RAW debit and credit totals (no netting in SQL).
     * - Filters by period OR by date range (never both) to avoid missing rows.
     * - Uses COALESCE to prevent NULL totals.
     * - Groups by raw DB columns for deterministic SQL, while selecting TRIM() aliases for display.
     */
    private function getAccountsData(?string $period, Carbon $dateFrom, Carbon $dateTo)
    {
        $query = DB::table('sacco_accounts_trans as t')
            ->join('sacco_sub_account as s', 't.accounts_trans_sub_account', '=', 's.sub_account_id')
            ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
            ->select(
                DB::raw('TRIM(m.main_account_code) as main_account_code'),
                DB::raw('TRIM(m.main_account_name) as main_account_name'),
                DB::raw('TRIM(m.main_account_type) as main_account_type'),
                DB::raw('TRIM(s.sub_account_code) as sub_account_code'),
                DB::raw('TRIM(s.sub_account_name) as sub_account_name'),
                DB::raw('COALESCE(SUM(t.accounts_trans_debit),0)  as debit'),
                DB::raw('COALESCE(SUM(t.accounts_trans_credit),0) as credit')
            )
            // group by raw columns (not TRIM aliases) for SQL stability
            ->groupBy(
                'm.main_account_code',
                'm.main_account_name',
                'm.main_account_type',
                's.sub_account_code',
                's.sub_account_name'
            )
            ->orderByRaw("
                CASE
                    WHEN UPPER(m.main_account_type) LIKE 'ASSET%' OR UPPER(m.main_account_type) LIKE 'ASSETS%' THEN 1
                    WHEN UPPER(m.main_account_type) LIKE 'LIABILITY%' OR UPPER(m.main_account_type) LIKE 'LIABILITIES%' THEN 2
                    WHEN UPPER(m.main_account_type) LIKE 'CAPITAL%' THEN 3
                    WHEN UPPER(m.main_account_type) LIKE 'INCOME%' THEN 4
                    WHEN UPPER(m.main_account_type) LIKE 'EXPENSE%' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy('m.main_account_code')
            ->orderBy('s.sub_account_code');

        if (!empty($period)) {
            $query->where('t.accounts_trans_period', $period);
        } else {
            $query->whereBetween('t.accounts_trans_dat_date', [
                $dateFrom->format('Y-m-d H:i:s'),
                $dateTo->format('Y-m-d H:i:s'),
            ]);
        }

        return $query->get();
    }
}
