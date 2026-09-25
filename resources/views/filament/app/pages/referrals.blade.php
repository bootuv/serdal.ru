@php($word = fn(int $n) => \App\Services\SubscriptionService::lessonsWord($n))
<x-filament-panels::page>
    {{-- Правило программы --}}
    <x-filament::section>
        <p class="text-xl font-bold text-gray-950 dark:text-white">
            Пригласите коллегу — получите +{{ $referrerBonus }} {{ $word($referrerBonus) }}
        </p>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
            Когда учитель, которого вы пригласили, оплатит любой тариф, вам начислится
            +{{ $referrerBonus }} {{ $word($referrerBonus) }}@if($referredBonus > 0), а ему — +{{ $referredBonus }} {{ $word($referredBonus) }} в подарок@endif.
            Бонусные занятия не сгорают и расходуются после лимита тарифа.
        </p>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach([
                ['1', 'Отправьте ссылку', 'Коллеге в мессенджер или на почту.'],
                ['2', 'Коллега подаёт заявку', 'По вашей ссылке — так мы узнаем, что это вы его пригласили.'],
                ['3', 'Коллега оплачивает тариф', 'Вам сразу приходят +' . $referrerBonus . ' ' . $word($referrerBonus) . '.'],
            ] as [$num, $title, $text])
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-500 text-sm font-bold text-white">{{ $num }}</div>
                    <p class="mt-2 font-semibold text-gray-950 dark:text-white">{{ $title }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Ссылка-приглашение --}}
    <x-filament::section>
        <x-slot name="heading">Ваша ссылка</x-slot>

        <div x-data="{ copied: false, url: @js($inviteUrl) }" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <x-filament::input.wrapper class="min-w-0 flex-1">
                <x-filament::input type="text" readonly :value="$inviteUrl" x-on:focus="$el.select()" />
            </x-filament::input.wrapper>

            <div class="flex flex-wrap gap-2">
                <x-filament::button
                    icon="heroicon-o-clipboard-document"
                    x-on:click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-show="!copied">Скопировать</span>
                    <span x-show="copied" x-cloak>Скопировано</span>
                </x-filament::button>

                @php($shareText = 'Провожу онлайн-занятия на Serdal — удобная платформа для репетиторов. Регистрируйся по моей ссылке' . ($referredBonus > 0 ? ' и получи +' . $referredBonus . ' ' . $word($referredBonus) . ' в подарок' : '') . ':')
                <x-filament::button
                    tag="a"
                    color="gray"
                    target="_blank"
                    :href="'https://t.me/share/url?url=' . rawurlencode($inviteUrl) . '&text=' . rawurlencode($shareText)"
                >
                    Telegram
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    color="gray"
                    target="_blank"
                    :href="'https://wa.me/?text=' . rawurlencode($shareText . ' ' . $inviteUrl)"
                >
                    WhatsApp
                </x-filament::button>
            </div>
        </div>

        <div class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
            <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-5 w-5 shrink-0" />
            <p>Приглашение засчитается, если коллега зарегистрируется по вашей ссылке.</p>
        </div>
    </x-filament::section>

    {{-- Статистика и приглашённые --}}
    <x-filament::section>
        <x-slot name="heading">Ваши приглашения</x-slot>

        <div class="grid grid-cols-3 gap-3">
            @foreach([
                [$stats['invited'], 'Пригласили'],
                [$stats['paid'], 'Оплатили'],
                ['+' . $stats['lessons'], 'Получено занятий'],
            ] as [$value, $label])
                <div class="rounded-xl bg-gray-50 p-4 text-center dark:bg-white/5">
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $value }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                </div>
            @endforeach
        </div>

        @if($invited->isEmpty())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Пока никого. Отправьте ссылку коллеге — здесь появятся все, кто пришёл по ней.
            </p>
        @else
            <div class="mt-4 divide-y divide-gray-200 dark:divide-white/10">
                @foreach($invited as $row)
                    <div class="flex items-center justify-between gap-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['date']?->format('d.m.Y') }}</p>
                        </div>
                        <x-filament::badge :color="$row['color']">{{ $row['status'] }}</x-filament::badge>
                    </div>
                @endforeach
            </div>
        @endif

        @if($monthlyLimit > 0)
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Бонус начисляется максимум за {{ $monthlyLimit }} {{ $monthlyLimit % 10 === 1 && $monthlyLimit % 100 !== 11 ? 'коллегу' : 'коллег' }} в месяц.
            </p>
        @endif
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Бонус начисляется один раз — за первую оплату тарифа приглашённым учителем. Если платёж будет возвращён, бонус списывается.
        </p>
    </x-filament::section>
</x-filament-panels::page>
