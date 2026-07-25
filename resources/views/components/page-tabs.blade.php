{{--
    Единый компонент табов-навигации для всех страниц панелей.
    Стиль один везде: контейнер на всю ширину страницы, табы слева.

    Использование:
    <x-page-tabs :tabs="[
        ['label' => 'Все', 'href' => '...', 'active' => true, 'icon' => null],
    ]" />
--}}
@props(['tabs' => []])

@php
    // Активный таб должен быть выделен всегда: если ни один не совпал — первый
    $hasActive = collect($tabs)->contains(fn ($tab) => $tab['active'] ?? false);
@endphp

{{-- Без обёртки с overflow: у fi-tabs прокрутка встроена, а внешний overflow режет обводку (ring) --}}
<x-filament::tabs {{ $attributes->class(['w-full']) }}>
    @foreach ($tabs as $tab)
        <x-filament::tabs.item
            tag="a"
            :href="$tab['href']"
            :active="($tab['active'] ?? false) || (! $hasActive && $loop->first)"
            :icon="$tab['icon'] ?? null"
        >
            {{ $tab['label'] }}
        </x-filament::tabs.item>
    @endforeach
</x-filament::tabs>
