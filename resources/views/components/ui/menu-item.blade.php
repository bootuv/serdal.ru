{{-- Пункт меню действий (x-ui.menu). Атрибуты (wire:click, href) — на кнопку. --}}
<button type="button" role="menuitem" {{ $attributes->class('flex h-9 w-full items-center rounded-sm px-3 text-left text-t2 font-medium text-ink hover:bg-soft-hover') }}>{{ $slot }}</button>
