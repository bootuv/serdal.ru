<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Мой тариф
            @if($subscription)
                <span class="text-gray-500 dark:text-gray-400">·</span>
                {{ $tariff->name }}
                @if($tariff->isFree())
                    <x-filament::badge color="gray" class="ml-1 inline-flex">Бесплатный</x-filament::badge>
                @elseif($subscription->isComplimentary())
                    <x-filament::badge color="success" class="ml-1 inline-flex">Предоставлен бесплатно</x-filament::badge>
                @endif
            @endif
        </x-slot>

        <x-slot name="headerEnd">
            <a href="{{ $subscriptionUrl }}"
                class="text-sm font-medium text-amber-600 hover:text-amber-500 dark:text-amber-500 dark:hover:text-amber-400 hover:underline">
                Управление подпиской
            </a>
        </x-slot>

        @if($subscription)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Занятия в периоде --}}
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10 sm:col-span-2">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm text-gray-600 dark:text-gray-300">Занятий осталось в периоде</p>
                        @if($periodStart && $periodResetsAt)
                            <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400"
                                title="Текущий период тарифа">
                                {{ $periodStart->format('d.m') }} – {{ $periodResetsAt->format('d.m.Y') }}
                            </p>
                        @endif
                    </div>

                    @if($limit !== null)
                        <p class="mt-1 flex items-baseline gap-2">
                            <span @class([
                                'text-3xl font-bold',
                                'text-danger-600 dark:text-danger-400' => $limitReached,
                                'text-amber-600 dark:text-amber-400' => $lessonsLow,
                                'text-gray-950 dark:text-white' => !$limitReached && !$lessonsLow,
                            ])>{{ $remaining }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">из {{ $limit }} · проведено {{ $lessonsUsed }}</span>
                            @if($extraBalance > 0)
                                <span class="text-sm text-gray-500 dark:text-gray-400">+ {{ plural_ru($extraBalance, 'докупленное', 'докупленных', 'докупленных') }}</span>
                            @endif
                        </p>

                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div @class([
                                'h-full rounded-full transition-all',
                                'bg-danger-500' => $limitReached,
                                'bg-amber-500' => $lessonsLow,
                                'bg-primary-500' => !$limitReached && !$lessonsLow,
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

                        @if($canBuyExtra && ($limitReached || $lessonsLow))
                            <div class="mt-3">
                                <x-filament::button
                                    tag="a"
                                    :href="$subscriptionUrl"
                                    size="sm"
                                    :color="$limitReached && $extraBalance <= 0 ? 'primary' : 'gray'"
                                    :outlined="!($limitReached && $extraBalance <= 0)">
                                    Докупить занятия
                                </x-filament::button>
                            </div>
                        @endif
                    @else
                        <p class="mt-1 flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-gray-950 dark:text-white">∞</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">без лимита · проведено {{ $lessonsUsed }}</span>
                        </p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Количество занятий на вашем тарифе не ограничено.
                        </p>
                    @endif
                </div>

                {{-- Срок действия --}}
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $subscription->isComplimentary() || $tariff->isFree() ? 'Действует' : 'Оплачен' }}
                    </p>
                    @if($subscription->ends_at)
                        <p @class([
                            'mt-1 text-3xl font-bold',
                            'text-danger-600 dark:text-danger-400' => $isExpiring,
                            'text-gray-950 dark:text-white' => !$isExpiring,
                        ])>
                            {{ $daysLeft }}<span class="text-base font-semibold"> {{ plural_ru($daysLeft, 'день', 'дня', 'дней', false) }}</span>
                        </p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            до {{ $subscription->ends_at->format('d.m.Y') }}
                        </p>
                        @if($scheduled)
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                Затем — тариф «{{ $scheduled->tariff->name }}»
                            </p>
                        @elseif($isExpiring && !$tariff->isFree())
                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">
                                <a href="{{ $subscriptionUrl }}" class="font-medium hover:underline">Продлить подписку</a>
                            </p>
                        @endif
                    @else
                        <p class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">∞</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Бессрочно</p>
                    @endif
                </div>

                {{-- Условия тарифа --}}
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Ограничения тарифа</p>
                    <dl class="mt-2 space-y-1.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Участников</dt>
                            <dd class="font-semibold text-gray-950 dark:text-white">до {{ $tariff->max_participants }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Длительность</dt>
                            <dd class="font-semibold text-gray-950 dark:text-white">
                                {{ $tariff->max_duration_minutes ? 'до ' . $tariff->max_duration_minutes . ' мин' : 'без лимита' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Записи</dt>
                            <dd class="font-semibold text-gray-950 dark:text-white">
                                {{ $tariff->recording_retention_days ? plural_ru($tariff->recording_retention_days, 'день', 'дня', 'дней') : 'недоступны' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        @elseif($expired)
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ $expired->tariff->name }}
                        <x-filament::badge color="danger" class="ml-1 inline-flex">Срок истёк</x-filament::badge>
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        @if($expired->ends_at)
                            Действовал до {{ $expired->ends_at->format('d.m.Y') }}.
                        @endif
                        Продлите подписку, чтобы продолжить проводить занятия.
                        @if($extraBalance > 0)
                            Докупленных занятий на балансе: {{ $extraBalance }} — они сохранятся после продления.
                        @endif
                    </p>
                </div>
                <x-filament::button tag="a" :href="$subscriptionUrl" color="primary">
                    Продлить подписку
                </x-filament::button>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    У вас пока нет активной подписки. Выберите тариф — бесплатный тариф подключается в один клик.
                </p>
                <x-filament::button tag="a" :href="$subscriptionUrl" color="primary">
                    Выбрать тариф
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
