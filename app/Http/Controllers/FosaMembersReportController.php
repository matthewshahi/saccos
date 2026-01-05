<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FosaMembersReportController extends Controller
{
    // Column mapping (explicit & centralized)
    private string $COL_ID     = 'fosa_id';
    private string $COL_MEMBER = 'fosa_member_id';
    private string $COL_TYPE   = 'fosa_type_id';
    private string $COL_PERIOD = 'fosa_period';
    private string $COL_DATE   = 'fosa_date_paid';
    private string $COL_AMOUNT = 'fosa_amount_paying';

    public function index(Request $request)
    {
        // Table aliases
        $f = 'sacco_fosas';
        $m = 'sacco_members';
        $d = 'sacco_department';
        $c = 'sacco_company';
        $t = 'sacco_fosa_types';

        /*
        |--------------------------------------------------------------------------
        | DEFAULTS
        |--------------------------------------------------------------------------
        | Period: include all (YYYYMM)
        | Date  : current month-to-date
        */
        $periodFrom = $request->filled('period_from') ? $request->period_from : '000000';
        $periodTo   = $request->filled('period_to')   ? $request->period_to   : '999999';

        $defaultDateFrom = Carbon::now()->startOfMonth()->toDateString();
        $defaultDateTo   = Carbon::now()->toDateString();

        $dateFrom = $request->filled('date_from') ? $request->date_from : $defaultDateFrom;
        $dateTo   = $request->filled('date_to')   ? $request->date_to   : $defaultDateTo;

        /*
        |--------------------------------------------------------------------------
        | BASE QUERY (ledger-first)
        |--------------------------------------------------------------------------
        */
        $query = DB::table($f)
            ->join($m, "{$f}.{$this->COL_MEMBER}", '=', "{$m}.member_id")
            ->join($d, "{$m}.member_dept", '=', "{$d}.department_id")
            ->join($c, "{$d}.department_company_id", '=', "{$c}.company_id")
            ->leftJoin($t, "{$f}.{$this->COL_TYPE}", '=', "{$t}.type_id")
            ->where("{$m}.member_deleted", '<>', 'Y');

        /*
        |--------------------------------------------------------------------------
        | FOSA TYPE FILTER (NULL-SAFE)
        |--------------------------------------------------------------------------
        | - empty: ALL (typed + NULL)
        | - value: specific type
        | - '__NULL__': UNSORTED only
        */
        if ($request->filled('fosa_type_id')) {
            if ($request->fosa_type_id === '__NULL__') {
                $query->whereNull("{$f}.{$this->COL_TYPE}");
            } else {
                $query->where("{$f}.{$this->COL_TYPE}", $request->fosa_type_id);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PERIOD FILTER (ALWAYS APPLIED)
        |--------------------------------------------------------------------------
        */
        $query->whereBetween("{$f}.{$this->COL_PERIOD}", [$periodFrom, $periodTo]);

        /*
        |--------------------------------------------------------------------------
        | DATE FILTER (DEFAULT: THIS MONTH)
        |--------------------------------------------------------------------------
        */
        $query->whereDate("{$f}.{$this->COL_DATE}", '>=', $dateFrom)
              ->whereDate("{$f}.{$this->COL_DATE}", '<=', $dateTo);

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */
        if ($request->filled('search')) {
            $query->where("{$m}.member_name", 'like', '%' . trim($request->search) . '%');
        }

        /*
        |--------------------------------------------------------------------------
        | MEMBER-UNIQUE AGGREGATION (NON-NEGOTIABLE)
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */
        $orderBy  = $request->get('order_by', 'member_name');
        $orderDir = $request->get('order_dir') === 'desc' ? 'desc' : 'asc';
        $allowed  = ['member_name', 'txn_count', 'total_fosa_amount', 'last_txn_date'];

        if (!in_array($orderBy, $allowed, true)) {
            $orderBy = 'member_name';
        }

        $query->orderBy($orderBy, $orderDir);

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        $records = $query->paginate(25)->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | FILTER DATA
        |--------------------------------------------------------------------------
        */
        $fosaTypes = DB::table('sacco_fosa_types')
            ->where('type_active', 'Y')
            ->orderBy('type_name')
            ->get();

        return view('reports.fosa_members.index', compact(
            'records',
            'fosaTypes',
            'periodFrom',
            'periodTo',
            'dateFrom',
            'dateTo'
        ));
    }
}
