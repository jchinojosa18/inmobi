<div>
    <x-ui.modal
        :open="$open"
        :title="$step === 'picker' ? __('catalog.properties.new_property') : __('catalog.properties.new_type', ['type' => strtolower($selectedTypeLabel)])"
        :aria-label="$step === 'picker' ? __('catalog.properties.new_property') : __('catalog.properties.new_type', ['type' => strtolower($selectedTypeLabel)])"
        max-width="2xl"
        close-action="cancelForm"
    >
        @if ($step === 'picker')
            <p class="mb-4 text-sm text-slate-600">
                {{ __('catalog.properties.type_picker_description') }}
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($typeOptions as $option)
                    <button
                        type="button"
                        wire:click="selectType('{{ $option['key'] }}')"
                        class="rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-slate-400 hover:bg-slate-50"
                    >
                        <p class="font-medium text-slate-900">{{ $option['label'] }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $option['description'] }}</p>
                    </button>
                @endforeach
            </div>
        @else
            <div class="mb-4 flex items-center gap-2">
                <x-ui.button type="button" wire:click="backToPicker" variant="secondary" size="sm">
                    {{ __('catalog.properties.change_type') }}
                </x-ui.button>
                <span class="text-xs text-slate-500">{{ $selectedTypeLabel }}</span>
            </div>

            @if ($selectedType === \App\Livewire\Properties\CreateModal::TYPE_BUILDING)
                <p class="mb-4 text-sm text-slate-600">
                    {{ __('catalog.properties.building_form_description') }}
                </p>

                <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-ui.input :label="__('common.name').' *'" type="text" wire:model.live="name" class="uppercase" />
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <x-ui.input :label="__('common.code').' *'" type="text" wire:model.live="code" class="uppercase" />
                        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <x-ui.select :label="__('common.status').' *'" wire:model="formStatus">
                            <option value="active">{{ __('common.active') }}</option>
                            <option value="inactive">{{ __('common.inactive') }}</option>
                        </x-ui.select>
                        @error('formStatus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <x-ui.input :label="__('common.address')" type="text" wire:model.live="address" class="uppercase" />
                        @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('common.notes') }}</label>
                        <textarea wire:model.blur="notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                        <x-ui.button type="button" wire:click="cancelForm" variant="secondary">
                            {{ __('common.cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit">
                            {{ __('catalog.properties.create_building') }}
                        </x-ui.button>
                    </div>
                </form>
            @else
                <p class="mb-4 text-sm text-slate-600">
                    {{ __('catalog.properties.standalone_form_description') }}
                </p>

                <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-ui.input :label="__('common.name').' *'" type="text" wire:model.live="name" class="uppercase" />
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <x-ui.input :label="__('common.address')" type="text" wire:model.live="address" class="uppercase" />
                        @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('common.notes') }}</label>
                        <textarea wire:model.blur="notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                        <x-ui.button type="button" wire:click="cancelForm" variant="secondary">
                            {{ __('common.cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit">
                            {{ __('catalog.properties.create_type', ['type' => strtolower($selectedTypeLabel)]) }}
                        </x-ui.button>
                    </div>
                </form>
            @endif
        @endif
    </x-ui.modal>
</div>
