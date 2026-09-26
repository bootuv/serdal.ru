<div class="flex flex-col gap-6 lg:gap-8">
    {{-- Шапка: назад, аватар 64, имя, одна строка фактов; справа действия --}}
    <div class="flex flex-col gap-4">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 self-start text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />Ученики</a>
        <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
            <div class="flex min-w-0 items-center gap-4">
                <x-ui.avatar :user="$student" size="lg" />
                <div class="flex min-w-0 flex-col gap-2">
                    <h1 class="text-h1-m font-medium lg:text-h1">{{ $student->name }}</h1>
                    @if ($facts || $next)
                        <p class="text-t1 text-muted">
                            {{ implode(' · ', $facts) }}@if ($facts && $next) · @endif
                            @if ($next)следующее занятие <a href="{{ $next['href'] }}" class="link">{{ $next['when'] }}</a>@endif
                        </p>
                    @endif
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                    <x-ui.btn square icon="more" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="Другие действия: условия оплаты, занятия, удалить из списка" />
                    <div x-show="open" x-cloak x-transition.opacity role="menu"
                         class="absolute left-0 top-full z-10 mt-2 flex w-sidebar flex-col rounded-lg bg-white p-2 shadow-modal lg:left-auto lg:right-0">
                        <button type="button" role="menuitem" class="flex h-11 items-center rounded-sm px-3 text-left text-t1-s font-medium hover:bg-soft-hover" x-on:click="open = false" wire:click="openModal('settings')">Условия оплаты</button>
                        <button type="button" role="menuitem" class="flex h-11 items-center rounded-sm px-3 text-left text-t1-s font-medium hover:bg-soft-hover" x-on:click="open = false" wire:click="openModal('assign')">Занятия ученика</button>
                        <button type="button" role="menuitem" class="flex h-11 items-center rounded-sm px-3 text-left text-t1-s font-medium text-danger-fg hover:bg-soft-hover" x-on:click="open = false" wire:click="openModal('remove')">Удалить из списка</button>
                    </div>
                </div>
                <x-ui.btn icon="chat" :href="$chatUrl">Написать</x-ui.btn>
                <x-ui.btn variant="dark" icon="plus" :href="$planUrl">Запланировать занятие</x-ui.btn>
            </div>
        </header>
    </div>

    <x-ui.tabs :items="['overview' => 'Обзор', 'lessons' => 'Занятия', 'tasks' => 'Задания', 'pay' => 'Оплата']" model="tab" :active="$tab" :counts="$tabCounts" aria-label="Разделы карточки ученика" />

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            @if ($tab === 'overview')
                {{-- Фокус: успеваемость у текущего учителя --}}
                <x-ui.card focus aria-labelledby="r-perf">
                    <x-ui.card-head id="r-perf" title="Успеваемость">
                        @if ($perfSub)
                            <x-slot:action><span class="text-t2 text-muted">{{ $perfSub }}</span></x-slot:action>
                        @endif
                    </x-ui.card-head>
                    <x-ui.rings :metrics="$metrics" />
                </x-ui.card>

                @if ($dues->isNotEmpty() || $paidNow->isNotEmpty())
                    @include('livewire.cabinet.teacher.partials.student-dues', ['focus' => false, 'id' => 'o-pay', 'waive' => false])
                @endif

                <x-ui.card aria-labelledby="o-hw">
                    <x-ui.card-head id="o-hw" title="Задания">
                        <x-slot:action><x-ui.btn size="s" icon="plus" :href="$taskNewUrl">Выдать задание</x-ui.btn></x-slot:action>
                    </x-ui.card-head>
                    @if ($homework->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — выданные задания появятся здесь.</p>
                    @else
                        <x-ui.list>
                            @foreach ($homework->take(3) as $t)
                                @include('livewire.cabinet.teacher.partials.student-task')
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>
            @elseif ($tab === 'lessons')
                <x-ui.card focus aria-labelledby="l-up">
                    <x-ui.card-head id="l-up" title="Предстоящие">
                        <x-slot:action><a href="{{ $scheduleUrl }}" class="link text-t2">Всё расписание</a></x-slot:action>
                    </x-ui.card-head>
                    @if ($upcoming->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — запланируйте занятие, и оно появится здесь.</p>
                    @else
                        <x-ui.list>
                            @foreach ($upcoming as $l)
                                @if ($loop->first && $l['today'])
                                    <x-ui.row wire:key="up-{{ $l['key'] }}">
                                        <span class="w-12 shrink-0 text-t1 font-medium">{{ $l['time'] }}</span>
                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <a href="{{ $l['href'] }}" class="truncate text-t1 font-medium hover:underline">{{ $l['title'] }}</a>
                                            <span class="text-t2 text-muted">@if ($l['soon'])<x-ui.em>{{ $l['soon'] }}</x-ui.em>@if ($l['meta']) · @endif @endif{{ $l['meta'] }}</span>
                                        </div>
                                        <x-ui.btn variant="primary" icon="play" :href="$l['startUrl']" class="hidden lg:inline-flex">{{ $l['running'] ? 'Войти в класс' : 'Начать занятие' }}</x-ui.btn>
                                        <x-ui.btn variant="primary" icon="play" square :href="$l['startUrl']" class="lg:hidden" :aria-label="$l['running'] ? 'Войти в класс' : 'Начать занятие'" />
                                    </x-ui.row>
                                @else
                                    <x-ui.row :href="$l['href']" wire:key="up-{{ $l['key'] }}">
                                        <span class="w-12 shrink-0 text-t1 font-medium">{{ $l['time'] }}</span>
                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span class="truncate text-t1 font-medium">{{ $l['title'] }}</span>
                                            @if ($l['meta'])<span class="text-t2 text-muted">{{ $l['meta'] }}</span>@endif
                                        </div>
                                    </x-ui.row>
                                @endif
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>

                <x-ui.card aria-labelledby="l-past">
                    <x-ui.card-head id="l-past" title="Прошедшие">
                        @if ($past['total'])
                            <x-slot:action>
                                <span class="text-t2 text-muted">{{ plural_ru($past['total'], 'занятие', 'занятия', 'занятий') }}@if ($past['missed']) · {{ plural_ru($past['missed'], 'пропуск', 'пропуска', 'пропусков') }}@endif</span>
                            </x-slot:action>
                        @endif
                    </x-ui.card-head>
                    @if ($past['rows']->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — прошедшие занятия появятся здесь.</p>
                    @else
                        <x-ui.list>
                            @foreach ($past['rows'] as $p)
                                <x-ui.row :href="$p['href']" wire:key="past-{{ $p['key'] }}">
                                    <span class="w-12 shrink-0 text-t1 font-medium">{{ $p['time'] }}</span>
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1 font-medium">{{ $p['title'] }}</span>
                                        <span class="text-t2 text-muted">{{ $p['sub'] }}</span>
                                    </div>
                                    @if ($p['missed'])<x-ui.badge tone="danger">Пропуск</x-ui.badge>@endif
                                    @if ($p['unpaid'])<x-ui.badge tone="danger">Не оплачено</x-ui.badge>@endif
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>
            @elseif ($tab === 'tasks')
                @php $toReview = $homework->where('state', 'review'); $rest = $homework->where('state', '!=', 'review'); @endphp
                <x-ui.card focus aria-labelledby="h-check">
                    <x-ui.card-head id="h-check" title="Нужно проверить" />
                    @if ($toReview->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — новых работ на проверку нет.</p>
                    @else
                        <x-ui.list>
                            @foreach ($toReview as $t)
                                @include('livewire.cabinet.teacher.partials.student-task')
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>

                <x-ui.card aria-labelledby="h-all">
                    <x-ui.card-head id="h-all" title="Все задания">
                        <x-slot:action><x-ui.btn size="s" icon="plus" :href="$taskNewUrl">Выдать задание</x-ui.btn></x-slot:action>
                    </x-ui.card-head>
                    @if ($rest->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — выданные задания появятся здесь.</p>
                    @else
                        <x-ui.list>
                            @foreach ($rest as $t)
                                @include('livewire.cabinet.teacher.partials.student-task')
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>
            @else
                @include('livewire.cabinet.teacher.partials.student-dues', ['focus' => true, 'id' => 'p-due', 'waive' => true])

                <x-ui.card aria-labelledby="p-hist">
                    <x-ui.card-head id="p-hist" title="История оплат" />
                    @if ($history->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — здесь появятся оплаченные занятия.</p>
                    @else
                        <x-ui.list>
                            @foreach ($history as $h)
                                <x-ui.row wire:key="hist-{{ $h['id'] }}">
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="text-t1 font-medium">{{ $h['title'] }}</span>
                                        @if ($h['sub'])<span class="text-t2 text-muted">{{ $h['sub'] }}</span>@endif
                                    </div>
                                    @if ($h['waived'])
                                        <x-ui.badge>Оплата не требуется</x-ui.badge>
                                    @elseif ($h['amount'])
                                        <span class="shrink-0 text-t1 font-medium">{{ $h['amount'] }}</span>
                                    @endif
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>

                <x-ui.card aria-labelledby="p-terms">
                    <x-ui.card-head id="p-terms" title="Условия оплаты">
                        <x-slot:action><x-ui.btn size="s" wire:click="openModal('settings')">Изменить</x-ui.btn></x-slot:action>
                    </x-ui.card-head>
                    <div class="flex flex-col gap-1">
                        <span class="text-t1 font-medium">{{ $terms['line'] }}</span>
                        <span class="text-t2 text-muted">{{ $terms['note'] }}</span>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <x-ui.card aria-labelledby="r-contacts">
            <x-ui.card-head id="r-contacts" title="Контакты" />
            @if ($contacts)
                <div class="flex flex-col gap-2">
                    @foreach ($contacts as $c)
                        <a href="{{ $c['href'] }}" class="truncate text-t1-s font-medium hover:underline" {!! $c['external'] ? 'target="_blank" rel="noopener"' : '' !!}>{{ $c['label'] }}</a>
                    @endforeach
                </div>
            @else
                <p class="text-t2 text-muted">Контактов нет — напишите в чате.</p>
            @endif
            <div class="flex flex-col gap-2 border-t border-line pt-4">
                <span class="text-t2 font-medium text-muted">Занятия</span>
                <div class="flex items-start justify-between gap-4">
                    <span class="min-w-0 text-t1-s">{{ $roomsLine }}</span>
                    <button type="button" class="link shrink-0 text-t2" wire:click="openModal('assign')">Изменить</button>
                </div>
            </div>
        </x-ui.card>
    </div>

    {{-- Окна «Отметить оплату» (PmMarkPaid) и «Не требовать оплату» (PmWaive): выбор неоплаченных начислений --}}
    @if (in_array($modal, ['mark', 'waive'], true))
        @php $isMark = $modal === 'mark'; @endphp
        <x-ui.modal :title="$isMark ? 'Отметить оплату' : 'Не требовать оплату?'" :sub="$student->name" close="closeModal" width="s">
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-t2 font-medium">{{ $isMark ? 'Что оплачено' : 'За какие занятия не брать оплату' }}</span>
                    <button type="button" class="link text-t2" wire:click="toggleAllRecords">{{ $modalData['allOn'] ? 'Снять все' : 'Выбрать все' }}</button>
                </div>
                <div class="flex flex-col gap-2" role="group" aria-label="Занятия">
                    @foreach ($modalData['rows'] as $row)
                        <x-ui.option value="{{ $row['id'] }}" wire:model.live="selected" wire:key="sel-{{ $row['id'] }}">
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1-s font-medium">{{ $row['title'] }}</span>
                                @if ($row['hint'])<span @class(['text-t2', 'font-semibold text-ink' => $row['overdue'], 'text-muted' => ! $row['overdue']])>{{ $row['hint'] }}</span>@endif
                            </span>
                            @if ($row['amount'])<span class="shrink-0 text-t1-s font-medium">{{ $row['amount'] }}</span>@endif
                        </x-ui.option>
                    @endforeach
                </div>
                @error('selected')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>
            @if ($isMark)
                @if ($modalData['total'])
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                        <span class="text-t1 font-medium">Итого</span>
                        <span class="text-h2 font-medium">{{ $modalData['total'] }}</span>
                    </div>
                @endif
            @else
                <p class="text-t2 text-muted">@if ($modalData['total'])Выбранное — <x-ui.em>{{ $modalData['total'] }}</x-ui.em> — перестанет считаться долгом. @endif Вернуть в долг нельзя.</p>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                @if ($isMark)
                    <x-ui.btn variant="primary" wire:click="markPaid" wire:loading.attr="disabled" wire:target="markPaid" :disabled="! $modalData['total']">Отметить оплату</x-ui.btn>
                @else
                    <x-ui.btn variant="dark" wire:click="waive" wire:loading.attr="disabled" wire:target="waive" :disabled="! $modalData['total']">Не требовать оплату</x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно «Продлить срок оплаты» (PmExtend) --}}
    @if ($modal === 'extend' && $modalData)
        <x-ui.modal title="Продлить срок оплаты" :sub="$modalData['sub']" close="closeModal" width="s">
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">На сколько продлить</span>
                <x-ui.seg :items="[3 => '3 дня', 7 => '7 дней', 14 => '14 дней', 30 => '30 дней']" model="extendDays" :active="$extendDays" aria-label="На сколько продлить" />
            </div>
            <div class="flex flex-col gap-2 border-t border-line pt-4">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-t1 font-medium">Новый срок</span>
                    <span class="text-t1 font-semibold">{{ $modalData['newDate'] }}</span>
                </div>
                <p class="text-t2 text-muted">{{ $modalData['explain'] }}</p>
            </div>
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="extend" wire:loading.attr="disabled" wire:target="extend">Продлить срок</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно «Условия оплаты» (PmSettings): бесплатно и персональный тип оплаты --}}
    @if ($modal === 'settings')
        <x-ui.modal title="Условия оплаты" :sub="$student->name" close="closeModal">
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-t1 font-medium">Не требовать оплату</span>
                    <span class="text-t2 text-muted">Счета и напоминания не приходят</span>
                </div>
                <x-ui.switch :checked="$settingsFree" label="Не требовать оплату" wire:click="$toggle('settingsFree')" />
            </div>
            @if ($settingsFree && ! $isFree && $modalData['debt'])
                <p class="text-t2 text-muted">Долг за <x-ui.em>{{ $modalData['debt'] }}</x-ui.em> спишется.</p>
            @endif
            @unless ($settingsFree)
                <div class="flex flex-col gap-2 border-t border-line pt-4" role="radiogroup" aria-labelledby="st-type">
                    <span id="st-type" class="text-t2 font-medium">Как {{ $firstName }} платит</span>
                    @foreach ($terms['options'] as $value => $option)
                        <x-ui.option type="radio" name="settingsType" value="{{ $value }}" wire:model.live="settingsType" wire:key="pt-{{ $value }}" :title="$option['title']" :sub="$option['sub']" />
                    @endforeach
                    <span class="text-t3 text-muted">Действует для следующих счетов</span>
                </div>
            @endunless
            <x-slot:note><a href="{{ $pricesUrl }}" class="link">Ваши цены</a></x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveSettings" wire:loading.attr="disabled" wire:target="saveSettings">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно «Занятия ученика» (PmAssign) --}}
    @if ($modal === 'assign')
        <x-ui.modal title="Занятия ученика" :sub="'Отметьте, в каких участвует ' . $firstName" close="closeModal">
            @if ($modalData['rooms']->isEmpty())
                <p class="text-t2 text-muted">У вас пока нет занятий. Создайте занятие, а затем назначьте его ученику.</p>
            @else
                <div class="flex flex-col gap-2" role="group" aria-label="Ваши занятия">
                    @foreach ($modalData['rooms'] as $r)
                        <x-ui.option value="{{ $r['id'] }}" wire:model.live="roomIds" wire:key="room-{{ $r['id'] }}">
                            @if ($r['group'])
                                <x-ui.avatar group />
                            @else
                                <span class="flex size-10 shrink-0 items-center justify-center rounded bg-soft" aria-hidden="true"><x-ui.icon name="user" size="s" /></span>
                            @endif
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1-s font-medium">{{ $r['name'] }}</span>
                                <span class="truncate text-t2 text-muted">{{ $r['sub'] }}</span>
                            </span>
                        </x-ui.option>
                    @endforeach
                </div>
            @endif
            <x-slot:note><a href="{{ $planUrl }}" class="link">Новое занятие</a></x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                @if ($modalData['rooms']->isNotEmpty())
                    <x-ui.btn variant="primary" wire:click="saveRooms" wire:loading.attr="disabled" wire:target="saveRooms">Сохранить</x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно «Удалить из списка» (PmRemove) --}}
    @if ($modal === 'remove')
        <x-ui.modal title="Удалить из списка?" :sub="$student->name . ($since ? ' · занимается с ' . $since : '')" close="closeModal" width="s">
            <p class="text-t1">
                {{ $firstName }} выйдет из всех ваших занятий@if ($modalData['next']), включая ближайшее — {{ $modalData['next']['when'] }},@endif и получит уведомление. Задания и ответы сохранятся.
            </p>
            @if ($modalData['debt'])
                <p class="text-t2 text-muted">Долг {{ $modalData['debt'] }} не спишется.</p>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="remove" wire:loading.attr="disabled" wire:target="remove">Удалить из списка</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @include('livewire.cabinet.teacher.partials.payment-claim-modal')
</div>
