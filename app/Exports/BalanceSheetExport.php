<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BalanceSheetExport implements FromCollection, WithHeadings
{
    protected Collection $assets;
    protected Collection $liabilities;
    protected Collection $capital;

    protected float $totalAssets;
    protected float $totalLiabilities;
    protected float $totalCapital;
    protected float $totalRight;

    public function __construct(
        Collection $assets,
        Collection $liabilities,
        Collection $capital,
        float $totalAssets,
        float $totalLiabilities,
        float $totalCapital,
        float $totalRight
    ) {
        $this->assets           = $assets;
        $this->liabilities      = $liabilities;
        $this->capital          = $capital;
        $this->totalAssets      = $totalAssets;
        $this->totalLiabilities = $totalLiabilities;
        $this->totalCapital     = $totalCapital;
        $this->totalRight       = $totalRight;
    }

    public function collection()
    {
        $rows = [];

        /* =========================
         * ASSETS (show non-zero side)
         * ========================= */
        foreach ($this->assets as $a) {
            $amount =
                ($a->debit ?? 0) > 0
                    ? (float) $a->debit
                    : (float) ($a->credit ?? 0);

            if ($amount <= 0) continue;

            $rows[] = [
                'Assets',
                trim((string) $a->sub_account_name),
                trim((string) $a->main_account_code) . '/' . trim((string) $a->sub_account_code),
                $amount,
            ];
        }

        /* Spacer */
        $rows[] = ['', '', '', ''];

        /* =========================
         * LIABILITIES (show non-zero side)
         * ========================= */
        foreach ($this->liabilities as $l) {
            $amount =
                ($l->credit ?? 0) > 0
                    ? (float) $l->credit
                    : (float) ($l->debit ?? 0);

            if ($amount <= 0) continue;

            $rows[] = [
                'Liabilities',
                trim((string) $l->sub_account_name),
                trim((string) $l->main_account_code) . '/' . trim((string) $l->sub_account_code),
                $amount,
            ];
        }

        /* Spacer */
        $rows[] = ['', '', '', ''];

        /* =========================
         * CAPITAL (incl. period result)
         * ========================= */
        foreach ($this->capital as $c) {
            $amount =
                ($c->credit ?? 0) > 0
                    ? (float) $c->credit
                    : (float) ($c->debit ?? 0);

            if ($amount <= 0) continue;

            $rows[] = [
                'Capital',
                trim((string) $c->sub_account_name),
                trim((string) $c->main_account_code ?? '') . '/' . trim((string) $c->sub_account_code ?? ''),
                $amount,
            ];
        }

        /* =========================
         * TOTALS (from controller)
         * ========================= */
        $rows[] = ['', '', '', ''];
        $rows[] = ['TOTAL ASSETS', '', '', $this->totalAssets];
        $rows[] = ['TOTAL LIABILITIES', '', '', $this->totalLiabilities];
        $rows[] = ['TOTAL CAPITAL', '', '', $this->totalCapital];
        $rows[] = ['LIABILITIES + CAPITAL', '', '', $this->totalRight];

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'Section',
            'Account Name',
            'Account Code',
            'Amount (KES)',
        ];
    }
}
