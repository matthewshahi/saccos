<?php
namespace App\Http\Controllers\Transport;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PenaltyController extends Controller
{
    public function index()
    {
        $penalties = DB::table('sacco_matatu_penalties')
            ->join('sacco_members', 'sacco_matatu_penalties.penalty_member_id', '=', 'sacco_members.member_id')
            ->select('sacco_matatu_penalties.*', 'sacco_members.member_name')
            ->orderByDesc('penalty_date')
            ->get();

        return view('transport.penalties.index', compact('penalties'));
    }

    public function create()
    {
        $members = DB::table('sacco_members')->get();
        return view('transport.penalties.create', compact('members'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'penalty_date' => 'required|date',
            'penalty_member_id' => 'required|integer|exists:sacco_members,member_id',
            'penalty_description' => 'required|string|max:255',
            'penalty_amount' => 'required|numeric|min:1',
        ]);

        DB::table('sacco_matatu_penalties')->insert([
            'penalty_date' => $request->penalty_date,
            'penalty_member_id' => $request->penalty_member_id,
            'penalty_description' => $request->penalty_description,
            'penalty_amount' => $request->penalty_amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('penalties')->with('success', 'Penalty recorded and deposit adjusted.');
    }

    public function edit($id)
    {
        $penalty = DB::table('sacco_matatu_penalties')->where('id', $id)->first();
        $members = DB::table('sacco_members')->get();

        return view('transport.penalties.edit', compact('penalty', 'members'));
    }

    public function update($id, Request $request)
    {
        $request->validate([
            'penalty_date' => 'required|date',
            'penalty_member_id' => 'required|integer|exists:sacco_members,member_id',
            'penalty_description' => 'required|string|max:255',
            'penalty_amount' => 'required|numeric|min:1',
        ]);

        DB::table('sacco_matatu_penalties')->where('id', $id)->update([
            'penalty_date' => $request->penalty_date,
            'penalty_member_id' => $request->penalty_member_id,
            'penalty_description' => $request->penalty_description,
            'penalty_amount' => $request->penalty_amount,
            'updated_at' => now(),
        ]);

        return redirect()->route('penalties')->with('success', 'Penalty updated successfully.');
    }

    public function destroy($id)
    {
        DB::table('sacco_matatu_penalties')->where('id', $id)->delete();
        return redirect()->route('penalties')->with('success', 'Penalty deleted.');
    }
}