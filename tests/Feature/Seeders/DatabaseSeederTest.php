<?php

namespace Tests\Feature\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\Plaza;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_seeder_creates_owner_plaza_and_expense_categories(): void
    {
        $this->seed(DatabaseSeeder::class);

        $organization = Organization::query()->where('name', 'Default')->first();
        $this->assertNotNull($organization);

        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($organization->id, (int) $user->organization_id);
        $this->assertSame($user->id, (int) $organization->owner_user_id);
        $this->assertTrue($user->hasRole('Admin'));

        $this->assertTrue(
            Plaza::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('is_default', true)
                ->exists()
        );

        $this->assertGreaterThanOrEqual(
            4,
            ExpenseCategory::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->count()
        );
    }
}
