<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\RenewWizard;
use App\Livewire\Contracts\Show;
use App\Mail\ContractAgreementMail;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RenewWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_renew_wizard_happy_path_creates_new_contract_and_shows_success_actions(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        CarbonImmutable::setTestNow('2026-08-01 10:00:00');

        [$organization, $contract, $user] = $this->createRenewableGraph(email: 'tenant@example.com', phone: '526611234567');

        Livewire::actingAs($user)
            ->test(RenewWizard::class)
            ->dispatch('open-contract-renew', contractId: $contract->id)
            ->assertSet('open', true)
            ->assertSet('contractId', $contract->id)
            ->assertSet('send_email', true)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->call('renew')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertNotSet('newContractId', null)
            ->assertNotSet('pdfUrl', null)
            ->assertNotSet('shareUrl', null)
            ->assertNotSet('whatsAppUrl', null)
            ->assertDispatched('contract-renewed');

        $oldContract = $contract->fresh();
        $newContract = Contract::query()
            ->withoutOrganizationScope()
            ->where('id', '!=', $contract->id)
            ->where('unit_id', $contract->unit_id)
            ->first();

        $this->assertNotNull($newContract);
        $this->assertSame(Contract::STATUS_ENDED, $oldContract->status);
        $this->assertSame(Contract::STATUS_ACTIVE, $newContract->status);
        $this->assertSame($contract->id, data_get($newContract->meta, 'renewed_from_contract_id'));

        Mail::assertSent(ContractAgreementMail::class, function (ContractAgreementMail $mail) {
            return $mail->hasTo('tenant@example.com');
        });

        CarbonImmutable::setTestNow();
    }

    public function test_renew_wizard_blocked_when_outstanding_balance(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$organization, $contract, $user] = $this->createRenewableGraph();

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-07',
            'charge_date' => '2026-07-01',
            'amount' => 500,
        ]);

        Livewire::actingAs($user)
            ->test(RenewWizard::class)
            ->dispatch('open-contract-renew', contractId: $contract->id)
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->call('renew')
            ->assertHasErrors(['renew_general'])
            ->assertSet('step', 'form');

        $this->assertSame(
            1,
            Contract::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('status', Contract::STATUS_ACTIVE)
                ->count(),
        );
    }

    public function test_open_prefills_dates_and_deposit_from_available_hold(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00');

        [$organization, $contract, $user] = $this->createRenewableGraph();

        Livewire::actingAs($user)
            ->test(RenewWizard::class)
            ->dispatch('open-contract-renew', contractId: $contract->id)
            ->assertSet('rent_amount', '0.00')
            ->assertSet('deposit_amount', '9500.00')
            ->assertSet('due_day', '5')
            ->assertSet('grace_days', '3')
            ->assertSet('starts_at', '2026-08-01')
            ->assertSet('ends_at', '2027-07-31');

        CarbonImmutable::setTestNow();
    }

    public function test_increasing_rent_does_not_auto_increase_deposit_or_difference(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00');

        [$organization, $contract, $user] = $this->createRenewableGraph();
        $contract->update(['rent_amount' => 9500]);

        Livewire::actingAs($user)
            ->test(RenewWizard::class)
            ->dispatch('open-contract-renew', contractId: $contract->id)
            ->assertSet('rent_amount', '9500.00')
            ->assertSet('deposit_amount', '9500.00')
            ->set('rent_amount', '10000')
            ->assertSet('deposit_amount', '9500.00')
            ->assertSee(__('contracts.available'))
            ->assertDontSee(__('contracts.register_deposit_difference', ['amount' => number_format(500, 2)]));

        CarbonImmutable::setTestNow();
    }

    public function test_send_email_defaults_false_when_tenant_has_no_email(): void
    {
        [$organization, $contract, $user] = $this->createRenewableGraph(email: null);

        Livewire::actingAs($user)
            ->test(RenewWizard::class)
            ->dispatch('open-contract-renew', contractId: $contract->id)
            ->assertSet('send_email', false);
    }

    public function test_landlord_missing_shows_settings_link(): void
    {
        [$organization, $contract, $user] = $this->createRenewableGraph(withLandlordName: false);

        Livewire::actingAs($user)
            ->test(RenewWizard::class)
            ->dispatch('open-contract-renew', contractId: $contract->id)
            ->assertSee(__('contracts.renew_landlord_required'))
            ->assertSeeHtml(route('settings.index'));
    }

    public function test_renew_without_generate_pdf_shows_only_detail_action(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        CarbonImmutable::setTestNow('2026-08-01 10:00:00');

        [$organization, $contract, $user] = $this->createRenewableGraph(
            email: 'tenant@example.com',
            phone: '526611234567',
        );

        Livewire::actingAs($user)
            ->test(RenewWizard::class)
            ->dispatch('open-contract-renew', contractId: $contract->id)
            ->assertSet('generate_pdf', true)
            ->set('generate_pdf', false)
            ->assertSet('send_email', false)
            ->set('rent_amount', '10000')
            ->set('deposit_amount', '10000')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2027-07-31')
            ->set('send_email', true) // must be ignored server-side when PDF off
            ->call('renew')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertNotSet('newContractId', null)
            ->assertSet('pdfUrl', null)
            ->assertSet('shareUrl', null)
            ->assertSet('whatsAppUrl', null)
            ->assertDispatched('contract-renewed');

        Mail::assertNothingSent();

        CarbonImmutable::setTestNow();
    }

    public function test_renew_button_visible_on_show_when_renewable(): void
    {
        [$organization, $contract, $user] = $this->createRenewableGraph();

        $this->actingAs($user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(__('contracts.renew_contract'))
            ->assertSeeLivewire(RenewWizard::class);
    }

    public function test_renew_button_hidden_when_outstanding_balance(): void
    {
        [$organization, $contract, $user] = $this->createRenewableGraph();

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-07',
            'charge_date' => '2026-07-01',
            'amount' => 500,
        ]);

        Livewire::actingAs($user)
            ->test(Show::class, ['contract' => $contract])
            ->assertSet('contract.id', $contract->id)
            ->assertViewHas('canRenew', false);

        $this->actingAs($user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertDontSee(__('contracts.renew_contract'));
    }

    /**
     * @return array{Organization, Contract, User}
     */
    private function createRenewableGraph(
        ?string $email = 'tenant@example.com',
        ?string $phone = null,
        bool $withLandlordName = true,
    ): array {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'email' => $email,
            'phone' => $phone,
        ]);

        TenantContext::setOrganizationId($organization->id);

        if ($withLandlordName) {
            OrganizationSetting::query()
                ->withoutOrganizationScope()
                ->updateOrCreate(
                    ['organization_id' => $organization->id],
                    ['landlord_name' => 'Arrendador Demo S.A. de C.V.'],
                );
        }

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 0,
            'deposit_amount' => 9500,
            'due_day' => 5,
            'grace_days' => 3,
            'penalty_rate_daily' => 0.05,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => '2025-08-01',
            'ends_at' => '2026-07-31',
            'meta' => null,
        ]);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2025-08',
            'charge_date' => '2025-08-01',
            'amount' => 9500,
            'meta' => [
                'subtype' => 'RECEIVED',
                'received_at' => '2025-08-01',
            ],
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        return [$organization, $contract, $user];
    }
}
