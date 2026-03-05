<?php

namespace Tests\Feature\Plazas;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plaza;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlazaScopedScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_plaza_filters_key_screens(): void
    {
        $data = $this->seedTwoPlazaDataset();
        $sessionKey = TenantContext::sessionKeyForCurrentPlaza((int) $data['user']->id);

        $dashboard = $this->actingAs($data['user'])
            ->withSession([$sessionKey => $data['plazaA']->id])
            ->get(route('dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSeeText('Tenant Plaza A');
        $dashboard->assertDontSeeText('Tenant Plaza B');
        $dashboard->assertSeeText('REC-PLAZA-A');
        $dashboard->assertDontSeeText('REC-PLAZA-B');

        $cobranza = $this->actingAs($data['user'])
            ->withSession([$sessionKey => $data['plazaA']->id])
            ->get(route('cobranza.index', ['tab' => 'overdue']));

        $cobranza->assertOk();
        $cobranza->assertSeeText('Tenant Plaza A');
        $cobranza->assertDontSeeText('Tenant Plaza B');

        $contracts = $this->actingAs($data['user'])
            ->withSession([$sessionKey => $data['plazaA']->id])
            ->get(route('contracts.index', ['status' => 'all']));

        $contracts->assertOk();
        $contracts->assertSeeText('Tenant Plaza A');
        $contracts->assertDontSeeText('Tenant Plaza B');

        $expenses = $this->actingAs($data['user'])
            ->withSession([$sessionKey => $data['plazaA']->id])
            ->get(route('expenses.index'));

        $expenses->assertOk();
        $expenses->assertSeeText('EGRESO-PLAZA-A');
        $expenses->assertDontSeeText('EGRESO-PLAZA-B');

        $reports = $this->actingAs($data['user'])
            ->withSession([$sessionKey => $data['plazaA']->id])
            ->get(route('reports.flow', [
                'date_from' => $data['dateFrom'],
                'date_to' => $data['dateTo'],
            ]));

        $reports->assertOk();
        $reports->assertSeeText('Property Plaza A');
        $reports->assertDontSeeText('Property Plaza B');
        $reports->assertSeeText('REC-PLAZA-A');
        $reports->assertDontSeeText('REC-PLAZA-B');
    }

    public function test_all_plazas_selection_shows_data_from_both_plazas(): void
    {
        $data = $this->seedTwoPlazaDataset();
        $sessionKey = TenantContext::sessionKeyForCurrentPlaza((int) $data['user']->id);

        $dashboard = $this->actingAs($data['user'])
            ->withSession([$sessionKey => null])
            ->get(route('dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSeeText('Tenant Plaza A');
        $dashboard->assertSeeText('Tenant Plaza B');

        $cobranza = $this->actingAs($data['user'])
            ->withSession([$sessionKey => null])
            ->get(route('cobranza.index', ['tab' => 'overdue']));

        $cobranza->assertOk();
        $cobranza->assertSeeText('Tenant Plaza A');
        $cobranza->assertSeeText('Tenant Plaza B');

        $contracts = $this->actingAs($data['user'])
            ->withSession([$sessionKey => null])
            ->get(route('contracts.index', ['status' => 'all']));

        $contracts->assertOk();
        $contracts->assertSeeText('Tenant Plaza A');
        $contracts->assertSeeText('Tenant Plaza B');

        $expenses = $this->actingAs($data['user'])
            ->withSession([$sessionKey => null])
            ->get(route('expenses.index'));

        $expenses->assertOk();
        $expenses->assertSeeText('EGRESO-PLAZA-A');
        $expenses->assertSeeText('EGRESO-PLAZA-B');

        $reports = $this->actingAs($data['user'])
            ->withSession([$sessionKey => null])
            ->get(route('reports.flow', [
                'date_from' => $data['dateFrom'],
                'date_to' => $data['dateTo'],
            ]));

        $reports->assertOk();
        $reports->assertSeeText('Property Plaza A');
        $reports->assertSeeText('Property Plaza B');
        $reports->assertSeeText('REC-PLAZA-A');
        $reports->assertSeeText('REC-PLAZA-B');
    }

    /**
     * @return array{
     *     user:User,
     *     plazaA:Plaza,
     *     plazaB:Plaza,
     *     dateFrom:string,
     *     dateTo:string
     * }
     */
    private function seedTwoPlazaDataset(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $plazaA = $organization->defaultPlaza()
            ->withoutOrganizationScope()
            ->firstOrFail();
        $plazaA->update([
            'nombre' => 'Plaza A',
            'timezone' => 'America/Tijuana',
            'created_by_user_id' => $user->id,
        ]);

        $plazaB = Plaza::query()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Plaza B',
            'ciudad' => 'Ensenada',
            'timezone' => 'America/Tijuana',
            'is_default' => false,
            'created_by_user_id' => $user->id,
        ]);

        $today = CarbonImmutable::now('America/Tijuana')->startOfDay();
        $period = $today->format('Y-m');

        $propertyA = Property::factory()->create([
            'organization_id' => $organization->id,
            'plaza_id' => $plazaA->id,
            'name' => 'Property Plaza A',
        ]);
        $propertyB = Property::factory()->create([
            'organization_id' => $organization->id,
            'plaza_id' => $plazaB->id,
            'name' => 'Property Plaza B',
        ]);

        $unitA = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $propertyA->id,
            'name' => 'Unit A-101',
            'code' => 'A101',
            'status' => 'active',
        ]);
        $unitB = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $propertyB->id,
            'name' => 'Unit B-101',
            'code' => 'B101',
            'status' => 'active',
        ]);

        $tenantA = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Tenant Plaza A',
            'email' => 'tenant.a@example.test',
            'phone' => '664-000-0001',
        ]);
        $tenantB = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Tenant Plaza B',
            'email' => 'tenant.b@example.test',
            'phone' => '664-000-0002',
        ]);

        $contractA = $this->createActiveContract((int) $organization->id, (int) $unitA->id, (int) $tenantA->id, $today);
        $contractB = $this->createActiveContract((int) $organization->id, (int) $unitB->id, (int) $tenantB->id, $today);

        $chargeA = $this->createOverdueRentCharge((int) $organization->id, (int) $contractA->id, (int) $unitA->id, $period, $today);
        $chargeB = $this->createOverdueRentCharge((int) $organization->id, (int) $contractB->id, (int) $unitB->id, $period, $today);

        $paymentA = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contractA->id,
            'paid_at' => $today->setTime(10, 0)->toDateTimeString(),
            'amount' => 400,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'PAY-A',
            'receipt_folio' => 'REC-PLAZA-A',
            'meta' => [],
        ]);
        $paymentB = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contractB->id,
            'paid_at' => $today->setTime(11, 0)->toDateTimeString(),
            'amount' => 450,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'PAY-B',
            'receipt_folio' => 'REC-PLAZA-B',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $paymentA->id,
            'charge_id' => $chargeA->id,
            'amount' => 400,
            'meta' => [],
        ]);
        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $paymentB->id,
            'charge_id' => $chargeB->id,
            'amount' => 450,
            'meta' => [],
        ]);

        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitA->id,
            'category' => 'EGRESO-PLAZA-A',
            'amount' => 150,
            'spent_at' => $today->toDateString(),
            'vendor' => 'Proveedor A',
            'notes' => null,
            'meta' => [],
        ]);
        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitB->id,
            'category' => 'EGRESO-PLAZA-B',
            'amount' => 180,
            'spent_at' => $today->toDateString(),
            'vendor' => 'Proveedor B',
            'notes' => null,
            'meta' => [],
        ]);

        return [
            'user' => $user,
            'plazaA' => $plazaA,
            'plazaB' => $plazaB,
            'dateFrom' => $today->startOfMonth()->toDateString(),
            'dateTo' => $today->endOfMonth()->toDateString(),
        ];
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

    private function createOverdueRentCharge(
        int $organizationId,
        int $contractId,
        int $unitId,
        string $period,
        CarbonImmutable $today
    ): Charge {
        return Charge::query()->create([
            'organization_id' => $organizationId,
            'contract_id' => $contractId,
            'unit_id' => $unitId,
            'type' => Charge::TYPE_RENT,
            'period' => $period,
            'charge_date' => $today->subDays(10)->toDateString(),
            'due_date' => $today->subDays(9)->toDateString(),
            'grace_until' => $today->subDays(5)->toDateString(),
            'amount' => 1000,
            'meta' => [],
        ]);
    }
}
