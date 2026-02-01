<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CustomAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);


        // ------------------------------------------------------
        // 🔒 Verify reCAPTCHA v3 ONLY in Production
        // ------------------------------------------------------
        // ------------------------------------------------------
        // 🔒 Verify reCAPTCHA v3 ONLY in Production
        // ------------------------------------------------------
        if (app()->environment('production')) {
            $token = (string) $request->input('recaptcha_token', '');

            if ($token === '') {
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
                    'remoteip' => $request->ip(), // optional but helpful
                ]);

                if (!$http->ok()) {
                    Log::warning('reCAPTCHA siteverify HTTP error', [
                        'status' => $http->status(),
                        'body'   => $http->body(),
                    ]);

                    return back()->withErrors([
                        'login' => 'Unable to verify security check. Please try again.',
                    ])->withInput($request->only('login'));
                }

                $data = $http->json();

                $success = (bool)($data['success'] ?? false);
                $score   = (float)($data['score'] ?? 0.0);
                $action  = (string)($data['action'] ?? '');
                $host    = (string)($data['hostname'] ?? '');
                $errors  = $data['error-codes'] ?? [];

                // Log only on failure (or keep temporarily while tuning)
                if (!$success || $action !== 'login') {
                    Log::warning('reCAPTCHA rejected login', compact('success', 'score', 'action', 'host', 'errors'));
                }

                // ✅ Must match the action you used on frontend
                if (!$success || $action !== 'login') {
                    return back()->withErrors([
                        'login' => 'Security check failed. Please refresh the page and try again.',
                    ])->withInput($request->only('login'));
                }

                // ✅ Tune this threshold after reviewing logs
                $threshold = 0.35; // start lower for login to reduce false positives
                if ($score < $threshold) {
                    Log::warning('reCAPTCHA low score login', ['score' => $score, 'threshold' => $threshold, 'host' => $host]);
                    return back()->withErrors([
                        'login' => 'Login blocked by security check. If you are using a VPN/ad-blocker, disable it and try again.',
                    ])->withInput($request->only('login'));
                }
            } catch (\Throwable $e) {
                Log::warning('reCAPTCHA verify exception', ['error' => $e->getMessage()]);
                return back()->withErrors([
                    'login' => 'Unable to verify security check. Please try again.',
                ])->withInput($request->only('login'));
            }
        }


        $login    = $request->input('login');
        $password = md5($request->input('password'));

        // ✅ Check if member exists by email or phone
        $member = Member::where(function ($query) use ($login) {
            $query->where('member_email', $login)
                ->orWhere('member_phone_no', $login);
        })
            ->where('member_password', $password)
            ->where('member_active', 'Y')
            ->first();

        if (!$member) {
            return back()->withErrors([
                'login' => 'The provided credentials do not match our records.',
            ])->withInput($request->only('login'));
        }

        // ✅ Log in the member
        Auth::login($member);

        // ✅ Define the time immediately
        $time = Carbon::now()->format('Y-m-d H:i:s');

        // ✅ Capture detailed client-side audit info
        $ip        = $request->ip();
        $agent     = $request->header('User-Agent');
        $hostname  = gethostbyaddr($ip) ?: 'Unknown Host';
        $referer   = $request->headers->get('referer') ?: 'Direct Access';
        $method    = $request->method();
        $uri       = $request->getRequestUri();

        // ✅ Update last mobile login timestamp and IP
        DB::table('sacco_members')
            ->where('member_id', $member->member_id)
            ->update([
                'member_last_mobile_login' => $time,
                'member_ip'                => $ip,
            ]);

        // ✅ Build strong client-focused security message
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

        // ✅ Store login audit record
        DB::table('sacco_system_notifications')->insert([
            'notif_recipient_name'  => $member->member_name,
            'notif_recipient_email' => $member->member_email,
            'notif_recipient_phone' => $member->member_phone_no,
            'notif_subject'         => 'Login Alert - ' . $member->member_name,
            'notif_message'         => $auditMessage,
            'notif_status'          => 'unread', // default status
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
        return redirect()->route('login')->with('success', 'Logged out successfully');
    }
}
