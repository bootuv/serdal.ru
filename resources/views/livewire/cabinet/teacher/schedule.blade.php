<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Расписание" :sub="$sub">
        <x-slot:actions>
            <x-ui.btn variant="dark" icon="plus" wire:click="openPlan" class="hidden lg:inline-flex">Запланировать занятие</x-ui.btn>
            <x-ui.btn variant="dark" square icon="plus" wire:click="openPlan" class="lg:hidden" aria-label="Запланировать занятие" />
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        {{-- Панель: вкладки (список) или навигация по неделе/месяцу; справа — фильтр и вид --}}
        @if ($view === 'list')
            <div class="relative flex flex-col gap-4">
                <x-ui.tabs :items="['upcoming' => 'Предстоящие', 'past' => 'Прошедшие']" model="tab" :active="$tab" aria-label="Какие занятия показать" />
                <div class="lg:absolute lg:bottom-2 lg:right-0">@include('livewire.cabinet.teacher.partials.schedule-tools')</div>
            </div>
        @else
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2">
                    <x-ui.btn size="s" square wire:click="shift(-1)" :aria-label="$view === 'week' ? 'Предыдущая неделя' : 'Предыдущий месяц'"><x-ui.icon name="chevron-right" size="s" class="rotate-180" /></x-ui.btn>
                    <h2 class="min-w-0 flex-1 text-center text-h2 font-medium lg:w-sidebar lg:flex-none">{{ $calTitle }}</h2>
                    <x-ui.btn size="s" square wire:click="shift(1)" :aria-label="$view === 'week' ? 'Следующая неделя' : 'Следующий месяц'"><x-ui.icon name="chevron-right" size="s" /></x-ui.btn>
                    @unless ($isCurrent)<x-ui.btn size="s" wire:click="goToday">Сегодня</x-ui.btn>@endunless
                </div>
                @include('livewire.cabinet.teacher.partials.schedule-tools')
            </div>
        @endif

        @if ($view === 'week')
            <section class="overflow-hidden rounded-xl shadow-outline" aria-label="Неделя {{ $calTitle }}">
                <div class="grid grid-cols-1 lg:grid-cols-7">
                    @foreach ($weekDays as $day)
                        <div @class(['min-w-0 flex-col gap-2 border-line p-3 lg:min-h-40',
                                     'border-t first:border-t-0 lg:border-l lg:border-t-0 lg:first:border-l-0',
                                     'flex' => $day['events']->isNotEmpty(), 'hidden lg:flex' => $day['events']->isEmpty()])
                             aria-label="{{ $day['short'] }}, {{ \App\Support\HumanDate::date($day['date']) }}" wire:key="wd-{{ $day['date']->format('Ymd') }}">
                            <div class="flex items-center gap-2">
                                <span class="text-t3 text-muted">{{ $day['short'] }}</span>
                                <span @class(['flex size-8 items-center justify-center rounded-full text-t2 font-semibold', 'bg-ink text-white' => $day['isToday']])>{{ $day['date']->day }}</span>
                            </div>
                            @foreach ($day['events'] as $ev)
                                <a href="{{ $ev['url'] }}" @class(['flex flex-col gap-1 rounded-sm bg-soft p-2 hover:shadow-outline', 'opacity-60' => $ev['past']]) wire:key="ev-{{ $ev['key'] }}">
                                    <span class="truncate text-t3 font-semibold">{{ $ev['time'] }} · {{ $ev['title'] }}</span>
                                    <span class="truncate text-t3 text-muted">@if ($ev['running'])<x-ui.em>идёт сейчас</x-ui.em> · @endif{{ $ev['sub'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>
        @elseif ($view === 'month')
            <section class="overflow-hidden rounded-xl shadow-outline" aria-label="{{ $calTitle }}">
                <div class="grid grid-cols-7 border-b border-line">
                    @foreach (['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'] as $wd)
                        <span class="flex h-11 items-center px-2 text-t2 font-medium text-muted lg:px-3">{{ $wd }}</span>
                    @endforeach
                </div>
                <div class="grid grid-cols-7">
                    @foreach ($cells as $cell)
                        <div @class(['flex min-h-16 min-w-0 flex-col gap-1 border-line p-1 lg:min-h-40 lg:p-2',
                                     'border-l' => $loop->index % 7 !== 0, 'border-t' => $loop->index >= 7])
                             aria-label="{{ \App\Support\HumanDate::date($cell['date']) }}" wire:key="mc-{{ $cell['date']->format('Ymd') }}">
                            <span @class(['flex size-8 items-center justify-center rounded-full text-t2 font-semibold',
                                          'bg-ink text-white' => $cell['isToday'], 'text-faint' => ! $cell['inMonth'] && ! $cell['isToday']])>{{ $cell['date']->day }}</span>
                            @foreach ($cell['events']->take(3) as $ev)
                                <a href="{{ $ev['url'] }}" @class(['hidden truncate rounded-sm bg-soft px-2 py-1 text-count font-medium hover:shadow-outline lg:block', 'opacity-60' => $ev['past']]) wire:key="me-{{ $ev['key'] }}">{{ $ev['time'] }} {{ $ev['title'] }}</a>
                            @endforeach
                            @if ($cell['events']->count() > 3)
                                <span class="hidden px-2 text-count text-muted lg:block">ещё {{ $cell['events']->count() - 3 }}</span>
                            @endif
                            @if ($cell['events']->isNotEmpty())
                                <span class="flex gap-1 px-1 lg:hidden" aria-label="{{ plural_ru($cell['events']->count(), 'занятие', 'занятия', 'занятий') }}">
                                    @foreach ($cell['events']->take(3) as $ev)<span @class(['size-2 rounded-full bg-ink', 'opacity-60' => $ev['past']])></span>@endforeach
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @elseif ($tab === 'upcoming')
            @forelse ($days as $day)
                <div class="flex flex-col gap-2 lg:flex-row lg:gap-6" wire:key="day-{{ $day['date'] }}">
                    <div class="flex items-baseline gap-2 lg:w-40 lg:shrink-0 lg:flex-col lg:gap-1 lg:pt-6">
                        <span class="text-t1 font-semibold">{{ $day['title'] }}</span>
                        <span class="text-t2 text-muted">{{ $day['sub'] }}</span>
                    </div>
                    <x-ui.card :focus="$day['date'] === $focusDate" class="min-w-0 flex-1" :aria-label="$day['title']">
                        <x-ui.list>
                            @foreach ($day['rows'] as $row)
                                @include('livewire.cabinet.teacher.partials.lesson-row', ['row' => $row, 'bare' => true, 'afterFocus' => $loop->index > 0 && $day['rows'][$loop->index - 1]['focus']])
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                </div>
            @empty
                <x-ui.card>
                    <x-ui.empty icon="calendar" title="Ближайших занятий нет" text="Запланируйте занятие — ученики получат уведомление, а занятие появится здесь." class="py-8" />
                </x-ui.card>
            @endforelse
        @else
            @forelse ($pastDays as $day)
                <div class="flex flex-col gap-2 lg:flex-row lg:gap-6" wire:key="pday-{{ $day['date'] }}">
                    <div class="flex items-baseline gap-2 lg:w-40 lg:shrink-0 lg:flex-col lg:gap-1 lg:pt-6">
                        <span class="text-t1 font-semibold">{{ $day['title'] }}</span>
                        <span class="text-t2 text-muted">{{ $day['sub'] }}</span>
                    </div>
                    <x-ui.card class="min-w-0 flex-1" :aria-label="$day['title']">
                        <x-ui.list>
                            @foreach ($day['rows'] as $row)
                                <div class="flex items-center gap-4 border-t border-line py-4 first:border-t-0 first:pt-0 last:pb-0" wire:key="past-{{ $row['key'] }}">
                                    <div class="flex w-16 shrink-0 flex-col gap-1">
                                        <span @class(['text-t1 font-medium', 'text-muted' => $row['dim']])>{{ $row['time'] }}</span>
                                        <span class="text-t3 text-muted">{{ $row['duration'] }}</span>
                                    </div>
                                    <x-ui.avatar :name="$row['avatar']['name'] ?? ''" :id="$row['avatar']['id'] ?? 0" :group="$row['avatar']['group'] ?? false" class="hidden lg:inline-flex" />
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <a href="{{ $row['url'] }}" @class(['truncate text-t1 font-medium hover:underline', 'text-muted' => $row['dim']])>{{ $row['heading'] }}</a>
                                        @if ($row['facts'])<span class="text-t2 text-muted">{{ $row['facts'] }}</span>@endif
                                    </div>
                                    @if ($row['unpaid'])<x-ui.badge tone="danger">Не оплачено</x-ui.badge>@endif
                                    @if ($row['recordingUrl'])<a href="{{ $row['recordingUrl'] }}" class="link hidden shrink-0 text-t2 lg:inline">Смотреть запись</a>@endif
                                </div>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                </div>
            @empty
                <x-ui.card>
                    <p class="text-t2 text-muted">Пока пусто — здесь появятся занятия, которые уже прошли.</p>
                </x-ui.card>
            @endforelse
            @if ($hasEarlier)
                <x-ui.btn size="s" wire:click="showEarlier" class="self-start">Показать раньше</x-ui.btn>
            @endif
        @endif
    </div>

    @include('livewire.cabinet.teacher.partials.lesson-plan-modal')
    @include('livewire.cabinet.teacher.partials.lesson-start-blocked')
</div>
