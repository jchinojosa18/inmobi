<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\SettlementWizard;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettlementWizardAccordionTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_starts_closed_with_summary_in_header(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 10000.0);

        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->assertSeeHtml('x-data="{ open: false }"')
            ->assertSeeHtml('aria-expanded="false"')
            ->assertSee(__('contracts.settlement_title'))
            ->assertSee(__('contracts.deposit_paid'))
            ->assertSee(__('contracts.current_outstanding'))
            ->assertSee('$0.00')
            ->assertSee(__('contracts.deposit_applied'))
            ->assertSee(__('contracts.deposit_refunded'))
            ->assertSee(__('contracts.available'))
            ->assertSee(__('contracts.settlement_description'));
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithUser(float $depositAmount): array
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
            'deposit_amount' => $depositAmount,
            'rent_amount' => 0,
            'status' => Contract::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);

        return [$user, $contract];
    }
}
