<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsShareController extends Controller
{
    public function fetchMemberContributionsData(Request $request)
    {
        $currentPeriod = Carbon::now()->format('Ym');

        $startInput = $request->input('start_period') ?? Carbon::now()->subMonths(11)->format('Ym');
        $endInput = $request->input('end_period') ?? $currentPeriod;

        $startPeriod = $this->sanitizePeriod($startInput, '200001');
        $endPeriod = $this->sanitizePeriod($endInput, $currentPeriod);

        // ✅ Generate period range
        $periods = [];
        try {
            $current = Carbon::createFromFormat('Ym', $startPeriod)->startOfMonth();
            $end = Carbon::createFromFormat('Ym', $endPeriod)->startOfMonth();

            while ($current <= $end) {
                $periods[] = $current->format('Ym');
                $current->addMonth();
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Invalid period range.');
        }

        // ✅ Load members
        $membersQuery = DB::table('sacco_members as m')
            ->select(
                'm.member_id',
                'm.member_name',
                'm.member_national_id',
                'm.member_phone_no',
                'c.company_name'
            )
            ->leftJoin('sacco_department as d', 'm.member_dept', '=', 'd.department_id')
            ->leftJoin('sacco_company as c', 'd.department_company_id', '=', 'c.company_id')
            ->where('m.member_deleted', 'N');

        if (in_array(strtolower($request->input('status')), ['y', 'n'])) {
            $membersQuery->where('m.member_active', strtoupper($request->input('status')));
        } else {
            $membersQuery->where('m.member_active', 'Y');
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $membersQuery->where(function ($q) use ($search) {
                $q->where('m.member_name', 'like', "%{$search}%")
                    ->orWhere('m.member_national_id', 'like', "%{$search}%")
                    ->orWhere('m.member_phone_no', 'like', "%{$search}%")
                    ->orWhere('c.company_name', 'like', "%{$search}%");
            });
        }

        $members = $membersQuery->orderBy('m.member_name')->get();

        // ✅ Fetch contributions
        $contributions = DB::table('sacco_shares')
            ->select('share_member_id', 'share_period', DB::raw('SUM(share_amount_paying) as amount'))
            ->whereBetween('share_period', [$startPeriod, $endPeriod])
            ->groupBy('share_member_id', 'share_period')
            ->get()
            ->groupBy('share_member_id');

        // ✅ Build result rows
        $rows = [];
        foreach ($members as $member) {
            $row = [
                'member_name' => strtoupper($member->member_name),
                'member_id_number' => strtoupper($member->member_national_id),
                'member_phone' => strtoupper($member->member_phone_no),
                'company_name' => strtoupper($member->company_name ?? '-'),
            ];

            $memberTotal = 0;

            foreach ($periods as $period) {
                $amount = 0;

                if (isset($contributions[$member->member_id])) {
                    foreach ($contributions[$member->member_id] as $c) {
                        if ($c->share_period == $period) {
                            $amount = $c->amount;
                            break;
                        }
                    }
                }

                $row[$period] = $amount;
                $memberTotal += $amount;
            }

            $row['total'] = $memberTotal;

            // ✅ Apply filter: only_blank means total must be zero
            if ($request->input('only_blank') == '1' && $memberTotal > 0) {
                continue;
            }

            $rows[] = $row;
        }

        return view('reports.sasra.member_contributions_report', [
            'periods' => $periods,
            'rows' => $rows,
            'search' => $request->input('search'),
            'start_period' => $startPeriod,
            'end_period' => $endPeriod,
            'status' => $request->input('status'),
            'only_blank' => $request->input('only_blank'),
        ]);
    }

    /**
     * Sanitize and correct invalid periods (expecting 6-digit string like 202506)
     */
  private function sanitizePeriod($period, $fallback)
{
    if (!preg_match('/^\d{6}$/', $period)) {
        return $fallback;
    }

    $year = intval(substr($period, 0, 4));
    $month = intval(substr($period, 4, 2));

    if ($month < 1 || $month > 12 || $year < 1900 || $year > 2100) {
        return $fallback;
    }

    return $period;
}

    /**
     * Optional: Generate full list of periods from a start
     */
    private function generateAllPeriodsBackTo($start = '2010-01')
    {
        $start = Carbon::parse($start)->startOfMonth();
        $now = Carbon::now()->startOfMonth();

        $periods = [];
        while ($start <= $now) {
            $periods[] = $start->format('Ym');
            $start->addMonth();
        }

        return array_reverse($periods);
    }

 
    public function index(Request $request)
{
    $currentPeriod = Carbon::now()->format('Ym');

    // Sanitize start and end periods
    $startInput = $request->input('start_period');
    $endInput = $request->input('end_period');

    $startPeriod = $this->sanitizePeriod($startInput, Carbon::now()->subMonths(11)->format('Ym'));
    $endPeriod = $this->sanitizePeriod($endInput, $currentPeriod);

    // Generate period range
    $periods = [];
    try {
        $start = Carbon::createFromFormat('Ym', $startPeriod)->startOfMonth();
        $end = Carbon::createFromFormat('Ym', $endPeriod)->startOfMonth();

        while ($start <= $end) {
            $periods[] = $start->format('Ym');
            $start->addMonth();
        }
    } catch (\Exception $e) {
        return back()->with('error', 'Invalid period range.');
    }

    // Build member query with filters
    $status = $request->input('status');
    $membersQuery = DB::table('sacco_members as m')
        ->select('m.member_id', 'm.member_name', 'm.member_national_id', 'm.member_phone_no', 'c.company_name')
        ->leftJoin('sacco_department as d', 'm.member_dept', '=', 'd.department_id')
        ->leftJoin('sacco_company as c', 'd.department_company_id', '=', 'c.company_id')
        ->where('m.member_deleted', 'N');

    if (in_array($status, ['Y', 'N'])) {
        $membersQuery->where('m.member_active', $status);
    }

    if ($request->filled('search_name')) {
        $membersQuery->where('m.member_name', 'like', '%' . $request->input('search_name') . '%');
    }

    if ($request->filled('search_phone')) {
        $membersQuery->where('m.member_phone_no', 'like', '%' . $request->input('search_phone') . '%');
    }

    if ($request->filled('search_company')) {
        $membersQuery->where('c.company_name', 'like', '%' . $request->input('search_company') . '%');
    }

    $members = $membersQuery->orderBy('m.member_name')->get();

    // Get contributions for the period
    $contributions = DB::table('sacco_shares')
        ->select('share_member_id', 'share_period')
        ->whereBetween('share_period', [$startPeriod, $endPeriod])
        ->groupBy('share_member_id', 'share_period')
        ->get()
        ->groupBy('share_member_id');

    // Build result rows
    $rows = [];
    foreach ($members as $member) {
        $paidPeriods = isset($contributions[$member->member_id])
            ? collect($contributions[$member->member_id])->pluck('share_period')->unique()->sort()->values()
            : collect();

        $first = $paidPeriods->first();
        $last = $paidPeriods->last();
        $paid = $paidPeriods->count();
        $total = count($periods);
        $missed = $total - $paid;

        $compliance = $total > 0 ? round(($paid / $total) * 100, 1) : 0;
        $statusIcon = $compliance >= 90 ? '✅' : ($compliance >= 50 ? '⚠️' : '❌');

        $rows[] = [
            'member_name' => strtoupper($member->member_name),
            'member_id_number' => $member->member_national_id,
            'member_phone' => $member->member_phone_no,
            'company_name' => strtoupper($member->company_name ?? '-'),
            'first_period' => $first ? Carbon::createFromFormat('Ym', $first)->format('M Y') : '-',
            'last_period' => $last ? Carbon::createFromFormat('Ym', $last)->format('M Y') : '-',
            'months_paid' => $paid,
            'months_missed' => $missed,
            'compliance_percent' => $compliance,
            'status' => $statusIcon,
        ];
    }

    return view('reports.sasra.member_compliance_summary', [
        'rows' => $rows,
        'start_period' => $startPeriod,
        'end_period' => $endPeriod,
        'total_months' => count($periods),
        'search_name' => $request->input('search_name'),
        'search_phone' => $request->input('search_phone'),
        'search_company' => $request->input('search_company'),
        'status' => $status,
    ]);
}

public function topShareholdingMembers(Request $request)
{
    $startPeriod ='000000';
    $endPeriod = $this->sanitizePeriod($request->input('end_period'), now()->format('Ym'));

    // Fetch top members with total shares
    $members = DB::table('sacco_shares as s')
        ->join('sacco_members as m', 's.share_member_id', '=', 'm.member_id')
        ->leftJoin('sacco_department as d', 'm.member_dept', '=', 'd.department_id')
        ->leftJoin('sacco_company as c', 'd.department_company_id', '=', 'c.company_id')
        ->select(
            'm.member_id',
            'm.member_name',
            'm.member_national_id',
            'm.member_phone_no',
            'c.company_name',
            DB::raw('SUM(s.share_amount_paying) as total_amount')
        )
        ->where('m.member_deleted', 'N')
        ->whereBetween('s.share_period', [$startPeriod, $endPeriod])
        ->groupBy(
            'm.member_id',
            'm.member_name',
            'm.member_national_id',
            'm.member_phone_no',
            'c.company_name'
        )
        ->orderByDesc('total_amount')
        ->limit(50)
        ->get();

    return view('reports.sasra.top_shareholding_members', [
        'members' => $members,
        'start_period' => $startPeriod,
        'end_period' => $endPeriod,
        'filter_company' => $request->input('search_company'),
    ]);

     

}
public function shareAgingReport(Request $request)
{
    $buckets = [
        '0-3' => [0, 3],
        '4-6' => [4, 6],
        '7-12' => [7, 12],
        '13-24' => [13, 24],
        '25-36' => [25, 36],
        '37+' => [37, 9999],
    ];

    $today = Carbon::now();

    $query = DB::table('sacco_shares as s')
        ->join('sacco_members as m', 's.share_member_id', '=', 'm.member_id')
        ->leftJoin('sacco_department as d', 'm.member_dept', '=', 'd.department_id')
        ->leftJoin('sacco_company as c', 'd.department_company_id', '=', 'c.company_id')
        ->where('m.member_deleted', 'N')
        ->select(
            'm.member_id',
            'm.member_name',
            'm.member_national_id',
            'm.member_phone_no',
            'm.member_active',
            'c.company_name',
            DB::raw('MAX(s.share_period) as last_period'),
            DB::raw('SUM(s.share_amount_paying) as total_shares')
        )
        ->groupBy(
            'm.member_id',
            'm.member_name',
            'm.member_national_id',
            'm.member_phone_no',
            'm.member_active',
            'c.company_name'
        );

    // 🔍 Smart search
    if ($request->filled('search')) {
        $search = $request->input('search');
        $query->where(function ($q) use ($search) {
            $q->where('m.member_name', 'like', "%{$search}%")
              ->orWhere('m.member_national_id', 'like', "%{$search}%")
              ->orWhere('m.member_phone_no', 'like', "%{$search}%")
              ->orWhere('c.company_name', 'like', "%{$search}%");
        });
    }

    // 🧾 Active/Inactive filter
    if ($request->filled('status') && in_array($request->status, ['active', 'inactive'])) {
        if ($request->status === 'active') {
            $query->where('m.member_active', 'Y');
        } else {
            $query->where(function ($q) {
                $q->whereNull('m.member_active')->orWhere('m.member_active', '!=', 'Y');
            });
        }
    }

    $shareAging = $query->get();

    $rows = [];
    foreach ($shareAging as $row) {
        $last = $row->last_period ? Carbon::createFromFormat('Ym', $row->last_period) : null;
        $monthsAgo = $last ? $last->diffInMonths($today) : null;

        $bucket = 'Unknown';
        foreach ($buckets as $label => [$min, $max]) {
            if ($monthsAgo !== null && $monthsAgo >= $min && $monthsAgo <= $max) {
                $bucket = $label;
                break;
            }
        }

        $rows[] = [
            'member_name' => strtoupper($row->member_name),
            'id_number' => $row->member_national_id,
            'phone' => $row->member_phone_no,
            'company_name' => strtoupper($row->company_name ?? '-'),
            'last_period' => $row->last_period ? Carbon::createFromFormat('Ym', $row->last_period)->format('M Y') : '-',
            'months_ago' => $monthsAgo,
            'bucket' => $bucket,
            'total_shares' => $row->total_shares,
            'status' => $row->member_active === 'Y' ? 'Active' : 'Inactive',
        ];
    }

    return view('reports.sasra.share_aging_report', [
        'rows' => $rows,
        'buckets' => array_keys($buckets),
        'search' => $request->input('search'),
        'status' => $request->input('status', 'all'),
    ]);
}

}