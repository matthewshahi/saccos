<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FosaTypeController extends Controller
{
    // List all FOSA deposit types
    public function index()
    {
        $records = DB::table('sacco_fosa_types')->orderBy('type_id')->get();
        return view('fosa.index', compact('records'));
    }

    // Show add form
    public function create()
    {
        return view('fosa.create');
    }

    // Store new type
    public function store(Request $request)
    {
        $request->validate([
            'type_name' => 'required|string|max:50|unique:sacco_fosa_types,type_name',
        ]);

        DB::table('sacco_fosa_types')->insert([
            'type_name'   => ucfirst($request->type_name),
            'type_active' => 'Y',
            'type_default'=> 'N',
            'created_by'  => Auth::id(),
            'created_ip'  => $request->ip(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('fosa.index')->with('success', 'FOSA Type added successfully.');
    }

    // Toggle Active/Inactive
    public function toggle($id, Request $request)
    {
        $record = DB::table('sacco_fosa_types')->where('type_id', $id)->first();
        if (!$record) {
            return redirect()->route('fosa.index')->with('error', 'Record not found.');
        }

        $newStatus = $record->type_active === 'Y' ? 'N' : 'Y';

        DB::table('sacco_fosa_types')->where('type_id', $id)->update([
            'type_active' => $newStatus,
            'updated_by'  => Auth::id(),
            'updated_ip'  => $request->ip(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('fosa.index')->with('success', 'Status updated.');
    }
}