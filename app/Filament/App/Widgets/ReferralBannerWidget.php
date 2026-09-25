<?php

namespace App\Filament\App\Widgets;

use App\Services\ReferralService;
use Filament\Widgets\Widget;

/**
 * Компактный баннер партнёрской программы на инфопанели учителя.
 * Появляется через несколько дней после регистрации; «×» скрывает его
 * на заданный в админке срок (Настройки → Партнёрская программа).
 */
class ReferralBannerWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.referral-banner';

    // После «Ближайших занятий» (2): рабочие блоки остаются выше
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public bool $hidden = false;

    public static function canView(): bool
    {
        return auth()->check() && ReferralService::shouldShowBanner(auth()->user());
    }

    public function dismiss(): void
    {
        ReferralService::hideBanner(auth()->user());
        $this->hidden = true;
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'inviteUrl' => ReferralService::inviteUrl($user),
            'referrerBonus' => ReferralService::referrerBonus(),
            'referredBonus' => ReferralService::referredBonus(),
            'earned' => ReferralService::stats($user)['lessons'],
            'pageUrl' => \App\Filament\App\Pages\Referrals::getUrl(panel: 'app'),
        ];
    }
}
