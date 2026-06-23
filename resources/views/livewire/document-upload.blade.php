<x-ui.card>
    <h2 class="mb-3 text-lg font-semibold">Subir evidencia/documento</h2>
    <p class="mb-4 text-sm text-slate-600">
        Tipos permitidos: JPG, PNG, PDF. Tamano maximo: 5 MB.
    </p>

    <form wire:submit="upload" class="space-y-4" enctype="multipart/form-data">
        <div>
            <input
                type="file"
                wire:model="document"
                accept=".jpg,.jpeg,.png,.pdf"
                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
            >
            @error('document')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <x-ui.button
            type="submit"
            wire:loading.attr="disabled"
        >
            Subir documento
        </x-ui.button>

        <p wire:loading wire:target="document,upload" class="text-sm text-slate-500">
            Procesando archivo...
        </p>
    </form>

    @if ($downloadUrl)
        <div class="mt-5 rounded-md bg-slate-50 p-4 text-sm">
            <p class="mb-2 font-medium text-slate-700">Archivo guardado correctamente.</p>
            <p class="mb-1 text-slate-600">Disk: <code>{{ $storedDisk }}</code></p>
            <p class="mb-3 text-slate-600">Path: <code>{{ $storedPath }}</code></p>
            <a
                href="{{ $downloadUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="text-blue-700 underline"
            >
                Descargar archivo
            </a>
        </div>
    @endif
</x-ui.card>
