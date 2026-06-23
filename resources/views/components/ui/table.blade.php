@props(['compact' => false])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm']) }}>
    @isset($header)
        <div class="border-b border-slate-100 px-4 py-3">
            {{ $header }}
        </div>
    @endisset
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    {{ $head }}
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                {{ $body }}
            </tbody>
        </table>
    </div>
    @isset($footer)
        <div class="border-t border-slate-100">
            {{ $footer }}
        </div>
    @endisset
</div>
