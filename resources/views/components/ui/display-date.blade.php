@props([
    'value' => null,
    'time' => false,
    'empty' => '-',
])

{{ $time
    ? \App\Support\DateDisplay::formatDateTime($value, $empty)
    : \App\Support\DateDisplay::formatDate($value, $empty) }}
