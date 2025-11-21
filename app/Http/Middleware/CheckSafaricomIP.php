<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckSafaricomIP
{
    private $allowedRanges = [
        '196.201.212.0/24',
        '196.201.213.0/24',
        '196.201.214.0/24',
        '102.219.172.0/24',
        '102.219.173.0/24',
        '197.248.94.0/24',
        '197.248.95.0/24',
    ];

    public function handle(Request $request, Closure $next)
    {
        $clientIP = $request->ip();

        $allowed = false;
        foreach ($this->allowedRanges as $range) {
            if ($this->ipInRange($clientIP, $range)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            Log::warning('Unauthorized Safaricom IP attempt', [
                'ip' => $clientIP,
                'url' => $request->fullUrl()
            ]);

            return response()->json(['error' => 'Unauthorized IP'], 403);
        }

        return $next($request);
    }

    /**
     * Proper CIDR range checker
     */
    private function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range);

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);

        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }
}
