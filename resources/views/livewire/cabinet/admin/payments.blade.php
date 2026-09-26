{{-- Платежи учителей (макет AdminPayments): вкладки «Платежи» и «Подписки», окно платежа, подтверждение оплаты, возврат. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Платежи" :sub="$monthLine" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-col lg:flex-row lg:items-end">
            <x-ui.tabs class="flex-1" :items="$tabs" model="tab" :active="$tab" aria-label="Платежи и подписки" />
            <div class="pt-4 lg:border-b lg:border-line lg:pb-2 lg:pl-6 lg:pt-0">
                <x-ui.search wire:model.live.debounce.400ms="q" placeholder="Найти учителя" />
            </div>
        </div>

        @if ($tab === 'payments')
            {{-- Фокус: зависшие платежи — уведомление ЮKassa не пришло --}}
            @if ($queue->isNotEmpty())
                <x-ui.card focus aria-labelledby="pay-wait">
                    <x-ui.card-head id="pay-wait" title="Ожидают оплаты дольше суток">
                        <x-slot:action><span class="text-t1 font-medium">{{ $queueSum }}</span></x-slot:action>
                    </x-ui.card-head>
                    <x-ui.list>
                        @foreach ($queue as $r)
                            <button type="button" wire:key="wait-{{ $r['id'] }}" wire:click="openPayment({{ $r['id'] }})" class="group flex w-full items-center gap-4 border-t border-line py-4 text-left last:pb-0">
                                <x-ui.avatar :user="$r['user']" :name="$r['name']" />
                                <span class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1 font-medium group-hover:underline">{{ $r['name'] }}</span>
                                    <span class="text-t2 text-muted">{{ $r['meta'] }}<x-ui.em>{{ $r['age'] }}</x-ui.em></span>
                                </span>
                                <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                            </button>
                        @endforeach
                    </x-ui.list>
                </x-ui.card>
            @endif

            <x-ui.card aria-label="Все платежи">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.seg fit :items="$statuses" model="status" :active="$status" aria-label="Статус платежа" />
                    <x-ui.filter :label="$purposeLabel" :active="$purpose !== 'all'">
                        @foreach ($purposes as $key => $label)
                            <x-ui.menu-item wire:click="setPurpose('{{ $key }}')" class="justify-between gap-3">
                                <span class="truncate">{{ $label }}</span>@if ((string) $purpose === (string) $key)<x-ui.icon name="check" size="s" />@endif
                            </x-ui.menu-item>
                        @endforeach
                    </x-ui.filter>
                    <x-ui.filter :label="$periodLabel" :active="$period !== 'month'">
                        @foreach ($periods as $key => $label)
                            <x-ui.menu-item wire:click="setPeriod('{{ $key }}')" class="justify-between gap-3">
                                <span class="truncate">{{ $label }}</span>@if ($period === $key)<x-ui.icon name="check" size="s" />@endif
                            </x-ui.menu-item>
                        @endforeach
                    </x-ui.filter>
                    @if ($hasReset)
                        <button type="button" wire:click="resetFilters" class="link ml-2 text-t2">Сбросить</button>
                    @endif
                </div>

                @if ($rows->isEmpty())
                    <p class="border-t border-line pt-4 text-t2 text-muted">
                        @if ($hasReset || $q !== '')
                            Таких платежей нет. <button type="button" wire:click="resetFilters" class="link">Сбросить фильтры</button>
                        @else
                            Пока пусто — платежи появятся, когда учителя начнут оплачивать тарифы.
                        @endif
                    </p>
                @else
                    <div>
                        <div class="hidden items-center gap-4 pb-3 text-t3 font-medium text-muted lg:flex">
                            <span class="flex-1">Учитель и назначение</span>
                            <span class="w-40 text-right">Сумма</span>
                            <span class="w-40">Когда</span>
                            <span class="w-40"></span>
                            <span class="size-5"></span>
                        </div>
                        <x-ui.list>
                            @foreach ($rows as $r)
                                <button type="button" wire:key="pay-{{ $r['id'] }}" wire:click="openPayment({{ $r['id'] }})" class="group flex w-full items-center gap-4 border-t border-line py-4 text-left last:pb-0">
                                    <x-ui.avatar :user="$r['user']" :name="$r['name']" />
                                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1 font-medium group-hover:underline">{{ $r['name'] }}</span>
                                        <span class="truncate text-t2 text-muted">{{ $r['what'] }}</span>
                                        <span class="text-t2 text-muted lg:hidden">{{ $r['when'] }}</span>
                                    </span>
                                    <span class="flex shrink-0 flex-col items-end gap-1 lg:w-40">
                                        <span @class(['whitespace-nowrap text-t1 font-medium', 'text-muted line-through' => $r['off']])>{{ $r['amount'] }}</span>
                                        <span class="lg:hidden">@include('livewire.cabinet.admin.partials.payment-status', ['status' => $r['status']])</span>
                                    </span>
                                    <span class="hidden w-40 shrink-0 text-t1-s lg:block">{{ $r['when'] }}</span>
                                    <span class="hidden w-40 shrink-0 lg:flex">@include('livewire.cabinet.admin.partials.payment-status', ['status' => $r['status']])</span>
                                    <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                                </button>
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
        @else
            {{-- Подписки учителей — только просмотр --}}
            <x-ui.card aria-label="Подписки учителей">
                <x-ui.seg fit :items="$segments" model="sub" :active="$sub" aria-label="Какие подписки показать" />

                @if ($subs->isEmpty())
                    <p class="border-t border-line pt-4 text-t2 text-muted">{{ $q !== '' ? 'Никого не нашлось — проверьте имя.' : 'Пока пусто — здесь появятся подписки учителей.' }}</p>
                @else
                    <div>
                        <div class="hidden items-center gap-4 pb-3 text-t3 font-medium text-muted lg:flex">
                            <span class="flex-1">Учитель</span>
                            <span class="w-40">Тариф</span>
                            <span class="w-44">Действует</span>
                            <span class="w-40">Докуплено</span>
                            <span class="w-16"></span>
                        </div>
                        <x-ui.list>
                            @foreach ($subs as $s)
                                <a href="{{ $s['url'] }}" wire:key="sub-{{ $s['id'] }}" class="group flex items-center gap-4 border-t border-line py-4 text-ink last:pb-0">
                                    <x-ui.avatar :user="$s['user']" />
                                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1 font-medium group-hover:underline">{{ $s['user']?->name }}</span>
                                        <span class="text-t2 text-muted lg:hidden">{{ $s['tariff'] }} · @if ($s['soon'])<x-ui.em>{{ $s['until'] }}</x-ui.em>@else{{ $s['until'] }}@endif</span>
                                    </span>
                                    <span class="hidden w-40 shrink-0 flex-col gap-1 lg:flex">
                                        <span class="text-t1-s font-medium">{{ $s['tariff'] }}</span>
                                        <span class="text-t2 text-muted">{{ $s['price'] }}</span>
                                    </span>
                                    <span class="hidden w-44 shrink-0 flex-col items-start gap-1 lg:flex">
                                        <span @class(['text-t1-s', 'font-semibold' => $s['soon']])>{{ $s['until'] }}</span>
                                        @if ($s['gift'])<x-ui.badge>Предоставлен бесплатно</x-ui.badge>@endif
                                        @if ($s['note'])<span class="text-t2 text-muted">{{ $s['note'] }}</span>@endif
                                    </span>
                                    <span class="hidden w-40 shrink-0 text-t1-s lg:block">{{ $s['extra'] }}</span>
                                    <span class="link w-16 shrink-0 text-right text-t2">Изменить</span>
                                </a>
                            @endforeach
                        </x-ui.list>
                    </div>
                    @if ($total > $subs->count())
                        <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                            <span class="text-t2 text-muted">Показаны {{ $subs->count() }} из {{ $total }}</span>
                            <x-ui.btn size="s" wire:click="more">Показать ещё</x-ui.btn>
                        </div>
                    @endif
                @endif
                <p class="border-t border-line pt-4 text-t2 text-muted">Назначить или изменить тариф можно в карточке учителя.</p>
            </x-ui.card>
        @endif
    </div>

    {{-- Окно платежа --}}
    @if ($cur && $modal === 'details')
        <x-ui.modal :title="$cur['what']" :sub="$cur['headSub']" close="closeModal">
            <dl class="flex flex-col">
                <div class="flex flex-col gap-1 pb-3 lg:flex-row lg:items-center lg:gap-4">
                    <dt class="shrink-0 text-t2 font-medium text-muted lg:w-40">Сумма</dt>
                    <dd class="flex min-w-0 flex-1 items-center justify-between gap-4">
                        <span class="text-h2 font-medium">{{ $cur['amount'] }}</span>
                        @include('livewire.cabinet.admin.partials.payment-status', ['status' => $cur['status']])
                    </dd>
                </div>
                @foreach ([[$cur['stateLabel'], $cur['stateText']], ['Учитель', null], ['Что даёт', $cur['gives']], ['Способ оплаты', $cur['method']], ['Платёж в ЮKassa', null]] as [$label, $value])
                    <div class="flex flex-col gap-1 border-t border-line py-3 last:pb-0 lg:flex-row lg:items-center lg:gap-4">
                        <dt class="shrink-0 text-t2 font-medium text-muted lg:w-40">{{ $label }}</dt>
                        <dd class="flex min-w-0 flex-1 items-center justify-between gap-4 text-t1-s font-medium">
                            @if ($label === 'Учитель')
                                <span class="truncate">{{ $cur['name'] }}</span>
                                <a href="{{ $cur['userUrl'] }}" class="link shrink-0 text-t2">Карточка учителя</a>
                            @elseif ($label === 'Платёж в ЮKassa')
                                @if ($cur['yk'])
                                    <span class="flex min-w-0 flex-1 items-center gap-2">
                                        <input type="text" readonly value="{{ $cur['yk'] }}" class="field h-9 min-w-0 flex-1 text-t2 text-muted" aria-label="Номер платежа в ЮKassa" x-data x-on:focus="$el.select()">
                                        <x-ui.copy :value="$cur['yk']" message="Номер платежа скопирован" size="s" />
                                    </span>
                                @else
                                    <span class="text-muted">Нет номера — платёж создан вручную</span>
                                @endif
                            @else
                                <span>{{ $value }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
            @if ($cur['note'])<p class="text-t2 text-muted">{{ $cur['note'] }}</p>@endif
            <x-slot:note>
                @if ($cur['receiptUrl'])<a href="{{ $cur['receiptUrl'] }}" target="_blank" rel="noopener" class="link">Квитанция</a>@endif
            </x-slot:note>
            <x-slot:footer>
                @if ($cur['canRefund'])<x-ui.btn wire:click="openRefund">Оформить возврат</x-ui.btn>@endif
                @if ($cur['canConfirm'])<x-ui.btn variant="primary" wire:click="openConfirm">Подтвердить оплату</x-ui.btn>@endif
                @if (! $cur['canRefund'] && ! $cur['canConfirm'])<x-ui.btn wire:click="closeModal">Закрыть</x-ui.btn>@endif
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Ручное подтверждение оплаты --}}
    @if ($cur && $modal === 'confirm')
        <x-ui.modal title="Подтвердить оплату?" :sub="$cur['confirmSub']" close="closeModal" width="s">
            <p class="text-t1-s">{{ $cur['confirmEffect'] }}</p>
            <p class="text-t1-s"><x-ui.em>Подтверждайте, только если оплата видна в личном кабинете ЮKassa.</x-ui.em> Если денег там нет, учитель получит тариф бесплатно.</p>
            <x-ui.option wire:model.live="checked" title="Оплата видна в ЮKassa" :sub="$cur['chkSub']" />
            <x-slot:note><a href="https://yookassa.ru/my/payments" target="_blank" rel="noopener" class="link">Открыть ЮKassa</a></x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="backToDetails">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="confirmPayment" wire:loading.attr="disabled" :disabled="! $checked">Подтвердить оплату</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Возврат --}}
    @if ($cur && $modal === 'refund')
        <x-ui.modal title="Оформить возврат?" :sub="$cur['refundSub']" close="closeModal" width="s">
            <div class="flex items-baseline justify-between gap-4">
                <span class="text-t1 font-medium">Вернём</span>
                <span class="text-h2 font-medium">{{ $cur['amount'] }}</span>
            </div>
            <p class="text-t1-s">{{ $cur['refundEffect'] }}</p>
            <p class="text-t1-s">{{ $cur['refundMoney'] }} <x-ui.em>Отменить возврат нельзя.</x-ui.em></p>
            <x-slot:footer>
                <x-ui.btn wire:click="backToDetails">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="refundPayment" wire:loading.attr="disabled">Вернуть {{ $cur['amount'] }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
