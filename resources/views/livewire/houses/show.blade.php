<section class="space-y-6">
    <x-ui.page-header
        :title="$property->name"
        :description="__('catalog.houses.description', ['type' => $entityLabel])"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('properties.index') }}" variant="secondary">
                {{ __('common.back_to_properties') }}
            </x-ui.button>
            @if ($canManageContracts)
                <x-ui.button href="{{ route('contracts.index', ['create_contract' => 1, 'unit_id' => $unit->id]) }}">
                    {{ __('common.new_contract') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-ui.stat-card
                :label="__('common.property')"
                :value="$property->name"
                :hint="$property->address ?: __('common.no_address')"
            />
            @if ($property->notes)
                <p class="mt-3 text-sm text-slate-600">{{ $property->notes }}</p>
            @endif
        </div>

        <x-ui.stat-card
            :label="__('catalog.houses.single_unit')"
            :value="$unit->name"
            :hint="__('catalog.houses.type_code_hint', ['kind' => $unit->kind, 'code' => $unit->code ?: __('common.no_code')])"
        />
    </div>
</section>
