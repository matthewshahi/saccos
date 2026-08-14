<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class LoansIssuedExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStrictNullComparison
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
            // Member
            'Member Name',
            'Sacco ID',
            'National ID',
            'Company',

            // Member balances
            'Total Savings',
            'Other Contributions',
            'Capital Contributions',

            // Loan
            'Loan Type',
            'Loan ID',
            'Loan Amount',
            'Insurance',
            'Loan Paid',
            'Period (Months)',
            'Taken Period (YYYYMM)',
            'Start Deduction Period',
            'Issued On',
            'Doc No',
            'Description',
            'Stopped',
        ];
    }

    public function map($row): array
    {
        return [
            // Member
            $row->member_name ?? '',
            $row->member_sacco_id ?? '',
            $row->member_national_id ?? '',
            $row->company_name ?? '',

            // Member balances
            (float) ($row->member_total_share ?? 0),
            (float) ($row->member_total_fosa ?? 0),
            (float) ($row->member_total_share_capital ?? 0),

            // Loan
            $row->loan_type_name ?? '',
            $row->loan_id ?? '',
            (float) ($row->loan_amount ?? 0),
            (float) ($row->loan_insurance ?? 0),
            (float) ($row->loan_loan_paid ?? 0),
            $row->loan_payment_period ?? '',
            $row->loan_taken_period ?? '',
            $row->loan_start_deduction_period ?? '',
            $row->loan_on ?? '',
            $row->loan_doc_no ?? '',
            $row->loan_description ?? '',
            $row->loan_stoped ?? '',
        ];
    }
}