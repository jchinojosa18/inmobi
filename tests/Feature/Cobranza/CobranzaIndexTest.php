<?php

namespace Tests\Feature\Cobranza;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CobranzaIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_cobranza_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cobranza.index'));

        $response->assertOk();
        $response->assertSeeText('Cobranza');
        $response->assertSeeText('Vencidos');
        $response->assertSeeText('En gracia');
        $response->assertSeeText('Al corriente');
    }

    public function test_it_lists_contract_in_overdue_tab_with_real_pending_balance(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;
        $today = CarbonImmutable::now('America/Tijuana')->startOfDay();

        $property = Property::factory()->create([
            'organization_id' => $organizationId,
            'name' => 'Propiedad Centro',
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'name' => 'Casa 1',
            'code' => 'C1',
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $organizationId,
            'full_name' => 'Carlos Vencido',
        ]);

        $contract = Contract::withoutEvents(function () use ($organizationId, $unit, $tenant, $today): Contract {
            return Contract::query()->create([
                'organization_id' => $organizationId,
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'rent_amount' => 1000,
                'deposit_amount' => 1000,
                'due_day' => 1,
                'grace_days' => 5,
                'penalty_rate_daily' => 0.01,
                'status' => Contract::STATUS_ACTIVE,
                'active_lock' => 1,
                'starts_at' => $today->subMonths(2)->startOfMonth()->toDateString(),
                'ends_at' => null,
                'meta' => [],
            ]);
        });

        $rentCharge = Charge::query()->create([
            'organization_id' => $organizationId,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => $today->subMonthNoOverflow()->format('Y-m'),
            'charge_date' => $today->subDays(10)->toDateString(),
            'due_date' => $today->subDays(9)->toDateString(),
            'grace_until' => $today->subDays(5)->toDateString(),
            'amount' => 1000,
            'meta' => [],
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organizationId,
            'contract_id' => $contract->id,
            'paid_at' => $today->setTime(11, 0)->toDateTimeString(),
            'amount' => 250,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'COB-TEST',
            'receipt_folio' => 'REC-COB-0001',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $organizationId,
            'payment_id' => $payment->id,
            'charge_id' => $rentCharge->id,
            'amount' => 250,
            'meta' => [],
        ]);

        $response = $this->actingAs($user)->get(route('cobranza.index', ['tab' => 'overdue']));

        $response->assertOk();
        $response->assertSeeText('Carlos Vencido');
        $response->assertSeeText('$750.00');
        $response->assertSee(route('contracts.payments.create', $contract->id), false);
        $response->assertSeeText('Copiar mensaje WhatsApp');
    }

    public function test_it_applies_filters_by_property_and_tenant_search(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;
        $today = CarbonImmutable::now('America/Tijuana')->startOfDay();

        $propertyA = Property::factory()->create([
            'organization_id' => $organizationId,
            'name' => 'Edificio A',
        ]);
        $propertyB = Property::factory()->create([
            'organization_id' => $organizationId,
            'name' => 'Edificio B',
        ]);

        $unitA = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $propertyA->id,
        ]);
        $unitB = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $propertyB->id,
        ]);

        $tenantA = Tenant::factory()->create([
            'organization_id' => $organizationId,
            'full_name' => 'Ana Filtro',
        ]);
        $tenantB = Tenant::factory()->create([
            'organization_id' => $organizationId,
            'full_name' => 'Bruno Filtro',
        ]);

        $contractA = $this->createActiveContract($organizationId, $unitA->id, $tenantA->id, $today);
        $contractB = $this->createActiveContract($organizationId, $unitB->id, $tenantB->id, $today);

        $this->createCurrentRentPendingCharge($organizationId, $contractA->id, $unitA->id, $today);
        $this->createCurrentRentPendingCharge($organizationId, $contractB->id, $unitB->id, $today);

        $response = $this->actingAs($user)->get(route('cobranza.index', [
            'tab' => 'current',
            'property_id' => $propertyA->id,
            'q' => 'Ana',
        ]));

        $response->assertOk();
        $response->assertSeeText('Ana Filtro');
        $response->assertDontSeeText('Bruno Filtro');
    }

    private function createActiveContract(int $organizationId, int $unitId, int $tenantId, CarbonImmutable $today): Contract
    {
        return Contract::withoutEvents(function () use ($organizationId, $unitId, $tenantId, $today): Contract {
            return Contract::query()->create([
                'organization_id' => $organizationId,
                'unit_id' => $unitId,
                'tenant_id' => $tenantId,
                'rent_amount' => 1000,
                'deposit_amount' => 1000,
                'due_day' => 1,
                'grace_days' => 5,
                'penalty_rate_daily' => 0.01,
                'status' => Contract::STATUS_ACTIVE,
                'active_lock' => 1,
                'starts_at' => $today->subMonths(2)->startOfMonth()->toDateString(),
                'ends_at' => null,
                'meta' => [],
            ]);
        });
    }

    private function createCurrentRentPendingCharge(int $organizationId, int $contractId, int $unitId, CarbonImmutable $today): void
    {
        Charge::query()->create([
            'organization_id' => $organizationId,
            'contract_id' => $contractId,
            'unit_id' => $unitId,
            'type' => Charge::TYPE_RENT,
            'period' => $today->format('Y-m'),
            'charge_date' => $today->toDateString(),
            'due_date' => $today->addDays(7)->toDateString(),
            'grace_until' => $today->addDays(12)->toDateString(),
            'amount' => 500,
            'meta' => [],
        ]);
    }
}
