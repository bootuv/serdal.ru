@php
    $hour = now()->hour;
    $greeting = match (true) {
        $hour >= 5 && $hour < 12 => 'Доброе утро',
        $hour >= 12 && $hour < 18 => 'Добрый день',
        $hour >= 18 && $hour < 23 => 'Добрый вечер',
        default => 'Доброй ночи',
    };
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$greeting . ', ' . $firstName" :sub="$today" />

    {{-- Фокус-блок: следующее занятие --}}
    @if ($next)
        <x-ui.card focus class="lg:flex-row lg:items-center lg:justify-between lg:gap-6" aria-label="Следующее занятие">
            <div class="flex min-w-0 flex-col gap-4">
                <p class="text-t2 text-muted">
                    Следующее занятие@if ($next['until']) · <x-ui.em>{{ $next['until'] }}</x-ui.em>@endif
                </p>
                <div class="flex flex-col gap-1">
                    <h2 class="text-h2 font-medium lg:text-num">{{ $next['title'] }}</h2>
                    <p class="text-t1 text-muted">{{ $next['when'] }}</p>
                </div>
                @if ($next['teacher'])
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="$next['teacher']" :id="$next['teacherId']" />
                        <div class="flex flex-col gap-1">
                            <span class="text-t1-s font-medium">{{ $next['teacher'] }}</span>
                            <span class="text-t3 text-muted">Ваш учитель</span>
                        </div>
                    </div>
                @endif
            </div>
            <div class="flex shrink-0 flex-col gap-3 lg:items-end">
                @if ($next['blocked'])
                    <x-ui.btn variant="outline" size="l" icon="lock" disabled>Войти в класс</x-ui.btn>
                    <p class="text-t2 text-muted">Вход закрыт до оплаты · <a href="{{ url('/student/payment-debts') }}" class="link">оплатить</a></p>
                @else
                    <x-ui.btn variant="primary" size="l" icon="video" :href="$next['joinUrl']" target="_blank" rel="noopener">Войти в класс</x-ui.btn>
                @endif
            </div>
        </x-ui.card>
    @else
        <x-ui.card focus>
            <x-ui.empty icon="calendar" title="Занятий пока нет" text="Когда учитель запланирует занятие, оно появится здесь и в расписании." class="py-8" />
        </x-ui.card>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        {{-- Задания --}}
        <x-ui.card class="lg:col-span-2" aria-labelledby="hw">
            <x-ui.card-head id="hw" title="Задания">
                <x-slot:action><a href="{{ url('/student/homework') }}" class="link text-t2">Все задания</a></x-slot:action>
            </x-ui.card-head>
            @if ($homework->isEmpty())
                <p class="text-t2 text-muted">Пока пусто — новые задания от учителя появятся здесь.</p>
            @else
                <x-ui.list>
                    @foreach ($homework as $hw)
                        <x-ui.row :href="$hw['url']">
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1 font-medium">{{ $hw['title'] }}</span>
                                <span class="text-t2 text-muted">
                                    {{ $hw['teacher'] }}@if ($hw['state'] === 'todo' && $hw['deadline']) · <x-ui.em>сдать {{ $hw['deadline'] }}</x-ui.em>@endif
                                </span>
                            </div>
                            @if ($hw['state'] === 'revision')<x-ui.badge tone="danger">На доработке</x-ui.badge>
                            @elseif ($hw['state'] === 'overdue')<x-ui.badge tone="danger">Срок прошёл</x-ui.badge>
                            @elseif ($hw['state'] === 'review')<x-ui.badge>На проверке</x-ui.badge>
                            @endif
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
            @endif
        </x-ui.card>

        <div class="flex flex-col gap-6">
            {{-- Оплата: только если есть неоплаченное --}}
            @if ($payment)
                <x-ui.card aria-labelledby="pay">
                    <x-ui.card-head id="pay" title="К оплате" />
                    <div class="flex items-end justify-between gap-4">
                        <div class="flex flex-col gap-1">
                            <span class="text-num font-medium">{{ plural_ru($payment['count'], 'занятие', 'занятия', 'занятий') }}</span>
                            @if ($payment['due'])
                                <span class="text-t2 text-muted">@if ($payment['overdue'])<x-ui.em>срок прошёл {{ $payment['due'] }}</x-ui.em>@else оплатить до {{ $payment['due'] }}@endif</span>
                            @endif
                        </div>
                        <x-ui.btn variant="dark" :href="$payment['url']">Оплатить</x-ui.btn>
                    </div>
                </x-ui.card>
            @endif

            {{-- Ближайшие занятия --}}
            @if ($week->isNotEmpty())
                <x-ui.card aria-labelledby="week">
                    <x-ui.card-head id="week" title="Дальше">
                        <x-slot:action><a href="{{ url('/student/schedule-calendar') }}" class="link text-t2">Расписание</a></x-slot:action>
                    </x-ui.card-head>
                    <x-ui.list>
                        @foreach ($week as $lesson)
                            <x-ui.row :chevron="false">
                                <x-ui.date-tile :date="$lesson['start']" :today="$lesson['isToday']" />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1-s font-medium">{{ $lesson['start']?->format('H:i') }} · {{ $lesson['title'] }}</span>
                                    <span class="truncate text-t2 text-muted">{{ $lesson['teacher'] }}</span>
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                </x-ui.card>
            @endif

            {{-- Успеваемость --}}
            @if ($metrics)
                <x-ui.card aria-labelledby="perf">
                    <x-ui.card-head id="perf" title="Успеваемость" />
                    @if ($teachers->count() > 1)
                        <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Учитель">
                            @foreach ($teachers as $t)
                                <button type="button" wire:click="$set('perfTeacherId', {{ $t->id }})"
                                        @class(['h-9 flex-1 truncate rounded-sm px-3 text-t2 font-medium',
                                                'bg-white font-semibold text-ink shadow-seg' => $perfTeacherId === $t->id,
                                                'text-muted hover:text-ink' => $perfTeacherId !== $t->id])
                                        aria-pressed="{{ $perfTeacherId === $t->id ? 'true' : 'false' }}">{{ $t->name }}</button>
                            @endforeach
                        </div>
                    @endif
                    <x-ui.rings :metrics="$metrics" stacked />
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
