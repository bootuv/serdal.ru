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
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        @if($subscription->ends_at)
                            Оплачен до {{ $subscription->ends_at->format('d.m.Y') }}
                            ({{ $subscription->ends_at->diffForHumans() }})
                        @else
                            Действует бессрочно
                        @endif
                    </p>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        Занятий проведено в этом периоде:
                        <span class="font-semibold text-gray-950 dark:text-white">
                            {{ $lessonsUsed }}@if($subscription->tariff->lessons_per_month) из {{ $subscription->tariff->lessons_per_month }}@endif
                        </span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">
                        {{ number_format($subscription->tariff->price, 0, ',', ' ') }} ₽<span
                            class="text-sm font-normal text-gray-500">/мес</span>
                    </p>
                    @if(!$subscription->tariff->isFree())
                        <x-filament::button wire:click="selectTariff({{ $subscription->tariff_id }})" class="mt-2"
                            icon="heroicon-o-arrow-path">
                            Продлить
                        </x-filament::button>
                    @endif
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
                <div @class([
                    'flex flex-col rounded-xl p-5 ring-1',
                    'ring-primary-500 bg-primary-50 dark:bg-primary-500/10' => $isCurrent,
                    'ring-gray-200 dark:ring-white/10' => !$isCurrent,
                ])>
                    <div class="flex items-center justify-between">
                        <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $tariff->name }}</p>
                        @if($tariff->is_popular)
                            <x-filament::badge color="warning">Популярный</x-filament::badge>
                        @endif
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $tariff->isFree() ? '0 ₽' : number_format($tariff->price, 0, ',', ' ') . ' ₽' }}<span
                            class="text-sm font-normal text-gray-500">/мес</span>
                    </p>
                    <ul class="mt-4 flex-1 space-y-2">
                        @foreach($tariff->features ?? [] as $feature)
                            <li class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-5">
                        @if($isCurrent)
                            <x-filament::button color="gray" disabled class="w-full justify-center">
                                Подключён
                            </x-filament::button>
                        @else
                            <x-filament::button wire:click="selectTariff({{ $tariff->id }})"
                                wire:confirm="Переключиться на тариф «{{ $tariff->name }}»{{ $tariff->isFree() ? '' : ' и перейти к оплате' }}?"
                                class="w-full justify-center">
                                {{ $tariff->isFree() ? 'Подключить' : 'Оплатить и подключить' }}
                            </x-filament::button>
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
        </x-filament::section>
    @endif
</x-filament-panels::page>
