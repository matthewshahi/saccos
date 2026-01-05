<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FosaMembersReportController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('sacco_fosas')
            ->join('sacco_members', 'sacco_fosas.fosa_member_id', '=', 'sacco_members.member_id')
            ->join('sacco_department', 'sacco_members.member_dept', '=', 'sacco_department.department_id')
            ->join('sacco_company', 'sacco_department.department_company_id', '=', 'sacco_company.company_id')
            ->leftJoin('sacco_fosa_types', 'sacco_fosas.fosa_type_id', '=', 'sacco_fosa_types.type_id')
            ->where('sacco_members.member_deleted', '<>', 'Y');

        /* ---------------- Filters ---------------- */

        if ($request->filled('fosa_type_id')) {
            $query->where('sacco_fosas.fosa_type_id', $request->fosa_type_id);
        }

        if ($request->filled('period_from')) {
            $query->where('sacco_fosas.fosa_period', '>=', $request->period_from);
        }

        if ($request->filled('period_to')) {
            $query->where('sacco_fosas.fosa_period', '<=', $request->period_to);
        }

        if ($request->filled('company_id')) {
            $query->where('sacco_company.company_id', $request->company_id);
        }

        if ($request->filled('department_id')) {
            $query->where('sacco_department.department_id', $request->department_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('sacco_members.member_name', 'like', "%{$search}%");
        }

        /* -------- Enforce ONE ROW PER MEMBER -------- */

        $query->groupBy(
            'sacco_members.member_id',
            'sacco_members.member_name',
            'sacco_department.department_name',
            'sacco_company.company_name'
        );

        /* ---------------- Select ---------------- */

        $query->select(
            'sacco_members.member_id',
            'sacco_members.member_name',
            'sacco_department.department_name',
            'sacco_company.company_name',
            DB::raw('COUNT(sacco_fosas.fosa_id) AS txn_count'),
            DB::raw('SUM(sacco_fosas.fosa_amount) AS total_fosa_amount'),
            DB::raw('MAX(sacco_fosas.fosa_date_paid) AS last_txn_date')
        );

        $query->orderBy('sacco_members.member_name');

        $records = $query->paginate(25)->withQueryString();

        $fosaTypes = DB::table('sacco_fosa_types')->orderBy('type_name')->get();

        return view('reports.fosa_members.index', compact('records', 'fosaTypes'));
    }
}
