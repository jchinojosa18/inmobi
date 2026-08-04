@php
    $sortable = $sortable ?? true;
    $sort = $sort ?? 'urgency';
    $dir = $dir ?? 'asc';
    $sortIndicator = static fn (string $field): string => $sort === $field ? ($dir === 'asc' ? '↑' : '↓') : '';
@endphp
<th class="px-4 py-3">{{ __('common.contract') }}</th>
<th class="px-4 py-3">
    @if ($sortable)
        <button type="button" wire:click="sortBy('tenant')" class="inline-flex items-center gap-1 hover:text-slate-800">
            {{ __('common.tenant') }} <span>{{ $sortIndicator('tenant') }}</span>
        </button>
    @else
        {{ __('common.tenant') }}
    @endif
</th>
<th class="px-4 py-3">
    @if ($sortable)
        <button type="button" wire:click="sortBy('unit')" class="inline-flex items-center gap-1 hover:text-slate-800">
            {{ __('contracts.property_unit') }} <span>{{ $sortIndicator('unit') }}</span>
        </button>
    @else
        {{ __('contracts.property_unit') }}
    @endif
</th>
<th class="px-4 py-3">
    @if ($sortable)
        <button type="button" wire:click="sortBy('ends_at')" class="inline-flex items-center gap-1 hover:text-slate-800">
            {{ __('contracts.expiration') }} <span>{{ $sortIndicator('ends_at') }}</span>
        </button>
    @else
        {{ __('contracts.expiration') }}
    @endif
</th>
<th class="px-4 py-3">
    @if ($sortable)
        <button type="button" wire:click="sortBy('next_due')" class="inline-flex items-center gap-1 hover:text-slate-800">
            {{ __('contracts.next_due') }} <span>{{ $sortIndicator('next_due') }}</span>
        </button>
    @else
        {{ __('contracts.next_due') }}
    @endif
</th>
<th class="px-4 py-3 text-right">{{ __('contracts.overdue_days') }}</th>
<th class="px-4 py-3 text-right">
    @if ($sortable)
        <button type="button" wire:click="sortBy('balance')" class="ml-auto inline-flex items-center gap-1 hover:text-slate-800">
            {{ __('common.balance') }} <span>{{ $sortIndicator('balance') }}</span>
        </button>
    @else
        {{ __('common.balance') }}
    @endif
</th>
<th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
