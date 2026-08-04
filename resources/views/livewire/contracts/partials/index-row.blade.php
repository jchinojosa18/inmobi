@php
    $nextDueDate = $contract->next_due_date ? \Illuminate\Support\Carbon::parse($contract->next_due_date) : null;
    $graceUntil = $contract->grace_until ? \Illuminate\Support\Carbon::parse($contract->grace_until) : null;
    $overdueStatusLabel = match ($contract->overdue_status) {
        'overdue' => __('contracts.overdue_status.overdue'),
        'grace' => __('contracts.overdue_status.grace'),
        default => __('contracts.overdue_status.current'),
    };
    $overdueBadgeVariant = match ($contract->overdue_status) {
        'overdue' => 'danger',
        'grace' => 'warning',
        default => 'success',
    };
@endphp
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
            <x-ui.badge :variant="$contract->status === 'active' ? 'success' : 'neutral'" class="mt-1">
                {{ $contract->status === 'active' ? __('common.active') : __('common.finished') }}
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
            @if ($contract->status === 'active')
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
            @endif
        @else
            <p class="text-slate-500">—</p>
        @endif
    </td>

    <td class="px-4 py-3 align-top">
        @if ($nextDueDate)
            <p class="font-medium text-slate-900"><x-ui.display-date :value="$nextDueDate" /></p>
            <p class="text-xs text-slate-500">{{ __('contracts.grace_until', ['date' => \App\Support\DateDisplay::formatDate($graceUntil)]) }}</p>
            <x-ui.badge :variant="$overdueBadgeVariant" class="mt-1">
                {{ $overdueStatusLabel }}
            </x-ui.badge>
        @else
            <p class="font-medium text-slate-700">{{ __('contracts.no_charges') }}</p>
            <p class="text-xs text-slate-500">{{ __('contracts.no_pending_rent_due') }}</p>
        @endif
    </td>

    <td class="px-4 py-3 text-right align-top font-medium text-slate-900">
        {{ max((int) $contract->overdue_days, 0) }}
    </td>

    <td class="px-4 py-3 text-right align-top">
        <p class="font-medium text-slate-900">${{ number_format((float) $contract->pending_balance, 2) }}</p>
        <p class="text-xs text-slate-500">{{ __('contracts.credit_balance_short') }}: ${{ number_format((float) $contract->credit_balance, 2) }}</p>
    </td>

    <td class="px-4 py-3 text-right align-top">
        <div class="flex justify-end gap-2">
            <x-ui.button href="{{ route('contracts.show', $contract) }}" variant="secondary" size="sm">
                {{ __('contracts.view') }}
            </x-ui.button>
            @if ($canCreatePayments)
                <x-ui.button
                    type="button"
                    variant="accent"
                    size="sm"
                    onclick="Livewire.dispatch('open-quick-payment', { contractId: {{ $contract->id }} })"
                >
                    {{ __('common.register_payment') }}
                </x-ui.button>
            @endif
        </div>
    </td>
</tr>
