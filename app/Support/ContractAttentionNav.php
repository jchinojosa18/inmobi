<?php

namespace App\Support;

use App\Models\Contract;
use Carbon\CarbonImmutable;

final class ContractAttentionNav
{
    /**
     * @return array{count: int, has_expired: bool}
     */
    public static function summary(): array
    {
        if (! (auth()->user()?->can('contracts.view') ?? false)) {
            return ['count' => 0, 'has_expired' => false];
        }

        $today = CarbonImmutable::now('America/Tijuana')->startOfDay();
        $horizon = $today->addDays(Contract::EXPIRING_SOON_DAYS)->toDateString();
        $todayDate = $today->toDateString();

        $base = Contract::query()
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where('contracts.status', Contract::STATUS_ACTIVE)
            ->whereNotNull('contracts.ends_at')
            ->whereDate('contracts.ends_at', '<=', $horizon);

        TenantContext::applyCurrentPlazaFilter($base, 'properties.plaza_id');

        $count = (clone $base)->count('contracts.id');
        $hasExpired = $count > 0
            && (clone $base)->whereDate('contracts.ends_at', '<', $todayDate)->exists();

        return [
            'count' => $count,
            'has_expired' => $hasExpired,
        ];
    }
}
