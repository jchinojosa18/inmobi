<?php

namespace Tests\Feature\Expenses;

use App\Livewire\Expenses\QuickRegisterModal;
use App\Models\Expense;
use App\Models\MonthClose;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
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
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertSeeLivewire(QuickRegisterModal::class);
    }

    public function test_opens_on_event_shows_form(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open')
            ->assertSet('open', true)
            ->assertSet('scope', 'general')
            ->assertSet('unitId', null);
    }

    public function test_opens_with_unit_id_preselects_unit_scope(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        $property = Property::factory()->create(['organization_id' => $organizationId, 'name' => 'Torre A']);
        $unit = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'name' => 'Depto 101',
            'code' => '101',
        ]);

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open', $unit->id)
            ->assertSet('open', true)
            ->assertSet('scope', 'unit')
            ->assertSet('unitId', $unit->id);
    }

    public function test_save_creates_expense_and_closes_modal(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open')
            ->set('spentAt', now()->toDateString())
            ->set('amount', '850')
            ->set('category', 'MANTENIMIENTO')
            ->call('save')
            ->assertSet('open', false)
            ->assertDispatched('expense-created');

        $this->assertDatabaseHas('expenses', [
            'organization_id' => $organizationId,
            'category' => 'MANTENIMIENTO',
            'amount' => 850,
            'unit_id' => null,
        ]);
    }

    public function test_save_with_unit_scope_assigns_unit(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        $property = Property::factory()->create(['organization_id' => $organizationId]);
        $unit = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
        ]);

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open')
            ->set('spentAt', now()->toDateString())
            ->set('amount', '300')
            ->set('category', 'LIMPIEZA')
            ->set('scope', 'unit')
            ->set('unitId', $unit->id)
            ->call('save')
            ->assertSet('open', false);

        $this->assertDatabaseHas('expenses', [
            'organization_id' => $organizationId,
            'category' => 'LIMPIEZA',
            'unit_id' => $unit->id,
        ]);
    }

    public function test_save_blocked_by_closed_month(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

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

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open')
            ->set('spentAt', now()->toDateString())
            ->set('amount', '500')
            ->set('category', 'SERVICIO')
            ->call('save')
            ->assertSeeHtml('Mes bloqueado')
            ->assertSet('open', true);

        $this->assertDatabaseMissing('expenses', [
            'organization_id' => $organizationId,
            'category' => 'SERVICIO',
        ]);
    }

    public function test_unit_typeahead_returns_scoped_results(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        $property = Property::factory()->create([
            'organization_id' => $organizationId,
            'name' => 'Edificio Central',
        ]);
        Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'name' => 'Local 5',
        ]);

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('open')
            ->set('scope', 'unit')
            ->set('unitQuery', 'Local')
            ->assertSeeHtml('Local 5');
    }

    public function test_expenses_index_shows_new_button_and_refreshes_on_event(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;

        $this->actingAs($user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertSeeText('Registrar egreso')
            ->assertSeeHtml('open-quick-expense');

        Expense::query()->withoutOrganizationScope()->create([
            'organization_id' => $organizationId,
            'category' => 'REPARACION',
            'amount' => 1200,
            'spent_at' => now()->toDateString(),
            'meta' => [],
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Expenses\Index::class)
            ->dispatch('expense-created')
            ->assertSeeHtml('REPARACION');
    }
}
