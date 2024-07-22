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
        
        if (!$user) {
            return redirect()->route('login'); // Ensure user is authenticated
        }

        $grantedRights = $this->getGrantedRights($moduleName, $user->member_id);

        if (trim($grantedRights) !== 'Y') {
            return response('Access denied', 403);
        }

        return $next($request);
    }

    private function getGrantedRights($moduleName, $userId)
    {
        $query = "SELECT * FROM sacco_userrights 
                  INNER JOIN sacco_modules ON sacco_userrights.rights_app = sacco_modules.module_id 
                  WHERE sacco_modules.module_name = ? AND rights_user = ?";

        $results = DB::select($query, [$moduleName, $userId]);

        if (count($results) === 1) {
            return $results[0]->rights_access;
        }

        return 'N';
    }
}
