{{-- Админка · Занятия · Расписание: список, неделя, месяц (макет AdminLessons). Цвет в календаре — по учителю. --}}
<div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
        <x-ui.seg fit :items="['list' => 'Список', 'week' => 'Неделя', 'month' => 'Месяц']" model="view" :active="$view" aria-label="Вид" />
        <div class="lg:w-sidebar">
            <x-ui.select name="teacher" :options="$teacherOptions" wire:model.live="teacher" aria-label="Учитель" />
        </div>
    </div>
    @if ($view === 'list')
        <x-ui.search wire:model.live.debounce.400ms="q" placeholder="Название, учитель или ученик" />
    @else
        <div class="flex items-center gap-2">
            <x-ui.btn size="s" square wire:click="shift(-1)" :aria-label="$view === 'week' ? 'Предыдущая неделя' : 'Предыдущий месяц'"><x-ui.icon name="chevron-right" size="s" class="rotate-180" /></x-ui.btn>
            <h2 class="min-w-0 flex-1 text-center text-h2 font-medium lg:w-44 lg:flex-none">{{ $calTitle }}</h2>
            <x-ui.btn size="s" square wire:click="shift(1)" :aria-label="$view === 'week' ? 'Следующая неделя' : 'Следующий месяц'"><x-ui.icon name="chevron-right" size="s" /></x-ui.btn>
            @unless ($isCurrent)<x-ui.btn size="s" wire:click="goToday">Сегодня</x-ui.btn>@endunless
        </div>
    @endif
</div>

@if ($view === 'list')
    <x-ui.card class="gap-6" aria-label="Список занятий">
        <x-ui.seg fit :items="$filters" model="filter" :active="$filter" aria-label="Какие занятия показать" />

        @if ($rows->isEmpty())
            <p class="text-t2 text-muted">Таких занятий нет. <button type="button" wire:click="resetFilters" class="link">Показать все</button></p>
        @else
            <div class="flex flex-col">
                <div class="hidden gap-4 pb-3 text-t3 font-medium text-muted lg:grid lg:grid-cols-12">
                    <span class="col-span-3">Занятие</span><span class="col-span-2">Учитель</span><span class="col-span-2">Ученики</span><span class="col-span-3">Ближайшее</span><span class="col-span-2"></span>
                </div>
                @foreach ($rows as $row)
                    <div class="group relative flex flex-col gap-2 border-t border-line py-4 last:pb-0 lg:grid lg:grid-cols-12 lg:items-center lg:gap-4" wire:key="room-{{ $row['id'] }}">
                        <a href="{{ $row['url'] }}" class="flex min-w-0 flex-col gap-1 after:absolute after:inset-0 lg:col-span-3">
                            <span class="truncate text-t1 font-medium group-hover:underline">{{ $row['title'] }}</span>
                            <span class="truncate text-t2 text-muted">{{ $row['rule'] }}</span>
                        </a>
                        <span class="truncate text-t2 text-muted lg:col-span-2 lg:text-t1-s lg:text-ink">{{ $row['teacher'] }}</span>
                        <div class="hidden min-w-0 items-center gap-2 lg:col-span-2 lg:flex">
                            @if ($row['count'] === 1)
                                <x-ui.avatar :user="$row['participants'][0]" />
                                <span class="truncate text-t1-s">{{ $row['participants'][0]->name }}</span>
                            @elseif ($row['count'] > 1)
                                <span class="flex shrink-0 -space-x-2">
                                    @foreach ($row['participants']->take(2) as $p)<x-ui.avatar :user="$p" class="shadow-dot-ring" />@endforeach
                                </span>
                                <span class="whitespace-nowrap text-t2 text-muted">{{ plural_ru($row['count'], 'ученик', 'ученика', 'учеников') }}</span>
                            @else
                                <span class="text-t2 text-muted">Нет учеников</span>
                            @endif
                        </div>
                        <div class="flex min-w-0 flex-col gap-1 lg:col-span-3">
                            @if ($row['state'] === 'live')
                                <span class="text-t1-s font-semibold">Идёт сейчас</span>
                                <span class="truncate text-t2 text-muted">{{ $row['liveNote'] }}</span>
                            @elseif ($row['state'] === 'plan')
                                <span class="text-t1-s">{{ $row['when'] }}</span>
                                @if ($row['whenNote'])<span class="truncate text-t2 text-muted">{{ $row['whenNote'] }}</span>@endif
                            @elseif ($row['state'] === 'none')
                                <span class="text-t1-s text-muted">Нет расписания</span>
                                <span class="truncate text-t2 text-muted">{{ $row['whenNote'] }}</span>
                            @else
                                <span><x-ui.badge>В архиве</x-ui.badge></span>
                                @if ($row['whenNote'])<span class="truncate text-t2 text-muted">{{ $row['whenNote'] }}</span>@endif
                            @endif
                        </div>
                        <div class="relative z-10 flex lg:col-span-2 lg:justify-end">
                            @if ($row['joinUrl'])
                                <x-ui.btn size="s" :href="$row['joinUrl']" target="_blank" rel="noopener">Подключиться</x-ui.btn>
                            @else
                                <x-ui.icon name="chevron-right" class="pointer-events-none hidden text-faint group-hover:text-ink lg:block" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($hasMore)
                <x-ui.btn size="s" wire:click="showMore" class="self-center">Показать ещё</x-ui.btn>
            @endif
        @endif
    </x-ui.card>
@elseif ($view === 'week')
    {{-- Телефон: дни списком --}}
    <section class="overflow-hidden rounded-xl shadow-outline lg:hidden" aria-label="Неделя {{ $calTitle }}">
        @foreach ($weekDays as $day)
            <div @class(['min-w-0 flex-col gap-2 border-t border-line p-3 first:border-t-0', 'flex' => $day['events']->isNotEmpty(), 'hidden' => $day['events']->isEmpty()]) wire:key="awm-{{ $day['date']->format('Ymd') }}">
                <div class="flex items-center gap-2">
                    <span class="text-t3 text-muted">{{ $day['short'] }}</span>
                    <span @class(['flex size-8 items-center justify-center rounded-full text-t2 font-semibold', 'bg-ink text-white' => $day['isToday']])>{{ $day['date']->day }}</span>
                </div>
                @foreach ($day['events'] as $ev)
                    <a href="{{ $ev['url'] }}" class="flex flex-col gap-1 rounded-sm p-2 hover:shadow-card {{ $ev['tone'] }} {{ $ev['past'] ? 'opacity-60' : '' }}" wire:key="aevm-{{ $ev['key'] }}">
                        <span class="truncate text-t3 font-semibold">{{ $ev['title'] }}</span>
                        <span class="truncate text-count text-muted">{{ $ev['teacher'] }}{{ $ev['note'] ? ' · ' . $ev['note'] : '' }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </section>

    {{-- Компьютер: сетка по часам, линия «Сейчас» --}}
    <section class="hidden overflow-hidden rounded-xl shadow-outline lg:block" aria-label="Неделя {{ $calTitle }}">
        <div class="flex border-b border-line">
            <div class="w-16 shrink-0"></div>
            <div class="grid flex-1 grid-cols-7">
                @foreach ($weekDays as $day)
                    <div @class(['flex h-16 items-center gap-2 border-l border-line px-3', 'bg-mint' => $day['isToday']]) wire:key="awh-{{ $day['date']->format('Ymd') }}">
                        <span @class(['text-t2', 'font-semibold text-ink' => $day['isToday'], 'text-muted' => ! $day['isToday']])>{{ $day['short'] }}</span>
                        @if ($day['isToday'])
                            <span class="flex size-9 items-center justify-center rounded-full bg-ink text-t1 font-semibold text-white">{{ $day['date']->day }}</span>
                        @else
                            <span class="text-h2 font-medium">{{ $day['date']->day }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        <div class="flex">
            <div class="w-16 shrink-0" aria-hidden="true">
                @foreach ($hours as $h)
                    <div class="h-16 border-t border-line px-2 pt-1 text-right text-t3 text-muted first:border-t-0">{{ sprintf('%02d:00', $h) }}</div>
                @endforeach
            </div>
            <div class="grid flex-1 grid-cols-7">
                @foreach ($weekDays as $day)
                    <div @class(['min-w-0 border-l border-line', 'bg-mint/60' => $day['isToday']]) aria-label="{{ $day['short'] }}, {{ \App\Support\HumanDate::date($day['date']) }}" wire:key="awd-{{ $day['date']->format('Ymd') }}">
                        @foreach ($hours as $h)
                            <div class="relative h-16 border-t border-line first:border-t-0">
                                @if ($nowLine && $day['isToday'] && $nowLine['hour'] === $h)
                                    <div class="pointer-events-none absolute inset-x-0 z-20 border-t-2 border-ink {{ $nowLine['top'] }}" role="img" aria-label="{{ $nowLine['label'] }}" title="{{ $nowLine['label'] }}">
                                        <span class="absolute -left-1 -top-1 size-2 rounded-full bg-ink"></span>
                                    </div>
                                @endif
                                @foreach ($day['events']->where('hour', $h) as $ev)
                                    <a href="{{ $ev['url'] }}" class="absolute z-10 flex overflow-hidden rounded-sm hover:shadow-card {{ $ev['top'] }} {{ $ev['lane'] }} {{ $ev['tone'] }} {{ $ev['past'] ? 'opacity-60' : '' }}" wire:key="aev-{{ $ev['key'] }}">
                                        {{-- Высота — длительность занятия: по 16 px на четверть часа --}}
                                        <span class="flex flex-col" aria-hidden="true">@for ($i = 0; $i < $ev['quarters']; $i++)<span class="h-4"></span>@endfor</span>
                                        <span class="absolute inset-0 flex min-w-0 flex-col px-2 py-1">
                                            <span class="truncate text-t3 font-semibold">{{ $ev['title'] }}</span>
                                            @if ($ev['quarters'] > 1)<span class="truncate text-count text-muted">{{ $ev['teacher'] }}</span>@endif
                                            @if ($ev['note'] && $ev['quarters'] > 2)<span @class(['truncate text-count', 'font-semibold text-ink' => $ev['running'], 'text-muted' => ! $ev['running']])>{{ $ev['note'] }}</span>@endif
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@else
    <section class="overflow-hidden rounded-xl shadow-outline" aria-label="{{ $calTitle }}">
        <div class="grid grid-cols-7 border-b border-line">
            @foreach (['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'] as $wd)
                <span class="flex h-11 items-center px-2 text-t2 font-medium text-muted lg:px-3">{{ $wd }}</span>
            @endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($cells as $cell)
                <div @class(['flex min-h-16 min-w-0 flex-col gap-1 border-line p-1 lg:min-h-40 lg:p-2',
                             'border-l' => $loop->index % 7 !== 0, 'border-t' => $loop->index >= 7, 'bg-mint' => $cell['isToday']])
                     aria-label="{{ \App\Support\HumanDate::date($cell['date']) }}" wire:key="amc-{{ $cell['date']->format('Ymd') }}">
                    <span @class(['flex size-8 items-center justify-center rounded-full text-t2 font-semibold',
                                  'bg-ink text-white' => $cell['isToday'], 'text-faint' => ! $cell['inMonth'] && ! $cell['isToday']])>{{ $cell['date']->day }}</span>
                    @foreach ($cell['events']->take(3) as $ev)
                        <a href="{{ $ev['url'] }}" title="{{ $ev['short'] }}"
                           class="hidden truncate rounded-sm px-2 py-1 text-count font-medium hover:shadow-card lg:block {{ $ev['tone'] }} {{ $ev['past'] ? 'opacity-60' : '' }}" wire:key="ame-{{ $ev['key'] }}">{{ $ev['short'] }}</a>
                    @endforeach
                    @if ($cell['events']->count() > 3)
                        <span class="hidden px-2 text-count text-muted lg:block">ещё {{ $cell['events']->count() - 3 }}</span>
                    @endif
                    @if ($cell['events']->isNotEmpty())
                        <span class="flex gap-1 px-1 lg:hidden" aria-label="{{ plural_ru($cell['events']->where('cancelled', false)->count(), 'занятие', 'занятия', 'занятий') }}">
                            @foreach ($cell['events']->take(3) as $ev)<span @class(['size-2 rounded-full', 'bg-ink' => ! $ev['cancelled'], 'bg-line-strong' => $ev['cancelled'], 'opacity-60' => $ev['past']])></span>@endforeach
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

@if ($view !== 'list' && $legend)
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2" aria-label="Цвета занятий">
        @foreach ($legend as $item)
            <span class="inline-flex items-center gap-2 text-t2 text-muted"><span class="size-3 shrink-0 rounded-sm {{ $item['swatch'] }}"></span>{{ $item['label'] }}</span>
        @endforeach
    </div>
@endif
