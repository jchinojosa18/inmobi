<?php

namespace App\Http\Middleware;

use App\Models\Plaza;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        TenantContext::setOrganizationId($organizationId);
        TenantContext::setCurrentPlazaId(
            $this->resolveCurrentPlazaId($request, $user?->id, $organizationId)
        );

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }

    private function resolveCurrentPlazaId(Request $request, ?int $userId, mixed $organizationId): ?int
    {
        $organizationId = is_numeric($organizationId) ? (int) $organizationId : 0;
        if ($organizationId <= 0 || ! $request->hasSession()) {
            return null;
        }

        $selectedPlazaId = TenantContext::readCurrentPlazaIdFromSession($request->session(), $userId);
        if ($selectedPlazaId === null) {
            return null;
        }

        $belongsToOrganization = Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->whereKey($selectedPlazaId)
            ->exists();

        if ($belongsToOrganization) {
            return $selectedPlazaId;
        }

        TenantContext::writeCurrentPlazaIdToSession($request->session(), null, $userId);

        return null;
    }
}
