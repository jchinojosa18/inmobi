<?php

namespace Tests\Feature\MonthCloses;

use App\Actions\MonthCloses\CloseMonthAction;
use App\Livewire\MonthCloses\Index as MonthClosesIndex;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\MonthClose;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonthCloseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_month_blocks_creating_payment_and_expense_in_that_month(): void
    {
        [$user, $contract, $unit] = $this->createGraph();

        app(CloseMonthAction::class)->execute(
            organizationId: (int) $user->organization_id,
            userId: (int) $user->id,
            month: '2026-03',
            notes: 'Cierre de prueba',
        );

        try {
            Payment::query()->withoutOrganizationScope()->create([
                'organization_id' => $user->organization_id,
                'contract_id' => $contract->id,
                'paid_at' => '2026-03-12 10:00:00',
                'amount' => 1200,
                'method' => Payment::METHOD_TRANSFER,
                'reference' => 'LOCK-TEST',
                'receipt_folio' => 'REC-2026-LOCK-001',
                'meta' => [],
            ]);

            $this->fail('Expected payment creation to be blocked in closed month.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Mes bloqueado: 2026-03', $exception->errors()['month_close'][0] ?? '');
        }

        try {
            Expense::query()->withoutOrganizationScope()->create([
                'organization_id' => $user->organization_id,
                'unit_id' => $unit->id,
                'category' => 'MANTENIMIENTO',
                'amount' => 350,
                'spent_at' => '2026-03-15',
                'vendor' => 'Proveedor',
                'notes' => null,
                'meta' => [],
            ]);

            $this->fail('Expected expense creation to be blocked in closed month.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Mes bloqueado: 2026-03', $exception->errors()['month_close'][0] ?? '');
        }
    }

    public function test_it_allows_creating_adjustment_with_reason_in_closed_month(): void
    {
        [$user, $contract, $unit] = $this->createGraph();

        app(CloseMonthAction::class)->execute(
            organizationId: (int) $user->organization_id,
            userId: (int) $user->id,
            month: '2026-03',
            notes: null,
        );

        $adjustment = Charge::query()->withoutOrganizationScope()->create([
            'organization_id' => $user->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_ADJUSTMENT,
            'period' => '2026-03',
            'charge_date' => '2026-03-20',
            'amount' => -150,
            'meta' => [
                'reason' => 'Correccion de captura',
                'linked_to' => 'payment:1',
                'comment' => 'Ajuste manual autorizado',
            ],
        ]);

        $this->assertNotNull($adjustment->id);
        $this->assertSame(Charge::TYPE_ADJUSTMENT, $adjustment->type);
    }

    public function test_only_admin_can_reopen_closed_month(): void
    {
        Permission::findOrCreate('month_close.view', 'web');
        Role::findOrCreate('MonthViewer', 'web')->syncPermissions(['month_close.view']);

        [$user] = $this->createGraph();
        $user->syncRoles(['MonthViewer']);

        MonthClose::query()->withoutOrganizationScope()->create([
            'organization_id' => $user->organization_id,
            'month' => '2026-03',
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

        $this->actingAs($user);

        Livewire::test(MonthClosesIndex::class)
            ->call('reopenMonth', '2026-03')
            ->assertForbidden();

        $admin = User::factory()->create([
            'organization_id' => $user->organization_id,
            'email' => 'admin-reopen@example.com',
            'password' => 'password',
        ]);
        Role::findOrCreate('Admin', 'web');
        $admin->assignRole('Admin');

        $this->actingAs($admin);

        Livewire::test(MonthClosesIndex::class)
            ->call('reopenMonth', '2026-03');

        $this->assertFalse(
            MonthClose::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $user->organization_id)
                ->where('month', '2026-03')
                ->exists()
        );
    }

    /**
     * @return array{0: User, 1: Contract, 2: Unit}
     */
    private function createGraph(): array
    {
        $user = User::factory()->create();

        $property = Property::factory()->create([
            'organization_id' => $user->organization_id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $user->organization_id,
            'property_id' => $property->id,
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $user->organization_id,
        ]);

        $contract = Contract::factory()->ended()->create([
            'organization_id' => $user->organization_id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
        ]);

        return [$user, $contract, $unit];
    }
}
