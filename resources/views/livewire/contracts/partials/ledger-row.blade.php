@php
    $statusVariant = match ($row['status_tone']) {
        'red' => 'danger',
        'amber' => 'warning',
        'emerald' => 'success',
        'blue' => 'info',
        default => 'neutral',
    };
@endphp
<tr @class([
    'bg-slate-50/60' => $nested,
])>
    <td @class([
        'px-4 py-3',
        'pl-10 text-slate-600' => $nested,
    ])>
        {{ $row['period_label'] }}
    </td>
    <td @class([
        'px-4 py-3 font-medium',
        'text-slate-900' => ! $nested,
        'text-rose-600' => $nested,
    ])>
        {{ $row['type_label'] ?? $row['type'] }}
    </td>
    <td class="px-4 py-3">{{ $row['charge_date'] ?: '-' }}</td>
    <td class="px-4 py-3">{{ $row['due_date'] }}</td>
    <td class="px-4 py-3 text-right">${{ number_format($row['amount'], 2) }}</td>
    <td class="px-4 py-3 text-right">${{ number_format($row['paid'], 2) }}</td>
    <td class="px-4 py-3 text-right font-medium text-slate-900">${{ number_format($row['balance'], 2) }}</td>
    <td class="px-4 py-3">
        <x-ui.badge :variant="$statusVariant">
            {{ $row['status_label'] }}
        </x-ui.badge>
    </td>
</tr>
