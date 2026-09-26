<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Services\ReferralService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Партнёрская программа: ссылка-приглашение, условия и приглашённые коллеги.
 * Макета нет — собрано по BRAND.md, логика — ReferralService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Пригласить коллегу', 'active' => null])]
class Referrals extends Component
{
    use TeacherScreen;

    public function mount(): void
    {
        $this->authorizeTeacher();

        abort_unless(ReferralService::enabled(), 403);
    }

    public function render()
    {
        $user = auth()->user();
        $inviteUrl = ReferralService::inviteUrl($user);
        $referredBonus = ReferralService::referredBonus();
        $shareText = 'Провожу онлайн-занятия на ' . \App\Support\Seo::SITE_NAME . ' — удобная платформа для репетиторов. Регистрируйся по моей ссылке'
            . ($referredBonus > 0 ? ' и получи +' . plural_ru($referredBonus, 'занятие', 'занятия', 'занятий') . ' в подарок' : '') . ':';

        return view('livewire.cabinet.teacher.referrals', [
            'inviteUrl' => $inviteUrl,
            'referrerBonus' => ReferralService::referrerBonus(),
            'referredBonus' => $referredBonus,
            'monthlyLimit' => ReferralService::monthlyLimit(),
            'cookieDays' => ReferralService::cookieDays(),
            'stats' => ReferralService::stats($user),
            'invited' => ReferralService::invited($user),
            'telegramUrl' => 'https://t.me/share/url?url=' . rawurlencode($inviteUrl) . '&text=' . rawurlencode($shareText),
            'whatsappUrl' => 'https://wa.me/?text=' . rawurlencode($shareText . ' ' . $inviteUrl),
        ]);
    }
}
