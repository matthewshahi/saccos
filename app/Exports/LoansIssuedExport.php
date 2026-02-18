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
            'Loan ID','Member Name','Member Sacco ID','Company','Loan Amount','Taken Period (YYYYMM)',
            'Start Deduction Period','Loan Date','Doc No','Description','Stopped','Payment Period','Loan Paid','Insurance',
        ];
    }

    public function map($row): array
    {
        return [
            $row->loan_id ?? '',
            $row->member_name ?? '',
            $row->member_sacco_id ?? '',
            $row->company_name ?? '',
            (float)($row->loan_amount ?? 0),
            $row->loan_taken_period ?? '',
            $row->loan_start_deduction_period ?? '',
            $row->loan_on ?? '',
            $row->loan_doc_no ?? '',
            $row->loan_description ?? '',
            $row->loan_stoped ?? '',
            $row->loan_payment_period ?? '',
            (float)($row->loan_loan_paid ?? 0),
            (float)($row->loan_insurance ?? 0),
        ];
    }
}
