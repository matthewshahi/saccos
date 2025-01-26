<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\LedgerExport; // Import the LedgerExport class
use Maatwebsite\Excel\Facades\Excel; // Import the Laravel Excel facade

class ReportLedgerController extends Controller
{
    public function reportsAccountsLedger(Request $request)
    {
        $startPeriod = $request->input('start_period', '000000');
        $endPeriod = $request->input('end_period', '999999');
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $search = $request->input('search', '');
        $orderField = $request->input('order_field', 'accounts_trans_period');
        $orderDirection = $request->input('order_direction', 'asc');
    
        // Build the base query (used for both pagination and totals)
        $baseQuery = DB::table('sacco_accounts_trans')
            ->leftJoin('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->whereBetween('sacco_accounts_trans.accounts_trans_period', [$startPeriod, $endPeriod])
            ->whereBetween('sacco_accounts_trans.accounts_trans_dat_date', [$startDate, $endDate]); // Add date range filter
    
        // Add search functionality
        if (!empty($search)) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('sacco_sub_account.sub_account_name', 'LIKE', "%$search%")
                    ->orWhere('sacco_main_account.main_account_code', 'LIKE', "%$search%")
                    ->orWhere('sacco_sub_account.sub_account_code', 'LIKE', "%$search%")
                    ->orWhere(DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code)"), 'LIKE', "%$search%")
                    ->orWhere('sacco_accounts_trans.accounts_trans_doc_no', 'LIKE', "%$search%")
                    ->orWhere('sacco_accounts_trans.accounts_trans_decription', 'LIKE', "%$search%");
            });
        }
    
        // Calculate totals for debits and credits (entire filtered dataset)
        $totals = $baseQuery->selectRaw('SUM(sacco_accounts_trans.accounts_trans_debit) as total_debit, SUM(sacco_accounts_trans.accounts_trans_credit) as total_credit')->first();
    
        // Calculate opening balance (excluding period filter but including date and search filter)
        $openingBalanceQuery = DB::table('sacco_accounts_trans')
            ->leftJoin('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
            ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
            ->where('sacco_accounts_trans.accounts_trans_period', '<', $startPeriod)
            ->where('sacco_accounts_trans.accounts_trans_dat_date', '<', $startDate); // Add date range filter for opening balance
    
        if (!empty($search)) {
            $openingBalanceQuery->where(function ($q) use ($search) {
                $q->where('sacco_sub_account.sub_account_name', 'LIKE', "%$search%")
                    ->orWhere('sacco_main_account.main_account_code', 'LIKE', "%$search%")
                    ->orWhere('sacco_sub_account.sub_account_code', 'LIKE', "%$search%")
                    ->orWhere(DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code)"), 'LIKE', "%$search%")
                    ->orWhere('sacco_accounts_trans.accounts_trans_doc_no', 'LIKE', "%$search%")
                    ->orWhere('sacco_accounts_trans.accounts_trans_decription', 'LIKE', "%$search%");
            });
        }
    
        $openingBalanceData = $openingBalanceQuery
            ->selectRaw('SUM(sacco_accounts_trans.accounts_trans_debit) as total_debit, SUM(sacco_accounts_trans.accounts_trans_credit) as total_credit')
            ->first();
    
        $openingBalanceDebit = $openingBalanceData->total_debit ?? 0;
        $openingBalanceCredit = $openingBalanceData->total_credit ?? 0;
    
        $openingBalance = (object) [
            'balance' => abs($openingBalanceDebit - $openingBalanceCredit),
            'type' => $openingBalanceDebit >= $openingBalanceCredit ? 'Debit' : 'Credit',
        ];
    
        // Clone the base query for paginated results and add ordering
        $transactionsQuery = clone $baseQuery;
        $transactionsQuery->select(
            'sacco_accounts_trans.*', 
            'sacco_sub_account.sub_account_code',
            'sacco_sub_account.sub_account_name',
            'sacco_main_account.main_account_code'
        )
        ->orderBy($orderField, $orderDirection);
    
        // Paginate results
        $transactions = $transactionsQuery->paginate(2000);
    
        // Determine active period
        $activePeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->value('period_name');
    
        return view('reports.accounts.accounts_ledger', compact('transactions', 'activePeriod', 'totals', 'openingBalance'));
    }

//     public function reportsAccountsLedger(Request $request)
// {
//     $startPeriod = $request->input('start_period', '000000');
//     $endPeriod = $request->input('end_period', '999999');
//     $search = $request->input('search', '');
//     $orderField = $request->input('order_field', 'accounts_trans_period');
//     $orderDirection = $request->input('order_direction', 'asc');

//     // Build the base query (used for both pagination and totals)
//     $baseQuery = DB::table('sacco_accounts_trans')
//         ->leftJoin('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
//         ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
//         ->whereBetween('sacco_accounts_trans.accounts_trans_period', [$startPeriod, $endPeriod]);

//     // Add search functionality
//     if (!empty($search)) {
//         $baseQuery->where(function ($q) use ($search) {
//             $q->where('sacco_sub_account.sub_account_name', 'LIKE', "%$search%")
//                 ->orWhere('sacco_main_account.main_account_code', 'LIKE', "%$search%")
//                 ->orWhere('sacco_sub_account.sub_account_code', 'LIKE', "%$search%")
//                 ->orWhere(DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code)"), 'LIKE', "%$search%")
//                 ->orWhere('sacco_accounts_trans.accounts_trans_doc_no', 'LIKE', "%$search%")
//                 ->orWhere('sacco_accounts_trans.accounts_trans_decription', 'LIKE', "%$search%");
//         });
//     }

//     // Calculate totals for debits and credits (entire filtered dataset)
//     $totals = $baseQuery->selectRaw('SUM(sacco_accounts_trans.accounts_trans_debit) as total_debit, SUM(sacco_accounts_trans.accounts_trans_credit) as total_credit')->first();

//     // Calculate opening balance (excluding period filter but including search filter)
//     $openingBalanceQuery = DB::table('sacco_accounts_trans')
//         ->leftJoin('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
//         ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id');

//     if (!empty($search)) {
//         $openingBalanceQuery->where(function ($q) use ($search) {
//             $q->where('sacco_sub_account.sub_account_name', 'LIKE', "%$search%")
//                 ->orWhere('sacco_main_account.main_account_code', 'LIKE', "%$search%")
//                 ->orWhere('sacco_sub_account.sub_account_code', 'LIKE', "%$search%")
//                 ->orWhere(DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code)"), 'LIKE', "%$search%")
//                 ->orWhere('sacco_accounts_trans.accounts_trans_doc_no', 'LIKE', "%$search%")
//                 ->orWhere('sacco_accounts_trans.accounts_trans_decription', 'LIKE', "%$search%");
//         });
//     }

//     $openingBalanceData = $openingBalanceQuery
//         ->where('sacco_accounts_trans.accounts_trans_period', '<', $startPeriod)
//         ->selectRaw('SUM(sacco_accounts_trans.accounts_trans_debit) as total_debit, SUM(sacco_accounts_trans.accounts_trans_credit) as total_credit')
//         ->first();

//     $openingBalanceDebit = $openingBalanceData->total_debit ?? 0;
//     $openingBalanceCredit = $openingBalanceData->total_credit ?? 0;

//     $openingBalance = (object) [
//         'balance' => abs($openingBalanceDebit - $openingBalanceCredit),
//         'type' => $openingBalanceDebit >= $openingBalanceCredit ? 'Debit' : 'Credit',
//     ];

//     // Clone the base query for paginated results and add ordering
//     $transactionsQuery = clone $baseQuery;
//     $transactionsQuery->select(
//         'sacco_accounts_trans.*', 
//         'sacco_sub_account.sub_account_code',
//         'sacco_sub_account.sub_account_name',
//         'sacco_main_account.main_account_code'
//     )
//     ->orderBy($orderField, $orderDirection);

//     // Paginate results
//     $transactions = $transactionsQuery->paginate(50);

//     // Determine active period
//     $activePeriod = DB::table('sacco_period')
//         ->where('period_active', 'Y')
//         ->value('period_name');

//     return view('reports.accounts.accounts_ledger', compact('transactions', 'activePeriod', 'totals', 'openingBalance'));
// }
//     public function reportsAccountsLedger(Request $request)
// {
//     $startPeriod = $request->input('start_period', '000000');
//     $endPeriod = $request->input('end_period', '999999');
//     $search = $request->input('search', '');
//     $orderField = $request->input('order_field', 'accounts_trans_period');
//     $orderDirection = $request->input('order_direction', 'asc');

//     // Build the base query (used for both pagination and totals)
//     $baseQuery = DB::table('sacco_accounts_trans')
//         ->leftJoin('sacco_sub_account', 'sacco_accounts_trans.accounts_trans_sub_account', '=', 'sacco_sub_account.sub_account_id')
//         ->leftJoin('sacco_main_account', 'sacco_sub_account.sub_account_main_account', '=', 'sacco_main_account.main_account_id')
//         ->whereBetween('sacco_accounts_trans.accounts_trans_period', [$startPeriod, $endPeriod]);

//     // Add search functionality
//     if (!empty($search)) {
//         $baseQuery->where(function ($q) use ($search) {
//             $q->where('sacco_sub_account.sub_account_name', 'LIKE', "%$search%")
//                 ->orWhere('sacco_main_account.main_account_code', 'LIKE', "%$search%")
//                 ->orWhere('sacco_sub_account.sub_account_code', 'LIKE', "%$search%")
//                 ->orWhere(DB::raw("CONCAT(sacco_main_account.main_account_code, '/', sacco_sub_account.sub_account_code)"), 'LIKE', "%$search%")
//                 ->orWhere('sacco_accounts_trans.accounts_trans_doc_no', 'LIKE', "%$search%")
//                 ->orWhere('sacco_accounts_trans.accounts_trans_decription', 'LIKE', "%$search%");
//         });
//     }

//     // Calculate totals for debits and credits (entire filtered dataset)
//     $totals = $baseQuery->selectRaw('SUM(sacco_accounts_trans.accounts_trans_debit) as total_debit, SUM(sacco_accounts_trans.accounts_trans_credit) as total_credit')->first();

//     // Clone the base query for paginated results and add ordering
//     $transactionsQuery = clone $baseQuery;
//     $transactionsQuery->select(
//         'sacco_accounts_trans.*', 
//         'sacco_sub_account.sub_account_code',
//         'sacco_sub_account.sub_account_name',
//         'sacco_main_account.main_account_code'
//     )
//     ->orderBy($orderField, $orderDirection);

//     // Paginate results
//     $transactions = $transactionsQuery->paginate(50);

//     // Determine active period
//     $activePeriod = DB::table('sacco_period')
//         ->where('period_active', 'Y')
//         ->value('period_name');

//     return view('reports.accounts.accounts_ledger', compact('transactions', 'activePeriod', 'totals'));
// }
public function updateTransaction(Request $request, $id)
{
    $request->validate([
        'accounts_trans_cash_in_already' => 'nullable|string|in:Y,N',
        'accounts_trans_reconsiled' => 'nullable|string|in:Y,N',
        'accounts_trans_payment_type' => 'nullable|string|max:255',
        'accounts_trans_reconsiled_comments' => 'nullable|string|max:1000',
    ]);

    // Find the transaction
    $transaction = DB::table('sacco_accounts_trans')->where('accounts_trans_id', $id)->first();

    if (!$transaction) {
        return redirect()->back()->withErrors(['error' => 'Transaction not found.']);
    }

    // Check if the period is active
    $period = DB::table('sacco_period')
        ->where('period_name', $transaction->accounts_trans_period)
        ->where('period_active', 'Y')
        ->first();

    if (!$period) {
        return redirect()->back()->withErrors(['error' => 'This transaction cannot be updated because the period is closed.']);
    }

    // Update the transaction
    DB::table('sacco_accounts_trans')->where('accounts_trans_id', $id)->update([
        'accounts_trans_cash_in_already' => $request->input('accounts_trans_cash_in_already'),
        'accounts_trans_reconsiled' => $request->input('accounts_trans_reconsiled'),
        'accounts_trans_payment_type' => $request->input('accounts_trans_payment_type'),
        'accounts_trans_reconsiled_comments' => $request->input('accounts_trans_reconsiled_comments'),
        'accounts_trans_reconsiled_transdate' => now(),
    ]);

    // Redirect back with filters preserved
    return redirect()->route('reports.accounts.ledger', [
        'start_period' => $request->input('start_period', '000000'),
        'end_period' => $request->input('end_period', '999999'),
        'search' => $request->input('search', ''),
        'order_field' => $request->input('order_field', 'accounts_trans_period'),
        'order_direction' => $request->input('order_direction', 'asc'),
    ])->with('success', 'Transaction updated successfully.');
}
public function export(Request $request)
{
    $data = $this->reportsAccountsLedger($request); // Fetch the filtered data
    return Excel::download(new LedgerExport($data->getData()), 'accounts_ledger.xlsx');
}
}