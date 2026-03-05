<?php

namespace Tests\Feature\Migrations;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizeContractPenaltyRateMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_divides_only_rates_greater_than_one_by_one_hundred(): void
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unitA = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $unitB = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $tenantA = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $tenantB = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $contractPercent = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitA->id,
            'tenant_id' => $tenantA->id,
            'penalty_rate_daily' => 5.0000,
        ]);

        $contractDecimal = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitB->id,
            'tenant_id' => $tenantB->id,
            'penalty_rate_daily' => 0.0500,
        ]);

        DB::table('contracts')
            ->where('id', $contractPercent->id)
            ->update(['penalty_rate_daily' => 5.0000]);

        DB::table('contracts')
            ->where('id', $contractDecimal->id)
            ->update(['penalty_rate_daily' => 0.0500]);

        $migrationPath = database_path('migrations/2026_03_05_130000_normalize_contract_penalty_rate_daily_values.php');

        /** @var object{up:callable} $migration */
        $migration = require $migrationPath;
        $migration->up();

        $normalizedPercent = (float) DB::table('contracts')->where('id', $contractPercent->id)->value('penalty_rate_daily');
        $normalizedDecimal = (float) DB::table('contracts')->where('id', $contractDecimal->id)->value('penalty_rate_daily');

        $this->assertEqualsWithDelta(0.05, $normalizedPercent, 0.00001);
        $this->assertEqualsWithDelta(0.05, $normalizedDecimal, 0.00001);
    }
}
