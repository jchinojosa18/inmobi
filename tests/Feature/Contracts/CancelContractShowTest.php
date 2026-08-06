<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\Show;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CancelContractShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_user_sees_cancel_button_on_active_contract(): void
    {
        [$user, $contract] = $this->makeShowGraph();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->assertSee(__('contracts.cancel_contract'));
    }

    public function test_lectura_does_not_see_cancel_button(): void
    {
        [$user, $contract] = $this->makeShowGraph();
        $user->syncRoles(['Lectura']);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->assertDontSee(__('contracts.cancel_contract'));
    }

    public function test_cancel_requires_reason(): void
    {
        [$user, $contract] = $this->makeShowGraph();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->call('confirmCancelContract')
            ->set('cancellation_reason', '')
            ->call('executeCancelContract')
            ->assertHasErrors(['cancellation_reason']);

        $this->assertSame(Contract::STATUS_ACTIVE, $contract->fresh()->status);
    }

    public function test_cancel_success_sets_cancelled_and_flashes(): void
    {
        [$user, $contract] = $this->makeShowGraph();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->call('confirmCancelContract')
            ->set('cancellation_reason', 'Inquilino incorrecto')
            ->call('executeCancelContract')
            ->assertRedirect(route('contracts.index'));

        $this->assertSame(Contract::STATUS_CANCELLED, $contract->fresh()->status);
        $this->assertSame(
            __('contracts.flash.contract_cancelled'),
            session('success')
        );
    }

    public function test_blocked_contract_shows_blockers_and_does_not_cancel(): void
    {
        [$user, $contract] = $this->makeShowGraph();
        Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 50,
        ]);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->call('confirmCancelContract')
            ->assertSet('showCancelConfirm', true)
            ->assertSee(__('contracts.cancel_blocked_title'))
            ->assertSee(__('contracts.validation.cancel_has_payments'));

        $this->assertSame(Contract::STATUS_ACTIVE, $contract->fresh()->status);
    }

    public function test_follow_cancel_shortcut_closes_modal(): void
    {
        [$user, $contract] = $this->makeShowGraph();
        Payment::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'amount' => 50,
        ]);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->call('confirmCancelContract')
            ->assertSet('showCancelConfirm', true)
            ->call('followCancelShortcut', 'has_payments')
            ->assertSet('showCancelConfirm', false)
            ->assertSet('cancelBlockers', []);
    }

    public function test_cancelled_contract_hides_ledger_mutation_actions(): void
    {
        [$user, $contract] = $this->makeShowGraph();
        $contract->forceFill(['status' => Contract::STATUS_CANCELLED])->save();

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract->fresh()])
            ->assertSee(__('contracts.cancelled_banner'))
            ->assertDontSee(__('common.register_payment'))
            ->assertDontSee(__('contracts.cancel_contract'))
            ->assertDontSee(__('contracts.edit_contract'))
            ->assertDontSee(__('contracts.create_adjustment'))
            ->assertDontSee(__('contracts.settlement_title'))
            ->assertViewHas('canCreatePayments', false)
            ->assertViewHas('canManageCharges', false)
            ->assertViewHas('canSettleContracts', false)
            ->call('createAdjustment')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeShowGraph(): array
    {
        Role::findOrCreate('Lectura', 'web');
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
            'rent_amount' => 1000,
        ]);

        return [$user, $contract];
    }
}
