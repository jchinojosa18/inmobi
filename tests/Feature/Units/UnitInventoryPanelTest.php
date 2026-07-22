<?php

namespace Tests\Feature\Units;

use App\Livewire\Units\InventoryPanel;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitInventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnitInventoryPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_inventory_item_with_manage_permission(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->set('formName', 'Refrigerador')
            ->set('formQuantity', 1)
            ->set('formCondition', UnitInventoryItem::CONDITION_GOOD)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('unit_inventory_items', [
            'unit_id' => $unit->id,
            'name' => 'Refrigerador',
            'condition' => 'good',
        ]);
    }

    public function test_it_updates_inventory_item(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'name' => 'Estufa',
            'condition' => UnitInventoryItem::CONDITION_FAIR,
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('openEditForm', $item->id)
            ->set('formName', 'Estufa de gas')
            ->set('formCondition', UnitInventoryItem::CONDITION_GOOD)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('unit_inventory_items', [
            'id' => $item->id,
            'name' => 'Estufa de gas',
            'condition' => 'good',
        ]);
    }

    public function test_it_deletes_inventory_item(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('deleteItem', $item->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('unit_inventory_items', ['id' => $item->id]);
    }

    public function test_it_uploads_photo_for_inventory_item(): void
    {
        Storage::fake('public');
        config(['filesystems.documents_disk' => 'public']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);
        $file = UploadedFile::fake()->image('evidence.jpg');

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->set('photoUploads.'.$item->id, [$file])
            ->call('uploadPhoto', $item->id)
            ->assertHasNoErrors()
            ->assertDispatched('inventory-photo-uploaded');

        $this->assertDatabaseHas('documents', [
            'documentable_type' => UnitInventoryItem::class,
            'documentable_id' => $item->id,
            'type' => 'UNIT_INVENTORY_PHOTO',
        ]);
    }

    public function test_it_blocks_photo_upload_without_documents_upload_permission(): void
    {
        $organization = Organization::factory()->create();
        $viewerRole = Role::findOrCreate('Lectura', 'web');
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$viewerRole]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('uploadPhoto', $item->id)
            ->assertForbidden();
    }

    public function test_it_opens_empty_photo_gallery_for_upload(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'name' => 'Lámpara',
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('openPhotoGallery', $item->id)
            ->assertSet('showPhotoGallery', true)
            ->assertSet('galleryItemId', $item->id)
            ->assertSee(__('inventory.no_photos'));
    }

    public function test_it_opens_photo_gallery_for_item_with_photos(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'name' => 'Microondas',
        ]);

        Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => UnitInventoryItem::class,
            'documentable_id' => $item->id,
            'mime' => 'image/jpeg',
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('openPhotoGallery', $item->id)
            ->assertSet('showPhotoGallery', true)
            ->assertSet('galleryItemId', $item->id)
            ->assertSee(__('inventory.photo_gallery_for', ['item' => $item->name]));
    }

    public function test_it_deletes_inventory_photo_via_confirm_modal(): void
    {
        Storage::fake('public');
        config(['filesystems.documents_disk' => 'public']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        $path = 'documents/unitinventoryitem/'.$organization->id.'/photo.jpg';
        Storage::disk('public')->put($path, 'imagen');

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => UnitInventoryItem::class,
            'documentable_id' => $item->id,
            'path' => $path,
            'mime' => 'image/jpeg',
            'meta' => ['disk' => 'public'],
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('confirmDeletePhoto', $document->id)
            ->assertSet('showDeletePhotoConfirm', true)
            ->call('executeDeletePhotoConfirm')
            ->assertSet('showDeletePhotoConfirm', false)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_it_deletes_inventory_photo_with_delete_permission(): void
    {
        Storage::fake('public');
        config(['filesystems.documents_disk' => 'public']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        $path = 'documents/unitinventoryitem/'.$organization->id.'/photo.jpg';
        Storage::disk('public')->put($path, 'imagen');

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => UnitInventoryItem::class,
            'documentable_id' => $item->id,
            'path' => $path,
            'mime' => 'image/jpeg',
            'meta' => ['disk' => 'public'],
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('deletePhoto', $document->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_it_syncs_viewer_after_deleting_last_photo_in_gallery(): void
    {
        Storage::fake('public');
        config(['filesystems.documents_disk' => 'public']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        $path = 'documents/unitinventoryitem/'.$organization->id.'/photo.jpg';
        Storage::disk('public')->put($path, 'imagen');

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => UnitInventoryItem::class,
            'documentable_id' => $item->id,
            'path' => $path,
            'mime' => 'image/jpeg',
            'meta' => ['disk' => 'public'],
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('openPhotoGallery', $item->id)
            ->call('deletePhoto', $document->id)
            ->assertSet('showPhotoGallery', true)
            ->assertDispatched('inventory-photo-viewer-sync', photos: []);
    }

    public function test_it_blocks_photo_delete_without_documents_delete_permission(): void
    {
        $organization = Organization::factory()->create();
        $viewerRole = Role::findOrCreate('Lectura', 'web');
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([$viewerRole]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);
        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => UnitInventoryItem::class,
            'documentable_id' => $item->id,
        ]);

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->call('deletePhoto', $document->id)
            ->assertForbidden();
    }

    public function test_it_blocks_more_than_five_photos_per_item(): void
    {
        Storage::fake('public');
        config(['filesystems.documents_disk' => 'public']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        Document::factory()->count(5)->create([
            'organization_id' => $organization->id,
            'documentable_type' => UnitInventoryItem::class,
            'documentable_id' => $item->id,
        ]);

        $file = UploadedFile::fake()->image('extra.jpg');

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->set('photoUploads.'.$item->id, [$file])
            ->call('uploadPhoto', $item->id)
            ->assertHasErrors(['photoUploads.'.$item->id]);
    }

    public function test_it_uploads_multiple_photos_for_inventory_item(): void
    {
        Storage::fake('public');
        config(['filesystems.documents_disk' => 'public']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);
        $files = [
            UploadedFile::fake()->image('evidence-1.jpg'),
            UploadedFile::fake()->image('evidence-2.jpg'),
        ];

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->set('photoUploads.'.$item->id, $files)
            ->call('uploadPhoto', $item->id)
            ->assertHasNoErrors()
            ->assertDispatched('inventory-photo-uploaded');

        $this->assertSame(2, Document::query()
            ->where('documentable_type', UnitInventoryItem::class)
            ->where('documentable_id', $item->id)
            ->where('type', 'UNIT_INVENTORY_PHOTO')
            ->count());
    }

    public function test_it_rejects_entire_batch_when_photos_would_exceed_limit(): void
    {
        Storage::fake('public');
        config(['filesystems.documents_disk' => 'public']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        Document::factory()->count(3)->create([
            'organization_id' => $organization->id,
            'documentable_type' => UnitInventoryItem::class,
            'documentable_id' => $item->id,
            'type' => 'UNIT_INVENTORY_PHOTO',
        ]);

        $files = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ];

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->set('photoUploads.'.$item->id, $files)
            ->call('uploadPhoto', $item->id)
            ->assertHasErrors(['photoUploads.'.$item->id]);

        $this->assertSame(3, Document::query()
            ->where('documentable_type', UnitInventoryItem::class)
            ->where('documentable_id', $item->id)
            ->count());
    }

    public function test_it_rejects_entire_batch_when_one_file_is_invalid(): void
    {
        Storage::fake('public');
        config(['filesystems.documents_disk' => 'public']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $item = UnitInventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        $files = [
            UploadedFile::fake()->image('ok.jpg'),
            UploadedFile::fake()->create('bad.pdf', 100, 'application/pdf'),
        ];

        Livewire::actingAs($user)
            ->test(InventoryPanel::class, ['unit' => $unit])
            ->set('photoUploads.'.$item->id, $files)
            ->call('uploadPhoto', $item->id)
            ->assertHasErrors();

        $this->assertSame(0, Document::query()
            ->where('documentable_type', UnitInventoryItem::class)
            ->where('documentable_id', $item->id)
            ->count());
    }
}
