<?php

namespace Tests\Feature\Payments;

use App\Livewire\Payments\QuickRegisterModal;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\MonthClose;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickRegisterModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_is_mounted_in_layout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(QuickRegisterModal::class);
    }

    public function test_opens_without_contract_id_shows_search_step(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open')
            ->assertSet('open', true)
            ->assertSet('step', 'search')
            ->assertSet('contractId', null);
    }

    public function test_opens_with_contract_id_skips_to_form_step(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        [$contract] = $this->createContractGraph($organizationId);

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open', $contract->id)
            ->assertSet('open', true)
            ->assertSet('step', 'form')
            ->assertSet('contractId', $contract->id);
    }

    public function test_search_scoped_to_authenticated_user_organization(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        $user = User::factory()->create(['organization_id' => $org->id]);

        $property = Property::factory()->create(['organization_id' => $org->id, 'name' => 'Prop Mi Org']);
        $unit = Unit::factory()->create(['organization_id' => $org->id, 'property_id' => $property->id, 'name' => 'Unidad A']);
        $tenant = Tenant::factory()->create(['organization_id' => $org->id, 'full_name' => 'Inquilino Mi Org']);
        Contract::withoutEvents(fn () => Contract::query()->create([
            'organization_id' => $org->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 1000,
            'deposit_amount' => 0,
            'due_day' => 1,
            'grace_days' => 5,
            'penalty_rate_daily' => 0,
            'status' => Contract::STATUS_ACTIVE,
            'active_lock' => 1,
            'starts_at' => now()->subMonth()->toDateString(),
            'ends_at' => null,
            'meta' => [],
        ]));

        $otherProperty = Property::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Prop Otra Org']);
        $otherUnit = Unit::factory()->create(['organization_id' => $otherOrg->id, 'property_id' => $otherProperty->id, 'name' => 'Unidad B']);
        $otherTenant = Tenant::factory()->create(['organization_id' => $otherOrg->id, 'full_name' => 'Inquilino Otra Org']);
        Contract::withoutEvents(fn () => Contract::query()->create([
            'organization_id' => $otherOrg->id,
            'unit_id' => $otherUnit->id,
            'tenant_id' => $otherTenant->id,
            'rent_amount' => 1000,
            'deposit_amount' => 0,
            'due_day' => 1,
            'grace_days' => 5,
            'penalty_rate_daily' => 0,
            'status' => Contract::STATUS_ACTIVE,
            'active_lock' => 1,
            'starts_at' => now()->subMonth()->toDateString(),
            'ends_at' => null,
            'meta' => [],
        ]));

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open')
            ->set('q', 'Inquilino')
            ->assertSeeHtml('Inquilino Mi Org')
            ->assertDontSeeHtml('Inquilino Otra Org');
    }

    public function test_save_registers_payment_creates_allocations_and_folio(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        [$contract, $charge] = $this->createContractGraph($organizationId);

        $paidAt = now()->format('Y-m-d\TH:i');

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open', $contract->id)
            ->assertSet('step', 'form')
            ->set('paidAt', $paidAt)
            ->set('amount', '1000')
            ->set('method', Payment::METHOD_TRANSFER)
            ->call('save')
            ->assertSet('step', 'done')
            ->assertSet('receiptFolio', fn ($folio) => is_string($folio) && $folio !== '');

        $this->assertDatabaseHas('payments', [
            'organization_id' => $organizationId,
            'contract_id' => $contract->id,
            'amount' => 1000,
            'method' => Payment::METHOD_TRANSFER,
        ]);

        $this->assertDatabaseHas('payment_allocations', [
            'charge_id' => $charge->id,
        ]);
    }

    public function test_save_blocked_by_closed_month(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        [$contract] = $this->createContractGraph($organizationId);

        $currentMonth = now('America/Tijuana')->format('Y-m');

        MonthClose::query()->withoutOrganizationScope()->create([
            'organization_id' => $organizationId,
            'month' => $currentMonth,
            'closed_at' => now(),
            'closed_by_user_id' => $user->id,
            'snapshot' => [
                'ingresos_operativos' => 0,
                'egresos' => 0,
                'neto' => 0,
                'cartera' => 0,
                'conteos' => [
                    'contratos_activos' => 0,
                    'pagos' => 0,
                    'egresos' => 0,
                ],
            ],
        ]);

        $paidAt = now()->format('Y-m-d\TH:i');

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open', $contract->id)
            ->set('paidAt', $paidAt)
            ->set('amount', '1000')
            ->set('method', Payment::METHOD_TRANSFER)
            ->call('save')
            ->assertSeeHtml('Mes bloqueado')
            ->assertNotSet('step', 'done');
    }

    /**
     * @return array{0: Contract, 1: Charge}
     */
    private function createContractGraph(int $organizationId): array
    {
        $property = Property::factory()->create(['organization_id' => $organizationId]);
        $unit = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'status' => 'active',
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organizationId]);

        $today = CarbonImmutable::now('America/Tijuana');

        $contract = Contract::withoutEvents(fn () => Contract::query()->create([
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
            'starts_at' => $today->subMonth()->startOfMonth()->toDateString(),
            'ends_at' => null,
            'meta' => [],
        ]));

        $charge = Charge::query()->create([
            'organization_id' => $organizationId,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => $today->format('Y-m'),
            'amount' => 1000,
            'charge_date' => $today->startOfMonth()->toDateString(),
            'due_date' => $today->startOfMonth()->addDays(4)->toDateString(),
            'grace_until' => $today->startOfMonth()->addDays(9)->toDateString(),
            'meta' => [],
        ]);

        return [$contract, $charge];
    }
}
