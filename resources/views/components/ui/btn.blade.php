{{-- Кнопка: три стиля (primary — жёлтая, одна на экран; dark — ≤ 1 на экран; outline — остальное).
     size: s (36) | m (44) | l (52). Если передан href — рендерится ссылкой. --}}
@props(['variant' => 'outline', 'size' => 'm', 'href' => null, 'icon' => null, 'square' => false, 'type' => 'button'])
@php
    $base = 'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap font-medium transition-colors disabled:cursor-default disabled:opacity-50';
    $sizes = [
        's' => $square ? 'size-9 rounded text-t2' : 'h-9 rounded px-3 text-t2',
        'm' => $square ? 'size-11 rounded text-t1-s' : 'h-11 rounded px-4 text-t1-s',
        'l' => $square ? 'size-13 rounded text-t1' : 'h-13 rounded px-6 text-t1',
    ];
    $variants = [
        'primary' => 'bg-brand text-ink hover:bg-brand-hover',
        'dark' => 'bg-ink text-white hover:bg-ink-hover',
        'outline' => 'bg-transparent text-ink shadow-outline hover:shadow-outline-ink',
    ];
    $classes = $base . ' ' . $sizes[$size] . ' ' . $variants[$variant];
    $iconSize = $size === 's' ? 's' : 'm';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>@if ($icon)<x-ui.icon :name="$icon" :size="$iconSize" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>@if ($icon)<x-ui.icon :name="$icon" :size="$iconSize" />@endif{{ $slot }}</button>
@endif
