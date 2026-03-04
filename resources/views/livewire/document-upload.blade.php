<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
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
                class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            >
            @error('document')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
        >
            Subir documento
        </button>

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
</div>
