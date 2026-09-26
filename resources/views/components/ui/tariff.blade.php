{{-- Тариф учителя на виду: название, срок, остаток занятий и лимиты. Данные — SubscriptionService::teacherSummary().
     variant: side — компактная плашка в сайдбаре и в «Ещё» (ссылка на «Тариф и платежи»); card — карточка на «Сегодня».
     Исключение (занятия на исходе, тариф скоро закончится) — жирным красным. --}}
@props(['summary', 'variant' => 'side'])
@php
    $s = $summary;
    $hasLimit = ($s['limit'] ?? null) !== null;
@endphp
@if ($variant === 'side')
    <a href="{{ $s['url'] }}" {{ $attributes->class('flex flex-col gap-2 rounded-lg bg-soft p-3 hover:bg-soft-hover') }}>
        @if (! $s['name'])
            <span class="text-t2 font-medium">Тариф не выбран</span>
            <span class="text-t3 font-semibold text-danger-fg">{{ $s['warning'] }}</span>
        @else
            <span class="flex items-baseline justify-between gap-2">
                <span class="truncate text-t2 font-medium">Тариф «{{ $s['name'] }}»</span>
                <span class="shrink-0 text-t3 text-muted">{{ $s['until'] }}</span>
            </span>
            @if ($hasLimit)
                <x-ui.progress :value="$s['used']" :max="$s['limit']" label="Проведено занятий в этом периоде" on-mint />
            @endif
            @if ($s['warning'])
                <span class="text-t3 font-semibold text-danger-fg">{{ $s['warning'] }}</span>
            @elseif ($hasLimit)
                <span class="text-t3 text-muted">Осталось {{ $s['left'] }} из {{ $s['limit'] }} занятий@if ($s['extra']) · +{{ $s['extra'] }} докуплено@endif</span>
            @else
                <span class="text-t3 text-muted">Занятий без ограничений</span>
            @endif
        @endif
    </a>
@else
    <x-ui.card {{ $attributes }} aria-labelledby="tariff-title">
        <x-ui.card-head id="tariff-title" :title="$s['name'] ? 'Тариф «' . $s['name'] . '»' : 'Тариф не выбран'">
            <x-slot:action><a href="{{ $s['url'] }}" class="link text-t2">{{ $s['name'] ? 'Тариф и платежи' : 'Выбрать тариф' }}</a></x-slot:action>
        </x-ui.card-head>
        @if (! $s['name'])
            <p class="text-t2 font-semibold text-danger-fg">{{ $s['warning'] }}</p>
        @else
            <div class="flex flex-col gap-3">
                @if ($hasLimit)
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-num font-medium tabular-nums">{{ $s['left'] }} из {{ $s['limit'] }}</span>
                        <span class="shrink-0 text-t2 text-muted">{{ $s['until'] }}</span>
                    </div>
                    <x-ui.progress :value="$s['used']" :max="$s['limit']" label="Проведено занятий в этом периоде" />
                    <span class="text-t2 text-muted">{{ implode(' · ', array_filter(['занятий осталось', $s['resets'] ? 'обновится ' . $s['resets'] : null, $s['extra'] ? '+' . plural_ru($s['extra'], 'докупленное', 'докупленных', 'докупленных') : null])) }}</span>
                @else
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-t1 font-medium">Занятий без ограничений</span>
                        <span class="shrink-0 text-t2 text-muted">{{ $s['until'] }}</span>
                    </div>
                @endif
                @if ($s['warning'])<span class="text-t2 font-semibold text-danger-fg">{{ $s['warning'] }}</span>@endif
            </div>
            @if ($s['limits'])
                <span class="border-t border-line pt-3 text-t2 text-muted">{{ \Illuminate\Support\Str::ucfirst(implode(' · ', $s['limits'])) }}</span>
            @endif
        @endif
    </x-ui.card>
@endif
