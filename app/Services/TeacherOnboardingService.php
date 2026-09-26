<?php

namespace App\Services;

use App\Models\LessonType;
use App\Models\Tariff;
use App\Models\User;

/**
 * Первые шаги учителя (Профиль → Цены → Тариф). Используется старым кабинетом
 * (Filament App\Pages\Onboarding) и новым (Cabinet\Teacher\Onboarding).
 */
class TeacherOnboardingService
{
    /** Первая цена по умолчанию: шаг «Цены» сразу показывает открытую форму. */
    public const DEFAULT_LESSON_TYPE = [
        'type' => LessonType::TYPE_INDIVIDUAL,
        'payment_type' => LessonType::PAYMENT_PER_LESSON,
        'price' => null,
        'count_per_week' => null,
        'duration' => 60,
    ];

    /** Цены учителя для шага «Цены» (или одна пустая строка). */
    public static function lessonTypesForForm(User $user): array
    {
        $existing = $user->lessonTypes()
            ->get(['type', 'payment_type', 'price', 'count_per_week', 'duration'])
            ->map->only(['type', 'payment_type', 'price', 'count_per_week', 'duration'])
            ->all();

        return $existing ?: [self::DEFAULT_LESSON_TYPE];
    }

    /** Предвыбор тарифа: выбранный на публичной странице при подаче заявки, иначе бесплатный. */
    public static function defaultTariffId(User $user): ?int
    {
        $desired = $user->desired_tariff_id ? Tariff::active()->find($user->desired_tariff_id) : null;

        return $desired?->id ?? Tariff::active()->where('price', 0)->value('id');
    }

    /**
     * Завершить настройку: цены, фото и контакты (и, если переданы, направления и классы),
     * бесплатный «Старт» как база, уведомление админам. Выбран платный тариф и настроена
     * оплата — создаёт платёж.
     *
     * Возвращает ['tariff' => ?Tariff, 'payment_url' => ?string, 'payment_failed' => bool].
     */
    public function complete(User $user, array $data): array
    {
        // Пересоздаём базовые цены из шага «Цены для учеников»
        $user->lessonTypes()->delete();

        foreach ($data['lesson_types'] ?? [] as $item) {
            $user->lessonTypes()->create([
                'type' => $item['type'],
                'payment_type' => $item['payment_type'],
                'price' => $item['price'],
                'duration' => $item['duration'],
                'count_per_week' => ($item['payment_type'] ?? null) === LessonType::PAYMENT_MONTHLY ? ($item['count_per_week'] ?? null) : null,
            ]);
        }

        $profile = [
            'avatar' => $data['avatar'] ?? null,
            'whatsup' => $data['whatsup'] ?? null,
            'telegram' => $data['telegram'] ?? null,
            'is_profile_completed' => true,
        ];
        if (array_key_exists('grade', $data)) {
            $profile['grade'] = array_values($data['grade'] ?? []);
        }
        if (array_key_exists('directs', $data)) {
            $profile['directs'] = $data['directs'] ?? [];
        }

        app(TeacherProfileService::class)->update($user, $profile);

        // Бесплатный «Старт» — база до оплаты, чтобы пользователь
        // не остался без подписки, даже если передумает платить
        if (!$user->activeSubscription()) {
            $freeTariff = Tariff::active()->where('price', 0)->first();

            if ($freeTariff) {
                SubscriptionService::activate($user, $freeTariff);
            }
        }

        foreach (User::where('role', User::ROLE_ADMIN)->get() as $admin) {
            $admin->notify(new \App\Notifications\TeacherCompletedOnboarding($user));
        }

        // Выбран платный тариф — сразу уводим на платёжную страницу
        $tariff = isset($data['tariff_id']) ? Tariff::active()->find($data['tariff_id']) : null;
        $result = ['tariff' => $tariff, 'payment_url' => null, 'payment_failed' => false];

        if ($tariff && !$tariff->isFree() && YooKassaService::isConfigured()) {
            $payment = SubscriptionCheckoutService::createTariffPayment(
                $user,
                $tariff,
                ($data['billing_period'] ?? 'month') === 'year',
            );

            try {
                $result['payment_url'] = YooKassaService::createPayment(
                    $payment,
                    route('subscription.payment.return', $payment),
                    methodType: $data['payment_method'] ?? null,
                );
            } catch (\Throwable $e) {
                $payment->update(['status' => \App\Models\SubscriptionPayment::STATUS_FAILED]);
                $result['payment_failed'] = true;
            }
        }

        return $result;
    }
}
