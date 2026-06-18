<?php

namespace App\Http\Middleware;

use App\Services\SaccoRouteAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SaccoRouteAuditLog
{
    public function __construct(
        private SaccoRouteAuditLogger $logger
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        if (!$request->attributes->has('sacco_route_audit_request_id')) {
            $request->attributes->set('sacco_route_audit_request_id', (string) Str::uuid());
        }

        try {
            $response = $next($request);

            $statusCode = $response->getStatusCode();

            $this->logger->logRouteRequest(
                request: $request,
                eventType: $this->logger->eventTypeFromRequest($request),
                outcome: $this->logger->outcomeFromStatus($statusCode),
                statusCode: $statusCode,
                startedAt: $startedAt
            );

            return $response;
        } catch (Throwable $e) {
            $this->logger->logRouteRequest(
                request: $request,
                eventType: $this->logger->eventTypeFromRequest($request),
                outcome: 'ERROR',
                statusCode: 500,
                startedAt: $startedAt,
                exception: $e
            );

            throw $e;
        }
    }
}