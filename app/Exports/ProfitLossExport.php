<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProfitLossExport implements FromCollection, WithHeadings
{
    protected Collection $records;

    public function __construct(Collection $records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->records as $r) {

            $type = strtoupper(trim($r->main_account_type));

            // INCOME → Credit - Debit
            if (str_contains($type, 'INCOME')) {
                $amount = ($r->credit ?? 0) - ($r->debit ?? 0);
                if ($amount != 0) {
                    $rows[] = [
                        'Category' => 'Income',
                        'Account'  => $r->sub_account_name,
                        'Code'     => $r->main_account_code.'/'.$r->sub_account_code,
                        'Amount'   => abs($amount),
                    ];
                }
            }

            // EXPENSE → Debit - Credit
            if (str_contains($type, 'EXPENSE')) {
                $amount = ($r->debit ?? 0) - ($r->credit ?? 0);
                if ($amount != 0) {
                    $rows[] = [
                        'Category' => 'Expense',
                        'Account'  => $r->sub_account_name,
                        'Code'     => $r->main_account_code.'/'.$r->sub_account_code,
                        'Amount'   => abs($amount),
                    ];
                }
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'Category',
            'Account Name',
            'Account Code',
            'Amount (KES)',
        ];
    }
}
