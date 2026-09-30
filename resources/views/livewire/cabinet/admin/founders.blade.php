{{-- Основатели: вкладки «Взносы» (текущий сбор, долги), «История» (все месяцы, итоги по основателям), «Расходы» (постоянные и разовые), «Доли»; окна расхода, основателя, удаления и настроек сбора. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Основатели" :sub="$factLine">
        <x-slot:actions>
            @if ($tab === 'expenses')
                <x-ui.btn variant="dark" icon="plus" wire:click="editExpense">Добавить расход</x-ui.btn>
            @elseif ($tab === 'shares')
                <x-ui.btn variant="dark" icon="plus" wire:click="editFounder">Добавить основателя</x-ui.btn>
            @elseif ($tab === 'collect')
                <x-ui.btn icon="settings" wire:click="openSettings">Настройки сбора</x-ui.btn>
            @endif
        </x-slot:actions>
    </x-ui.page-head>

    <x-ui.tabs :items="$tabs" model="tab" :active="$tab" :counts="$tabCounts" aria-label="Разделы" />

    @if ($tab === 'collect')
        @if ($contributions->isEmpty())
            <x-ui.card>
                <x-ui.empty icon="lock" title="Пока нет основателей" text="Укажите основателей и их доли — взносы посчитаются от расходов.">
                    <x-slot:action><x-ui.btn wire:click="$set('tab', 'shares')">Указать доли</x-ui.btn></x-slot:action>
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <x-ui.card focus class="min-w-0 lg:col-span-2" aria-labelledby="f-now">
                    <div class="flex flex-col gap-1">
                        <h2 id="f-now" class="text-h2 font-medium">{{ $periodTitle }}</h2>
                        <span @class(['text-t2', 'font-semibold text-danger-fg' => $overdue && $contributions->contains('paid', false), 'text-muted' => ! $overdue || ! $contributions->contains('paid', false)])>{{ $periodSub }}</span>
                    </div>
                    <x-ui.list>
                        @foreach ($contributions as $c)
                            <div wire:key="fc-{{ $c['id'] }}" class="flex items-center gap-4 border-t border-line py-4 last:pb-0">
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1 font-medium">{{ $c['name'] }}</span>
                                    <span class="text-t2 text-muted">{{ $c['sub'] }}</span>
                                    @if ($c['claimed'])<span class="text-t2"><x-ui.em>{{ $c['claimed'] }}</x-ui.em></span>@endif
                                </div>
                                <span class="shrink-0 text-t1 font-medium">{{ $c['amount'] }}</span>
                                <x-ui.switch :checked="$c['paid']" :label="'Внесено: ' . $c['name']" wire:click="togglePaid({{ $c['id'] }})" />
                            </div>
                        @endforeach
                    </x-ui.list>
                    <div class="flex flex-col gap-1 border-t border-line pt-4">
                        <div class="flex items-baseline justify-between gap-4">
                            <span class="text-t2 text-muted">Расходы в месяц</span>
                            <span class="text-t1 font-medium">{{ $money($total) }}</span>
                        </div>
                        @unless ($sharesOk)
                            <span class="text-t2"><x-ui.em danger>Доли в сумме {{ \App\Services\FounderService::percent($sharesTotal) }}, а должно быть 100 %</x-ui.em> — поправьте на вкладке «Доли».</span>
                        @endunless
                        <span class="text-t2 text-muted">{{ $remindLine }}</span>
                    </div>
                    @if ($canRemind)
                        <x-ui.btn icon="mail" class="self-start" wire:click="openRemind('{{ $periodKey }}')">Напомнить на почту</x-ui.btn>
                    @endif
                </x-ui.card>

                <x-ui.card class="min-w-0" aria-labelledby="f-debts">
                    <x-ui.card-head id="f-debts" title="Долги" :count="$debts->count()">
                        <x-slot:action><button type="button" wire:click="$set('tab', 'history')" class="link text-t2">Вся история</button></x-slot:action>
                    </x-ui.card-head>
                    @if ($debts->isEmpty())
                        <p class="text-t2 text-muted">Долгов нет — за прошлые месяцы все внесли.</p>
                    @else
                        <x-ui.list>
                            @foreach ($debts as $d)
                                <div wire:key="fd-{{ $d['id'] }}" class="flex items-center gap-4 border-t border-line py-4 first:border-t-0 first:pt-0 last:pb-0">
                                    <x-ui.text :title="$d['name']" :sub="$d['amount'] . ' ' . $d['sub']" />
                                    <x-ui.switch :checked="false" :label="'Внесено: ' . $d['name']" wire:click="togglePaid({{ $d['id'] }})" />
                                </div>
                            @endforeach
                        </x-ui.list>
                        @if ($debts->count() > 1)
                            <span class="border-t border-line pt-4 text-t2 text-muted">Всего <x-ui.em danger>{{ $debtsTotal }}</x-ui.em></span>
                        @endif
                    @endif
                </x-ui.card>
            </div>
        @endif
    @elseif ($tab === 'history')
        @if ($history['totals']->isEmpty())
            <x-ui.card>
                <x-ui.empty icon="lock" title="Истории пока нет" text="Укажите основателей и их доли — взносы начнут записываться с этого месяца.">
                    <x-slot:action><x-ui.btn wire:click="$set('tab', 'shares')">Указать доли</x-ui.btn></x-slot:action>
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <x-ui.card class="min-w-0 lg:col-span-2" aria-labelledby="f-months">
                    <x-ui.card-head id="f-months" title="По месяцам" />
                    <x-ui.seg fit :items="$historyFilters" model="historyFilter" :active="$historyFilter" aria-label="Какие месяцы показать" />
                    @if ($history['months']->isEmpty())
                        <p class="border-t border-line pt-4 text-t2 text-muted">{{ $historyFilter === 'debts' ? 'Таких месяцев нет — все взносы внесены.' : 'Пока пусто — взносы появятся в день сбора.' }}</p>
                    @else
                        <div class="flex flex-col">
                            @foreach ($history['months'] as $m)
                                <div wire:key="fm-{{ $m['key'] }}" class="flex flex-col gap-2 border-t border-line py-4 last:pb-0">
                                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                        <div class="flex min-w-0 basis-1/2 flex-1 flex-col gap-1">
                                            <span class="text-t1 font-medium">{{ $m['title'] }}</span>
                                            <span class="text-t2 text-muted">{{ $m['sub'] }} · @if ($m['unpaid'])<x-ui.em :danger="$m['overdue']">{{ $m['status'] }}</x-ui.em>@else{{ $m['status'] }}@endif</span>
                                        </div>
                                        @if ($m['unpaid'])
                                            <div class="flex flex-wrap items-center gap-2">
                                                <x-ui.btn size="s" icon="mail" wire:click="openRemind('{{ $m['key'] }}')">Напомнить</x-ui.btn>
                                                <x-ui.btn size="s" icon="check" wire:click="markAllPaid('{{ $m['key'] }}')">Все внесли</x-ui.btn>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex flex-col">
                                        @foreach ($m['rows'] as $r)
                                            <div wire:key="fmr-{{ $r['id'] }}" class="flex items-center gap-4 py-2">
                                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                    <span class="truncate text-t1-s font-medium">{{ $r['name'] }}</span>
                                                    <span @class(['text-t2', 'font-semibold text-danger-fg' => $r['overdue'], 'text-muted' => ! $r['overdue']])>{{ $r['sub'] }}</span>
                                                </div>
                                                <span @class(['shrink-0 text-t1-s font-medium', 'text-muted' => $r['paid']])>{{ $r['amount'] }}</span>
                                                <x-ui.switch :checked="$r['paid']" :label="'Внесено: ' . $r['name'] . ', ' . $m['title']" wire:click="togglePaid({{ $r['id'] }})" />
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($history['hasMore'])
                            <div class="flex justify-end border-t border-line pt-4">
                                <x-ui.btn size="s" wire:click="moreHistory">Показать ещё</x-ui.btn>
                            </div>
                        @endif
                    @endif
                </x-ui.card>

                <x-ui.card class="min-w-0" aria-labelledby="f-totals">
                    <x-ui.card-head id="f-totals" title="Итоги" />
                    <x-ui.list>
                        @foreach ($history['totals'] as $t)
                            <div wire:key="ft-{{ $t['id'] }}" class="flex flex-col gap-1 border-t border-line py-4 first:border-t-0 first:pt-0 last:pb-0">
                                <span class="truncate text-t1 font-medium">{{ $t['name'] }}</span>
                                <span class="text-t2 text-muted">{{ $t['sub'] }}</span>
                                @if ($t['debt'])<span class="text-t2"><x-ui.em danger>{{ $t['debt'] }}</x-ui.em></span>@endif
                            </div>
                        @endforeach
                    </x-ui.list>
                </x-ui.card>
            </div>
        @endif
    @elseif ($tab === 'expenses')
        <x-ui.card aria-labelledby="f-exp">
            <x-ui.card-head id="f-exp" title="Постоянные расходы" />
            @if ($expenses->isEmpty())
                <p class="text-t2 text-muted">Пока пусто — добавьте сервер, домен и сервисы, за которые платим каждый месяц или раз в год.</p>
            @else
                <div>
                    <div class="hidden items-center gap-4 pb-3 text-t3 font-medium text-muted lg:flex">
                        <span class="flex-1">За что платим</span>
                        <span class="w-40 text-right">В месяц</span>
                        <span class="size-5"></span>
                    </div>
                    <x-ui.list>
                        @foreach ($expenses as $e)
                            <button type="button" wire:key="fe-{{ $e['id'] }}" wire:click="editExpense({{ $e['id'] }})" class="group flex items-center gap-4 border-t border-line py-4 text-left text-ink last:pb-0">
                                <span class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="flex min-w-0 flex-wrap items-center gap-2">
                                        <span @class(['truncate text-t1 font-medium group-hover:underline', 'text-muted' => ! $e['active']])>{{ $e['name'] }}</span>
                                        @unless ($e['active'])<x-ui.badge>Не учитывается</x-ui.badge>@endunless
                                    </span>
                                    @if ($e['sub'])<span class="text-t2 text-muted">{{ $e['sub'] }}</span>@endif
                                </span>
                                <span @class(['shrink-0 text-right text-t1 font-medium lg:w-40', 'text-muted line-through' => ! $e['active']])>{{ $e['monthly'] }}</span>
                                <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                            </button>
                        @endforeach
                    </x-ui.list>
                </div>
                <div class="flex items-baseline justify-between gap-4 border-t border-line pt-4">
                    <span class="text-t2 text-muted">Итого · {{ $money($total * 12) }} в год</span>
                    <span class="text-num font-medium">{{ $money($total) }}<span class="text-t2 font-normal text-muted"> в месяц</span></span>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card aria-labelledby="f-once">
            <x-ui.card-head id="f-once" title="Разовые расходы" />
            @if ($oneOffs->isEmpty())
                <p class="text-t2 text-muted">Пока пусто — покупка, которую оплачиваем один раз, делится по долям и собирается вместе с ближайшим или выбранным сбором.</p>
            @else
                <x-ui.list>
                    @foreach ($oneOffs as $e)
                        <button type="button" wire:key="fo-{{ $e['id'] }}" wire:click="editExpense({{ $e['id'] }})" class="group flex items-center gap-4 border-t border-line py-4 text-left text-ink first:border-t-0 first:pt-0 last:pb-0">
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="flex min-w-0 flex-wrap items-center gap-2">
                                    <span @class(['truncate text-t1 font-medium group-hover:underline', 'text-muted' => ! $e['active'] || $e['past']])>{{ $e['name'] }}</span>
                                    @unless ($e['active'])<x-ui.badge>Не учитывается</x-ui.badge>@endunless
                                </span>
                                @if ($e['sub'])<span class="text-t2 text-muted">{{ $e['sub'] }}</span>@endif
                            </span>
                            <span @class(['shrink-0 text-right text-t1 font-medium lg:w-40', 'text-muted' => $e['past'], 'line-through' => ! $e['active']])>{{ $e['amount'] }}</span>
                            <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                        </button>
                    @endforeach
                </x-ui.list>
            @endif
        </x-ui.card>
    @else
        <x-ui.card aria-labelledby="f-sh">
            <x-ui.card-head id="f-sh" title="Доли основателей" />
            @if ($founders->isEmpty())
                <p class="text-t2 text-muted">Пока пусто — добавьте основателей из пользователей сайта и укажите их доли.</p>
            @else
                <div>
                    <div class="hidden items-center gap-4 pb-3 text-t3 font-medium text-muted lg:flex">
                        <span class="flex-1">Основатель</span>
                        <span class="w-24 text-right">Доля</span>
                        <span class="w-40 text-right">Взнос в месяц</span>
                        <span class="size-5"></span>
                    </div>
                    <x-ui.list>
                        @foreach ($founders as $f)
                            <button type="button" wire:key="ff-{{ $f['id'] }}" wire:click="editFounder({{ $f['id'] }})" class="group flex items-center gap-4 border-t border-line py-4 text-left text-ink last:pb-0">
                                <span class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1 font-medium group-hover:underline">{{ $f['name'] }}</span>
                                    <span class="truncate text-t2 text-muted">@if ($f['unlinked'])<x-ui.em danger>{{ $f['unlinked'] }}</x-ui.em>@else{{ $f['sub'] }}@endif<span class="lg:hidden"> · {{ $f['share'] }}</span></span>
                                </span>
                                <span class="hidden w-24 shrink-0 text-right text-t1-s font-medium lg:block">{{ $f['share'] }}</span>
                                <span class="shrink-0 text-right text-t1 font-medium lg:w-40">{{ $f['amount'] }}</span>
                                <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                            </button>
                        @endforeach
                    </x-ui.list>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-4 border-t border-line pt-4">
                    <span class="text-t2 text-muted">Сумма долей</span>
                    @if ($sharesOk)
                        <span class="text-t1 font-medium">100 %</span>
                    @else
                        <span class="text-t2"><x-ui.em danger>{{ \App\Services\FounderService::percent($sharesTotal) }} — должно быть 100 %</x-ui.em></span>
                    @endif
                </div>
            @endif
        </x-ui.card>
    @endif

    @if ($expenseId !== null)
        <x-ui.modal :title="$expenseId ? 'Расход' : 'Новый расход'" :sub="$expensePeriod === 'once' ? 'Делится по долям и собирается отдельной строкой' : 'Во взносах годовой расход делится на 12'" close="closeExpense">
            <x-ui.field label="За что платим" name="expenseName" placeholder="Например, сервер" wire:model="expenseName" />
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-x-4">
                <x-ui.unit-field label="Цена" name="expenseAmount" unit="₽" inputmode="decimal" :hint="$expenseMonthly" wire:model.live.debounce.400ms="expenseAmount" />
            </div>
            <div class="flex min-w-0 flex-col gap-2">
                <span class="text-t2 font-medium">Как платим</span>
                <x-ui.seg :items="['month' => 'Каждый месяц', 'year' => 'Раз в год', 'once' => 'Один раз']" model="expensePeriod" :active="$expensePeriod" aria-label="Как платим" />
            </div>
            @if ($expensePeriod === 'once')
                <x-ui.select label="Скидываемся" name="expenseCharge" :options="$chargeOptions" wire:model.live="expenseCharge" />
            @endif
            <x-ui.field label="Заметка" name="expenseNote" optional placeholder="Где оплачиваем, с какой карты" wire:model="expenseNote" />
            <div class="flex items-start justify-between gap-4 rounded-lg p-4 shadow-line">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-t1-s font-medium">Учитывать во взносах</span>
                    <span class="text-t2 text-muted">{{ $expenseActive ? 'Расход делится между основателями по долям' : 'Расход остаётся в списке, но во взносы не входит' }}</span>
                </div>
                <x-ui.switch :checked="$expenseActive" label="Учитывать во взносах" wire:click="$toggle('expenseActive')" />
            </div>
            <x-slot:note>
                @if ($expenseId)<button type="button" wire:click="askDelete('expense')" class="link">Удалить расход</button>@endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeExpense">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveExpense">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($founderId !== null)
        <x-ui.modal :title="$founderId ? 'Основатель' : 'Новый основатель'" sub="От доли зависит, сколько он вносит на расходы" close="closeFounder">
            <div class="flex flex-col gap-2">
                <x-ui.person-select label="Профиль на сайте" name="founderUserId" :people="$founderUser['people']" :selected="$founderUserId" model="founderUserId" placeholder="Выберите пользователя" />
                @unless ($errors->has('founderUserId'))<span class="text-t3 text-muted">{{ $founderUser['hint'] }}</span>@endunless
            </div>
            <x-ui.unit-field label="Доля" name="founderShare" unit="%" inputmode="decimal" hint="Доли всех основателей в сумме — 100 %" wire:model="founderShare" />
            <x-slot:note>
                @if ($founderId)<button type="button" wire:click="askDelete('founder')" class="link">Удалить основателя</button>@endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeFounder">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveFounder">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($deleting)
        <x-ui.modal :title="$deleting === 'expense' ? 'Удалить расход?' : 'Удалить основателя?'" :sub="$deleting === 'expense' ? $expenseName : null" close="cancelDelete" width="s">
            <p class="text-t1-s">{{ $deleting === 'expense' ? 'Расход пропадёт из списка, невнесённые взносы пересчитаются.' : 'Вместе с основателем удалится история его взносов.' }}</p>
            <p class="text-t2 text-muted">Внесённые взносы не изменятся.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="cancelDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="confirmDelete">Удалить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($remind)
        <x-ui.modal :title="$remind['title']" sub="Письмо с суммой, сроком и списком расходов" close="closeRemind">
            @if ($remind['rows']->isEmpty())
                <p class="text-t1-s">Напоминать некому — за этот месяц все внесли.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach ($remind['rows'] as $r)
                        @if ($r['email'])
                            <x-ui.option :title="$r['name']" :sub="$r['sub']" value="{{ $r['id'] }}" wire:model="remindIds" wire:key="rm-{{ $r['id'] }}" />
                        @else
                            <div wire:key="rm-{{ $r['id'] }}" class="flex flex-col gap-1 rounded-lg px-4 py-3 shadow-line">
                                <span class="truncate text-t1-s font-medium text-muted">{{ $r['name'] }}</span>
                                <span class="text-t2 text-muted">{{ $r['sub'] }} — добавьте её на вкладке «Доли»</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
            <x-slot:note>Письмо уйдёт сразу, даже если сегодня уже напоминали</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeRemind">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" icon="send" wire:click="sendRemind" wire:loading.attr="disabled">Отправить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($settingsOpen)
        <x-ui.modal title="Настройки сбора" sub="Скидываемся раз в месяц" close="closeSettings">
            <x-ui.unit-field label="День сбора" name="day" hint="Число месяца. Если в месяце меньше дней — последний день" wire:model="day" />
            <div class="flex flex-col gap-4 border-t border-line pt-6">
                <div class="flex flex-col gap-1">
                    <span class="text-t1-s font-medium">Куда переводить</span>
                    <span class="text-t2 text-muted">Эти данные увидят основатели в письме и на своей странице сбора</span>
                </div>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-x-4">
                    <x-ui.field label="Номер телефона или карты" name="payNumber" wire:model="payNumber" />
                    <x-ui.field label="Банк" name="payBank" optional wire:model="payBank" />
                    <x-ui.field label="Получатель" name="payRecipient" optional wire:model="payRecipient" />
                    <x-ui.field label="Комментарий к переводу" name="payNote" optional wire:model="payNote" />
                </div>
            </div>
            <div class="flex items-start justify-between gap-4 rounded-lg p-4 shadow-line">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-t1-s font-medium">Напоминать на почту</span>
                    <span class="text-t2 text-muted">Тем, кто ещё не внёс: заранее, в день сбора и через {{ plural_ru(\App\Services\FounderService::OVERDUE_DAYS, 'день', 'дня', 'дней') }} после</span>
                </div>
                <x-ui.switch :checked="$reminders" label="Напоминать на почту" wire:click="$toggle('reminders')" />
            </div>
            @if ($reminders)
                <x-ui.unit-field label="Напомнить заранее за" name="remindDays" unit="дней" hint="0 — только в день сбора" wire:model="remindDays" />
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeSettings">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveSettings">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
