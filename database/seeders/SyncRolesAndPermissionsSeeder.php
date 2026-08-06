<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncRolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'dashboard.view',
        'cobranza.view',
        'properties.view',
        'properties.manage',
        'units.view',
        'units.manage',
        'plazas.manage',
        'tenants.view',
        'tenants.manage',
        'contracts.view',
        'contracts.manage',
        'contracts.settle',
        'charges.view',
        'charges.manage',
        'rents.generate',
        'penalties.run',
        'payments.view',
        'payments.create',
        'receipts.send',
        'expenses.view',
        'expenses.create',
        'expenses.manage',
        'expense_categories.manage',
        'reports.view',
        'reports.export',
        'month_close.view',
        'month_close.close',
        'month_close.reopen',
        'documents.view',
        'documents.upload',
        'documents.delete',
        'audit.view',
        'audit.export',
        'users.manage',
        'invitations.manage',
        'settings.manage',
        'system.view',
    ];

    /**
     * @var list<string>
     */
    private const CAPTURISTA_PERMISSIONS = [
        'dashboard.view',
        'cobranza.view',
        'properties.view',
        'units.view',
        'tenants.view',
        'contracts.view',
        'charges.view',
        'payments.view',
        'payments.create',
        'receipts.send',
        'expenses.view',
        'expenses.create',
        'reports.view',
        'reports.export',
        'documents.view',
        'documents.upload',
    ];

    /**
     * @var list<string>
     */
    private const LECTURA_PERMISSIONS = [
        'dashboard.view',
        'cobranza.view',
        'properties.view',
        'units.view',
        'tenants.view',
        'contracts.view',
        'charges.view',
        'payments.view',
        'expenses.view',
        'reports.view',
        'reports.export',
        'documents.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $adminRole = Role::findOrCreate('Admin', 'web');
        $capturistaRole = Role::findOrCreate('Capturista', 'web');
        $lecturaRole = Role::findOrCreate('Lectura', 'web');

        $adminRole->syncPermissions(self::PERMISSIONS);
        $capturistaRole->syncPermissions(self::CAPTURISTA_PERMISSIONS);
        $lecturaRole->syncPermissions(self::LECTURA_PERMISSIONS);

        $this->ensureFirstUserIsAdmin();
        $this->ensureOwnersAreAdmins();
    }

    private function ensureFirstUserIsAdmin(): void
    {
        $firstUser = User::query()->orderBy('id')->first();

        if ($firstUser !== null && ! $firstUser->hasRole('Admin')) {
            $firstUser->assignRole('Admin');
        }
    }

    private function ensureOwnersAreAdmins(): void
    {
        $ownerIds = Organization::query()
            ->whereNotNull('owner_user_id')
            ->pluck('owner_user_id')
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ownerIds->isEmpty()) {
            return;
        }

        User::query()
            ->whereIn('id', $ownerIds->all())
            ->get()
            ->each(function (User $owner): void {
                if (! $owner->hasRole('Admin')) {
                    $owner->assignRole('Admin');
                }
            });
    }
}
