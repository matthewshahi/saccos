<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckSafaricomIP
{
   private $allowedIPs = [
    '196.201.214.200',
    '196.201.214.206',
    '196.201.213.114',
    '196.201.214.207',
    '196.201.214.208',
    '196.201.213.44',
    '196.201.212.127',
    '196.201.212.138',
    '196.201.212.129',
    '196.201.212.136',
    '196.201.212.74',
    '196.201.212.69',
];

    public function handle(Request $request, Closure $next)
    {
        $clientIP = $request->getClientIp(); // safer with proxies

        if (!in_array($clientIP, $this->allowedIPs)) {
            
            Log::warning('Unauthorized Safaricom IP attempt', ['ip' => $clientIP]);
Log::channel('safaricom')->warning('Unauthorized Safaricom IP attempt', ['ip' => $clientIP]);

            return response()->json(['error' => 'Unauthorized IP'], 403);
        }

        return $next($request);
    }
}