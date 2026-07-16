<section class="space-y-6">
    <x-ui.page-header
        :title="__('cobranza.title')"
        :description="__('cobranza.description')"
    />

    <x-ui.card :padding="true" class="!p-4">
        <div class="grid gap-3 md:grid-cols-6">
            <div class="md:col-span-2">
                <x-ui.input
                    :label="__('common.tenant')"
                    type="text"
                    wire:model.live.debounce.350ms="q"
                    :placeholder="__('cobranza.tenant_placeholder')"
                />
            </div>

            <x-ui.select :label="__('cobranza.property')" wire:model.live="property_id">
                <option value="">{{ __('cobranza.all_feminine') }}</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}">{{ $property->name }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select :label="__('common.unit')" wire:model.live="unit_id">
                <option value="">{{ __('cobranza.all_feminine') }}</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' ('.$unit->code.')' : '' }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input
                :label="__('cobranza.days_min')"
                type="number"
                min="0"
                wire:model.live="days_min"
            />

            <x-ui.input
                :label="__('cobranza.days_max')"
                type="number"
                min="0"
                wire:model.live="days_max"
            />
        </div>
    </x-ui.card>

    <x-ui.table>
        <x-slot:header>
            <nav class="flex flex-wrap gap-1 py-1 text-sm">
                <button
                    type="button"
                    wire:click="$set('tab', 'overdue')"
                    class="rounded-lg px-3 py-1.5 font-medium {{ $tab === 'overdue' ? 'bg-amber-50 text-amber-800' : 'text-slate-600 hover:bg-slate-50' }}"
                >
                    {{ __('cobranza.tabs.overdue') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('tab', 'grace')"
                    class="rounded-lg px-3 py-1.5 font-medium {{ $tab === 'grace' ? 'bg-sky-50 text-sky-700' : 'text-slate-600 hover:bg-slate-50' }}"
                >
                    {{ __('cobranza.tabs.grace') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('tab', 'current')"
                    class="rounded-lg px-3 py-1.5 font-medium {{ $tab === 'current' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50' }}"
                >
                    {{ __('cobranza.tabs.current') }}
                </button>
            </nav>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('common.contract') }}</th>
            <th class="px-4 py-3">{{ __('common.tenant') }}</th>
            <th class="px-4 py-3">{{ __('cobranza.property_unit') }}</th>
            <th class="px-4 py-3">{{ __('cobranza.pending_rent') }}</th>
            <th class="px-4 py-3 text-right">{{ __('cobranza.overdue_days') }}</th>
            <th class="px-4 py-3 text-right">{{ __('cobranza.pending_balance') }}</th>
            <th class="px-4 py-3 text-right">{{ __('cobranza.credit_balance') }}</th>
            <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($contracts as $row)
                <tr wire:key="cobranza-row-{{ $row->contract_id }}" class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-3 font-medium text-slate-900">#{{ $row->contract_id }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ $row->tenant_name }}
                        <p class="text-xs text-slate-500">{{ $row->tenant_phone ?: ($row->tenant_email ?: __('common.no_contact')) }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        <p>{{ $row->overdue_period ?: __('cobranza.no_period') }}</p>
                        <p class="text-xs text-slate-500">
                            {{ __('cobranza.due') }}: {{ $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('Y-m-d') : __('cobranza.nd') }}
                            · {{ __('cobranza.grace') }}: {{ $row->grace_until ? \Carbon\Carbon::parse($row->grace_until)->format('Y-m-d') : __('cobranza.nd') }}
                        </p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if ((int) $row->overdue_days > 0)
                            <x-ui.badge variant="{{ (int) $row->overdue_days > 7 ? 'danger' : 'warning' }}">
                                {{ (int) $row->overdue_days }} {{ __('common.days') }}
                            </x-ui.badge>
                        @else
                            <span class="text-slate-500">0</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-900">${{ number_format((float) $row->pending_balance, 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold {{ (float) $row->credit_balance > 0 ? 'text-emerald-700' : 'text-slate-500' }}">
                        ${{ number_format((float) $row->credit_balance, 2) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex flex-wrap justify-end gap-2">
                            @if ($canCreatePayments)
                                <x-ui.button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $row->contract_id }} })"
                                >
                                    {{ __('common.register_payment') }}
                                </x-ui.button>
                            @endif
                            <x-ui.button
                                type="button"
                                variant="secondary"
                                size="sm"
                                class="!border-emerald-200 !text-emerald-700 hover:!bg-emerald-50"
                                x-data
                                x-on:click="
                                    navigator.clipboard.writeText(@js($row->whatsapp_message));
                                    window.dispatchEvent(new CustomEvent('notify-copy'));
                                "
                            >
                                {{ __('cobranza.copy_whatsapp') }}
                            </x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('cobranza.empty')" :colspan="8" />
            @endforelse
        </x-slot:body>
        <x-slot:footer>
            <div class="px-4 py-3">
                {{ $contracts->links() }}
            </div>
        </x-slot:footer>
    </x-ui.table>

    <div
        x-data="{ show: false }"
        x-on:notify-copy.window="show = true; setTimeout(() => show = false, 1400)"
        x-show="show"
        x-transition
        class="fixed bottom-4 right-4 rounded-lg bg-slate-800 px-4 py-2 text-xs font-medium text-white shadow-lg"
        style="display: none;"
    >
        {{ __('cobranza.message_copied') }}
    </div>
</section>
