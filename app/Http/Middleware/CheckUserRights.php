<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckUserRights
{
    public function handle(Request $request, Closure $next, $moduleName)
    {
        $user = Auth::user();

        // Ensure the user is authenticated
        if (!$user) {
            return redirect()->route('login');
        }

        // Check if user exists and has member_position = 2
        $member = DB::table('sacco_members')->where('member_id', $user->member_id)->first();
        if (!$member || $member->member_position != 2) {
            return response('Access denied', 403);
        }

        // Check if the user has the granted rights for the specified module
        $grantedRights = $this->getGrantedRights($moduleName, $user->member_id);

        if (trim($grantedRights) !== 'Y') {
            return response('Access denied', 403);
        }

        return $next($request);
    }
 

    private function getGrantedRights($moduleName, $userId)
    {
        // ✅ Ensure module exists (insert if missing)
        $module = DB::table('sacco_modules')
            ->where('module_name', $moduleName)
            ->first();

        if (!$module) {
            $id = DB::table('sacco_modules')->insertGetId([
                'module_name'        => $moduleName,
                'module_active'      => 'Y',
                'module_description' => $moduleName,
                'module_deleted'     => 'N',
                'module_userid'      => $userId,
                'module_ip'          => request()->ip(),
                'module_transdate'   => now(),
            ]);

            $module = DB::table('sacco_modules')->where('module_id', $id)->first();
        }

        // ✅ Now check rights for the user against this module
        $results = DB::table('sacco_userrights')
            ->where('rights_app', $module->module_id)
            ->where('rights_user', $userId)
            ->first();

        return $results ? $results->rights_access : 'N';
    }
    public static function userHasRight($moduleName)
{
    $user = Auth::user();
    if (!$user) return false;

    // ✅ Ensure the module exists — create if missing
    $module = DB::table('sacco_modules')
        ->where('module_name', $moduleName)
        ->first();

    if (!$module) {
        $moduleId = DB::table('sacco_modules')->insertGetId([
            'module_name'        => $moduleName,
            'module_active'      => 'Y',
            'module_description' => $moduleName,
            'module_deleted'     => 'N',
            'module_userid'      => $user->member_id,
            'module_ip'          => request()->ip(),
            'module_transdate'   => now(),
        ]);

        // Load the created record
        $module = DB::table('sacco_modules')->where('module_id', $moduleId)->first();
    }

    // ✅ Check if user has access rights
    $right = DB::table('sacco_userrights')
        ->where('rights_app', $module->module_id)
        ->where('rights_user', $user->member_id)
        ->where('rights_access', 'Y')
        ->first();

    return (bool) $right;
}
}
