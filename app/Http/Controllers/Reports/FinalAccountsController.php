<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class FinalAccountsController extends Controller
{
    /**
     * Default timezone for date cutoffs.
     */
    private const TZ = 'Africa/Nairobi';

    // ============================================================
    // PUBLIC: TRIAL BALANCE (STANDARD: AS AT ONLY)
    // ============================================================

    public function trialBalance(Request $request)
    {
        $ctx = $this->resolveContext($request, 'TB');

        $rows   = $this->getTrialBalanceRows($ctx);
        $totals = $this->computeTrialBalanceTotals($rows);

        return view('reports.final_accounts.trial_balance', [
            'rows'    => $rows,
            'totals'  => $totals,
            'ctx'     => $ctx,
            'notices' => $ctx['notices'],
        ]);
    }

    public function trialBalancePdf(Request $request)
    {
        $ctx = $this->resolveContext($request, 'TB');

        $rows   = $this->getTrialBalanceRows($ctx);
        $totals = $this->computeTrialBalanceTotals($rows);

        $pdf = Pdf::loadView('reports.final_accounts.trial_balance_pdf', [
            'rows'    => $rows,
            'totals'  => $totals,
            'ctx'     => $ctx,
            'notices' => $ctx['notices'],
        ]);

        $suffix = ($ctx['mode'] === 'period')
            ? $ctx['as_at_period']
            : $ctx['as_at_date']->format('Y-m-d');

        return $pdf->download("trial_balance_{$suffix}.pdf");
    }

    public function trialBalanceExcel(Request $request)
    {
        $ctx = $this->resolveContext($request, 'TB');

        $rows   = $this->getTrialBalanceRows($ctx);
        $totals = $this->computeTrialBalanceTotals($rows);

        // Standard TB: one balance per sub account (netted to Dr/Cr)
        $headings = [
            'Main Code', 'Main Account', 'Main Type',
            'Sub Code', 'Sub Account',
            'Debit', 'Credit',
        ];

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r->main_account_code,
                $r->main_account_name,
                $r->main_account_type,
                $r->sub_account_code,
                $r->sub_account_name,
                (float) ($r->tb_debit ?? 0),
                (float) ($r->tb_credit ?? 0),
            ];
        }

        $data[] = [
            '', 'GRAND TOTALS', '', '', '',
            (float) ($totals['debit'] ?? 0),
            (float) ($totals['credit'] ?? 0),
        ];

        $export = new class($headings, $data) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $headings, private array $data) {}
            public function headings(): array { return $this->headings; }
            public function array(): array { return $this->data; }
        };

        $suffix = ($ctx['mode'] === 'period')
            ? $ctx['as_at_period']
            : $ctx['as_at_date']->format('Y-m-d');

        return Excel::download($export, "trial_balance_{$suffix}.xlsx");
    }

    // ============================================================
    // PUBLIC: PROFIT & LOSS (STANDARD RANGE)
    // ============================================================

    public function profitLoss(Request $request)
    {
        $ctx = $this->resolveContext($request, 'PL');

        $rows   = $this->getProfitLossRows($ctx);
        $totals = $this->computeProfitLossTotals($rows);

        return view('reports.final_accounts.profit_loss', [
            'rows'    => $rows,
            'totals'  => $totals,
            'ctx'     => $ctx,
            'notices' => $ctx['notices'],
        ]);
    }

    public function profitLossPdf(Request $request)
    {
        $ctx = $this->resolveContext($request, 'PL');

        $rows   = $this->getProfitLossRows($ctx);
        $totals = $this->computeProfitLossTotals($rows);

        $pdf = Pdf::loadView('reports.final_accounts.profit_loss_pdf', [
            'rows'    => $rows,
            'totals'  => $totals,
            'ctx'     => $ctx,
            'notices' => $ctx['notices'],
        ]);

        $suffix = $ctx['mode'] === 'period'
            ? ($ctx['period_from'] . '_to_' . $ctx['period_to'])
            : ($ctx['date_from']->format('Y-m-d') . '_to_' . $ctx['date_to']->format('Y-m-d'));

        return $pdf->download("profit_loss_{$suffix}.pdf");
    }

    public function profitLossExcel(Request $request)
    {
        $ctx = $this->resolveContext($request, 'PL');

        $rows   = $this->getProfitLossRows($ctx);
        $totals = $this->computeProfitLossTotals($rows);

        // Present expenses as positive (standard readable P&L)
        $headings = ['Type', 'Main Account', 'Debit', 'Credit', 'Net (Cr - Dr)'];

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r->main_group,
                $r->main_account_name,
                (float) ($r->debit ?? 0),
                (float) ($r->credit ?? 0),
                (float) ($r->net_effect ?? 0),
            ];
        }

        $data[] = ['', 'TOTAL INCOME', '', '', (float) ($totals['income_total'] ?? 0)];
        $data[] = ['', 'TOTAL EXPENSES', '', '', (float) ($totals['expense_total'] ?? 0)];
        $data[] = ['', 'NET SURPLUS / (DEFICIT)', '', '', (float) ($totals['net_surplus'] ?? 0)];

        $export = new class($headings, $data) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $headings, private array $data) {}
            public function headings(): array { return $this->headings; }
            public function array(): array { return $this->data; }
        };

        $suffix = $ctx['mode'] === 'period'
            ? ($ctx['period_from'] . '_to_' . $ctx['period_to'])
            : ($ctx['date_from']->format('Y-m-d') . '_to_' . $ctx['date_to']->format('Y-m-d'));

        return Excel::download($export, "profit_loss_{$suffix}.xlsx");
    }

    // ============================================================
    // PUBLIC: BALANCE SHEET (STANDARD AS AT)
    // ============================================================

    public function balanceSheet(Request $request)
    {
        $ctx = $this->resolveContext($request, 'BS');

        $rows   = $this->getBalanceSheetRows($ctx);
        $totals = $this->computeBalanceSheetTotals($rows);

        return view('reports.final_accounts.balance_sheet', [
            'rows'    => $rows,
            'totals'  => $totals,
            'ctx'     => $ctx,
            'notices' => $ctx['notices'],
        ]);
    }

    public function balanceSheetPdf(Request $request)
    {
        $ctx = $this->resolveContext($request, 'BS');

        $rows   = $this->getBalanceSheetRows($ctx);
        $totals = $this->computeBalanceSheetTotals($rows);

        $pdf = Pdf::loadView('reports.final_accounts.balance_sheet_pdf', [
            'rows'    => $rows,
            'totals'  => $totals,
            'ctx'     => $ctx,
            'notices' => $ctx['notices'],
        ]);

        $suffix = ($ctx['mode'] === 'period')
            ? $ctx['as_at_period']
            : $ctx['as_at_date']->format('Y-m-d');

        return $pdf->download("balance_sheet_{$suffix}.pdf");
    }

    public function balanceSheetExcel(Request $request)
    {
        $ctx = $this->resolveContext($request, 'BS');

        $rows   = $this->getBalanceSheetRows($ctx);
        $totals = $this->computeBalanceSheetTotals($rows);

        $headings = ['Group', 'Main Account', 'Debit', 'Credit', 'Balance (Dr - Cr)'];

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r->main_group,
                $r->main_account_name,
                (float) ($r->debit ?? 0),
                (float) ($r->credit ?? 0),
                (float) ($r->balance ?? 0),
            ];
        }

        $data[] = ['ASSET', 'TOTAL ASSETS', '', '', (float) ($totals['total_assets'] ?? 0)];
        $data[] = ['LIABILITY', 'TOTAL LIABILITIES', '', '', (float) ($totals['total_liabilities'] ?? 0)];
        $data[] = ['CAPITAL', 'TOTAL CAPITAL', '', '', (float) ($totals['total_capital'] ?? 0)];
        $data[] = ['', 'LIABILITIES + CAPITAL', '', '', (float) ($totals['liabilities_plus_capital'] ?? 0)];
        $data[] = ['', 'BALANCE CHECK (ASSETS - (L+C))', '', '', (float) ($totals['diff'] ?? 0)];

        $export = new class($headings, $data) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $headings, private array $data) {}
            public function headings(): array { return $this->headings; }
            public function array(): array { return $this->data; }
        };

        $suffix = ($ctx['mode'] === 'period')
            ? $ctx['as_at_period']
            : $ctx['as_at_date']->format('Y-m-d');

        return Excel::download($export, "balance_sheet_{$suffix}.xlsx");
    }

    // ============================================================
    // CONTEXT / FILTERS (RESPECTS mode=period/date)
    // ============================================================

    private function resolveContext(Request $request, string $reportKey): array
    {
        $notices = [];

        $now        = Carbon::now(self::TZ);
        $todayEod   = $now->copy()->endOfDay();
        $todayPeriod = $todayEod->format('Ym');

        // Authoritative mode if supplied
        $modeParam = strtolower((string) $request->input('mode', ''));
        if ($modeParam === 'period' || $modeParam === 'date') {
            $mode = $modeParam;
        } else {
            $hasPeriodInput = $request->filled('period')
                || $request->filled('as_at_period')
                || $request->filled('period_from')
                || $request->filled('period_to');

            $hasDateInput = $request->filled('as_at_date')
                || $request->filled('date')
                || $request->filled('date_from')
                || $request->filled('date_to');

            if ($hasPeriodInput && $hasDateInput) {
                $mode = 'period';
                $notices[] = "Both period and date inputs provided. Using PERIOD mode.";
            } else {
                $mode = $hasPeriodInput ? 'period' : 'date';
            }
        }

        // TB / BS: single cutoff
        if ($reportKey === 'TB' || $reportKey === 'BS') {
            if ($mode === 'period') {
                $asAtPeriod = $this->cleanPeriod($request->input('as_at_period', $request->input('period')));
                if (!$asAtPeriod) {
                    $asAtPeriod = $todayPeriod;
                    $notices[] = "Invalid/missing as_at_period. Defaulted to {$todayPeriod}.";
                }

                return [
                    'report'       => $reportKey,
                    'mode'         => 'period',
                    'as_at_period' => $asAtPeriod,
                    'as_at_date'   => $this->periodEndDate($asAtPeriod), // display
                    'notices'      => $notices,
                ];
            }

            // Date mode: ALWAYS end-of-day (user provides date, we cut off at 23:59:59)
            $asAtDate = $this->parseDateOrNull($request->input('as_at_date', $request->input('date')));
            if (!$asAtDate) {
                $asAtDate = $todayEod->copy();
                $notices[] = "Invalid/missing as_at_date. Defaulted to end of today ({$asAtDate->format('Y-m-d')}).";
            } else {
                $asAtDate = $asAtDate->setTimezone(self::TZ)->endOfDay();
            }

            return [
                'report'       => $reportKey,
                'mode'         => 'date',
                'as_at_date'   => $asAtDate,
                'as_at_period' => $asAtDate->format('Ym'),
                'notices'      => $notices,
            ];
        }

        // PL: range
        if ($mode === 'period') {
            $from = $this->cleanPeriod($request->input('period_from'));
            $to   = $this->cleanPeriod($request->input('period_to'));

            if (!$from && !$to) {
                $from = $todayPeriod;
                $to   = $todayPeriod;
                $notices[] = "Missing period range. Defaulted to {$todayPeriod}.";
            } else {
                if (!$from) {
                    $from = $to ?: $todayPeriod;
                    $notices[] = "Missing/invalid period_from. Defaulted to {$from}.";
                }
                if (!$to) {
                    $to = $from;
                    $notices[] = "Missing/invalid period_to. Defaulted to {$to}.";
                }
            }

            if ($from > $to) {
                [$from, $to] = [$to, $from];
                $notices[] = "Period range was reversed. Swapped to {$from} → {$to}.";
            }

            return [
                'report'      => $reportKey,
                'mode'        => 'period',
                'period_from' => $from,
                'period_to'   => $to,
                'date_from'   => $this->periodStartDate($from),
                'date_to'     => $this->periodEndDate($to),
                'notices'     => $notices,
            ];
        }

        $fromDate = $this->parseDateOrNull($request->input('date_from'));
        $toDate   = $this->parseDateOrNull($request->input('date_to'));

        if (!$fromDate && !$toDate) {
            $fromDate = $todayEod->copy()->startOfDay();
            $toDate   = $todayEod->copy()->endOfDay();
            $notices[] = "Missing date range. Defaulted to today ({$toDate->format('Y-m-d')}).";
        } else {
            if (!$fromDate) {
                $fromDate = ($toDate ? $toDate->copy() : $todayEod->copy())->startOfDay();
                $notices[] = "Missing/invalid date_from. Defaulted to {$fromDate->format('Y-m-d')}.";
            } else {
                $fromDate = $fromDate->setTimezone(self::TZ)->startOfDay();
            }

            if (!$toDate) {
                $toDate = $fromDate->copy()->endOfDay();
                $notices[] = "Missing/invalid date_to. Defaulted to {$toDate->format('Y-m-d')}.";
            } else {
                $toDate = $toDate->setTimezone(self::TZ)->endOfDay();
            }
        }

        if ($fromDate->gt($toDate)) {
            $a = $fromDate->copy();
            $b = $toDate->copy();
            $fromDate = $b->copy()->startOfDay();
            $toDate   = $a->copy()->endOfDay();
            $notices[] = "Date range was reversed. Swapped to {$fromDate->format('Y-m-d')} → {$toDate->format('Y-m-d')}.";
        }

        return [
            'report'    => $reportKey,
            'mode'      => 'date',
            'date_from' => $fromDate,
            'date_to'   => $toDate,
            'notices'   => $notices,
        ];
    }

    private function cleanPeriod(?string $p): ?string
    {
        if (!$p) return null;
        $p = preg_replace('/[^0-9]/', '', (string) $p);
        if (strlen($p) !== 6) return null;

        $yyyy = (int) substr($p, 0, 4);
        $mm   = (int) substr($p, 4, 2);
        if ($yyyy < 1900 || $yyyy > 2100) return null;
        if ($mm < 1 || $mm > 12) return null;

        return $p;
    }

    /**
     * Parse user date and force TZ (then caller applies startOfDay/endOfDay).
     */
    private function parseDateOrNull(?string $s): ?Carbon
    {
        if (!$s) return null;
        try {
            return Carbon::parse($s)->setTimezone(self::TZ);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function periodStartDate(string $period): Carbon
    {
        $yyyy = (int) substr($period, 0, 4);
        $mm   = (int) substr($period, 4, 2);
        return Carbon::create($yyyy, $mm, 1, 0, 0, 0, self::TZ)->startOfDay();
    }

    private function periodEndDate(string $period): Carbon
    {
        return $this->periodStartDate($period)->endOfMonth()->endOfDay();
    }

    // ============================================================
    // QUERIES
    // ============================================================

    private function baseJoinQuery()
    {
        return DB::table('sacco_accounts_trans as t')
            ->join('sacco_sub_account as sa', 'sa.sub_account_id', '=', 't.accounts_trans_sub_account')
            ->join('sacco_main_account as ma', 'ma.main_account_id', '=', 'sa.sub_account_main_account');
    }

    /**
     * Strict "as at" cutoff.
     * IMPORTANT: In period mode, require a valid 6-digit period. Do not coalesce NULL periods into ''.
     */
    private function applyAsAtFilter($q, array $ctx)
    {
        if (($ctx['mode'] ?? '') === 'period') {
            // Safer: ignore rows with NULL/invalid period in period-mode.
            return $q->whereNotNull('t.accounts_trans_period')
                ->whereRaw("t.accounts_trans_period <= ?", [$ctx['as_at_period']]);
        }

        return $q->where('t.accounts_trans_dat_date', '<=', $ctx['as_at_date']->format('Y-m-d H:i:s'));
    }

    private function applyRangeFilter($q, array $ctx)
    {
        if (($ctx['mode'] ?? '') === 'period') {
            return $q->whereNotNull('t.accounts_trans_period')
                ->whereRaw("t.accounts_trans_period BETWEEN ? AND ?", [
                    $ctx['period_from'], $ctx['period_to'],
                ]);
        }

        return $q->whereBetween('t.accounts_trans_dat_date', [
            $ctx['date_from']->format('Y-m-d H:i:s'),
            $ctx['date_to']->format('Y-m-d H:i:s'),
        ]);
    }

    private function normMainGroup(string $mainType): string
    {
        $t = strtoupper(trim($mainType));

        if (str_starts_with($t, 'ASSET')) return 'ASSET';
        if (str_starts_with($t, 'LIABILIT')) return 'LIABILITY';
        if (str_starts_with($t, 'CAPITAL')) return 'CAPITAL';
        if (str_starts_with($t, 'INCOME')) return 'INCOME';
        if (str_starts_with($t, 'EXPENSE')) return 'EXPENSE';

        return $t ?: 'UNKNOWN';
    }

    // ============================================================
    // TRIAL BALANCE (STANDARD AS-AT)
    // ============================================================

    private function selectTrialBalanceNettedColumns($q)
    {
        return $q->select([
                'ma.main_account_id',
                'ma.main_account_code',
                'ma.main_account_name',
                'ma.main_account_type',
                'sa.sub_account_id',
                'sa.sub_account_code',
                'sa.sub_account_name',

                DB::raw('(SUM(COALESCE(t.accounts_trans_debit,0)) - SUM(COALESCE(t.accounts_trans_credit,0))) AS net'),
                DB::raw('GREATEST((SUM(COALESCE(t.accounts_trans_debit,0)) - SUM(COALESCE(t.accounts_trans_credit,0))), 0) AS tb_debit'),
                DB::raw('GREATEST((SUM(COALESCE(t.accounts_trans_credit,0)) - SUM(COALESCE(t.accounts_trans_debit,0))), 0) AS tb_credit'),
            ])
            ->groupBy(
                'ma.main_account_id',
                'ma.main_account_code',
                'ma.main_account_name',
                'ma.main_account_type',
                'sa.sub_account_id',
                'sa.sub_account_code',
                'sa.sub_account_name'
            )
            ->orderBy('ma.main_account_code')
            ->orderBy('sa.sub_account_code');
    }

    private function getTrialBalanceRows(array $ctx)
    {
        $q = $this->applyAsAtFilter($this->baseJoinQuery(), $ctx);
        return $this->selectTrialBalanceNettedColumns($q)->get();
    }

    private function computeTrialBalanceTotals($rows): array
    {
        $dr = 0.0;
        $cr = 0.0;

        foreach ($rows as $r) {
            $dr += (float) ($r->tb_debit ?? 0);
            $cr += (float) ($r->tb_credit ?? 0);
        }

        return [
            'debit'  => $dr,
            'credit' => $cr,
            'diff'   => $dr - $cr,
        ];
    }

    // ============================================================
    // PROFIT & LOSS (RANGE)
    // ============================================================

    private function getProfitLossRows(array $ctx)
{
    $q = $this->applyRangeFilter($this->baseJoinQuery(), $ctx);

    $q->where(function ($w) {
        $w->where('ma.main_account_type', 'like', 'INCOME%')
          ->orWhere('ma.main_account_type', 'like', 'EXPENSE%')
          ->orWhere('ma.main_account_type', 'like', 'TAX%'); // optional if you store taxation separately
    });

    $rows = $q->select([
            'ma.main_account_id',
            'ma.main_account_name',
            'ma.main_account_type',

            // raw totals (optional to keep for audit)
            DB::raw('SUM(COALESCE(t.accounts_trans_debit,0))  AS raw_debit'),
            DB::raw('SUM(COALESCE(t.accounts_trans_credit,0)) AS raw_credit'),

            // net effect (Cr - Dr)
            DB::raw('(SUM(COALESCE(t.accounts_trans_credit,0)) - SUM(COALESCE(t.accounts_trans_debit,0))) AS net_effect'),

            // ✅ netted presentation columns (only one side will be > 0)
            DB::raw('GREATEST((SUM(COALESCE(t.accounts_trans_debit,0)) - SUM(COALESCE(t.accounts_trans_credit,0))), 0) AS debit'),
            DB::raw('GREATEST((SUM(COALESCE(t.accounts_trans_credit,0)) - SUM(COALESCE(t.accounts_trans_debit,0))), 0) AS credit'),
        ])
        ->groupBy('ma.main_account_id', 'ma.main_account_name', 'ma.main_account_type')
        ->orderBy('ma.main_account_type')
        ->orderBy('ma.main_account_name')
        ->get();

    foreach ($rows as $r) {
        $r->main_group = $this->normMainGroup((string) $r->main_account_type);
    }

    return $rows;
}


    /**
     * Totals are presented as:
     * - income_total: positive
     * - expense_total: positive
     * - net_surplus = income_total - expense_total
     */
    private function computeProfitLossTotals($rows): array
{
    $income = 0.0;
    $expense = 0.0;

    foreach ($rows as $r) {
        $g = $r->main_group ?? $this->normMainGroup((string) $r->main_account_type);

        $dr = (float) ($r->debit ?? 0);   // net debit
        $cr = (float) ($r->credit ?? 0);  // net credit

        if ($g === 'INCOME') {
            $income += $cr; // income normally nets to credit
        } elseif ($g === 'EXPENSE') {
            $expense += $dr; // expenses normally net to debit
        } elseif ($g === 'TAXATION') {
            // if you classify taxation separately, decide policy:
            $expense += $dr; // common: treat tax as expense
        }
    }

    return [
        'income_total'  => $income,
        'expense_total' => $expense,
        'net_surplus'   => $income - $expense,
    ];
}


    // ============================================================
    // BALANCE SHEET (AS AT)
    // ============================================================

    private function getBalanceSheetRows(array $ctx)
    {
        $q = $this->applyAsAtFilter($this->baseJoinQuery(), $ctx);

        $q->where(function ($w) {
            $w->where('ma.main_account_type', 'like', 'ASSET%')
              ->orWhere('ma.main_account_type', 'like', 'LIABILIT%')
              ->orWhere('ma.main_account_type', 'like', 'CAPITAL%');
        });

        $rows = $q->select([
                'ma.main_account_id',
                'ma.main_account_name',
                'ma.main_account_type',
                DB::raw('SUM(COALESCE(t.accounts_trans_debit,0))  AS debit'),
                DB::raw('SUM(COALESCE(t.accounts_trans_credit,0)) AS credit'),
                DB::raw('(SUM(COALESCE(t.accounts_trans_debit,0)) - SUM(COALESCE(t.accounts_trans_credit,0))) AS balance'),
            ])
            ->groupBy('ma.main_account_id', 'ma.main_account_name', 'ma.main_account_type')
            ->orderBy('ma.main_account_type')
            ->orderBy('ma.main_account_name')
            ->get();

        foreach ($rows as $r) {
            $r->main_group = $this->normMainGroup((string) $r->main_account_type);
        }

        return $rows;
    }

    private function computeBalanceSheetTotals($rows): array
    {
        $assets      = 0.0;
        $liabilities = 0.0;
        $capital     = 0.0;

        foreach ($rows as $r) {
            $bal = (float) ($r->balance ?? 0);
            $g   = $r->main_group ?? $this->normMainGroup((string) $r->main_account_type);

            if ($g === 'ASSET') {
                // Assets: usually positive under (Dr - Cr)
                $assets += $bal;
            } elseif ($g === 'LIABILITY') {
                // Liabilities: usually negative under (Dr - Cr); invert to positive
                $liabilities += (-1 * $bal);
            } elseif ($g === 'CAPITAL') {
                // Capital: usually negative under (Dr - Cr); invert to positive
                $capital += (-1 * $bal);
            }
        }

        $lc = $liabilities + $capital;

        return [
            'total_assets'             => $assets,
            'total_liabilities'        => $liabilities,
            'total_capital'            => $capital,
            'liabilities_plus_capital' => $lc,
            'diff'                     => $assets - $lc,
        ];
    }
}
