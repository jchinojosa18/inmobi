<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\ExpenseCategory;
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
            ->set('organizationName', 'Inmobiliaria Renovada')
            ->set('receiptFolioMode', OrganizationSetting::RECEIPT_MODE_CONTINUOUS)
            ->set('receiptFolioPrefix', 'FAC')
            ->set('receiptFolioPadding', '4')
            ->set('whatsAppTemplate', 'Hola {tenant_name} saldo ${amount_due} en {unit_name}. {shared_receipt_url}')
            ->set('emailTemplate', 'Hola {tenant_name} / {unit_name} / {amount_due} / {shared_receipt_url}')
            ->call('saveSettings')
            ->assertDispatched('settings-saved');

        $this->assertDatabaseHas('organization_settings', [
            'organization_id' => $organizationA->id,
            'receipt_folio_mode' => OrganizationSetting::RECEIPT_MODE_CONTINUOUS,
            'receipt_folio_prefix' => 'FAC',
            'receipt_folio_padding' => 4,
        ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organizationA->id,
            'name' => 'Inmobiliaria Renovada',
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

    public function test_delete_confirmation_escapes_expense_category_name(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        $category = ExpenseCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => '<script>alert(1)</script>',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->call('confirmDeleteExpenseCategory', $category->id)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_organization_name_must_be_unique_across_organizations(): void
    {
        Role::findOrCreate('Admin', 'web');

        $organizationA = Organization::factory()->create(['name' => 'Empresa A']);
        $organizationB = Organization::factory()->create(['name' => 'Empresa B']);

        $adminA = User::factory()->create(['organization_id' => $organizationA->id]);
        $adminA->assignRole('Admin');

        Livewire::actingAs($adminA)
            ->test(SettingsIndex::class)
            ->set('organizationName', 'Empresa B')
            ->set('receiptFolioMode', OrganizationSetting::RECEIPT_MODE_ANNUAL)
            ->set('receiptFolioPadding', '6')
            ->set('whatsAppTemplate', 'Hola {tenant_name}')
            ->set('emailTemplate', 'Hola {tenant_name}')
            ->call('saveSettings')
            ->assertHasErrors(['organizationName' => 'unique']);
    }

    public function test_cannot_rename_system_expense_category(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        $category = ExpenseCategory::factory()->system()->create([
            'organization_id' => $organization->id,
            'name' => 'MANTENIMIENTO',
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->set('editingExpenseCategoryId', $category->id)
            ->set('editingExpenseCategoryName', 'OTRO NOMBRE')
            ->call('updateExpenseCategory')
            ->assertHasErrors(['expenseCategory']);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'MANTENIMIENTO',
        ]);
    }

    public function test_cannot_delete_system_expense_category(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        $category = ExpenseCategory::factory()->system()->create([
            'organization_id' => $organization->id,
            'name' => 'MANTENIMIENTO',
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->call('deleteExpenseCategory', $category->id)
            ->assertHasErrors(['expenseCategory']);

        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_expense_category_in_use(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        $category = ExpenseCategory::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'CUSTOM',
        ]);

        \App\Models\Expense::factory()->create([
            'organization_id' => $organization->id,
            'expense_category_id' => $category->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->call('deleteExpenseCategory', $category->id)
            ->assertHasErrors(['expenseCategory']);

        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    public function test_save_settings_button_disables_while_saving_and_shows_saved_toast_copy(): void
    {
        Role::findOrCreate('Admin', 'web');
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('Admin');

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:target="saveSettings"')
            ->assertSeeHtml('settings-saved.window')
            ->assertSee(__('settings.flash.configuration_saved'));
    }
}
