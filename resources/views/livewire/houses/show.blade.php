<section class="space-y-6">
    <x-ui.page-header
        :title="$property->name"
        :description="$entityLabel . '. Este inmueble usa una única unidad interna para contratos y cobranza.'"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('properties.index') }}" variant="secondary">
                Volver a propiedades
            </x-ui.button>
            @if ($canManageContracts)
                <x-ui.button href="{{ route('contracts.index', ['create_contract' => 1, 'unit_id' => $unit->id]) }}">
                    Nuevo contrato
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-ui.stat-card
                label="Propiedad"
                :value="$property->name"
                :hint="$property->address ?: 'Sin dirección registrada'"
            />
            @if ($property->notes)
                <p class="mt-3 text-sm text-slate-600">{{ $property->notes }}</p>
            @endif
        </div>

        <x-ui.stat-card
            label="Unidad única"
            :value="$unit->name"
            :hint="'Tipo: ' . $unit->kind . ' · Código: ' . ($unit->code ?: 'Sin código')"
        />
    </div>
</section>
