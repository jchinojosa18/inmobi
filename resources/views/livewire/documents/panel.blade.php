<x-ui.card :padding="$variant !== 'contract'">
    @if ($variant === 'contract')
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
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

        @if ($documents->isEmpty())
            <p class="px-5 py-6 text-center text-sm text-slate-600">
                {{ __('documents.empty') }}
            </p>
        @else
            <x-ui.table flush>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('contracts.document_category') }}</th>
                    <th class="px-4 py-3">{{ __('common.date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                </x-slot:head>
                <x-slot:body>
                    @foreach ($documents as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ $item['category_label'] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-700"><x-ui.display-date :value="$item['created_at']" time /></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <x-ui.file-viewer-trigger
                                        :items="$viewerItems"
                                        :index="$loop->index"
                                        variant="secondary"
                                        size="sm"
                                        class="!px-2"
                                        :title="__('documents.view_document')"
                                        :aria-label="__('documents.view_document')"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </x-ui.file-viewer-trigger>
                                    @if ($item['category'] === 'contract')
                                        <x-ui.button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            wire:click="openShareModal({{ $item['id'] }})"
                                        >
                                            {{ __('documents.share') }}
                                        </x-ui.button>
                                    @endif
                                    @if ($canDeleteDocuments)
                                        <x-ui.button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            wire:click="confirmDeleteDocument({{ $item['id'] }})"
                                        >
                                            {{ __('contracts.delete_document') }}
                                        </x-ui.button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        @endif
    @else
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
                        <th class="px-4 py-3">{{ __('documents.file') }}</th>
                        <th class="px-4 py-3">{{ __('common.type') }}</th>
                        <th class="px-4 py-3">{{ __('documents.size') }}</th>
                        <th class="px-4 py-3">{{ __('common.date') }}</th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($documents as $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <x-ui.file-viewer-trigger
                                        :items="$viewerItems"
                                        :index="$loop->index"
                                    >
                                        {{ $item['file_name'] }}
                                    </x-ui.file-viewer-trigger>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $item['mime'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $this->formatFileSize($item['size']) }}</td>
                                <td class="px-4 py-3 text-slate-700"><x-ui.display-date :value="$item['created_at']" time /></td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            @endif
        </div>
    @endif

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

                <div wire:key="document-upload-wrap-{{ $documentableId }}-{{ $uploadInputKey }}">
                    <x-ui.file-input
                        :id="'document-upload-'.$documentableId.'-'.$uploadInputKey"
                        wire:model="document"
                        :accept="$variant === 'contract' ? '.pdf' : '.jpg,.jpeg,.png,.pdf'"
                        boxed
                        :label="__('documents.file')"
                        reset-event="document-upload-reset"
                        loading-target="document"
                        :uploading-label="__('documents.uploading')"
                    />
                    @error('document')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('month_close')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
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

    @if ($showShareModal)
        <x-ui.modal
            :open="true"
            :title="__('documents.share_title')"
            :aria-label="__('documents.share_title')"
            max-width="md"
            close-action="closeShareModal"
        >
            <p class="text-sm text-slate-600">{{ __('documents.share_description') }}</p>

            <div class="mt-4 space-y-4">
                @if ($canSendReceipts)
                    <div>
                        <x-ui.input
                            id="contract-doc-email"
                            :label="__('documents.email_recipient')"
                            type="email"
                            :value="$shareTenantEmail"
                            disabled
                        />
                        @if ($shareTenantEmail)
                            <x-ui.button
                                type="button"
                                class="mt-3"
                                wire:click="sendContractDocumentEmail"
                                wire:loading.attr="disabled"
                            >
                                {{ __('documents.send_email') }}
                            </x-ui.button>
                        @else
                            <p class="mt-2 text-sm text-amber-700">{{ __('documents.no_tenant_email') }}</p>
                        @endif
                        @if ($shareEmailFeedback)
                            <p class="mt-2 text-sm text-slate-700">{{ $shareEmailFeedback }}</p>
                        @endif
                    </div>
                @endif

                <div class="flex flex-wrap gap-2">
                    @if ($shareUrl)
                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <span x-text="copied ? @js(__('documents.copied')) : @js(__('documents.copy_link'))"></span>
                        </button>
                    @endif

                    @if ($whatsAppUrl)
                        <a
                            href="{{ $whatsAppUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                        >
                            {{ __('documents.open_whatsapp') }}
                        </a>
                    @endif
                </div>

                @if ($shareUrl)
                    <div>
                        <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-slate-500">
                            {{ __('documents.shareable_link') }}
                        </p>
                        <textarea readonly rows="3" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">{{ $shareUrl }}</textarea>
                    </div>
                @endif
            </div>
        </x-ui.modal>
    @endif
</x-ui.card>
