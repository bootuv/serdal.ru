{{-- Вариант выбора картинкой (значок, цвет): квадрат 44, в слоте — значок; wide — по ширине подписи («Авто»).
     Выбранный — мятный с обводкой 1.5. Атрибуты (wire:click, aria-label, title) — на кнопку. --}}
@props(['on' => false, 'wide' => false])
<button type="button" aria-pressed="{{ $on ? 'true' : 'false' }}" {{ $attributes->class([
    'inline-flex h-11 shrink-0 items-center justify-center rounded text-t2 font-medium',
    'w-11' => ! $wide,
    'px-4' => $wide,
    'bg-mint shadow-selected' => $on,
    'bg-white shadow-outline hover:shadow-outline-ink' => ! $on,
]) }}>{{ $slot }}</button>
