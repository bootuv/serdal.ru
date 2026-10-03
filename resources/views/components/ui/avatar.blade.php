{{-- Аватар 40 (lg — 64, радиус 16; xl — 80, радиус 24). Есть фото — фото, нет — инициалы, цвет закреплён за человеком по id. group — иконка группы.
     photo — адрес фото, если человек передан не моделью (null — берём у user, false — показать инициалы, даже если фото есть). --}}
@props(['user' => null, 'name' => null, 'id' => 0, 'photo' => null, 'size' => 'm', 'group' => false])
@php
    $name = $name ?? $user?->name ?? '';
    $id = $user?->id ?? $id;
    $photo = $group ? null : ($photo ?? ($user?->avatar ? $user->photoThumb() : null));
    $parts = preg_split('/\s+/u', trim($name));
    $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    $colors = ['bg-av-1', 'bg-av-2', 'bg-av-3'];
    $bg = $group ? 'bg-soft' : $colors[$id % 3];
    $box = match ($size) {
        'xl' => 'size-20 rounded-xl text-h2',
        'lg' => 'size-16 rounded-lg text-h2',
        default => 'size-10 rounded text-t2',
    };
@endphp
@if ($photo)
    <img src="{{ $photo }}" alt="" loading="lazy" {{ $attributes->class("shrink-0 object-cover $bg $box") }} aria-hidden="true">
@else
    <span {{ $attributes->class("inline-flex shrink-0 items-center justify-center font-semibold text-ink $bg $box") }} aria-hidden="true">
        @if ($group)<x-ui.icon name="users" size="s" />@else{{ $initials }}@endif
    </span>
@endif
