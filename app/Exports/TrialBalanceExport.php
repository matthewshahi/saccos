<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TrialBalanceExport implements FromCollection, WithHeadings
{
    protected Collection $records;

    public function __construct(Collection $records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records->map(function ($r) {

            $grossDebit  = $r->debit ?? 0;
            $grossCredit = $r->credit ?? 0;

            $debit  = 0;
            $credit = 0;

            if ($grossDebit > $grossCredit) {
                $debit = $grossDebit - $grossCredit;
            } elseif ($grossCredit > $grossDebit) {
                $credit = $grossCredit - $grossDebit;
            } else {
                return null; // skip zero balance
            }

            return [
                'Account Type' => strtoupper($r->main_account_type),
                'Account Code' => $r->main_account_code.'/'.$r->sub_account_code,
                'Account Name' => $r->sub_account_name,
                'Debit'        => $debit,
                'Credit'       => $credit,
            ];
        })->filter();
    }

    public function headings(): array
    {
        return [
            'Account Type',
            'Account Code',
            'Account Name',
            'Debit',
            'Credit',
        ];
    }
}
