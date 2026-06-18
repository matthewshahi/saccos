<?php

namespace App\Listeners;

use App\Services\SaccoRouteAuditLogger;
use Illuminate\Auth\Events\Login;

class LogSaccoSuccessfulLogin
{
    public function __construct(
        private SaccoRouteAuditLogger $logger
    ) {
    }

    public function handle(Login $event): void
    {
        $this->logger->logSecurityEvent(
            eventType: 'LOGIN_SUCCESS',
            outcome: 'SUCCESS',
            user: $event->user,
            attemptedLogin: request()->input('email')
                ?? request()->input('username')
                ?? request()->input('user_name')
        );
    }
}