<div class="flex flex-col gap-6 lg:gap-8">
    @if ($empty)
        {{-- Новый учитель: первые шаги (макет SyEmptyTeacher) --}}
        <x-ui.page-head :title="'Добро пожаловать, ' . $firstName . '!'" :sub="$today" />

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <x-ui.card focus class="lg:col-span-2" aria-labelledby="fs">
                <x-ui.card-head id="fs" title="Первые шаги">
                    <x-slot:action><span class="text-t2 text-muted"><x-ui.em>{{ $steps['done'] }} из {{ $steps['total'] }}</x-ui.em> готово</span></x-slot:action>
                </x-ui.card-head>
                <x-ui.list>
                    @foreach ($steps['items'] as $step)
                        @if ($step['current'])
                            <div class="-mx-4 flex flex-col gap-3 rounded-lg bg-white p-4 shadow-card" wire:key="step-{{ $step['key'] }}">
                                <div class="flex items-start gap-4">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-ink text-count font-semibold text-white">{{ $step['n'] }}</span>
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="text-t1 font-medium">{{ $step['title'] }}</span>
                                        <span class="text-t2 text-muted">{{ $step['text'] }}</span>
                                    </div>
                                </div>
                                @if ($step['key'] === 'invite' && $steps['invitation'])
                                    <x-ui.copy-field label="Ссылка-приглашение" hide-label id="inv-link" :value="$steps['invitation']" variant="primary" button="Скопировать ссылку" message="Ссылка скопирована — отправьте её ученику" />
                                    <a href="{{ $steps['emailUrl'] }}" class="link self-start text-t2">Отправить на почту</a>
                                @elseif ($step['key'] === 'plan')
                                    <x-ui.btn variant="primary" icon="plus" wire:click="openPlan" class="self-start">Запланировать занятие</x-ui.btn>
                                @elseif ($step['action'])
                                    <x-ui.btn variant="primary" :href="$step['url']" class="self-start">{{ $step['action'] }}</x-ui.btn>
                                @endif
                            </div>
                        @elseif ($step['done'] && $step['url'])
                            <x-ui.row :href="$step['url']" wire:key="step-{{ $step['key'] }}">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-ok-bg text-ok-fg"><x-ui.icon name="check" size="s" /></span>
                                <span class="min-w-0 flex-1 truncate text-t1 font-medium text-muted">{{ $step['title'] }}</span>
                                @if ($step['facts'])<span class="hidden text-t2 text-muted lg:inline">{{ $step['facts'] }}</span>@endif
                            </x-ui.row>
                        @else
                            <x-ui.row wire:key="step-{{ $step['key'] }}">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-soft text-count font-semibold text-muted">{{ $step['n'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-t1 font-medium text-muted">{{ $step['title'] }}</span>
                                @if ($step['facts'])<span class="text-t2 text-muted">{{ $step['facts'] }}</span>@endif
                            </x-ui.row>
                        @endif
                    @endforeach
                </x-ui.list>
            </x-ui.card>

            @if ($tariff)<x-ui.tariff :summary="$tariff" />@endif
        </div>
    @else
        <x-ui.page-head title="Сегодня" :sub="$today">
            <x-slot:actions>
                <x-ui.btn :href="$inviteUrl" class="hidden lg:inline-flex">Пригласить ученика</x-ui.btn>
                <x-ui.btn variant="dark" icon="plus" wire:click="openPlan" class="hidden lg:inline-flex">Запланировать занятие</x-ui.btn>
                <x-ui.btn variant="dark" square icon="plus" wire:click="openPlan" class="lg:hidden" aria-label="Запланировать занятие" />
            </x-slot:actions>
        </x-ui.page-head>

        {{-- Лимит тарифа (макет SyLimit) --}}
        @if ($limitBanner)
            <x-ui.card class="lg:flex-row lg:items-center lg:justify-between lg:gap-6" aria-label="Лимит занятий">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-t1 font-medium">{{ $limitBanner['title'] }}</span>
                    <span class="text-t2 text-muted">{{ $limitBanner['text'] }}@if ($limitBanner['em']) <x-ui.em>{{ $limitBanner['em'] }}</x-ui.em>@endif</span>
                </div>
                <x-ui.btn :href="$limitBanner['action']['url']" class="self-start lg:self-center">{{ $limitBanner['action']['label'] }}</x-ui.btn>
            </x-ui.card>
        @endif

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                {{-- Фокус: занятия сегодня --}}
                <x-ui.card focus aria-labelledby="t-lessons">
                    <x-ui.card-head id="t-lessons" title="Занятия сегодня">
                        <x-slot:action><a href="{{ $scheduleUrl }}" class="link text-t2">Всё расписание</a></x-slot:action>
                    </x-ui.card-head>
                    @if ($rows->isEmpty())
                        <p class="text-t2 text-muted">
                            Сегодня занятий нет.
                            @if ($nextLesson) Ближайшее — <a href="{{ $nextLesson['url'] }}" class="link">{{ $nextLesson['when'] }}, {{ $nextLesson['title'] }}</a>.@endif
                        </p>
                    @else
                        <x-ui.list>
                            @foreach ($rows as $row)
                                @include('livewire.cabinet.teacher.partials.lesson-row', ['row' => $row, 'afterFocus' => $loop->index > 0 && $rows[$loop->index - 1]['focus']])
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>

                {{-- Работы на проверку --}}
                @if ($review->isNotEmpty())
                    <x-ui.card aria-labelledby="t-review">
                        <x-ui.card-head id="t-review" :title="'Нужно проверить · ' . $reviewCount">
                            <x-slot:action><a href="{{ $reviewAllUrl }}" class="link text-t2">Все работы</a></x-slot:action>
                        </x-ui.card-head>
                        <x-ui.list>
                            @foreach ($review as $item)
                                <x-ui.row :href="$item['url']" wire:key="rv-{{ $item['key'] }}">
                                    <x-ui.avatar :name="$item['student']" :id="$item['studentId']" />
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1 font-medium">{{ $item['title'] }}</span>
                                        <span class="truncate text-t2 text-muted">{{ $item['student'] }} · {{ $item['submitted'] }}</span>
                                    </div>
                                    @if ($item['waits'])<span class="shrink-0 text-t2"><x-ui.em>{{ $item['waits'] }}</x-ui.em></span>@endif
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                @endif

                {{-- Сообщения --}}
                @if ($messages->isNotEmpty())
                    <x-ui.card aria-labelledby="t-msg">
                        <x-ui.card-head id="t-msg" title="Сообщения">
                            <x-slot:action><a href="{{ $messagesUrl }}" class="link text-t2">Все</a></x-slot:action>
                        </x-ui.card-head>
                        <x-ui.list>
                            @foreach ($messages as $m)
                                <x-ui.row :href="$m['url']" :chevron="false" align="start" wire:key="msg-{{ $m['key'] }}">
                                    <x-ui.avatar :name="$m['name']" :id="$m['userId']" />
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span @class(['truncate text-t1-s', 'font-semibold' => $m['unread'], 'font-medium' => ! $m['unread']])>{{ $m['name'] }}</span>
                                            <span class="shrink-0 text-t2 text-muted">{{ $m['time'] }}</span>
                                        </div>
                                        <span @class(['truncate text-t2', 'text-ink' => $m['unread'], 'text-muted' => ! $m['unread']])>{{ $m['text'] }}</span>
                                    </div>
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                @endif
            </div>

            <div class="flex min-w-0 flex-col gap-6">
                {{-- Тариф на виду: остаток занятий и лимиты --}}
                <x-ui.tariff :summary="$tariff" />

                {{-- Отзыв о платформе: приглашение после нескольких проведённых занятий --}}
                <livewire:cabinet.teacher.platform-review prompt />

                {{-- Ждут оплаты --}}
                @if ($payments->isNotEmpty())
                    <x-ui.card aria-labelledby="t-pay">
                        <x-ui.card-head id="t-pay" title="Ждут оплаты">
                            <x-slot:action><a href="{{ $paymentsUrl }}" class="link text-t2">Все</a></x-slot:action>
                        </x-ui.card-head>
                        <x-ui.list>
                            @foreach ($payments as $p)
                                <div class="flex flex-col gap-3 border-t border-line py-4 last:pb-0" wire:key="pay-{{ $p['id'] }}">
                                    <div class="flex items-start gap-3">
                                        <x-ui.avatar :name="$p['name']" :id="$p['id']" />
                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span class="truncate text-t1 font-medium">{{ $p['name'] }}</span>
                                            <span class="text-t2 text-muted">{{ $p['facts'] }}</span>
                                            @if ($p['claim'])<span class="text-t2"><x-ui.em>Ученик сообщил об оплате</x-ui.em></span>@endif
                                        </div>
                                        @if ($p['paid'])<x-ui.badge tone="ok">Оплачено</x-ui.badge>
                                        @elseif ($p['badge'])<x-ui.badge tone="danger">{{ $p['badge'] }}</x-ui.badge>@endif
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($p['paid'])
                                            <x-ui.btn size="s" wire:click="undoPaid({{ $p['id'] }})">Отменить</x-ui.btn>
                                        @elseif ($p['claim'])
                                            <x-ui.btn size="s" wire:click="openClaim({{ $p['claim']['id'] }})">Проверить оплату</x-ui.btn>
                                        @else
                                            <x-ui.btn size="s" wire:click="markPaid({{ $p['id'] }})">Отметить оплату</x-ui.btn>
                                            @if ($p['overdue'])<x-ui.btn size="s" wire:click="remind({{ $p['id'] }})" wire:loading.attr="disabled" wire:target="remind">Напомнить</x-ui.btn>@endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                @endif

            </div>
        </div>
    @endif

    @include('livewire.cabinet.teacher.partials.lesson-plan-modal')
    @include('livewire.cabinet.teacher.partials.lesson-start-blocked')
    @include('livewire.cabinet.teacher.partials.lesson-mark-paid-modal')
    @include('livewire.cabinet.teacher.partials.payment-claim-modal')
</div>
