<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed roles and assign Admin role to the first user.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['Admin', 'Capturista', 'Lectura'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $firstUser = User::query()->orderBy('id')->first();

        if ($firstUser !== null && ! $firstUser->hasRole('Admin')) {
            $firstUser->assignRole('Admin');
        }
    }
}
