{{-- Чип-переключатель (направления, классы, дни недели): высота 40, выбранный — чёрный.
     on — выбран; square — квадрат 44 для коротких подписей (пн, вт…). Атрибуты (wire:click, aria-label) — на кнопку. --}}
@props(['on' => false, 'square' => false])
<button type="button" aria-pressed="{{ $on ? 'true' : 'false' }}" {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center rounded transition-colors',
    'size-11 text-t2' => $square,
    'h-10 px-4 text-t1-s' => ! $square,
    'bg-ink font-semibold text-white' => $on,
    'bg-white text-ink shadow-outline hover:shadow-outline-ink' => ! $on,
]) }}>{{ $slot }}</button>
