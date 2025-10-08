<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Support\Facades\DB;

class FosaTransactionsExport implements FromView
{
    protected $search;

    public function __construct($search = null)
    {
        $this->search = $search;
    }

    public function view(): View
    {
        $query = DB::table('sacco_fosas as f')
            ->join('sacco_members as m', 'f.fosa_member_id', '=', 'm.member_id')
            ->leftJoin('sacco_fosa_types as t', 'f.fosa_type_id', '=', 't.type_id')
            ->leftJoin('users as u', 'f.fosa_by', '=', 'u.id')
            ->select([
                'f.fosa_id',
                'm.member_name',
                't.type_name as fosa_type_name',
                'f.fosa_amount_paying',
                'f.fosa_description',
                'f.fosa_doc_no',
                'f.fosa_period',
                'f.fosa_date_paid',
                'u.name as entered_by_name',
            ])
            ->orderBy('f.fosa_transdate', 'desc');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $term = $this->search;
                $q->where('m.member_name', 'like', "%{$term}%")
                    ->orWhere('m.member_national_id', 'like', "%{$term}%")
                    ->orWhere('m.member_phone_no', 'like', "%{$term}%")
                    ->orWhere('f.fosa_doc_no', 'like', "%{$term}%")
                    ->orWhere('f.fosa_description', 'like', "%{$term}%")
                    ->orWhere('f.fosa_period', 'like', "%{$term}%")
                    ->orWhere('u.name', 'like', "%{$term}%");
            });
        }

        $records = $query->limit(2000)->get();

        return view('exports.fosa-transactions', ['records' => $records]);
    }
}