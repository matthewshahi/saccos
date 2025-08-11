<?php

 

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    public function index()
    {
        $records = DB::table('sacco_matatu_maintenance as m')
            ->leftJoin('sacco_matatus_vehicles as v', 'm.maintenance_vehicle_id', '=', 'v.id')
            ->select('m.*', 'v.vehicles_registration_number as vehicle_reg')
            ->orderByDesc('m.maintenance_date')
            ->get();

        return view('transport.maintenance.index', compact('records'));
    }

    public function create()
    {
        $vehicles = DB::table('sacco_matatus_vehicles')->select('id', 'vehicles_registration_number')->get();
        return view('transport.maintenance.create', compact('vehicles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'maintenance_vehicle_id'        => 'required|integer',
            'maintenance_date'              => 'required|date',
            'maintenance_type'              => 'required|string|max:100',
            'maintenance_internal'          => 'nullable|boolean',
            'maintenance_description'       => 'required|string',
            'maintenance_fault_reported_by' => 'nullable|string|max:100',
            'maintenance_odometer_reading'  => 'nullable|integer',
            'maintenance_next_due_date'     => 'nullable|date',
            'maintenance_vendor'            => 'nullable|string|max:150',
            'maintenance_cost'              => 'required|numeric|min:0',
            'maintenance_payment_mode'      => 'nullable|string|max:50',
            'maintenance_doc_ref'           => 'nullable|string|max:100',
            'maintenance_parts_used'        => 'nullable|string|max:255',
            'maintenance_status'            => 'required|string|max:50',
            'maintenance_attachment_path'   => 'nullable|string|max:255',
        ]);

        $validated['maintenance_internal'] = $request->has('maintenance_internal');
        $validated['maintenance_recorded_by'] = auth()->id();
        $validated['maintenance_ip'] = $request->ip();
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        DB::table('sacco_matatu_maintenance')->insert($validated);

        return redirect()->route('maintenance')->with('success', 'Maintenance record added successfully.');
    }

    public function edit($id)
    {
        $record = DB::table('sacco_matatu_maintenance')->where('id', $id)->first();
        $vehicles = DB::table('sacco_matatus_vehicles')->select('id', 'vehicles_registration_number')->get();

        if (!$record) {
            return redirect()->route('maintenance')->with('error', 'Record not found.');
        }

        return view('transport.maintenance.edit', compact('record', 'vehicles'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'maintenance_vehicle_id'        => 'required|integer',
            'maintenance_date'              => 'required|date',
            'maintenance_type'              => 'required|string|max:100',
            'maintenance_internal'          => 'nullable|boolean',
            'maintenance_description'       => 'required|string',
            'maintenance_fault_reported_by' => 'nullable|string|max:100',
            'maintenance_odometer_reading'  => 'nullable|integer',
            'maintenance_next_due_date'     => 'nullable|date',
            'maintenance_vendor'            => 'nullable|string|max:150',
            'maintenance_cost'              => 'required|numeric|min:0',
            'maintenance_payment_mode'      => 'nullable|string|max:50',
            'maintenance_doc_ref'           => 'nullable|string|max:100',
            'maintenance_parts_used'        => 'nullable|string|max:255',
            'maintenance_status'            => 'required|string|max:50',
            'maintenance_attachment_path'   => 'nullable|string|max:255',
        ]);

        $validated['maintenance_internal'] = $request->has('maintenance_internal');
        $validated['updated_at'] = now();

        DB::table('sacco_matatu_maintenance')->where('id', $id)->update($validated);

        return redirect()->route('maintenance')->with('success', 'Maintenance record updated successfully.');
    }
}