<?php

namespace App\Support;

final class TenantContext
{
    private static ?int $organizationId = null;

    public static function setOrganizationId(?int $organizationId): void
    {
        self::$organizationId = $organizationId;
    }

    public static function currentOrganizationId(): ?int
    {
        return self::$organizationId;
    }

    public static function clear(): void
    {
        self::$organizationId = null;
    }
}
