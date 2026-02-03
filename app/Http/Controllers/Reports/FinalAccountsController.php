<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class FinalAccountsController extends Controller
{
    /**
     * Default timezone for period/date cutoffs.
     * (You said Africa/Nairobi.)
     */
    private const TZ = 'Africa/Nairobi';

    // -----------------------------
    // PUBLIC: TRIAL BALANCE
    // -----------------------------

    public function trialBalance(Request $request)
    {
        $ctx = $this->resolveContext($request, 'TB');

        $rows = $this->getTrialBalanceRows($ctx);
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

        $rows = $this->getTrialBalanceRows($ctx);
        $totals = $this->computeTrialBalanceTotals($rows);

        $pdf = Pdf::loadView('reports.final_accounts.trial_balance_pdf', [
            'rows'    => $rows,
            'totals'  => $totals,
            'ctx'     => $ctx,
            'notices' => $ctx['notices'],
        ]);

        $filename = 'trial_balance_' . ($ctx['mode'] === 'period' ? $ctx['as_at_period'] : $ctx['as_at_date']->format('Y-m-d')) . '.pdf';
        return $pdf->download($filename);
    }

    public function trialBalanceExcel(Request $request)
    {
        $ctx = $this->resolveContext($request, 'TB');

        $rows = $this->getTrialBalanceRows($ctx);
        $totals = $this->computeTrialBalanceTotals($rows);

        $headings = [
            'Main Code', 'Main Account', 'Main Type',
            'Sub Code', 'Sub Account',
            'Debit', 'Credit',
            'Net (Dr - Cr)',
            'Closing Debit', 'Closing Credit',
        ];

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r->main_account_code,
                $r->main_account_name,
                $r->main_account_type,
                $r->sub_account_code,
                $r->sub_account_name,
                (float) $r->debit,
                (float) $r->credit,
                (float) $r->net,
                (float) $r->closing_debit,
                (float) $r->closing_credit,
            ];
        }

        // Totals row
        $data[] = [
            '', 'TOTALS', '',
            '', '',
            (float) $totals['debit'],
            (float) $totals['credit'],
            (float) $totals['net'],
            (float) $totals['closing_debit'],
            (float) $totals['closing_credit'],
        ];

        $export = new class($headings, $data) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $headings, private array $data) {}
            public function headings(): array { return $this->headings; }
            public function array(): array { return $this->data; }
        };

        $filename = 'trial_balance_' . ($ctx['mode'] === 'period' ? $ctx['as_at_period'] : $ctx['as_at_date']->format('Y-m-d')) . '.xlsx';
        return Excel::download($export, $filename);
    }

    // -----------------------------
    // PUBLIC: PROFIT & LOSS
    // -----------------------------

    public function profitLoss(Request $request)
    {
        $ctx = $this->resolveContext($request, 'PL');

        $rows = $this->getProfitLossRows($ctx);
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

        $rows = $this->getProfitLossRows($ctx);
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

        $rows = $this->getProfitLossRows($ctx);
        $totals = $this->computeProfitLossTotals($rows);

        $headings = ['Type', 'Main Account', 'Debit', 'Credit', 'P&L Effect (Cr - Dr)'];

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r->main_group,
                $r->main_account_name,
                (float) $r->debit,
                (float) $r->credit,
                (float) $r->pnl_effect,
            ];
        }

        $data[] = ['', 'NET SURPLUS / (DEFICIT)', '', '', (float) $totals['net_surplus']];

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

    // -----------------------------
    // PUBLIC: BALANCE SHEET
    // -----------------------------

    public function balanceSheet(Request $request)
    {
        $ctx = $this->resolveContext($request, 'BS');

        $rows = $this->getBalanceSheetRows($ctx);
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

        $rows = $this->getBalanceSheetRows($ctx);
        $totals = $this->computeBalanceSheetTotals($rows);

        $pdf = Pdf::loadView('reports.final_accounts.balance_sheet_pdf', [
            'rows'    => $rows,
            'totals'  => $totals,
            'ctx'     => $ctx,
            'notices' => $ctx['notices'],
        ]);

        $filename = 'balance_sheet_' . ($ctx['mode'] === 'period' ? $ctx['as_at_period'] : $ctx['as_at_date']->format('Y-m-d')) . '.pdf';
        return $pdf->download($filename);
    }

    public function balanceSheetExcel(Request $request)
    {
        $ctx = $this->resolveContext($request, 'BS');

        $rows = $this->getBalanceSheetRows($ctx);
        $totals = $this->computeBalanceSheetTotals($rows);

        $headings = ['Group', 'Main Account', 'Debit', 'Credit', 'Balance (Dr - Cr)'];

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r->main_group,
                $r->main_account_name,
                (float) $r->debit,
                (float) $r->credit,
                (float) $r->balance,
            ];
        }

        $data[] = ['ASSET', 'TOTAL ASSETS', '', '', (float) $totals['total_assets']];
        $data[] = ['LIABILITY', 'TOTAL LIABILITIES', '', '', (float) $totals['total_liabilities']];
        $data[] = ['CAPITAL', 'TOTAL CAPITAL', '', '', (float) $totals['total_capital']];
        $data[] = ['', 'LIABILITIES + CAPITAL', '', '', (float) $totals['liabilities_plus_capital']];
        $data[] = ['', 'BALANCE CHECK (ASSETS - (L+C))', '', '', (float) $totals['diff']];

        $export = new class($headings, $data) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $headings, private array $data) {}
            public function headings(): array { return $this->headings; }
            public function array(): array { return $this->data; }
        };

        $filename = 'balance_sheet_' . ($ctx['mode'] === 'period' ? $ctx['as_at_period'] : $ctx['as_at_date']->format('Y-m-d')) . '.xlsx';
        return Excel::download($export, $filename);
    }

    // ============================================================
    // CONTEXT / FILTERS
    // ============================================================

    /**
     * Resolve report context with:
     * - mode: period/date
     * - end-of-day cutoffs
     * - defaults if missing/invalid
     * - notices for UI
     */
    private function resolveContext(Request $request, string $reportKey): array
    {
        $notices = [];

        // Defaults: end of day today (Nairobi)
        $todayEod = Carbon::now(self::TZ)->endOfDay();
        $todayPeriod = $todayEod->format('Ym');

        // Decide mode:
        // If any period field provided -> period mode, else date mode.
        $hasPeriodInput = $request->filled('period')
            || $request->filled('as_at_period')
            || $request->filled('period_from')
            || $request->filled('period_to');

        $mode = $hasPeriodInput ? 'period' : 'date';

        // Normalize per report:
        // TB/BS: AS AT (single cutoff)
        // PL: FROM/TO range
        if ($reportKey === 'TB' || $reportKey === 'BS') {
            if ($mode === 'period') {
                $asAtPeriod = $this->cleanPeriod($request->input('as_at_period', $request->input('period')));
                if (!$asAtPeriod) {
                    $asAtPeriod = $todayPeriod;
                    $notices[] = "Invalid/missing period. Defaulted to current period {$todayPeriod}.";
                }

                return [
                    'report'       => $reportKey,
                    'mode'         => 'period',
                    'as_at_period' => $asAtPeriod,
                    'as_at_date'   => $this->periodEndDate($asAtPeriod), // for display only
                    'notices'      => $notices,
                ];
            }

            // date mode
            $asAtDate = $this->parseDateOrNull($request->input('as_at_date', $request->input('date')));
            if (!$asAtDate) {
                $asAtDate = $todayEod->copy();
                $notices[] = "Invalid/missing date. Defaulted to end of today ({$asAtDate->format('Y-m-d H:i:s')}).";
            } else {
                // Always use end-of-day cutoff for that day
                $asAtDate = $asAtDate->setTimezone(self::TZ)->endOfDay();
            }

            return [
                'report'       => $reportKey,
                'mode'         => 'date',
                'as_at_date'   => $asAtDate,
                'as_at_period' => $asAtDate->format('Ym'), // helpful for labels
                'notices'      => $notices,
            ];
        }

        // PL report
        if ($mode === 'period') {
            $from = $this->cleanPeriod($request->input('period_from'));
            $to   = $this->cleanPeriod($request->input('period_to'));

            if (!$from && !$to) {
                // Default to current month only
                $from = $todayPeriod;
                $to = $todayPeriod;
                $notices[] = "Missing period range. Defaulted to current period {$todayPeriod}.";
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

            // Ensure from <= to
            if ($from > $to) {
                [$from, $to] = [$to, $from];
                $notices[] = "Period range was reversed. Swapped to {$from} → {$to}.";
            }

            return [
                'report'      => $reportKey,
                'mode'        => 'period',
                'period_from' => $from,
                'period_to'   => $to,
                // convenience dates (end-of-month)
                'date_from'   => $this->periodStartDate($from),
                'date_to'     => $this->periodEndDate($to),
                'notices'     => $notices,
            ];
        }

        // date mode PL
        $fromDate = $this->parseDateOrNull($request->input('date_from'));
        $toDate   = $this->parseDateOrNull($request->input('date_to'));

        if (!$fromDate && !$toDate) {
            // default: today only (start->end)
            $fromDate = $todayEod->copy()->startOfDay();
            $toDate = $todayEod->copy()->endOfDay();
            $notices[] = "Missing date range. Defaulted to today ({$toDate->format('Y-m-d')}).";
        } else {
            if (!$fromDate) {
                // if only toDate provided, use same day range
                $fromDate = $toDate ? $toDate->copy()->startOfDay() : $todayEod->copy()->startOfDay();
                $notices[] = "Missing/invalid date_from. Defaulted to {$fromDate->format('Y-m-d')}.";
            } else {
                $fromDate = $fromDate->setTimezone(self::TZ)->startOfDay();
            }

            if (!$toDate) {
                $toDate = $fromDate->copy()->endOfDay();
                $notices[] = "Missing/invalid date_to. Defaulted to {$toDate->format('Y-m-d')}.";
            } else {
                // Always end-of-day cutoff for toDate
                $toDate = $toDate->setTimezone(self::TZ)->endOfDay();
            }
        }

        // Ensure from <= to
        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
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

    private function parseDateOrNull(?string $s): ?Carbon
    {
        if (!$s) return null;
        try {
            // Accept YYYY-MM-DD and full timestamps; always interpret in Nairobi.
            return Carbon::parse($s, self::TZ);
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
    // QUERIES (REUSABLE)
    // ============================================================

    /**
     * Base join (trans -> sub -> main)
     * Returns a Query Builder you can further filter/group.
     */
    private function baseJoinQuery()
    {
        return DB::table('sacco_accounts_trans as t')
            ->join('sacco_sub_account as sa', 'sa.sub_account_id', '=', 't.accounts_trans_sub_account')
            ->join('sacco_main_account as ma', 'ma.main_account_id', '=', 'sa.sub_account_main_account');
    }

    /**
     * Apply "as at" cutoff for TB/BS.
     */
    private function applyAsAtFilter($q, array $ctx)
    {
        if ($ctx['mode'] === 'period') {
            // Canonical TB/BS is cumulative up to period
            return $q->whereRaw("COALESCE(t.accounts_trans_period,'') <= ?", [$ctx['as_at_period']]);
        }

        // Date mode: cumulative up to end-of-day cutoff
        return $q->where('t.accounts_trans_dat_date', '<=', $ctx['as_at_date']->format('Y-m-d H:i:s'));
    }

    /**
     * Apply range filter for P&L.
     */
    private function applyRangeFilter($q, array $ctx)
    {
        if ($ctx['mode'] === 'period') {
            return $q->whereRaw("COALESCE(t.accounts_trans_period,'') BETWEEN ? AND ?", [
                $ctx['period_from'], $ctx['period_to']
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

        // fallback (keeps report stable)
        return $t ?: 'UNKNOWN';
    }

    // ============================================================
    // TRIAL BALANCE (SUB-ACCOUNT LEVEL)
    // ============================================================

    private function getTrialBalanceRows(array $ctx)
    {
        $q = $this->baseJoinQuery();
        $q = $this->applyAsAtFilter($q, $ctx);

        // Null-safe sums everywhere.
        // net = Dr - Cr
        // closing_debit = max(net, 0)
        // closing_credit = max(-net, 0)
        $rows = $q->select([
                'ma.main_account_id',
                'ma.main_account_code',
                'ma.main_account_name',
                'ma.main_account_type',
                'sa.sub_account_id',
                'sa.sub_account_code',
                'sa.sub_account_name',
                DB::raw('SUM(COALESCE(t.accounts_trans_debit,0))  AS debit'),
                DB::raw('SUM(COALESCE(t.accounts_trans_credit,0)) AS credit'),
                DB::raw('(SUM(COALESCE(t.accounts_trans_debit,0)) - SUM(COALESCE(t.accounts_trans_credit,0))) AS net'),
                DB::raw('GREATEST((SUM(COALESCE(t.accounts_trans_debit,0)) - SUM(COALESCE(t.accounts_trans_credit,0))), 0) AS closing_debit'),
                DB::raw('GREATEST((SUM(COALESCE(t.accounts_trans_credit,0)) - SUM(COALESCE(t.accounts_trans_debit,0))), 0) AS closing_credit'),
            ])
            ->groupBy(
                'ma.main_account_id',
                'ma.main_account_code',
                'ma.main_account_name',
                'ma.main_account_type',
                'sa.sub_account_id',
                'sa.sub_account_code',
                'sa.sub_account_name',
            )
            ->orderBy('ma.main_account_code')
            ->orderBy('sa.sub_account_code')
            ->get();

        return $rows;
    }

    private function computeTrialBalanceTotals($rows): array
    {
        $totDebit = 0.0;
        $totCredit = 0.0;
        $totNet = 0.0;
        $totClosingDr = 0.0;
        $totClosingCr = 0.0;

        foreach ($rows as $r) {
            $totDebit += (float) ($r->debit ?? 0);
            $totCredit += (float) ($r->credit ?? 0);
            $totNet += (float) ($r->net ?? 0);
            $totClosingDr += (float) ($r->closing_debit ?? 0);
            $totClosingCr += (float) ($r->closing_credit ?? 0);
        }

        return [
            'debit'         => $totDebit,
            'credit'        => $totCredit,
            'net'           => $totNet,
            'closing_debit' => $totClosingDr,
            'closing_credit'=> $totClosingCr,
            'diff'          => $totDebit - $totCredit,
        ];
    }

    // ============================================================
    // PROFIT & LOSS (MAIN-ACCOUNT ROLLUP)
    // ============================================================

    private function getProfitLossRows(array $ctx)
    {
        $q = $this->baseJoinQuery();
        $q = $this->applyRangeFilter($q, $ctx);

        // Only INCOME/EXPENSE
        $q->where(function ($w) {
            $w->where('ma.main_account_type', 'like', 'INCOME%')
              ->orWhere('ma.main_account_type', 'like', 'EXPENSE%');
        });

        $rows = $q->select([
                'ma.main_account_id',
                'ma.main_account_name',
                'ma.main_account_type',
                DB::raw('SUM(COALESCE(t.accounts_trans_debit,0))  AS debit'),
                DB::raw('SUM(COALESCE(t.accounts_trans_credit,0)) AS credit'),
                // pnl_effect: Cr - Dr (so income -> positive, expense -> negative)
                DB::raw('(SUM(COALESCE(t.accounts_trans_credit,0)) - SUM(COALESCE(t.accounts_trans_debit,0))) AS pnl_effect'),
            ])
            ->groupBy('ma.main_account_id', 'ma.main_account_name', 'ma.main_account_type')
            ->orderBy('ma.main_account_type')
            ->orderBy('ma.main_account_name')
            ->get();

        // add main_group (normalized) for display grouping
        foreach ($rows as $r) {
            $r->main_group = $this->normMainGroup((string) $r->main_account_type);
        }

        return $rows;
    }

    private function computeProfitLossTotals($rows): array
    {
        $income = 0.0;
        $expense = 0.0;
        $net = 0.0;

        foreach ($rows as $r) {
            $effect = (float) ($r->pnl_effect ?? 0);
            $net += $effect;

            $g = $r->main_group ?? $this->normMainGroup((string) $r->main_account_type);
            if ($g === 'INCOME') $income += $effect;
            if ($g === 'EXPENSE') $expense += $effect; // will usually be negative
        }

        return [
            'income_total' => $income,
            'expense_total'=> $expense,
            'net_surplus'  => $net,
        ];
    }

    // ============================================================
    // BALANCE SHEET (MAIN-ACCOUNT ROLLUP, AS AT)
    // ============================================================

    private function getBalanceSheetRows(array $ctx)
    {
        $q = $this->baseJoinQuery();
        $q = $this->applyAsAtFilter($q, $ctx);

        // Assets, Liabilities, Capital only
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
        $assets = 0.0;
        $liabilities = 0.0;
        $capital = 0.0;

        foreach ($rows as $r) {
            $bal = (float) ($r->balance ?? 0); // Dr - Cr

            $g = $r->main_group ?? $this->normMainGroup((string) $r->main_account_type);

            if ($g === 'ASSET') {
                // Assets are normally debit balances -> bal positive
                $assets += $bal;
            } elseif ($g === 'LIABILITY') {
                // Liabilities are normally credit balances -> bal negative, so invert to get a positive total
                $liabilities += (-1 * $bal);
            } elseif ($g === 'CAPITAL') {
                // Capital normally credit -> bal negative, invert
                $capital += (-1 * $bal);
            }
        }

        $lc = $liabilities + $capital;

        return [
            'total_assets'            => $assets,
            'total_liabilities'       => $liabilities,
            'total_capital'           => $capital,
            'liabilities_plus_capital'=> $lc,
            'diff'                    => $assets - $lc,
        ];
    }
}
