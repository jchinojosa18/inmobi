<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            if (! app()->bound('request')) {
                return;
            }

            $organizationId = TenantContext::currentOrganizationId()
                ?? Auth::user()?->organization_id;

            if ($organizationId === null) {
                // Fail closed when tenant context is missing to avoid cross-organization leaks.
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('organization_id'),
                $organizationId
            );
        });

        static::creating(function (Model $model): void {
            // Authenticated HTTP requests: always stamp the current tenant org.
            // Never trust a client-supplied organization_id while a user is logged in.
            if (Auth::check()) {
                $organizationId = TenantContext::currentOrganizationId()
                    ?? Auth::user()?->organization_id;

                if ($organizationId !== null) {
                    $model->setAttribute('organization_id', $organizationId);
                }

                return;
            }

            // Console/jobs/tests without auth may set organization_id explicitly.
            if ($model->getAttribute('organization_id') !== null) {
                return;
            }

            $organizationId = TenantContext::currentOrganizationId();

            if ($organizationId !== null) {
                $model->setAttribute('organization_id', $organizationId);
            }
        });
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->withoutGlobalScope('organization')
            ->where($this->qualifyColumn('organization_id'), $organizationId);
    }

    public function scopeWithoutOrganizationScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('organization');
    }
}
