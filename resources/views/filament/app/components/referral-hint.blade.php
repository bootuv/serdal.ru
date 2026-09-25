{{-- Подсказка о партнёрской программе там, где учителю не хватает занятий --}}
@if(\App\Services\ReferralService::enabled())
    @php($bonus = \App\Services\ReferralService::referrerBonus())
    <p class="mt-2 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
        <x-filament::icon icon="heroicon-o-gift" class="h-4 w-4 shrink-0 text-primary-500" />
        <span>
            Или получите +{{ $bonus }} {{ \App\Services\SubscriptionService::lessonsWord($bonus) }} бесплатно —
            <a href="{{ \App\Filament\App\Pages\Referrals::getUrl(panel: 'app') }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">пригласите коллегу</a>
        </span>
    </p>
@endif
