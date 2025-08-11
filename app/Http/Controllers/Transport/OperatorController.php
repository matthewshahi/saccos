<?php

namespace App\Http\Controllers\Transport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OperatorController extends Controller
{
  public function index(Request $request)
{
    $query = DB::table('sacco_matatus_operators');

    if ($search = $request->input('q')) {
        $query->where(function ($q) use ($search) {
            $q->where('full_name', 'like', "%{$search}%")
              ->orWhere('national_id', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    $operators = $query->orderBy('full_name')->get();

    return view('transport.operators.index', compact('operators'));
}

public function edit($id)
{
    $operator = DB::table('sacco_matatus_operators')->where('id', $id)->first();

    if (!$operator) {
        return redirect()->route('operators')->with('error', 'Operator not found.');
    }

    $members = DB::table('sacco_members')
    ->select('member_id as id', 'member_name as full_name')
    ->where('member_deleted', 'N')
    ->orderBy('member_name')
    ->get();

    return view('transport.operators.edit', compact('operator', 'members'));
}

public function update(Request $request, $id)
{
    $request->validate([
        'full_name' => 'required|string|max:255',
        'phone' => 'nullable|string|max:20',
        'national_id' => 'nullable|string|max:50',
        'gender' => 'nullable|in:male,female',
        'operator_type' => 'required|in:driver,conductor',
        'status' => 'required|in:active,inactive',
        'status_reason' => 'nullable|string|max:255',
        'introduced_by_member_id' => 'nullable|integer',
        'notes' => 'nullable|string',
    ]);

    DB::table('sacco_matatus_operators')->where('id', $id)->update([
        'full_name' => $request->full_name,
        'phone' => $request->phone,
        'national_id' => $request->national_id,
        'gender' => $request->gender,
        'operator_type' => $request->operator_type,
        'status' => $request->status,
        'status_reason' => $request->status_reason,
        'introduced_by_member_id' => $request->introduced_by_member_id,
        'notes' => $request->notes,
        'updated_at' => now(),
    ]);

    return redirect()->route('operators')->with('success', 'Operator updated successfully.');
}


public function create()
{
    $members = DB::table('sacco_members')
        ->select('member_id as id', 'member_name as full_name')
        ->where('member_deleted', 'N')
        ->orderBy('member_name')
        ->get();

    return view('transport.operators.create', compact('members'));
}

public function store(Request $request)
{
    $request->validate([
        'full_name' => 'required|string|max:255',
        'phone' => 'nullable|string|max:20',
        'national_id' => 'nullable|string|max:100|unique:sacco_matatus_operators,national_id',
        'gender' => 'nullable|in:male,female',
        'operator_type' => 'required|in:driver,conductor',
        'status' => 'nullable|in:active,inactive',
        'status_reason' => 'nullable|string|max:255',
        'introduced_by_member_id' => 'nullable|integer|exists:sacco_members,member_id',
        'notes' => 'nullable|string',
        'photo' => 'nullable|image|max:2048',
    ]);

    $data = $request->only([
        'full_name',
        'phone',
        'national_id',
        'gender',
        'operator_type',
        'status',
        'status_reason',
        'introduced_by_member_id',
        'notes',
    ]);

    if ($request->hasFile('photo')) {
        $data['photo'] = $request->file('photo')->store('operators', 'public');
    }

    $data['status'] = $data['status'] ?? 'active';
    $data['created_at'] = now();
    $data['updated_at'] = now();

    DB::table('sacco_matatus_operators')->insert($data);

    return redirect()->route('operators')->with('success', 'Operator added successfully.');
}

}
