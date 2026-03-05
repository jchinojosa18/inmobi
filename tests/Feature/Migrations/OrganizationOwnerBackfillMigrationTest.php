<?php

namespace Tests\Feature\Migrations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationOwnerBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_backfill_prefers_first_admin_and_fallbacks_to_oldest_user(): void
    {
        Role::findOrCreate('Admin', 'web');

        $organizationWithAdmin = Organization::factory()->create();
        $organizationWithoutAdmin = Organization::factory()->create();

        $legacyUser = User::factory()->create([
            'organization_id' => $organizationWithAdmin->id,
            'created_at' => now()->subDays(5),
        ]);
        $legacyUser->syncRoles([]);

        $adminUser = User::factory()->create([
            'organization_id' => $organizationWithAdmin->id,
            'created_at' => now()->subDays(2),
        ]);
        $adminUser->syncRoles(['Admin']);

        $oldestUser = User::factory()->create([
            'organization_id' => $organizationWithoutAdmin->id,
            'created_at' => now()->subDays(10),
        ]);
        $oldestUser->syncRoles([]);

        $newestUser = User::factory()->create([
            'organization_id' => $organizationWithoutAdmin->id,
            'created_at' => now()->subDay(),
        ]);
        $newestUser->syncRoles([]);

        DB::table('organizations')->whereIn('id', [$organizationWithAdmin->id, $organizationWithoutAdmin->id])->update([
            'owner_user_id' => null,
            'updated_at' => now(),
        ]);

        $migrationPath = database_path('migrations/2026_03_05_120000_backfill_organization_owner_user_id.php');

        /** @var object{up:callable} $migration */
        $migration = require $migrationPath;
        $migration->up();

        $organizationWithAdmin->refresh();
        $organizationWithoutAdmin->refresh();

        $this->assertSame((int) $adminUser->id, (int) $organizationWithAdmin->owner_user_id);
        $this->assertSame((int) $oldestUser->id, (int) $organizationWithoutAdmin->owner_user_id);
        $this->assertNotSame((int) $legacyUser->id, (int) $organizationWithAdmin->owner_user_id);
    }
}
