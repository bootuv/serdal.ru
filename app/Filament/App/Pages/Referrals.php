<?php

namespace App\Filament\App\Pages;

use App\Models\ReferralReward;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Services\ReferralService;
use Filament\Pages\Page;

class Referrals extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Пригласить коллегу';

    protected static ?string $title = 'Пригласить коллегу';

    protected static ?string $slug = 'referrals';

    protected static string $view = 'filament.app.pages.referrals';

    // Нижняя группа сайдбара (отделена чертой), последним пунктом
    protected static ?string $navigationGroup = '';

    protected static ?int $navigationSort = 99;

    public static function shouldRegisterNavigation(): bool
    {
        return ReferralService::enabled();
    }

    /** Метка «+10» у пункта меню — сколько занятий даёт одно приглашение. */
    public static function getNavigationBadge(): ?string
    {
        $bonus = ReferralService::referrerBonus();

        return $bonus > 0 ? '+' . $bonus : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Занятий за каждого приглашённого коллегу';
    }

    public static function canAccess(): bool
    {
        return ReferralService::enabled();
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'inviteUrl' => ReferralService::inviteUrl($user),
            'referrerBonus' => ReferralService::referrerBonus(),
            'referredBonus' => ReferralService::referredBonus(),
            'monthlyLimit' => ReferralService::monthlyLimit(),
            'cookieDays' => ReferralService::cookieDays(),
            'stats' => ReferralService::stats($user),
            'invited' => ReferralService::invited($user),
        ];
    }
}
