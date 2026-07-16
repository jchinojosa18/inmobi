@php
    $current = app()->getLocale();
    $active = 'rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-900 shadow-sm';
    $inactive = 'rounded-full px-2.5 py-1 text-xs font-medium text-slate-500 hover:text-slate-700';
@endphp

<form
    method="POST"
    action="{{ route('locale.update') }}"
    class="inline-flex rounded-full border border-slate-200 bg-slate-50 p-0.5"
    aria-label="{{ __('ui.language') }}"
>
    @csrf
    <button
        type="submit"
        name="locale"
        value="es"
        @if ($current === 'es') aria-current="true" @endif
        class="{{ $current === 'es' ? $active : $inactive }}"
    >ES</button>
    <button
        type="submit"
        name="locale"
        value="en"
        @if ($current === 'en') aria-current="true" @endif
        class="{{ $current === 'en' ? $active : $inactive }}"
    >EN</button>
</form>
