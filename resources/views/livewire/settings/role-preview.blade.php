<section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Roles y permisos</h1>
            <p class="mt-1 text-sm text-slate-600">
                Vista de solo lectura. Los permisos se sincronizan por código desde el seeder.
            </p>
        </div>

        <div class="w-full sm:w-72">
            <label for="role-preview-search" class="mb-1 block text-sm font-medium text-slate-700">Buscar permiso o acción</label>
            <input
                id="role-preview-search"
                type="text"
                wire:model.live.debounce.300ms="q"
                placeholder="Ej. pagos.create, auditoría"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
            >
        </div>
    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Esta pantalla no permite edición. Si necesitas cambiar permisos, actualiza el seeder
        <span class="font-semibold">SyncRolesAndPermissionsSeeder</span> y vuelve a sincronizar.
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        @foreach ($roles as $role)
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $role['label'] }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $role['description'] }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                        {{ $role['allowed_permissions'] }}/{{ $role['total_permissions'] }}
                    </span>
                </div>

                @if (count($role['modules']) === 0)
                    <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        Sin resultados para "{{ $q }}".
                    </p>
                @else
                    <div class="space-y-4">
                        @foreach ($role['modules'] as $module)
                            <div class="rounded-lg border border-slate-200">
                                <div class="border-b border-slate-100 bg-slate-50 px-3 py-2">
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $module['label'] }}</h3>
                                </div>
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($module['permissions'] as $permission)
                                        <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                            <div>
                                                <p class="font-medium text-slate-800">{{ $permission['label'] }}</p>
                                                <p class="text-xs text-slate-500">{{ $permission['permission'] }}</p>
                                            </div>
                                            @if ($permission['allowed'])
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                    <span aria-hidden="true">✅</span>
                                                    Permitido
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700">
                                                    <span aria-hidden="true">❌</span>
                                                    No permitido
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</section>
