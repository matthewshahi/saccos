<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index()
    {
        $loans = DB::table('loans')->get();
        return view('loans.index', ['loans' => $loans]);
    }

    public function show($id)
    {
        $loan = DB::table('loans')->find($id);
        return view('loans.show', ['loan' => $loan]);
    }
}
