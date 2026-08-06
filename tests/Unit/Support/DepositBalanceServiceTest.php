<?php

namespace Tests\Unit\Support;

use App\Actions\Charges\RegisterContractAdjustmentAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\DepositBalanceService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_transfer_out_zeros_available_deposit(): void
    {
        $contract = $this->makeContract(depositAmount: 9500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 9500,
        ]);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_TRANSFER_OUT,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => -9500,
        ]);

        $service = app(DepositBalanceService::class);

        $this->assertSame(9500.0, $service->transferredOutDepositAmount($contract));
        $this->assertSame(0.0, $service->availableDepositAmount($contract));
    }

    public function test_outstanding_ignores_transfer_out_and_keeps_rent_debt(): void
    {
        $contract = $this->makeContract(depositAmount: 9500.0, rentAmount: 1000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_TRANSFER_OUT,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => -9500,
        ]);

        $service = app(DepositBalanceService::class);

        $this->assertSame(1000.0, $service->outstandingBalanceExcludingDepositHold($contract));
    }

    public function test_outstanding_does_not_double_count_settled_negative_adjustment(): void
    {
        $contract = $this->makeContract(depositAmount: 0.0, rentAmount: 1000.0);
        TenantContext::setOrganizationId((int) $contract->organization_id);

        $user = User::factory()->create(['organization_id' => $contract->organization_id]);

        CreditBalance::query()->updateOrCreate(
            [
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
            ],
            ['balance' => 0]
        );

        app(RegisterContractAdjustmentAction::class)->execute(
            contract: $contract,
            amount: -200.0,
            chargeDate: CarbonImmutable::parse('2026-07-15'),
            reason: 'Condonación parcial',
            createdByUserId: (int) $user->id,
        );

        // RENT 1000 − crédito 200 aplicado = 800. No debe ser 600 (doble descuento).
        $this->assertSame(
            800.0,
            app(DepositBalanceService::class)->outstandingBalanceExcludingDepositHold($contract->fresh())
        );
    }

    private function makeContract(float $depositAmount, float $rentAmount = 0): Contract
    {
        $organization = Organization::factory()->create();
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
            'deposit_amount' => $depositAmount,
            'rent_amount' => $rentAmount,
        ]);

        if ($rentAmount > 0) {
            $rents = Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->where('type', Charge::TYPE_RENT)
                ->orderBy('id')
                ->get();

            $rent = $rents->first();
            if ($rent === null) {
                Charge::factory()->create([
                    'organization_id' => $organization->id,
                    'contract_id' => $contract->id,
                    'unit_id' => $unit->id,
                    'type' => Charge::TYPE_RENT,
                    'period' => '2026-07',
                    'rent_period_key' => '2026-07',
                    'charge_date' => '2026-07-01',
                    'due_date' => '2026-07-15',
                    'amount' => $rentAmount,
                ]);
            } else {
                $rent->update(['amount' => $rentAmount]);
                $rents->skip(1)->each(fn (Charge $extra) => $extra->forceDelete());
            }
        }

        return $contract->fresh();
    }
}
