<x-ui.card>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ __('inventory.title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('inventory.section_description') }}</p>
        </div>

        @if ($canManageUnits)
            <x-ui.button type="button" wire:click="openCreateForm">
                {{ __('inventory.add_item') }}
            </x-ui.button>
        @endif
    </div>

    @if ($showForm && $canManageUnits)
        <x-ui.modal
            :open="true"
            :title="$editingItemId ? __('inventory.edit_item') : __('inventory.add_item')"
            :aria-label="$editingItemId ? __('inventory.edit_item') : __('inventory.add_item')"
            max-width="2xl"
            close-action="cancelForm"
        >
            <form wire:submit="saveItem" wire:key="inventory-item-form-{{ $editingItemId ?? 'new' }}" class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-ui.input
                        id="inventory-item-name"
                        :label="__('inventory.item_name')"
                        type="text"
                        wire:model="formName"
                        required
                    />
                    @error('formName')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-ui.input
                        id="inventory-item-quantity"
                        :label="__('inventory.quantity')"
                        type="number"
                        wire:model="formQuantity"
                        min="1"
                        required
                    />
                    @error('formQuantity')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-ui.select id="inventory-item-condition" :label="__('inventory.condition')" wire:model="formCondition">
                        @foreach (\App\Models\UnitInventoryItem::conditionOptions() as $condition)
                            <option value="{{ $condition }}">{{ __('inventory.conditions.'.$condition) }}</option>
                        @endforeach
                    </x-ui.select>
                    @error('formCondition')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('common.notes') }}</label>
                    <textarea
                        wire:model="formNotes"
                        rows="3"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    ></textarea>
                    @error('formNotes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelForm">
                        {{ __('common.cancel') }}
                    </x-ui.button>
                    <x-ui.button type="submit">
                        {{ __('common.save') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showPhotoGallery && $galleryItem)
        @php
            $galleryPhotos = $galleryItem->documents->map(fn ($document) => [
                'id' => $document->id,
                'url' => route('documents.download', $document),
            ])->values();
        @endphp

        <div
            x-data="{
                viewerOpen: false,
                activeIndex: 0,
                photos: @js($galleryPhotos),
                openViewer(index) {
                    this.activeIndex = index;
                    this.viewerOpen = true;
                },
                closeViewer() {
                    this.viewerOpen = false;
                },
                nextPhoto() {
                    if (this.photos.length === 0) return;
                    this.activeIndex = (this.activeIndex + 1) % this.photos.length;
                },
                prevPhoto() {
                    if (this.photos.length === 0) return;
                    this.activeIndex = (this.activeIndex - 1 + this.photos.length) % this.photos.length;
                },
            }"
            @keydown.escape.window="viewerOpen && closeViewer()"
            @keydown.arrow-right.window="viewerOpen && nextPhoto()"
            @keydown.arrow-left.window="viewerOpen && prevPhoto()"
        >
            <x-ui.modal
                :open="true"
                :title="__('inventory.photo_gallery_for', ['item' => $galleryItem->name])"
                :aria-label="__('inventory.photo_gallery')"
                max-width="2xl"
                close-action="closePhotoGallery"
            >
                <div class="space-y-4">
                    @if ($galleryItem->documents->isEmpty())
                        <p class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            {{ __('inventory.no_photos') }}
                        </p>
                    @else
                        <p class="text-sm text-slate-600">
                            {{ trans_choice('inventory.photo_count', $galleryItem->documents->count(), ['count' => $galleryItem->documents->count()]) }}
                        </p>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($galleryItem->documents as $document)
                                <div class="group relative aspect-square overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <button
                                        type="button"
                                        @click="openViewer({{ $loop->index }})"
                                        class="h-full w-full transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-slate-400"
                                        aria-label="{{ __('inventory.view_photos') }}: {{ $galleryItem->name }}"
                                    >
                                        <img
                                            src="{{ route('documents.download', $document) }}"
                                            alt="{{ $galleryItem->name }}"
                                            class="h-full w-full object-cover transition group-hover:scale-105"
                                        >
                                    </button>
                                    @if ($canDeletePhotos)
                                        <button
                                            type="button"
                                            wire:click="confirmDeletePhoto({{ $document->id }})"
                                            class="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white opacity-0 transition hover:bg-red-600 group-hover:opacity-100"
                                            aria-label="{{ __('inventory.delete_photo') }}"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($canUploadPhotos && $galleryItem->documents_count < $maxPhotosPerItem)
                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                            <p class="mb-2 text-sm font-medium text-slate-700">{{ __('inventory.upload_photo') }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <input
                                    type="file"
                                    wire:model="photoUploads.{{ $galleryItem->id }}"
                                    accept=".jpg,.jpeg,.png"
                                    class="block max-w-xs text-xs text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-white file:px-2 file:py-1 file:text-xs file:font-medium file:text-slate-700"
                                >
                                <x-ui.button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    wire:click="uploadPhoto({{ $galleryItem->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="photoUploads.{{ $galleryItem->id }},uploadPhoto"
                                >
                                    {{ __('inventory.upload_photo') }}
                                </x-ui.button>
                            </div>
                            @error('photoUploads.'.$galleryItem->id)
                                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <p wire:loading wire:target="photoUploads.{{ $galleryItem->id }}" class="mt-2 text-xs text-slate-500">
                                {{ __('inventory.uploading_photo') }}
                            </p>
                        </div>
                    @endif
                </div>
            </x-ui.modal>

            <div
                x-show="viewerOpen"
                x-cloak
                class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="@js(__('inventory.photo_viewer'))"
            >
                <div
                    class="absolute inset-0 bg-black/80"
                    @click="closeViewer()"
                    aria-hidden="true"
                ></div>

                <div class="relative z-10 flex w-full max-w-6xl flex-col gap-3">
                    <div class="flex items-center justify-between gap-3 text-white">
                        <p class="text-sm font-medium text-white">
                            {{ __('inventory.photo_viewer') }}
                            <span x-show="photos.length > 0" class="text-white/80">
                                (<span x-text="activeIndex + 1"></span>/<span x-text="photos.length"></span>)
                            </span>
                        </p>
                        <button
                            type="button"
                            @click="closeViewer()"
                            class="rounded-lg p-2 text-white/80 transition hover:bg-white/10 hover:text-white"
                            :aria-label="@js(__('inventory.close_viewer'))"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    @if ($canDeletePhotos)
                        <div class="flex justify-end">
                            <button
                                type="button"
                                @click="$wire.confirmDeletePhoto(photos[activeIndex]?.id)"
                                class="inline-flex items-center gap-2 rounded-lg border border-red-400/50 bg-red-500/20 px-3 py-1.5 text-sm font-medium text-red-100 transition hover:bg-red-500/40"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                {{ __('inventory.delete_photo') }}
                            </button>
                        </div>
                    @endif

                    <div class="relative flex items-center justify-center">
                        <button
                            type="button"
                            @click="prevPhoto()"
                            class="absolute left-0 z-10 -translate-x-2 rounded-full bg-black/50 p-3 text-white transition hover:bg-black/70 sm:translate-x-0"
                            :aria-label="@js(__('inventory.previous_photo'))"
                            x-show="photos.length > 1"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>

                        <div class="flex w-full items-center justify-center px-10 sm:px-14">
                            <img
                                x-bind:src="photos[activeIndex]?.url"
                                alt="{{ __('inventory.photo_viewer') }}"
                                class="block max-h-[50vh] w-auto max-w-full rounded-xl border border-white/20 bg-black object-contain shadow-2xl sm:max-h-[65vh]"
                            >
                        </div>

                        <button
                            type="button"
                            @click="nextPhoto()"
                            class="absolute right-0 z-10 translate-x-2 rounded-full bg-black/50 p-3 text-white transition hover:bg-black/70 sm:translate-x-0"
                            :aria-label="@js(__('inventory.next_photo'))"
                            x-show="photos.length > 1"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex justify-center gap-2" x-show="photos.length > 1">
                        <button
                            type="button"
                            @click="prevPhoto()"
                            class="inline-flex items-center justify-center rounded-lg border border-white/30 bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/20"
                        >
                            {{ __('inventory.previous_photo') }}
                        </button>
                        <button
                            type="button"
                            @click="nextPhoto()"
                            class="inline-flex items-center justify-center rounded-lg border border-white/30 bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/20"
                        >
                            {{ __('inventory.next_photo') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-4">
        @if ($items->isEmpty())
            <p class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                {{ __('inventory.empty') }}
                @if ($canManageUnits)
                    {{ __('inventory.empty_cta') }}
                @endif
            </p>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('inventory.item_name') }}</th>
                    <th class="px-4 py-3">{{ __('inventory.quantity') }}</th>
                    <th class="px-4 py-3">{{ __('inventory.condition') }}</th>
                    <th class="px-4 py-3">{{ __('inventory.photos') }}</th>
                    @if ($canManageUnits)
                        <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                    @endif
                </x-slot:head>
                <x-slot:body>
                    @foreach ($items as $item)
                        <tr wire:key="inventory-item-{{ $item->id }}" class="align-top">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $item->name }}</p>
                                @if ($item->notes)
                                    <p class="mt-1 text-xs text-slate-500">{{ $item->notes }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $item->quantity }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :variant="$this->conditionBadgeVariant($item->condition)">
                                    {{ __('inventory.conditions.'.$item->condition) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($item->documents_count === 0)
                                        @if ($canUploadPhotos)
                                            <button
                                                type="button"
                                                wire:click="openPhotoGallery({{ $item->id }})"
                                                class="inline-flex items-center rounded-md border border-slate-300 bg-white p-1.5 text-slate-700 transition hover:bg-slate-50"
                                                aria-label="{{ __('inventory.upload_photo') }}: {{ $item->name }}"
                                                title="{{ __('inventory.upload_photo') }}"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    @elseif ($canViewPhotos)
                                        <button
                                            type="button"
                                            wire:click="openPhotoGallery({{ $item->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                            aria-label="{{ __('inventory.view_photos') }}: {{ $item->name }}"
                                            title="{{ __('inventory.view_photos') }}"
                                        >
                                            <svg class="h-4 w-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span class="text-xs tabular-nums text-slate-500">{{ $item->documents_count }}</span>
                                        </button>
                                    @else
                                        <span class="text-xs tabular-nums text-slate-500">{{ $item->documents_count }}</span>
                                    @endif
                                </div>
                            </td>
                            @if ($canManageUnits)
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <x-ui.button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            wire:click="openEditForm({{ $item->id }})"
                                        >
                                            {{ __('common.edit') }}
                                        </x-ui.button>
                                        <x-ui.button
                                            type="button"
                                            size="sm"
                                            variant="danger"
                                            wire:click="deleteItem({{ $item->id }})"
                                            wire:confirm="{{ __('inventory.messages.confirm_delete_item') }}"
                                        >
                                            {{ __('common.delete') }}
                                        </x-ui.button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        @endif
    </div>

    @if ($canDeletePhotos)
        <x-ui.confirm-modal
            :open="$showDeletePhotoConfirm"
            :title="__('inventory.delete_photo_title')"
            confirm-action="executeDeletePhotoConfirm"
            cancel-action="cancelDeletePhotoConfirm"
            :confirm-label="__('inventory.delete_photo')"
            :cancel-label="__('common.cancel')"
            :aria-label="__('inventory.delete_photo_title')"
        >
            <p class="text-slate-700">{{ __('inventory.messages.confirm_delete_photo') }}</p>
        </x-ui.confirm-modal>
    @endif
</x-ui.card>
