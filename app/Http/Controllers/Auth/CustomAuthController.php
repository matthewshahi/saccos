<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\SaccoRouteAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class CustomAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request, SaccoRouteAuditLogger $auditLogger)
    {
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->logLoginAuditFailure(
                $auditLogger,
                $request,
                'LOGIN_VALIDATION_FAILED',
                'Login validation failed'
            );

            return back()
                ->withErrors($validator)
                ->withInput($request->only('login'));
        }

        /*
        |--------------------------------------------------------------------------
        | Verify reCAPTCHA v3 only in production
        |--------------------------------------------------------------------------
        */
        if (app()->environment('production')) {
            $token = (string) $request->input('recaptcha_token', '');

            if ($token === '') {
                $this->logLoginAuditFailure(
                    $auditLogger,
                    $request,
                    'LOGIN_RECAPTCHA_MISSING',
                    'reCAPTCHA token missing'
                );

                return back()->withErrors([
                    'login' => 'Security check could not load. Please disable any ad-blocker and refresh the page.',
                ])->withInput($request->only('login'));
            }

            $secret = (string) config('services.recaptcha.secret');
            $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

            try {
                $http = Http::asForm()->timeout(5)->post($verifyUrl, [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

                if (!$http->ok()) {
                    Log::warning('reCAPTCHA siteverify HTTP error', [
                        'status' => $http->status(),
                        'body'   => $http->body(),
                    ]);

                    $this->logLoginAuditFailure(
                        $auditLogger,
                        $request,
                        'LOGIN_RECAPTCHA_HTTP_FAILED',
                        'reCAPTCHA verification HTTP request failed'
                    );

                    return back()->withErrors([
                        'login' => 'Unable to verify security check. Please try again.',
                    ])->withInput($request->only('login'));
                }

                $data = $http->json();

                $success = (bool) ($data['success'] ?? false);
                $score   = (float) ($data['score'] ?? 0.0);
                $action  = (string) ($data['action'] ?? '');
                $host    = (string) ($data['hostname'] ?? '');
                $errors  = $data['error-codes'] ?? [];

                if (!$success || $action !== 'login') {
                    Log::warning('reCAPTCHA rejected login', [
                        'success' => $success,
                        'score'   => $score,
                        'action'  => $action,
                        'host'    => $host,
                        'errors'  => $errors,
                    ]);

                    $this->logLoginAuditFailure(
                        $auditLogger,
                        $request,
                        'LOGIN_RECAPTCHA_REJECTED',
                        'reCAPTCHA rejected login attempt'
                    );

                    return back()->withErrors([
                        'login' => 'Security check failed. Please refresh the page and try again.',
                    ])->withInput($request->only('login'));
                }

                $threshold = 0.35;

                if ($score < $threshold) {
                    Log::warning('reCAPTCHA low score login', [
                        'score'     => $score,
                        'threshold' => $threshold,
                        'host'      => $host,
                    ]);

                    $this->logLoginAuditFailure(
                        $auditLogger,
                        $request,
                        'LOGIN_RECAPTCHA_LOW_SCORE',
                        'Login blocked because reCAPTCHA score was too low'
                    );

                    return back()->withErrors([
                        'login' => 'Login blocked by security check. If you are using a VPN/ad-blocker, disable it and try again.',
                    ])->withInput($request->only('login'));
                }
            } catch (\Throwable $e) {
                Log::warning('reCAPTCHA verify exception', [
                    'error' => $e->getMessage(),
                ]);

                $this->logLoginAuditFailure(
                    $auditLogger,
                    $request,
                    'LOGIN_RECAPTCHA_EXCEPTION',
                    'reCAPTCHA verification exception: ' . $e->getMessage()
                );

                return back()->withErrors([
                    'login' => 'Unable to verify security check. Please try again.',
                ])->withInput($request->only('login'));
            }
        }

        $login = trim((string) $request->input('login'));
        $password = md5((string) $request->input('password'));

        /*
        |--------------------------------------------------------------------------
        | Check member by email, phone, national ID, or SACCO number
        |--------------------------------------------------------------------------
        */
        $member = $this->findActiveMemberForLogin($login, $password);

        if (!$member) {
            $this->logLoginAuditFailure(
                $auditLogger,
                $request,
                'LOGIN_FAILED',
                'The provided credentials do not match active member records'
            );

            return back()->withErrors([
                'login' => 'The provided credentials do not match our records.',
            ])->withInput($request->only('login'));
        }

        /*
        |--------------------------------------------------------------------------
        | Log in member
        |--------------------------------------------------------------------------
        */
        Auth::login($member);
        $request->session()->regenerate();

        $time = Carbon::now()->format('Y-m-d H:i:s');

        $ip       = $request->ip();
        $agent    = $request->header('User-Agent');
        $hostname = gethostbyaddr($ip) ?: 'Unknown Host';
        $referer  = $request->headers->get('referer') ?: 'Direct Access';
        $method   = $request->method();
        $uri      = $request->getRequestUri();

        DB::table('sacco_members')
            ->where('member_id', $member->member_id)
            ->update([
                'member_last_mobile_login' => $time,
                'member_ip'                => $ip,
            ]);

        $auditMessage = "
            <p style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
              This is to notify you that a login to your SACCO account was recorded with the following details:
            </p>

            <table style='font-family: Arial, sans-serif; font-size: 14px; color: #333; border-collapse: collapse; margin-top: 10px;'>
              <tr><td colspan='2' style='padding: 8px 0;'><strong>📍 Login Details</strong></td></tr>
              <tr><td style='padding: 4px 8px;'>IP Address:</td><td><code>{$ip}</code></td></tr>
              <tr><td style='padding: 4px 8px;'>Hostname:</td><td><code>{$hostname}</code></td></tr>
              <tr><td style='padding: 4px 8px;'>Device / Browser:</td><td><code>{$agent}</code></td></tr>
              <tr><td style='padding: 4px 8px;'>Accessed URL:</td><td><code>{$uri}</code></td></tr>
              <tr><td style='padding: 4px 8px;'>Request Method:</td><td><code>{$method}</code></td></tr>
              <tr><td style='padding: 4px 8px;'>Referrer:</td><td><code>{$referer}</code></td></tr>
              <tr><td style='padding: 4px 8px;'>Login Time:</td><td><code>{$time}</code></td></tr>
            </table>

            <p style='font-family: Arial, sans-serif; font-size: 15px; color: #b30000; margin-top: 20px;'>
              ⚠️ <strong>Security Notice:</strong><br>
              If this activity was not initiated by you, it may indicate unauthorized access.
              Please change your password immediately and contact your SACCO administrator.
            </p>

            <p style='font-family: Arial, sans-serif; font-size: 14px; color: #666; margin-top: 20px;'>
              <i>This login event has been logged for your security and compliance purposes.</i>
            </p>
        ";

        DB::table('sacco_system_notifications')->insert([
            'notif_recipient_name'  => $member->member_name,
            'notif_recipient_email' => $member->member_email,
            'notif_recipient_phone' => $member->member_phone_no,
            'notif_subject'         => 'Login Alert - ' . $member->member_name,
            'notif_message'         => $auditMessage,
            'notif_status'          => 'unread',
            'notif_type'            => 'login_audit',
            'notif_sent_at'         => null,
            'notif_member_id'       => $member->member_id,
            'notif_created_by'      => $member->member_id,
            'notif_ip'              => $ip,
            'notif_created_at'      => $time,
        ]);

        return redirect()->intended('home')->with('success', 'Logged in successfully');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out successfully');
    }

    private function findActiveMemberForLogin(string $login, string $password): ?Member
    {
        $memberTable = (new Member())->getTable();

        /*
        |--------------------------------------------------------------------------
        | Supported login columns
        |--------------------------------------------------------------------------
        |
        | The first four are the important ones:
        | - member_email
        | - member_phone_no
        | - member_national_id
        | - member_sacco_id
        |
        | The extra national ID / phone variants are included to avoid breaking if
        | your actual column name differs slightly.
        |
        */
        $possibleLoginColumns = [
            'member_email',
            'member_phone_no',
            'member_national_id',
            'member_sacco_id',

            'member_national_id_no',
            'member_national_no',
            'member_id_no',
            'member_id_number',
            'member_identity_no',
            'member_passport_no',

            'member_phone',
            'member_mobile',
            'member_mobile_no',
        ];

        $loginColumns = collect($possibleLoginColumns)
            ->filter(fn ($column) => Schema::hasColumn($memberTable, $column))
            ->values()
            ->all();

        if (empty($loginColumns)) {
            Log::error('No valid member login columns found on sacco_members table', [
                'table' => $memberTable,
            ]);

            return null;
        }

        return Member::where(function ($query) use ($loginColumns, $login) {
                foreach ($loginColumns as $column) {
                    $query->orWhere($column, $login);
                }
            })
            ->where('member_password', $password)
            ->where('member_active', 'Y')
            ->first();
    }

    private function logLoginAuditFailure(
        SaccoRouteAuditLogger $auditLogger,
        Request $request,
        string $eventType,
        string $reason
    ): void {
        $auditLogger->logSecurityEvent(
            eventType: $eventType,
            outcome: 'FAILED',
            user: null,
            attemptedLogin: $request->input('login')
                ?? $request->input('email')
                ?? $request->input('username')
                ?? $request->input('user_name')
                ?? null,
            request: $request
        );

        Log::warning($eventType, [
            'reason'          => $reason,
            'attempted_login' => $request->input('login'),
            'ip'              => $request->ip(),
            'path'            => $request->path(),
            'user_agent'      => $request->userAgent(),
        ]);
    }
}