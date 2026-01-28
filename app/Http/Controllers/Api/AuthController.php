<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use App\Models\Member;
use App\Models\AuthSession;
use App\Services\AuthTokenService;

class AuthController extends Controller
{
    /**
     * ---------------------------------------------------------
     * LOGIN (Member email OR phone + legacy MD5 password)
     * ---------------------------------------------------------
     */
    public function login(Request $request, AuthTokenService $tokens)
    {
        $validated = $request->validate([
            'login'       => ['required', 'string'], // email OR phone
            'password'    => ['required', 'string'],
            'device_id'   => ['required', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $login        = $validated['login'];
        $passwordHash = md5($validated['password']); // legacy SACCO auth

        $member = Member::where(function ($q) use ($login) {
            $q->where('member_email', $login)
                ->orWhere('member_phone_no', $login);
        })
            ->where('member_password', $passwordHash)
            ->where('member_active', 'Y')
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // 🔒 Revoke any existing active session for this device
        AuthSession::where('user_id', $member->member_id)
            ->where('device_id', $validated['device_id'])
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        // 🔑 Generate tokens
        $refreshToken = $tokens->generateRefreshToken();
        $accessToken  = $tokens->createAccessToken(
            $member,
            $member->sacco_id ?? 0
        );

        // 📱 Create new device session
        $tokens->createSession(
            $member,
            $validated['device_id'],
            $validated['device_name'] ?? null,
            $refreshToken,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'access_token'  => $accessToken,
            'expires_in'    => AuthTokenService::ACCESS_TOKEN_TTL,
            'refresh_token' => $refreshToken,
            'member' => [
                'id'    => $member->member_id,
                'name'  => $member->member_name,
                'email' => $member->member_email,
                'phone' => $member->member_phone_no,
            ],
        ]);
    }

    /**
     * ---------------------------------------------------------
     * REFRESH TOKEN (Rotation, single-use)
     * ---------------------------------------------------------
     */
    public function refresh(Request $request, AuthTokenService $tokens)
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
            'device_id'     => ['required', 'string', 'max:64'],
        ]);

        $hashed = $tokens->hashRefreshToken($validated['refresh_token']);

        $session = AuthSession::where('refresh_token_hash', $hashed)
            ->where('device_id', $validated['device_id'])
            ->whereNull('revoked_at')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        // 🔒 Rotate: revoke old session
        $session->revoke();

        $member = Member::where('member_id', $session->user_id)
            ->where('member_active', 'Y')
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Account inactive.',
            ], 401);
        }

        // 🔑 Issue new tokens
        $newRefreshToken = $tokens->generateRefreshToken();
        $accessToken    = $tokens->createAccessToken(
            $member,
            $member->sacco_id ?? 0
        );

        // 📱 Create new session
        $tokens->createSession(
            $member,
            $validated['device_id'],
            $session->device_name,
            $newRefreshToken,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'access_token'  => $accessToken,
            'expires_in'    => AuthTokenService::ACCESS_TOKEN_TTL,
            'refresh_token' => $newRefreshToken,
        ]);
    }

    /**
     * ---------------------------------------------------------
     * LOGOUT (revoke current device session)
     * ---------------------------------------------------------
     */
    public function logout(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
        ]);

        AuthSession::where('device_id', $validated['device_id'])
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return response()->json([
            'success' => true,
        ]);
    }
}
