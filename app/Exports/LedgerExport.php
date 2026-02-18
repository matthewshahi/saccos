<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class LedgerExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStrictNullComparison
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Period',
            'Date',
            'Doc No',
            'Account Code',
            'Account Name',
            'Description',
            'Debit',
            'Credit',
            'Cash In Already',
            'Reconciled',
            'Payment Type',
            'Reconciled Comments',
        ];
    }

    public function map($row): array
    {
        $accountCode = trim(($row->main_account_code ?? '') . '/' . ($row->sub_account_code ?? ''), '/');

        return [
            $row->accounts_trans_period ?? '',
            $row->accounts_trans_dat_date ?? '',
            $row->accounts_trans_doc_no ?? '',
            $accountCode,
            $row->sub_account_name ?? '',
            $row->accounts_trans_decription ?? '',
            (float)($row->accounts_trans_debit ?? 0),
            (float)($row->accounts_trans_credit ?? 0),
            $row->accounts_trans_cash_in_already ?? '',
            $row->accounts_trans_reconsiled ?? '',
            $row->accounts_trans_payment_type ?? '',
            $row->accounts_trans_reconsiled_comments ?? '',
        ];
    }
}
