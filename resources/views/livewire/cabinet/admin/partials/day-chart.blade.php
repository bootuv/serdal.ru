{{-- Светлый столбчатый график «Занятия по дням» (макет AdminToday). Столбцы av-2, свежий день и наведение — chart-1,
     пунктир — среднее за период. Координаты — проценты высоты области графика (без инлайн-стилей).
     $chart: bars[{v, top, now, title, label}], ticks[{label, y}], avgY, avgText, days, radius, label. --}}
@php
    // Промежутки и ширина столбцов по периоду (классы здесь, чтобы их видел Tailwind)
    $gap = match ($chart['days']) { 7 => 'gap-6', 30 => 'gap-2', default => 'gap-px' };
    $barMax = match ($chart['days']) { 7 => 'max-w-12', 30 => 'max-w-4', default => 'max-w-2' };
@endphp
<div class="flex flex-col gap-2" role="img" aria-label="{{ $chart['label'] }}">
    <div class="flex gap-3">
        {{-- Ось значений --}}
        <svg class="h-44 w-8 shrink-0 overflow-visible" aria-hidden="true">
            @foreach ($chart['ticks'] as $t)
                <text x="100%" y="{{ $t['y'] }}%" dy="4" text-anchor="end" class="fill-muted text-t3">{{ $t['label'] }}</text>
            @endforeach
        </svg>

        {{-- Область графика --}}
        <div class="relative h-44 min-w-0 flex-1">
            <svg class="pointer-events-none absolute inset-0 size-full overflow-visible" aria-hidden="true">
                @foreach ($chart['ticks'] as $t)
                    <line x1="0" x2="100%" y1="{{ $t['y'] }}%" y2="{{ $t['y'] }}%" class="stroke-line" stroke-width="1" />
                @endforeach
            </svg>
            <div class="absolute inset-0 flex items-end {{ $gap }}">
                @foreach ($chart['bars'] as $i => $b)
                    <div class="group relative flex h-full min-w-0 flex-1 justify-center" wire:key="bar-{{ $i }}">
                        <svg class="h-full w-full overflow-visible {{ $barMax }}">
                            <title>{{ $b['title'] }}</title>
                            <defs><clipPath id="bar-clip-{{ $i }}"><rect width="100%" height="100%" /></clipPath></defs>
                            @if ($b['v'] > 0)
                                <rect x="0" y="{{ $b['top'] }}%" width="100%" height="{{ 100 - $b['top'] + 10 }}%" rx="{{ $chart['radius'] }}" clip-path="url(#bar-clip-{{ $i }})"
                                      class="{{ $b['now'] ? 'fill-chart-1' : 'fill-av-2 group-hover:fill-chart-1' }}" />
                            @endif
                            <text x="50%" y="{{ $b['top'] }}%" dy="-4" text-anchor="middle" class="fill-ink text-t3 font-semibold opacity-0 group-hover:opacity-100">{{ $b['v'] }}</text>
                        </svg>
                    </div>
                @endforeach
            </div>
            <svg class="pointer-events-none absolute inset-0 size-full overflow-visible" aria-hidden="true">
                <line x1="0" x2="100%" y1="{{ $chart['avgY'] }}%" y2="{{ $chart['avgY'] }}%" class="stroke-chart-1" stroke-width="2" stroke-dasharray="6 4" />
            </svg>
        </div>

        {{-- Подпись среднего: ширину задаёт невидимый текст, сама подпись — на высоте линии --}}
        <div class="relative shrink-0" aria-hidden="true">
            <span class="invisible whitespace-nowrap text-t3 font-semibold">{{ $chart['avgText'] }}</span>
            <svg class="absolute inset-0 h-44 w-full overflow-visible">
                <text x="0" y="{{ $chart['avgY'] }}%" dy="4" class="fill-ink text-t3 font-semibold">{{ $chart['avgText'] }}</text>
            </svg>
        </div>
    </div>

    {{-- Подписи дней --}}
    <div class="flex gap-3" aria-hidden="true">
        <span class="w-8 shrink-0"></span>
        <div class="flex min-w-0 flex-1 {{ $gap }}">
            @foreach ($chart['bars'] as $i => $b)
                <span class="relative h-4 min-w-0 flex-1" wire:key="x-{{ $i }}">
                    @if ($b['label'])
                        <span class="absolute left-1/2 top-0 -translate-x-1/2 whitespace-nowrap text-t3 text-muted">{{ $b['label'] }}</span>
                    @endif
                </span>
            @endforeach
        </div>
        <span class="invisible shrink-0 whitespace-nowrap text-t3 font-semibold">{{ $chart['avgText'] }}</span>
    </div>
</div>
