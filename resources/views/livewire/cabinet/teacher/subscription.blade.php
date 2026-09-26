@php
    use App\Models\SubscriptionPayment;
    use App\Support\HumanDate;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' ₽';
    $limit = $subscription?->tariff->lessons_per_month;
    $daysLeft = $subscription?->ends_at ? max(0, (int) now()->diffInDays($subscription->ends_at, false)) : null;
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Тариф и платежи" :back="$profileUrl" back-label="Профиль и цены" />

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        {{-- Фокус: текущий тариф и лимиты --}}
        <x-ui.card focus class="lg:col-span-2" aria-labelledby="s-cur">
            @if ($subscription)
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="s-cur" class="text-h2 font-medium">Тариф «{{ $subscription->tariff->name }}»</h2>
                            @if ($complimentary)<x-ui.badge tone="ok">Предоставлен бесплатно</x-ui.badge>@endif
                        </div>
                        <span class="text-t2 text-muted">
                            @if ($subscription->ends_at)
                                {{ $complimentary ? 'Действует' : 'Оплачен' }} <x-ui.em>до {{ HumanDate::date($subscription->ends_at) }}</x-ui.em> · осталось {{ plural_ru($daysLeft, 'день', 'дня', 'дней') }}
                            @elseif ($subscription->tariff->isFree())
                                Бесплатный, без срока
                            @else
                                Действует бессрочно
                            @endif
                        </span>
                        @if ($scheduled)
                            <span class="text-t2 text-muted"><x-ui.em>{{ HumanDate::date($scheduled->starts_at) }}</x-ui.em> подключится тариф «{{ $scheduled->tariff->name }}» — после окончания оплаченного периода</span>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1 text-right">
                        @if ($complimentary)
                            <span class="text-t1 font-semibold line-through">{{ $rub($subscription->tariff->price) }}</span>
                            <span class="text-t3 text-muted">Оплата не требуется</span>
                        @else
                            <span class="text-t1 font-semibold">{{ $rub($subscription->tariff->price) }}</span>
                            <span class="text-t3 text-muted">в месяц</span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex items-baseline justify-between gap-4 text-t2">
                        <span>Занятия в этом периоде</span>
                        <span><x-ui.em>{{ $lessonsUsed }}</x-ui.em>@if ($limit) из {{ $limit }}@endif @if ($extraBalance > 0)<span class="text-muted"> + {{ $extraBalance }} доп.</span>@endif</span>
                    </div>
                    @if ($limit)
                        <x-ui.progress :value="$lessonsUsed" :max="$limit" label="Занятия в этом периоде" on-mint />
                        <span class="text-t3 text-muted">
                            @if ($limitReached)
                                <x-ui.em>Лимит тарифа исчерпан</x-ui.em>{{ $periodResetsAt ? ', обновится ' . HumanDate::date($periodResetsAt) : '' }}.
                                {{ $extraBalance > 0 ? 'Занятия проводятся за счёт докупленных.' : 'Чтобы проводить занятия сейчас — докупите их или перейдите на тариф выше.' }}
                            @else
                                @if ($periodResetsAt)Лимит обновится {{ HumanDate::date($periodResetsAt) }}.@endif
                                @if ($canBuyExtra)Дополнительные занятия — {{ $rub($extraPrice) }} за штуку, они не сгорают.@elseif ($extraBalance > 0)Докупленные занятия не сгорают и расходуются после лимита.@endif
                            @endif
                        </span>
                    @endif
                </div>

                @if (($showRenew && ! $complimentary) || ($canBuyExtra && $limit))
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($showRenew && ! $complimentary)
                            <x-ui.btn variant="primary" wire:click="openSelect({{ $subscription->tariff_id }}, true)">Продлить</x-ui.btn>
                        @endif
                        @if ($canBuyExtra && $limit)
                            <x-ui.btn :variant="$primary === 'buy' ? 'primary' : 'outline'" icon="plus" wire:click="openBuy">Докупить занятия</x-ui.btn>
                        @endif
                    </div>
                @endif
                @if ($limitReached && $extraBalance <= 0 && $referralBonus > 0)
                    <span class="text-t2 text-muted">Или получите +{{ plural_ru($referralBonus, 'занятие', 'занятия', 'занятий') }} бесплатно — <a href="{{ $referralsUrl }}" class="link">пригласите коллегу</a></span>
                @endif
            @elseif ($expired)
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="s-cur" class="text-h2 font-medium">Тариф «{{ $expired->tariff->name }}»</h2>
                            <x-ui.badge tone="danger">Срок истёк</x-ui.badge>
                        </div>
                        <span class="text-t2 text-muted">
                            @if ($expired->ends_at)Действовал до {{ HumanDate::date($expired->ends_at) }}. @endif
                            Продлите подписку, чтобы продолжить проводить занятия.
                        </span>
                        @if ($extraBalance > 0)
                            <span class="text-t2 text-muted">Докупленных занятий на балансе: <x-ui.em>{{ $extraBalance }}</x-ui.em> — они сохранятся и после продления</span>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1 text-right">
                        <span class="text-t1 font-semibold">{{ $rub($expired->tariff->price) }}</span>
                        <span class="text-t3 text-muted">в месяц</span>
                    </div>
                </div>
                <div class="flex">
                    <x-ui.btn variant="primary" wire:click="openSelect({{ $expired->tariff_id }}, true)">Продлить</x-ui.btn>
                </div>
            @else
                <div class="flex flex-col gap-1">
                    <h2 id="s-cur" class="text-h2 font-medium">Тариф не выбран</h2>
                    <span class="text-t2 text-muted">Выберите тариф ниже — бесплатный «Старт» подключается в один клик.</span>
                </div>
            @endif
        </x-ui.card>

        {{-- Способ оплаты и автопродление --}}
        <x-ui.card aria-labelledby="s-card">
            <x-ui.card-head id="s-card" title="Способ оплаты" />
            @if ($user->yookassa_payment_method_id)
                <div class="flex items-center gap-3">
                    <x-ui.pay-logo tile :type="$savedMethodType" />
                    <span class="min-w-0 flex-1 truncate text-t1 font-medium">{{ $user->payment_method_title ?? 'Сохранённый способ оплаты' }}</span>
                    <button type="button" class="link text-t2" wire:click="$set('removeOpen', true)">Отвязать</button>
                </div>
                <div class="flex items-center gap-4 border-t border-line pt-4">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-t1 font-medium">Автопродление</span>
                        <span class="text-t2 text-muted">
                            @if ($user->auto_renew)
                                {{ $subscription?->ends_at && ! $subscription->tariff->isFree() ? 'Продлится ' . HumanDate::date($subscription->ends_at) . ', предупредим о списании заранее' : 'Подписка продлится в конце оплаченного периода, предупредим о списании заранее' }}
                            @else
                                {{ $subscription?->ends_at && ! $subscription->tariff->isFree() ? 'Выключено — продлите тариф до ' . HumanDate::date($subscription->ends_at) . ' сами' : 'Выключено — в конце периода тариф нужно будет продлить вручную' }}
                            @endif
                        </span>
                    </div>
                    <x-ui.switch :checked="(bool) $user->auto_renew" label="Автопродление" wire:click="toggleAutoRenew" wire:loading.attr="disabled" wire:target="toggleAutoRenew" />
                </div>
            @else
                <div class="flex flex-col gap-1">
                    <span class="text-t1 font-medium">Способ оплаты не сохранён</span>
                    <span class="text-t2 text-muted">
                        @if ($recurring)
                            {{ count($savableMethods) === 1 ? 'Привяжите банковскую карту, чтобы оплачивать в один клик и включить автопродление. Карту можно сохранить и при оплате тарифа.' : 'Привяжите способ оплаты, чтобы оплачивать в один клик и включить автопродление. Его можно сохранить и при оплате тарифа.' }}
                        @else
                            Оплата в один клик и автопродление скоро станут доступны.
                        @endif
                    </span>
                </div>
                @if ($recurring)
                    <x-ui.btn size="s" class="self-start" wire:click="openBind">{{ count($savableMethods) === 1 ? 'Привязать карту' : 'Привязать способ оплаты' }}</x-ui.btn>
                @endif
            @endif
        </x-ui.card>
    </div>

    {{-- Тарифы --}}
    <section class="flex flex-col gap-4" aria-labelledby="s-tar">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="s-tar" class="text-h2 font-medium">{{ $subscription ? 'Сменить тариф' : 'Выбрать тариф' }}</h2>
            @if ($hasYearly)
                <div class="flex items-center gap-2">
                    @if ($billingPeriod === 'year' && $maxDiscount > 0)<x-ui.badge>выгода до {{ $maxDiscount }}%</x-ui.badge>@endif
                    <x-ui.seg fit :items="['month' => 'Помесячно', 'year' => 'На год']" model="billingPeriod" :active="$billingPeriod" aria-label="Период оплаты" />
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 items-stretch gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($tariffs as $tariff)
                @php
                    $isCurrent = $subscription && $subscription->tariff_id === $tariff->id;
                    $isExpired = $expired && $expired->tariff_id === $tariff->id;
                    $isScheduled = $scheduled && $scheduled->tariff_id === $tariff->id;
                    $yearly = $billingPeriod === 'year' && $tariff->hasYearly();
                    $features = array_merge($tariff->features ?? [], $tariff->extra_features ?? []);
                @endphp
                <x-ui.card wire:key="tariff-{{ $tariff->id }}" :class="$isCurrent ? 'h-full shadow-outline-ink' : 'h-full'">
                    <div class="flex min-h-6 items-center justify-between gap-2">
                        <span class="text-t1 font-medium">{{ $tariff->name }}</span>
                        @if ($isCurrent)<x-ui.badge>Ваш тариф</x-ui.badge>
                        @elseif ($isExpired)<x-ui.badge tone="danger">Срок истёк</x-ui.badge>
                        @elseif ($tariff->is_popular)<x-ui.badge>Популярный</x-ui.badge>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2">
                        <span><span class="text-num font-medium">{{ $rub($yearly ? $tariff->yearly_price : $tariff->price) }}</span><span class="text-t2 text-muted"> {{ $yearly ? 'в год' : 'в месяц' }}</span></span>
                        <span class="text-t2 text-muted">
                            @if ($tariff->isFree())
                                Бесплатно
                            @elseif ($yearly)
                                ≈ {{ $rub(round($tariff->yearly_price / 12)) }} в месяц@if ($tariff->yearlyDiscountPercent() > 0) · −{{ $tariff->yearlyDiscountPercent() }}%@endif
                            @elseif ($tariff->hasYearly())
                                или {{ $rub($tariff->yearly_price) }} за год
                            @endif
                        </span>
                    </div>
                    <ul class="flex flex-col gap-2">
                        @foreach ([$tariff->lessons_label, $tariff->participants_label, $tariff->duration_label, $tariff->recording_label] as $line)
                            <li class="flex items-start gap-2 text-t2"><x-ui.icon name="check" size="s" class="mt-px text-muted" />{{ $line }}</li>
                        @endforeach
                    </ul>
                    @if ($features)
                        <x-ui.disclosure title="Все возможности" :meta="(string) count($features)">
                            <ul class="flex flex-col gap-2">
                                @foreach ($features as $feature)
                                    <li class="text-t2 text-muted">{{ $feature }}</li>
                                @endforeach
                            </ul>
                        </x-ui.disclosure>
                    @endif
                    <div class="mt-auto flex flex-col gap-2">
                        @if ($isCurrent)
                            {{-- подключён — действий нет --}}
                        @elseif ($isScheduled)
                            <span class="text-t3 text-muted">Начнёт действовать {{ HumanDate::date($scheduled->starts_at) }}</span>
                        @elseif ($isExpired)
                            <x-ui.btn size="s" class="self-start" wire:click="openSelect({{ $tariff->id }}, true)">Продлить</x-ui.btn>
                        @else
                            <x-ui.btn size="s" class="self-start" :variant="$primary === 'tariff' && $primaryTariffId === $tariff->id ? 'primary' : 'outline'"
                                      wire:click="openSelect({{ $tariff->id }})">{{ $tariff->isFree() ? 'Подключить' : 'Оплатить и подключить' }}</x-ui.btn>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>
        <span class="text-t3 text-muted">Оплата картой или через СБП с помощью ЮKassa, тариф включается сразу после оплаты. Условия возврата — в <a href="{{ route('offer') }}" target="_blank" rel="noopener" class="link">публичной оферте</a></span>
    </section>

    {{-- История платежей --}}
    @if ($payments->isNotEmpty())
        <x-ui.card aria-labelledby="s-hist">
            <x-ui.card-head id="s-hist" title="История платежей">
                <x-slot:action><a href="{{ $historyUrl }}" class="link text-t2">Все платежи</a></x-slot:action>
            </x-ui.card-head>
            <x-ui.list>
                @foreach ($payments as $payment)
                    @php
                        $binding = ! empty($payment->meta['card_binding']);
                        $refunded = $payment->status === SubscriptionPayment::STATUS_REFUNDED;
                        $title = $binding ? 'Привязка способа оплаты' : $payment->title . (! $payment->isExtraLessons() && $payment->period_days >= 365 ? ' на год' : '');
                    @endphp
                    <x-ui.row class="flex-wrap lg:flex-nowrap" wire:key="pay-{{ $payment->id }}">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="text-t1 font-medium">{{ $title }}</span>
                            <span class="text-t2 text-muted">
                                {{ HumanDate::at($payment->created_at) }}@if ($binding) · проверочный платёж@endif
                                @if ($refunded)
                                    <br>
                                    @if ($binding)
                                        Проверочный 1 ₽ возвращён
                                    @else
                                        Возврат оформлен {{ HumanDate::date(! empty($payment->meta['refunded_at']) ? \Illuminate\Support\Carbon::parse($payment->meta['refunded_at']) : $payment->updated_at) }}. Деньги вернутся на карту, с которой была оплата, в течение {{ plural_ru((int) $refundProcessingDays, 'рабочего дня', 'рабочих дней', 'рабочих дней') }}
                                    @endif
                                @endif
                            </span>
                        </div>
                        <div class="flex shrink-0 items-center gap-4">
                            @if ($refunded)
                                <x-ui.badge>Вернули</x-ui.badge>
                            @elseif ($payment->status === SubscriptionPayment::STATUS_PENDING)
                                @if ($payment->isResumable())
                                    <span class="text-t3 text-muted">ссылка до {{ $payment->created_at->copy()->addHour()->format('H:i') }}</span>
                                    <x-ui.btn size="s" :href="$payment->payment_url">Оплатить</x-ui.btn>
                                @else
                                    <x-ui.badge>Ожидает оплаты</x-ui.badge>
                                @endif
                            @endif
                            <span class="whitespace-nowrap text-t1 font-semibold">{{ $rub($payment->amount) }}</span>
                            @if ($payment->status === SubscriptionPayment::STATUS_PAID)
                                <a href="{{ route('subscription.payment.receipt', $payment) }}" target="_blank" rel="noopener" class="link text-t2" aria-label="Чек: {{ $title }}, {{ HumanDate::date($payment->created_at) }}">Чек</a>
                            @endif
                        </div>
                    </x-ui.row>
                @endforeach
            </x-ui.list>
        </x-ui.card>
    @endif

    {{-- Окно: смена или продление тарифа --}}
    @if ($selecting)
        @php
            $yearlySel = $billingPeriod === 'year' && $selecting->hasYearly();
            $periodText = $yearlySel ? ' на год (' . $rub($selecting->yearly_price) . ')' : '';
        @endphp
        <x-ui.modal :title="$selectUnavailable ? 'Тариф недоступен' : ($selectRenew ? 'Продление тарифа' : 'Смена тарифа')" :sub="'Тариф «' . $selecting->name . '»'" close="closeSelect" width="s">
            <p class="text-t1">
                @if ($selectUnavailable)
                    Тариф «{{ $selecting->name }}» больше недоступен для оформления. Выберите другой тариф из списка.
                @elseif ($selectRenew)
                    Продлить тариф «{{ $selecting->name }}»{{ $periodText }} и перейти к оплате?
                @elseif ($selectDeferred)
                    Тариф «{{ $selecting->name }}» подключится {{ HumanDate::date($selectDeferred) }} — после окончания оплаченного периода. До этого действуют условия тарифа «{{ $subscription->tariff->name }}».
                @else
                    Переключиться на тариф «{{ $selecting->name }}»{{ $selecting->isFree() ? '' : $periodText . ' и перейти к оплате' }}?
                @endif
            </p>
            @if (! $selectUnavailable && $showPicker)
                @include('livewire.cabinet.teacher.partials.pay-methods', ['methods' => $methods, 'model' => 'payMethod', 'active' => $payMethod])
                @if ($canSave)
                    <div class="flex items-center gap-4">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="text-t2 font-medium">Сохранить способ оплаты</span>
                            <span class="text-t3 text-muted">Следующие оплаты пройдут в один клик. Отвязать можно в любой момент на этой странице</span>
                        </div>
                        <x-ui.switch :checked="$saveMethod" label="Сохранить способ оплаты" wire:click="$toggle('saveMethod')" />
                    </div>
                    @if ($saveMethod)
                        <div class="flex items-center gap-4">
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="text-t2 font-medium">Включить автопродление</span>
                                <span class="text-t3 text-muted">Тариф продлится сам в конце оплаченного периода — предупредим о списании заранее</span>
                            </div>
                            <x-ui.switch :checked="$autoRenewOptIn" label="Включить автопродление" wire:click="$toggle('autoRenewOptIn')" />
                        </div>
                    @endif
                @endif
            @endif
            @if ($modalError)<p class="text-t2 font-medium text-danger-fg">{{ $modalError }}</p>@endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeSelect">Отмена</x-ui.btn>
                @unless ($selectUnavailable)
                    <x-ui.btn variant="primary" wire:click="confirmSelect" wire:loading.attr="disabled" wire:target="confirmSelect">{{ $selectRenew ? 'Продлить' : 'Переключиться' }}</x-ui.btn>
                @endunless
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно: докупка занятий --}}
    @if ($buyOpen)
        <x-ui.modal title="Дополнительные занятия" :sub="'Одно занятие — ' . $rub($extraPrice)" close="closeBuy">
            <p class="text-t2 text-muted">Докупленные занятия не сгорают: расходуются после лимита тарифа и переносятся в следующий период и при смене тарифа.@if ($periodResetsAt) Лимит тарифа обновится {{ HumanDate::date($periodResetsAt) }}.@endif</p>
            <x-ui.field label="Сколько занятий докупить" name="quantity" type="number" min="1" :max="$extraMax" inputmode="numeric" wire:model.live.debounce.300ms="quantity"
                        :hint="'За одну покупку — до ' . $extraMax" />
            <div class="flex flex-col gap-1">
                <span class="text-t2 text-muted">К оплате</span>
                <span class="text-num font-medium">{{ $rub($extraTotal) }}</span>
                <span class="text-t2 text-muted">за {{ plural_ru($extraQty, 'занятие', 'занятия', 'занятий') }}</span>
                @if ($upgrade)
                    <span class="text-t2 text-muted">Тариф «{{ $upgrade->name }}» даёт {{ mb_strtolower($upgrade->lessons_label) }} за {{ $rub($upgrade->price) }} в месяц — это может быть выгоднее</span>
                @endif
            </div>
            @unless ($user->yookassa_payment_method_id)
                @include('livewire.cabinet.teacher.partials.pay-methods', ['methods' => $methods, 'model' => 'payMethod', 'active' => $payMethod])
            @endunless
            @if ($modalError)<p class="text-t2 font-medium text-danger-fg">{{ $modalError }}</p>@endif
            <x-slot:note>@if ($user->yookassa_payment_method_id)Спишем с {{ $user->payment_method_title ?? 'сохранённого способа оплаты' }}@endif</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeBuy">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="confirmBuy" wire:loading.attr="disabled" wire:target="confirmBuy">Оплатить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно: привязка способа оплаты --}}
    @if ($bindOpen)
        @php $single = count($savableMethods) === 1; @endphp
        <x-ui.modal :title="$single ? 'Привязка карты' : 'Привязка способа оплаты'" sub="Для проверки спишем 1 ₽ и сразу вернём" close="closeBind" width="s">
            <p class="text-t1">После привязки оплата будет проходить в один клик, а автопродление можно будет включить.</p>
            @unless ($single)
                @include('livewire.cabinet.teacher.partials.pay-methods', ['methods' => $savableMethods, 'model' => 'payMethod', 'active' => $payMethod])
            @endunless
            @if ($modalError)<p class="text-t2 font-medium text-danger-fg">{{ $modalError }}</p>@endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeBind">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="confirmBind" wire:loading.attr="disabled" wire:target="confirmBind">Привязать</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно: отвязать способ оплаты --}}
    @if ($removeOpen)
        <x-ui.modal title="Отвязать способ оплаты?" :sub="$user->payment_method_title ?? 'Сохранённый способ оплаты'" close="$set('removeOpen', false)" width="s">
            <p class="text-t1">Автопродление выключится, а оплата снова будет проходить через платёжную страницу.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('removeOpen', false)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="confirmRemove">Отвязать</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
