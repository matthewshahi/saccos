<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
       $validator = Validator::make($request->all(), [
    'type_name'   => 'required|string|max:50|unique:sacco_fosa_types,type_name',
    'type_prefix' => 'required|string|size:2|unique:sacco_fosa_types,type_prefix|not_in:CA,SH,LN,RF',

    'expected_amount' => 'nullable|numeric|min:0',
    'expected_period' => 'nullable|in:one_time,daily,weekly,monthly,yearly',
]);

        $validator->after(function ($v) use ($request) {
            $amountRaw = $request->input('expected_amount');
            $period    = $request->input('expected_period');

            $amountProvided = ($amountRaw !== null && $amountRaw !== '');

            // If one is set, require the other
            if ($amountProvided && !$period) {
                $v->errors()->add('expected_period', 'Select the expected period when an expected amount is provided.');
            }
            if ($period && !$amountProvided) {
                $v->errors()->add('expected_amount', 'Enter the expected amount when an expected period is selected.');
            }
        });

        $validator->validate();

        $expectedAmount = $request->input('expected_amount');
        $expectedAmount = ($expectedAmount === '' || $expectedAmount === null) ? null : (float) $expectedAmount;

        DB::table('sacco_fosa_types')->insert([
            'type_name'   => ucfirst($request->type_name),
            'type_prefix' => strtoupper($request->type_prefix),
            'type_active' => 'Y',
            'type_default'=> 'N',

            // ✅ save nullable fields
            'expected_amount' => $expectedAmount,
            'expected_period' => $request->input('expected_period') ?: null,

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
                'type_default' => 'N',
                'expected_amount' => null,
'expected_period' => null,
                'created_by'  => Auth::id() ?? 1, // fallback to system/admin
                'created_ip'  => $request->ip(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function edit($id)
{
    $rec = DB::table('sacco_fosa_types')->where('type_id', $id)->first();

    if (!$rec) {
        return redirect()
            ->route('fosa.index')
            ->with('error', 'Record not found.');
    }

    return view('fosa.edit', compact('rec'));
}
 public function update($id, Request $request)
{
    $rec = DB::table('sacco_fosa_types')->where('type_id', $id)->first();

    if (!$rec) {
        return redirect()
            ->route('fosa.index')
            ->with('error', 'Record not found.');
    }

    try {
        // ✅ Validation
        if (strtoupper($rec->type_prefix) === 'RF') {
            $request->validate([
                'expected_amount' => 'nullable|numeric|min:0',
                'expected_period' => 'nullable|in:one_time,daily,weekly,monthly,yearly',
            ]);
        } else {
            $request->validate([
                'type_name' => 'required|string|max:50|unique:sacco_fosa_types,type_name,' . $id . ',type_id',

                'expected_amount' => 'nullable|numeric|min:0',
                'expected_period' => 'nullable|in:one_time,daily,weekly,monthly,yearly',
            ]);
        }

        // ✅ Pair validation (if one is set, the other must be set)
        $amountRaw = $request->input('expected_amount');
        $period    = $request->input('expected_period');

        $amountProvided = ($amountRaw !== null && $amountRaw !== '');

        if ($amountProvided && !$period) {
            return back()->withInput()->with('error', 'Select Expected Period when Expected Amount is provided.');
        }
        if ($period && !$amountProvided) {
            return back()->withInput()->with('error', 'Enter Expected Amount when Expected Period is selected.');
        }

        // Normalize blanks -> null
        $expectedAmount = ($amountRaw === '' || $amountRaw === null) ? null : (float) $amountRaw;
        $expectedPeriod = ($period === '' || $period === null) ? null : $period;

        $update = [
            'expected_amount' => $expectedAmount,
            'expected_period' => $expectedPeriod,

            'updated_by' => Auth::id(),
            'updated_ip' => $request->ip(),
            'updated_at' => now(),
        ];

        // ✅ Only allow name updates if NOT RF
        if (strtoupper($rec->type_prefix) !== 'RF') {
            $update['type_name'] = ucfirst($request->type_name);
        }

        DB::table('sacco_fosa_types')
            ->where('type_id', $id)
            ->update($update);

        return redirect()
            ->route('fosa.index')
            ->with('success', 'FOSA Type updated successfully.');
    } catch (\Exception $e) {
        return back()
            ->withInput()
            ->with('error', 'Failed to update FOSA Type: ' . $e->getMessage());
    }
}
}
