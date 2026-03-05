<?php

namespace Tests\Feature\Console;

use App\Models\Organization;
use App\Models\Plaza;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillDefaultPlazasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_command_is_idempotent_and_does_not_duplicate_plazas(): void
    {
        $legacyOrganizationId = DB::table('organizations')->insertGetId([
            'name' => 'Legacy Org',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $organizationWithDefault = Organization::factory()->create();

        $this->assertSame(0, Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $legacyOrganizationId)
            ->count());

        $this->artisan('inmo:plazas:backfill-default')
            ->assertExitCode(0);

        $this->assertSame(1, Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $legacyOrganizationId)
            ->where('is_default', true)
            ->count());

        $totalAfterFirstRun = Plaza::query()
            ->withoutOrganizationScope()
            ->count();

        $this->artisan('inmo:plazas:backfill-default')
            ->assertExitCode(0);

        $totalAfterSecondRun = Plaza::query()
            ->withoutOrganizationScope()
            ->count();

        $this->assertSame($totalAfterFirstRun, $totalAfterSecondRun);

        $this->assertSame(1, Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationWithDefault->id)
            ->where('is_default', true)
            ->count());

        $this->assertSame(1, Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $legacyOrganizationId)
            ->where('is_default', true)
            ->count());
    }
}
