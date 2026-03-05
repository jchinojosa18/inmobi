<?php

namespace Tests\Feature\Search;

use App\Livewire\Search\CommandPalette;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    // ─── Apertura y cierre ──────────────────────────────────────────────────

    public function test_opens_when_triggered(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->assertSet('open', true)
            ->assertSet('q', '');
    }

    public function test_closes_and_resets_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->set('q', 'algo')
            ->call('close')
            ->assertSet('open', false)
            ->assertSet('q', '')
            ->assertSet('results', []);
    }

    public function test_does_not_return_results_for_query_shorter_than_2_characters(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->set('q', 'a')
            ->assertSet('results', []);
    }

    public function test_renders_actions_section_when_opened(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->assertSee('Acciones')
            ->assertSee('Registrar pago')
            ->assertSee('Registrar egreso');
    }

    public function test_filters_actions_by_query_text(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->set('q', 'pago')
            ->assertSee('Registrar pago')
            ->assertDontSee('Registrar egreso');
    }

    // ─── Scoping por organización ────────────────────────────────────────────

    public function test_search_results_are_scoped_to_authenticated_user_organization(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        Tenant::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Carlos García Mi Org',
        ]);

        Tenant::factory()->create([
            'organization_id' => $otherOrg->id,
            'full_name' => 'Carlos García Otra Org',
        ]);

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->set('q', 'Carlos García')
            ->assertSeeHtml('Carlos García Mi Org')
            ->assertDontSeeHtml('Carlos García Otra Org');
    }

    public function test_finds_tenants_by_email(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        Tenant::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Ana Ruiz',
            'email' => 'ana.ruiz@example.com',
        ]);

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->set('q', 'ana.ruiz')
            ->assertSeeHtml('Ana Ruiz');
    }

    public function test_finds_properties_by_name_scoped_to_organization(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        Property::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Edificio Orión',
        ]);

        Property::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Edificio Sirio',
        ]);

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->set('q', 'Edificio')
            ->assertSeeHtml('Edificio Orión')
            ->assertDontSeeHtml('Edificio Sirio');
    }

    public function test_finds_units_by_name_and_includes_property_name(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $property = Property::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Torre Norte',
        ]);

        Unit::factory()->create([
            'organization_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Depto 301',
            'kind' => Unit::KIND_APARTMENT,
        ]);

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->set('q', 'Depto 301')
            ->assertSeeHtml('Depto 301')
            ->assertSeeHtml('Torre Norte');
    }

    public function test_finds_contracts_and_includes_both_action_hrefs(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $property = Property::factory()->create(['organization_id' => $org->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $org->id,
            'property_id' => $property->id,
            'kind' => Unit::KIND_APARTMENT,
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Roberto Suárez',
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $org->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
        ]);

        $component = Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open')
            ->set('q', 'Roberto Suárez');

        $component->assertSeeHtml('Roberto Suárez');

        $results = $component->get('results');
        $contractResult = collect($results)->firstWhere('type', 'contract');

        $this->assertNotNull($contractResult);
        $this->assertStringContainsString("/contracts/{$contract->id}", $contractResult['href']);
        $this->assertStringContainsString("/contracts/{$contract->id}/payments/create", $contractResult['href2']);
    }

    // ─── Integración en layout ───────────────────────────────────────────────

    public function test_command_palette_component_is_mounted_in_admin_layout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(CommandPalette::class);
    }

    public function test_admin_can_run_generate_current_month_rent_action_idempotently(): void
    {
        $month = CarbonImmutable::now('America/Tijuana')->format('Y-m');
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        Role::findOrCreate('Admin', 'web');
        $user->assignRole('Admin');

        $contract = Contract::factory()->create([
            'organization_id' => $org->id,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => CarbonImmutable::now('America/Tijuana')->subMonth()->toDateString(),
            'ends_at' => null,
        ]);

        $component = Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->call('open');

        $component
            ->call('executeAction', 'generate_current_month_rent')
            ->assertSet('confirmingActionId', 'generate_current_month_rent');

        $component->call('executeAction', 'generate_current_month_rent');

        $this->assertSame(1, Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', $month)
            ->count());

        $component
            ->call('open')
            ->call('executeAction', 'generate_current_month_rent')
            ->call('executeAction', 'generate_current_month_rent');

        $this->assertSame(1, Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', $month)
            ->count());
    }
}
