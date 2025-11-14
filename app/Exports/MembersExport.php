<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\DB;

class MembersExport implements FromCollection, WithHeadings, WithMapping
{
    private $members;

    public function __construct($members)
    {
        $this->members = $members;
    }

    public function collection()
    {
        return $this->members;
    }

    public function headings(): array
    {
        return [
            'SACCO ID',
            'Member Name',
            'National ID',
            'Email',
            'Phone Number',
            'Department',
            'Position',
            'Company',
            'Status'
        ];
    }

    public function map($member): array
    {
        return [
            $member->member_sacco_id,
            $member->member_name,
            $member->member_national_id,
            $member->member_email,
            $member->member_phone_no,
            $member->department_name,
            $member->position_name,
            $member->company_name,
            $member->member_active ? 'Active' : 'Inactive'
        ];
    }
}