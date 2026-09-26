@php
    $canStart = ! $archived && ! $otherRunning;
    $copyGuest = isset($guestUrl)
        ? 'navigator.clipboard.writeText(' . \Illuminate\Support\Js::from($guestUrl) . "); \$dispatch('toast', { message: 'Ссылка скопирована — отправьте её гостю в любом мессенджере' })"
        : '';
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$room->name" :sub="$sub" :back="$backUrl" backLabel="Расписание">
        <x-slot:actions>
            <div class="hidden items-center gap-2 lg:flex">
                @if ($mode === 'report')
                    <x-ui.btn size="l" :href="$recordingsUrl">Все записи</x-ui.btn>
                    <x-ui.btn variant="primary" size="l" icon="plus" :href="$taskUrl">Выдать задание</x-ui.btn>
                @elseif ($mode === 'live')
                    <x-ui.btn size="l" wire:click="askStop">Завершить занятие</x-ui.btn>
                    <x-ui.btn variant="primary" size="l" icon="video" :href="$joinUrl" target="_blank" rel="noopener">Вернуться в класс</x-ui.btn>
                @elseif (! $archived)
                    {{-- Другие действия (временное меню: в ui/ пока нет компонента выпадающего меню) --}}
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <x-ui.btn size="l" square icon="more" x-on:click="open = ! open" aria-label="Другие действия" x-bind:aria-expanded="open" />
                        <div x-show="open" x-cloak class="absolute right-0 top-full z-10 mt-2 flex w-sidebar flex-col rounded-lg bg-white p-2 shadow-modal">
                            <button type="button" wire:click="openCancel" x-on:click="open = false" class="flex h-11 items-center rounded px-3 text-left text-t1-s font-medium hover:bg-soft">Отменить занятие</button>
                            <a href="{{ $editUrl }}" class="flex h-11 items-center rounded px-3 text-t1-s font-medium hover:bg-soft">Ученики и название</a>
                        </div>
                    </div>
                    <x-ui.btn size="l" wire:click="openReschedule">{{ $next ? 'Перенести' : 'Назначить время' }}</x-ui.btn>
                    @if ($canStart)
                        @if ($startBlock)
                            <x-ui.btn variant="primary" size="l" icon="play" wire:click="$set('startBlockedOpen', true)">Начать занятие</x-ui.btn>
                        @else
                            <x-ui.btn variant="primary" size="l" icon="play" :href="route('rooms.start', $room)" target="_blank" rel="noopener">Начать занятие</x-ui.btn>
                        @endif
                    @endif
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-head>

    {{-- Действия на телефоне --}}
    <div class="flex flex-wrap gap-2 lg:hidden">
        @if ($mode === 'report')
            <x-ui.btn variant="primary" icon="plus" :href="$taskUrl">Выдать задание</x-ui.btn>
            <x-ui.btn :href="$recordingsUrl">Все записи</x-ui.btn>
        @elseif ($mode === 'live')
            <x-ui.btn variant="primary" icon="video" :href="$joinUrl" target="_blank" rel="noopener">Вернуться в класс</x-ui.btn>
            <x-ui.btn wire:click="askStop">Завершить занятие</x-ui.btn>
        @elseif (! $archived)
            @if ($canStart)
                @if ($startBlock)
                    <x-ui.btn variant="primary" icon="play" wire:click="$set('startBlockedOpen', true)">Начать занятие</x-ui.btn>
                @else
                    <x-ui.btn variant="primary" icon="play" :href="route('rooms.start', $room)" target="_blank" rel="noopener">Начать занятие</x-ui.btn>
                @endif
            @endif
            <x-ui.btn wire:click="openReschedule">{{ $next ? 'Перенести' : 'Назначить время' }}</x-ui.btn>
            <x-ui.btn wire:click="openCancel">Отменить</x-ui.btn>
        @endif
    </div>

    @if ($mode === 'report')
        {{-- Отчёт о проведённом занятии (макеты LsEnded, LsDeleteRequest) --}}
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                @if ($deletion)
                    <x-ui.card aria-label="Запрос на удаление">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-t1 font-medium">Вы попросили удалить это занятие</span>
                            <x-ui.badge>На рассмотрении</x-ui.badge>
                        </div>
                        <p class="text-t2 text-muted">{{ \Illuminate\Support\Str::ucfirst($deletion['at']) }} · Причина: {{ $deletion['reason'] }}</p>
                        <p class="text-t2 text-muted">Решение придёт в уведомлениях. Пока запрос рассматривают, его можно отозвать.</p>
                    </x-ui.card>
                @endif

                <x-ui.card focus aria-labelledby="en-sum">
                    <x-ui.card-head id="en-sum" title="Итог занятия" />
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        @foreach ($stats as $stat)
                            <div class="flex flex-col gap-1">
                                <span class="text-num font-medium">{{ $stat['value'] }}</span>
                                <span class="text-t2 text-muted">{{ $stat['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <x-ui.list>
                        @foreach ($attendance as $st)
                            <x-ui.row wire:key="att-{{ $st['id'] }}">
                                <x-ui.avatar :name="$st['name']" :id="$st['id']" :class="$st['attended'] ? '' : 'opacity-60'" />
                                <x-ui.text :title="$st['name']" :sub="$st['attended'] ? 'Был на занятии' : 'Не был на занятии'" />
                            </x-ui.row>
                        @endforeach
                        <x-ui.row>
                            <x-ui.avatar :user="auth()->user()" />
                            <x-ui.text title="Вы" :sub="'Вели занятие ' . plural_ru($teacherMinutes, 'минуту', 'минуты', 'минут')" />
                        </x-ui.row>
                    </x-ui.list>
                </x-ui.card>

                <x-ui.card aria-labelledby="en-rec">
                    <x-ui.card-head id="en-rec" title="Запись занятия" />
                    @if ($recording)
                        <div class="flex items-center gap-4">
                            <x-ui.file-tile icon="video" />
                            <x-ui.text :title="$recording['title']" :sub="$recording['sub']" />
                            <a href="{{ $recording['url'] }}" class="link shrink-0 text-t2">Смотреть запись</a>
                        </div>
                    @elseif ($recordingState === 'processing')
                        <p class="text-t2 text-muted">Запись обрабатывается и появится в «Записях», когда будет готова.</p>
                    @else
                        <p class="text-t2 text-muted">Запись этого занятия не велась.</p>
                    @endif
                </x-ui.card>

                @include('livewire.cabinet.teacher.partials.lesson-homework', ['focus' => false, 'withAction' => false])
            </div>

            <div class="flex min-w-0 flex-col gap-6">
                <x-ui.card aria-labelledby="en-pay">
                    @php $single = $payments->count() === 1 ? $payments->first() : null; @endphp
                    <x-ui.card-head id="en-pay" title="Оплата занятия">
                        @if ($single)
                            <x-slot:action>
                                @if ($single['status'] === \App\Models\PaymentRecord::STATUS_PAID)<x-ui.badge tone="ok">Оплачено</x-ui.badge>
                                @elseif ($single['status'] === \App\Models\PaymentRecord::STATUS_UNPAID)<x-ui.badge :tone="$single['overdue'] ? 'danger' : 'neutral'">{{ $single['overdue'] ? 'Просрочено' : 'Не оплачено' }}</x-ui.badge>
                                @else<x-ui.badge>Не требуется</x-ui.badge>@endif
                            </x-slot:action>
                        @endif
                    </x-ui.card-head>
                    @if ($payments->isEmpty())
                        <p class="text-t2 text-muted">Начислений по этому занятию нет{{ $monthly ?? false ? ' — оплата помесячная' : '' }}.</p>
                    @elseif ($single)
                        <div class="flex flex-col gap-1">
                            @if ($single['amount'])<span class="text-t1 font-semibold">{{ \App\Support\Money::format($single['amount']) }}</span>@endif
                            <span class="text-t2 text-muted">
                                Начислено: {{ $single['name'] }}@if ($single['status'] === \App\Models\PaymentRecord::STATUS_UNPAID && $single['due']) · <x-ui.em>{{ $single['overdue'] ? 'срок был до ' : 'оплатить до ' }}{{ $single['due'] }}</x-ui.em>@endif
                            </span>
                        </div>
                        @if ($single['status'] === \App\Models\PaymentRecord::STATUS_UNPAID)
                            <x-ui.btn size="s" class="self-start" wire:click="markPaid({{ $single['studentId'] }}, [{{ $single['id'] }}])">Отметить оплату</x-ui.btn>
                        @elseif ($single['justPaid'])
                            <x-ui.btn size="s" class="self-start" wire:click="undoPaid({{ $single['studentId'] }})">Отменить</x-ui.btn>
                        @endif
                    @else
                        <x-ui.list>
                            @foreach ($payments as $p)
                                <x-ui.row wire:key="pay-{{ $p['id'] }}">
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1-s font-medium">{{ $p['name'] }}</span>
                                        <span class="text-t2 text-muted">{{ $p['amount'] ? \App\Support\Money::format($p['amount']) : '' }}@if ($p['status'] === \App\Models\PaymentRecord::STATUS_UNPAID && $p['overdue']) · <x-ui.em>срок был до {{ $p['due'] }}</x-ui.em>@endif</span>
                                    </div>
                                    @if ($p['status'] === \App\Models\PaymentRecord::STATUS_UNPAID)
                                        <x-ui.btn size="s" wire:click="markPaid({{ $p['studentId'] }}, [{{ $p['id'] }}])">Отметить</x-ui.btn>
                                    @elseif ($p['justPaid'])
                                        <x-ui.btn size="s" wire:click="undoPaid({{ $p['studentId'] }})">Отменить</x-ui.btn>
                                    @elseif ($p['status'] === \App\Models\PaymentRecord::STATUS_PAID)
                                        <x-ui.badge tone="ok">Оплачено</x-ui.badge>
                                    @endif
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>

                @if ($nextInfo && ! $archived)
                    <x-ui.card aria-labelledby="en-next">
                        <x-ui.card-head id="en-next" title="Следующее занятие">
                            <x-slot:action><a href="{{ route('cabinet.teacher.lesson', $room) }}" class="link text-t2">Открыть</a></x-slot:action>
                        </x-ui.card-head>
                        <x-ui.text :title="$nextInfo['when']" :sub="$nextInfo['sub']" />
                        <x-ui.btn size="s" class="self-start" wire:click="openReschedule">Перенести</x-ui.btn>
                    </x-ui.card>
                @endif

                @if ($sameDay->isNotEmpty())
                    <x-ui.card aria-labelledby="en-same">
                        <x-ui.card-head id="en-same" title="В тот же день" />
                        <x-ui.list>
                            @foreach ($sameDay as $o)
                                <x-ui.row :href="$o['url']" wire:key="same-{{ $o['id'] }}"><x-ui.text :title="$o['title']" /></x-ui.row>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                @endif

                <p class="text-t2 text-muted">
                    @if ($deletion)
                        Запрос на удаление отправлен. <button type="button" wire:click="revokeDeleteRequest" class="link">Отозвать запрос</button>
                    @else
                        Занятие прервалось или началось по ошибке? <button type="button" wire:click="openDeleteRequest" class="link">Запросить удаление</button>
                    @endif
                </p>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-6">
            <x-ui.tabs :items="['overview' => 'Обзор', 'materials' => 'Материалы', 'history' => 'Записи и история']" model="tab" :active="$tab" aria-label="Разделы занятия" />

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                    @if ($tab === 'overview')
                        @if ($mode === 'live')
                            <x-ui.card focus aria-labelledby="lv-now">
                                <x-ui.card-head id="lv-now" title="Сейчас в классе">
                                    <x-slot:action><a href="{{ $chatUrl }}" class="link text-t2">Чат занятия</a></x-slot:action>
                                </x-ui.card-head>
                                @if ($present->where('me', false)->isEmpty())
                                    <p class="text-t2 text-muted">Пока никто из учеников не подключился.</p>
                                @endif
                                @if ($present->isNotEmpty())
                                    <x-ui.list>
                                        @foreach ($present as $p)
                                            <x-ui.row wire:key="now-{{ $loop->index }}">
                                                <x-ui.avatar :name="$p['avatar']" :id="$p['id']" />
                                                <x-ui.text :title="$p['name']" :sub="$p['sub']" />
                                            </x-ui.row>
                                        @endforeach
                                    </x-ui.list>
                                @endif
                            </x-ui.card>
                            @include('livewire.cabinet.teacher.partials.lesson-homework', ['focus' => false, 'withAction' => true])
                        @else
                            @include('livewire.cabinet.teacher.partials.lesson-homework', ['focus' => true, 'withAction' => ! $archived])
                        @endif
                    @elseif ($tab === 'materials')
                        <x-ui.card focus aria-labelledby="l-mat">
                            <x-ui.card-head id="l-mat" title="Материалы к занятию">
                                <x-slot:action><x-ui.btn size="s" icon="plus" :href="$materialsUrl">Добавить</x-ui.btn></x-slot:action>
                            </x-ui.card-head>
                            @if ($files->isEmpty())
                                <p class="text-t2 text-muted">Пока пусто — откройте этому занятию материалы в разделе «Материалы».</p>
                            @else
                                <x-ui.list>
                                    @foreach ($files as $f)
                                        <x-ui.row wire:key="file-{{ $f['key'] }}">
                                            <x-ui.file-tile :name="$f['file'] ?? $f['name']" onMint />
                                            <x-ui.text :title="$f['name']" :sub="$f['sub']" />
                                            <a href="{{ $f['url'] }}" target="_blank" rel="noopener" class="link shrink-0 text-t2">Открыть</a>
                                        </x-ui.row>
                                    @endforeach
                                </x-ui.list>
                            @endif
                        </x-ui.card>
                    @else
                        <x-ui.card focus aria-labelledby="l-hist">
                            <x-ui.card-head id="l-hist" title="Прошедшие занятия" />
                            @if ($past->isEmpty())
                                <p class="text-t2 text-muted">Пока пусто — здесь появятся проведённые занятия, их записи и оплата.</p>
                            @else
                                <x-ui.list>
                                    @foreach ($past as $p)
                                        <x-ui.row wire:key="hist-{{ $p['id'] }}">
                                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                <a href="{{ $p['url'] }}" class="truncate text-t1 font-medium hover:underline">{{ $p['title'] }}</a>
                                                <span class="text-t2 text-muted">{{ $p['sub'] }}</span>
                                            </div>
                                            @if ($p['deletion'])<x-ui.badge>На удалении</x-ui.badge>@endif
                                            @if ($p['unpaid'])<x-ui.badge tone="danger">Не оплачено</x-ui.badge>@endif
                                            @if ($p['recordingUrl'])<a href="{{ $p['recordingUrl'] }}" class="link hidden shrink-0 text-t2 lg:inline">Смотреть запись</a>@endif
                                        </x-ui.row>
                                    @endforeach
                                </x-ui.list>
                            @endif
                        </x-ui.card>
                    @endif
                </div>

                <div class="flex min-w-0 flex-col gap-6">
                    @if ($mode === 'live')
                        <x-ui.card aria-labelledby="lv-guest">
                            <x-ui.card-head id="lv-guest" title="Позвать гостя" />
                            <p class="text-t2 text-muted">Родитель или второй учитель войдут по ссылке, без регистрации</p>
                            <div class="flex gap-2">
                                <input type="text" readonly value="{{ $guestUrl }}" class="field min-w-0 flex-1 text-muted" aria-label="Ссылка для гостя">
                                <x-ui.btn square icon="share" x-data x-on:click="{{ $copyGuest }}" aria-label="Скопировать ссылку" />
                            </div>
                        </x-ui.card>
                    @endif

                    @include('livewire.cabinet.teacher.partials.lesson-people')
                </div>
            </div>
        </div>
    @endif

    {{-- Завершить занятие --}}
    @if ($confirmStop && $mode === 'live')
        <x-ui.modal title="Завершить занятие?" :sub="$whoLine" close="keepRunning" width="s">
            <p class="text-t1 text-ink">Класс закроется для всех, вернуться в это занятие будет нельзя.</p>
            <p class="rounded-lg bg-soft p-4 text-t2 text-ink">{{ $stopNote }}</p>
            <x-slot:footer>
                <x-ui.btn wire:click="keepRunning">Продолжить занятие</x-ui.btn>
                <x-ui.btn variant="dark" :href="$stopUrl">Завершить занятие</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Перенести (макет LsReschedule): меняется правило расписания --}}
    @if ($rescheduleOpen)
        <x-ui.modal :title="$rsScheduleId ? 'Перенести занятие' : 'Назначить время'" :sub="$whoLine . ($rsCurrent ? ' · ' . $rsCurrent : '')" close="closeReschedule">
            @if ($rsSchedules)
                <x-ui.select label="Какое время изменить" name="rsScheduleId" :options="$rsSchedules" wire:model.live="rsScheduleId" />
            @endif
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Повтор</span>
                <x-ui.seg :items="['once' => 'Не повторять', 'weekly' => 'Каждую неделю']" model="rsRepeat" :active="$rsRepeat" aria-label="Повтор" />
            </div>
            @if ($rsRepeat === 'weekly')
                <div class="flex flex-col gap-2">
                    <span class="text-t2 font-medium">Новые дни</span>
                    @include('livewire.cabinet.teacher.partials.lesson-day-chips', ['selected' => $rsDays, 'action' => 'toggleRsDay', 'label' => 'Новые дни'])
                    @error('rsDays')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                </div>
            @endif
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <x-ui.field :label="$rsRepeat === 'weekly' ? 'Начиная с' : 'Новая дата'" name="rsDate" type="date" wire:model.live="rsDate" />
                <x-ui.field label="Время" name="rsTime" type="time" wire:model.live="rsTime" />
                <x-ui.select label="Длительность" name="rsDuration" :options="$rsDurations" wire:model="rsDuration" />
            </div>
            @if ($rsRepeat === 'weekly')
                <x-ui.field label="До какого дня, необязательно" name="rsUntil" type="date" wire:model="rsUntil" />
            @endif
            @if ($room->participants->isNotEmpty())
                <p class="text-t2 text-muted">{{ $group ? 'Ученики получат' : 'Ученик получит' }} уведомление об изменении расписания.</p>
            @endif
            <x-slot:note>@if ($rsNew)Новое время: <x-ui.em>{{ $rsNew }}</x-ui.em>@endif</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeReschedule">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveReschedule">{{ $rsScheduleId ? 'Перенести' : 'Сохранить' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Отменить (макет LsCancel): удаляется правило расписания или всё занятие --}}
    @if ($cancelOpen)
        @php
            $scopeSchedule = $cancelMany && $cancelScope === 'schedule';
            $once = $scopeSchedule && ($cancelSchedule['once'] ?? false);
        @endphp
        <x-ui.modal title="Отменить занятие?" :sub="$whoLine" close="closeCancel" width="s">
            @if ($cancelMany)
                <div class="flex flex-col gap-2" role="radiogroup" aria-label="Что отменить">
                    @foreach (['schedule' => ['Только это время', $cancelSchedule['label'] ?? ''], 'room' => ['Всё занятие', 'Все дни и время, занятие уйдёт в архив']] as $key => [$t, $d])
                        <button type="button" role="radio" aria-checked="{{ $cancelScope === $key ? 'true' : 'false' }}" wire:click="$set('cancelScope', '{{ $key }}')"
                                @class(['flex items-start gap-3 rounded-lg p-4 text-left', 'shadow-outline-ink' => $cancelScope === $key, 'shadow-outline' => $cancelScope !== $key])>
                            <span @class(['mt-1 flex size-4 shrink-0 items-center justify-center rounded-full', 'bg-ink' => $cancelScope === $key, 'shadow-outline' => $cancelScope !== $key])>
                                @if ($cancelScope === $key)<span class="size-1 rounded-full bg-white"></span>@endif
                            </span>
                            <span class="flex min-w-0 flex-col gap-1"><span class="text-t1-s font-medium">{{ $t }}</span><span class="text-t2 text-muted">{{ $d }}</span></span>
                        </button>
                    @endforeach
                </div>
            @endif
            <p class="rounded-lg bg-soft p-4 text-t2 text-ink">
                @if (! $scopeSchedule)
                    Занятие уйдёт в архив и исчезнет из расписания у вас и у учеников. Прошедшие занятия, записи и оплаты останутся.
                @elseif ($once)
                    Оплата за это занятие не начислится, в лимит тарифа оно не попадёт.
                @else
                    Будущие занятия в это время исчезнут из расписания у вас и у учеников. Прошедшие занятия, записи и оплаты останутся.
                @endif
            </p>
            @if ($room->participants->isNotEmpty())
                <p class="text-t2 text-muted">{{ $group ? 'Ученики получат' : 'Ученик получит' }} уведомление об изменении расписания.</p>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeCancel">Не отменять</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="confirmCancel">{{ $scopeSchedule && ! $once ? 'Отменить серию' : 'Отменить занятие' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Запросить удаление проведённого занятия (макет LsDeleteRequest) --}}
    @if ($deleteRequestOpen && $mode === 'report')
        <x-ui.modal title="Запросить удаление занятия" :sub="$sessionTitle" close="closeDeleteRequest" width="s">
            <p class="text-t1 text-ink">Проведённое занятие удаляет администратор Serdal. Решение придёт в уведомлениях.</p>
            <p class="rounded-lg bg-soft p-4 text-t2 text-ink">Занятие пропадёт из истории и не будет учитываться в лимите тарифа.</p>
            <x-ui.field label="Причина" name="deletionReason" :rows="3" wire:model="deletionReason" placeholder="Например: связь прервалась, и мы начали занятие заново" />
            <x-slot:footer>
                <x-ui.btn wire:click="closeDeleteRequest">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="sendDeleteRequest">Отправить запрос</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @include('livewire.cabinet.teacher.partials.lesson-start-blocked')
    @include('livewire.cabinet.teacher.partials.lesson-mark-paid-modal')
</div>
