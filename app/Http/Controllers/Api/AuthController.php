<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthSession;
use App\Models\Member;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Preserve the existing token claim expected by AuthTokenService.
     *
     * In the sacco_members table, member_sacco_id is the member's
     * SACCO membership number.
     */
    private function resolveSaccoClaim(Member $member): int
    {
        if (
            isset($member->member_sacco_id)
            && is_numeric($member->member_sacco_id)
        ) {
            return (int) $member->member_sacco_id;
        }

        /*
         * Backward compatibility for environments that may still
         * expose a separate sacco_id property.
         */
        if (
            isset($member->sacco_id)
            && is_numeric($member->sacco_id)
        ) {
            return (int) $member->sacco_id;
        }

        return 0;
    }

    /**
     * ---------------------------------------------------------
     * LOGIN
     * ---------------------------------------------------------
     *
     * Supported login identifiers:
     *
     * - Email address
     * - Phone number
     * - National ID
     * - SACCO membership number
     *
     * Passwords currently use the existing legacy MD5 format.
     */
    public function login(
        Request $request,
        AuthTokenService $tokens
    ): JsonResponse {
        $validated = $request->validate([
            'login' => [
                'bail',
                'required',
                'string',
                'max:190',
            ],

            'password' => [
                'bail',
                'required',
                'string',
                'max:255',
            ],

            'device_id' => [
                'bail',
                'required',
                'string',
                'max:64',
            ],

            'device_name' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        $login = trim((string) $validated['login']);

        /*
         * Do not trim passwords because spaces may legally be part
         * of an existing member password.
         */
        $passwordHash = md5((string) $validated['password']);

        $member = $this->findActiveMemberForLogin(
            $login,
            $passwordHash
        );

        if (!$member) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /*
         * Revoke any existing active session belonging to this member
         * on the same device before issuing a new session.
         */
        AuthSession::query()
            ->where('user_id', $member->member_id)
            ->where('device_id', $validated['device_id'])
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);

        /*
         * Generate access and refresh tokens.
         */
        $refreshToken = $tokens->generateRefreshToken();

        $accessToken = $tokens->createAccessToken(
            $member,
            $this->resolveSaccoClaim($member)
        );

        /*
         * Create the new device session.
         */
        $tokens->createSession(
            $member,
            $validated['device_id'],
            $validated['device_name'] ?? null,
            $refreshToken,
            $request->ip(),
            $request->userAgent()
        );

        /*
         * Record the successful mobile login.
         */
        DB::table('sacco_members')
            ->where('member_id', $member->member_id)
            ->update([
                'member_last_mobile_login' => now(),
                'member_ip'                => $request->ip(),
            ]);

        return response()->json([
            'access_token' => $accessToken,
            'expires_in'   => AuthTokenService::ACCESS_TOKEN_TTL,

            'refresh_token' => $refreshToken,

            'member' => [
                'id' => $member->member_id,

                'name' => $member->member_name,

                'email' => $member->member_email,

                'phone' => $member->member_phone_no,

                'member_number' => $member->member_sacco_id,

                'national_id' => $member->member_national_id,
            ],
        ]);
    }

    /**
     * ---------------------------------------------------------
     * REFRESH TOKEN
     * ---------------------------------------------------------
     *
     * Refresh tokens are rotated and are therefore single-use.
     */
    public function refresh(
        Request $request,
        AuthTokenService $tokens
    ): JsonResponse {
        $validated = $request->validate([
            'refresh_token' => [
                'bail',
                'required',
                'string',
            ],

            'device_id' => [
                'bail',
                'required',
                'string',
                'max:64',
            ],
        ]);

        $hashedRefreshToken = $tokens->hashRefreshToken(
            $validated['refresh_token']
        );

        $result = DB::transaction(function () use (
            $request,
            $tokens,
            $validated,
            $hashedRefreshToken
        ) {
            /*
             * Lock the session row so that two simultaneous refresh
             * requests cannot successfully reuse the same token.
             */
            $session = AuthSession::query()
                ->where(
                    'refresh_token_hash',
                    $hashedRefreshToken
                )
                ->where(
                    'device_id',
                    $validated['device_id']
                )
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return [
                    'status' => 'invalid',
                ];
            }

            $member = $this->findActiveMemberById(
                (int) $session->user_id
            );

            /*
             * Revoke the refresh token even where the member has
             * subsequently been deactivated or deleted.
             */
            if (!$member) {
                $session->revoke();

                return [
                    'status' => 'inactive',
                ];
            }

            /*
             * Rotate the refresh token by revoking the old session.
             */
            $deviceName = $session->device_name;

            $session->revoke();

            $newRefreshToken = $tokens->generateRefreshToken();

            $accessToken = $tokens->createAccessToken(
                $member,
                $this->resolveSaccoClaim($member)
            );

            /*
             * Create a replacement session for the same device.
             */
            $tokens->createSession(
                $member,
                $validated['device_id'],
                $deviceName,
                $newRefreshToken,
                $request->ip(),
                $request->userAgent()
            );

            return [
                'status'        => 'success',
                'access_token'  => $accessToken,
                'refresh_token' => $newRefreshToken,
            ];
        });

        if ($result['status'] === 'invalid') {
            return response()->json([
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        if ($result['status'] === 'inactive') {
            return response()->json([
                'message' => 'Account inactive.',
            ], 401);
        }

        return response()->json([
            'access_token' => $result['access_token'],

            'expires_in' => AuthTokenService::ACCESS_TOKEN_TTL,

            'refresh_token' => $result['refresh_token'],
        ]);
    }

    /**
     * ---------------------------------------------------------
     * LOGOUT
     * ---------------------------------------------------------
     *
     * Revokes the active refresh-token session for the submitted
     * device.
     */
    public function logout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => [
                'bail',
                'required',
                'string',
                'max:64',
            ],
        ]);

        /*
         * The logout route must be protected by auth.api.
         *
         * When auth.api attaches the authenticated Member to the
         * request, restrict revocation to that member.
         */
        $authenticatedMember = $request->user();

        if (
            $authenticatedMember
            && isset($authenticatedMember->member_id)
        ) {
            AuthSession::query()
                ->where(
                    'user_id',
                    $authenticatedMember->member_id
                )
                ->where(
                    'device_id',
                    $validated['device_id']
                )
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                ]);
        } else {
            /*
             * This prevents a caller from revoking another member's
             * session by submitting only their device ID.
             */
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Find an active, non-deleted member using any supported login
     * identifier.
     */
    private function findActiveMemberForLogin(
        string $login,
        string $passwordHash
    ): ?Member {
        $phoneCandidates = $this->buildPhoneLoginCandidates(
            $login
        );

        return Member::query()
            ->where(function ($query) use (
                $login,
                $phoneCandidates
            ) {
                /*
                 * Email address.
                 */
                $query->where(
                    'member_email',
                    $login
                );

                /*
                 * Phone number in any equivalent supported format.
                 */
                $query->orWhereIn(
                    'member_phone_no',
                    $phoneCandidates
                );

                /*
                 * National identification number.
                 */
                $query->orWhere(
                    'member_national_id',
                    $login
                );

                /*
                 * SACCO membership number.
                 */
                $query->orWhere(
                    'member_sacco_id',
                    $login
                );
            })
            ->where(
                'member_password',
                $passwordHash
            )
            ->where(
                'member_active',
                'Y'
            )
            ->where(function ($query) {
                /*
                 * Accommodate legacy records where member_deleted
                 * may be NULL, while blocking records marked deleted.
                 */
                $query->whereNull('member_deleted')
                    ->orWhere('member_deleted', 'N');
            })
            ->first();
    }

    /**
     * Find an active, non-deleted member by primary key.
     */
    private function findActiveMemberById(
        int $memberId
    ): ?Member {
        return Member::query()
            ->where(
                'member_id',
                $memberId
            )
            ->where(
                'member_active',
                'Y'
            )
            ->where(function ($query) {
                $query->whereNull('member_deleted')
                    ->orWhere('member_deleted', 'N');
            })
            ->first();
    }

    /**
     * Build equivalent phone-number formats for login.
     *
     * Examples treated as the same Kenyan number:
     *
     * 0721470718
     * 254721470718
     * +254721470718
     * 00254721470718
     */
    private function buildPhoneLoginCandidates(
        string $login
    ): array {
        $login = trim($login);

        /*
         * Always include the exact value entered. This ensures that
         * emails, member numbers and national IDs still work.
         */
        $candidates = [$login];

        /*
         * Do not attempt phone normalization for values containing
         * letters or unsupported characters.
         */
        if (!preg_match('/^\+?[\d\s().-]+$/', $login)) {
            return $candidates;
        }

        $digits = preg_replace('/\D+/', '', $login);

        if (
            !is_string($digits)
            || strlen($digits) < 10
        ) {
            return array_values(
                array_unique($candidates)
            );
        }

        /*
         * Kenyan local mobile number:
         *
         * 0712345678
         * 0112345678
         */
        if (
            preg_match(
                '/^0([17]\d{8})$/',
                $digits,
                $matches
            )
        ) {
            $subscriber = $matches[1];
            $international = '254' . $subscriber;

            return array_values(array_unique([
                $login,
                '0' . $subscriber,
                $international,
                '+' . $international,
                '00' . $international,
            ]));
        }

        /*
         * Kenyan international mobile number:
         *
         * 254712345678
         * 254112345678
         */
        if (
            preg_match(
                '/^254([17]\d{8})$/',
                $digits,
                $matches
            )
        ) {
            $subscriber = $matches[1];
            $international = '254' . $subscriber;

            return array_values(array_unique([
                $login,
                '0' . $subscriber,
                $international,
                '+' . $international,
                '00' . $international,
            ]));
        }

        /*
         * Kenyan or international number entered using 00.
         */
        if (
            str_starts_with($digits, '00')
            && strlen($digits) >= 12
        ) {
            $international = substr($digits, 2);

            return array_values(array_unique([
                $login,
                $digits,
                $international,
                '+' . $international,
            ]));
        }

        /*
         * International number entered using +.
         */
        if (
            str_starts_with($login, '+')
            && strlen($digits) >= 10
            && strlen($digits) <= 15
        ) {
            return array_values(array_unique([
                $login,
                $digits,
                '+' . $digits,
                '00' . $digits,
            ]));
        }

        /*
         * Digits-only international number.
         *
         * Restrict this to more than 10 digits to reduce the chance
         * of treating an ordinary national ID as a phone number.
         */
        if (
            strlen($digits) >= 11
            && strlen($digits) <= 15
        ) {
            return array_values(array_unique([
                $login,
                $digits,
                '+' . $digits,
                '00' . $digits,
            ]));
        }

        return array_values(
            array_unique($candidates)
        );
    }
}