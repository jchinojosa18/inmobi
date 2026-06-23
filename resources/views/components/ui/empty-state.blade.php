@props(['title', 'description' => null, 'colspan' => 1])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-8 text-center">
        <p class="text-sm font-medium text-slate-700">{{ $title }}</p>
        @if ($description)
            <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
        @endif
        @isset($action)
            <div class="mt-3">
                {{ $action }}
            </div>
        @endisset
    </td>
</tr>
