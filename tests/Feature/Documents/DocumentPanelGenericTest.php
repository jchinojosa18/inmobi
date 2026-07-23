<?php

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Panel;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentPanelGenericTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_panel_accepts_jpeg_without_category(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Unit::class,
                'documentableId' => $unit->id,
            ])
            ->set('document', UploadedFile::fake()->image('foto.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'documentable_type' => Unit::class,
            'documentable_id' => $unit->id,
            'category' => null,
        ]);
    }
}
