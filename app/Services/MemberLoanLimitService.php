<?php

namespace App\Services;

use App\Models\AuthSession;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthTokenService
{
    /* -------------------------
     | Configurable constants
     |--------------------------*/
    public const ACCESS_TOKEN_TTL = 900; // 15 minutes

    /* -------------------------
     | Access token (JWT)
     |--------------------------*/
    public function createAccessToken($principal, int $saccoId): string
    {
        // $principal is a SACCO Member (not User)
        $payload = [
            'iss'      => config('app.url'),
            'sub'      => $principal->member_id, // SACCO member ID
            'type'     => 'member',
            'sacco_id' => $saccoId,
            'iat'      => time(),
            'exp'      => time() + self::ACCESS_TOKEN_TTL,
        ];

        return JWT::encode(
            $payload,
            config('app.key'),
            'HS256'
        );
    }

    /* -------------------------
     | Refresh token (opaque)
     |--------------------------*/
    public function generateRefreshToken(): string
    {
        return Str::random(64);
    }

    public function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /* -------------------------
     | Session creation
     |--------------------------*/
    public function createSession(
        $principal,
        string $deviceId,
        ?string $deviceName,
        string $refreshToken,
        ?string $ip,
        ?string $userAgent
    ): AuthSession {
        return AuthSession::create([
            'user_id'            => $principal->member_id, // SACCO member ID
            'device_id'          => $deviceId,
            'device_name'        => $deviceName,
            'refresh_token_hash' => $this->hashRefreshToken($refreshToken),
            'ip_address'         => $ip,
            'user_agent'         => $userAgent,
            'last_used_at'       => now(),
        ]);
    }


public static function decodeAccessToken(string $token): array
{
    try {
        $decoded = JWT::decode(
            $token,
            new Key(config('app.key'), 'HS256')
        );

        // Convert stdClass to array
        return (array) $decoded;
    } catch (\Throwable $e) {
        throw $e;
    }
}

}
