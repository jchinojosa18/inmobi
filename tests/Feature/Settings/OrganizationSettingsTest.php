<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_settings_page(): void
    {
        Role::findOrCreate('Admin', 'web');
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        $response = $this->actingAs($user)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSeeText('Configuración');
    }

    public function test_user_without_settings_permission_cannot_access_settings(): void
    {
        Role::findOrCreate('Lectura', 'web');
        $user = User::factory()->create();
        $user->syncRoles(['Lectura']);

        $this->actingAs($user);

        $this->get(route('settings.index'))->assertForbidden();
    }

    public function test_admin_can_update_settings_and_expense_categories_scoped_by_organization(): void
    {
        Role::findOrCreate('Admin', 'web');

        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $adminA = User::factory()->create([
            'organization_id' => $organizationA->id,
        ]);
        $adminA->assignRole('Admin');

        $adminB = User::factory()->create([
            'organization_id' => $organizationB->id,
        ]);
        $adminB->assignRole('Admin');

        $this->actingAs($adminA);

        Livewire::test(SettingsIndex::class)
            ->set('receiptFolioMode', OrganizationSetting::RECEIPT_MODE_CONTINUOUS)
            ->set('receiptFolioPrefix', 'FAC')
            ->set('receiptFolioPadding', '4')
            ->set('whatsAppTemplate', 'Hola {tenant_name} saldo ${amount_due} en {unit_name}. {shared_receipt_url}')
            ->set('emailTemplate', 'Hola {tenant_name} / {unit_name} / {amount_due} / {shared_receipt_url}')
            ->call('saveSettings');

        $this->assertDatabaseHas('organization_settings', [
            'organization_id' => $organizationA->id,
            'receipt_folio_mode' => OrganizationSetting::RECEIPT_MODE_CONTINUOUS,
            'receipt_folio_prefix' => 'FAC',
            'receipt_folio_padding' => 4,
        ]);

        Livewire::test(SettingsIndex::class)
            ->set('newExpenseCategory', 'Mantenimiento')
            ->call('createExpenseCategory');

        Livewire::test(SettingsIndex::class)
            ->set('newExpenseCategory', 'Limpieza')
            ->call('createExpenseCategory');

        $this->assertDatabaseHas('expense_categories', [
            'organization_id' => $organizationA->id,
            'name' => 'MANTENIMIENTO',
        ]);

        $this->assertDatabaseMissing('expense_categories', [
            'organization_id' => $organizationB->id,
            'name' => 'MANTENIMIENTO',
        ]);

        $this->actingAs($adminB);

        $response = $this->get(route('settings.index'));

        $response->assertOk();
        $response->assertDontSeeText('MANTENIMIENTO');
    }
}
