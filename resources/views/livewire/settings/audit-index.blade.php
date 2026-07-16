<section class="space-y-6">
    <x-ui.page-header
        :title="__('settings.audit_title')"
        :description="__('settings.audit_description')"
    >
        <x-slot:actions>
            <x-ui.button
                variant="secondary"
                href="{{ route('settings.audit.export') }}?{{ http_build_query(['date_from' => $dateFrom, 'date_to' => $dateTo, 'actor_user_id' => $actorUserId, 'action' => $action, 'search' => $search]) }}"
            >
                {{ __('settings.export_csv') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.input :label="__('common.from')" type="date" wire:model.live="dateFrom" />
            <x-ui.input :label="__('common.to')" type="date" wire:model.live="dateTo" />
            <x-ui.select :label="__('settings.user')" wire:model.live="actorUserId">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($actors as $actor)
                    <option value="{{ $actor->id }}">{{ $actor->name }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select :label="__('settings.action')" wire:model.live="action">
                <option value="">{{ __('settings.all_actions') }}</option>
                @foreach ($actions as $act)
                    <option value="{{ $act }}">{{ $act }}</option>
                @endforeach
            </x-ui.select>
            <div class="sm:col-span-2">
                <x-ui.input :label="__('settings.search_summary')" type="text" wire:model.live.debounce.400ms="search" :placeholder="__('settings.search_placeholder')" />
            </div>
            <x-ui.select :label="__('settings.entity')" wire:model.live="auditableType">
                <option value="">{{ __('settings.all_entities') }}</option>
                @foreach ($auditableTypes as $fullClass => $label)
                    <option value="{{ $fullClass }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
        </div>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-2 whitespace-nowrap">{{ __('settings.datetime') }}</th>
            <th class="px-4 py-2">{{ __('settings.user') }}</th>
            <th class="px-4 py-2">{{ __('settings.action') }}</th>
            <th class="px-4 py-2">{{ __('settings.summary') }}</th>
            <th class="px-4 py-2">{{ __('settings.entity') }}</th>
            <th class="px-4 py-2 text-right">{{ __('common.view') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($events as $event)
                <tr class="hover:bg-slate-50/80">
                    <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-500">
                        {{ $event->occurred_at->timezone('America/Tijuana')->format('Y-m-d H:i') }}
                    </td>
                    <td class="px-4 py-2">
                        @if ($event->actor)
                            <span class="font-medium">{{ $event->actor->name }}</span>
                            <span class="block text-xs text-slate-400">{{ $event->actor->email }}</span>
                        @else
                            <span class="text-slate-400">{{ __('settings.system') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <x-ui.badge variant="neutral" class="font-mono">{{ $event->action }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-2 max-w-xs truncate">{{ $event->summary }}</td>
                    <td class="px-4 py-2 text-xs text-slate-500">
                        @if ($event->auditable_type)
                            {{ class_basename($event->auditable_type) }} #{{ $event->auditable_id }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <x-ui.button type="button" wire:click="viewEvent({{ $event->id }})" variant="secondary" size="sm">
                            {{ __('common.view') }}
                        </x-ui.button>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('settings.empty_audit_events')" :colspan="6" />
            @endforelse
        </x-slot:body>
        @if ($events->hasPages())
            <x-slot:footer>
                <div class="px-4 py-3">
                    {{ $events->links() }}
                </div>
            </x-slot:footer>
        @endif
    </x-ui.table>

    @if ($selectedEvent)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h2 class="text-base font-semibold">{{ __('settings.event_detail') }}</h2>
                        <p class="text-xs text-slate-500">
                            {{ $selectedEvent->occurred_at->timezone('America/Tijuana')->format('Y-m-d H:i:s') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeEvent" class="rounded p-1 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="space-y-4 p-6">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('settings.action') }}</p>
                            <p class="mt-1 font-mono text-slate-800">{{ $selectedEvent->action }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('settings.user') }}</p>
                            <p class="mt-1">{{ $selectedEvent->actor?->name ?? __('settings.system') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('settings.entity') }}</p>
                            <p class="mt-1">
                                @if ($selectedEvent->auditable_type)
                                    {{ class_basename($selectedEvent->auditable_type) }} #{{ $selectedEvent->auditable_id }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">IP</p>
                            <p class="mt-1 font-mono text-xs">{{ $selectedEvent->ip ?? '—' }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('settings.summary') }}</p>
                        <p class="mt-1 text-sm">{{ $selectedEvent->summary }}</p>
                    </div>
                    @if ($selectedEvent->user_agent)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('settings.user_agent') }}</p>
                            <p class="mt-1 break-all text-xs text-slate-500">{{ $selectedEvent->user_agent }}</p>
                        </div>
                    @endif
                    @if ($selectedEvent->meta)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('settings.metadata') }}</p>
                            <pre class="mt-1 max-h-60 overflow-auto rounded-lg bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($selectedEvent->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>
                <div class="border-t border-slate-200 px-6 py-4 text-right">
                    <x-ui.button type="button" wire:click="closeEvent">
                        {{ __('common.close') }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</section>
