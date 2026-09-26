{{-- Партнёрская программа (макет AdminReferrals): начисления, сводка за месяц, кто чаще приглашает, окно настроек. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Партнёрская программа" :sub="$factLine">
        <x-slot:actions><x-ui.btn icon="settings" wire:click="openSettings">Настройки программы</x-ui.btn></x-slot:actions>
    </x-ui.page-head>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <x-ui.card class="min-w-0 lg:col-span-2" aria-labelledby="r-log">
            <x-ui.card-head id="r-log" title="Начисления" class="flex-wrap">
                <x-slot:action><x-ui.search wire:model.live.debounce.400ms="q" placeholder="Имя или почта" /></x-slot:action>
            </x-ui.card-head>
            <x-ui.seg fit :items="$filters" model="filter" :active="$filter" aria-label="Какие начисления показать" />

            @if ($rows->isEmpty())
                <p class="border-t border-line pt-4 text-t2 text-muted">{{ $filter === 'all' && $q === '' ? 'Пока пусто — начисления появятся, когда приглашённые учителя оплатят тариф.' : 'Таких начислений нет.' }}</p>
            @else
                <div>
                    <div class="hidden items-center gap-4 pb-3 text-t3 font-medium text-muted lg:flex">
                        <span class="flex-1">Кто пригласил и кого</span>
                        <span class="w-16 text-right lg:w-40">Пригласившему</span>
                        <span class="w-16 text-right lg:w-40">Приглашённому</span>
                    </div>
                    <x-ui.list>
                        @foreach ($rows as $r)
                            <div wire:key="rw-{{ $r['id'] }}" class="flex items-center gap-4 border-t border-line py-4 last:pb-0">
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                                        <a href="{{ $r['fromUrl'] }}" class="truncate text-t1 font-medium hover:underline">{{ $r['from'] }}</a>
                                        <x-ui.icon name="chevron-right" size="s" class="text-muted" />
                                        <a href="{{ $r['toUrl'] }}" class="truncate text-t1 font-medium hover:underline">{{ $r['to'] }}</a>
                                    </div>
                                    <span class="truncate text-t2 text-muted">{{ $r['meta'] }}</span>
                                    @if ($r['badge'])
                                        <div class="flex min-w-0 flex-wrap items-center gap-2 pt-1">
                                            <x-ui.badge :tone="$r['status'] === 'rejected' ? 'danger' : 'neutral'">{{ $r['badge'] }}</x-ui.badge>
                                            @if ($r['note'])<span class="text-t2 text-muted">{{ $r['note'] }}</span>@endif
                                        </div>
                                    @endif
                                </div>
                                <span @class(['w-16 shrink-0 text-right text-t1-s font-medium lg:w-40', 'text-muted line-through' => $r['aOff']])>{{ $r['a'] }}</span>
                                <span @class(['w-16 shrink-0 text-right text-t1-s font-medium lg:w-40', 'text-muted line-through' => $r['bOff']])>{{ $r['b'] }}</span>
                            </div>
                        @endforeach
                    </x-ui.list>
                </div>
                @if ($total > $rows->count())
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                        <span class="text-t2 text-muted">Показаны {{ $rows->count() }} из {{ $total }}</span>
                        <x-ui.btn size="s" wire:click="more">Показать ещё</x-ui.btn>
                    </div>
                @endif
            @endif
        </x-ui.card>

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card focus aria-labelledby="r-sum">
                <x-ui.card-head id="r-sum" :title="$month" />
                <div class="flex flex-col">
                    @foreach (['Пришли по приглашению' => $summary['joined'], 'Оплатили тариф' => $summary['paid'], 'Начислено занятий' => $summary['toReferrers'] + $summary['toReferred']] as $label => $value)
                        <div class="flex items-baseline justify-between gap-4 border-t border-line py-3 first:border-t-0 first:pt-0 last:pb-0">
                            <span class="text-t2 text-muted">{{ $label }}</span>
                            <span class="text-t1 font-medium">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($summary['toReferrers'] + $summary['toReferred'] > 0)
                    <span class="text-t2 text-muted">{{ $summary['toReferrers'] }} — пригласившим, {{ $summary['toReferred'] }} — приглашённым</span>
                @endif
            </x-ui.card>

            <x-ui.card aria-labelledby="r-top">
                <x-ui.card-head id="r-top" title="Чаще всех приглашают" />
                @if ($top->isEmpty())
                    <p class="text-t2 text-muted">Пока никто не приглашал коллег.</p>
                @else
                    <x-ui.list>
                        @foreach ($top as $p)
                            <x-ui.row :href="\App\Livewire\Cabinet\Admin\Payments::userUrl($p['user']->id)" wire:key="top-{{ $p['user']->id }}">
                                <x-ui.avatar :user="$p['user']" />
                                <x-ui.text :title="$p['user']->name" :sub="plural_ru($p['invited'], 'коллега', 'коллеги', 'коллег') . ($p['lessons'] ? ' · +' . $lessons($p['lessons']) : '')" />
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
        </div>
    </div>

    @if ($settingsOpen)
        <x-ui.modal title="Настройки программы" sub="Бонусы — дополнительные занятия, они не сгорают" close="closeSettings">
            <div class="flex items-start justify-between gap-4 rounded-lg p-4 shadow-line">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-t1-s font-medium">Программа включена</span>
                    <span class="text-t2 text-muted">{{ $on ? 'Учителя видят страницу «Пригласить коллегу» и получают бонусы' : 'Страница приглашений скроется, новые бонусы не начислятся. Начисленное останется' }}</span>
                </div>
                <x-ui.switch :checked="$on" label="Программа включена" wire:click="$toggle('on')" />
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-x-4">
                <x-ui.unit-field label="Бонус пригласившему" name="bonusReferrer" unit="занятий" hint="За первую оплату тарифа коллегой. У тарифа может быть свой" wire:model="bonusReferrer" :disabled="! $on" />
                <x-ui.unit-field label="Бонус приглашённому" name="bonusReferred" unit="занятий" hint="После его первой оплаты тарифа. 0 — без подарка" wire:model="bonusReferred" :disabled="! $on" />
                <x-ui.unit-field label="Начислений пригласившему в месяц" name="monthlyLimit" hint="Сверх лимита бонус получит только приглашённый. 0 — без лимита" wire:model="monthlyLimit" :disabled="! $on" />
                <x-ui.unit-field label="Ссылка действует" name="cookieDays" unit="дней" hint="Столько помним приглашение, если коллега подаст заявку не сразу" wire:model="cookieDays" :disabled="! $on" />
            </div>
            <div class="flex flex-col gap-4 border-t border-line pt-6">
                <div class="flex items-start justify-between gap-4 rounded-lg p-4 shadow-line">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="text-t1-s font-medium">Баннер в кабинете учителя</span>
                        <span class="text-t2 text-muted">Приглашение на главной; учитель может его закрыть</span>
                    </div>
                    <x-ui.switch :checked="$banner && $on" label="Баннер в кабинете учителя" wire:click="toggleBanner" />
                </div>
                @if ($banner && $on)
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-x-4">
                        <x-ui.unit-field label="Показать через" name="bannerDelay" unit="дней" hint="После регистрации, чтобы новичок освоился. 0 — сразу" wire:model="bannerDelay" />
                        <x-ui.unit-field label="После закрытия скрыть на" name="bannerSnooze" unit="дней" wire:model="bannerSnooze" />
                    </div>
                @endif
            </div>
            <x-slot:note>Начисленные бонусы изменения не затронут</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeSettings">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveSettings">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
