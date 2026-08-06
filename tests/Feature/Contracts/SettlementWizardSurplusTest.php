<?php

namespace Tests\Feature\Contracts;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Livewire\Contracts\SettlementWizard;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettlementWizardSurplusTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_shows_surplus_when_exit_concepts_less_than_available_deposit(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 7500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => 7500,
        ]);

        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->set('concepts.0.description', 'salida')
            ->set('concepts.0.amount', '5000')
            ->assertSee(__('contracts.deposit_surplus_to_refund'))
            ->assertSee('$2,500.00')
            ->assertViewHas('estimatedRefund', 2500.0);
    }

    public function test_preview_shows_zero_surplus_when_exit_covers_deposit(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 7500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => 7500,
        ]);

        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->set('concepts.0.description', 'salida')
            ->set('concepts.0.amount', '7500')
            ->assertSee(__('contracts.deposit_surplus_to_refund'))
            ->assertSee('$0.00')
            ->assertViewHas('estimatedRefund', 0.0);
    }

    public function test_preview_does_not_count_credit_consumed_by_prior_outstanding(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 3000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => 3000,
        ]);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_PENALTY,
            'period' => '2026-08',
            'charge_date' => '2026-08-02',
            'penalty_date' => '2026-08-02',
            'amount' => 5000,
            'rent_period_key' => null,
        ]);

        CreditBalance::query()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'balance' => 2000,
            'meta' => [],
        ]);

        // Credit covers 2000 of 5000 outstanding → 3000 left; deposit covers all → refund 0.
        // Naive "depositSurplus + full credit" would wrongly show 2000.
        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->assertViewHas('estimatedRefund', 0.0);
    }

    public function test_preview_adds_leftover_credit_when_no_prior_outstanding(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 7500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-08',
            'charge_date' => '2026-08-01',
            'amount' => 7500,
        ]);

        CreditBalance::query()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'balance' => 1000,
            'meta' => [],
        ]);

        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract])
            ->set('concepts.0.description', 'salida')
            ->set('concepts.0.amount', '5000')
            ->assertViewHas('estimatedRefund', 3500.0);
    }

    public function test_ended_contract_shows_refunded_surplus_and_expense_link(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 7500.0);
        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $contract->organization_id);

        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $contract->organization_id)
            ->where('name', 'REEMBOLSO DEPÓSITO')
            ->value('id');

        $expense = Expense::query()->create([
            'organization_id' => $contract->organization_id,
            'unit_id' => $contract->unit_id,
            'contract_id' => $contract->id,
            'expense_category_id' => $categoryId,
            'spent_at' => '2026-08-06',
            'amount' => 2500,
            'notes' => 'Devolución de depósito por finiquito',
            'meta' => [
                'reason' => 'contract_settlement',
                'contract_id' => $contract->id,
                'settlement_batch_id' => 'batch-test-1',
            ],
        ]);

        $contract->update([
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-08-06',
            'meta' => array_merge($contract->meta ?? [], [
                'settlement_batch_id' => 'batch-test-1',
                'settlements' => [
                    'batch-test-1' => [
                        'batch_id' => 'batch-test-1',
                        'deposit_refund' => 2500,
                        'refund_expense_id' => $expense->id,
                        'deposit_applied' => 5000,
                        'deposit_available' => 7500,
                    ],
                ],
            ]),
        ]);

        $expectedUrl = route('expenses.index', ['contractFilter' => $contract->id]);

        Livewire::actingAs($user)
            ->test(SettlementWizard::class, ['contract' => $contract->fresh()])
            ->assertSee(__('contracts.deposit_surplus_refunded'))
            ->assertSee('$2,500.00')
            ->assertSee(__('contracts.view_deposit_refund_expense'))
            ->assertSeeHtml(e($expectedUrl));
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithUser(float $depositAmount): array
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
            'rent_amount' => 0,
            'status' => Contract::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);

        return [$user, $contract];
    }
}
