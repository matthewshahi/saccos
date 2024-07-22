<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $reports = DB::table('reports')->get();
        return view('reports.index', ['reports' => $reports]);
    }

    public function show($id)
    {
        $report = DB::table('reports')->find($id);
        return view('reports.show', ['report' => $report]);
    }
}
