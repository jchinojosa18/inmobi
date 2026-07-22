<?php

namespace App\Livewire\Units;

use App\Models\Document;
use App\Models\Unit;
use App\Models\UnitInventoryItem;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class InventoryPanel extends Component
{
    use WithFileUploads;

    private const MAX_PHOTOS_PER_ITEM = 5;

    public Unit $unit;

    public bool $showForm = false;

    public ?int $editingItemId = null;

    public string $formName = '';

    public int $formQuantity = 1;

    public string $formCondition = UnitInventoryItem::CONDITION_GOOD;

    public string $formNotes = '';

    /**
     * @var array<int, array<int, mixed>|mixed>
     */
    public array $photoUploads = [];

    /**
     * @var array<int, int>
     */
    public array $photoUploadInputKeys = [];

    public bool $showPhotoGallery = false;

    public ?int $galleryItemId = null;

    public bool $showDeletePhotoConfirm = false;

    public ?int $pendingDeletePhotoId = null;

    public bool $showDeleteItemConfirm = false;

    public ?int $pendingDeleteItemId = null;

    public function mount(Unit $unit): void
    {
        if (! (auth()->user()?->can('units.view') ?? false)) {
            abort(403);
        }

        if ((int) $unit->organization_id !== (int) auth()->user()?->organization_id) {
            abort(403);
        }

        $this->unit = $unit;
    }

    public function openCreateForm(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEditForm(int $itemId): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $item = $this->unit->inventoryItems()->findOrFail($itemId);

        $this->editingItemId = $item->id;
        $this->formName = $item->name;
        $this->formQuantity = (int) $item->quantity;
        $this->formCondition = $item->condition;
        $this->formNotes = (string) ($item->notes ?? '');
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function saveItem(): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate();

        if ($this->editingItemId !== null) {
            $item = $this->unit->inventoryItems()->findOrFail($this->editingItemId);
            $item->update([
                'name' => $validated['formName'],
                'quantity' => $validated['formQuantity'],
                'condition' => $validated['formCondition'],
                'notes' => $validated['formNotes'] !== '' ? $validated['formNotes'] : null,
            ]);

            session()->flash('success', __('inventory.messages.item_updated'));
        } else {
            $maxSort = (int) $this->unit->inventoryItems()->max('sort_order');

            $this->unit->inventoryItems()->create([
                'organization_id' => $this->unit->organization_id,
                'name' => $validated['formName'],
                'quantity' => $validated['formQuantity'],
                'condition' => $validated['formCondition'],
                'notes' => $validated['formNotes'] !== '' ? $validated['formNotes'] : null,
                'sort_order' => $maxSort + 1,
            ]);

            session()->flash('success', __('inventory.messages.item_created'));
        }

        $this->resetForm();
    }

    public function confirmDeleteItem(int $itemId): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $this->unit->inventoryItems()->findOrFail($itemId);

        $this->pendingDeleteItemId = $itemId;
        $this->showDeleteItemConfirm = true;
    }

    public function cancelDeleteItemConfirm(): void
    {
        $this->showDeleteItemConfirm = false;
        $this->pendingDeleteItemId = null;
    }

    public function executeDeleteItemConfirm(): void
    {
        if ($this->pendingDeleteItemId === null) {
            return;
        }

        $this->deleteItem($this->pendingDeleteItemId);
        $this->cancelDeleteItemConfirm();
    }

    public function deleteItem(int $itemId): void
    {
        if (! (auth()->user()?->can('units.manage') ?? false)) {
            abort(403);
        }

        $item = $this->unit->inventoryItems()->with('documents')->findOrFail($itemId);

        DB::transaction(function () use ($item): void {
            foreach ($item->documents as $document) {
                $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'public'));

                if (Storage::disk($disk)->exists($document->path)) {
                    Storage::disk($disk)->delete($document->path);
                }

                $document->delete();
            }

            $item->delete();
        });

        if ($this->galleryItemId === $itemId) {
            $this->closePhotoGallery();
            $this->dispatchPhotoViewerSync(null);
        }

        session()->flash('success', __('inventory.messages.item_deleted'));
    }

    public function uploadPhoto(int $itemId): void
    {
        if (! (auth()->user()?->can('documents.upload') ?? false)) {
            abort(403);
        }

        $item = $this->unit->inventoryItems()->findOrFail($itemId);
        $key = 'photoUploads.'.$itemId;
        $uploads = $this->photoUploads[$itemId] ?? null;

        if (! is_array($uploads)) {
            $uploads = $uploads !== null ? [$uploads] : [];
            $this->photoUploads[$itemId] = $uploads;
        }

        $existingCount = $item->documents()->count();
        $incomingCount = count($uploads);

        if ($incomingCount < 1) {
            $this->clearPhotoUploadInput($itemId);
            throw ValidationException::withMessages([
                $key => __('inventory.validation.photo_required'),
            ]);
        }

        if ($existingCount + $incomingCount > self::MAX_PHOTOS_PER_ITEM) {
            $this->clearPhotoUploadInput($itemId);
            throw ValidationException::withMessages([
                $key => __('inventory.validation.max_photos'),
            ]);
        }

        try {
            $this->validate([
                $key => ['required', 'array', 'min:1'],
                $key.'.*' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            ], [
                $key.'.required' => __('inventory.validation.photo_required'),
                $key.'.min' => __('inventory.validation.photo_required'),
                $key.'.*.required' => __('inventory.validation.photo_required'),
                $key.'.*.image' => __('inventory.validation.photo_invalid'),
                $key.'.*.mimes' => __('inventory.validation.photo_invalid'),
                $key.'.*.max' => __('inventory.validation.photo_invalid'),
            ]);
        } catch (ValidationException $exception) {
            $this->clearPhotoUploadInput($itemId);
            throw $exception;
        }

        $disk = (string) config('filesystems.documents_disk', 'public');

        DB::transaction(function () use ($item, $uploads, $disk): void {
            foreach ($uploads as $upload) {
                $path = $upload->store('documents/unitinventoryitem/'.$item->organization_id, $disk);

                Document::query()->create([
                    'organization_id' => (int) $item->organization_id,
                    'documentable_type' => UnitInventoryItem::class,
                    'documentable_id' => $item->id,
                    'path' => $path,
                    'mime' => $upload->getMimeType() ?: 'image/jpeg',
                    'size' => $upload->getSize() ?: 0,
                    'type' => 'UNIT_INVENTORY_PHOTO',
                    'tags' => ['inventory', 'photo'],
                    'meta' => [
                        'disk' => $disk,
                        'uploaded_at' => now()->toISOString(),
                    ],
                ]);

                app(AuditLogger::class)->log(
                    action: 'inventory.photo_uploaded',
                    auditable: $item,
                    summary: __('inventory.audit.photo_uploaded', ['id' => $item->id]),
                    meta: [
                        'unit_id' => $this->unit->id,
                        'item_id' => $item->id,
                    ],
                );
            }
        });

        $this->clearPhotoUploadInput($itemId);
        $this->resetValidation($key);

        $this->dispatch('inventory-photo-uploaded');
    }

    private function clearPhotoUploadInput(int $itemId): void
    {
        unset($this->photoUploads[$itemId]);
        $this->photoUploadInputKeys[$itemId] = ($this->photoUploadInputKeys[$itemId] ?? 0) + 1;
        $this->dispatch('inventory-photo-upload-reset');
    }

    public function openPhotoGallery(int $itemId): void
    {
        $item = $this->unit->inventoryItems()->withCount('documents')->findOrFail($itemId);

        if ($item->documents_count > 0) {
            if (! (auth()->user()?->can('documents.view') ?? false)) {
                abort(403);
            }
        } elseif (! (auth()->user()?->can('documents.upload') ?? false)) {
            abort(403);
        }

        $this->galleryItemId = $item->id;
        $this->showPhotoGallery = true;
    }

    public function closePhotoGallery(): void
    {
        $this->showPhotoGallery = false;
        $this->galleryItemId = null;
    }

    public function confirmDeletePhoto(int $documentId): void
    {
        if (! (auth()->user()?->can('documents.delete') ?? false)) {
            abort(403);
        }

        $this->pendingDeletePhotoId = $documentId;
        $this->showDeletePhotoConfirm = true;
    }

    public function cancelDeletePhotoConfirm(): void
    {
        $this->showDeletePhotoConfirm = false;
        $this->pendingDeletePhotoId = null;
    }

    public function executeDeletePhotoConfirm(): void
    {
        if ($this->pendingDeletePhotoId === null) {
            return;
        }

        $this->deletePhoto($this->pendingDeletePhotoId);
        $this->cancelDeletePhotoConfirm();
    }

    public function deletePhoto(int $documentId): void
    {
        if (! (auth()->user()?->can('documents.delete') ?? false)) {
            abort(403);
        }

        $inventoryItemIds = $this->unit->inventoryItems()->pluck('id');

        $document = Document::query()
            ->where('documentable_type', UnitInventoryItem::class)
            ->whereIn('documentable_id', $inventoryItemIds)
            ->findOrFail($documentId);

        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'public'));

        if (Storage::disk($disk)->exists($document->path)) {
            Storage::disk($disk)->delete($document->path);
        }

        $itemId = (int) $document->documentable_id;
        $document->delete();

        app(AuditLogger::class)->log(
            action: 'inventory.photo_deleted',
            auditable: $this->unit,
            summary: __('inventory.audit.photo_deleted', ['id' => $itemId]),
            meta: [
                'unit_id' => $this->unit->id,
                'item_id' => $itemId,
                'document_id' => $documentId,
            ],
        );

        if ($this->galleryItemId !== null) {
            $remaining = UnitInventoryItem::query()
                ->whereKey($this->galleryItemId)
                ->where('unit_id', $this->unit->id)
                ->with(['documents' => fn ($query) => $query->latest('created_at')])
                ->withCount('documents')
                ->first();

            if ($remaining === null) {
                $this->closePhotoGallery();
                $this->dispatchPhotoViewerSync(null);
            } else {
                $this->dispatchPhotoViewerSync($remaining);
            }
        }

        $this->dispatch('inventory-photo-deleted');

        session()->flash('success', __('inventory.messages.photo_deleted'));
    }

    public function conditionBadgeVariant(string $condition): string
    {
        return match ($condition) {
            UnitInventoryItem::CONDITION_GOOD => 'success',
            UnitInventoryItem::CONDITION_FAIR => 'warning',
            UnitInventoryItem::CONDITION_POOR => 'danger',
            default => 'neutral',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'formName' => ['required', 'string', 'max:255'],
            'formQuantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'formCondition' => ['required', Rule::in(UnitInventoryItem::conditionOptions())],
            'formNotes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'formName.required' => __('inventory.validation.name_required'),
            'formQuantity.min' => __('inventory.validation.quantity_min'),
            'formCondition.in' => __('inventory.validation.condition_invalid'),
        ];
    }

    public function render(): View
    {
        $items = $this->unit->inventoryItems()
            ->with(['documents' => fn ($query) => $query->latest('created_at')])
            ->withCount('documents')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $galleryItem = $this->galleryItemId !== null
            ? $items->firstWhere('id', $this->galleryItemId)
            : null;

        return view('livewire.units.inventory-panel', [
            'items' => $items,
            'galleryItem' => $galleryItem,
            'canManageUnits' => auth()->user()?->can('units.manage') ?? false,
            'canUploadPhotos' => auth()->user()?->can('documents.upload') ?? false,
            'canViewPhotos' => auth()->user()?->can('documents.view') ?? false,
            'canDeletePhotos' => auth()->user()?->can('documents.delete') ?? false,
            'maxPhotosPerItem' => self::MAX_PHOTOS_PER_ITEM,
        ]);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingItemId = null;
        $this->formName = '';
        $this->formQuantity = 1;
        $this->formCondition = UnitInventoryItem::CONDITION_GOOD;
        $this->formNotes = '';
        $this->resetValidation();
    }

    /**
     * @return list<array{id: int, url: string}>
     */
    private function galleryPhotosPayload(UnitInventoryItem $item): array
    {
        return $item->documents
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'url' => route('documents.download', $document),
            ])
            ->values()
            ->all();
    }

    private function dispatchPhotoViewerSync(?UnitInventoryItem $item): void
    {
        $photos = $item !== null ? $this->galleryPhotosPayload($item) : [];

        $this->dispatch('inventory-photo-viewer-sync', photos: $photos);
    }
}
