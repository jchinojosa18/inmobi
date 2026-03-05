<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;

final class TenantContext
{
    private static ?int $organizationId = null;

    private static ?int $plazaId = null;

    private const PLAZA_SESSION_KEY_PREFIX = 'tenant.current_plaza_id.';

    public static function setOrganizationId(?int $organizationId): void
    {
        self::$organizationId = self::normalizeNullablePositiveInt($organizationId);
    }

    public static function currentOrganizationId(): ?int
    {
        return self::$organizationId;
    }

    public static function setCurrentPlazaId(?int $plazaId): void
    {
        self::$plazaId = self::normalizeNullablePositiveInt($plazaId);
    }

    public static function currentPlazaId(): ?int
    {
        return self::$plazaId;
    }

    public static function applyCurrentPlazaFilter(Builder $query, string $column = 'properties.plaza_id'): Builder
    {
        $plazaId = self::currentPlazaId();
        if ($plazaId === null) {
            return $query;
        }

        return $query->where($column, $plazaId);
    }

    public static function sessionKeyForCurrentPlaza(?int $userId = null): ?string
    {
        $userId = self::normalizeNullablePositiveInt($userId);
        if ($userId === null) {
            return null;
        }

        return self::PLAZA_SESSION_KEY_PREFIX.$userId;
    }

    public static function readCurrentPlazaIdFromSession(Session $session, ?int $userId = null): ?int
    {
        $key = self::sessionKeyForCurrentPlaza($userId);
        if ($key === null) {
            return null;
        }

        return self::normalizeNullablePositiveInt($session->get($key));
    }

    public static function writeCurrentPlazaIdToSession(Session $session, ?int $plazaId, ?int $userId = null): void
    {
        $key = self::sessionKeyForCurrentPlaza($userId);
        if ($key === null) {
            return;
        }

        $plazaId = self::normalizeNullablePositiveInt($plazaId);
        if ($plazaId === null) {
            $session->forget($key);

            return;
        }

        $session->put($key, $plazaId);
    }

    public static function clear(): void
    {
        self::$organizationId = null;
        self::$plazaId = null;
    }

    private static function normalizeNullablePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
