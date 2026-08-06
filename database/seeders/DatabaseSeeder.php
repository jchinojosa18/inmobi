<?php

namespace Database\Seeders;

use App\Actions\Expenses\SeedDefaultExpenseCategoriesAction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultOrganization = Organization::firstOrCreate([
            'name' => 'Default',
        ]);

        // Organization::created already ensures plaza "Principal".
        $defaultOrganization->ensureDefaultPlaza();

        $firstUser = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'organization_id' => $defaultOrganization->id,
            'name' => 'Test User',
            'password' => 'password',
        ]);

        if ($firstUser->organization_id === null) {
            $firstUser->organization()->associate($defaultOrganization);
            $firstUser->save();
        }

        if ($defaultOrganization->owner_user_id === null) {
            $defaultOrganization->forceFill([
                'owner_user_id' => $firstUser->id,
            ])->save();
        }

        $this->call([
            SyncRolesAndPermissionsSeeder::class,
        ]);

        Role::findOrCreate('Admin', 'web');
        if (! $firstUser->hasRole('Admin')) {
            $firstUser->assignRole('Admin');
        }

        app(SeedDefaultExpenseCategoriesAction::class)->execute((int) $defaultOrganization->id);
    }
}
