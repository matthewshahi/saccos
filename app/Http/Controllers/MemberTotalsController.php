<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberTotalsController extends Controller
{
    public function recalculateAll()
    {
    
        // Step 1: Reset totals to zero
        DB::table('sacco_members')->update([
            'member_total_share' => 0,
            'member_total_fosa' => 0,
            'member_total_share_capital' => 0,
        ]);

        // Step 2: Update member_total_share from sacco_shares
        $shares = DB::table('sacco_shares')
            ->select('share_member_id', DB::raw('SUM(share_amount_paying) as total'))
            ->groupBy('share_member_id')
            ->get();

        foreach ($shares as $record) {
            DB::table('sacco_members')
                ->where('member_id', $record->share_member_id)
                ->update(['member_total_share' => $record->total]);
        }

        // Step 3: Update member_total_fosa from sacco_fosas
        $fosas = DB::table('sacco_fosas')
            ->select('fosa_member_id', DB::raw('SUM(fosa_amount_paying) as total'))
            ->groupBy('fosa_member_id')
            ->get();

        foreach ($fosas as $record) {
            DB::table('sacco_members')
                ->where('member_id', $record->fosa_member_id)
                ->update(['member_total_fosa' => $record->total]);
        }

        // Step 4: Update member_total_share_capital from sacco_capital_shares
        $capitalShares = DB::table('sacco_capital_shares')
            ->select('share_capitalmember_id', DB::raw('SUM(share_capitalamount_paying) as total'))
            ->groupBy('share_capitalmember_id')
            ->get();

        foreach ($capitalShares as $record) {
            DB::table('sacco_members')
                ->where('member_id', $record->share_capitalmember_id)
                ->update(['member_total_share_capital' => $record->total]);
        }

        // 🟢 Instead of redirect, just dump summary to the screen
    dd([
        'shares_updated'   => $shares->count(),
        'fosas_updated'    => $fosas->count(),
        'capital_updated'  => $capitalShares->count(),
        'message'          => 'All member totals have been recalculated successfully.'
    ]);
    }
}