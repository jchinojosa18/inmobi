<?php

namespace Tests\Feature\FileViewer;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileViewerLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_layout_includes_global_file_viewer(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="file-viewer-root"', false)
            ->assertSee(__('file_viewer.download'));
    }
}
