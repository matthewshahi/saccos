<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LoansExport implements FromCollection, WithHeadings
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return collect($this->records)->map(function($r){
            return [
                'Loan#' => $r->loan_id,
                'Member' => $r->member_name,
                'Phone' => $r->member_phone_no,
                'Company' => $r->company_name,
                'Type' => $r->loan_type_name,
                'Loan Amount' => $r->loan_amount,
                'Balance' => $r->current_balance,
                'Monthly Principal' => $r->loan_monthly_repayment_principal,
                'Expected Interest' => $r->annual_rate,
                'Monthly Amount' => $r->loan_monthly_repayment_amount,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Loan#','Member','Phone','Company','Type',
            'Loan Amount','Balance','Monthly Principal',
            'Expected Interest','Monthly Amount'
        ];
    }
}