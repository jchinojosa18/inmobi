<tr wire:key="{{ $rowKey }}" class="transition hover:bg-slate-50/80">
    <td class="px-4 py-3 align-top">
        <p class="font-medium text-slate-900">#{{ $contract->id }}</p>
        @if ($contract->isExpired())
            <x-ui.badge variant="danger" class="mt-1">
                {{ __('contracts.status_expired_label') }}
            </x-ui.badge>
        @elseif ($contract->isExpiringSoon())
            <x-ui.badge variant="warning" class="mt-1">
                {{ __('contracts.status_expiring_label') }}
            </x-ui.badge>
        @else
            <x-ui.badge variant="success" class="mt-1">
                {{ __('common.active') }}
            </x-ui.badge>
        @endif
    </td>

    <td class="px-4 py-3 align-top">
        <p class="font-medium text-slate-900">{{ $contract->tenant->full_name }}</p>
        <p class="text-xs text-slate-500">
            {{ $contract->tenant->email ?: __('contracts.no_email') }}
            @if ($contract->tenant->phone)
                · {{ $contract->tenant->phone }}
            @endif
        </p>
    </td>

    <td class="px-4 py-3 align-top">
        <p class="font-medium text-slate-900">{{ $contract->unit->property->name }}</p>
        <p class="text-xs text-slate-500">
            {{ $contract->unit->name }}
            @if ($contract->unit->code)
                ({{ $contract->unit->code }})
            @endif
        </p>
    </td>

    <td class="px-4 py-3 align-top">
        @if ($contract->ends_at)
            <p class="font-medium text-slate-900">
                <x-ui.display-date :value="$contract->ends_at" />
            </p>
            @php $daysUntilEnd = $contract->daysUntilEnd(); @endphp
            <p class="text-xs text-slate-500">
                @if ($daysUntilEnd === 0)
                    {{ __('contracts.ends_today') }}
                @elseif ($daysUntilEnd > 0)
                    {{ __('contracts.ends_in_days', ['days' => $daysUntilEnd]) }}
                @else
                    {{ __('contracts.ended_days_ago', ['days' => abs($daysUntilEnd)]) }}
                @endif
            </p>
        @else
            <p class="text-slate-500">—</p>
        @endif
    </td>

    <td class="px-4 py-3 text-right align-top">
        <x-ui.button href="{{ route('contracts.show', $contract) }}" variant="secondary" size="sm">
            {{ __('contracts.view') }}
        </x-ui.button>
    </td>
</tr>
