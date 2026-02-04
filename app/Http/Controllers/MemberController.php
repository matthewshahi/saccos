<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;
 
use Illuminate\Support\Facades\Validator;


class MemberController extends Controller
{
    public function index()
    {
        $members = DB::table('members')->get();
        return view('members.index', ['members' => $members]);
    }

    public function show($id)
    {
        $member = DB::table('members')->find($id);
        return view('members.show', ['member' => $member]);
    }


    public function createJunior()
{
    $guardian = auth()->user(); // Logged-in member from sacco_members

    if ($guardian->member_is_junior == 1) {
        return redirect()->back()->withErrors('You must be a verified guardian to register juniors.');
    }

    $juniors = DB::table('sacco_members')
        ->where('member_guardian_id', $guardian->member_id)
        ->where('member_is_junior', 1)
        ->get();

    return view('members.juniors.add', compact('guardian', 'juniors'));
}

public function storeJunior(Request $request)
{
    $request->validate([
        'member_name' => 'required|string|max:255',
        'member_dob' => 'required|date',
        'member_gender' => 'required|in:M,F',
        'member_phone_no' => 'nullable|string|max:25',
        'member_email' => 'nullable|email|max:255',
        'member_national_id' => 'required|string|max:100', // Birth cert or ID
    ]);

    $guardian = Auth::user(); // logged-in guardian

    // Generate unique member_sacco_id by appending _J1, _J2, etc.
    $baseId = $guardian->member_sacco_id . '_J';
    $suffix = 1;

    do {
        $newSaccoId = $baseId . $suffix;
        $exists = DB::table('sacco_members')->where('member_sacco_id', $newSaccoId)->exists();
        $suffix++;
    } while ($exists);

    // Insert junior member
    DB::table('sacco_members')->insert([
        'member_name' => $request->member_name,
        'member_national_id' => $request->member_national_id, // birth cert or ID
        'member_dob' => $request->member_dob,
        'member_date_joined' => $request->member_dob, // for consistency
        'member_gender' => $request->member_gender,
        'member_phone_no' => $request->member_phone_no,
        'member_email' => $request->member_email,

        // Use the generated sacco ID like C13_J1
        'member_sacco_id' => $newSaccoId,

        // Inherit from guardian
        'member_postal_address' => $guardian->member_postal_address,
        'bank_name' => $guardian->bank_name,
        'bank_branch' => $guardian->bank_branch,
        'bank_account_number' => $guardian->bank_account_number,
        'member_dept' => $guardian->member_dept,

        // Set default financial fields
        'member_share_contr_monthly' => 0,
        'member_fosa_contr_monthly' => 0,
        'member_total_share' => 0,
        'member_total_fosa' => 0,
        'member_total_loan' => 0,
        'member_total_share_capital' => 0,
        'member_tied_shares' => 0,
        'member_tied_shares_self' => 0,
        'member_position' => 1,

        // Junior settings
        'member_is_junior' => 1,
        'member_guardian_id' => $guardian->member_id,
        'member_active' => 'N', // Inactive until SACCO validates

        // Audit
        'member_ip' => request()->ip(),
        'member_transdate' => now(),
        'member_user_id' => $guardian->member_user_id,
    ]);

  
    return redirect()->back()->with('success', 'Junior member registered successfully. The SACCO will review and activate the account.');
}

 
}
