{{-- Кнопка «Скопировать»: кладёт value в буфер и показывает тост message. Остальные атрибуты (variant, size, icon) — как у x-ui.btn. --}}
@props(['value', 'message' => 'Скопировано'])
<x-ui.btn {{ $attributes }} x-data x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($value) }}); $dispatch('toast', { message: {{ \Illuminate\Support\Js::from($message) }} })">{{ $slot->isEmpty() ? 'Скопировать' : $slot }}</x-ui.btn>
