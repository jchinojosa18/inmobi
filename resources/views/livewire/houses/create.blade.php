<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Nueva casa</h1>
            <p class="mt-1 text-sm text-slate-600">Captura una casa en un solo paso. El sistema crea propiedad + unidad automáticamente.</p>
        </div>
        <a
            href="{{ route('properties.index') }}"
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Volver a propiedades
        </a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre de la casa *</label>
                <input
                    type="text"
                    wire:model.blur="name"
                    placeholder="Ej. Casa Calle X #123"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Dirección</label>
                <input
                    type="text"
                    wire:model.blur="address"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                <textarea
                    wire:model.blur="notes"
                    rows="3"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                ></textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button
                    type="submit"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Crear casa
                </button>
            </div>
        </form>
    </div>
</section>
