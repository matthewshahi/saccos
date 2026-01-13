<?php

namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FosaMembersReportController extends Controller
{
    public function index(Request $request)
    {
        $f = 'sacco_fosas';
        $m = 'sacco_members';
        $d = 'sacco_department';
        $c = 'sacco_company';
        $t = 'sacco_fosa_types';

        // -----------------------------
        // DEFAULTS (PERIOD IS KING)
        // -----------------------------
        $periodFrom = $request->filled('period_from') ? $request->period_from : '000000';
        $periodTo   = $request->filled('period_to')   ? $request->period_to   : '999999';

        // Dates are OPTIONAL (advanced)
        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        // -----------------------------
        // BASE QUERY
        // -----------------------------
        $query = DB::table($f)
            ->join($m, "{$f}.fosa_member_id", '=', "{$m}.member_id")
            ->join($d, "{$m}.member_dept", '=', "{$d}.department_id")
            ->join($c, "{$d}.department_company_id", '=', "{$c}.company_id")
            ->leftJoin($t, "{$f}.fosa_type_id", '=', "{$t}.type_id")
            ->where("{$m}.member_deleted", '<>', 'Y');

        // -----------------------------
        // FOSA TYPE FILTER (AUTHORITATIVE)
        // -----------------------------
        if ($request->filled('fosa_type_id')) {
            if ($request->fosa_type_id === '__NULL__') {
                $query->whereNull("{$f}.fosa_type_id");
            } else {
                $query->where("{$f}.fosa_type_id", $request->fosa_type_id);
            }
        }

        // -----------------------------
        // PERIOD FILTER (ALWAYS APPLIED)
        // -----------------------------
        $query->whereBetween("{$f}.fosa_period", [$periodFrom, $periodTo]);

        // -----------------------------
        // DATE FILTER (ONLY IF USER SETS)
        // -----------------------------
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $dateFrom ?: '1900-01-01';
            $to   = $dateTo   ?: Carbon::now()->toDateString();

            $query->whereBetween("{$f}.fosa_date_paid", [$from, $to]);
        }

        // -----------------------------
        // MEMBER NAME SEARCH
        // -----------------------------
        if ($request->filled('search')) {
            $query->where(
                "{$m}.member_name",
                'like',
                '%' . trim($request->search) . '%'
            );
        }

        // -----------------------------
        // DESCRIPTION SEARCH (DIAGNOSTIC)
        // -----------------------------
        if ($request->filled('description_search')) {
            $query->where(
                "{$f}.fosa_description",
                'like',
                '%' . trim($request->description_search) . '%'
            );
        }

        // -----------------------------
        // MEMBER-UNIQUE AGGREGATION
        // -----------------------------
        $query->groupBy(
            "{$m}.member_id",
            "{$m}.member_name",
            "{$m}.member_sacco_id",
            "{$m}.member_national_id",
            "{$m}.member_kra_pin",
            "{$m}.member_phone_no",
            "{$d}.department_name",
            "{$c}.company_name"
        );

        $query->select(
            "{$m}.member_id",
            "{$m}.member_name",
            "{$m}.member_sacco_id",
            "{$m}.member_national_id",
            "{$m}.member_kra_pin",
            "{$m}.member_phone_no",
            "{$d}.department_name",
            "{$c}.company_name",
            DB::raw("COUNT({$f}.fosa_id) AS txn_count"),
            DB::raw("SUM({$f}.fosa_amount_paying) AS total_fosa_amount"),
            DB::raw("MAX({$f}.fosa_date_paid) AS last_txn_date")
        );

        $query->orderBy("{$m}.member_name", 'asc');

        $records = $query->paginate(400)->withQueryString();

        $fosaTypes = DB::table('sacco_fosa_types')
            ->where('type_active', 'Y')
            ->orderBy('type_name')
            ->get();

        return view('reports.fosa_members.index', compact(
            'records',
            'fosaTypes'
        ));
    }

    public function exportCsv(Request $request): StreamedResponse
{
    $f = 'sacco_fosas';
    $m = 'sacco_members';
    $d = 'sacco_department';
    $c = 'sacco_company';

    // Same filters as index()
    $periodFrom = $request->filled('period_from') ? $request->period_from : '000000';
    $periodTo   = $request->filled('period_to')   ? $request->period_to   : '999999';

    $query = DB::table($f)
        ->join($m, "{$f}.fosa_member_id", '=', "{$m}.member_id")
        ->join($d, "{$m}.member_dept", '=', "{$d}.department_id")
        ->join($c, "{$d}.department_company_id", '=', "{$c}.company_id")
        ->where("{$m}.member_deleted", '<>', 'Y')
        ->whereBetween("{$f}.fosa_period", [$periodFrom, $periodTo]);

    // FOSA type filter
    if ($request->filled('fosa_type_id')) {
        if ($request->fosa_type_id === '__NULL__') {
            $query->whereNull("{$f}.fosa_type_id");
        } else {
            $query->where("{$f}.fosa_type_id", $request->fosa_type_id);
        }
    }

    // Member name search
    if ($request->filled('search')) {
        $query->where("{$m}.member_name", 'like', '%' . trim($request->search) . '%');
    }

    // Description search (explicitly allowed)
    if ($request->filled('description_search')) {
        $query->where("{$f}.fosa_description", 'like', '%' . trim($request->description_search) . '%');
    }

    $query->groupBy(
        "{$m}.member_id",
        "{$m}.member_name",
        "{$m}.member_sacco_id",
        "{$m}.member_national_id",
        "{$m}.member_kra_pin",
        "{$m}.member_phone_no",
        "{$d}.department_name",
        "{$c}.company_name"
    );

    $query->select(
        "{$m}.member_name",
        "{$m}.member_sacco_id",
        "{$m}.member_national_id",
        "{$m}.member_kra_pin",
        "{$m}.member_phone_no",
        "{$d}.department_name",
        "{$c}.company_name",
        DB::raw("COUNT({$f}.fosa_id) AS transactions"),
        DB::raw("SUM({$f}.fosa_amount_paying) AS total_fosa"),
        DB::raw("MAX({$f}.fosa_date_paid) AS last_transaction")
    );

    $filename = 'fosa_members_' . now()->format('Ymd_His') . '.csv';

    return response()->streamDownload(function () use ($query) {
        $handle = fopen('php://output', 'w');

        // Header row
        fputcsv($handle, [
            'Member Name',
            'SACCO No',
            'National ID',
            'KRA PIN',
            'Phone',
            'Department',
            'Company',
            'Transactions',
            'Total FOSA',
            'Last Transaction'
        ]);

        $query->orderBy('member_name')->chunk(500, function ($rows) use ($handle) {
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->member_name,
                    $row->member_sacco_id,
                    $row->member_national_id,
                    $row->member_kra_pin,
                    $row->member_phone_no,
                    $row->department_name,
                    $row->company_name,
                    $row->transactions,
                    number_format($row->total_fosa, 2, '.', ''),
                    optional($row->last_transaction)
                        ? \Carbon\Carbon::parse($row->last_transaction)->format('d/m/Y H:i')
                        : ''
                ]);
            }
        });

        fclose($handle);
    }, $filename, [
        'Content-Type' => 'text/csv',
    ]);
}


}

