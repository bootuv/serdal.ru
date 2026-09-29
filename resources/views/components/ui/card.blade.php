{{-- Карточка: радиус 24, отступ 24. focus — мятный фокус-блок (один на экран). news — голубая карточка важной новости (только news-banner).
     Между блоками gap-4; свой отступ — классом gap-* (gap-0 для списков с шапкой таблицы). --}}
@props(['focus' => false, 'news' => false, 'as' => 'section'])
@php $ownGap = (bool) preg_match('/(^|\s)gap-/', (string) $attributes->get('class')); @endphp
{{-- Фокус-блок — то, ради чего страницу открыли: его подсвечивает тур по кабинету (data-tour="focus") --}}
<{{ $as }} {!! $focus ? 'data-tour="focus"' : '' !!} {{ $attributes->class(['flex flex-col rounded-xl p-6', 'gap-4' => ! $ownGap, 'bg-mint' => $focus, 'bg-news' => $news && ! $focus, 'shadow-outline' => ! $focus && ! $news]) }}>
    {{ $slot }}
</{{ $as }}>
