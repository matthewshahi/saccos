<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BankIpnIpMiddleware
{
    public function handle(Request $request, Closure $next, ?string $bankCode = null)
    {
        $bankCode = strtoupper(trim((string) $bankCode));
        $clientIp = $this->getClientIp($request);

        /*
        |--------------------------------------------------------------------------
        | TEMPORARY TEST MODE
        |--------------------------------------------------------------------------
        | While waiting for official bank IPs, allow all requests.
        | Set BANK_IPN_ALLOW_ALL=false later in production.
        */
        if (filter_var(env('BANK_IPN_ALLOW_ALL', false), FILTER_VALIDATE_BOOLEAN)) {
            Log::info('Bank IPN IP check bypassed for testing', [
                'bank_code' => $bankCode,
                'client_ip' => $clientIp,
                'path'      => $request->path(),
            ]);

            return $next($request);
        }

        $allowedIps = $this->allowedIpsForBank($bankCode);

        if (empty($allowedIps)) {
            Log::warning('Bank IPN rejected because no allowed IPs are configured', [
                'bank_code' => $bankCode,
                'client_ip' => $clientIp,
                'path'      => $request->path(),
            ]);

            return response()->json([
                'status'  => '99',
                'message' => 'IPN source not allowed',
            ], 403);
        }

        if (!$this->ipIsAllowed($clientIp, $allowedIps)) {
            Log::warning('Bank IPN rejected due to invalid source IP', [
                'bank_code'   => $bankCode,
                'client_ip'   => $clientIp,
                'allowed_ips' => $allowedIps,
                'path'        => $request->path(),
            ]);

            return response()->json([
                'status'  => '99',
                'message' => 'IPN source not allowed',
            ], 403);
        }

        return $next($request);
    }

    private function allowedIpsForBank(string $bankCode): array
    {
        $envKey = match ($bankCode) {
            'SBM'  => 'SBM_IPN_ALLOWED_IPS',
            'KCB'  => 'KCB_IPN_ALLOWED_IPS',
            'NCBA' => 'NCBA_IPN_ALLOWED_IPS',
            'COOP' => 'COOP_IPN_ALLOWED_IPS',
            default => 'BANK_IPN_ALLOWED_IPS',
        };

        $value = env($envKey, '');

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function getClientIp(Request $request): ?string
    {
        if ($request->header('CF-Connecting-IP')) {
            return trim($request->header('CF-Connecting-IP'));
        }

        if ($request->header('X-Real-IP')) {
            return trim($request->header('X-Real-IP'));
        }

        if ($request->header('X-Forwarded-For')) {
            return trim(explode(',', $request->header('X-Forwarded-For'))[0]);
        }

        return $request->ip();
    }

    private function ipIsAllowed(?string $clientIp, array $allowedIps): bool
    {
        if (!$clientIp) {
            return false;
        }

        foreach ($allowedIps as $allowedIp) {
            if ($allowedIp === '*') {
                return true;
            }

            if ($clientIp === $allowedIp) {
                return true;
            }

            if (str_contains($allowedIp, '/') && $this->ipMatchesCidr($clientIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask       = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}