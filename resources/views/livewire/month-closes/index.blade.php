<section class="space-y-6">
    <x-ui.page-header
        :title="__('finance.month_closes.title')"
        :description="__('finance.month_closes.description')"
    />

    <x-ui.card>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('finance.month_closes.close_month') }}</h2>
        <form wire:submit="closeMonth" class="mt-3 grid gap-3 md:grid-cols-4">
            <div>
                <x-ui.input
                    id="month-to-close"
                    :label="__('finance.month_closes.month_label')"
                    type="month"
                    wire:model="monthToClose"
                    :disabled="! $canCloseMonth"
                />
                @error('monthToClose') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <x-ui.input
                    id="month-close-notes"
                    :label="__('common.notes').' ('.__('common.optional').')'"
                    type="text"
                    wire:model.blur="notes"
                    maxlength="500"
                    :placeholder="__('finance.month_closes.notes_placeholder')"
                    :disabled="! $canCloseMonth"
                />
            </div>
            <div class="flex items-end justify-end">
                @if ($canCloseMonth)
                    <x-ui.button type="submit">
                        {{ __('finance.month_closes.close_month_action') }}
                    </x-ui.button>
                @else
                    <span class="text-xs text-slate-500">{{ __('finance.month_closes.no_close_permission') }}</span>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('common.month') }}</th>
            <th class="px-4 py-3">{{ __('common.status') }}</th>
            <th class="px-4 py-3">{{ __('finance.month_closes.closed_by') }}</th>
            <th class="px-4 py-3">{{ __('finance.month_closes.closed_at') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @foreach ($rows as $row)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $row['month'] }}</td>
                    <td class="px-4 py-3">
                        @if ($row['is_closed'])
                            <x-ui.badge variant="danger">{{ __('finance.month_closes.closed') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="success">{{ __('finance.month_closes.open') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $row['closed_by'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700"><x-ui.display-date :value="$row['closed_at']" time :empty="'-'" /></td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if (! $row['is_closed'])
                                <x-ui.button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    wire:click="closeMonth('{{ $row['month'] }}')"
                                >
                                    {{ __('finance.month_closes.close_month_action') }}
                                </x-ui.button>
                            @elseif ($canReopenMonth)
                                <x-ui.button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    wire:click="reopenMonth('{{ $row['month'] }}')"
                                >
                                    {{ __('finance.month_closes.reopen_month') }}
                                </x-ui.button>
                            @else
                                <span class="text-xs text-slate-500">{{ __('finance.month_closes.no_reopen_permission') }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot:body>
    </x-ui.table>
</section>
