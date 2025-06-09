<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsShareComplianceController extends Controller
{
    public function index(Request $request)
    {
        $currentPeriod = Carbon::now()->format('Ym');
        $startPeriod = $request->input('start_period') ?? Carbon::now()->subMonths(11)->format('Ym');
        $endPeriod = $request->input('end_period') ?? $currentPeriod;

        // Sanitize
        $startPeriod = $this->sanitizePeriod($startPeriod, '202001');
        $endPeriod = $this->sanitizePeriod($endPeriod, $currentPeriod);

        // Generate period range
        $periods = [];
        $start = Carbon::createFromFormat('Ym', $startPeriod)->startOfMonth();
        $end = Carbon::createFromFormat('Ym', $endPeriod)->startOfMonth();
        while ($start <= $end) {
            $periods[] = $start->format('Ym');
            $start->addMonth();
        }

        // Load members
        $members = DB::table('sacco_members')
            ->select('member_id', 'member_name', 'member_national_id', 'member_phone_no')
            ->where('member_deleted', 'N')
            ->where('member_active', 'Y')
            ->orderBy('member_name')
            ->get();

        // Load contributions
        $contributions = DB::table('sacco_shares')
            ->select('share_member_id', 'share_period')
            ->whereBetween('share_period', [$startPeriod, $endPeriod])
            ->groupBy('share_member_id', 'share_period')
            ->get()
            ->groupBy('share_member_id');

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

            $status = $compliance >= 90 ? '✅' : ($compliance >= 50 ? '⚠️' : '❌');

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
    'status' => $status,
];
        }

        return view('reports.sasra.member_compliance_summary', [
            'rows' => $rows,
            'start_period' => $startPeriod,
            'end_period' => $endPeriod,
            'total_months' => count($periods),
        ]);
    }

    private function sanitizePeriod($period, $fallback)
    {
        if (!preg_match('/^\d{6}$/', $period)) return $fallback;
        $year = intval(substr($period, 0, 4));
        $month = intval(substr($period, 4, 2));
        return ($month >= 1 && $month <= 12 && $year >= 1900 && $year <= 2100) ? $period : $fallback;
    }
}
