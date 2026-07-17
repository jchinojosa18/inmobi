<section class="space-y-6">
    <x-ui.page-header
        :title="$unit->code ?: $unit->name"
        :description="__('inventory.unit_info')"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('properties.units.index', $property) }}" variant="secondary">
                {{ __('inventory.back_to_units') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.stat-card
            :label="__('common.code')"
            :value="$unit->code ?: __('common.no_code')"
        />
        <x-ui.stat-card
            :label="__('common.status')"
            :value="$unit->status === 'active' ? __('common.active') : __('common.inactive')"
        />
        @if ($unit->floor)
            <x-ui.stat-card
                :label="__('catalog.units.floor_label', ['floor' => $unit->floor])"
                :value="$unit->floor"
            />
        @endif
    </div>

    @if ($unit->notes)
        <x-ui.card :padding="true" class="!p-4">
            <p class="text-sm text-slate-600">{{ $unit->notes }}</p>
        </x-ui.card>
    @endif

    <livewire:units.inventory-panel :unit="$unit" :key="'inventory-panel-'.$unit->id" />
</section>
