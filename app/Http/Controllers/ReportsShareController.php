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
}