{{-- Меню действий «⋯»: кнопка 36 и список под ней. Пункты — x-ui.menu-item. align: right | left — к какому краю кнопки прижать список. --}}
@props(['label' => 'Действия', 'align' => 'right'])
<div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" {{ $attributes->class('relative') }}>
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="{{ $label }}" class="flex size-9 items-center justify-center rounded text-muted hover:bg-soft-hover hover:text-ink">
        <x-ui.icon name="more" />
    </button>
    <div x-show="open" x-cloak x-on:click="open = false" role="menu" @class([
        'absolute top-full z-10 mt-1 flex w-44 flex-col rounded border border-line bg-white p-1 shadow-card',
        'right-0' => $align === 'right',
        'left-0' => $align === 'left',
    ])>{{ $slot }}</div>
</div>
