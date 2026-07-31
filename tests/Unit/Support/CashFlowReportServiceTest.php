<?php

namespace Tests\Unit\Support;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Actions\MonthCloses\CloseMonthAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plaza;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\CashFlowReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_plaza_scope_includes_general_expenses_and_excludes_other_plaza(): void
    {
        [$organization, $plazaA, $plazaB, $unitA] = $this->seedOrgWithTwoPlazas();

        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $organization->id);
        $categoryId = (int) ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('name', 'MANTENIMIENTO')
            ->value('id');

        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => null,
            'expense_category_id' => $categoryId,
            'amount' => 50,
            'spent_at' => '2026-03-10',
            'vendor' => 'General',
            'notes' => null,
            'meta' => [],
        ]);
        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitA->id,
            'expense_category_id' => $categoryId,
            'amount' => 80,
            'spent_at' => '2026-03-11',
            'vendor' => 'Plaza A',
            'notes' => null,
            'meta' => [],
        ]);

        $unitB = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => Property::factory()->create([
                'organization_id' => $organization->id,
                'plaza_id' => $plazaB->id,
                'name' => 'PROPERTY PLAZA B',
            ])->id,
        ]);
        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unitB->id,
            'expense_category_id' => $categoryId,
            'amount' => 999,
            'spent_at' => '2026-03-12',
            'vendor' => 'Plaza B',
            'notes' => null,
            'meta' => [],
        ]);

        $from = CarbonImmutable::parse('2026-03-01', 'America/Tijuana')->startOfDay();
        $to = CarbonImmutable::parse('2026-03-31', 'America/Tijuana')->endOfDay();

        $report = app(CashFlowReportService::class)->build(
            (int) $organization->id,
            $from,
            $to,
            (int) $plazaA->id,
        );

        $this->assertSame(130.0, $report['expenseTotal']);
        $this->assertSame(2, $report['expenseCount']);
        $this->assertTrue($report['expenses']->contains(fn ($e) => $e->vendor === 'General'));
        $this->assertFalse($report['expenses']->contains(fn ($e) => $e->vendor === 'Plaza B'));
    }

    public function test_snapshot_only_when_full_month_and_no_plaza(): void
    {
        [$user, $organization] = $this->seedIncomeAndExpenseForMarch();

        app(CloseMonthAction::class)->execute(
            organizationId: (int) $organization->id,
            userId: (int) $user->id,
            month: '2026-03',
            notes: 'parity',
        );

        $from = CarbonImmutable::parse('2026-03-01', 'America/Tijuana')->startOfDay();
        $to = CarbonImmutable::parse('2026-03-31', 'America/Tijuana')->endOfDay();
        $service = app(CashFlowReportService::class);

        $orgWide = $service->build((int) $organization->id, $from, $to, null);
        $this->assertIsArray($orgWide['closedMonthSnapshot']);
        $this->assertTrue($orgWide['snapshotMatches']);

        $plaza = Plaza::factory()->create(['organization_id' => $organization->id]);
        $withPlaza = $service->build((int) $organization->id, $from, $to, (int) $plaza->id);
        $this->assertNull($withPlaza['closedMonthSnapshot']);
        $this->assertNull($withPlaza['snapshotMatches']);

        $partial = $service->build(
            (int) $organization->id,
            $from,
            CarbonImmutable::parse('2026-03-15', 'America/Tijuana')->endOfDay(),
            null,
        );
        $this->assertNull($partial['closedMonthSnapshot']);
    }

    /**
     * @return array{0: Organization, 1: Plaza, 2: Plaza, 3: Unit}
     */
    private function seedOrgWithTwoPlazas(): array
    {
        $organization = Organization::factory()->create();
        $plazaA = Plaza::factory()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Plaza A',
            'is_default' => true,
        ]);
        $plazaB = Plaza::factory()->create([
            'organization_id' => $organization->id,
            'nombre' => 'Plaza B',
            'is_default' => false,
        ]);
        $propertyA = Property::factory()->create([
            'organization_id' => $organization->id,
            'plaza_id' => $plazaA->id,
            'name' => 'PROPERTY PLAZA A',
        ]);
        $unitA = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $propertyA->id,
        ]);

        return [$organization, $plazaA, $plazaB, $unitA];
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function seedIncomeAndExpenseForMarch(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
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

        $rent = Charge::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 1000,
            'meta' => [],
        ]);
        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'paid_at' => '2026-03-05 10:00:00',
            'amount' => 1000,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'P-1',
            'receipt_folio' => 'REC-CF-1',
            'meta' => [],
        ]);
        PaymentAllocation::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 1000,
            'meta' => [],
        ]);

        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $organization->id);
        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('name', 'MANTENIMIENTO')
            ->value('id');

        Expense::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'expense_category_id' => $categoryId,
            'amount' => 300,
            'spent_at' => '2026-03-12',
            'vendor' => 'Proveedor',
            'notes' => null,
            'meta' => [],
        ]);

        return [$user, $organization];
    }
}
