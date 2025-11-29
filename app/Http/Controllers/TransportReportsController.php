<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransportReportsController extends Controller
{
    /**
     * TRANSPORT — MPESA COLLECTION REPORT
     *
     * Route:
     *  /reports/transport/mpesa
     * Name:
     *  reports.transport.mpesa
     */
    public function mpesa(Request $request)
    {
        // ------------------------------
        // Filters – default: today only
        // ------------------------------
        $from = $request->input('from', date('Y-m-d'));
        $to   = $request->input('to', date('Y-m-d'));

        // ------------------------------
        // Fetch MPESA transport collections
        // ------------------------------
        $records = DB::table('sacco_matatus_collections')
            ->leftJoin('sacco_matatus_operators', 
                'sacco_matatus_collections.coll_operator_id', 
                '=', 
                'sacco_matatus_operators.id'
            )
            ->leftJoin('sacco_matatus_vehicles', 
                'sacco_matatus_collections.coll_vehicle_id', 
                '=', 
                'sacco_matatus_vehicles.id'
            )
            ->where('coll_mode', 'mpesa')
            ->whereBetween('coll_date', [$from, $to])
            ->select(
                'sacco_matatus_collections.*',
                'sacco_matatus_operators.full_name AS operator_name',
                'sacco_matatus_operators.phone AS operator_phone',
                'sacco_matatus_vehicles.vehicles_registration_number AS vehicle_reg'
            )
            ->orderBy('coll_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // ------------------------------
        // Total MPESA Amount
        // ------------------------------
        $total = $records->sum('coll_amount');

        return view('reports.transport.mpesa', compact('records', 'from', 'to', 'total'));
    }
}
