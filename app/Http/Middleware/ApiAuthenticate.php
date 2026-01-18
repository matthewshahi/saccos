<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuthTokenService;
use App\Models\Member;

class ApiAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $payload = AuthTokenService::decodeAccessToken($token);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $memberId = $payload['sub'] ?? null;

        if (!$memberId) {
            return response()->json([
                'message' => 'Invalid token payload.',
            ], 401);
        }

        $member = Member::where('member_id', $memberId)
            ->where('member_active', 'Y')
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Account inactive.',
            ], 401);
        }

        // Attach authenticated member
        $request->setUserResolver(fn () => $member);

        return $next($request);
    }
}
