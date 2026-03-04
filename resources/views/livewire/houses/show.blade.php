<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Casa standalone</p>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $property->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">Esta casa usa una única unidad interna para contratos y cobranza.</p>
        </div>
        <div class="flex gap-2">
            <a
                href="{{ route('properties.index') }}"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Volver a propiedades
            </a>
            <a
                href="{{ route('contracts.create', ['unit_id' => $unit->id]) }}"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
            >
                Nuevo contrato
            </a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Propiedad</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $property->name }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $property->address ?: 'Sin dirección registrada' }}</p>
            @if ($property->notes)
                <p class="mt-3 text-sm text-slate-600">{{ $property->notes }}</p>
            @endif
        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Unidad única</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $unit->name }}</p>
            <p class="mt-1 text-sm text-slate-600">Tipo: {{ $unit->kind }}</p>
            <p class="mt-1 text-sm text-slate-600">Código: {{ $unit->code ?: 'Sin código' }}</p>
        </article>
    </div>
</section>
