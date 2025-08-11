<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperatorVehicleAssignmentController extends Controller
{
    
public function index(Request $request)
{
    $query = DB::table('sacco_matatus_operator_vehicle_assignments as va')
        ->leftJoin('sacco_matatus_operators as o', 'o.id', '=', 'va.v_assignment_operator_id')
        ->leftJoin('sacco_matatus_vehicles as v', 'v.id', '=', 'va.v_assignment_vehicle_id')
        ->select(
            'va.id',
            'o.full_name as operator_name',
            'v.vehicles_registration_number as vehicle_reg',
            'va.v_assignment_status',
            'va.v_assignment_start_date',
            'va.v_assignment_end_date'
        )
        ->orderByDesc('va.id');

    if ($request->filled('q')) {
        $q = $request->input('q');
        $query->where(function ($sub) use ($q) {
            $sub->where('o.full_name', 'like', "%{$q}%")
                ->orWhere('v.vehicles_registration_number', 'like', "%{$q}%");
        });
    }

    $assignments = $query->get();

    return view('transport.assignments.index', compact('assignments'));
}
public function create()
{
    $operators = DB::table('sacco_matatus_operators')->where('status', 'active')->get();
    $vehicles = DB::table('sacco_matatus_vehicles')->where('vehicles_status', 'active')->get();

    return view('transport.assignments.create', compact('operators', 'vehicles'));
}

public function store(Request $request)
{
    $request->validate([
        'v_assignment_operator_id' => 'required|exists:sacco_matatus_operators,id',
        'v_assignment_vehicle_id' => 'required|exists:sacco_matatus_vehicles,id',
        'v_assignment_start_date' => 'required|date',
        'v_assignment_end_date' => 'nullable|date|after_or_equal:v_assignment_start_date',
        'v_assignment_status' => 'required|string',
    ]);

    DB::table('sacco_matatus_operator_vehicle_assignments')->insert([
        'v_assignment_operator_id' => $request->v_assignment_operator_id,
        'v_assignment_vehicle_id' => $request->v_assignment_vehicle_id,
        'v_assignment_start_date' => $request->v_assignment_start_date,
        'v_assignment_end_date' => $request->v_assignment_end_date,
        'v_assignment_status' => $request->v_assignment_status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return redirect()->route('assignments.index')->with('success', 'Assignment added successfully.');
}

public function edit($id)
{
    $assignment = DB::table('sacco_matatus_operator_vehicle_assignments')->where('id', $id)->first();
    if (!$assignment) {
        return redirect()->route('assignments.index')->with('error', 'Assignment not found.');
    }

    $operators = DB::table('sacco_matatus_operators')->where('status', 'active')->get();
    $vehicles = DB::table('sacco_matatus_vehicles')->where('vehicles_status', 'active')->get();

    return view('transport.assignments.edit', compact('assignment', 'operators', 'vehicles'));
}

public function update(Request $request, $id)
{
    $request->validate([
        'v_assignment_operator_id' => 'required|exists:sacco_matatus_operators,id',
        'v_assignment_vehicle_id' => 'required|exists:sacco_matatus_vehicles,id',
        'v_assignment_start_date' => 'required|date',
        'v_assignment_end_date' => 'nullable|date|after_or_equal:v_assignment_start_date',
        'v_assignment_status' => 'required|string',
    ]);

    DB::table('sacco_matatus_operator_vehicle_assignments')
        ->where('id', $id)
        ->update([
            'v_assignment_operator_id' => $request->v_assignment_operator_id,
            'v_assignment_vehicle_id' => $request->v_assignment_vehicle_id,
            'v_assignment_start_date' => $request->v_assignment_start_date,
            'v_assignment_end_date' => $request->v_assignment_end_date,
            'v_assignment_status' => $request->v_assignment_status,
            'updated_at' => now(),
        ]);

    return redirect()->route('assignments.index')->with('success', 'Assignment updated successfully.');
}


}
