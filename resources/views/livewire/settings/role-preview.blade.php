<section class="space-y-6">
    <x-ui.page-header
        :title="__('settings.roles_title')"
        :description="__('settings.roles_description')"
    >
        <x-slot:actions>
            <div class="w-full sm:w-72">
                <x-ui.input
                    id="role-preview-search"
                    :label="__('settings.search_permission')"
                    type="text"
                    wire:model.live.debounce.300ms="q"
                    :placeholder="__('settings.search_permission_placeholder')"
                />
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        {{ __('settings.roles_readonly_notice') }}
        <span class="font-semibold">SyncRolesAndPermissionsSeeder</span> {{ __('settings.roles_readonly_notice_suffix') }}
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        @foreach ($roles as $role)
            <x-ui.card>
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $role['label'] }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $role['description'] }}</p>
                    </div>
                    <x-ui.badge variant="neutral" class="font-semibold">
                        {{ $role['allowed_permissions'] }}/{{ $role['total_permissions'] }}
                    </x-ui.badge>
                </div>

                @if (count($role['modules']) === 0)
                    <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        {{ __('settings.no_results_for', ['query' => $q]) }}
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
                                                <x-ui.badge variant="success">
                                                    <span aria-hidden="true">✅</span>
                                                    {{ __('settings.allowed') }}
                                                </x-ui.badge>
                                            @else
                                                <x-ui.badge variant="danger">
                                                    <span aria-hidden="true">❌</span>
                                                    {{ __('settings.not_allowed') }}
                                                </x-ui.badge>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</section>
