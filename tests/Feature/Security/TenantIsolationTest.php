<?php

namespace Tests\Feature\Security;

use App\Actions\Expenses\RegisterExpenseAction;
use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Actions\Payments\ApplyPaymentAction;
use App\Livewire\Contracts\Show as ContractShow;
use App\Livewire\Payments\Show as PaymentShow;
use App\Models\Contract;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_contract_show_forbids_other_organization_contract(): void
    {
        [$orgA, $userA] = $this->orgAdmin();
        [, $foreignContract] = $this->contractInOrg(Organization::factory()->create());

        Livewire::actingAs($userA)
            ->test(ContractShow::class, ['contract' => $foreignContract])
            ->assertForbidden();
    }

    public function test_payment_show_forbids_other_organization_payment(): void
    {
        [$orgA, $userA] = $this->orgAdmin();
        [, $foreignContract] = $this->contractInOrg(Organization::factory()->create());

        $foreignPayment = Payment::factory()->create([
            'organization_id' => $foreignContract->organization_id,
            'contract_id' => $foreignContract->id,
        ]);

        Livewire::actingAs($userA)
            ->test(PaymentShow::class, ['payment' => $foreignPayment])
            ->assertForbidden();
    }

    public function test_http_contract_show_route_hides_other_organization_contract(): void
    {
        [$orgA, $userA] = $this->orgAdmin();
        TenantContext::setOrganizationId((int) $orgA->id);

        [, $foreignContract] = $this->contractInOrg(Organization::factory()->create());

        $this->actingAs($userA)
            ->get(route('contracts.show', $foreignContract))
            ->assertNotFound();
    }

    public function test_apply_payment_rejects_payment_for_a_different_contract(): void
    {
        $organization = Organization::factory()->create();
        TenantContext::setOrganizationId((int) $organization->id);

        [, $contractA] = $this->contractInOrg($organization);
        [, $contractB] = $this->contractInOrg($organization);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contractB->id,
            'amount' => 100,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment does not belong to provided contract.');

        app(ApplyPaymentAction::class)->execute($contractA, $payment);
    }

    public function test_apply_payment_cannot_reload_payment_from_another_organization(): void
    {
        $orgA = Organization::factory()->create();
        [, $contractA] = $this->contractInOrg($orgA);
        [, $contractB] = $this->contractInOrg(Organization::factory()->create());
        TenantContext::setOrganizationId((int) $orgA->id);

        $foreignPayment = Payment::query()->withoutOrganizationScope()->create([
            'organization_id' => $contractB->organization_id,
            'contract_id' => $contractB->id,
            'amount' => 100,
            'method' => Payment::METHOD_CASH,
            'paid_at' => now(),
            'meta' => [],
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(ApplyPaymentAction::class)->execute($contractA, $foreignPayment);
    }

    public function test_register_expense_rejects_unit_from_another_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $orgA->id);

        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $orgA->id)
            ->value('id');

        $foreignUnit = Unit::factory()->create([
            'organization_id' => $orgB->id,
            'property_id' => Property::factory()->create(['organization_id' => $orgB->id])->id,
        ]);

        $this->expectException(ValidationException::class);

        app(RegisterExpenseAction::class)->execute((int) $orgA->id, [
            'expense_category_id' => $categoryId,
            'amount' => 100,
            'spent_at' => '2026-07-15',
            'unit_id' => $foreignUnit->id,
        ]);
    }

    public function test_register_expense_rejects_contract_from_another_organization(): void
    {
        $orgA = Organization::factory()->create();
        [, $foreignContract] = $this->contractInOrg(Organization::factory()->create());
        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $orgA->id);

        $categoryId = ExpenseCategory::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $orgA->id)
            ->value('id');

        $unitA = Unit::factory()->create([
            'organization_id' => $orgA->id,
            'property_id' => Property::factory()->create(['organization_id' => $orgA->id])->id,
        ]);

        $this->expectException(ValidationException::class);

        app(RegisterExpenseAction::class)->execute((int) $orgA->id, [
            'expense_category_id' => $categoryId,
            'amount' => 100,
            'spent_at' => '2026-07-15',
            'unit_id' => $unitA->id,
            'contract_id' => $foreignContract->id,
        ]);
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function orgAdmin(): array
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        return [$organization, $admin];
    }

    /**
     * @return array{0: Organization, 1: Contract}
     */
    private function contractInOrg(Organization $organization): array
    {
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
            'rent_amount' => 0,
        ]);

        return [$organization, $contract];
    }
}
