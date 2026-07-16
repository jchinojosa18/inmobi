<?php

namespace Tests\Feature\Payments;

use App\Livewire\Payments\Show;
use App\Mail\PaymentReceiptMail;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentShowEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_email_always_uses_tenant_email_and_ignores_arbitrary_input(): void
    {
        Mail::fake();

        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'tenant@example.com',
        ]);

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
        ]);

        Livewire::actingAs($admin)
            ->test(Show::class, ['payment' => $payment])
            ->call('sendEmail')
            ->assertHasNoErrors();

        Mail::assertSent(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) {
            return $mail->hasTo('tenant@example.com');
        });

        Mail::assertNotSent(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) {
            return $mail->hasTo('attacker@evil.com');
        });
    }

    public function test_component_does_not_expose_an_editable_email_recipient_property(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'tenant@example.com',
        ]);

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
        ]);

        $this->assertFalse(
            property_exists(Show::class, 'emailRecipient') && (new \ReflectionProperty(Show::class, 'emailRecipient'))->isPublic(),
            'emailRecipient must not remain a public, client-writable Livewire property.'
        );

        $this->actingAs($admin)
            ->get(route('payments.show', $payment))
            ->assertOk();
    }

    public function test_send_button_is_disabled_when_tenant_has_no_email(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'email' => null,
        ]);

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
        ]);

        Mail::fake();

        Livewire::actingAs($admin)
            ->test(Show::class, ['payment' => $payment])
            ->call('sendEmail')
            ->assertHasErrors();

        Mail::assertNothingSent();
    }
}
