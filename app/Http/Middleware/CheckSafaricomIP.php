<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckSafaricomIP
{
    private $allowedRanges = [
        '196.201.212.', // Safaricom /24 range
        '196.201.213.',
        '196.201.214.',
    ];

    public function handle(Request $request, Closure $next)
    {
        $clientIP = $request->ip();

        $allowed = false;
        foreach ($this->allowedRanges as $prefix) {
            if (str_starts_with($clientIP, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            // Default log
            Log::warning('Unauthorized Safaricom IP attempt', ['ip' => $clientIP]);

            // Optional: log to safaricom channel (must be defined in config/logging.php)
            try {
                Log::channel('safaricom')->warning('Unauthorized Safaricom IP attempt', ['ip' => $clientIP]);
            } catch (\Exception $e) {
                // Fail silently if channel doesn't exist
            }

            return response()->json(['error' => 'Unauthorized IP'], 403);
        }

        return $next($request);
    }
}