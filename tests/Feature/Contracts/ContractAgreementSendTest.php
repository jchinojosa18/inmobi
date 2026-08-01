<?php

namespace Tests\Feature\Contracts;

use App\Actions\Contracts\RenewContractAction;
use App\Mail\ContractAgreementMail;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\ContractAgreementShareUrl;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ContractAgreementSendTest extends TestCase
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

    public function test_renew_sends_contract_agreement_email_when_tenant_has_email_and_send_email_true(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        $source = $this->createRenewableSource(email: 'tenant@example.com');

        app(RenewContractAction::class)->execute(
            source: $source,
            input: [
                'starts_at' => '2026-08-01',
                'ends_at' => '2027-07-31',
                'rent_amount' => 10000,
                'deposit_amount' => 10000,
                'register_difference' => false,
                'send_email' => true,
            ],
            userId: null,
        );

        Mail::assertSent(ContractAgreementMail::class, function (ContractAgreementMail $mail) {
            return $mail->hasTo('tenant@example.com');
        });
    }

    public function test_renew_does_not_send_email_when_tenant_has_no_email(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        $source = $this->createRenewableSource(email: null);

        app(RenewContractAction::class)->execute(
            source: $source,
            input: [
                'starts_at' => '2026-08-01',
                'ends_at' => '2027-07-31',
                'rent_amount' => 10000,
                'deposit_amount' => 10000,
                'register_difference' => false,
                'send_email' => true,
            ],
            userId: null,
        );

        Mail::assertNothingSent();
    }

    public function test_renew_does_not_send_email_when_send_email_false(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        $source = $this->createRenewableSource(email: 'tenant@example.com');

        app(RenewContractAction::class)->execute(
            source: $source,
            input: [
                'starts_at' => '2026-08-01',
                'ends_at' => '2027-07-31',
                'rent_amount' => 10000,
                'deposit_amount' => 10000,
                'register_difference' => false,
                'send_email' => false,
            ],
            userId: null,
        );

        Mail::assertNothingSent();
    }

    public function test_contract_agreement_share_url_generates_signed_route(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        $source = $this->createRenewableSource(email: 'tenant@example.com');

        $result = app(RenewContractAction::class)->execute(
            source: $source,
            input: [
                'starts_at' => '2026-08-01',
                'ends_at' => '2027-07-31',
                'rent_amount' => 10000,
                'deposit_amount' => 10000,
                'register_difference' => false,
                'send_email' => false,
            ],
            userId: null,
        );

        URL::forceRootUrl('http://127.0.0.1');
        $shareUrl = ContractAgreementShareUrl::make($result->newContract->id);
        $pathWithQuery = parse_url($shareUrl, PHP_URL_PATH).'?'.parse_url($shareUrl, PHP_URL_QUERY);

        $this->get($pathWithQuery)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function createRenewableSource(?string $email): Contract
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'email' => $email,
        ]);

        TenantContext::setOrganizationId($organization->id);

        OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                ['organization_id' => $organization->id],
                ['landlord_name' => 'Arrendador Demo S.A. de C.V.'],
            );

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

        return $contract;
    }
}
