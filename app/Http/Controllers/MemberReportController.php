<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberReportController extends Controller
{
    public function index()
    {
        return view('members.report'); // Return the Blade view
    }

    public function fetchReportData(Request $request)
{
    $filters = $request->validate([
        'search' => 'nullable|string|max:255',
        'period' => 'nullable|string|size:6', // YYYYMM format
        'page' => 'nullable|integer|min:1',
        'member_active' => 'nullable|string|in:Y,N', // Active status filter
    ]);

    $search = $filters['search'] ?? null;
    $period = $filters['period'] ?? null;
    $page = $filters['page'] ?? 1;
    $memberActive = $filters['member_active'] ?? 'Y';

    $limit = 30; // Number of records per page
    $offset = ($page - 1) * $limit;

    // Fetch loan types
    $loanTypes = DB::table('sacco_loan_types')->pluck('loan_type_name', 'loan_type_id');

    // Fetch members with filters
    $membersQuery = DB::table('sacco_members')
        ->select('member_id', 'member_sacco_id', 'member_name', 'member_phone_no', 'member_kra_pin', 'member_active')
        ->when($search, function ($query, $search) {
            return $query->where(function ($q) use ($search) {
                $q->where('member_name', 'LIKE', "%{$search}%")
                    ->orWhere('member_phone_no', 'LIKE', "%{$search}%")
                    ->orWhere('member_sacco_id', 'LIKE', "%{$search}%");
            });
        })
        ->when($memberActive, function ($query, $memberActive) {
            return $query->where('member_active', $memberActive);
        })
        ->orderBy('member_name', 'asc')
        ->offset($offset)
        ->limit($limit);

    $members = $membersQuery->get();

    $reportData = [];
    $totals = [
        'total_shares' => 0,
        'total_capital' => 0,
        'total_fosa' => 0,
        'total_loans' => 0,
        'loan_type_totals' => [],
    ];

    foreach ($members as $member) {
        $memberData = [
            'member_id' => $member->member_sacco_id,
            'name' => $member->member_name,
            'phone' => $member->member_phone_no,
            'kra_pin' => $member->member_kra_pin,
            'active' => $member->member_active === 'Y' ? 'Active' : 'Inactive',
        ];

        // Calculate total shares
        $totalShares = DB::table('sacco_shares')
            ->where('share_member_id', $member->member_id)
            ->where('share_period', '<=', $period)
            ->sum('share_amount_paying');
        $memberData['total_shares'] = $totalShares;
        $totals['total_shares'] += $totalShares;

        // Calculate total capital
        $totalCapital = DB::table('sacco_capital_shares')
            ->where('share_capitalmember_id', $member->member_id)
            ->where('share_capitalperiod', '<=', $period)
            ->sum('share_capitalamount_paying');
        $memberData['total_capital'] = $totalCapital;
        $totals['total_capital'] += $totalCapital;

        // Calculate total FOSA
        $totalFOSA = DB::table('sacco_fosas')
            ->where('fosa_member_id', $member->member_id)
            ->where('fosa_period', '<=', $period)
            ->sum('fosa_amount_paying');
        $memberData['total_fosa'] = $totalFOSA;
        $totals['total_fosa'] += $totalFOSA;

        // Calculate loans by type and loan ID
        $outstandingLoans = DB::table('sacco_loans as l')
            ->leftJoin('sacco_loan_payments as p', 'l.loan_id', '=', 'p.loan_payments_loan_id')
            ->select(
                'l.loan_loan_type',
                'l.loan_id',
                DB::raw('SUM(p.loan_payments_amount) as total_payments'),
                'l.loan_amount'
            )
            ->where('l.loan_member', $member->member_id)
            ->where(function ($query) use ($period) {
                $query->whereNull('p.loan_payments_period') // Include loans with no payments yet
                    ->orWhere('p.loan_payments_period', '<=', $period);
            })
            ->groupBy('l.loan_loan_type', 'l.loan_id', 'l.loan_amount')
            ->get();

        $totalLoans = 0;
        foreach ($loanTypes as $loanTypeId => $loanTypeName) {
            $loanBalances = $outstandingLoans->filter(function ($loan) use ($loanTypeId) {
                return $loan->loan_loan_type == $loanTypeId;
            })->map(function ($loan) {
                return $loan->loan_amount - $loan->total_payments;
            });

            $loanBalanceSum = $loanBalances->sum();
            $memberData[$loanTypeName] = $loanBalanceSum;
            $totalLoans += $loanBalanceSum;

            // Accumulate loan type totals
            if (!isset($totals['loan_type_totals'][$loanTypeName])) {
                $totals['loan_type_totals'][$loanTypeName] = 0;
            }
            $totals['loan_type_totals'][$loanTypeName] += $loanBalanceSum;
        }

        $memberData['total_loans'] = $totalLoans;
        $totals['total_loans'] += $totalLoans;

        $reportData[] = $memberData;
    }

    return response()->json([
        'loanTypes' => $loanTypes,
        'data' => $reportData,
        'totals' => $totals,
        'hasMoreData' => count($members) === $limit,
    ]);
}
}