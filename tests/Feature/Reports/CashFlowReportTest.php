<?php

namespace Tests\Feature\Reports;

use App\Actions\MonthCloses\CloseMonthAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\MonthClose;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_income_expense_and_net_totals_for_selected_range(): void
    {
        [$user] = $this->seedFinanceData();

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Reports\CashFlow::class)
            ->set('date_from', '2026-03-01')
            ->set('date_to', '2026-03-31')
            ->assertSee('$1,500.00')
            ->assertSee('$300.00')
            ->assertSee('$1,200.00')
            ->assertSee('RENT')
            ->assertSee('SERVICE');
    }

    public function test_it_exports_csv_summary_for_selected_range(): void
    {
        [$user] = $this->seedFinanceData();

        $response = $this->actingAs($user)->get(route('reports.flow.export.csv', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('TOTAL_INGRESOS,1500.00', $csv);
        $this->assertStringContainsString('TOTAL_EGRESOS,300.00', $csv);
        $this->assertStringContainsString('NETO,1200.00', $csv);
    }

    public function test_deposit_hold_allocations_are_excluded_from_operating_income(): void
    {
        [$user, $contract] = $this->seedFinanceDataWithContract();

        $rent = Charge::query()->create([
            'organization_id' => $user->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
            'meta' => [],
        ]);

        $depositHold = Charge::query()->create([
            'organization_id' => $user->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 600,
            'meta' => [],
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-10 10:00:00',
            'amount' => 1600,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'P-DEP',
            'receipt_folio' => 'REC-2026-DEP001',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $user->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 1000,
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $user->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $depositHold->id,
            'amount' => 600,
            'meta' => [],
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Reports\CashFlow::class)
            ->set('date_from', '2026-03-01')
            ->set('date_to', '2026-03-31')
            ->assertSee('$1,000.00')
            ->assertDontSee('$1,600.00');
    }

    public function test_payment_covering_rent_and_penalty_is_split_in_allocations_breakdown(): void
    {
        [$user, $contract] = $this->seedFinanceDataWithContract();

        $rent = Charge::query()->create([
            'organization_id' => $user->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
            'meta' => [],
        ]);
        $penalty = Charge::query()->create([
            'organization_id' => $user->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_PENALTY,
            'period' => '2026-03',
            'charge_date' => '2026-03-07',
            'amount' => 120,
            'meta' => [],
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-10 10:00:00',
            'amount' => 1120,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'P-RP',
            'receipt_folio' => 'REC-2026-RP001',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $user->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 1000,
            'meta' => [],
        ]);
        PaymentAllocation::query()->create([
            'organization_id' => $user->organization_id,
            'payment_id' => $payment->id,
            'charge_id' => $penalty->id,
            'amount' => 120,
            'meta' => [],
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Reports\CashFlow::class)
            ->set('date_from', '2026-03-01')
            ->set('date_to', '2026-03-31')
            ->assertSee('RENT')
            ->assertSee('PENALTY')
            ->assertSee('$1,120.00');
    }

    public function test_monthly_report_matches_month_close_snapshot_when_month_is_closed(): void
    {
        [$user] = $this->seedFinanceData();

        app(CloseMonthAction::class)->execute(
            organizationId: (int) $user->organization_id,
            userId: (int) $user->id,
            month: '2026-03',
            notes: 'Close for report parity',
        );

        $snapshot = MonthClose::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $user->organization_id)
            ->where('month', '2026-03')
            ->value('snapshot');

        $this->assertIsArray($snapshot);
        $this->assertSame(1500.0, (float) data_get($snapshot, 'ingresos_operativos'));
        $this->assertSame(300.0, (float) data_get($snapshot, 'egresos'));
        $this->assertSame(1200.0, (float) data_get($snapshot, 'neto'));

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Reports\CashFlow::class)
            ->set('date_from', '2026-03-01')
            ->set('date_to', '2026-03-31')
            ->assertSee('Mes cerrado: el reporte coincide con el snapshot.')
            ->assertSee('$1,500.00')
            ->assertSee('$300.00')
            ->assertSee('$1,200.00');
    }

    /**
     * @return array{0: User}
     */
    private function seedFinanceData(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

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
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => '2026-01-01',
            'ends_at' => null,
        ]);

        $rentMarch = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
            'meta' => [],
        ]);
        $serviceMarch = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_SERVICE,
            'period' => '2026-03',
            'charge_date' => '2026-03-10',
            'amount' => 500,
            'meta' => [],
        ]);
        $rentApril = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-04',
            'charge_date' => '2026-04-01',
            'amount' => 900,
            'meta' => [],
        ]);

        $paymentOne = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-05 10:00:00',
            'amount' => 1000,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'P-1',
            'receipt_folio' => 'REC-2026-000100',
            'meta' => [],
        ]);
        $paymentTwo = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-20 10:00:00',
            'amount' => 500,
            'method' => Payment::METHOD_CASH,
            'reference' => 'P-2',
            'receipt_folio' => 'REC-2026-000101',
            'meta' => [],
        ]);
        $paymentApril = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-04-01 10:00:00',
            'amount' => 900,
            'method' => Payment::METHOD_CASH,
            'reference' => 'P-3',
            'receipt_folio' => 'REC-2026-000102',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $paymentOne->id,
            'charge_id' => $rentMarch->id,
            'amount' => 1000,
            'meta' => [],
        ]);
        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $paymentTwo->id,
            'charge_id' => $serviceMarch->id,
            'amount' => 500,
            'meta' => [],
        ]);
        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $paymentApril->id,
            'charge_id' => $rentApril->id,
            'amount' => 900,
            'meta' => [],
        ]);

        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'category' => 'MANTENIMIENTO',
            'amount' => 300,
            'spent_at' => '2026-03-12',
            'vendor' => 'Proveedor A',
            'notes' => null,
            'meta' => [],
        ]);
        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'category' => 'SERVICIO',
            'amount' => 250,
            'spent_at' => '2026-04-05',
            'vendor' => 'Proveedor B',
            'notes' => null,
            'meta' => [],
        ]);

        return [$user];
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function seedFinanceDataWithContract(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

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
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => '2026-01-01',
            'ends_at' => null,
        ]);

        return [$user, $contract];
    }
}
