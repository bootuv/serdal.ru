{{-- Строка списка: линия сверху, отступ 16. С href — вся строка ссылка со стрелкой. --}}
@props(['href' => null, 'chevron' => true, 'align' => 'center'])
@php $classes = 'group flex gap-4 border-t border-line py-4 last:pb-0 text-ink ' . ($align === 'start' ? 'items-start' : 'items-center'); @endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
        @if ($chevron)<x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />@endif
    </a>
@else
    <div {{ $attributes->class($classes) }}>{{ $slot }}</div>
@endif
