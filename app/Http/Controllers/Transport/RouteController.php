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
}
