{{-- Поиск: поле 36 с иконкой. Атрибуты (wire:model.live.debounce) — в input. full — на всю ширину колонки. --}}
@props(['placeholder' => 'Поиск', 'full' => false])
<label @class(['relative flex w-full', 'lg:w-sidebar' => ! $full])>
    <span class="sr-only">{{ $placeholder }}</span>
    <x-ui.icon name="search" size="s" class="pointer-events-none absolute left-3 top-3 text-muted" />
    <input type="search" placeholder="{{ $placeholder }}" {{ $attributes->class('h-9 w-full rounded bg-white pl-8 pr-3 text-t2 text-ink shadow-outline outline-none placeholder:text-faint focus:shadow-outline-ink') }}>
</label>
