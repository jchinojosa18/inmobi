<x-ui.card>
    <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($documents->isEmpty())
                <p class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    No hay documentos asociados aún.
                </p>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-3">Archivo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Tamano</th>
                        <th class="px-4 py-3">Fecha</th>
                    </x-slot:head>
                    <x-slot:body>
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
                    </x-slot:body>
                </x-ui.table>
            @endif
        </div>

        <x-ui.card :padding="true" class="!p-4 bg-slate-50">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Subir documento</h3>
            <p class="mt-1 text-xs text-slate-500">Permitidos: JPG, PNG, PDF. Maximo 5 MB.</p>

            @if ($canUploadDocuments)
                <form wire:submit="save" class="mt-3 space-y-3" enctype="multipart/form-data">
                    <input
                        type="file"
                        wire:model="document"
                        accept=".jpg,.jpeg,.png,.pdf"
                        class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                    >
                    @error('document')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('month_close')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <x-ui.button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full"
                    >
                        Subir documento
                    </x-ui.button>

                    <p wire:loading wire:target="document,upload" class="text-xs text-slate-500">Subiendo archivo...</p>
                </form>
            @else
                <p class="mt-3 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500">
                    No tienes permiso para subir documentos.
                </p>
            @endif
        </x-ui.card>
    </div>
</x-ui.card>
