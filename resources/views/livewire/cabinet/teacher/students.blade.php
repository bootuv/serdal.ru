<div class="flex flex-col gap-6 lg:gap-8">
    @if ($flash)
        <div x-data x-init="setTimeout(() => $dispatch('toast', { message: @js($flash) }))"></div>
    @endif

    <x-ui.page-head title="Ученики" :sub="$sub">
        @unless ($isEmpty)
            <x-slot:actions>
                <x-ui.btn variant="dark" icon="plus" wire:click="openInvite('link')">Пригласить ученика</x-ui.btn>
            </x-slot:actions>
        @endunless
    </x-ui.page-head>

    @if ($isEmpty)
        {{-- Пустой раздел (макет PmTeacherEmpty) --}}
        <x-ui.card focus aria-label="Учеников пока нет">
            <x-ui.empty icon="users" title="Учеников пока нет" text="Отправьте ссылку-приглашение — ученик появится здесь после регистрации.">
                <x-slot:action>
                    <div class="flex flex-wrap justify-center gap-2">
                        <x-ui.btn variant="primary" icon="plus" wire:click="openInvite('link')">Пригласить ученика</x-ui.btn>
                        <x-ui.btn wire:click="openInvite('find')">Найти на Serdal</x-ui.btn>
                    </div>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:gap-6">
            <x-ui.tabs class="flex-1" :items="['students' => 'Ученики', 'groups' => 'Группы']" model="tab" :active="$tab" aria-label="Ученики и группы" />
            <div class="lg:border-b lg:border-line lg:pb-2">
                <x-ui.search placeholder="Имя, email или телефон" wire:model.live.debounce.300ms="search" />
            </div>
        </div>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                @if ($tab === 'groups')
                    <x-ui.card aria-label="Группы">
                        @if ($groups->isEmpty())
                            <p class="text-t2 text-muted">{{ $search !== '' ? 'Никого не нашли — проверьте запрос.' : 'Пока пусто — групп ещё нет.' }}</p>
                        @else
                            <div class="hidden gap-4 text-t3 font-medium text-muted lg:flex" aria-hidden="true">
                                <span class="flex-1">Группа</span><span class="w-40">Ближайшее занятие</span><span class="w-40">Оплата</span><span class="w-5"></span>
                            </div>
                            <x-ui.list>
                                @foreach ($groups as $g)
                                    <x-ui.row :href="$g['href']" wire:key="group-{{ $g['id'] }}">
                                        <x-ui.avatar group />
                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span class="truncate text-t1 font-medium">{{ $g['name'] }}</span>
                                            <span class="truncate text-t2 text-muted">{{ $g['people'] }}</span>
                                        </div>
                                        <div class="hidden w-40 shrink-0 flex-col gap-1 lg:flex">
                                            @if ($g['next'])
                                                <span class="text-t1-s font-medium">{{ $g['next']['when'] }}</span>
                                                @if ($g['next']['note'])<span class="truncate text-t2 font-semibold text-ink">{{ $g['next']['note'] }}</span>
                                                @elseif ($g['schedule'])<span class="truncate text-t2 text-muted">{{ $g['schedule'] }}</span>@endif
                                            @else
                                                <span class="text-t2 text-muted">Нет в расписании</span>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-1 lg:w-40 lg:items-start">
                                            @if ($g['overdue'])
                                                <x-ui.badge tone="danger">Просрочено</x-ui.badge>
                                                <span class="hidden text-t2 text-muted lg:block">у {{ plural_ru($g['overdue'], 'ученика', 'учеников', 'учеников') }}</span>
                                            @elseif ($g['unpaid'])
                                                <x-ui.badge>Не оплачено</x-ui.badge>
                                                <span class="hidden text-t2 text-muted lg:block">у {{ plural_ru($g['unpaid'], 'ученика', 'учеников', 'учеников') }}</span>
                                            @endif
                                        </div>
                                    </x-ui.row>
                                @endforeach
                            </x-ui.list>
                        @endif
                        <p class="border-t border-line pt-4 text-t2 text-muted">Чтобы собрать группу, запланируйте занятие с несколькими учениками.</p>
                    </x-ui.card>
                @else
                    <x-ui.card aria-label="Список учеников">
                        <x-ui.seg fit :items="$filters" model="filter" :active="$filter" aria-label="Кого показать" />

                        @if ($rows->isEmpty())
                            <p class="text-t2 text-muted">
                                @if ($search !== '')
                                    Никого не нашли — проверьте запрос или <button type="button" class="link" wire:click="openInvite('find')">найдите ученика на Serdal</button>.
                                @elseif ($filter === 'debt')
                                    Долгов нет — все прошедшие занятия оплачены. <button type="button" class="link" wire:click="$set('filter', 'all')">Показать всех</button>
                                @else
                                    У всех учеников есть занятия. <button type="button" class="link" wire:click="$set('filter', 'all')">Показать всех</button>
                                @endif
                            </p>
                        @else
                            <div class="hidden gap-4 text-t3 font-medium text-muted lg:flex" aria-hidden="true">
                                <span class="flex-1">Ученик</span><span class="w-40">Ближайшее занятие</span><span class="w-40">Оплата</span><span class="w-5"></span>
                            </div>
                            <x-ui.list>
                                @foreach ($rows as $s)
                                    <x-ui.row :href="$s['href']" wire:key="student-{{ $s['id'] }}">
                                        <x-ui.avatar :user="$s['user']" />
                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span class="truncate text-t1 font-medium">{{ $s['name'] }}</span>
                                            <span class="truncate text-t2 text-muted">{{ $s['sub'] }}</span>
                                        </div>
                                        <div class="hidden w-40 shrink-0 flex-col gap-1 lg:flex">
                                            @if ($s['next'])
                                                <span class="text-t1-s font-medium">{{ $s['next']['when'] }}</span>
                                                @if ($s['next']['note'])
                                                    <span @class(['truncate text-t2', 'font-semibold text-ink' => $s['next']['urgent'], 'text-muted' => ! $s['next']['urgent']])>{{ $s['next']['note'] }}</span>
                                                @endif
                                            @elseif ($s['none'])
                                                <span class="text-t1-s font-medium">Не назначено</span>
                                                <span class="text-t2 text-muted">назначьте в карточке</span>
                                            @else
                                                <span class="text-t2 text-muted">Нет в расписании</span>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-1 lg:w-40 lg:items-start">
                                            @if ($s['state'] === 'blocked')
                                                <x-ui.badge tone="danger">Вход закрыт</x-ui.badge>
                                            @elseif ($s['state'] === 'overdue')
                                                <x-ui.badge tone="danger">Просрочено</x-ui.badge>
                                            @elseif ($s['state'] === 'unpaid')
                                                <x-ui.badge>Не оплачено</x-ui.badge>
                                            @endif
                                            @if ($s['payNote'])<span class="hidden text-t2 text-muted lg:block">{{ $s['payNote'] }}</span>@endif
                                        </div>
                                    </x-ui.row>
                                @endforeach
                            </x-ui.list>
                        @endif
                    </x-ui.card>
                @endif
            </div>

            {{-- Фокус: кто ждёт отметки оплаты --}}
            <x-ui.card focus aria-labelledby="s-pay">
                <x-ui.card-head id="s-pay" title="Ждут оплаты">
                    <x-slot:action>
                        <span class="text-t1 font-medium">{{ $debts->where('paid', false)->isNotEmpty() ? $debtTotalLabel : 'Всё оплачено' }}</span>
                    </x-slot:action>
                </x-ui.card-head>
                @if ($debts->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — все прошедшие занятия оплачены.</p>
                @else
                    <x-ui.list>
                        @foreach ($debts as $d)
                            <x-ui.row wire:key="debt-{{ $d['row']['id'] }}">
                                <div class="flex min-w-0 flex-1 flex-col gap-3">
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :user="$d['row']['user']" />
                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <a href="{{ $d['row']['href'] }}" class="truncate text-t1 font-medium hover:underline">{{ $d['row']['name'] }}</a>
                                            <span class="text-t2 text-muted">{{ $d['meta'] }}</span>
                                        </div>
                                        @if ($d['paid'])
                                            <x-ui.badge tone="ok">Оплачено</x-ui.badge>
                                        @elseif ($d['overdue'])
                                            <x-ui.badge tone="danger">Просрочено</x-ui.badge>
                                        @endif
                                    </div>
                                    @if ($d['note'])<p class="text-t2 text-muted">{{ $d['note'] }}</p>@endif
                                    <div class="flex flex-wrap gap-2">
                                        @unless ($d['paid'])
                                            <x-ui.btn size="s" wire:click="markPaid({{ $d['row']['id'] }})" wire:loading.attr="disabled" wire:target="markPaid">Отметить оплату</x-ui.btn>
                                            @if ($d['overdue'])
                                                <x-ui.btn size="s" wire:click="openExtend({{ $d['row']['id'] }})">Продлить срок</x-ui.btn>
                                            @endif
                                        @endunless
                                        @if ($d['undo'])
                                            <x-ui.btn size="s" wire:click="undoPaid({{ $d['row']['id'] }})">Отменить</x-ui.btn>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
        </div>
    @endif

    {{-- Окно «Пригласить ученика» (макет PmInvite) --}}
    @if ($invite)
        <x-ui.modal title="Пригласить ученика" sub="Он появится в вашем списке" close="closeInvite">
            <x-ui.tabs :items="['link' => 'По ссылке', 'find' => 'Уже на Serdal']" model="inviteTab" :active="$inviteTab" aria-label="Как пригласить" />

            @if ($inviteTab === 'link')
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-2" x-data="{ copied: false, link: @js($invite['link']) }">
                        <label for="invite-link" class="text-t2 font-medium">Ссылка-приглашение</label>
                        <div class="flex items-center gap-2">
                            <input id="invite-link" type="text" readonly value="{{ $invite['link'] }}" class="field min-w-0 flex-1 bg-soft text-muted shadow-none" x-on:focus="$el.select()">
                            <x-ui.btn x-show="! copied" x-on:click="navigator.clipboard.writeText(link); copied = true; $dispatch('toast', { message: 'Ссылка скопирована' })">Скопировать</x-ui.btn>
                            <x-ui.badge tone="ok" x-show="copied" x-cloak>Скопировано</x-ui.badge>
                        </div>
                        <span class="text-t3 text-muted">Одна для всех учеников и не устаревает</span>
                    </div>
                    <x-ui.field label="Или отправим на почту" name="inviteEmail" type="email" placeholder="Email ученика" wire:model="inviteEmail" wire:keydown.enter="sendInvite" />
                </div>
            @else
                <div class="flex flex-col gap-4">
                    <x-ui.field label="Имя или email ученика" name="findQuery" type="search" placeholder="Например, Ольга Белова" wire:model.live.debounce.300ms="findQuery" autofocus />
                    @if ($invite['query'] === '')
                        <p class="text-t2 text-muted">Начните вводить имя или email</p>
                    @elseif ($invite['results']->isEmpty())
                        <p class="text-t2 text-muted">Никого не нашли. <button type="button" class="link" wire:click="$set('inviteTab', 'link')">Пригласить по ссылке</button></p>
                    @else
                        <div class="flex flex-col gap-2" role="radiogroup" aria-label="Найденные ученики">
                            @foreach ($invite['results'] as $found)
                                @php $on = $pickId === $found->id; @endphp
                                <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:key="found-{{ $found->id }}" wire:click="$set('pickId', {{ $found->id }})"
                                        @class(['flex items-center gap-3 rounded-lg p-3 text-left', 'shadow-outline-ink' => $on, 'shadow-outline hover:shadow-outline-ink' => ! $on])>
                                    <span @class(['flex size-5 shrink-0 items-center justify-center rounded-full', 'bg-ink' => $on, 'shadow-outline' => ! $on])>
                                        @if ($on)<span class="size-2 rounded-full bg-white"></span>@endif
                                    </span>
                                    <x-ui.avatar :user="$found" />
                                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1-s font-medium">{{ $found->name }}</span>
                                        <span class="truncate text-t2 text-muted">{{ $found->email }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                        @error('pickId')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                    @endif
                </div>
            @endif

            <x-slot:note>@if ($inviteTab === 'find')Ученик получит уведомление@endif</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeInvite">Отмена</x-ui.btn>
                @if ($inviteTab === 'link')
                    <x-ui.btn variant="primary" wire:click="sendInvite" wire:loading.attr="disabled" wire:target="sendInvite">Отправить приглашение</x-ui.btn>
                @else
                    <x-ui.btn variant="primary" wire:click="addStudent" :disabled="! $pickId">Добавить ученика</x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @include('livewire.cabinet.teacher.partials.lesson-mark-paid-modal')

    {{-- Окно «Продлить срок оплаты» (макет PmExtend) --}}
    @if ($extend)
        <x-ui.modal title="Продлить срок оплаты" :sub="$extend['sub']" close="closeExtend" width="s">
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">На сколько продлить</span>
                <x-ui.seg :items="[3 => '3 дня', 7 => '7 дней', 14 => '14 дней', 30 => '30 дней']" model="extendDays" :active="$extendDays" aria-label="На сколько продлить" />
            </div>
            <div class="flex flex-col gap-2 border-t border-line pt-4">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-t1 font-medium">Новый срок</span>
                    <span class="text-t1 font-semibold">{{ $extend['newDate'] }}</span>
                </div>
                <p class="text-t2 text-muted">{{ $extend['explain'] }}</p>
            </div>
            <x-slot:footer>
                <x-ui.btn wire:click="closeExtend">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="extend" wire:loading.attr="disabled" wire:target="extend">Продлить срок</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
