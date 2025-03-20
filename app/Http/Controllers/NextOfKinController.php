<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NextOfKinController extends Controller
{
    public function index()
    {
        $nextOfKin = DB::table('sacco_next_of_kin')
            ->where('kin_deleted', 0)
            ->get();

        return view('nextofkin.index', compact('nextOfKin'));
    }

    public function add()
    {
        return view('nextofkin.add');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kin_member_id' => 'required',
            'kin_names' => 'required|string|max:255',
            'kin_address' => 'nullable|string|max:255',
            'kin_national_id' => 'required|string|max:20',
            'kin_percent' => 'required|numeric|min:0|max:100',
            'kin_relationship' => 'required|string|max:50',
        ]);

        DB::table('sacco_next_of_kin')->insert([
            'kin_member_id' => $request->kin_member_id,
            'kin_names' => $request->kin_names,
            'kin_address' => $request->kin_address,
            'kin_national_id' => $request->kin_national_id,
            'kin_percent' => $request->kin_percent,
            'kin_relationship' => $request->kin_relationship,
            'kin_user_id' => auth()->user()->id,
            'kin_ip' => request()->ip(),
            'kin_transdate' => now(),
            'kin_deleted' => 0,
        ]);

        return redirect()->route('nextofkin.list')->with('success', 'Next of Kin added successfully');
    }

    public function edit($id)
    {
        $nextOfKin = DB::table('sacco_next_of_kin')->where('kin_id', $id)->first();

        if (!$nextOfKin) {
            return redirect()->route('nextofkin.list')->with('error', 'Record not found.');
        }

        return view('nextofkin.edit', compact('nextOfKin'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'kin_names' => 'required|string|max:255',
            'kin_address' => 'nullable|string|max:255',
            'kin_national_id' => 'required|string|max:20',
            'kin_percent' => 'required|numeric|min:0|max:100',
            'kin_relationship' => 'required|string|max:50',
        ]);

        DB::table('sacco_next_of_kin')
            ->where('kin_id', $id)
            ->update([
                'kin_names' => $request->kin_names,
                'kin_address' => $request->kin_address,
                'kin_national_id' => $request->kin_national_id,
                'kin_percent' => $request->kin_percent,
                'kin_relationship' => $request->kin_relationship,
                'kin_user_id' => auth()->user()->id,
                'kin_ip' => request()->ip(),
                'kin_transdate' => now(),
            ]);

        return redirect()->route('nextofkin.list')->with('success', 'Next of Kin updated successfully');
    }
}