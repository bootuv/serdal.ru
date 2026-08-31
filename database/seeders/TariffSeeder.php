<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    /**
     * Тарифы из модели экономики Serdal (Google-таблица «SERDAL — модель экономики платформы»).
     */
    public function run(): void
    {
        $tariffs = [
            [
                'slug' => 'start',
                'name' => 'Старт',
                'price' => 0,
                'lessons_per_month' => 8,
                'max_participants' => 2,
                'max_duration_minutes' => 60,
                'recording_retention_days' => null,
                'short_description' => 'Для знакомства с платформой: индивидуальные занятия 1-на-1.',
                'description' => 'Бесплатный тариф для начинающих репетиторов. Позволяет проводить индивидуальные онлайн-занятия и познакомиться со всеми базовыми возможностями платформы.',
                'features' => [
                    'Виртуальная доска и демонстрация экрана',
                    'Расписание и напоминания ученикам',
                    'Домашние задания и проверка работ',
                    'Материалы для учеников',
                    'Успеваемость учеников',
                    'Чат с учениками',
                    'Учёт оплат учеников',
                    'Доступ в сообщество репетиторов',
                ],
                'is_popular' => false,
                'sort' => 10,
            ],
            [
                'slug' => 'basic',
                'name' => 'Базовый',
                'price' => 1490,
                'lessons_per_month' => 40,
                'max_participants' => 6,
                'max_duration_minutes' => 90,
                'recording_retention_days' => 14,
                'short_description' => 'Для активных репетиторов: мини-группы и записи занятий.',
                'description' => 'Тариф для репетиторов с регулярной нагрузкой. Поддерживает мини-группы до 6 участников, записи занятий и доступ к базе знаний платформы.',
                'features' => [
                    'Всё из тарифа «Старт»',
                    'Записи занятий',
                    'База знаний для преподавателей',
                    'Чат поддержки',
                ],
                'is_popular' => false,
                'sort' => 20,
            ],
            [
                'slug' => 'pro',
                'name' => 'Профи',
                'price' => 2990,
                'lessons_per_month' => 120,
                'max_participants' => 12,
                'max_duration_minutes' => null,
                'recording_retention_days' => 90,
                'short_description' => 'Для профессионалов: группы до 12 учеников, без лимита длительности.',
                'description' => 'Тариф для репетиторов, работающих с группами. Без ограничения длительности занятий, расширенное хранение записей, приоритетная поддержка и личная страница преподавателя на сайте.',
                'features' => [
                    'Всё из тарифа «Базовый»',
                    'Приоритетная поддержка',
                    '1 обучающий тренинг в месяц',
                    'Личная страница преподавателя',
                ],
                'is_popular' => true,
                'sort' => 30,
            ],
            [
                'slug' => 'master',
                'name' => 'Мастер',
                'price' => 6900,
                'lessons_per_month' => 200,
                'max_participants' => 25,
                'max_duration_minutes' => null,
                'recording_retention_days' => 180,
                'short_description' => 'Максимум возможностей: вебинары до 25 участников, брендинг и аналитика.',
                'description' => 'Тариф для востребованных преподавателей и мини-школ: вебинары до 25 участников, собственный поддомен и брендинг, расширенная аналитика занятий.',
                'features' => [
                    'Всё из тарифа «Профи»',
                    'Поддомен и брендинг',
                    'Аналитика занятий и посещаемости',
                    '2 тренинга в квартал',
                ],
                'is_popular' => false,
                'sort' => 40,
            ],
        ];

        foreach ($tariffs as $tariff) {
            Tariff::updateOrCreate(['slug' => $tariff['slug']], $tariff);
        }
    }
}
