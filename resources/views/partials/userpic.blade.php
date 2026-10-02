{{-- Фото человека на сайте. Нет фото — инициалы на цветном блоке, цвет закреплён за человеком по id (как x-ui.avatar в кабинете).
     Параметры: user, class (размер и скругление), thumb (уменьшенная копия для списков), alt, attrs (доп. атрибуты img). --}}
@php
    $thumb = $thumb ?? true;
    $alt = $alt ?? '';
    $attrs = $attrs ?? 'loading="lazy"';
@endphp
@if ($user->avatar)
    <img src="{{ $thumb ? $user->avatarThumbUrl : $user->avatarUrl }}" alt="{{ $alt }}" class="{{ $class }}" {!! $attrs !!}>
@else
    @php
        $parts = preg_split('/\s+/u', trim($user->name ?? ''));
        $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    @endphp
    <div class="{{ $class }} userpic-initials userpic-initials-{{ $user->id % 3 + 1 }}" @if ($alt) role="img" aria-label="{{ $alt }}" @else aria-hidden="true" @endif><span>{{ $initials }}</span></div>
@endif
