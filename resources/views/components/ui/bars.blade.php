{{-- Столбчатый график одного ряда по дням (статистика блога). items — [{label, value, hint}] по порядку, последний — сегодня.
     Столбики серые, сегодня — жёлтый, наведённый — чёрный; подпись наведённого столбика (hint) — в строке над графиком вместо caption.
     Для экранного диктора — скрытая таблица. Высота столбика — доля от максимума; координаты в процентах, как у x-ui.progress. --}}
@props(['items' => [], 'label', 'caption' => null])
@php
    $count = max(1, count($items));
    $max = max(1, ...array_map(fn ($i) => (int) $i['value'], $items ?: [['value' => 0]]));
    $slot = 100 / $count;
@endphp
<div x-data="{ hint: null }" {{ $attributes->class('flex flex-col gap-3') }}>
    <span class="text-t2 text-muted" x-text="hint ?? {{ Js::from((string) $caption) }}" aria-hidden="true">{{ $caption }}</span>
    <svg class="h-40 w-full" role="img" aria-label="{{ $label }}">
        <rect y="100%" width="100%" height="1" transform="translate(0 -1)" class="fill-line" />
        @foreach ($items as $i => $item)
            @php $h = $item['value'] > 0 ? max(2, round($item['value'] / $max * 100, 2)) : 0; @endphp
            <g class="group" x-on:mouseenter="hint = {{ Js::from($item['hint']) }}" x-on:mouseleave="hint = null">
                <rect x="{{ round($i * $slot, 3) }}%" width="{{ round($slot, 3) }}%" height="100%" class="fill-transparent" />
                @if ($h > 0)
                    <rect x="{{ round($i * $slot + $slot * 0.15, 3) }}%" y="{{ 100 - $h }}%" width="{{ round($slot * 0.7, 3) }}%" height="{{ $h }}%" rx="3"
                          @class(['transition-colors group-hover:fill-ink', 'fill-brand' => $loop->last, 'fill-faint' => ! $loop->last]) />
                @endif
            </g>
        @endforeach
    </svg>
    @if (count($items) > 1)
        <div class="flex justify-between text-t3 text-muted" aria-hidden="true">
            <span>{{ $items[0]['label'] }}</span>
            <span>{{ $items[count($items) - 1]['label'] }}</span>
        </div>
    @endif
    <table class="sr-only">
        <caption>{{ $label }}</caption>
        @foreach ($items as $item)
            <tr><td>{{ $item['hint'] }}</td></tr>
        @endforeach
    </table>
</div>
