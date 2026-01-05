<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FosaMembersReportController extends Controller
{
    private string $COL_ID      = 'fosa_id';
    private string $COL_MEMBER  = 'fosa_member_id';
    private string $COL_TYPE    = 'fosa_type_id';
    private string $COL_PERIOD  = 'fosa_period';
    private string $COL_DATE    = 'fosa_date_paid';
    private string $COL_AMOUNT  = 'fosa_amount_paying';

    public function index(Request $request)
    {
        $f = 'sacco_fosas';
        $m = 'sacco_members';
        $d = 'sacco_department';
        $c = 'sacco_company';
        $t = 'sacco_fosa_types';

        $query = DB::table($f)
            ->join($m, "{$f}.{$this->COL_MEMBER}", '=', "{$m}.member_id")
            ->join($d, "{$m}.member_dept", '=', "{$d}.department_id")
            ->join($c, "{$d}.department_company_id", '=', "{$c}.company_id")
            ->leftJoin($t, "{$f}.{$this->COL_TYPE}", '=', "{$t}.type_id")
            ->where("{$m}.member_deleted", '<>', 'Y');

        /* -------------------------------------------------
         | FOSA TYPE FILTER (CORRECT, NULL-SAFE)
         |--------------------------------------------------*/
        if ($request->filled('fosa_type_id')) {
            if ($request->fosa_type_id === '__NULL__') {
                // Explicitly request UNSORTED contributions
                $query->whereNull("{$f}.{$this->COL_TYPE}");
            } else {
                // Specific type only
                $query->where("{$f}.{$this->COL_TYPE}", $request->fosa_type_id);
            }
        }
        // else: no filter → include ALL (typed + NULL)

        /* -------------------------------------------------
         | PERIOD FILTERS
         |--------------------------------------------------*/
        if ($request->filled('period_from')) {
            $query->where("{$f}.{$this->COL_PERIOD}", '>=', $request->period_from);
        }

        if ($request->filled('period_to')) {
            $query->where("{$f}.{$this->COL_PERIOD}", '<=', $request->period_to);
        }

        /* -------------------------------------------------
         | MEMBER SEARCH
         |--------------------------------------------------*/
        if ($request->filled('search')) {
            $query->where("{$m}.member_name", 'like', '%' . trim($request->search) . '%');
        }

        /* -------------------------------------------------
         | MEMBER-UNIQUE AGGREGATION (NON-NEGOTIABLE)
         |--------------------------------------------------*/
        $query->groupBy(
            "{$m}.member_id",
            "{$m}.member_name",
            "{$d}.department_name",
            "{$c}.company_name"
        );

        $query->select(
            "{$m}.member_id",
            "{$m}.member_name",
            "{$d}.department_name",
            "{$c}.company_name",
            DB::raw("COUNT({$f}.{$this->COL_ID}) AS txn_count"),
            DB::raw("SUM({$f}.{$this->COL_AMOUNT}) AS total_fosa_amount"),
            DB::raw("MAX({$f}.{$this->COL_DATE}) AS last_txn_date")
        );

        /* -------------------------------------------------
         | SORTING
         |--------------------------------------------------*/
        $orderBy  = $request->get('order_by', 'member_name');
        $orderDir = $request->get('order_dir') === 'desc' ? 'desc' : 'asc';

        $allowedOrder = ['member_name', 'txn_count', 'total_fosa_amount', 'last_txn_date'];
        if (!in_array($orderBy, $allowedOrder, true)) {
            $orderBy = 'member_name';
        }

        $query->orderBy($orderBy, $orderDir);

        $records = $query->paginate(25)->withQueryString();

        $fosaTypes = DB::table('sacco_fosa_types')
            ->where('type_active', 'Y')
            ->orderBy('type_name')
            ->get();

        return view('reports.fosa_members.index', compact('records', 'fosaTypes'));
    }
}
