<?php

namespace App\Listeners;

use App\Services\SaccoRouteAuditLogger;
use Illuminate\Auth\Events\Logout;

class LogSaccoLogout
{
    public function __construct(
        private SaccoRouteAuditLogger $logger
    ) {
    }

    public function handle(Logout $event): void
    {
        $this->logger->logSecurityEvent(
            eventType: 'LOGOUT',
            outcome: 'SUCCESS',
            user: $event->user,
            attemptedLogin: null
        );
    }
}