<?php

namespace App\Listeners;

use App\Services\SaccoRouteAuditLogger;
use Illuminate\Auth\Events\Failed;

class LogSaccoFailedLogin
{
    public function __construct(
        private SaccoRouteAuditLogger $logger
    ) {
    }

    public function handle(Failed $event): void
    {
        $credentials = $event->credentials ?? [];

        $attemptedLogin = $credentials['email']
            ?? $credentials['username']
            ?? $credentials['user_name']
            ?? request()->input('email')
            ?? request()->input('username')
            ?? request()->input('user_name')
            ?? null;

        $this->logger->logSecurityEvent(
            eventType: 'LOGIN_FAILED',
            outcome: 'FAILED',
            user: $event->user,
            attemptedLogin: $attemptedLogin
        );
    }
}