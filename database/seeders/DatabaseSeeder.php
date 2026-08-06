<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $defaultOrganization = Organization::firstOrCreate([
            'name' => 'Default',
        ]);

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

        $this->call([
            SyncRolesAndPermissionsSeeder::class,
        ]);
    }
}
