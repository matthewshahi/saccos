<?php

namespace App\Http\Controllers\Transport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RouteController extends Controller
{
   public function index()
{
    $routes = DB::table('sacco_matatus_routes')->orderBy('route_name')->get();

    return view('transport.routes.index', compact('routes'));
}

public function edit($id)
{
    $route = DB::table('sacco_matatus_routes')->where('id', $id)->first();

    if (!$route) {
        return redirect()->route('routes')->with('error', 'Route not found.');
    }

    return view('transport.routes.edit', compact('route'));
}

public function update(Request $request, $id)
{
    $request->validate([
        'route_name' => 'required|string|max:255',
        'route_start' => 'required|string|max:255',
        'route_end' => 'required|string|max:255',
        'route_distance_km' => 'nullable|numeric|min:0',
        'status' => 'required|in:active,inactive',
    ]);

    DB::table('sacco_matatus_routes')->where('id', $id)->update([
        'route_name' => $request->route_name,
        'route_start' => $request->route_start,
        'route_end' => $request->route_end,
        'route_distance_km' => $request->route_distance_km,
        'status' => $request->status,
        'updated_at' => now(),
    ]);

    return redirect()->route('routes')->with('success', 'Route updated successfully.');
}
public function store(Request $request)
{
    // Validate the request
    $request->validate([
        'route_name' => 'required|string|max:255',
        'route_start' => 'required|string|max:255',
        'route_end' => 'required|string|max:255',
        'route_distance_km' => 'nullable|numeric|min:0',
        'status' => 'required|in:active,inactive',
    ]);

    // Insert into DB
   DB::table('sacco_matatus_routes')->insert([
        'route_name' => $request->route_name,
        'route_start' => $request->route_start,
        'route_end' => $request->route_end,
        'route_distance_km' => $request->route_distance_km,
        'status' => $request->status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Redirect back with success message
    return redirect()->route('routes')->with('success', 'Route added successfully.');
}
public function create()
{
    return view('transport.routes.create');
}

public function stagesIndex(Request $request)
{
    $q = trim($request->q);

    $stages = DB::table('sacco_matatus_stages as s')
        ->leftJoin('sacco_matatus_stage_chairs as c', function ($join) {
            $join->on('c.chair_stage_id', '=', 's.id')
                 ->where('c.deleted', 'N');
        })
        ->where('s.deleted', 'N')
        ->when($q, function ($query) use ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('s.stage_name', 'like', "%$q%")
                    ->orWhere('c.chair_name', 'like', "%$q%");
            });
        })
        ->select(
            's.id',
            's.stage_name',
            's.created_at',
            'c.chair_name',
            'c.chair_phone'
        )
        ->orderBy('s.stage_name', 'asc')
        ->paginate(20);

    return view('transport.stages.index', compact('stages', 'q'));
}

public function stagesCreate()
{
    return view('transport.stages.create');
}

public function stagesStore(Request $request)
{
    // 1. Validation
    $validated = $request->validate([
        'stage_name'  => 'required|string|max:255',
        'chair_name'  => 'required|string|max:255',
        'chair_phone' => [
            'required',
            'regex:/^\+?[0-9]{7,15}$/'
        ],
    ], [
        'chair_phone.regex' => 'Please enter a valid phone number.',
    ]);

    // Normalize phone
    $phone = preg_replace('/[^0-9]/', '', $validated['chair_phone']);
    if (str_starts_with($phone, '7')) {
        $phone = '0' . $phone;
    }

    DB::beginTransaction();

    try {

        // 2. Check if stage already exists
        $existingStage = DB::table('sacco_matatus_stages')
            ->where('stage_name', $validated['stage_name'])
            ->where('deleted', '<>', 'Y')
            ->first();

        if ($existingStage) {
            return back()
                ->withErrors(['Stage already exists.'])
                ->withInput();
        }

        // 3. Create the stage
        $stageId = DB::table('sacco_matatus_stages')->insertGetId([
            'stage_name' => $validated['stage_name'],
            'created_at' => now(),
        ]);

        // 4. Check if chair already exists for this stage
        $existingChair = DB::table('sacco_matatus_stage_chairs')
            ->where('chair_name', $validated['chair_name'])
            ->where('chair_stage_id', $stageId)
            ->first();

        if ($existingChair) {
            // ✔ Reuse existing chair ID (DO NOT reject and DO NOT create new)
            $chairId = $existingChair->id;
        } else {
            // ✔ Create new chair
            $chairId = DB::table('sacco_matatus_stage_chairs')->insertGetId([
                'chair_name'     => $validated['chair_name'],
                'chair_phone'    => $phone,
                'chair_stage_id' => $stageId,
                'created_at'     => now(),
            ]);
        }

        DB::commit();

        return redirect()
            ->route('stages.index')
            ->with('success', 'Stage and Chair saved successfully.');

    } catch (\Exception $e) {

        DB::rollBack();
        Log::error("STAGE CREATE ERROR: " . $e->getMessage());

        return back()
            ->withErrors(['Failed to save stage. Please try again.'])
            ->withInput();
    }
}


public function stagesEdit($id)
{
    // Fetch stage
    $stage = DB::table('sacco_matatus_stages')
        ->where('id', $id)
        ->where('deleted', '<>', 'Y')
        ->first();

    if (!$stage) {
        return redirect()->route('stages.index')
            ->with('error', 'Stage not found.');
    }

    // Fetch linked chair (only one chair per stage)
    $chair = DB::table('sacco_matatus_stage_chairs')
        ->where('chair_stage_id', $id)
        ->where('deleted', '<>', 'Y')
        ->first();

    return view('transport.stages.edit', compact('stage', 'chair'));
}

public function stagesUpdate(Request $request, $id)
{
   $request->validate([
        'stage_name'  => 'required|string|max:255',
        'chair_name'  => 'nullable|string|max:255',
        'chair_phone' => 'nullable|regex:/^\+?[0-9]{7,15}$/',
    ]);

    DB::beginTransaction();

    try {

        // ------------------------------------------------------
        // 1. UPDATE THE STAGE
        // ------------------------------------------------------
        DB::table('sacco_matatus_stages')
            ->where('id', $id)
            ->update([
                'stage_name' => $request->stage_name,
                'updated_at' => now(),
            ]);


        // ------------------------------------------------------
        // 2. HANDLE CHAIR (Add / Update / Reject Duplicate)
        // ------------------------------------------------------
        $newName  = trim($request->chair_name);
        $newPhone = trim($request->chair_phone);

        if ($newName || $newPhone) {

            // Find if THIS stage already has chair
            $current = DB::table('sacco_matatus_stage_chairs')
                        ->where('chair_stage_id', $id)
                        ->where('deleted', '<>', 'Y')
                        ->first();

            // Check if NEW NAME exists on another stage
            if ($newName) {
                $nameExistsElsewhere = DB::table('sacco_matatus_stage_chairs')
                    ->where('chair_name', $newName)
                    ->where('chair_stage_id', '<>', $id)
                    ->where('deleted', '<>', 'Y')
                    ->exists();

                if ($nameExistsElsewhere) {
                    return back()->withErrors([
                        'error' => "Chair name '$newName' is already assigned to another stage."
                    ]);
                }
            }

            // Check if NEW PHONE exists on another stage
            if ($newPhone) {
                $phoneExistsElsewhere = DB::table('sacco_matatus_stage_chairs')
                    ->where('chair_phone', $newPhone)
                    ->where('chair_stage_id', '<>', $id)
                    ->where('deleted', '<>', 'Y')
                    ->exists();

                if ($phoneExistsElsewhere) {
                    return back()->withErrors([
                        'error' => "Phone number '$newPhone' is already used by another chair."
                    ]);
                }
            }

            // ------------------------------------------------------
            // If the stage already has a chair → update it
            // ------------------------------------------------------
            if ($current) {
                DB::table('sacco_matatus_stage_chairs')
                    ->where('id', $current->id)
                    ->update([
                        'chair_name'  => $newName,
                        'chair_phone' => $newPhone,
                        'updated_at'  => now(),
                    ]);
            } 
            else {
                // --------------------------------------------------
                // No chair yet → CREATE new one
                // --------------------------------------------------
                DB::table('sacco_matatus_stage_chairs')->insert([
                    'chair_name'     => $newName,
                    'chair_phone'    => $newPhone,
                    'chair_stage_id' => $id,
                    'created_at'     => now(),
                ]);
            }
        }

        DB::commit();
        return redirect()->route('stages.index')
                         ->with('success', 'Stage updated successfully!');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("STAGE UPDATE ERROR: ".$e->getMessage());

        return back()->withErrors(['error' => 'Failed to update stage. Try again.']);
    }
}
public function stagesDelete($id)
{
    DB::table('sacco_matatus_stages')
        ->where('id', $id)
        ->update([
            'deleted' => 'Y',
            'updated_at' => now(),
        ]);

    return back()->with('success', 'Stage deleted.');
}
public function chairsIndex(Request $request)
{
    $q = $request->q;

    $chairs = DB::table('sacco_matatus_stage_chairs AS c')
        ->leftJoin('sacco_matatus_stages AS s', 's.id', '=', 'c.chair_stage_id')
        ->select('c.*', 's.stage_name')
        ->where('c.deleted', '<>', 'Y')
        ->when($q, function($query) use ($q) {
            $query->where('c.chair_name', 'like', "%$q%")
                  ->orWhere('s.stage_name', 'like', "%$q%");
        })
        ->orderBy('s.stage_name')
        ->paginate(20);

    return view('transport.chairs.index', compact('chairs'));
}
public function chairsCreate()
{
    $stages = DB::table('sacco_matatus_stages')
        ->where('deleted', '<>', 'Y')
        ->orderBy('stage_name')
        ->get();

    return view('transport.chairs.create', compact('stages'));
}
public function chairsStore(Request $request)
{
    $request->validate([
        'chair_name' => 'required|string|max:255',
        'chair_phone' => 'nullable|string|max:20',
        'chair_stage_id' => 'required|integer',
    ]);

    DB::table('sacco_matatus_stage_chairs')->insert([
        'chair_name'     => $request->chair_name,
        'chair_phone'    => $request->chair_phone,
        'chair_stage_id' => $request->chair_stage_id,
        'deleted'        => 'N',
        'created_at'     => now(),
    ]);

    return redirect()->route('chairs.index')->with('success', 'Stage Chair added successfully.');
}
public function chairsEdit($id)
{
    $chair = DB::table('sacco_matatus_stage_chairs')->where('id', $id)->first();
    $stages = DB::table('sacco_matatus_stages')->where('deleted', '<>', 'Y')->get();

    return view('transport.chairs.edit', compact('chair','stages'));
}
public function chairsUpdate(Request $request, $id)
{
    $request->validate([
        'chair_name' => 'required|string|max:255',
        'chair_phone' => 'nullable|string|max:20',
        'chair_stage_id' => 'required|integer',
    ]);

    DB::table('sacco_matatus_stage_chairs')
        ->where('id', $id)
        ->update([
            'chair_name'     => $request->chair_name,
            'chair_phone'    => $request->chair_phone,
            'chair_stage_id' => $request->chair_stage_id,
            'updated_at'     => now(),
        ]);

    return redirect()->route('chairs.index')->with('success', 'Chair updated successfully.');
}
public function chairsDelete($id)
{
    DB::table('sacco_matatus_stage_chairs')
        ->where('id', $id)
        ->update([
            'deleted' => 'Y',
            'updated_at' => now(),
        ]);

    return back()->with('success', 'Chair deleted.');
}

}
