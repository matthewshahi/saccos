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
        return $this->records
            ->filter(function ($r) {
                $type = strtoupper(trim((string) $r->main_account_type));

                // Only INCOME and EXPENSE rows
                if (!str_contains($type, 'INCOME') && !str_contains($type, 'EXPENSE')) {
                    return false;
                }

                // Skip zero rows defensively
                return ((float) ($r->debit ?? 0) !== 0.0)
                    || ((float) ($r->credit ?? 0) !== 0.0);
            })
            ->map(function ($r) {
                $type = strtoupper(trim((string) $r->main_account_type));

                return [
                    'Category'     => str_contains($type, 'INCOME') ? 'Income' : 'Expense',
                    'Account Name' => trim((string) $r->sub_account_name),
                    'Account Code' => trim((string) $r->main_account_code) . '/' . trim((string) $r->sub_account_code),
                    'Amount (KES)' => str_contains($type, 'INCOME')
                        ? (float) ($r->credit ?? 0)
                        : (float) ($r->debit ?? 0),
                ];
            })
            ->values();
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
