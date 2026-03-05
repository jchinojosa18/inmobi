<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'key', 'hash'];

    /**
     * Log a business action to the audit trail.
     *
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $action,
        ?Model $auditable,
        string $summary,
        array $meta = [],
        ?int $organizationId = null,
        ?int $actorUserId = null,
    ): void {
        try {
            $user = auth()->user();
            $resolvedOrgId = $organizationId
                ?? $user?->organization_id
                ?? TenantContext::currentOrganizationId();

            AuditEvent::create([
                'organization_id' => $resolvedOrgId,
                'plaza_id' => TenantContext::currentPlazaId(),
                'actor_user_id' => $actorUserId ?? $user?->id,
                'action' => $action,
                'auditable_type' => $auditable !== null ? get_class($auditable) : null,
                'auditable_id' => $auditable?->getKey(),
                'occurred_at' => now(),
                'ip' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
                'summary' => $summary,
                'meta' => $this->sanitize($meta),
            ]);
        } catch (\Throwable) {
            // Never block business logic due to audit logging failure.
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function sanitize(array $meta): array
    {
        foreach ($meta as $key => $value) {
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains(strtolower((string) $key), $sensitive)) {
                    $meta[$key] = '[redacted]';
                    break;
                }
            }
        }

        return $meta;
    }
}
