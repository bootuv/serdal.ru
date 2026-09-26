<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\ReferralReward;
use App\Services\ReferralService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Партнёрская программа (макет AdminReferrals): журнал начислений с фильтром и поиском, сводка за месяц,
 * «Чаще всех приглашают», окно «Настройки программы» (те же настройки, что в Filament «Партнёрская программа»).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Партнёрская программа', 'active' => 'referrals'])]
class Referrals extends Component
{
    use AdminScreen;

    public const PER_PAGE = 20;

    #[Url]
    public string $filter = 'all';

    #[Url]
    public string $q = '';

    public int $limit = self::PER_PAGE;

    public bool $settingsOpen = false;

    /** Черновик настроек в окне. */
    public bool $on = true;
    public string $bonusReferrer = '';
    public string $bonusReferred = '';
    public string $monthlyLimit = '';
    public string $cookieDays = '';
    public bool $banner = true;
    public string $bannerDelay = '';
    public string $bannerSnooze = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['filter', 'q'], true)) {
            $this->limit = self::PER_PAGE;
        }
    }

    public function more(): void
    {
        $this->limit += self::PER_PAGE;
    }

    public function openSettings(): void
    {
        $s = ReferralService::settings();
        $this->on = (bool) $s['referral_enabled'];
        $this->bonusReferrer = (string) $s['referral_bonus_referrer'];
        $this->bonusReferred = (string) $s['referral_bonus_referred'];
        $this->monthlyLimit = (string) $s['referral_monthly_limit'];
        $this->cookieDays = (string) $s['referral_cookie_days'];
        $this->banner = (bool) $s['referral_banner_enabled'];
        $this->bannerDelay = (string) $s['referral_banner_delay_days'];
        $this->bannerSnooze = (string) $s['referral_banner_snooze_days'];
        $this->resetErrorBag();
        $this->settingsOpen = true;
    }

    public function closeSettings(): void
    {
        $this->settingsOpen = false;
    }

    public function toggleBanner(): void
    {
        if ($this->on) {
            $this->banner = ! $this->banner;
        }
    }

    public function saveSettings(): void
    {
        foreach (['bonusReferrer', 'bonusReferred', 'monthlyLimit', 'cookieDays', 'bannerDelay', 'bannerSnooze'] as $f) {
            $this->{$f} = preg_replace('/\D+/', '', $this->{$f});
        }

        $this->validate([
            'bonusReferrer' => ['required', 'integer', 'min:0', 'max:1000'],
            'bonusReferred' => ['required', 'integer', 'min:0', 'max:1000'],
            'monthlyLimit' => ['required', 'integer', 'min:0', 'max:1000'],
            'cookieDays' => ['required', 'integer', 'min:1', 'max:365'],
            'bannerDelay' => ['required', 'integer', 'min:0', 'max:365'],
            'bannerSnooze' => ['required', 'integer', 'min:1', 'max:3650'],
        ], [
            'required' => 'Укажите число',
            'cookieDays.min' => 'Не меньше 1 дня',
            'bannerSnooze.min' => 'Не меньше 1 дня',
        ]);

        ReferralService::saveSettings([
            'referral_enabled' => $this->on,
            'referral_bonus_referrer' => $this->bonusReferrer,
            'referral_bonus_referred' => $this->bonusReferred,
            'referral_monthly_limit' => $this->monthlyLimit,
            'referral_cookie_days' => $this->cookieDays,
            'referral_banner_enabled' => $this->banner,
            'referral_banner_delay_days' => $this->bannerDelay,
            'referral_banner_snooze_days' => $this->bannerSnooze,
        ]);

        $this->settingsOpen = false;
        $this->dispatch('toast', message: 'Настройки программы сохранены');
    }

    private function query(): Builder
    {
        $q = trim($this->q);

        return ReferralReward::query()
            ->with(['referrer', 'referred', 'payment.tariff'])
            ->when($q !== '', fn (Builder $w) => $w->where(fn (Builder $x) => $x
                ->whereHas('referrer', fn (Builder $u) => $u->where('name', 'like', '%' . $q . '%')->orWhere('email', 'like', '%' . $q . '%'))
                ->orWhereHas('referred', fn (Builder $u) => $u->where('name', 'like', '%' . $q . '%')->orWhere('email', 'like', '%' . $q . '%'))));
    }

    public function render()
    {
        $lessons = fn (int $n) => plural_ru($n, 'занятие', 'занятия', 'занятий');
        $s = ReferralService::settings();
        $counts = (clone $this->query())->reorder()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $list = $this->query()->when($this->filter !== 'all', fn (Builder $w) => $w->where('status', $this->filter))->latest()->latest('id');
        $total = (clone $list)->count();
        $summary = ReferralService::monthSummary(now());
        $month = Str::ucfirst(HumanDate::month(now()));

        return view('livewire.cabinet.admin.referrals', [
            'factLine' => $s['referral_enabled']
                ? 'Включена · ' . $lessons((int) $s['referral_bonus_referrer']) . ' пригласившему, ' . $s['referral_bonus_referred'] . ' приглашённому · '
                    . ($s['referral_monthly_limit'] ? 'не больше ' . plural_ru((int) $s['referral_monthly_limit'], 'начисления', 'начислений', 'начислений') . ' в месяц' : 'без лимита в месяц')
                : 'Выключена — новые бонусы не начисляются',
            'filters' => [
                'all' => 'Все',
                ReferralReward::STATUS_LIMIT => 'Лимит в месяц · ' . (int) ($counts[ReferralReward::STATUS_LIMIT] ?? 0),
                ReferralReward::STATUS_REJECTED => 'Отклонено · ' . (int) ($counts[ReferralReward::STATUS_REJECTED] ?? 0),
                ReferralReward::STATUS_REVOKED => 'Отозвано · ' . (int) ($counts[ReferralReward::STATUS_REVOKED] ?? 0),
            ],
            'rows' => $list->limit($this->limit)->get()->map(fn (ReferralReward $r) => $this->row($r)),
            'total' => $total,
            'month' => $month,
            'summary' => $summary,
            'top' => ReferralService::topReferrers(),
            'lessons' => $lessons,
        ]);
    }

    private function row(ReferralReward $r): array
    {
        $p = $r->payment;
        $revoked = $r->status === ReferralReward::STATUS_REVOKED;

        return [
            'id' => $r->id,
            'from' => $r->referrer?->name ?? 'Удалённый учитель',
            'fromUrl' => Payments::userUrl($r->referrer_id),
            'to' => $r->referred?->name ?? 'Удалённый учитель',
            'toUrl' => Payments::userUrl($r->referred_id),
            'meta' => implode(' · ', array_filter([
                $p?->tariff ? 'Тариф «' . $p->tariff->name . '»' . ((int) $p->period_days >= 365 ? ' на год' : '') : null,
                $p ? Money::format((int) round((float) $p->amount)) : null,
                HumanDate::day($r->created_at) === 'сегодня' || HumanDate::day($r->created_at) === 'вчера' ? HumanDate::at($r->created_at) : HumanDate::day($r->created_at),
            ])),
            'a' => ($r->referrer_lessons > 0 ? '+' : '') . $r->referrer_lessons,
            'b' => ($r->referred_lessons > 0 ? '+' : '') . $r->referred_lessons,
            'aOff' => $revoked || $r->referrer_lessons === 0,
            'bOff' => $revoked || $r->referred_lessons === 0,
            'status' => $r->status,
            'badge' => [
                ReferralReward::STATUS_LIMIT => 'Лимит в месяц',
                ReferralReward::STATUS_REJECTED => 'Отклонено',
                ReferralReward::STATUS_REVOKED => 'Отозвано из-за возврата',
            ][$r->status] ?? null,
            'note' => match ($r->status) {
                ReferralReward::STATUS_LIMIT => 'у пригласившего уже ' . plural_ru(ReferralService::creditedInMonthBefore($r), 'начисление', 'начисления', 'начислений') . ' в ' . $this->monthIn($r->created_at),
                ReferralReward::STATUS_REJECTED => match (true) {
                    str_contains((string) $r->note, 'телефон') => 'похоже на приглашение себя — совпал телефон',
                    str_contains((string) $r->note, 'способ оплаты') => 'похоже на приглашение себя — совпала карта',
                    default => $r->note ? Str::lcfirst($r->note) : null,
                },
                ReferralReward::STATUS_REVOKED => 'возврат ' . HumanDate::day($r->revoked_at ?? $r->updated_at),
                default => null,
            },
        ];
    }

    /** «сентябре» — месяц в предложном падеже. */
    private function monthIn(\Illuminate\Support\Carbon $date): string
    {
        return ['январе', 'феврале', 'марте', 'апреле', 'мае', 'июне', 'июле', 'августе', 'сентябре', 'октябре', 'ноябре', 'декабре'][$date->month - 1];
    }
}
