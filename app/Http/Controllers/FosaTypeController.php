<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FosaTypeController extends Controller
{
     public function index(Request $request)
    {
        // ✅ Ensure "Registration Fee (RF)" always exists
        $this->ensureRegistrationFeeExists($request);
        
        
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
        try {
            $request->validate([
    'type_name'   => 'required|string|max:50|unique:sacco_fosa_types,type_name',
    'type_prefix' => 'required|string|size:2|unique:sacco_fosa_types,type_prefix|not_in:CA,SH,LN,RF',
]);

            DB::table('sacco_fosa_types')->insert([
                'type_name'   => ucfirst($request->type_name),
                'type_prefix' => strtoupper($request->type_prefix),
                'type_active' => 'Y',
                'type_default'=> 'N',
                'created_by'  => Auth::id(),
                'created_ip'  => $request->ip(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return redirect()
                ->route('fosa.index')
                ->with('success', 'FOSA Type added successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to add FOSA Type: ' . $e->getMessage());
        }
    }

    // Toggle Active/Inactive
    public function toggle($id, Request $request)
    {
        $record = DB::table('sacco_fosa_types')->where('type_id', $id)->first();

        if (!$record) {
            return redirect()
                ->route('fosa.index')
                ->with('error', 'Record not found.');
        }

        try {
            $newStatus = $record->type_active === 'Y' ? 'N' : 'Y';

            DB::table('sacco_fosa_types')
                ->where('type_id', $id)
                ->update([
                    'type_active' => $newStatus,
                    'updated_by'  => Auth::id(),
                    'updated_ip'  => $request->ip(),
                    'updated_at'  => now(),
                ]);

            return redirect()
                ->route('fosa.index')
                ->with('success', 'Status updated.');
        } catch (\Exception $e) {
            return redirect()
                ->route('fosa.index')
                ->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }
private function ensureRegistrationFeeExists(Request $request)
{
    $exists = DB::table('sacco_fosa_types')
        ->where('type_prefix', 'RF')
        ->exists();

    if (!$exists) {
        DB::table('sacco_fosa_types')->insert([
            'type_name'   => 'Registration Fee',
            'type_prefix' => 'RF',
            'type_active' => 'Y',
            'type_default'=> 'N',
            'created_by'  => Auth::id() ?? 1, // fallback to system/admin
            'created_ip'  => $request->ip(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}

}