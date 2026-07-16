@props([
    'variant' => 'sidebar', // sidebar | guest
    'showOrg' => false,
    'href' => null,
])

@php
    $isSidebar = $variant === 'sidebar';
    $markClass = $isSidebar ? 'h-8 w-8' : 'h-9 w-9';
    $wordClass = $isSidebar
        ? 'text-base font-semibold tracking-tight text-white'
        : 'text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100';
    $orgName = $showOrg ? (auth()->user()?->organization?->name) : null;
    $tag = $href ? 'a' : 'div';
@endphp

<div {{ $attributes->class(['flex items-center gap-2.5 min-w-0']) }}>
    <{{ $tag }}
        @if ($href) href="{{ $href }}" @endif
        class="flex min-w-0 items-center gap-2.5"
    >
        <img
            src="{{ asset('images/brand/axis-mark.svg') }}"
            alt="AXIS"
            class="{{ $markClass }} shrink-0"
            width="32"
            height="32"
        >
        <span class="min-w-0">
            <span class="block {{ $wordClass }}">AXIS</span>
            @if ($orgName)
                <span class="block truncate text-xs text-slate-400">{{ $orgName }}</span>
            @endif
        </span>
    </{{ $tag }}>
</div>
