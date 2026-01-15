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

        foreach ($this->assets as $a) {
            $rows[] = [
                'Asset',
                $a->sub_account_name,
                ($a->main_account_code ?? '') . '/' . ($a->sub_account_code ?? ''),
                max(0, ($a->debit ?? 0) - ($a->credit ?? 0)),
            ];
        }

        foreach ($this->liabilities as $l) {
            $rows[] = [
                'Liability',
                $l->sub_account_name,
                ($l->main_account_code ?? '') . '/' . ($l->sub_account_code ?? ''),
                max(0, ($l->credit ?? 0) - ($l->debit ?? 0)),
            ];
        }

        foreach ($this->capital as $c) {
            $rows[] = [
                'Capital',
                $c->sub_account_name,
                ($c->main_account_code ?? '') . '/' . ($c->sub_account_code ?? ''),
                max(0, ($c->credit ?? 0) - ($c->debit ?? 0)),
            ];
        }

        // Totals
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
