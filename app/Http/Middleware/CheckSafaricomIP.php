<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

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
        '196.201.212.128',
        '196.201.212.129',
        '196.201.212.132',
        '196.201.212.136',
        '196.201.212.138',
        '196.201.212.69',
        '196.201.212.74',
    ];

    private $cloudflareRanges = [
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '108.162.192.0/18',
        '131.0.72.0/22',
        '141.101.64.0/18',
        '162.158.0.0/15',
        '172.64.0.0/13',
        '173.245.48.0/20',
        '188.114.96.0/20',
        '190.93.240.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',

        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    public function handle(Request $request, Closure $next)
    {
        /*
         * Start with the real network peer.
         * This avoids trusting spoofed X-Forwarded-For headers from direct requests.
         */
        $remoteIp = $request->server('REMOTE_ADDR');
        $clientIP = $remoteIp;

        /*
         * If the request came through Cloudflare, then and only then
         * trust Cloudflare's real visitor IP header.
         */
        if ($remoteIp && IpUtils::checkIp($remoteIp, $this->cloudflareRanges)) {
            $clientIP = $request->header('CF-Connecting-IP', $remoteIp);
        }

        if (!in_array($clientIP, $this->allowedIPs, true)) {
            Log::warning('Blocked non-whitelisted IP for M-Pesa callback', [
                'client_ip'        => $clientIP,
                'remote_addr'      => $remoteIp,
                'request_ip'       => $request->ip(),
                'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
                'x_forwarded_for'  => $request->header('X-Forwarded-For'),
                'url'              => $request->fullUrl(),
                'user_agent'       => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'Unauthorized IP',
            ], 403);
        }

        Log::info('Allowed Safaricom M-Pesa callback IP', [
            'client_ip'        => $clientIP,
            'remote_addr'      => $remoteIp,
            'request_ip'       => $request->ip(),
            'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
            'url'              => $request->fullUrl(),
        ]);

        return $next($request);
    }
}