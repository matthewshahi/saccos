<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = DB::table('transactions')->get();
        return view('transactions.index', ['transactions' => $transactions]);
    }

    public function show($id)
    {
        $transaction = DB::table('transactions')->find($id);
        return view('transactions.show', ['transaction' => $transaction]);
    }
}
