<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\Show;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractShowAdjustmentAccordionTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_panel_starts_closed(): void
    {
        [$user, $contract] = $this->makeContractWithManageChargesUser();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->assertSeeHtml('aria-controls="adjustment-panel"')
            ->assertSeeHtml('id="adjustment-panel"')
            ->assertSeeHtml('aria-expanded="false"')
            ->assertSee(__('contracts.create_adjustment'));
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithManageChargesUser(): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);

        return [$user, $contract];
    }
}
