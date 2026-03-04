<?php

namespace App\Http\Middleware;

use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAuditReason
{
    /**
     * Capture audit reason from request input/header for write operations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $reason = $request->input('audit_reason', $request->header('X-Audit-Reason'));
            AuditContext::setReason(is_string($reason) ? $reason : null);
        }

        try {
            return $next($request);
        } finally {
            AuditContext::clear();
        }
    }
}
