<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSafaricomIP
{
    /**
     * List of Safaricom IPs to whitelist.
     */
    private $allowedIPs = [
        '196.201.214.200',
        '196.201.214.206',
        '196.201.213.114',
        '196.201.214.207',
        '196.201.214.208',
        '196.201.213.44',
        '196.201.212.127',
        '196.201.212.128',
        '196.201.212.129',
        '196.201.212.132',
        '196.201.212.136',
        '196.201.212.138',
        '196.201.212.69',
        '196.201.212.74',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $clientIP = $request->ip(); // Get the IP address of the client

        if (!in_array($clientIP, $this->allowedIPs)) {
            return response()->json(['error' => 'Unauthorized IP'], 403); // Reject unauthorized IPs
        }

        return $next($request); // Allow the request if IP is valid
    }
}