<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KinTypeController extends Controller
{
    public function index()
    {
        $kinTypes = DB::table('sacco_kin_type')
            ->where(function ($query) {
                $query->where('kin_type_deleted', 'N')
                      ->orWhere('kin_type_deleted', 0); // Handle both 'N' and 0 as "not deleted"
            })
            ->get();

        return view('kintype.index', compact('kinTypes'));
    }

    public function add()
    {
        return view('kintype.add');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kin_type_name' => 'required|string|max:255|unique:sacco_kin_type,kin_type_name,NULL,kin_type_id,kin_type_deleted,N',
            'kin_type_details' => 'nullable|string',
        ]);

        DB::table('sacco_kin_type')->insert([
            'kin_type_name' => $request->kin_type_name,
            'kin_type_details' => $request->kin_type_details,
            'kin_type_user_id' => auth()->user()->id,
            'kin_type_ip' => request()->ip(),
            'kin_type_transdate' => now(),
            'kin_type_deleted' => 'N', // Ensuring 'N' is set for active records
        ]);

        return redirect()->route('kintype.list')->with('success', 'Kin Type added successfully');
    }

    public function edit($id)
    {
        $kinType = DB::table('sacco_kin_type')->where('kin_type_id', $id)->first();

        if (!$kinType) {
            return redirect()->route('kintype.list')->with('error', 'Record not found.');
        }

        return view('kintype.edit', compact('kinType'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'kin_type_name' => 'required|string|max:255|unique:sacco_kin_type,kin_type_name,' . $id . ',kin_type_id,kin_type_deleted,N',
            'kin_type_details' => 'nullable|string',
        ]);

        DB::table('sacco_kin_type')
            ->where('kin_type_id', $id)
            ->update([
                'kin_type_name' => $request->kin_type_name,
                'kin_type_details' => $request->kin_type_details,
                'kin_type_user_id' => auth()->user()->id,
                'kin_type_ip' => request()->ip(),
                'kin_type_transdate' => now(),
            ]);

        return redirect()->route('kintype.list')->with('success', 'Kin Type updated successfully');
    }
}