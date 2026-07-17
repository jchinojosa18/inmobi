<?php

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Panel;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_upload_route_is_gone(): void
    {
        $this->get('/demo/document-upload')->assertNotFound();
    }

    public function test_panel_rejects_disallowed_morph(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $otherUser = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => User::class,
                'documentableId' => $otherUser->id,
            ])
            ->assertStatus(404);
    }

    public function test_panel_rejects_morph_from_another_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $foreignUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Unit::class,
                'documentableId' => $foreignUnit->id,
            ])
            ->assertStatus(403);
    }

    public function test_download_requires_same_org(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $foreignUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $document = Document::factory()->create([
            'organization_id' => $otherOrganization->id,
            'documentable_type' => Unit::class,
            'documentable_id' => $foreignUnit->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_download_requires_documents_view_permission(): void
    {
        $organization = Organization::factory()->create();

        $role = Role::findOrCreate('NoDocuments', 'web');
        $role->syncPermissions(['dashboard.view']);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $user->syncRoles(['NoDocuments']);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Unit::class,
            'documentable_id' => $unit->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_download_streams_file_for_same_org_user(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('documents/unit/'.$organization->id.'/evidence.pdf', 'contenido');

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Unit::class,
            'documentable_id' => $unit->id,
            'path' => 'documents/unit/'.$organization->id.'/evidence.pdf',
            'meta' => ['disk' => 'local'],
        ]);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk();
    }

    public function test_inline_view_streams_file_with_inline_disposition(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('documents/unit/'.$organization->id.'/photo.jpg', 'imagen');

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Unit::class,
            'documentable_id' => $unit->id,
            'path' => 'documents/unit/'.$organization->id.'/photo.jpg',
            'mime' => 'image/jpeg',
            'meta' => ['disk' => 'local'],
        ]);

        $this->actingAs($user)
            ->get(route('documents.download', ['document' => $document, 'inline' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline');
    }
}
