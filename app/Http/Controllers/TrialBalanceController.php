<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Exports\TrialBalanceExport;
use App\Exports\ProfitLossExport;
use App\Exports\BalanceSheetExport;

class TrialBalanceController extends Controller
{
    /* ============================================================
     * TRIAL BALANCE
     * ============================================================
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

    /* ============================================================
     * BALANCE SHEET (SCREEN)
     * ============================================================
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

        // Normalize account types
        $records->transform(function ($r) {
            $r->main_account_type = strtoupper(trim((string) $r->main_account_type));
            return $r;
        });

        // Group accounts
        $assets = $records->filter(fn ($r) =>
            str_starts_with($r->main_account_type, 'ASSET')
            || str_starts_with($r->main_account_type, 'ASSETS')
        );

        $liabilities = $records->filter(fn ($r) =>
            str_starts_with($r->main_account_type, 'LIABILITY')
            || str_starts_with($r->main_account_type, 'LIABILITIES')
        );

        $capital = $records->filter(fn ($r) =>
            str_starts_with($r->main_account_type, 'CAPITAL')
        );

        // Profit & Loss for retained earnings
        $income   = $records->filter(fn ($r) => str_contains($r->main_account_type, 'INCOME'));
        $expenses = $records->filter(fn ($r) => str_contains($r->main_account_type, 'EXPENSE'));

        $totalIncome   = $income->sum(fn ($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
        $totalExpenses = $expenses->sum(fn ($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
        $netProfit     = $totalIncome - $totalExpenses;

        // Retained earnings (added ONCE)
        $capital = $capital->values()->push((object) [
            'main_account_code' => '',
            'sub_account_code'  => '',
            'sub_account_name'  => $netProfit >= 0
                ? 'Retained Earnings (Profit)'
                : 'Accumulated Loss',
            'debit'             => $netProfit < 0 ? abs($netProfit) : 0,
            'credit'            => $netProfit >= 0 ? abs($netProfit) : 0,
            'main_account_type' => 'CAPITAL',
        ]);

        // Totals (same logic as exports)
        $totalAssets = $assets->sum(fn ($r) =>
            max(0, ($r->debit ?? 0) - ($r->credit ?? 0))
        );

        $totalLiabilities = $liabilities->sum(fn ($r) =>
            max(0, ($r->credit ?? 0) - ($r->debit ?? 0))
        );

        $totalCapital = $capital->sum(fn ($r) =>
            max(0, ($r->credit ?? 0) - ($r->debit ?? 0))
        );

        $totalRight = $totalLiabilities + $totalCapital;

        return view('reports.accounts.balance_sheet', [
            'period'           => $filters['period'],
            'dateFrom'         => $filters['dateFrom']->format('Y-m-d'),
            'dateTo'           => $filters['dateTo']->format('Y-m-d'),
            'assets'           => $assets->values(),
            'liabilities'      => $liabilities->values(),
            'capital'          => $capital->values(),
            'totalAssets'      => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalCapital'     => $totalCapital,
            'totalRight'       => $totalRight,
            'netProfit'        => $netProfit,
        ]);
    }

    /* ============================================================
     * PROFIT & LOSS (SCREEN)
     * ============================================================
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

        $records->transform(function ($r) {
            $r->main_account_type = strtoupper(trim((string) $r->main_account_type));
            return $r;
        });

        $income   = $records->filter(fn ($r) => str_contains($r->main_account_type, 'INCOME'))->values();
        $expenses = $records->filter(fn ($r) => str_contains($r->main_account_type, 'EXPENSE'))->values();

        $totalIncome   = $income->sum(fn ($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
        $totalExpenses = $expenses->sum(fn ($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
        $netProfit     = $totalIncome - $totalExpenses;

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

    /* ============================================================
     * EXPORTS
     * ============================================================
     */

    public function exportExcel(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        return Excel::download(
            new TrialBalanceExport(
                $this->getAccountsData(
                    $filters['period'],
                    $filters['dateFrom'],
                    $filters['dateTo']
                )
            ),
            'trial_balance_' . $filters['period'] . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        return Pdf::loadView(
            'reports.accounts.trial_balance_pdf',
            [
                'rows'   => $this->getAccountsData(
                    $filters['period'],
                    $filters['dateFrom'],
                    $filters['dateTo']
                ),
                'period' => $filters['period'],
            ]
        )->download('trial_balance.pdf');
    }

    public function exportProfitLossExcel(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        return Excel::download(
            new ProfitLossExport(
                $this->getAccountsData(
                    $filters['period'],
                    $filters['dateFrom'],
                    $filters['dateTo']
                )
            ),
            'profit_and_loss_' . $filters['period'] . '.xlsx'
        );
    }

    public function exportProfitLossPdf(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        return Pdf::loadView(
            'reports.accounts.profit_loss_pdf',
            [
                'records' => $this->getAccountsData(
                    $filters['period'],
                    $filters['dateFrom'],
                    $filters['dateTo']
                ),
                'period'  => $filters['period'],
            ]
        )->download('profit_and_loss.pdf');
    }

    public function exportBalanceSheetExcel(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        $records = $this->getAccountsData(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        $records->transform(fn ($r) => tap($r, function ($x) {
            $x->main_account_type = strtoupper(trim($x->main_account_type));
        }));

        $assets = $records->filter(fn ($r) => str_starts_with($r->main_account_type, 'ASSET'));
        $liabilities = $records->filter(fn ($r) => str_starts_with($r->main_account_type, 'LIABILITY'));
        $capital = $records->filter(fn ($r) => str_starts_with($r->main_account_type, 'CAPITAL'));

        return Excel::download(
            new BalanceSheetExport($assets, $liabilities, $capital),
            'balance_sheet_' . $filters['period'] . '.xlsx'
        );
    }

    public function exportBalanceSheetPdf(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        return Pdf::loadView(
            'reports.accounts.balance_sheet_pdf',
            [
                'records' => $this->getAccountsData(
                    $filters['period'],
                    $filters['dateFrom'],
                    $filters['dateTo']
                ),
                'period'  => $filters['period'],
            ]
        )->download('balance_sheet.pdf');
    }

    /* ============================================================
     * SHARED HELPERS
     * ============================================================
     */

    private function prepareFilters(Request $request): array
    {
        $period   = $request->input('period');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        if (!empty($period) && !preg_match('/^\d{6}$/', $period)) {
            return ['error' => back()->with('error', 'Invalid period format (YYYYMM).')->withInput()];
        }

        $dateFrom = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo   = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfMonth();

        return compact('period', 'dateFrom', 'dateTo');
    }

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
                DB::raw('COALESCE(SUM(t.accounts_trans_debit),0) as debit'),
                DB::raw('COALESCE(SUM(t.accounts_trans_credit),0) as credit')
            )
            ->groupBy(
                'm.main_account_code',
                'm.main_account_name',
                'm.main_account_type',
                's.sub_account_code',
                's.sub_account_name'
            );

        if ($period) {
            $query->where('t.accounts_trans_period', $period);
        } else {
            $query->whereBetween('t.accounts_trans_dat_date', [$dateFrom, $dateTo]);
        }

        return $query->get();
    }
}
