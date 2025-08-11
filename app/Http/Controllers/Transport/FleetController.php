<?php
namespace App\Http\Controllers\Transport;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class FleetController extends Controller
{
   public function index(Request $request)
{
    $query = DB::table('sacco_matatus_vehicles as v')
        ->leftJoin('sacco_members as m', 'v.vehicles_member_id', '=', 'm.member_id')
        ->select(
            'v.*',
            'm.member_name',
            'm.member_phone_no',
            'm.member_national_id'
        );

    if ($request->filled('q')) {
        $q = $request->q;
        $query->where(function ($q1) use ($q) {
            $q1->where('v.vehicles_registration_number', 'like', "%{$q}%")
                ->orWhere('v.vehicles_make', 'like', "%{$q}%")
                ->orWhere('v.vehicles_model', 'like', "%{$q}%")
                ->orWhere('v.vehicles_route_name', 'like', "%{$q}%")
                ->orWhere('v.vehicles_status', 'like', "%{$q}%")
                ->orWhere('m.member_name', 'like', "%{$q}%");
        });
    }

    $vehicles = $query->orderByDesc('v.id')->paginate(2000);

    return view('transport.fleet.index', compact('vehicles'));
}

     public function create()
    {
        $routes = DB::table('sacco_matatus_routes')->orderBy('route_name')->get();
        $members = DB::table('sacco_members')->where('member_active', 'Y')->orderBy('member_name')->get();

        return view('transport.fleet.create', compact('routes', 'members'));
    }

    public function store(Request $request)
{
    // Sanitize registration number first
    $request->merge([
        'vehicles_registration_number' => strtoupper(str_replace(' ', '', $request->vehicles_registration_number)),
    ]);

    // Validate after formatting
    $validated = $request->validate([
        'vehicles_registration_number' => 'required|unique:sacco_matatus_vehicles',
        'vehicles_make' => 'nullable|string',
        'vehicles_model' => 'nullable|string',
        'vehicles_year' => 'nullable|numeric',
        'vehicles_chassis_number' => 'nullable|string',
        'vehicles_insurance_provider' => 'nullable|string',
        'vehicles_insurance_expiry' => 'nullable|date',
        'vehicles_last_inspection_date' => 'nullable|date',
        'vehicles_psv_license_number' => 'nullable|string',
        'vehicles_psv_expiry' => 'nullable|date',
        'vehicles_route_name' => 'nullable|string',
        'vehicles_member_id' => 'nullable|exists:sacco_members,member_id',
        'vehicles_status' => 'required|string',
    ]);

    // Insert into DB
    DB::table('sacco_matatus_vehicles')->insert($validated);

    return redirect()->route('fleet')->with('success', 'Vehicle added successfully.');
}

     

  public function edit($id)
{
    $vehicle = DB::table('sacco_matatus_vehicles as v')
        ->leftJoin('sacco_members as m', 'v.vehicles_member_id', '=', 'm.member_id')
        ->leftJoin('sacco_matatus_routes as r', 'v.vehicles_route_name', '=', 'r.route_name')
        ->select(
            'v.*',
            'm.member_name',
            'm.member_sacco_id',
            'r.route_name as route_name_display'
        )
        ->where('v.id', $id)
        ->first();

    $routes = DB::table('sacco_matatus_routes')->orderBy('route_name')->get();
    $members = DB::table('sacco_members')
        ->where('member_active', 'Y')
        ->orderBy('member_name')
        ->get();

    return view('transport.fleet.edit', compact('vehicle', 'routes', 'members'));
}

    public function update(Request $request, $id)
{
    // Sanitize the registration number
    $request->merge([
        'vehicles_registration_number' => strtoupper(str_replace(' ', '', $request->vehicles_registration_number)),
    ]);

    // Validate input
    $validated = $request->validate([
        'vehicles_registration_number' => 'required|unique:sacco_matatus_vehicles,vehicles_registration_number,' . $id,
        'vehicles_make' => 'nullable|string',
        'vehicles_model' => 'nullable|string',
        'vehicles_year' => 'nullable|numeric',
        'vehicles_chassis_number' => 'nullable|string',
        'vehicles_insurance_provider' => 'nullable|string',
        'vehicles_insurance_expiry' => 'nullable|date',
        'vehicles_last_inspection_date' => 'nullable|date',
        'vehicles_psv_license_number' => 'nullable|string',
        'vehicles_psv_expiry' => 'nullable|date',
        'vehicles_route_name' => 'nullable|string',
        'vehicles_member_id' => 'nullable|exists:sacco_members,member_id',
        'vehicles_status' => 'required|string',
    ]);

    // Update record
    DB::table('sacco_matatus_vehicles')
        ->where('id', $id)
        ->update(array_merge($validated, ['updated_at' => now()]));

    return redirect()->route('fleet')->with('success', 'Vehicle updated successfully.');
}

    public function destroy($id)
    {
        DB::table('sacco_matatus_vehicles')->where('id', $id)->delete();
        return back()->with('success', 'Vehicle deleted.');
    }
}