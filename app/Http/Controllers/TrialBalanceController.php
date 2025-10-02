<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrialBalanceController extends Controller
{
    /**
     * Display the Trial Balance report
     */
    public function index(Request $request)
    {
        $period   = $request->input('period'); // YYYYmm format
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = DB::table('sacco_accounts_trans as t')
    ->join('sacco_sub_account as s', 't.accounts_trans_sub_account', '=', 's.sub_account_id')
    ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
    ->select(
        DB::raw("TRIM(m.main_account_code) as main_account_code"),
        DB::raw("TRIM(m.main_account_name) as main_account_name"),
        DB::raw("TRIM(m.main_account_type) as main_account_type"),
        DB::raw("TRIM(s.sub_account_code) as sub_account_code"),
        DB::raw("TRIM(s.sub_account_name) as sub_account_name"),
        DB::raw("
            CASE 
                WHEN SUM(t.accounts_trans_debit) - SUM(t.accounts_trans_credit) > 0 
                THEN SUM(t.accounts_trans_debit) - SUM(t.accounts_trans_credit) 
                ELSE 0 
            END as debit
        "),
        DB::raw("
            CASE 
                WHEN SUM(t.accounts_trans_credit) - SUM(t.accounts_trans_debit) > 0 
                THEN SUM(t.accounts_trans_credit) - SUM(t.accounts_trans_debit) 
                ELSE 0 
            END as credit
        ")
    )
    ->groupBy('m.main_account_code','m.main_account_name','m.main_account_type','s.sub_account_code','s.sub_account_name')
    ->orderByRaw("
        CASE 
            WHEN m.main_account_type LIKE 'ASSET%' THEN 1
            WHEN m.main_account_type LIKE 'LIABILITY%' THEN 2
            WHEN m.main_account_type LIKE 'CAPITAL%' THEN 3
            WHEN m.main_account_type LIKE 'INCOME%' THEN 4
            WHEN m.main_account_type LIKE 'EXPENSE%' THEN 5
            ELSE 6
        END
    ")
    ->orderBy('m.main_account_code')
    ->orderBy('s.sub_account_code');

        // ✅ Filter by period (YYYYmm)
        if ($period) {
            $query->where('t.accounts_trans_period', $period);
        }

        // ✅ Filter by date range
        if ($dateFrom && $dateTo) {
            $query->whereBetween(DB::raw("DATE(t.accounts_trans_dat_date)"), [$dateFrom, $dateTo]);
        }

        $records = $query->get();

        return view('reports.accounts.trial_balance', compact('records','period','dateFrom','dateTo'));
    }
}