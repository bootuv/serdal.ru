<?php

namespace App\Services;

use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Партнёрская программа: учитель приглашает коллегу по ссылке, и когда тот
 * впервые оплачивает платный тариф, оба получают бонусные занятия на баланс
 * дополнительных занятий (не сгорают, расходуются после лимита тарифа).
 * Все параметры задаются в админке: Настройки → Партнёрская программа.
 */
class ReferralService
{
    /** Cookie со ссылкой-приглашением. */
    const COOKIE = 'serdal_ref';

    const DEFAULTS = [
        'referral_enabled' => '1',
        'referral_bonus_referrer' => '10',
        'referral_bonus_referred' => '5',
        'referral_monthly_limit' => '0',
        'referral_cookie_days' => '30',
        'referral_banner_enabled' => '1',
        'referral_banner_delay_days' => '3',
        'referral_banner_snooze_days' => '30',
    ];

    protected static function setting(string $key): string
    {
        $value = Setting::where('key', $key)->value('value');

        return $value === null || $value === '' ? self::DEFAULTS[$key] : (string) $value;
    }

    public static function enabled(): bool
    {
        return self::setting('referral_enabled') === '1';
    }

    /** Бонус пригласившему по умолчанию (тариф может его переопределить). */
    public static function referrerBonus(): int
    {
        return max(0, (int) self::setting('referral_bonus_referrer'));
    }

    /** Бонус приглашённому за первую оплату. */
    public static function referredBonus(): int
    {
        return max(0, (int) self::setting('referral_bonus_referred'));
    }

    /** Максимум начислений пригласившему в календарный месяц (0 — без ограничения). */
    public static function monthlyLimit(): int
    {
        return max(0, (int) self::setting('referral_monthly_limit'));
    }

    /** Сколько дней ссылка-приглашение помнится в браузере. */
    public static function cookieDays(): int
    {
        return max(1, (int) self::setting('referral_cookie_days'));
    }

    /** Показывать баннер программы на инфопанели учителя. */
    public static function bannerEnabled(): bool
    {
        return self::enabled() && self::setting('referral_banner_enabled') === '1';
    }

    /** Через сколько дней после регистрации учитель впервые видит баннер. */
    public static function bannerDelayDays(): int
    {
        return max(0, (int) self::setting('referral_banner_delay_days'));
    }

    /** На сколько дней баннер скрывается после нажатия «×». */
    public static function bannerSnoozeDays(): int
    {
        return max(1, (int) self::setting('referral_banner_snooze_days'));
    }

    /**
     * Баннер показывается учителю, который уже освоился (прошло N дней с
     * регистрации) и не скрыл его недавно.
     */
    public static function shouldShowBanner(User $user): bool
    {
        if (!self::bannerEnabled()) {
            return false;
        }

        if ($user->created_at && $user->created_at->gt(now()->subDays(self::bannerDelayDays()))) {
            return false;
        }

        return !$user->referral_banner_hidden_until || $user->referral_banner_hidden_until->isPast();
    }

    public static function hideBanner(User $user): void
    {
        $user->forceFill(['referral_banner_hidden_until' => now()->addDays(self::bannerSnoozeDays())])->save();
    }

    /** Бонус пригласившему за оплату тарифа платежа. */
    public static function referrerBonusFor(SubscriptionPayment $payment): int
    {
        $tariffBonus = $payment->tariff?->referral_bonus;

        return $tariffBonus !== null ? (int) $tariffBonus : self::referrerBonus();
    }

    public static function inviteUrl(User $user): string
    {
        return route('referral.invite', $user->referralCode());
    }

    /**
     * Учитель — владелец реферального кода (приглашать могут только учителя).
     */
    public static function findReferrer(?string $code): ?User
    {
        if (!$code) {
            return null;
        }

        return User::where('referral_code', strtolower($code))
            ->whereIn('role', [User::ROLE_TUTOR, User::ROLE_MENTOR])
            ->first();
    }

    /**
     * Пригласивший для новой заявки: код из cookie, если программа включена
     * и заявитель не приглашает сам себя.
     */
    public static function referrerForApplication(?string $code, ?string $email): ?User
    {
        if (!self::enabled()) {
            return null;
        }

        $referrer = self::findReferrer($code);

        if (!$referrer || ($email && mb_strtolower($referrer->email) === mb_strtolower($email))) {
            return null;
        }

        return $referrer;
    }

    /**
     * Начисляет бонусы за первую оплату платного тарифа приглашённым учителем.
     * Вызывается из SubscriptionService::applyPaidPayment после активации подписки.
     */
    public static function rewardForPayment(SubscriptionPayment $payment): ?ReferralReward
    {
        if (!self::enabled() || $payment->isExtraLessons() || (int) $payment->amount <= 0) {
            return null;
        }

        $referred = $payment->user;
        $referrer = $referred?->referrer;

        if (!$referrer || $referrer->id === $referred->id) {
            return null;
        }

        $reward = DB::transaction(function () use ($payment, $referred, $referrer) {
            // Одно начисление на приглашённого — только за первую оплату
            if (ReferralReward::where('referred_id', $referred->id)->lockForUpdate()->exists()) {
                return null;
            }

            $status = ReferralReward::STATUS_CREDITED;
            $note = null;
            $referrerLessons = self::referrerBonusFor($payment);
            $referredLessons = self::referredBonus();

            if ($reason = self::selfReferralReason($referrer, $referred)) {
                $status = ReferralReward::STATUS_REJECTED;
                $note = $reason;
                $referrerLessons = 0;
                $referredLessons = 0;
            } elseif (self::monthlyLimit() > 0 && self::creditedThisMonth($referrer) >= self::monthlyLimit()) {
                $status = ReferralReward::STATUS_LIMIT;
                $note = 'Превышен лимит начислений в месяц (' . self::monthlyLimit() . ')';
                $referrerLessons = 0;
            }

            $reward = ReferralReward::create([
                'referrer_id' => $referrer->id,
                'referred_id' => $referred->id,
                'payment_id' => $payment->id,
                'referrer_lessons' => $referrerLessons,
                'referred_lessons' => $referredLessons,
                'status' => $status,
                'note' => $note,
            ]);

            self::addBalance($referrer, $referrerLessons);
            self::addBalance($referred, $referredLessons);

            return $reward;
        });

        if (!$reward) {
            return null;
        }

        if ($reward->referrer_lessons > 0) {
            $referrer->refresh();
            $referrer->notify(new \App\Notifications\ReferralBonusCredited(
                $reward->referrer_lessons,
                (int) $referrer->extra_lessons_balance,
                'Ваш коллега ' . $referred->name . ' оплатил тариф по вашему приглашению.',
            ));
        }

        if ($reward->referred_lessons > 0) {
            $referred->refresh();
            $referred->notify(new \App\Notifications\ReferralBonusCredited(
                $reward->referred_lessons,
                (int) $referred->extra_lessons_balance,
                'Подарок за регистрацию по приглашению коллеги.',
            ));
        }

        return $reward;
    }

    /**
     * Списывает бонусы после возврата платежа, за который они были начислены
     * (в пределах остатка — баланс не уходит в минус).
     */
    public static function revokeForPayment(SubscriptionPayment $payment): void
    {
        $reward = ReferralReward::where('payment_id', $payment->id)
            ->whereIn('status', [ReferralReward::STATUS_CREDITED, ReferralReward::STATUS_LIMIT])
            ->first();

        if (!$reward) {
            return;
        }

        DB::transaction(function () use ($reward) {
            self::addBalance($reward->referrer, -$reward->referrer_lessons);
            self::addBalance($reward->referred, -$reward->referred_lessons);

            $reward->update(['status' => ReferralReward::STATUS_REVOKED, 'revoked_at' => now()]);
        });
    }

    /**
     * Сводка для страницы «Пригласить коллегу».
     */
    public static function stats(User $user): array
    {
        $invited = User::where('referred_by_id', $user->id)->count()
            + \App\Models\TeacherApplication::where('referred_by_id', $user->id)->where('status', 'pending')->count();

        $paid = ReferralReward::where('referrer_id', $user->id)
            ->whereIn('status', [ReferralReward::STATUS_CREDITED, ReferralReward::STATUS_LIMIT])
            ->count();

        $lessons = (int) ReferralReward::where('referrer_id', $user->id)
            ->where('status', ReferralReward::STATUS_CREDITED)
            ->sum('referrer_lessons');

        return compact('invited', 'paid', 'lessons');
    }

    /**
     * Признаки того, что учитель пригласил сам себя: совпадает телефон
     * или сохранённый способ оплаты ЮKassa.
     */
    protected static function selfReferralReason(User $referrer, User $referred): ?string
    {
        $phone = fn(?string $value) => $value ? substr(preg_replace('/\D+/', '', $value), -10) : '';

        if ($phone($referrer->phone) !== '' && $phone($referrer->phone) === $phone($referred->phone)) {
            return 'Совпадает телефон пригласившего и приглашённого';
        }

        if ($referrer->yookassa_payment_method_id && $referrer->yookassa_payment_method_id === $referred->yookassa_payment_method_id) {
            return 'Совпадает сохранённый способ оплаты';
        }

        return null;
    }

    protected static function creditedThisMonth(User $referrer): int
    {
        return ReferralReward::where('referrer_id', $referrer->id)
            ->where('status', ReferralReward::STATUS_CREDITED)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    protected static function addBalance(?User $user, int $lessons): void
    {
        if (!$user || $lessons === 0) {
            return;
        }

        $locked = User::whereKey($user->id)->lockForUpdate()->first();
        $locked->update(['extra_lessons_balance' => max(0, (int) $locked->extra_lessons_balance + $lessons)]);

        SubscriptionService::flushCanStartCache();
    }
}
