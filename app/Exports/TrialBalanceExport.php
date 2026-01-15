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
        return $this->records
            ->filter(function ($r) {
                // Skip zero rows (controller already filtered, this is defensive)
                return ((float) ($r->debit ?? 0) !== 0.0)
                    || ((float) ($r->credit ?? 0) !== 0.0);
            })
            ->map(function ($r) {
                return [
                    'Account Type' => strtoupper((string) $r->main_account_type),
                    'Account Code' => trim($r->main_account_code) . '/' . trim($r->sub_account_code),
                    'Account Name' => trim($r->sub_account_name),
                    'Debit'        => (float) ($r->debit ?? 0),
                    'Credit'       => (float) ($r->credit ?? 0),
                ];
            })
            ->values();
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
