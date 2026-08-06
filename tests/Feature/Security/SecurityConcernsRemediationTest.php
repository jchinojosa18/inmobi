<?php

namespace Tests\Feature\Security;

use App\Livewire\Reports\CashFlow;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\DocumentShareUrl;
use App\Support\OrganizationInvitationService;
use App\Support\SignedShareUrl;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityConcernsRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_path_and_mime_are_not_mass_assignable(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);

        $document = Document::storeNew([
            'organization_id' => $organization->id,
            'documentable_type' => Unit::class,
            'documentable_id' => $unit->id,
            'type' => 'evidence',
            'path' => 'documents/safe.pdf',
            'mime' => 'application/pdf',
            'size' => 1200,
        ]);

        $document->fill([
            'path' => 'evil/path.pdf',
            'mime' => 'application/x-evil',
            'size' => 999,
            'type' => 'receipt',
        ]);
        $document->save();

        $document->refresh();

        $this->assertSame('documents/safe.pdf', $document->path);
        $this->assertSame('application/pdf', $document->mime);
        $this->assertSame(1200, $document->size);
        $this->assertSame('receipt', $document->type);
    }

    public function test_document_store_new_persists_storage_attributes(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);

        $document = Document::storeNew([
            'organization_id' => $organization->id,
            'documentable_type' => Unit::class,
            'documentable_id' => $unit->id,
            'type' => 'evidence',
            'path' => 'documents/safe.pdf',
            'mime' => 'application/pdf',
            'size' => 1200,
        ]);

        $this->assertSame('documents/safe.pdf', $document->path);
        $this->assertSame('application/pdf', $document->mime);
        $this->assertSame(1200, $document->size);
    }

    public function test_signed_share_urls_default_to_forty_eight_hour_ttl(): void
    {
        $this->assertSame(48, SignedShareUrl::TTL_HOURS);

        $expires = now()->addHours(SignedShareUrl::TTL_HOURS);
        $url = DocumentShareUrl::make(123, $expires);

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('expires', $query);
        $this->assertEqualsWithDelta($expires->getTimestamp(), (int) $query['expires'], 2);
    }

    public function test_cash_flow_mount_forbids_users_without_reports_view(): void
    {
        $organization = Organization::factory()->create();
        $role = Role::findOrCreate('NoReports', 'web');
        $role->syncPermissions(['dashboard.view']);

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles(['NoReports']);

        Livewire::actingAs($user)
            ->test(CashFlow::class)
            ->assertForbidden();
    }

    public function test_contract_and_payment_policies_require_same_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $user = User::factory()->create(['organization_id' => $orgA->id]);
        Role::findOrCreate('Admin', 'web');
        $user->assignRole('Admin');

        $property = Property::factory()->create(['organization_id' => $orgB->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $orgB->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $orgB->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $orgB->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 0,
        ]);
        $payment = Payment::factory()->create([
            'organization_id' => $orgB->id,
            'contract_id' => $contract->id,
        ]);

        $this->assertFalse($user->can('view', $contract));
        $this->assertFalse($user->can('view', $payment));
    }

    public function test_invitation_token_lookup_bypasses_organization_scope(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Lectura', 'web');
        $admin->assignRole('Admin');

        $result = app(OrganizationInvitationService::class)->createInvitation(
            organizationId: (int) $organization->id,
            email: 'guest-invite@test.dev',
            role: 'Lectura',
            expiresAt: CarbonImmutable::now()->addDays(7),
            invitedByUserId: (int) $admin->id,
        );

        $found = app(OrganizationInvitationService::class)->findActiveByToken($result['token']);

        $this->assertNotNull($found);
        $this->assertSame((int) $result['invitation']->id, (int) $found->id);

        // Guest HTTP context has no tenant — scoped query must not see the invite.
        $this->assertNull(
            OrganizationInvitation::query()
                ->whereKey($result['invitation']->id)
                ->first()
        );
    }

    public function test_signed_share_route_is_throttled(): void
    {
        URL::forceRootUrl('http://localhost');

        $url = URL::temporarySignedRoute(
            'documents.shared',
            now()->addHour(),
            ['documentId' => 1],
            absolute: false,
        );

        for ($i = 0; $i < 30; $i++) {
            $this->get($url);
        }

        $this->get($url)->assertStatus(429);
    }
}
