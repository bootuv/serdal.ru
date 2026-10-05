<?php

namespace App\Demo\Teacher;

use App\Demo\Screen;
use Carbon\Carbon;

/**
 * «Пригласить коллегу» — App\Livewire\Cabinet\Teacher\Referrals. Условия — значения по умолчанию из настроек
 * партнёрской программы (+10 пригласившему, +5 коллеге, ссылка помнится 30 дней), ссылка и коллеги выдуманные.
 */
class Referrals extends Screen
{
    public const PATH = 'referrals';

    public string $view = 'livewire.cabinet.teacher.referrals';

    public string $title = 'Пригласить коллегу';

    public ?string $active = null;

    public function data(): array
    {
        $inviteUrl = route('referral.invite', 'zarema-m7k4');
        $referredBonus = 5;
        $now = Carbon::now();

        // ReferralService::invited(): сначала заявки на рассмотрении, затем зарегистрированные (новые сверху)
        $invited = collect([
            [1101, 'Мержоева Танзила', $now->copy()->subDays(2), 'application', 0],
            [1102, 'Албогачиева Луиза', $now->copy()->subDays(9), 'waiting', 0],
            [1103, 'Хамхоев Магомед', $now->copy()->subDays(26), 'credited', 10],
            [1104, 'Яндиева Седа', $now->copy()->subDays(48), 'credited', 10],
        ])->map(fn (array $r) => [
            'id' => $r[0],
            'name' => $r[1],
            'photo' => null,
            'date' => $r[2],
            'state' => $r[3],
            'lessons' => $r[4],
            'status' => '',
            'color' => $r[3] === 'credited' ? 'success' : 'gray',
        ]);

        return [
            'inviteUrl' => $inviteUrl,
            'referrerBonus' => 10,
            'referredBonus' => $referredBonus,
            'monthlyLimit' => 0,
            'cookieDays' => 30,
            'stats' => ['invited' => 4, 'paid' => 2, 'lessons' => 20],
            'invited' => $invited,
            // Поделиться выдуманной ссылкой в настоящем мессенджере нельзя: ссылка своего сайта вне демо — demo-cabinet.js покажет тост
            'telegramUrl' => $inviteUrl,
            'whatsappUrl' => $inviteUrl,
        ];
    }
}
