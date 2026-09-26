{{-- Фильтр списка: кнопка 36 с обводкой; выбранный — тёмная обводка 1.5 и полужирный.
     toggle — переключатель без списка («Без учителя»; атрибуты wire:click — на кнопку), иначе кнопка открывает список под собой (слот).
     keep — список не закрывается по клику внутри (чипы классов с «Готово»). --}}
@props(['label', 'active' => false, 'toggle' => false, 'keep' => false])
@php
    $classes = 'inline-flex h-9 shrink-0 items-center gap-2 whitespace-nowrap rounded px-3 text-t2 text-ink '
        . ($active ? 'font-semibold shadow-selected' : 'font-medium shadow-outline hover:shadow-outline-ink');
@endphp
@if ($toggle)
    <button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->class($classes) }}>@if ($active)<x-ui.icon name="check" size="s" />@endif{{ $label }}</button>
@else
    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
        <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true" {{ $attributes->class($classes) }}>{{ $label }}<x-ui.icon name="chevron-down" size="s" class="text-muted" /></button>
        <div x-show="open" x-cloak {!! $keep ? '' : 'x-on:click="open = false"' !!} class="absolute left-0 top-full z-10 mt-1 flex w-sidebar flex-col rounded-lg border border-line bg-white p-1 shadow-card">
            {{ $slot }}
        </div>
    </div>
@endif
