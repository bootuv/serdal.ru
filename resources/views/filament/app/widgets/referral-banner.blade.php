@php($word = fn(int $n) => \App\Services\SubscriptionService::lessonsWord($n))
<x-filament-widgets::widget>
    @unless($hidden)
        <div
            x-data="{ copied: false, url: @js($inviteUrl) }"
            class="relative overflow-hidden rounded-xl bg-gradient-to-r from-primary-50 to-white p-4 shadow-sm ring-1 ring-primary-200 dark:from-primary-500/10 dark:to-gray-900 dark:ring-primary-500/30 sm:p-5"
        >
            <button
                type="button"
                wire:click="dismiss"
                class="absolute right-2 top-2 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-200"
                title="Скрыть"
                aria-label="Скрыть"
            >
                <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
            </button>

            <div class="flex flex-col gap-4 pr-6 sm:flex-row sm:items-center">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-500 text-white">
                    <x-filament::icon icon="heroicon-o-gift" class="h-6 w-6" />
                </div>

                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-950 dark:text-white">
                        Приглашайте коллег — получайте занятия бесплатно
                    </p>
                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                        +{{ $referrerBonus }} {{ $word($referrerBonus) }} за каждого учителя, который оплатит тариф по вашей ссылке@if($referredBonus > 0), а ему — +{{ $referredBonus }} в подарок@endif.
                        @if($earned > 0)
                            <span class="font-medium text-success-600 dark:text-success-400">Вы уже получили +{{ $earned }}.</span>
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    <x-filament::button
                        size="sm"
                        icon="heroicon-o-clipboard-document"
                        x-on:click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span x-show="!copied">Скопировать ссылку</span>
                        <span x-show="copied" x-cloak>Скопировано</span>
                    </x-filament::button>
                    <a href="{{ $pageUrl }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        Подробнее
                    </a>
                </div>
            </div>
        </div>
    @endunless
</x-filament-widgets::widget>
