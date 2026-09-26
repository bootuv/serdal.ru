<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Расписание" :sub="$sub">
        <x-slot:actions>
            @if ($googleConnected)
                <x-ui.btn icon="calendar" wire:click="$set('confirmGoogleDisconnect', true)" class="hidden lg:inline-flex">Отключить Google Календарь</x-ui.btn>
                <x-ui.btn square icon="calendar" wire:click="$set('confirmGoogleDisconnect', true)" class="lg:hidden" aria-label="Отключить Google Календарь" />
            @else
                <x-ui.btn icon="calendar" :href="route('google.calendar.connect')" class="hidden lg:inline-flex">Добавить в Google Календарь</x-ui.btn>
                <x-ui.btn square icon="calendar" :href="route('google.calendar.connect')" class="lg:hidden" aria-label="Добавить в Google Календарь" />
            @endif
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <div class="relative flex flex-col gap-4">
            <x-ui.tabs :items="['upcoming' => 'Предстоящие', 'past' => 'Прошедшие']" model="tab" :active="$tab" aria-label="Какие занятия показать" />
            @if ($teacherFilter)
                <x-ui.seg :items="$teacherFilter" model="teacher" :active="$teacher" aria-label="Учитель" fit class="lg:absolute lg:bottom-2 lg:right-0" />
            @endif
        </div>

        @if ($tab === 'upcoming')
            {{-- Фокус-блок: идущее или ближайшее занятие --}}
            @if ($focus)
                <x-ui.card focus class="lg:flex-row lg:items-center lg:gap-6" aria-label="Ближайшее занятие">
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <x-ui.date-tile :date="$focus['start']" :today="$focus['isToday']" />
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="text-t1 font-medium">{{ $focus['title'] }}</span>
                            <span class="text-t2 text-muted">
                                @if ($focus['status'])<x-ui.em>{{ $focus['status'] }}</x-ui.em> · @endif
                                @if ($focus['extra'])<x-ui.em>Дополнительное</x-ui.em> · @endif
                                {{ $focus['facts'] }}
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 lg:items-end">
                        @if ($focus['blocked'])
                            <x-ui.btn variant="outline" size="l" icon="lock" disabled>Войти в класс</x-ui.btn>
                            <p class="text-t2 text-muted">Вход закрыт до оплаты · <a href="{{ $paymentsUrl }}" class="link">оплатить</a></p>
                        @else
                            <x-ui.btn variant="primary" size="l" icon="video" :href="$focus['joinUrl']" target="_blank" rel="noopener">Войти в класс</x-ui.btn>
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card aria-labelledby="sc-next">
                    <x-ui.card-head id="sc-next" title="Следующие занятия" />
                    @if ($next->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — других занятий в ближайшие две недели нет.</p>
                    @else
                        <x-ui.list>
                            @foreach ($next as $lesson)
                                <x-ui.row :href="$lesson['url']" wire:key="next-{{ $loop->index }}">
                                    <x-ui.date-tile :date="$lesson['start']" :today="$lesson['isToday']" />
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1 font-medium">{{ $lesson['title'] }}</span>
                                        <span class="text-t2 text-muted">
                                            @if ($lesson['running'])<x-ui.em>Идёт сейчас</x-ui.em> · @endif
                                            @if ($lesson['extra'])<x-ui.em>Дополнительное</x-ui.em> · @endif
                                            {{ $lesson['facts'] }}
                                        </span>
                                    </div>
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>
            @else
                <x-ui.card focus>
                    <x-ui.empty icon="calendar" title="Ближайших занятий нет" text="Когда учитель запланирует занятие, оно появится здесь." class="py-8" />
                </x-ui.card>
            @endif
        @else
            <x-ui.card aria-label="Прошедшие занятия">
                @if ($past->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — здесь появятся занятия, которые уже прошли.</p>
                @else
                    <x-ui.list>
                        @foreach ($past as $lesson)
                            <x-ui.row class="first:border-t-0 first:pt-0" wire:key="past-{{ $loop->index }}">
                                <x-ui.date-tile :date="$lesson['start']" />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span @class(['truncate text-t1 font-medium', 'text-muted' => ! $lesson['attended']])>{{ $lesson['title'] }}</span>
                                    <span class="text-t2 text-muted">{{ $lesson['facts'] }}</span>
                                </div>
                                @if ($lesson['unpaid'])<x-ui.badge tone="danger">Не оплачено</x-ui.badge>@endif
                                @if ($lesson['recordingUrl'])
                                    <a href="{{ $lesson['recordingUrl'] }}" class="link shrink-0 text-t2">Смотреть запись</a>
                                @endif
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
                @if ($hasEarlier)
                    <x-ui.btn size="s" wire:click="showEarlier" class="self-start">Показать раньше</x-ui.btn>
                @endif
            </x-ui.card>
        @endif
    </div>

    @if ($confirmGoogleDisconnect)
        <x-ui.modal title="Отключить Google Календарь?" sub="Новые занятия перестанут появляться в вашем календаре." close="$set('confirmGoogleDisconnect', false)" width="s">
            <p class="text-t2 text-muted">Подключить снова можно в любой момент на этой странице.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('confirmGoogleDisconnect', false)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" :href="route('google.calendar.disconnect')">Отключить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
