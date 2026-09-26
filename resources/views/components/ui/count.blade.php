{{-- Красный счётчик (уведомления, непрочитанное). Ноль не показываем. --}}
@props(['value'])
@if ($value > 0)
    <span {{ $attributes->class('inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-count font-semibold text-white') }}>{{ $value > 99 ? '99+' : $value }}</span>
@endif
