<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Charges\GenerateMonthlyRentChargesAction;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\OrganizationSetting;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\OrganizationSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardOnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_onboarding_checklist_when_system_is_empty(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('Configura tu sistema');
        $response->assertSeeText('Crear propiedad o casa');
        $response->assertSeeText('Generar o confirmar rentas del mes');
    }

    public function test_dashboard_hides_onboarding_checklist_when_critical_steps_are_complete(): void
    {
        $user = User::factory()->create();
        $contract = $this->createActiveContractGraph($user);

        $month = now('America/Tijuana')->format('Y-m');
        app(GenerateMonthlyRentChargesAction::class)
            ->executeForOrganization($month, (int) $user->organization_id);

        $this->assertGreaterThan(
            0,
            Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_RENT)
                ->where('period', $month)
                ->count()
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('Configura tu sistema');
    }

    public function test_dismiss_onboarding_hides_checklist_for_dismiss_window(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->call('dismissOnboarding');

        $setting = OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $user->organization_id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertNotNull($setting?->onboarding_dismissed_until);
        $this->assertTrue(
            app(OrganizationSettingsService::class)->isOnboardingDismissed((int) $user->organization_id)
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('Configura tu sistema');
    }

    public function test_generate_current_month_rent_is_idempotent_from_dashboard_action(): void
    {
        $user = User::factory()->create();
        $contract = $this->createActiveContractGraph($user);
        $month = now('America/Tijuana')->format('Y-m');

        $component = Livewire::actingAs($user)->test(DashboardIndex::class);
        $component->call('generateCurrentMonthRent');

        $afterFirst = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', $month)
            ->count();

        $component->call('generateCurrentMonthRent');

        $afterSecond = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->where('period', $month)
            ->count();

        $this->assertGreaterThan(0, $afterFirst);
        $this->assertSame($afterFirst, $afterSecond);
    }

    private function createActiveContractGraph(User $user): Contract
    {
        $property = Property::factory()->create([
            'organization_id' => $user->organization_id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $user->organization_id,
            'property_id' => $property->id,
            'status' => 'active',
        ]);

        $tenant = Tenant::factory()->create([
            'organization_id' => $user->organization_id,
            'status' => 'active',
        ]);

        return Contract::factory()->create([
            'organization_id' => $user->organization_id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => now('America/Tijuana')->startOfMonth()->toDateString(),
            'ends_at' => null,
        ]);
    }
}
