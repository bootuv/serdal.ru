{{-- Занятие ученика. Данные — App\Livewire\Cabinet\Student\Lesson. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    @if ($poll)<div wire:poll.60s.visible class="hidden"></div>@endif

    <x-ui.page-head :title="$room->name" :sub="$sub" :back="$backUrl" backLabel="Расписание">
        <x-slot:actions>
            <div class="hidden items-center gap-2 lg:flex">
                <x-ui.btn size="l" icon="chat" :href="$chatUrl">Написать учителю</x-ui.btn>
                @include('livewire.cabinet.student.partials.lesson-join', ['size' => 'l'])
            </div>
        </x-slot:actions>
    </x-ui.page-head>

    {{-- Действия на телефоне --}}
    <div class="flex flex-wrap items-center gap-2 lg:hidden">
        @include('livewire.cabinet.student.partials.lesson-join', ['size' => 'm'])
        <x-ui.btn icon="chat" :href="$chatUrl">Написать учителю</x-ui.btn>
    </div>

    @if ($blocked && ! $archived)
        <p class="text-t2 text-muted">Вход закрыт до оплаты · <a href="{{ $paymentsUrl }}" class="link">сообщить об оплате</a></p>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            {{-- Фокус-блок: задания этого занятия --}}
            <x-ui.card focus aria-labelledby="sl-tasks">
                <x-ui.card-head id="sl-tasks" title="Задания">
                    <x-slot:action><a href="{{ $tasksUrl }}" class="link text-t2">Все задания</a></x-slot:action>
                </x-ui.card-head>
                @if ($tasks->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — задания к этому занятию появятся здесь.</p>
                @else
                    <x-ui.list>
                        @foreach ($tasks as $item)
                            @include('livewire.cabinet.student.partials.task-row', ['item' => $item])
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>

            <x-ui.card aria-labelledby="sl-past">
                <x-ui.card-head id="sl-past" title="Прошедшие занятия" />
                @if ($past->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — здесь появятся занятия, которые уже прошли.</p>
                @else
                    <x-ui.list>
                        @foreach ($past as $p)
                            <x-ui.row wire:key="past-{{ $p['key'] }}">
                                <x-ui.date-tile :date="$p['at']" :class="$p['muted'] ? 'opacity-60' : ''" />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span @class(['truncate text-t1 font-medium', 'text-muted' => $p['muted'], 'line-through' => $p['cancelled']])>{{ $p['title'] }}</span>
                                    <span class="text-t2 text-muted">{{ $p['sub'] }}</span>
                                </div>
                                @if ($p['unpaid'])<x-ui.badge tone="danger">Не оплачено</x-ui.badge>@endif
                                @if ($p['recordingUrl'])
                                    <a href="{{ $p['recordingUrl'] }}" class="link shrink-0 text-t2">Смотреть запись</a>
                                @endif
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
                @if ($hasEarlier)
                    <x-ui.btn size="s" wire:click="showEarlier" class="self-start">Показать раньше</x-ui.btn>
                @endif
            </x-ui.card>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card aria-labelledby="sl-rules">
                <x-ui.card-head id="sl-rules" title="Расписание" />
                @if ($archived)
                    <p class="text-t2 text-muted">Занятие в архиве — новых занятий не будет.</p>
                @elseif ($rules->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — учитель ещё не назначил время.</p>
                @else
                    <x-ui.list>
                        @foreach ($rules as $rule)
                            <x-ui.row wire:key="rule-{{ $rule['key'] }}">
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span @class(['truncate text-t1-s font-medium', 'text-muted line-through' => $rule['cancelled']])>{{ $rule['title'] }}</span>
                                    <span class="text-t2 text-muted">{{ $rule['sub'] }}</span>
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>

            <x-ui.card aria-labelledby="sl-mat">
                <x-ui.card-head id="sl-mat" title="Материалы">
                    <x-slot:action><a href="{{ $materialsUrl }}" class="link text-t2">Все материалы</a></x-slot:action>
                </x-ui.card-head>
                @if ($materials->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — материалы к занятию от учителя появятся здесь.</p>
                @else
                    <x-ui.list>
                        @foreach ($materials as $item)
                            @include('livewire.cabinet.student.partials.material-row', ['item' => $item])
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>

            <x-ui.card aria-labelledby="sl-rec">
                <x-ui.card-head id="sl-rec" title="Записи">
                    @if ($recordings->isNotEmpty())
                        <x-slot:action><a href="{{ route('cabinet.student.recordings') }}" class="link text-t2">Все записи</a></x-slot:action>
                    @endif
                </x-ui.card-head>
                @if ($recordings->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — записи появятся здесь после занятий.</p>
                @else
                    <x-ui.list>
                        @foreach ($recordings as $r)
                            @if ($r['url'])
                                <x-ui.row :href="$r['url']" :target="$r['external'] ? '_blank' : null" :rel="$r['external'] ? 'noopener' : null" wire:key="rec-{{ $r['id'] }}">
                                    <x-ui.file-tile icon="play" />
                                    <x-ui.text :title="$r['title']" :sub="$r['sub']" />
                                    @if ($r['status'] === 'uploading')<x-ui.badge>Загружается</x-ui.badge>@endif
                                </x-ui.row>
                            @else
                                <x-ui.row wire:key="rec-{{ $r['id'] }}">
                                    <x-ui.file-tile icon="clock" />
                                    <x-ui.text :title="$r['title']" :sub="$r['sub']" />
                                    <x-ui.badge>Обрабатывается</x-ui.badge>
                                </x-ui.row>
                            @endif
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
