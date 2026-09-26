{{-- Успеваемость: вложенные кольца + общая оценка в центре + легенда (всегда видна).
     metrics: [['value' => 92, 'label' => 'Посещаемость', 'sub' => '22 из 24 занятий', 'empty' => false], …] — максимум 3, порядок фиксирован.
     В центре — общая оценка: среднее по показателям с данными (empty — показателя ещё нет, в среднее не входит).
     stacked — кольца над легендой всегда (узкая колонка); без stacked — над легендой на телефоне, рядом с ней с sm.
     Цвета chart-1..3 — только для графиков. --}}
@props(['metrics', 'stacked' => false])
@php
    // Кольца 12 с зазором 4; внутри остаётся круг ~90 для общей оценки
    $radii = [82, 66, 50];
    $strokes = ['stroke-chart-1', 'stroke-chart-2', 'stroke-chart-3'];
    $dots = ['bg-chart-1', 'bg-chart-2', 'bg-chart-3'];
    $total = \App\Services\StudentPerformanceService::overall($metrics);
    $aria = ($total !== null ? 'в среднем ' . $total . '%; ' : '') . collect($metrics)->map(fn ($m) => $m['label'] . ' ' . $m['value'] . '%')->implode('; ');
@endphp
<div {{ $attributes->class(['flex flex-col items-stretch gap-6', 'sm:flex-row sm:items-center sm:gap-8' => ! $stacked]) }}>
    <div class="relative shrink-0 self-center">
    <svg viewBox="0 0 176 176" role="img" aria-label="Успеваемость: {{ $aria }}"
         class="{{ $stacked ? 'size-40' : 'size-40 lg:size-44' }} -rotate-90">
        @foreach ($metrics as $i => $m)
            @php $c = 2 * M_PI * $radii[$i]; $v = max(0, min(100, (int) $m['value'])); @endphp
            <circle cx="88" cy="88" r="{{ $radii[$i] }}" fill="none" stroke-width="12" class="{{ $strokes[$i] }}" stroke-opacity="0.16" />
            @if ($v > 0)
                <circle cx="88" cy="88" r="{{ $radii[$i] }}" fill="none" stroke-width="12" stroke-linecap="round" class="{{ $strokes[$i] }}"
                        stroke-dasharray="{{ round($c * $v / 100, 1) }} {{ round($c, 1) }}" />
            @endif
        @endforeach
    </svg>
        {{-- Общая оценка: подпись не нужна — карточка называется «Успеваемость» --}}
        <span class="absolute inset-0 flex items-center justify-center text-h2 font-semibold tabular-nums" aria-hidden="true">{{ $total !== null ? $total . '%' : '—' }}</span>
    </div>
    <div class="flex min-w-0 flex-1 flex-col gap-4">
        @foreach ($metrics as $i => $m)
            <div class="flex items-center gap-3">
                <span class="size-3 shrink-0 rounded-full {{ $dots[$i] }}"></span>
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="text-t1 font-medium">{{ $m['label'] }}</span>
                    @if (! empty($m['sub']))<span class="text-t2 text-muted">{{ $m['sub'] }}</span>@endif
                </div>
                <span @class(['text-h2 font-semibold tabular-nums', 'text-faint' => $m['empty'] ?? false])>{{ ($m['empty'] ?? false) ? '—' : $m['value'] . '%' }}</span>
            </div>
        @endforeach
    </div>
</div>
