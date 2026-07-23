<x-ui.card>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>

        @if ($canUploadDocuments)
            <x-ui.button
                type="button"
                wire:click="openUploadModal"
                :disabled="! $canOpenUpload"
            >
                {{ __('documents.upload_button') }}
            </x-ui.button>
        @endif
    </div>

    <div class="mt-4">
        @if ($documents->isEmpty())
            <p class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                {{ __('documents.empty') }}
            </p>
        @else
            <x-ui.table>
                <x-slot:head>
                    @if ($variant === 'contract')
                        <th class="px-4 py-3">{{ __('contracts.document_category') }}</th>
                    @endif
                    <th class="px-4 py-3">{{ __('documents.file') }}</th>
                    @if ($variant !== 'contract')
                        <th class="px-4 py-3">{{ __('common.type') }}</th>
                    @endif
                    <th class="px-4 py-3">{{ __('documents.size') }}</th>
                    <th class="px-4 py-3">{{ __('common.date') }}</th>
                    @if ($variant === 'contract' && $canUploadDocuments)
                        <th class="px-4 py-3">{{ __('common.actions') }}</th>
                    @endif
                </x-slot:head>
                <x-slot:body>
                    @foreach ($documents as $item)
                        <tr>
                            @if ($variant === 'contract')
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    {{ $item['category_label'] ?? '—' }}
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <a
                                    href="{{ $item['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-blue-700 underline"
                                >
                                    {{ $item['file_name'] }}
                                </a>
                            </td>
                            @if ($variant !== 'contract')
                                <td class="px-4 py-3 text-slate-700">{{ $item['mime'] }}</td>
                            @endif
                            <td class="px-4 py-3 text-slate-700">{{ $this->formatFileSize($item['size']) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ optional($item['created_at'])->format('Y-m-d H:i') }}</td>
                            @if ($variant === 'contract' && $canUploadDocuments)
                                <td class="px-4 py-3">
                                    <x-ui.button
                                        type="button"
                                        variant="danger"
                                        size="sm"
                                        wire:click="confirmDeleteDocument({{ $item['id'] }})"
                                    >
                                        {{ __('contracts.delete_document') }}
                                    </x-ui.button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        @endif
    </div>

    @if ($showUploadModal && $canUploadDocuments)
        <x-ui.modal
            :open="true"
            :title="__('documents.upload_title')"
            :aria-label="__('documents.upload_title')"
            max-width="md"
            close-action="closeUploadModal"
        >
            <p class="text-sm text-slate-600">
                {{ $variant === 'contract' ? __('documents.allowed_types_contract') : __('documents.allowed_types') }}
            </p>

            <form wire:submit="save" class="mt-4 space-y-4" enctype="multipart/form-data">
                @if ($variant === 'contract')
                    <div>
                        <label for="document-category" class="mb-1 block text-sm font-medium text-slate-700">
                            {{ __('contracts.document_category') }}
                        </label>
                        <select
                            id="document-category"
                            wire:model="category"
                            class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                            @disabled($availableCategories === [])
                        >
                            <option value="">{{ __('contracts.document_category_required') }}</option>
                            @foreach ($availableCategories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                    <p class="mb-2 text-sm font-medium text-slate-700">{{ __('documents.file') }}</p>
                    @php
                        $documentInputId = 'document-upload-'.$documentableId.'-'.$uploadInputKey;
                    @endphp
                    <div
                        wire:key="document-upload-wrap-{{ $documentableId }}-{{ $uploadInputKey }}"
                        x-data="{ fileLabel: '' }"
                        x-on:document-upload-reset.window="fileLabel = ''"
                        class="flex flex-col gap-2"
                    >
                        <input
                            id="{{ $documentInputId }}"
                            type="file"
                            wire:model="document"
                            accept="{{ $variant === 'contract' ? '.pdf' : '.jpg,.jpeg,.png,.pdf' }}"
                            x-on:change="
                                const files = Array.from($event.target.files || []);
                                fileLabel = files.map(file => file.name).join(', ');
                            "
                            class="sr-only"
                        >
                        <label
                            for="{{ $documentInputId }}"
                            class="inline-flex min-h-10 w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2"
                        >
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ __('documents.choose_file') }}
                        </label>
                        <p
                            x-show="fileLabel"
                            x-text="fileLabel"
                            x-cloak
                            class="truncate text-xs text-slate-500"
                        ></p>
                    </div>
                    @error('document')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('month_close')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p wire:loading wire:target="document" class="mt-2 text-xs text-slate-500">{{ __('documents.uploading') }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <x-ui.button type="button" variant="secondary" wire:click="closeUploadModal">
                        {{ __('common.cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        wire:loading.attr="disabled"
                        :disabled="$variant === 'contract' && $availableCategories === []"
                    >
                        {{ __('documents.upload_button') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($canUploadDocuments && $variant === 'contract')
        <x-ui.confirm-modal
            :open="$showDeleteConfirm"
            :title="__('documents.delete_title')"
            confirm-action="executeDeleteDocumentConfirm"
            cancel-action="cancelDeleteDocumentConfirm"
            :confirm-label="__('contracts.delete_document')"
            :cancel-label="__('common.cancel')"
            :aria-label="__('documents.delete_title')"
        >
            <p class="text-slate-700">{{ __('documents.confirm_delete') }}</p>
        </x-ui.confirm-modal>
    @endif
</x-ui.card>
