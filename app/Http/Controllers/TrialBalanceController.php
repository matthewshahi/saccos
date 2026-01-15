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
        if (isset($filters['error'])) return $filters['error']; // validation redirect

        $records = $this->getAccountsData($filters['period'], $filters['dateFrom'], $filters['dateTo']);

        return view('reports.accounts.trial_balance', [
            'records'  => $records,
            'period'   => $filters['period'],
            'dateFrom' => $filters['dateFrom']->format('Y-m-d'),
            'dateTo'   => $filters['dateTo']->format('Y-m-d'),
        ]);
    }

    /**
     * Example usage in Balance Sheet (later)
     */
   public function balanceSheet(Request $request)
{
    $filters = $this->prepareFilters($request);
    if (isset($filters['error'])) return $filters['error'];

    $records = $this->getAccountsData($filters['period'], $filters['dateFrom'], $filters['dateTo']);

    // --- Group by type ---
    $assets = $records->filter(fn($r) => str_starts_with($r->main_account_type, 'ASSET'));
    $liabilities = $records->filter(fn($r) => str_starts_with($r->main_account_type, 'LIABILITY'));
    $capital = $records->filter(fn($r) => str_starts_with($r->main_account_type, 'CAPITAL'));
    $income = $records->filter(fn($r) => str_starts_with($r->main_account_type, 'INCOME'));
    $expenses = $records->filter(fn($r) => str_starts_with($r->main_account_type, 'EXPENSE'));

    // --- Compute section totals ---
    $totalAssets = $assets->sum(fn($r) => $r->debit - $r->credit);
    $totalLiabilities = $liabilities->sum(fn($r) => $r->credit - $r->debit);
    $totalCapital = $capital->sum(fn($r) => $r->credit - $r->debit);

    // --- Compute Net Profit/Loss (retained earnings) ---
    $totalIncome = $income->sum(fn($r) => $r->credit - $r->debit);
    $totalExpenses = $expenses->sum(fn($r) => $r->debit - $r->credit);
    $netProfit = $totalIncome - $totalExpenses;

    // Add retained earnings to capital section (for display)
    $retainedEarnings = (object) [
        'sub_account_name' => $netProfit >= 0 ? 'Retained Earnings (Profit)' : 'Accumulated Loss',
        'main_account_code' => '',
        'sub_account_code' => '',
        'debit' => 0,
        'credit' => abs($netProfit),
    ];

    $capital = $capital->push($retainedEarnings);
    $totalCapital += $netProfit;
    $totalRight = $totalLiabilities + $totalCapital;

    // --- Ensure all visible sections exist even if zero ---
    if ($assets->isEmpty()) {
        $assets = collect([(object)[
            'sub_account_name' => 'No Asset Records',
            'main_account_code' => '',
            'sub_account_code' => '',
            'debit' => 0, 'credit' => 0
        ]]);
    }
    if ($liabilities->isEmpty()) {
        $liabilities = collect([(object)[
            'sub_account_name' => 'No Liability Records',
            'main_account_code' => '',
            'sub_account_code' => '',
            'debit' => 0, 'credit' => 0
        ]]);
    }
    if ($capital->isEmpty()) {
        $capital = collect([(object)[
            'sub_account_name' => 'No Capital Records',
            'main_account_code' => '',
            'sub_account_code' => '',
            'debit' => 0, 'credit' => 0
        ]]);
    }

    return view('reports.accounts.balance_sheet', [
        'period'          => $filters['period'],
        'dateFrom'        => $filters['dateFrom']->format('Y-m-d'),
        'dateTo'          => $filters['dateTo']->format('Y-m-d'),
        'assets'          => $assets,
        'liabilities'     => $liabilities,
        'capital'         => $capital,
        'totalAssets'     => $totalAssets,
        'totalLiabilities'=> $totalLiabilities,
        'totalCapital'    => $totalCapital,
        'totalRight'      => $totalRight,
        'netProfit'       => $netProfit,
    ]);
}

public function profitLoss(Request $request)
{
    $filters = $this->prepareFilters($request);
    if (isset($filters['error'])) return $filters['error'];

    // Reuse the same trial balance data
    $records = $this->getAccountsData($filters['period'], $filters['dateFrom'], $filters['dateTo']);

    // --- Normalize casing for safety ---
    foreach ($records as $r) {
        $r->main_account_type = strtoupper(trim($r->main_account_type));
    }

    // --- Group by type just like trial balance ---
    $income = $records->filter(fn($r) => str_contains($r->main_account_type, 'INCOME'));
    $expenses = $records->filter(fn($r) => str_contains($r->main_account_type, 'EXPENSE'));

    // --- Compute totals using same debit/credit balance logic ---
    $totalIncome = $income->sum(fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
    $totalExpenses = $expenses->sum(fn($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
    $netProfit = $totalIncome - $totalExpenses;

    // --- Ensure both sides appear even if empty ---
    if ($income->isEmpty()) {
        $income = collect([(object)[
            'sub_account_name' => 'No Income Records',
            'main_account_code' => '',
            'sub_account_code' => '',
            'debit' => 0,
            'credit' => 0,
        ]]);
    }

    if ($expenses->isEmpty()) {
        $expenses = collect([(object)[
            'sub_account_name' => 'No Expense Records',
            'main_account_code' => '',
            'sub_account_code' => '',
            'debit' => 0,
            'credit' => 0,
        ]]);
    }
//     dd([
//     'period'        => $filters['period'],
//     'dateFrom'      => $filters['dateFrom']->format('Y-m-d'),
//     'dateTo'        => $filters['dateTo']->format('Y-m-d'),
//     'income'        => $income,
//     'expenses'      => $expenses,
//     'totalIncome'   => $totalIncome,
//     'totalExpenses' => $totalExpenses,
//     'netProfit'     => $netProfit,
// ]);

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
    // 🔹 PRIVATE SHARED FUNCTIONS BELOW
    // ----------------------------------------------------------------

    /**
     * Prepare validated filter dates/period.
     */
    private function prepareFilters(Request $request): array
    {
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        // Validate period format
        if (!empty($period) && !preg_match('/^\d{6}$/', $period)) {
            return ['error' => back()->with('error', 'Invalid period format. Use YYYYmm (e.g. 202510).')->withInput()];
        }

        // Default to current month
        if (empty($dateFrom) || empty($dateTo)) {
            $now = Carbon::now();
            $dateFrom = $now->copy()->startOfMonth();
            $dateTo   = $now->copy()->endOfMonth();
        } else {
            $dateFrom = Carbon::parse($dateFrom)->startOfDay();
            $dateTo   = Carbon::parse($dateTo)->endOfDay();
        }

        // Validate range
        if ($dateFrom->gt($dateTo)) {
            return ['error' => back()->with('error', 'Start date cannot be after end date.')->withInput()];
        }

        if ($dateFrom->diffInDays($dateTo) > 732 || $dateFrom->diffInMonths($dateTo) > 24) {
    return ['error' => back()
        ->with('error', 'Date range cannot exceed 24 months (2 years).')
        ->withInput()
    ];
}


        return compact('period', 'dateFrom', 'dateTo');
    }

    /**
     * Core query logic shared by Trial Balance / Balance Sheet / P&L.
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
                DB::raw("
                    CASE 
                        WHEN SUM(t.accounts_trans_debit) - SUM(t.accounts_trans_credit) > 0 
                        THEN SUM(t.accounts_trans_debit) - SUM(t.accounts_trans_credit)
                        ELSE 0 
                    END as debit
                "),
                DB::raw("
                    CASE 
                        WHEN SUM(t.accounts_trans_credit) - SUM(t.accounts_trans_debit) > 0 
                        THEN SUM(t.accounts_trans_credit) - SUM(t.accounts_trans_debit)
                        ELSE 0 
                    END as credit
                ")
            )
            ->groupBy(
                'm.main_account_code',
                'm.main_account_name',
                'm.main_account_type',
                's.sub_account_code',
                's.sub_account_name'
            )
            ->orderByRaw("
                CASE 
                    WHEN m.main_account_type LIKE 'ASSET%' THEN 1
                    WHEN m.main_account_type LIKE 'LIABILITY%' THEN 2
                    WHEN m.main_account_type LIKE 'CAPITAL%' THEN 3
                    WHEN m.main_account_type LIKE 'INCOME%' THEN 4
                    WHEN m.main_account_type LIKE 'EXPENSE%' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy('m.main_account_code')
            ->orderBy('s.sub_account_code');

        if (!empty($period)) {
            $query->where('t.accounts_trans_period', $period);
        }

        $query->whereBetween('t.accounts_trans_dat_date', [
            $dateFrom->format('Y-m-d H:i:s'),
            $dateTo->format('Y-m-d H:i:s'),
        ]);

        return $query->get();
    }
}