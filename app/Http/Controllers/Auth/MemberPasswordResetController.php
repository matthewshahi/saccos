<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MemberPasswordResetController extends Controller
{
    private string $resetTable = 'sacco_member_password_resets';
    private string $membersTable = 'sacco_members';

    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetInstructions(Request $request)
    {
        $request->validate([
            'account_number'  => ['required', 'string', 'max:100'],
            'email'           => ['required', 'email', 'max:191'],
            'recaptcha_token' => ['nullable', 'string'],
        ]);

        if (! $this->verifyRecaptcha($request, 'password_request')) {
            return back()
                ->withErrors(['account_number' => 'Security check failed. Please refresh the page and try again.'])
                ->withInput($request->only('account_number', 'email'));
        }

        $accountNumber = $this->normaliseAccountNumber($request->input('account_number'));
        $email = $this->normaliseEmail($request->input('email'));
        $ip = (string) $request->ip();
        $now = Carbon::now();

        $publicMessage = 'If the details match an active account, password reset instructions have been sent to the registered email address.';

        /*
         * Anti-abuse rate limit by IP + submitted account/email pair.
         * Public message remains generic.
         */
        $rateKey = 'member-password-reset:' . sha1($ip . '|' . $accountNumber . '|' . $email);

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return back()->with('status', $publicMessage);
        }

        RateLimiter::hit($rateKey, 15 * 60);

        $identifierHash = hash('sha256', mb_strtolower($accountNumber . '|' . $email));

        $member = $this->findMatchingActiveMember($accountNumber, $email);

        /*
         * Store failed/non-matching request for audit, but do not send anything.
         * User still gets generic message.
         */
        if (! $member) {
            $this->storeResetAttempt([
                'reset_member_id'        => null,
                'reset_account_number'   => $accountNumber,
                'reset_email'            => $email,
                'reset_identifier_hash'  => $identifierHash,
                'reset_token_hash'       => hash('sha256', Str::random(80)),
                'reset_expires_at'       => $now->copy()->addMinutes(30),
                'reset_used_at'          => null,
                'reset_sent_at'          => null,
                'reset_ip'               => $ip,
                'reset_user_agent'       => Str::limit((string) $request->userAgent(), 255, ''),
                'reset_status'           => 'blocked_no_match',
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            return back()->with('status', $publicMessage);
        }

        /*
         * One email per member every 10 minutes.
         */
        $recentSent = DB::table($this->resetTable)
            ->where('reset_member_id', $member->member_id)
            ->whereNull('reset_used_at')
            ->whereNotNull('reset_sent_at')
            ->where('created_at', '>=', $now->copy()->subMinutes(10))
            ->exists();

        if ($recentSent) {
            $this->storeResetAttempt([
                'reset_member_id'        => $member->member_id,
                'reset_account_number'   => $accountNumber,
                'reset_email'            => $email,
                'reset_identifier_hash'  => $identifierHash,
                'reset_token_hash'       => hash('sha256', Str::random(80)),
                'reset_expires_at'       => $now->copy()->addMinutes(30),
                'reset_used_at'          => null,
                'reset_sent_at'          => null,
                'reset_ip'               => $ip,
                'reset_user_agent'       => Str::limit((string) $request->userAgent(), 255, ''),
                'reset_status'           => 'blocked_cooldown',
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            return back()->with('status', $publicMessage);
        }

        /*
         * Daily cap per member.
         */
        $sentToday = DB::table($this->resetTable)
            ->where('reset_member_id', $member->member_id)
            ->whereNotNull('reset_sent_at')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        if ($sentToday >= 5) {
            $this->storeResetAttempt([
                'reset_member_id'        => $member->member_id,
                'reset_account_number'   => $accountNumber,
                'reset_email'            => $email,
                'reset_identifier_hash'  => $identifierHash,
                'reset_token_hash'       => hash('sha256', Str::random(80)),
                'reset_expires_at'       => $now->copy()->addMinutes(30),
                'reset_used_at'          => null,
                'reset_sent_at'          => null,
                'reset_ip'               => $ip,
                'reset_user_agent'       => Str::limit((string) $request->userAgent(), 255, ''),
                'reset_status'           => 'blocked_daily_cap',
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            return back()->with('status', $publicMessage);
        }

        /*
         * Invalidate old active reset tokens for this member.
         */
        DB::table($this->resetTable)
            ->where('reset_member_id', $member->member_id)
            ->whereNull('reset_used_at')
            ->update([
                'reset_used_at' => $now,
                'reset_status'  => 'superseded',
                'updated_at'    => $now,
            ]);

        $plainToken = Str::random(72);
        $tokenHash = hash('sha256', $plainToken);
        $resetUrl = route('member.password.reset', ['token' => $plainToken]);

        DB::table($this->resetTable)->insert([
            'reset_member_id'        => $member->member_id,
            'reset_account_number'   => $accountNumber,
            'reset_email'            => $email,
            'reset_identifier_hash'  => $identifierHash,
            'reset_token_hash'       => $tokenHash,
            'reset_expires_at'       => $now->copy()->addMinutes(30),
            'reset_used_at'          => null,
            'reset_sent_at'          => $now,
            'reset_ip'               => $ip,
            'reset_user_agent'       => Str::limit((string) $request->userAgent(), 255, ''),
            'reset_status'           => 'sent',
            'created_at'             => $now,
            'updated_at'             => $now,
        ]);

        /*
         * Do not send email directly.
         * Store notification for your email worker/system to pick and send.
         */
        $this->queuePasswordResetNotification($member, $resetUrl, $ip, $now);

        return back()->with('status', $publicMessage);
    }

    public function showResetForm(Request $request, string $token)
    {
        $tokenHash = hash('sha256', $token);

        $reset = DB::table($this->resetTable)
            ->where('reset_token_hash', $tokenHash)
            ->where('reset_status', 'sent')
            ->whereNull('reset_used_at')
            ->where('reset_expires_at', '>=', Carbon::now())
            ->first();

        if (! $reset) {
            return redirect()
                ->route('member.password.request')
                ->withErrors(['account_number' => 'This password reset link is invalid or has expired.']);
        }

        return view('auth.reset-password', [
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'           => ['required', 'string'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
            'recaptcha_token' => ['nullable', 'string'],
        ]);

        if (! $this->verifyRecaptcha($request, 'password_reset')) {
            return back()
                ->withErrors(['password' => 'Security check failed. Please refresh the page and try again.'])
                ->withInput();
        }

        $now = Carbon::now();
        $tokenHash = hash('sha256', $request->input('token'));

        $reset = DB::table($this->resetTable)
            ->where('reset_token_hash', $tokenHash)
            ->where('reset_status', 'sent')
            ->whereNull('reset_used_at')
            ->where('reset_expires_at', '>=', $now)
            ->first();

        if (! $reset) {
            return back()
                ->withErrors(['password' => 'This password reset link is invalid or has expired.'])
                ->withInput();
        }

        $member = DB::table($this->membersTable)
            ->where('member_id', $reset->reset_member_id)
            ->where('member_active', 'Y')
            ->first();

        if (! $member) {
            return back()
                ->withErrors(['password' => 'This password reset link is invalid or has expired.'])
                ->withInput();
        }

        DB::transaction(function () use ($request, $member, $reset, $now) {
            /*
             * Keep MD5 for now so the existing login continues to work.
             * We will migrate to Laravel Hash::make() later.
             */
            DB::table($this->membersTable)
                ->where('member_id', $member->member_id)
                ->update([
                    'member_password' => md5($request->input('password')),
                ]);

            /*
             * Mark all active reset tokens for this member as used.
             */
            DB::table($this->resetTable)
                ->where('reset_member_id', $member->member_id)
                ->whereNull('reset_used_at')
                ->update([
                    'reset_used_at' => $now,
                    'reset_status'  => 'used',
                    'updated_at'    => $now,
                ]);
        });

        return redirect()
            ->route('login')
            ->with('success', 'Your password has been reset. You can now log in.');
    }

    private function findMatchingActiveMember(string $accountNumber, string $email)
    {
        $identifierColumn = $this->resolveMemberIdentifierColumn();

        if ($identifierColumn === null) {
            Log::error('Member password reset failed: no member identifier column found in sacco_members table.');

            return null;
        }

        return DB::table($this->membersTable)
            ->where($identifierColumn, $accountNumber)
            ->whereRaw('LOWER(TRIM(member_email)) = ?', [$email])
            ->where('member_active', 'Y')
            ->first();
    }

    private function resolveMemberIdentifierColumn(): ?string
    {
        /*
         * Put your real member/account number column first if needed.
         * member_id is deliberately last because it is usually the internal ID.
         */
        $candidateColumns = [
            'member_number',
            'member_no',
            'member_account_number',
            'member_account_no',
            'member_sacco_number',
            'member_sacco_no',
            'member_sacco_id',
            'member_code',
            'member_registration_no',
            'member_id',
        ];

        foreach ($candidateColumns as $column) {
            if (Schema::hasColumn($this->membersTable, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function queuePasswordResetNotification($member, string $resetUrl, string $ip, Carbon $now): void
    {
        $memberName = (string) ($member->member_name ?? 'Member');
        $memberEmail = (string) ($member->member_email ?? '');
        $memberPhone = (string) ($member->member_phone_no ?? '');

        if ($memberEmail === '') {
            return;
        }

        $safeName = htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8');
        $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        $message = "
            
            <p style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
                We received a request to reset the password for your SACCO member portal account.
            </p>

            <p style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
                Click the secure link below to create a new password. This link will expire in 30 minutes.
            </p>

            <p style='font-family: Arial, sans-serif; font-size: 15px; margin: 20px 0;'>
                <a href='{$safeResetUrl}' 
                   style='background:#643A28;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;'>
                    Reset Password
                </a>
            </p>

            <p style='font-family: Arial, sans-serif; font-size: 13px; color: #555;'>
                If the button does not work, copy and paste this link into your browser:<br>
                <a href='{$safeResetUrl}'>{$safeResetUrl}</a>
            </p>

            <p style='font-family: Arial, sans-serif; font-size: 14px; color: #b30000; margin-top: 20px;'>
                If you did not request this password reset, please ignore this message or contact your SACCO administrator.
            </p>

            <p style='font-family: Arial, sans-serif; font-size: 12px; color: #777; margin-top: 20px;'>
                Request IP: {$ip}<br>
                Request Time: {$now->format('Y-m-d H:i:s')}
            </p>
        ";

        DB::table('sacco_system_notifications')->insert([
            'notif_recipient_name'  => $memberName,
            'notif_recipient_email' => $memberEmail,
            'notif_recipient_phone' => $memberPhone,
            'notif_subject'         => 'Reset your SACCO portal password',
            'notif_message'         => $message,
            'notif_status'          => 'unread',
            'notif_type'            => 'password_reset',
            'notif_sent_at'         => null,
            'notif_member_id'       => $member->member_id,
            'notif_created_by'      => $member->member_id,
            'notif_ip'              => $ip,
            'notif_created_at'      => $now->format('Y-m-d H:i:s'),
        ]);
    }

    private function storeResetAttempt(array $payload): void
    {
        DB::table($this->resetTable)->insert($payload);
    }

    private function verifyRecaptcha(Request $request, string $expectedAction): bool
    {
        /*
         * Match your existing login approach:
         * enforce reCAPTCHA only in production.
         */
        if (! app()->environment('production')) {
            return true;
        }

        $token = (string) $request->input('recaptcha_token', '');

        if ($token === '') {
            return false;
        }

        $secret = (string) config('services.recaptcha.secret');

        if ($secret === '') {
            Log::warning('reCAPTCHA secret is missing.');

            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            if (! $response->ok()) {
                Log::warning('reCAPTCHA HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return false;
            }

            $data = $response->json();

            $success = (bool) ($data['success'] ?? false);
            $score = (float) ($data['score'] ?? 0.0);
            $action = (string) ($data['action'] ?? '');
            $errors = $data['error-codes'] ?? [];

            if (! $success || $action !== $expectedAction) {
                Log::warning('reCAPTCHA rejected password reset', [
                    'success' => $success,
                    'score'   => $score,
                    'action'  => $action,
                    'expected_action' => $expectedAction,
                    'errors'  => $errors,
                ]);

                return false;
            }

            /*
             * Password reset is sensitive, but avoid too high a threshold
             * to reduce false positives for real members.
             */
            if ($score < 0.35) {
                Log::warning('reCAPTCHA low score password reset', [
                    'score' => $score,
                    'action' => $action,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verify exception', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normaliseAccountNumber(string $accountNumber): string
    {
        $accountNumber = trim($accountNumber);
        $accountNumber = preg_replace('/\s+/', '', $accountNumber);

        return $accountNumber;
    }
}