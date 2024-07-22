<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

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
}
