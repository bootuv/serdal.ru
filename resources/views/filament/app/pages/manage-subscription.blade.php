<x-filament-panels::page>
    {{-- Текущая подписка --}}
    <x-filament::section>
        <x-slot name="heading">Текущий тариф</x-slot>

        @if($subscription)
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
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
                            ({{ $subscription->ends_at->diffForHumans() }})
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
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        Занятий проведено в этом периоде:
                        <span class="font-semibold text-gray-950 dark:text-white">
                            {{ $lessonsUsed }}@if($subscription->tariff->lessons_per_month) из {{ $subscription->tariff->lessons_per_month }}@endif
                        </span>
                    </p>
                </div>
                <div class="text-right">
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
                        @if(!$subscription->tariff->isFree() && $subscription->ends_at)
                            <x-filament::button wire:click="selectTariff({{ $subscription->tariff_id }})" class="mt-2"
                                icon="heroicon-o-arrow-path">
                                Продлить
                            </x-filament::button>
                        @endif
                    @endif
                </div>
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

    {{-- Выбор тарифа --}}
    <x-filament::section>
        <x-slot name="heading">Тарифы</x-slot>

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
                    <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $tariff->isFree() ? '0 ₽' : number_format($tariff->price, 0, ',', ' ') . ' ₽' }}<span
                            class="text-sm font-normal text-gray-500">/мес</span>
                    </p>
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

                    {{-- Дополнительные возможности --}}
                    <ul class="mt-3 flex-1 space-y-2 border-t border-gray-200 pt-3 dark:border-white/10">
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
                                    <x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="mt-5">
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
            Оплата картой через интернет-эквайринг Альфа-Банка. Подписка активируется сразу после оплаты.
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
                                Тариф «{{ $payment->tariff->name }}» — {{ number_format($payment->amount, 0, ',', ' ') }} ₽
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $payment->created_at->format('d.m.Y H:i') }}
                            </p>
                        </div>
                        <div class="shrink-0">
                            @switch($payment->status)
                                @case(\App\Models\SubscriptionPayment::STATUS_PAID)
                                    <x-filament::badge color="success">Оплачен</x-filament::badge>
                                    @break

                                @case(\App\Models\SubscriptionPayment::STATUS_PENDING)
                                    <x-filament::badge color="warning">Ожидает оплаты</x-filament::badge>
                                    @break

                                @case(\App\Models\SubscriptionPayment::STATUS_REFUNDED)
                                    <x-filament::badge color="gray">Возврат</x-filament::badge>
                                    @break

                                @default
                                    <x-filament::badge color="danger">Не прошёл</x-filament::badge>
                            @endswitch
                        </div>
                    </div>
                @endforeach
            </div>

            <a href="{{ \App\Filament\App\Pages\PaymentHistory::getUrl() }}"
                class="mt-3 inline-block text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                Все платежи и квитанции →
            </a>
        </x-filament::section>
    @endif
</x-filament-panels::page>
