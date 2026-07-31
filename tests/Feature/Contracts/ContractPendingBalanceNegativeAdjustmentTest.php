<?php

namespace Tests\Feature\Contracts;

use App\Actions\Charges\RegisterContractAdjustmentAction;
use App\Livewire\Payments\QuickRegisterModal;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\ContractOverdueQuery;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractPendingBalanceNegativeAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_settled_negative_adjustment_does_not_double_count_discount_in_contract_balance(): void
    {
        [$user, $contract, $rent] = $this->makeContractWithUnpaidRent(1000.0);

        app(RegisterContractAdjustmentAction::class)->execute(
            contract: $contract,
            amount: -200.0,
            chargeDate: CarbonImmutable::parse('2026-07-15'),
            reason: 'Condonación parcial',
            createdByUserId: (int) $user->id,
        );

        // Credit consumed the rent, so rent pending is 800 and the adjustment is settled.
        $this->assertSame(
            200.0,
            (float) PaymentAllocation::query()->where('charge_id', $rent->id)->sum('amount')
        );

        $pendingBalance = (new ContractOverdueQuery)
            ->balanceByContractSubquery()
            ->where('charges.contract_id', $contract->id)
            ->value('pending_balance');

        $this->assertSame(800.0, round((float) $pendingBalance, 2));
    }

    public function test_quick_register_modal_summary_reports_clamped_pending_balance(): void
    {
        [$user, $contract] = $this->makeContractWithUnpaidRent(1000.0);

        app(RegisterContractAdjustmentAction::class)->execute(
            contract: $contract,
            amount: -200.0,
            chargeDate: CarbonImmutable::parse('2026-07-15'),
            reason: 'Condonación parcial',
            createdByUserId: (int) $user->id,
        );

        Livewire::actingAs($user)
            ->test(QuickRegisterModal::class)
            ->call('selectContract', $contract->id)
            ->assertSet('contractSummary.pending_balance', 800.0);
    }

    /**
     * @return array{0: User, 1: Contract, 2: Charge}
     */
    private function makeContractWithUnpaidRent(float $rentAmount): array
    {
        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId($organization->id);

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
            'rent_amount' => $rentAmount,
        ]);

        // Keep exactly one unpaid RENT: contract creation hooks may already have generated one.
        $rents = Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->orderBy('id')
            ->get();

        $rent = $rents->first();

        if ($rent === null) {
            $rent = Charge::query()->create([
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
                'unit_id' => $unit->id,
                'type' => Charge::TYPE_RENT,
                'period' => '2026-07',
                'rent_period_key' => '2026-07',
                'charge_date' => '2026-07-01',
                'due_date' => '2026-07-15',
                'amount' => $rentAmount,
                'meta' => [],
            ]);
        } else {
            $rent->update(['amount' => $rentAmount]);
            $rents->skip(1)->each(fn (Charge $extra) => $extra->forceDelete());
        }

        CreditBalance::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'contract_id' => $contract->id,
            ],
            ['balance' => 0]
        );

        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user);
        TenantContext::setOrganizationId($organization->id);

        return [$user, $contract, $rent];
    }
}
