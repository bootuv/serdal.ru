<x-filament-panels::page>
    <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-2">
    {{-- Текущая подписка --}}
    <x-filament::section class="h-full">
        <x-slot name="heading">Текущий тариф</x-slot>

        @if($subscription)
            @php($limit = $subscription->tariff->lessons_per_month)
            @php($percent = $limit ? min(100, (int) round($lessonsUsed / $limit * 100)) : 0)
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xl font-bold text-gray-950 dark:text-white">
                        {{ $subscription->tariff->name }}
                        @if($subscription->tariff->isFree())
                            <x-filament::badge color="gray" class="ml-1 inline-flex">Бесплатный</x-filament::badge>
                        @elseif($subscription->isComplimentary())
                            <x-filament::badge color="success" class="ml-1 inline-flex">Предоставлен бесплатно</x-filament::badge>
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        @if($subscription->ends_at)
                            {{ $subscription->isComplimentary() ? 'Действует до' : 'Оплачен до' }}
                            {{ $subscription->ends_at->format('d.m.Y') }}
                            (осталось {{ max(0, (int) now()->diffInDays($subscription->ends_at, false)) }} дн.)
                        @else
                            Действует бессрочно
                        @endif
                    </p>
                    @if($scheduled)
                        <p class="mt-2 text-sm text-amber-600 dark:text-amber-400">
                            {{ $scheduled->starts_at->format('d.m.Y') }} будет подключён тариф
                            «{{ $scheduled->tariff->name }}» — после окончания оплаченного периода.
                        </p>
                    @endif
                </div>
                <div class="shrink-0 text-right">
                    @if($subscription->isComplimentary())
                        <p class="text-2xl font-bold text-gray-950 dark:text-white">
                            <span class="line-through">{{ number_format($subscription->tariff->price, 0, ',', ' ') }} ₽</span><span
                                class="text-sm font-normal text-gray-500">/мес</span>
                        </p>
                        <p class="mt-1 text-sm text-green-600 dark:text-green-400">Оплата не требуется</p>
                    @else
                        <p class="text-2xl font-bold text-gray-950 dark:text-white">
                            {{ number_format($subscription->tariff->price, 0, ',', ' ') }} ₽<span
                                class="text-sm font-normal text-gray-500">/мес</span>
                        </p>
                        @if($showRenew)
                            <div class="mt-2">
                                {{ ($this->selectTariffAction)(['tariff' => $subscription->tariff_id, 'renew' => true, 'primary' => true]) }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Занятия в периоде --}}
            <div class="mt-4 border-t border-gray-200 pt-4 dark:border-white/10">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">Занятий в этом периоде</span>
                    <span @class([
                        'font-semibold',
                        'text-danger-600 dark:text-danger-400' => $limitReached,
                        'text-gray-950 dark:text-white' => !$limitReached,
                    ])>
                        {{ $lessonsUsed }}@if($limit) из {{ $limit }}@endif
                        @if($extraBalance > 0)
                            <span class="font-normal text-gray-500 dark:text-gray-400">+ {{ $extraBalance }} докупл.</span>
                        @endif
                    </span>
                </div>
                @if($limit)
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div @class([
                            'h-full rounded-full',
                            'bg-danger-500' => $limitReached,
                            'bg-primary-500' => !$limitReached,
                        ]) style="width: {{ $percent }}%"></div>
                    </div>
                    <p @class([
                        'mt-2 text-xs',
                        'text-danger-600 dark:text-danger-400' => $limitReached && $extraBalance <= 0,
                        'text-gray-500 dark:text-gray-400' => !$limitReached || $extraBalance > 0,
                    ])>
                        @if($limitReached)
                            Лимит тарифа исчерпан{{ $periodResetsAt ? ', обновится ' . $periodResetsAt->format('d.m.Y') : '' }}.
                            @if($extraBalance > 0)
                                Занятия проводятся за счёт докупленных.
                            @else
                                Чтобы проводить занятия сейчас — докупите их или перейдите на тариф выше.
                            @endif
                        @else
                            @if($periodResetsAt) Лимит обновится {{ $periodResetsAt->format('d.m.Y') }}. @endif
                            @if($extraBalance > 0)
                                Докупленные занятия не сгорают и расходуются после лимита.
                            @endif
                        @endif
                    </p>
                    @if($canBuyExtra)
                        <div class="mt-3">
                            {{ ($this->buyExtraLessonsAction)(['primary' => $limitReached && $extraBalance <= 0]) }}
                        </div>
                    @endif
                @endif
            </div>
        @elseif($expired)
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xl font-bold text-gray-950 dark:text-white">
                        {{ $expired->tariff->name }}
                        <x-filament::badge color="danger" class="ml-1 inline-flex">Срок истёк</x-filament::badge>
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        @if($expired->ends_at)
                            Действовал до {{ $expired->ends_at->format('d.m.Y') }}
                            ({{ $expired->ends_at->diffForHumans() }}).
                        @endif
                        Продлите подписку, чтобы продолжить проводить занятия.
                    </p>
                    @if($extraBalance > 0)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Докупленных занятий на балансе:
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $extraBalance }}</span>
                            — они сохранятся и после продления.
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">
                        {{ number_format($expired->tariff->price, 0, ',', ' ') }} ₽<span
                            class="text-sm font-normal text-gray-500">/мес</span>
                    </p>
                    <div class="mt-2">
                        {{ ($this->selectTariffAction)(['tariff' => $expired->tariff_id, 'renew' => true, 'primary' => true]) }}
                    </div>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">
                У вас пока нет активной подписки. Выберите тариф ниже — бесплатный тариф «Старт» подключается в один клик.
            </p>
        @endif
    </x-filament::section>

    {{-- Способ оплаты и автопродление --}}
    <x-filament::section class="h-full">
        <x-slot name="heading">Способ оплаты</x-slot>

        @if(auth()->user()->yookassa_payment_method_id)
            @php($autoRenew = (bool) auth()->user()->auto_renew)
            <div class="flex h-full flex-col gap-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-credit-card class="mt-0.5 h-6 w-6 shrink-0 text-gray-400" />
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ auth()->user()->payment_method_title ?? 'Сохранённый способ оплаты' }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Оплата проходит в один клик, без повторного подтверждения в банке.
                        </p>
                    </div>
                </div>

                {{-- Автопродление: описание слева, переключатель справа --}}
                <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">Автопродление</p>
                        <p class="mt-0.5 text-sm {{ $autoRenew ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">
                            @if($autoRenew)
                                Подписка продлится автоматически в конце оплаченного периода — предупредим о списании заранее.
                            @else
                                Выключено — в конце периода подписку нужно будет продлить вручную.
                            @endif
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-checked="{{ $autoRenew ? 'true' : 'false' }}"
                        aria-label="Автопродление"
                        wire:click="toggleAutoRenew"
                        wire:loading.attr="disabled"
                        wire:target="toggleAutoRenew"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70 dark:focus:ring-offset-gray-900 {{ $autoRenew ? 'bg-primary-600' : 'bg-gray-200 dark:bg-white/10' }}"
                    >
                        <span
                            aria-hidden="true"
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $autoRenew ? 'translate-x-5' : 'translate-x-0' }}"
                        ></span>
                    </button>
                </div>

                <div class="mt-auto">
                    {{ $this->removeCardAction }}
                </div>
            </div>
        @else
            <div class="flex h-full flex-col gap-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-credit-card class="mt-0.5 h-6 w-6 shrink-0 text-gray-400" />
                    <div>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">Способ оплаты не сохранён</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            @if(\App\Services\YooKassaService::recurringEnabled())
                                Привяжите способ оплаты — карту, СБП, SberPay, T-Pay или ЮMoney, — чтобы оплачивать
                                в один клик и включить автопродление. Также его можно сохранить при оплате тарифа.
                            @else
                                Оплата в один клик и автопродление скоро станут доступны.
                            @endif
                        </p>
                    </div>
                </div>
                @if(\App\Services\YooKassaService::recurringEnabled())
                    <div class="mt-auto">
                        {{ $this->bindCardAction }}
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>
    </div>

    {{-- Выбор тарифа --}}
    <x-filament::section>
        <x-slot name="heading">Тарифы</x-slot>

        @if($hasYearly)
            {{-- Переключатель периода оплаты --}}
            <div class="mb-5 flex items-center gap-2">
                <div class="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-white/5">
                    <button type="button" wire:click="$set('billingPeriod', 'month')" @class([
                        'rounded-md px-4 py-1.5 text-sm font-medium transition',
                        'bg-white shadow text-gray-950 dark:bg-gray-800 dark:text-white' => $billingPeriod === 'month',
                        'text-gray-500 dark:text-gray-400' => $billingPeriod !== 'month',
                    ])>
                        Помесячно
                    </button>
                    <button type="button" wire:click="$set('billingPeriod', 'year')" @class([
                        'rounded-md px-4 py-1.5 text-sm font-medium transition',
                        'bg-white shadow text-gray-950 dark:bg-gray-800 dark:text-white' => $billingPeriod === 'year',
                        'text-gray-500 dark:text-gray-400' => $billingPeriod !== 'year',
                    ])>
                        На год
                    </button>
                </div>
                @php($maxDiscount = $tariffs->max(fn($t) => $t->yearlyDiscountPercent()))
                @if($maxDiscount > 0)
                    <x-filament::badge color="success">выгода до {{ $maxDiscount }}%</x-filament::badge>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($tariffs as $tariff)
                @php($isCurrent = $subscription && $subscription->tariff_id === $tariff->id)
                @php($isExpired = $expired && $expired->tariff_id === $tariff->id)
                <div @class([
                    'flex flex-col rounded-xl p-5 ring-1',
                    'ring-primary-500 bg-primary-50 dark:bg-primary-500/10' => $isCurrent,
                    'ring-danger-400 bg-danger-50 dark:bg-danger-500/10' => $isExpired,
                    'ring-gray-200 dark:ring-white/10' => !$isCurrent && !$isExpired,
                ])>
                    <div class="flex items-center justify-between">
                        <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $tariff->name }}</p>
                        @if($isExpired)
                            <x-filament::badge color="danger">Срок истёк</x-filament::badge>
                        @elseif($tariff->is_popular)
                            <x-filament::badge color="warning">Популярный</x-filament::badge>
                        @endif
                    </div>
                    @if($billingPeriod === 'year' && $tariff->hasYearly())
                        <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                            {{ number_format($tariff->yearly_price, 0, ',', ' ') }} ₽<span
                                class="text-sm font-normal text-gray-500">/год</span>
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            ≈ {{ number_format((int) round($tariff->yearly_price / 12), 0, ',', ' ') }} ₽/мес
                            @if($tariff->yearlyDiscountPercent() > 0)
                                <span class="font-medium text-green-600 dark:text-green-400">−{{ $tariff->yearlyDiscountPercent() }}%</span>
                            @endif
                        </p>
                    @else
                        <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                            {{ $tariff->isFree() ? '0 ₽' : number_format($tariff->price, 0, ',', ' ') . ' ₽' }}<span
                                class="text-sm font-normal text-gray-500">/мес</span>
                        </p>
                    @endif
                    {{-- Лимиты тарифа --}}
                    <ul class="mt-4 space-y-2">
                        <li class="flex items-start gap-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                            <x-heroicon-m-user-group class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                            {{ $tariff->participants_label }}
                        </li>
                        <li class="flex items-start gap-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                            <x-heroicon-m-calendar-days class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                            {{ $tariff->lessons_label }}
                        </li>
                        <li class="flex items-start gap-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                            <x-heroicon-m-clock class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                            {{ $tariff->duration_label }}
                        </li>
                        <li class="flex items-start gap-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                            <x-heroicon-m-video-camera class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                            {{ $tariff->recording_label }}
                        </li>
                    </ul>

                    {{-- Возможности тарифа: свёрнуты по умолчанию, чтобы карточки не растягивались --}}
                    @php($featuresCount = count($tariff->features ?? []) + count($tariff->extra_features ?? []))
                    @if($featuresCount > 0)
                        <div
                            x-data="{ open: false }"
                            class="mt-3 border-t border-gray-200 pt-3 dark:border-white/10"
                        >
                            <button
                                type="button"
                                x-on:click="open = !open"
                                x-bind:aria-expanded="open"
                                class="flex w-full items-center justify-between gap-2 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400"
                            >
                                <span x-text="open ? 'Скрыть возможности' : 'Все возможности ({{ $featuresCount }})'">Все возможности ({{ $featuresCount }})</span>
                                <x-heroicon-m-chevron-down
                                    class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200"
                                    x-bind:class="{ 'rotate-180': open }"
                                />
                            </button>
                            <div x-show="open" x-collapse x-cloak>
                                {{-- Дополнительные возможности --}}
                                <ul class="mt-3 space-y-2">
                                    @foreach($tariff->features ?? [] as $feature)
                                        <li class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300">
                                            <x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                                            {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                                {{-- Доп. сервисы тарифа --}}
                                @if(!empty($tariff->extra_features))
                                    <ul class="mt-3 space-y-2 border-t border-gray-200 pt-3 dark:border-white/10">
                                        @foreach($tariff->extra_features as $feature)
                                            <li class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300">
                                                <x-heroicon-m-star class="mt-0.5 h-4 w-4 shrink-0 text-amber-400" />
                                                {{ $feature }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div class="mt-auto pt-5">
                        @if($isCurrent)
                            <x-filament::button color="gray" disabled class="w-full justify-center">
                                Подключён
                            </x-filament::button>
                        @elseif($scheduled && $scheduled->tariff_id === $tariff->id)
                            <x-filament::button color="gray" disabled class="w-full justify-center">
                                Подключится {{ $scheduled->starts_at->format('d.m.Y') }}
                            </x-filament::button>
                        @elseif($isExpired)
                            {{ ($this->selectTariffAction)(['tariff' => $tariff->id, 'renew' => true, 'primary' => true]) }}
                        @else
                            {{ ($this->selectTariffAction)(['tariff' => $tariff->id, 'primary' => $primaryTariffId === $tariff->id]) }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Оплата картой или через СБП с помощью ЮKassa. Подписка активируется сразу после оплаты.
            Условия возврата — в <a href="{{ route('offer') }}" target="_blank" class="underline">публичной оферте</a>.
        </p>
    </x-filament::section>

    {{-- История платежей --}}
    @if($payments->isNotEmpty())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">История платежей</x-slot>

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($payments as $payment)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">
                                @if(!empty($payment->meta['card_binding']))
                                    Привязка способа оплаты — 1 ₽ (возвращается)
                                @elseif($payment->isExtraLessons())
                                    {{ $payment->title }} — {{ number_format($payment->amount, 0, ',', ' ') }} ₽
                                @else
                                    Тариф «{{ $payment->tariff->name }}» — {{ number_format($payment->amount, 0, ',', ' ') }} ₽{{ $payment->period_days >= 365 ? ' (за год)' : '' }}
                                @endif
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $payment->created_at->format('d.m.Y H:i') }}
                            </p>
                            @if($payment->status === \App\Models\SubscriptionPayment::STATUS_REFUNDED)
                                <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                                    @if(!empty($payment->meta['card_binding']))
                                        Проверочный 1 ₽ возвращён.
                                    @else
                                        Возврат оформлен {{ !empty($payment->meta['refunded_at']) ? \Illuminate\Support\Carbon::parse($payment->meta['refunded_at'])->format('d.m.Y') : $payment->updated_at->format('d.m.Y') }}.
                                        Средства вернутся на карту, с которой была оплата, в течение {{ $refundProcessingDays }} рабочих дней.
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @switch($payment->status)
                                @case(\App\Models\SubscriptionPayment::STATUS_PAID)
                                    <x-filament::badge color="success">Оплачен</x-filament::badge>
                                    @break

                                @case(\App\Models\SubscriptionPayment::STATUS_PENDING)
                                    @if($payment->isResumable())
                                        {{-- Ссылка на оплату живёт ~час с момента создания платежа --}}
                                        <div class="flex items-center gap-2"
                                            x-data="{ left: {{ max(0, (int) now()->diffInSeconds($payment->created_at->copy()->addHour(), false)) }} }"
                                            x-init="setInterval(() => { if (left > 0) left-- }, 1000)">
                                            <template x-if="left > 0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs text-gray-500 dark:text-gray-400"
                                                        x-text="'ссылка действует ещё ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')"></span>
                                                    <x-filament::button size="sm" color="warning" outlined tag="a"
                                                        href="{{ $payment->payment_url }}">
                                                        Оплатить
                                                    </x-filament::button>
                                                </div>
                                            </template>
                                            <template x-if="left <= 0">
                                                <x-filament::badge color="gray">Срок оплаты истёк</x-filament::badge>
                                            </template>
                                        </div>
                                    @else
                                        <x-filament::badge color="warning">Ожидает оплаты</x-filament::badge>
                                    @endif
                                    @break

                                @case(\App\Models\SubscriptionPayment::STATUS_REFUNDED)
                                    <x-filament::badge color="info">Возврат оформлен</x-filament::badge>
                                    @break

                                @default
                                    <x-filament::badge color="danger">Не прошёл</x-filament::badge>
                            @endswitch
                        </div>
                    </div>
                @endforeach
            </div>

            <a href="{{ \App\Filament\App\Pages\PaymentHistory::getUrl(panel: 'app') }}"
                class="mt-3 inline-block text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                Все платежи и квитанции →
            </a>
        </x-filament::section>
    @endif
</x-filament-panels::page>
