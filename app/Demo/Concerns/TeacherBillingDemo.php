<?php

namespace App\Demo\Concerns;

use App\Demo\World;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Тариф и платежи учителя в демо (экраны «Тариф и платежи» и «Все платежи»): тарифы — как в TariffSeeder,
 * подписка «Профи» — как карточка тарифа на «Сегодня» (Today::tariff(): 64 из 120, ещё 23 дня), платежи — в памяти.
 */
trait TeacherBillingDemo
{
    /** Тарифы из database/seeders/TariffSeeder.php: id => [название, цена, занятий, участников, минут, дней записи, возможности, популярный]. */
    private static array $demoTariffs = [
        1 => ['Старт', 0, 8, 2, 60, null, [
            'Виртуальная доска и демонстрация экрана', 'Расписание и напоминания ученикам', 'Домашние задания и проверка работ',
            'Материалы для учеников', 'Успеваемость учеников', 'Чат с учениками', 'Учёт оплат учеников', 'Доступ в сообщество репетиторов',
        ], false],
        2 => ['Базовый', 1490, 40, 6, 90, 14, ['Всё из тарифа «Старт»', 'Записи занятий', 'База знаний для преподавателей', 'Чат поддержки'], false],
        3 => ['Профи', 2990, 120, 12, null, 90, ['Всё из тарифа «Базовый»', 'Приоритетная поддержка', '1 обучающий тренинг в месяц', 'Личная страница преподавателя'], true],
        4 => ['Мастер', 6900, 200, 25, null, 180, ['Всё из тарифа «Профи»', 'Поддомен и брендинг', 'Аналитика занятий и посещаемости', '2 тренинга в квартал'], false],
    ];

    /** Текущий тариф учителя. */
    protected const DEMO_TARIFF_ID = 3;

    /** @return Collection<int, Tariff> */
    protected static function demoTariffs(): Collection
    {
        return collect(self::$demoTariffs)->map(function (array $t, int $id) {
            [$name, $price, $lessons, $people, $minutes, $records, $features, $popular] = $t;
            $tariff = new Tariff;
            $tariff->forceFill([
                'id' => $id,
                'name' => $name,
                'slug' => ['start', 'basic', 'pro', 'master'][$id - 1],
                'price' => $price,
                'yearly_price' => null,
                'period_days' => 30,
                'lessons_per_month' => $lessons,
                'max_participants' => $people,
                'max_duration_minutes' => $minutes,
                'recording_retention_days' => $records,
                'features' => $features,
                'extra_features' => null,
                'is_active' => true,
                'is_popular' => $popular,
                'sort' => $id * 10,
            ]);
            $tariff->exists = true;

            return $tariff;
        });
    }

    protected static function demoTariff(int $id): ?Tariff
    {
        return self::demoTariffs()->get($id);
    }

    /** Подписка «Профи»: оплачена 7 дней назад, действует ещё 23 дня. */
    protected static function demoSubscription(): Subscription
    {
        $subscription = new Subscription;
        $subscription->forceFill([
            'id' => 1,
            'user_id' => World::TEACHER_ID,
            'tariff_id' => self::DEMO_TARIFF_ID,
            'status' => Subscription::STATUS_ACTIVE,
            'price' => 2990,
            'starts_at' => Carbon::now()->subDays(7),
            'ends_at' => Carbon::now()->addDays(23),
        ]);
        $subscription->exists = true;
        $subscription->setRelation('tariff', self::demoTariff(self::DEMO_TARIFF_ID));

        return $subscription;
    }

    /** Учитель с сохранённой картой и автопродлением ($autoRenew — из адреса). */
    protected static function demoBillingUser(bool $autoRenew = true): User
    {
        $user = World::teacher();
        $user->forceFill([
            'yookassa_payment_method_id' => 'demo-card',
            'payment_method_title' => 'Карта •••• 4417',
            'auto_renew' => $autoRenew,
            'extra_lessons_balance' => 0,
        ]);

        return $user;
    }

    /** Платежи учителя, новые сверху (SubscriptionCheckoutService::payments). */
    protected static function demoPayments(): Collection
    {
        $now = Carbon::now();
        $rows = [
            // [id, тариф, сумма, дней, доп. занятий, статус, когда, привязка карты]
            [9006, 3, 2990, 30, 0, SubscriptionPayment::STATUS_PAID, $now->copy()->subDays(7)->setTime(10, 14), false],
            [9005, null, 1000, 0, 10, SubscriptionPayment::STATUS_PAID, $now->copy()->subDays(15)->setTime(19, 42), false],
            [9004, 3, 2990, 30, 0, SubscriptionPayment::STATUS_PAID, $now->copy()->subDays(37)->setTime(9, 5), false],
            [9003, 2, 1490, 30, 0, SubscriptionPayment::STATUS_PAID, $now->copy()->subDays(67)->setTime(21, 30), false],
            [9002, 2, 1, 0, 0, SubscriptionPayment::STATUS_REFUNDED, $now->copy()->subDays(67)->setTime(21, 28), true],
            [9001, 2, 1490, 30, 0, SubscriptionPayment::STATUS_PAID, $now->copy()->subDays(97)->setTime(12, 3), false],
        ];

        return collect($rows)->map(function (array $r) {
            [$id, $tariffId, $amount, $days, $extra, $status, $at, $binding] = $r;
            $payment = new SubscriptionPayment;
            $payment->forceFill([
                'id' => $id,
                'user_id' => World::TEACHER_ID,
                'tariff_id' => $tariffId,
                'amount' => $amount,
                'period_days' => $days,
                'extra_lessons' => $extra,
                'status' => $status,
                'gateway' => 'yookassa',
                'payment_url' => null,
                'paid_at' => $at,
                'meta' => $binding ? ['card_binding' => true, 'refunded_at' => $at->copy()->addMinutes(2)->toIso8601String()] : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $payment->exists = true;
            $payment->setRelation('tariff', $tariffId ? self::demoTariff($tariffId) : null);

            return $payment;
        });
    }
}
