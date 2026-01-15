<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Exports\TrialBalanceExport;
use App\Exports\ProfitLossExport;
// If you have a BalanceSheetExport later, you can plug it in similarly.

class TrialBalanceController extends Controller
{
    // ------------------------------------------------------------
    // PUBLIC ENDPOINTS
    // ------------------------------------------------------------

    /**
     * Trial Balance view (canonical base report)
     */
    public function index(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        $tb = $this->getTrialBalanceRows(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        return view('reports.accounts.trial_balance', [
            'records'  => $tb,
            'period'   => $filters['period'],
            'dateFrom' => $filters['dateFrom']->format('Y-m-d'),
            'dateTo'   => $filters['dateTo']->format('Y-m-d'),
        ]);
    }

    /**
     * Balance Sheet view (derived from Trial Balance)
     */
   public function balanceSheet(Request $request)
{
    $filters = $this->prepareFilters($request);
    if (isset($filters['error'])) return $filters['error'];

    // Canonical Trial Balance (already netted)
    $tb = $this->getTrialBalanceRows(
        $filters['period'],
        $filters['dateFrom'],
        $filters['dateTo']
    );

    // Normalize account type once
    $tb = $tb->map(function ($r) {
        $r->main_account_type = strtoupper(trim((string) $r->main_account_type));
        return $r;
    });

    // -------------------------------
    // BALANCE SHEET SECTIONS (RAW TB ROWS)
    // -------------------------------
    $assets = $tb->filter(fn ($r) =>
        str_starts_with($r->main_account_type, 'ASSET')
        || str_starts_with($r->main_account_type, 'ASSETS')
    )->values();

    $liabilities = $tb->filter(fn ($r) =>
        str_starts_with($r->main_account_type, 'LIABILITY')
        || str_starts_with($r->main_account_type, 'LIABILITIES')
    )->values();

    $capital = $tb->filter(fn ($r) =>
        str_starts_with($r->main_account_type, 'CAPITAL')
    )->values();

    // -------------------------------
    // PERIOD RESULT (FROM SAME TB)
    // -------------------------------
    $income = $tb->filter(fn ($r) => str_contains($r->main_account_type, 'INCOME'));
    $expenses = $tb->filter(fn ($r) => str_contains($r->main_account_type, 'EXPENSE'));

    $totalIncome = $income->sum(fn ($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
    $totalExpenses = $expenses->sum(fn ($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
    $periodResult = $totalIncome - $totalExpenses;

    // Presentation-only row (TB-consistent)
    $periodResultRow = (object) [
        'main_account_code' => '',
        'main_account_name' => '',
        'sub_account_code'  => '',
        'sub_account_name'  => $periodResult >= 0 ? 'Period Profit' : 'Period Loss',
        'debit'             => $periodResult < 0 ? abs($periodResult) : 0,
        'credit'            => $periodResult >= 0 ? abs($periodResult) : 0,
        'main_account_type' => 'CAPITAL',
    ];

    $capitalDisplay = $capital->values()->push($periodResultRow);

    // -------------------------------
    // TOTALS (NET, FROM TB RULES)
    // -------------------------------
    $totalAssets = $assets->sum(fn ($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
    $totalLiabilities = $liabilities->sum(fn ($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
    $totalCapital = $capitalDisplay->sum(fn ($r) => ($r->credit ?? 0) - ($r->debit ?? 0));

    $totalRight = $totalLiabilities + $totalCapital;

    return view('reports.accounts.balance_sheet', [
        'period'           => $filters['period'],
        'dateFrom'         => $filters['dateFrom']->format('Y-m-d'),
        'dateTo'           => $filters['dateTo']->format('Y-m-d'),

        'assets'           => $assets,
        'liabilities'      => $liabilities,
        'capital'          => $capitalDisplay,

        'totalAssets'      => $totalAssets,
        'totalLiabilities' => $totalLiabilities,
        'totalCapital'     => $totalCapital,
        'totalRight'       => $totalRight,
    ]);
}



    /**
     * Profit & Loss view (derived from Trial Balance)
     */
    public function profitLoss(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        $tb = $this->getTrialBalanceRows(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        // Normalize type casing once
        $tb = $tb->map(function ($r) {
            $r->main_account_type = strtoupper(trim((string) $r->main_account_type));
            return $r;
        });

        $income   = $tb->filter(fn($r) => str_contains($r->main_account_type, 'INCOME'))->values();
        $expenses = $tb->filter(fn($r) => str_contains($r->main_account_type, 'EXPENSE'))->values();

        $totalIncome   = $income->sum(fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
        $totalExpenses = $expenses->sum(fn($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
        $netProfit     = $totalIncome - $totalExpenses;

        $income   = $this->ensureNotEmptyRows($income, 'No Income Records', 'INCOME');
        $expenses = $this->ensureNotEmptyRows($expenses, 'No Expense Records', 'EXPENSE');

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

    /**
     * Trial Balance Excel export
     */
    public function exportExcel(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) {
            return $filters['error'];
        }

        // Canonical Trial Balance rows (already netted correctly)
        $tb = $this->getTrialBalanceRows(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        /*
     |--------------------------------------------------------------------------
     | File naming logic (professional & audit-safe)
     |--------------------------------------------------------------------------
     | 1. If period is provided (YYYYMM):
     |    trial_balance_202601_generated_2026-01-15.xlsx
     |
     | 2. If date range is used:
     |    trial_balance_2025-01-01_to_2026-01-31_generated_2026-01-15.xlsx
     |--------------------------------------------------------------------------
     */

        if (!empty($filters['period'])) {
            $label = 'period_' . $filters['period'];
        } else {
            $label =
                $filters['dateFrom']->format('Y-m-d') .
                '_to_' .
                $filters['dateTo']->format('Y-m-d');
        }

        $filename =
            'trial_balance_' .
            $label .
            '_generated_' .
            now()->format('Y-m-d') .
            '.xlsx';

        return Excel::download(
            new TrialBalanceExport($tb),
            $filename
        );
    }


    /**
     * Trial Balance PDF export (uses the same Trial Balance rows)
     */
    public function exportPdf(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        $tb = $this->getTrialBalanceRows(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        // Provide rows in a simple structure commonly used by TB PDF blades
        // IMPORTANT: now we NEVER re-net here. Trial Balance rows are already netted.
        $rows = $tb->map(function ($r) {
            return [
                'name'   => $r->sub_account_name,
                'debit'  => ($r->debit ?? 0) > 0 ? number_format((float) $r->debit, 2) : '',
                'credit' => ($r->credit ?? 0) > 0 ? number_format((float) $r->credit, 2) : '',
            ];
        })->values()->all();

        $suffix = $filters['period'] ?: ($filters['dateFrom']->format('Ymd') . '_to_' . $filters['dateTo']->format('Ymd'));

        return Pdf::loadView('reports.accounts.trial_balance_pdf', [
            'rows'   => $rows,
            'period' => $filters['period'] ?: ('DATE RANGE: ' . $filters['dateFrom']->format('Y-m-d') . ' to ' . $filters['dateTo']->format('Y-m-d')),
        ])->download('trial_balance_' . $suffix . '.pdf');
    }

    /**
     * Profit & Loss Excel export (derived from Trial Balance, but export class may already filter)
     * If your ProfitLossExport expects raw records, we pass TB rows so export matches the view.
     */
    // public function exportProfitLossExcel(Request $request)
    // {
    //     $filters = $this->prepareFilters($request);
    //     if (isset($filters['error'])) return $filters['error'];

    //     $tb = $this->getTrialBalanceRows(
    //         $filters['period'],
    //         $filters['dateFrom'],
    //         $filters['dateTo']
    //     );

    //     $suffix = $filters['period'] ?: ($filters['dateFrom']->format('Ymd') . '_to_' . $filters['dateTo']->format('Ymd'));

    //     return Excel::download(
    //         new ProfitLossExport($tb),
    //         'profit_and_loss_' . $suffix . '.xlsx'
    //     );
    // }

    // ------------------------------------------------------------
    // PRIVATE SHARED FUNCTIONS (SINGLE SOURCE OF TRUTH: accounts_trans)
    // ------------------------------------------------------------

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
            return ['error' => back()->with('error', 'Invalid period format. Use YYYYMM (e.g. 202510).')->withInput()];
        }

        // If either date is missing, default to current month range
        if (empty($dateFrom) || empty($dateTo)) {
            $now = Carbon::now();
            $dateFrom = $now->copy()->startOfMonth()->startOfDay();
            $dateTo   = $now->copy()->endOfMonth()->endOfDay();
        } else {
            $dateFrom = Carbon::parse($dateFrom)->startOfDay();
            $dateTo   = Carbon::parse($dateTo)->endOfDay();
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
     * Public-facing canonical Trial Balance rows.
     *
     * This is the ONLY place where netting into a TB debit/credit happens.
     * Everything else (Balance Sheet, P&L, exports) must use these rows.
     */
    private function getTrialBalanceRows(?string $period, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        $ledger = $this->getLedgerAggregates($period, $dateFrom, $dateTo);

        // Build trial balance (net debit/credit per row)
        $tb = $this->buildTrialBalance($ledger);

        // Sort into statement order (assets, liabilities, capital, income, expense)
        return $this->sortTrialBalance($tb);
    }

    /**
     * LAYER 1: Extract authoritative aggregates from sacco_accounts_trans.
     *
     * Returns GROSS debit/credit per sub-account+main-account identity.
     * No netting here.
     */
    private function getLedgerAggregates(?string $period, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        $query = DB::table('sacco_accounts_trans as t')
            ->join('sacco_sub_account as s', 't.accounts_trans_sub_account', '=', 's.sub_account_id')
            ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
            ->select(
                'm.main_account_code',
                'm.main_account_name',
                'm.main_account_type',
                's.sub_account_code',
                's.sub_account_name',
                DB::raw('COALESCE(SUM(t.accounts_trans_debit),0)  as gross_debit'),
                DB::raw('COALESCE(SUM(t.accounts_trans_credit),0) as gross_credit')
            )
            ->where(function ($q) {
                // Optional: exclude soft-deleted chart items if you use those flags.
                // Keep minimal and safe: only ignore explicitly deleted accounts.
                $q->whereNull('m.main_account_deleted')->orWhere('m.main_account_deleted', 'N');
            })
            ->where(function ($q) {
                $q->whereNull('s.sub_account_deleted')->orWhere('s.sub_account_deleted', 'N');
            })
            ->groupBy(
                'm.main_account_code',
                'm.main_account_name',
                'm.main_account_type',
                's.sub_account_code',
                's.sub_account_name'
            );

        // Filter strategy:
        // - If period provided, filter strictly by period.
        // - Else, filter strictly by date range.
        if (!empty($period)) {
            $query->where('t.accounts_trans_period', $period);
        } else {
            $query->whereBetween('t.accounts_trans_dat_date', [
                $dateFrom->format('Y-m-d H:i:s'),
                $dateTo->format('Y-m-d H:i:s'),
            ]);
        }

        // Keep stable deterministic base ordering
        $query->orderBy('m.main_account_code')->orderBy('s.sub_account_code');

        // Return trimmed display variants later; keep raw for SQL safety
        return $query->get()->map(function ($r) {
            // Defensive trimming for display
            $r->main_account_code = trim((string) $r->main_account_code);
            $r->main_account_name = trim((string) $r->main_account_name);
            $r->main_account_type = trim((string) $r->main_account_type);
            $r->sub_account_code  = trim((string) $r->sub_account_code);
            $r->sub_account_name  = trim((string) $r->sub_account_name);

            $r->gross_debit  = (float) ($r->gross_debit ?? 0);
            $r->gross_credit = (float) ($r->gross_credit ?? 0);

            return $r;
        });
    }

    /**
     * LAYER 2: Build Trial Balance rows by netting gross debits/credits.
     *
     * Rule:
     * - Net debit  = max(gross_debit - gross_credit, 0)
     * - Net credit = max(gross_credit - gross_debit, 0)
     *
     * IMPORTANT:
     * - This is a standard trial balance presentation netting per account.
     * - It does not alter the underlying gross totals (still auditable).
     */
    private function buildTrialBalance(Collection $ledger): Collection
    {
        return $ledger->map(function ($r) {
            $grossDebit  = (float) ($r->gross_debit ?? 0);
            $grossCredit = (float) ($r->gross_credit ?? 0);

            $diff = $grossDebit - $grossCredit;

            $r->debit  = $diff > 0 ? $diff : 0.0;
            $r->credit = $diff < 0 ? abs($diff) : 0.0;

            // Keep names consistent with your blades/exports
            unset($r->gross_debit, $r->gross_credit);

            return $r;
        })->filter(function ($r) {
            // Hide pure zeros in TB (optional but typically desirable)
            return ((float) ($r->debit ?? 0) !== 0.0) || ((float) ($r->credit ?? 0) !== 0.0);
        })->values();
    }

    /**
     * LAYER 3: Sorting for consistent report order.
     */
    private function sortTrialBalance(Collection $tb): Collection
    {
        return $tb->sortBy(function ($r) {
            $type = strtoupper(trim((string) $r->main_account_type));

            if (str_starts_with($type, 'ASSET') || str_starts_with($type, 'ASSETS')) {
                $bucket = 1;
            } elseif (str_starts_with($type, 'LIABILITY') || str_starts_with($type, 'LIABILITIES')) {
                $bucket = 2;
            } elseif (str_starts_with($type, 'CAPITAL')) {
                $bucket = 3;
            } elseif (str_contains($type, 'INCOME')) {
                $bucket = 4;
            } elseif (str_contains($type, 'EXPENSE')) {
                $bucket = 5;
            } else {
                $bucket = 6;
            }

            return sprintf(
                '%d|%s|%s',
                $bucket,
                (string) $r->main_account_code,
                (string) $r->sub_account_code
            );
        })->values();
    }

    /**
     * Ensure the view gets at least one placeholder row (view stability).
     */
    private function ensureNotEmptyRows(Collection $rows, string $label, string $type): Collection
    {
        if ($rows->isNotEmpty()) return $rows->values();

        return collect([(object) [
            'main_account_code' => '',
            'main_account_name' => '',
            'sub_account_code'  => '',
            'sub_account_name'  => $label,
            'debit'             => 0,
            'credit'            => 0,
            'main_account_type' => $type,
        ]]);
    }

    public function exportProfitLossExcel(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        // Canonical source: Trial Balance rows
        $tb = $this->getTrialBalanceRows(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        // Filename logic (period or date range)
        $suffix = $filters['period']
            ?: ($filters['dateFrom']->format('Ymd') . '_to_' . $filters['dateTo']->format('Ymd'));

        return Excel::download(
            new ProfitLossExport($tb),
            'profit_and_loss_' . $suffix . '.xlsx'
        );
    }
    public function exportProfitLossPdf(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        $tb = $this->getTrialBalanceRows(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        // Split into income & expenses
        $income = $tb->filter(
            fn($r) =>
            str_contains(strtoupper($r->main_account_type), 'INCOME')
        )->values();

        $expenses = $tb->filter(
            fn($r) =>
            str_contains(strtoupper($r->main_account_type), 'EXPENSE')
        )->values();

        $totalIncome = $income->sum(
            fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0)
        );

        $totalExpenses = $expenses->sum(
            fn($r) => ($r->debit ?? 0) - ($r->credit ?? 0)
        );

        $netProfit = $totalIncome - $totalExpenses;


        $suffix = $filters['period']
            ?: ($filters['dateFrom']->format('Ymd') . '_to_' . $filters['dateTo']->format('Ymd'));

        return Pdf::loadView('reports.accounts.profit_loss_pdf', [
            'income'        => $income,
            'expenses'      => $expenses,
            'totalIncome'   => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netProfit'     => $netProfit,
            'periodLabel'   => $filters['period']
                ?: ($filters['dateFrom']->format('d M Y') . ' to ' . $filters['dateTo']->format('d M Y')),
        ])->download('profit_and_loss_' . $suffix . '.pdf');
    }

    public function exportBalanceSheetExcel(Request $request)
    {
        $filters = $this->prepareFilters($request);
        if (isset($filters['error'])) return $filters['error'];

        // Reuse canonical TB
        $tb = $this->getTrialBalanceRows(
            $filters['period'],
            $filters['dateFrom'],
            $filters['dateTo']
        );

        // Normalize types
        $tb = $tb->map(function ($r) {
            $r->main_account_type = strtoupper(trim((string) $r->main_account_type));
            return $r;
        });

        $assets = $tb->filter(fn($r) => str_starts_with($r->main_account_type, 'ASSET'))->values();
        $liabilities = $tb->filter(fn($r) => str_starts_with($r->main_account_type, 'LIABILITY'))->values();
        $capital = $tb->filter(fn($r) => str_starts_with($r->main_account_type, 'CAPITAL'))->values();

        // Totals
        $totalAssets = $assets->sum(fn($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
        $totalLiabilities = $liabilities->sum(fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
        $totalCapital = $capital->sum(fn($r) => ($r->credit ?? 0) - ($r->debit ?? 0));


        $totalRight = $totalLiabilities + $totalCapital;

        $suffix = $filters['period']
            ?: ($filters['dateFrom']->format('Ymd') . '_to_' . $filters['dateTo']->format('Ymd'));

        return Excel::download(
            new \App\Exports\BalanceSheetExport(
                $assets,
                $liabilities,
                $capital,
                $totalAssets,
                $totalLiabilities,
                $totalCapital,
                $totalRight
            ),
            'balance_sheet_' . $suffix . '.xlsx'
        );
    }
    public function exportBalanceSheetPdf(Request $request)
{
    $filters = $this->prepareFilters($request);
    if (isset($filters['error'])) return $filters['error'];

    // Canonical TB rows (ALREADY NETTED)
    $tb = $this->getTrialBalanceRows(
        $filters['period'],
        $filters['dateFrom'],
        $filters['dateTo']
    );

    // Normalize account type
    $tb = $tb->map(function ($r) {
        $r->main_account_type = strtoupper(trim((string) $r->main_account_type));
        return $r;
    });

    // Sections
    $assets = $tb->filter(fn ($r) =>
        str_starts_with($r->main_account_type, 'ASSET')
        || str_starts_with($r->main_account_type, 'ASSETS')
    )->values();

    $liabilities = $tb->filter(fn ($r) =>
        str_starts_with($r->main_account_type, 'LIABILITY')
        || str_starts_with($r->main_account_type, 'LIABILITIES')
    )->values();

    $capital = $tb->filter(fn ($r) =>
        str_starts_with($r->main_account_type, 'CAPITAL')
    )->values();

    // PERIOD RESULT (FROM TB) – must match HTML
    $income = $tb->filter(fn ($r) => str_contains($r->main_account_type, 'INCOME'));
    $expenses = $tb->filter(fn ($r) => str_contains($r->main_account_type, 'EXPENSE'));

    $totalIncome = $income->sum(fn ($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
    $totalExpenses = $expenses->sum(fn ($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
    $periodResult = $totalIncome - $totalExpenses;

    // Totals (must match HTML)
    $totalAssets = $assets->sum(fn ($r) => ($r->debit ?? 0) - ($r->credit ?? 0));
    $totalLiabilities = $liabilities->sum(fn ($r) => ($r->credit ?? 0) - ($r->debit ?? 0));
    $totalCapital = $capital->sum(fn ($r) => ($r->credit ?? 0) - ($r->debit ?? 0));

    $totalRight = $totalLiabilities + $totalCapital + $periodResult;

    // Presentation-only Period Result row injected into Capital (exactly like HTML)
    $periodResultRow = (object) [
        'main_account_code' => '',
        'sub_account_code'  => '',
        'sub_account_name'  => $periodResult >= 0 ? 'Period Profit' : 'Period Loss',
        'debit'             => $periodResult < 0 ? abs($periodResult) : 0,
        'credit'            => $periodResult >= 0 ? abs($periodResult) : 0,
        'main_account_type' => 'CAPITAL',
    ];

    $capitalDisplay = $capital->values()->push($periodResultRow);

    // Labels
    $periodLabel = $filters['period']
        ? 'Period ' . $filters['period']
        : $filters['dateFrom']->format('d M Y') . ' to ' . $filters['dateTo']->format('d M Y');

    $suffix = $filters['period']
        ?: ($filters['dateFrom']->format('Ymd') . '_to_' . $filters['dateTo']->format('Ymd'));

    return Pdf::loadView('reports.accounts.balance_sheet_pdf', [
        'assets'           => $assets,
        'liabilities'      => $liabilities,
        'capital'          => $capitalDisplay,

        'totalAssets'      => $totalAssets,
        'totalLiabilities' => $totalLiabilities,
        'totalCapital'     => $totalCapital,
        'totalRight'       => $totalRight,

        'periodLabel'      => $periodLabel,
    ])->download('balance_sheet_' . $suffix . '.pdf');
}

    
}
