{{-- Карточка: радиус 24, отступ 24. focus — мятный фокус-блок (один на экран). --}}
@props(['focus' => false, 'as' => 'section'])
<{{ $as }} {{ $attributes->class(['flex flex-col gap-4 rounded-xl p-6', 'bg-mint' => $focus, 'shadow-outline' => ! $focus]) }}>
    {{ $slot }}
</{{ $as }}>
