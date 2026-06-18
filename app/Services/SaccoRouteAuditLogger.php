<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaccoRouteAuditLogger
{
    public function logRouteRequest(
        Request $request,
        string $eventType,
        string $outcome,
        ?int $statusCode = null,
        ?float $startedAt = null,
        ?Throwable $exception = null
    ): void {
        try {
            if ($this->shouldSkip($request)) {
                return;
            }

            $user = Auth::user();

            DB::table('sacco_route_audit_logs')->insert([
                'route_audit_log_request_id' => $request->attributes->get('sacco_route_audit_request_id'),

                'route_audit_log_event_type' => $eventType,
                'route_audit_log_outcome' => $outcome,

                'route_audit_log_user_id' => $this->getUserId($user),
                'route_audit_log_user_name' => $this->getUserName($user),
                'route_audit_log_user_email' => $this->getUserEmail($user),

                'route_audit_log_attempted_login' => $this->getAttemptedLogin($request),

                'route_audit_log_route_name' => $request->route()?->getName(),
                'route_audit_log_controller_action' => $request->route()?->getActionName(),

                'route_audit_log_method' => $request->method(),
                'route_audit_log_path' => $request->path(),
                'route_audit_log_url' => $request->fullUrl(),
                'route_audit_log_referer' => $request->headers->get('referer'),

                'route_audit_log_status_code' => $statusCode,

                'route_audit_log_ip_address' => $request->ip(),
                'route_audit_log_user_agent' => $request->userAgent(),

                'route_audit_log_payload' => json_encode($this->safePayload($request)),
                'route_audit_log_files' => json_encode($this->filePayload($request)),

                'route_audit_log_exception_class' => $exception ? get_class($exception) : null,
                'route_audit_log_exception_message' => $exception ? substr($exception->getMessage(), 0, 5000) : null,

                'route_audit_log_duration_ms' => $startedAt
                    ? (int) round((microtime(true) - $startedAt) * 1000)
                    : null,

                'route_audit_log_created_at' => now(),
            ]);
        } catch (Throwable $e) {
            /**
             * Audit logging must never break the SACCO app.
             */
            report($e);
        }
    }

    public function logSecurityEvent(
        string $eventType,
        string $outcome,
        mixed $user = null,
        ?string $attemptedLogin = null,
        ?Request $request = null
    ): void {
        try {
            $request = $request ?: request();

            DB::table('sacco_route_audit_logs')->insert([
                'route_audit_log_request_id' => $request->attributes->get('sacco_route_audit_request_id'),

                'route_audit_log_event_type' => $eventType,
                'route_audit_log_outcome' => $outcome,

                'route_audit_log_user_id' => $this->getUserId($user),
                'route_audit_log_user_name' => $this->getUserName($user),
                'route_audit_log_user_email' => $this->getUserEmail($user),

                'route_audit_log_attempted_login' => $attemptedLogin ?: $this->getAttemptedLogin($request),

                'route_audit_log_route_name' => $request->route()?->getName(),
                'route_audit_log_controller_action' => $request->route()?->getActionName(),

                'route_audit_log_method' => $request->method(),
                'route_audit_log_path' => $request->path(),
                'route_audit_log_url' => $request->fullUrl(),
                'route_audit_log_referer' => $request->headers->get('referer'),

                'route_audit_log_status_code' => null,

                'route_audit_log_ip_address' => $request->ip(),
                'route_audit_log_user_agent' => $request->userAgent(),

                'route_audit_log_payload' => json_encode([
                    'attempted_login' => $attemptedLogin ?: $this->getAttemptedLogin($request),
                ]),

                'route_audit_log_files' => null,

                'route_audit_log_exception_class' => null,
                'route_audit_log_exception_message' => null,
                'route_audit_log_duration_ms' => null,

                'route_audit_log_created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function eventTypeFromRequest(Request $request): string
    {
        $routeName = strtolower((string) $request->route()?->getName());
        $path = strtolower($request->path());

        if (str_contains($routeName, 'login') || str_contains($path, 'login')) {
            return 'LOGIN_REQUEST';
        }

        if (str_contains($routeName, 'logout') || str_contains($path, 'logout')) {
            return 'LOGOUT_REQUEST';
        }

        if (str_contains($routeName, 'import') || str_contains($path, 'import')) {
            return 'IMPORT_REQUEST';
        }

        if (str_contains($routeName, 'export') || str_contains($path, 'export')) {
            return 'EXPORT_REQUEST';
        }

        if (str_contains($routeName, 'approve') || str_contains($path, 'approve')) {
            return 'APPROVAL_REQUEST';
        }

        if (str_contains($routeName, 'approval') || str_contains($path, 'approval')) {
            return 'APPROVAL_REQUEST';
        }

        if (str_contains($routeName, 'reverse') || str_contains($path, 'reverse')) {
            return 'REVERSAL_REQUEST';
        }

        if (str_contains($routeName, 'reversal') || str_contains($path, 'reversal')) {
            return 'REVERSAL_REQUEST';
        }

        if (str_contains($routeName, 'delete') || str_contains($path, 'delete')) {
            return 'DELETE_REQUEST';
        }

        if (str_contains($routeName, 'remove') || str_contains($path, 'remove')) {
            return 'DELETE_REQUEST';
        }

        if (str_contains($routeName, 'report') || str_contains($path, 'report')) {
            return 'REPORT_REQUEST';
        }

        if (str_contains($routeName, 'sasra') || str_contains($path, 'sasra')) {
            return 'SASRA_REQUEST';
        }

        if ($request->isMethod('GET')) {
            return 'PAGE_VIEW';
        }

        if ($request->isMethod('POST')) {
            return 'FORM_SUBMIT';
        }

        if ($request->isMethod('PUT') || $request->isMethod('PATCH')) {
            return 'UPDATE_REQUEST';
        }

        if ($request->isMethod('DELETE')) {
            return 'DELETE_REQUEST';
        }

        return 'ROUTE_REQUEST';
    }

    public function outcomeFromStatus(?int $statusCode): string
    {
        if (!$statusCode) {
            return 'UNKNOWN';
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return 'SUCCESS';
        }

        if ($statusCode >= 300 && $statusCode < 400) {
            return 'REDIRECT';
        }

        if ($statusCode === 401) {
            return 'UNAUTHENTICATED';
        }

        if ($statusCode === 403) {
            return 'FORBIDDEN';
        }

        if ($statusCode === 404) {
            return 'NOT_FOUND';
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return 'FAILED';
        }

        if ($statusCode >= 500) {
            return 'ERROR';
        }

        return 'UNKNOWN';
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is(
            '_debugbar/*',
            'telescope/*',
            'horizon/*',
            'livewire/*',
            'storage/*',
            'css/*',
            'js/*',
            'images/*',
            'img/*',
            'assets/*',
            'vendor/*',
            'favicon.ico',
            'robots.txt'
        );
    }

    private function safePayload(Request $request): array
    {
        $blocked = [
            '_token',
            '_method',

            'password',
            'password_confirmation',
            'current_password',
            'old_password',
            'new_password',

            'token',
            'remember_token',
            'api_token',
            'access_token',
            'refresh_token',

            'otp',
            'pin',
            'secret',
            'private_key',

            'recaptcha_token',
            'g-recaptcha-response',
        ];

        $payload = $request->except($blocked);

        /**
         * Avoid storing very large request payloads.
         */
        return collect($payload)
            ->take(100)
            ->toArray();
    }

    private function filePayload(Request $request): array
    {
        $files = [];

        foreach ($request->allFiles() as $key => $file) {
            if (is_array($file)) {
                foreach ($file as $index => $singleFile) {
                    if ($singleFile && method_exists($singleFile, 'getClientOriginalName')) {
                        $files[$key . '.' . $index] = [
                            'name' => $singleFile->getClientOriginalName(),
                            'mime' => $singleFile->getClientMimeType(),
                            'size' => $singleFile->getSize(),
                        ];
                    }
                }
            } else {
                if ($file && method_exists($file, 'getClientOriginalName')) {
                    $files[$key] = [
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ];
                }
            }
        }

        return $files;
    }

    private function getAttemptedLogin(Request $request): ?string
    {
        return $request->input('login')
            ?? $request->input('email')
            ?? $request->input('username')
            ?? $request->input('user_name')
            ?? $request->input('phone')
            ?? $request->input('user_email')
            ?? $request->input('member_sacco_id')
            ?? null;
    }

    private function getUserId(mixed $user): ?int
    {
        if (!$user) {
            return null;
        }

        return $user->id
            ?? $user->user_id
            ?? $user->admin_id
            ?? $user->staff_id
            ?? $user->member_id
            ?? null;
    }

    private function getUserName(mixed $user): ?string
    {
        if (!$user) {
            return null;
        }

        return $user->name
            ?? $user->user_name
            ?? $user->username
            ?? $user->full_name
            ?? $user->user_full_name
            ?? $user->user_fullname
            ?? $user->admin_name
            ?? $user->staff_name
            ?? $user->member_name
            ?? null;
    }

    private function getUserEmail(mixed $user): ?string
    {
        if (!$user) {
            return null;
        }

        return $user->email
            ?? $user->user_email
            ?? $user->admin_email
            ?? $user->staff_email
            ?? $user->member_email
            ?? null;
    }
}