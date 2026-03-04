<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($documents->isEmpty())
                <p class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    No hay documentos asociados aún.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Archivo</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Tamano</th>
                                <th class="px-4 py-3">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($documents as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a
                                            href="{{ $item['url'] }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="font-medium text-blue-700 underline"
                                        >
                                            {{ $item['path'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">{{ $item['mime'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $this->formatFileSize($item['size']) }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ optional($item['created_at'])->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Subir documento</h3>
            <p class="mt-1 text-xs text-slate-500">Permitidos: JPG, PNG, PDF. Maximo 5 MB.</p>

            <form wire:submit="upload" class="mt-3 space-y-3" enctype="multipart/form-data">
                <input
                    type="file"
                    wire:model="document"
                    accept=".jpg,.jpeg,.png,.pdf"
                    class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                >
                @error('document')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                >
                    Subir documento
                </button>

                <p wire:loading wire:target="document,upload" class="text-xs text-slate-500">Subiendo archivo...</p>
            </form>
        </div>
    </div>
</div>
