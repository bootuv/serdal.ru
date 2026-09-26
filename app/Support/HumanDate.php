<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Даты «по-человечески» для кабинетов (docs/design/BRAND.md, раздел «Текст»):
 * «сегодня в 16:00», «завтра», «чт, 3 октября в 18:30», «через 25 минут».
 */
class HumanDate
{
    private const WEEKDAYS_SHORT = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];

    private const MONTHS_GENITIVE = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

    /** «сегодня», «завтра», «вчера», «чт, 3 октября» (+ год, если не текущий). */
    public static function day(CarbonInterface $date): string
    {
        $today = Carbon::today();
        $d = $date->copy()->startOfDay();

        return match (true) {
            $d->equalTo($today) => 'сегодня',
            $d->equalTo($today->copy()->addDay()) => 'завтра',
            $d->equalTo($today->copy()->subDay()) => 'вчера',
            default => self::WEEKDAYS_SHORT[$date->dayOfWeek] . ', ' . self::date($date),
        };
    }

    /** «сегодня в 16:00», «чт, 3 октября в 18:30». */
    public static function at(CarbonInterface $date): string
    {
        return self::day($date) . ' в ' . $date->format('H:i');
    }

    /** «3 октября» (+ год, если не текущий). */
    public static function date(CarbonInterface $date): string
    {
        $s = $date->day . ' ' . self::MONTHS_GENITIVE[$date->month];

        return $date->year === now()->year ? $s : $s . ' ' . $date->year;
    }

    /** Месяц: «сентябрь» (genitive — «сентября», для «с марта»; + год, если не текущий). */
    public static function month(CarbonInterface $date, bool $genitive = false): string
    {
        $nominative = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
            'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
        $s = $genitive ? self::MONTHS_GENITIVE[$date->month] : $nominative[$date->month];

        return $date->year === now()->year ? $s : $s . ' ' . $date->year;
    }

    /** «Четверг, 26 сентября» — для строки под приветствием. */
    public static function todayLong(): string
    {
        $now = now();
        $weekday = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'][$now->dayOfWeek];

        return mb_strtoupper(mb_substr($weekday, 0, 1)) . mb_substr($weekday, 1) . ', ' . self::date($now);
    }

    /** «через 25 минут», «через 2 часа», «через 3 дня». Для прошедшего — null. */
    public static function until(CarbonInterface $date): ?string
    {
        $minutes = (int) ceil(now()->diffInMinutes($date, false));

        return match (true) {
            $minutes < 0 => null,
            $minutes < 60 => 'через ' . plural_ru(max(1, $minutes), 'минуту', 'минуты', 'минут'),
            $minutes < 60 * 24 => 'через ' . plural_ru(intdiv($minutes, 60), 'час', 'часа', 'часов'),
            default => 'через ' . plural_ru(intdiv($minutes, 60 * 24), 'день', 'дня', 'дней'),
        };
    }
}
