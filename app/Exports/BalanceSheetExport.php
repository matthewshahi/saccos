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

    public function __construct($assets, $liabilities, $capital)
    {
        $this->assets      = $assets;
        $this->liabilities = $liabilities;
        $this->capital     = $capital;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->assets as $a) {
            $rows[] = [
                'Section' => 'Asset',
                'Account' => $a->sub_account_name,
                'Code'    => $a->main_account_code.'/'.$a->sub_account_code,
                'Amount'  => ($a->debit ?? 0) - ($a->credit ?? 0),
            ];
        }

        foreach ($this->liabilities as $l) {
            $rows[] = [
                'Section' => 'Liability',
                'Account' => $l->sub_account_name,
                'Code'    => $l->main_account_code.'/'.$l->sub_account_code,
                'Amount'  => ($l->credit ?? 0) - ($l->debit ?? 0),
            ];
        }

        foreach ($this->capital as $c) {
            $rows[] = [
                'Section' => 'Capital',
                'Account' => $c->sub_account_name,
                'Code'    => $c->main_account_code.'/'.$c->sub_account_code,
                'Amount'  => ($c->credit ?? 0) - ($c->debit ?? 0),
            ];
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return ['Section', 'Account Name', 'Account Code', 'Amount (KES)'];
    }
}
