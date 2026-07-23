<?php

namespace Tests\Feature\Tenants;

use App\Livewire\Tenants\Show;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantKardexShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_requires_tenants_view(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        // actingAs() auto-assigns Admin when the user has no roles — use a role without tenants.view.
        Role::findOrCreate('NoTenants', 'web');
        $user->syncRoles(['NoTenants']);

        $this->actingAs($user)
            ->get(route('tenants.show', $tenant))
            ->assertForbidden();
    }

    public function test_show_hides_tenant_from_other_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $tenantB = Tenant::factory()->create(['organization_id' => $orgB->id]);
        $userA = User::factory()->create(['organization_id' => $orgA->id]);

        $this->actingAs($userA)
            ->get(route('tenants.show', $tenantB))
            ->assertNotFound();
    }

    public function test_show_renders_kpis_and_tabs(): void
    {
        [$organization, $tenant] = $this->seedKardexGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSeeText($tenant->full_name)
            ->assertSeeText(__('catalog.tenants.kardex.active_contracts'))
            ->assertSeeText(__('catalog.tenants.kardex.pending_balance'))
            ->assertSeeText(__('catalog.tenants.kardex.credit_balance'))
            ->assertSeeText(__('catalog.tenants.kardex.total_paid'))
            ->assertSeeText('$4,500.00')
            ->assertSeeText('$200.00')
            ->assertSeeText('$16,000.00')
            ->assertSeeText(__('catalog.tenants.kardex.tab_contracts'))
            ->assertSeeText(__('catalog.tenants.kardex.tab_charges'))
            ->assertSeeText(__('catalog.tenants.kardex.tab_payments'));
    }

    public function test_edit_from_show_updates_tenant(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Nombre Viejo',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        // Ensure Admin even when using Livewire::actingAs (does not go through TestCase::actingAs).
        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test(Show::class, ['tenant' => $tenant])
            ->call('startEdit')
            ->set('full_name', 'Nombre Nuevo')
            ->set('formStatus', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nombre Nuevo', $tenant->fresh()->full_name);
    }

    public function test_index_name_links_to_kardex(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Maria Link',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('href="'.route('tenants.show', $tenant).'"', false);
    }

    public function test_contract_and_payment_opened_from_kardex_return_to_tenant(): void
    {
        [$organization, $tenant] = $this->seedKardexGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $kardexPath = route('tenants.show', $tenant, false);
        $backLabel = __('catalog.tenants.kardex.back_to_tenant', ['name' => $tenant->full_name]);

        $this->actingAs($user)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSee('return='.rawurlencode($kardexPath), false)
            ->assertSee('return_label='.rawurlencode($backLabel), false);

        $contract = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', Contract::STATUS_ACTIVE)
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('contracts.show', [
                'contract' => $contract,
                'return' => $kardexPath,
                'return_label' => $backLabel,
            ]))
            ->assertOk()
            ->assertSee('href="'.$kardexPath.'"', false)
            ->assertSeeText($backLabel);

        $payment = Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('contract_id', $contract->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('payments.show', [
                'payment' => $payment,
                'return' => $kardexPath,
                'return_label' => $backLabel,
            ]))
            ->assertOk()
            ->assertSee('href="'.$kardexPath.'"', false)
            ->assertSeeText($backLabel);

        $paymentsTabPath = $kardexPath.'?tab=payments';

        $this->actingAs($user)
            ->get(route('tenants.show', ['tenant' => $tenant, 'tab' => 'payments']))
            ->assertOk()
            ->assertSee('return='.rawurlencode($paymentsTabPath), false);

        $this->actingAs($user)
            ->get(route('payments.show', [
                'payment' => $payment,
                'return' => $paymentsTabPath,
                'return_label' => $backLabel,
            ]))
            ->assertOk()
            ->assertSee('href="'.$paymentsTabPath.'"', false)
            ->assertSeeText($backLabel);

        $chargesTabPath = $kardexPath.'?tab=charges';

        $this->actingAs($user)
            ->get(route('tenants.show', ['tenant' => $tenant, 'tab' => 'charges']))
            ->assertOk()
            ->assertSee('return='.rawurlencode($chargesTabPath), false);
    }

    public function test_view_only_user_cannot_see_or_use_edit(): void
    {
        [$organization, $tenant] = $this->seedKardexGraph();
        $role = Role::findOrCreate('TenantViewer', 'web');
        $role->syncPermissions(['tenants.view']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$role]);

        $this->actingAs($user)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertDontSee('wire:click="startEdit"', false);

        Livewire::actingAs($user)
            ->test(Show::class, ['tenant' => $tenant])
            ->call('startEdit')
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(Show::class, ['tenant' => $tenant])
            ->set('full_name', 'Nombre Hackeado')
            ->call('save')
            ->assertForbidden();
    }

    public function test_view_only_user_does_not_see_contract_or_payment_links(): void
    {
        [$organization, $tenant] = $this->seedKardexGraph();
        $contract = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        $payment = Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('contract_id', $contract->id)
            ->firstOrFail();

        $role = Role::findOrCreate('TenantViewer', 'web');
        $role->syncPermissions(['tenants.view']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$role]);

        $contractShowUrl = route('contracts.show', $contract);
        $paymentShowUrl = route('payments.show', $payment);

        $this->actingAs($user)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertDontSee('href="'.$contractShowUrl.'"', false);

        $this->actingAs($user)
            ->get(route('tenants.show', ['tenant' => $tenant, 'tab' => 'charges']))
            ->assertOk()
            ->assertDontSee('href="'.$contractShowUrl.'"', false);

        $this->actingAs($user)
            ->get(route('tenants.show', ['tenant' => $tenant, 'tab' => 'payments']))
            ->assertOk()
            ->assertDontSee('href="'.$paymentShowUrl.'"', false);
    }

    public function test_user_with_related_view_permissions_sees_contract_and_payment_links(): void
    {
        [$organization, $tenant] = $this->seedKardexGraph();
        $contract = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        $payment = Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('contract_id', $contract->id)
            ->firstOrFail();

        $role = Role::findOrCreate('TenantKardexReader', 'web');
        $role->syncPermissions(['tenants.view', 'contracts.view', 'payments.view']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$role]);

        $contractShowUrl = route('contracts.show', $contract);
        $paymentShowUrl = route('payments.show', $payment);

        $this->actingAs($user)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSee($contractShowUrl, false);

        $this->actingAs($user)
            ->get(route('tenants.show', ['tenant' => $tenant, 'tab' => 'payments']))
            ->assertOk()
            ->assertSee($paymentShowUrl, false);
    }

    /**
     * @return array{Organization, Tenant}
     */
    private function seedKardexGraph(): array
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Maria Fernanda Lopez',
        ]);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $contract = Contract::withoutEvents(fn () => Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => Contract::STATUS_ACTIVE,
        ]));
        $endedUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $ended = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $endedUnit->id,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2025-02-28',
        ]);

        $rent = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'amount' => 12500,
            'charge_date' => '2026-07-01',
        ]);
        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 8000,
            'paid_at' => '2026-07-03 12:00:00',
        ]);
        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 8000,
        ]);
        Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $ended->id,
            'amount' => 8000,
            'paid_at' => '2025-02-01 12:00:00',
        ]);
        CreditBalance::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 200,
        ]);

        return [$organization, $tenant];
    }
}
