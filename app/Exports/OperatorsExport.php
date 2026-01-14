<?php
namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OperatorsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return DB::table('sacco_matatus_operators as o')
            ->leftJoin('sacco_members as m', 'm.member_id', '=', 'o.introduced_by_member_id')
            ->leftJoin('sacco_matatus_stages as s', 's.id', '=', 'o.operator_stage_id')
            ->leftJoin('sacco_matatus_stage_chairs as sc', 'sc.id', '=', 'o.operator_chair_id')
            ->leftJoin('sacco_matatus_operator_vehicle_assignments as ova', function ($join) {
                $join->on('ova.v_assignment_operator_id', '=', 'o.id')
                     ->whereNull('ova.v_assignment_end_date');
            })
            ->leftJoin('sacco_matatus_vehicles as v', 'v.id', '=', 'ova.v_assignment_vehicle_id')
            ->leftJoin('sacco_members as vm', 'vm.member_id', '=', 'v.vehicles_member_id')
            ->select([
                'o.full_name',
                'o.phone',
                'o.national_id',
                'o.operator_type',
                'o.status',
                'm.member_name',
                's.stage_name',
                'sc.chair_name',
                'v.vehicles_registration_number',
                'v.vehicles_status',
                'vm.member_name as vehicle_owner',
            ])
            ->orderBy('o.full_name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Operator Name',
            'Phone',
            'National ID',
            'Type',
            'Status',
            'Introduced By',
            'Stage',
            'Stage Chair',
            'Vehicle',
            'Vehicle Status',
            'Vehicle Owner',
        ];
    }
}
