<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckMemberPosition
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Ensure the user is authenticated
        if (!$user) {
            return redirect()->route('login');
        }

        // Check if the user exists and has member_position = 2
        $member = DB::table('sacco_members')->where('member_id', $user->member_id)->first();
        if (!$member || $member->member_position != 2) {
            return response('Access denied', 403);
        }

        return $next($request);
    }
}
