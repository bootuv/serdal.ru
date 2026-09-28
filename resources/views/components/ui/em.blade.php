{{-- Срочное внутри подписи (время, срок) — жирным, не бейджем. danger — уже проблема (работа ждёт проверки неделю). --}}
@props(['danger' => false])
<span {{ $attributes->class(['font-semibold', 'text-danger-fg' => $danger, 'text-ink' => ! $danger]) }}>{{ $slot }}</span>