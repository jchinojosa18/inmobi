<?php

namespace Tests\Feature\Tenants;

use App\Livewire\Tenants\Index;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantCreateModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_modal_teleports_to_body_for_full_viewport_backdrop(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('startCreate')
            ->assertSet('showForm', true)
            ->assertSee(__('catalog.tenants.create_tenant'), false)
            ->assertSeeHtml('x-teleport="body"');
    }
}
