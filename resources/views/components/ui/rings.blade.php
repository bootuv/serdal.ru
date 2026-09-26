{{-- Успеваемость: вложенные кольца + легенда (всегда видна).
     metrics: [['value' => 92, 'label' => 'Посещаемость', 'sub' => '22 из 24 занятий'], …] — максимум 3, порядок фиксирован.
     stacked — кольца над легендой (узкая колонка). Цвета chart-1..3 — только для графиков. --}}
@props(['metrics', 'stacked' => false])
@php
    $radii = [80, 62, 44];
    $strokes = ['stroke-chart-1', 'stroke-chart-2', 'stroke-chart-3'];
    $dots = ['bg-chart-1', 'bg-chart-2', 'bg-chart-3'];
    $aria = collect($metrics)->map(fn ($m) => $m['label'] . ' ' . $m['value'] . '%')->implode('; ');
@endphp
<div {{ $attributes->class(['flex gap-8', 'flex-col items-stretch gap-6' => $stacked, 'items-center' => ! $stacked]) }}>
    <svg viewBox="0 0 176 176" role="img" aria-label="Успеваемость: {{ $aria }}"
         class="{{ $stacked ? 'size-40 self-center' : 'size-40 lg:size-44' }} shrink-0 -rotate-90">
        @foreach ($metrics as $i => $m)
            @php $c = 2 * M_PI * $radii[$i]; $v = max(0, min(100, (int) $m['value'])); @endphp
            <circle cx="88" cy="88" r="{{ $radii[$i] }}" fill="none" stroke-width="14" class="{{ $strokes[$i] }}" stroke-opacity="0.16" />
            @if ($v > 0)
                <circle cx="88" cy="88" r="{{ $radii[$i] }}" fill="none" stroke-width="14" stroke-linecap="round" class="{{ $strokes[$i] }}"
                        stroke-dasharray="{{ round($c * $v / 100, 1) }} {{ round($c, 1) }}" />
            @endif
        @endforeach
    </svg>
    <div class="flex min-w-0 flex-1 flex-col gap-4">
        @foreach ($metrics as $i => $m)
            <div class="flex items-center gap-3">
                <span class="size-3 shrink-0 rounded-full {{ $dots[$i] }}"></span>
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="text-t1 font-medium">{{ $m['label'] }}</span>
                    @if (! empty($m['sub']))<span class="text-t2 text-muted">{{ $m['sub'] }}</span>@endif
                </div>
                <span class="text-h2 font-semibold tabular-nums">{{ $m['value'] }}%</span>
            </div>
        @endforeach
    </div>
</div>
