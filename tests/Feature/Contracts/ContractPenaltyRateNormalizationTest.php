<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\Form;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractPenaltyRateNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentage_input_is_normalized_to_decimal_on_create(): void
    {
        [$organization, $unit, $tenant, $user] = $this->createGraph();

        Livewire::actingAs($user)
            ->test(Form::class)
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('due_day', '1')
            ->set('grace_days', '5')
            ->set('penalty_rate_daily', '5')
            ->set('status', Contract::STATUS_ACTIVE)
            ->set('starts_at', '2026-03-01')
            ->call('save');

        $contract = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsWithDelta(0.05, (float) $contract->penalty_rate_daily, 0.00001);
    }

    public function test_decimal_input_is_preserved_on_create(): void
    {
        [$organization, $unit, $tenant, $user] = $this->createGraph();

        Livewire::actingAs($user)
            ->test(Form::class)
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '9500')
            ->set('deposit_amount', '5000')
            ->set('due_day', '10')
            ->set('grace_days', '5')
            ->set('penalty_rate_daily', '0.05')
            ->set('status', Contract::STATUS_ACTIVE)
            ->set('starts_at', '2026-03-01')
            ->call('save');

        $contract = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsWithDelta(0.05, (float) $contract->penalty_rate_daily, 0.00001);
    }

    public function test_edit_form_displays_percentage_when_stored_rate_is_decimal(): void
    {
        [$organization, $unit, $tenant, $user] = $this->createGraph();

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'penalty_rate_daily' => 0.05,
        ]);

        Livewire::actingAs($user)
            ->test(Form::class, ['contract' => $contract])
            ->assertSet('penalty_rate_daily', '5.0000');
    }

    public function test_it_blocks_rates_over_fifty_percent_daily_after_normalization(): void
    {
        [$organization, $unit, $tenant, $user] = $this->createGraph();

        Livewire::actingAs($user)
            ->test(Form::class)
            ->set('unit_id', $unit->id)
            ->set('tenant_id', $tenant->id)
            ->set('rent_amount', '9000')
            ->set('deposit_amount', '9000')
            ->set('due_day', '5')
            ->set('grace_days', '5')
            ->set('penalty_rate_daily', '60')
            ->set('status', Contract::STATUS_ACTIVE)
            ->set('starts_at', '2026-03-01')
            ->call('save')
            ->assertHasErrors('penalty_rate_daily');

        $this->assertSame(
            0,
            Contract::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->count()
        );
    }

    /**
     * @return array{Organization, Unit, Tenant, User}
     */
    private function createGraph(): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        return [$organization, $unit, $tenant, $user];
    }
}
