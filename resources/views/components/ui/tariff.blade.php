{{-- Карточка тарифа учителя на «Сегодня»: название, срок, остаток занятий и лимиты. Данные — SubscriptionService::teacherSummary().
     Исключение (занятия на исходе, тариф скоро закончится) — жирным красным. --}}
@props(['summary'])
@php
    $s = $summary;
    $hasLimit = ($s['limit'] ?? null) !== null;
@endphp
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
