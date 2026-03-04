<?php

namespace Tests\Feature\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_real_statement_totals_in_contract_show(): void
    {
        [$organization, $contract] = $this->createContractGraph();

        $rentCharge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
            'meta' => [
                'due_date' => '2026-03-05',
                'grace_days' => 5,
            ],
        ]);

        $serviceCharge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_SERVICE,
            'period' => '2026-03',
            'charge_date' => '2026-03-02',
            'amount' => 500,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 900,
            'paid_at' => '2026-03-04 12:00:00',
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rentCharge->id,
            'amount' => 700,
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $serviceCharge->id,
            'amount' => 200,
        ]);

        CreditBalance::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'last_payment_id' => $payment->id,
            'balance' => 150,
        ]);

        $response = $this
            ->actingAs($this->createUserForOrganization($organization))
            ->get(route('contracts.show', $contract));

        $response->assertOk();
        $response->assertSeeText('Estado de cuenta');
        $response->assertSeeText('$1,500.00');
        $response->assertSeeText('$900.00');
        $response->assertSeeText('$600.00');
        $response->assertSeeText('$150.00');
    }

    public function test_partial_charge_displays_partial_status_and_correct_balance(): void
    {
        [$organization, $contract] = $this->createContractGraph();

        $charge = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_SERVICE,
            'period' => '2026-03',
            'charge_date' => '2026-03-10',
            'amount' => 1000,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 250,
            'paid_at' => '2026-03-11 13:00:00',
        ]);

        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $charge->id,
            'amount' => 250,
        ]);

        $response = $this
            ->actingAs($this->createUserForOrganization($organization))
            ->get(route('contracts.show', $contract));

        $response->assertOk();
        $response->assertSeeText('Parcial');
        $response->assertSeeText('$750.00');
    }

    /**
     * @return array{Organization, Contract}
     */
    private function createContractGraph(): array
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

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ENDED,
            'grace_days' => 5,
            'ends_at' => '2026-12-31',
        ]);

        return [$organization, $contract];
    }

    private function createUserForOrganization(Organization $organization): User
    {
        return User::factory()->create([
            'organization_id' => $organization->id,
        ]);
    }
}
