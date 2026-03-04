<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::setOrganizationId($request->user()?->organization_id);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
