<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

class CheckSafaricomIP
{
    /**
     * Safaricom / M-Pesa callback IPs.
     *
     * These are checked against the resolved real client IP.
     * Exact IPs are supported, and CIDR ranges can also be added later.
     */
    private array $allowedIPs = [
        '102.217.167.34', // TEMP TEST ONLY - REMOVE AFTER TEST
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
     * Cloudflare official proxy IP ranges.
     *
     * We only trust CF-Connecting-IP when REMOTE_ADDR is inside these ranges.
     */
    private array $cloudflareRanges = [
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
         * Separate daily IP log file:
         * storage/logs/ip_logs-YYYY-MM-DD.log
         */
        $ipLog = Log::build([
            'driver' => 'daily',
            'path'   => storage_path('logs/ip_logs.log'),
            'days'   => 14,
        ]);

        /*
         * Start with the true network peer.
         *
         * If Safaricom hits the origin directly:
         * REMOTE_ADDR will be Safaricom's IP.
         *
         * If Safaricom comes through Cloudflare:
         * REMOTE_ADDR will be Cloudflare's IP,
         * and CF-Connecting-IP should contain Safaricom's real IP.
         */
        $remoteIp = $request->server('REMOTE_ADDR');
        $clientIP = $remoteIp;

        /*
         * Log every request that reaches this middleware.
         * If a failed callback is not logged here, it did not reach this middleware.
         */
        $ipLog->info('MPESA CALLBACK HIT MIDDLEWARE', [
            'remote_addr'      => $remoteIp,
            'request_ip'       => $request->ip(),
            'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
            'x_forwarded_for'  => $request->header('X-Forwarded-For'),
            'x_real_ip'        => $request->header('X-Real-IP'),
            'true_client_ip'   => $request->header('True-Client-IP'),
            'forwarded'        => $request->header('Forwarded'),
            'method'           => $request->method(),
            'url'              => $request->fullUrl(),
            'path'             => $request->path(),
            'user_agent'       => $request->userAgent(),
        ]);

        /*
         * Only trust CF-Connecting-IP if the immediate network peer
         * is genuinely Cloudflare.
         *
         * This blocks attackers from spoofing:
         * CF-Connecting-IP: 196.201.xxx.xxx
         */
        $cameThroughCloudflare = false;

        if ($remoteIp && IpUtils::checkIp($remoteIp, $this->cloudflareRanges)) {
            $cameThroughCloudflare = true;
            $clientIP = $request->header('CF-Connecting-IP', $remoteIp);
        }

        /*
         * Check resolved client IP against Safaricom allow-list.
         *
         * IpUtils::checkIp supports exact IPs and CIDR ranges.
         */
        $isAllowed = $clientIP && IpUtils::checkIp($clientIP, $this->allowedIPs);

        $ipLog->info('MPESA CALLBACK IP RESOLUTION RESULT', [
            'resolved_client_ip'      => $clientIP,
            'remote_addr'             => $remoteIp,
            'came_through_cloudflare' => $cameThroughCloudflare ? 'Y' : 'N',
            'is_safaricom_allowed'    => $isAllowed ? 'Y' : 'N',
        ]);

        if (!$isAllowed) {
            $ipLog->warning('MPESA CALLBACK BLOCKED - IP NOT WHITELISTED', [
                'blocked_client_ip' => $clientIP,
                'remote_addr'       => $remoteIp,
                'request_ip'        => $request->ip(),
                'cf_connecting_ip'  => $request->header('CF-Connecting-IP'),
                'x_forwarded_for'   => $request->header('X-Forwarded-For'),
                'x_real_ip'         => $request->header('X-Real-IP'),
                'true_client_ip'    => $request->header('True-Client-IP'),
                'url'               => $request->fullUrl(),
                'user_agent'        => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'Unauthorized IP',
            ], 403);
        }

        $ipLog->info('MPESA CALLBACK ALLOWED - SAFARICOM IP VERIFIED', [
            'allowed_client_ip'      => $clientIP,
            'remote_addr'            => $remoteIp,
            'request_ip'             => $request->ip(),
            'came_through_cloudflare'=> $cameThroughCloudflare ? 'Y' : 'N',
            'cf_connecting_ip'       => $request->header('CF-Connecting-IP'),
            'url'                    => $request->fullUrl(),
        ]);

        return $next($request);
    }
}