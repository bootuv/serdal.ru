{{-- Аватар 40 (lg — 64, радиус 16; xl — 80, радиус 24). Цвет закреплён за человеком по id. group — иконка группы. --}}
@props(['user' => null, 'name' => null, 'id' => 0, 'size' => 'm', 'group' => false])
@php
    $name = $name ?? $user?->name ?? '';
    $id = $user?->id ?? $id;
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
<span {{ $attributes->class("inline-flex shrink-0 items-center justify-center font-semibold text-ink $bg $box") }} aria-hidden="true">
    @if ($group)<x-ui.icon name="users" size="s" />@else{{ $initials }}@endif
</span>
