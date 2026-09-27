<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Формулировки посадочных страниц каталога: «Репетитор по математике», «Подготовка к ЕГЭ по физике».
 * Предметы и направления заводятся в базе, поэтому падеж строится по правилам, а исключения — в словарях.
 * Если правило не подходит, используется нейтральная форма «по предмету «…»».
 */
class SeoPhrases
{
    /** Предметы, для которых «по + дательный падеж» не подходит или правило ошибается. */
    private const SUBJECT_TOPICS = [
        'начальные классы' => 'начальных классов',
        'дополнительное образование' => 'по дополнительному образованию',
        'рки' => 'по русскому как иностранному',
    ];

    /**
     * Направления: заголовок страницы и шаблон для сочетания с предметом (%s — «по математике»).
     * null в шаблоне — направление с предметом не сочетаем (IT-наставничество по биологии не ищут).
     */
    private const DIRECTS = [
        'егэ' => ['Подготовка к ЕГЭ', 'Подготовка к ЕГЭ %s'],
        'огэ' => ['Подготовка к ОГЭ', 'Подготовка к ОГЭ %s'],
        'олимпиады' => ['Подготовка к олимпиадам', 'Подготовка к олимпиадам %s'],
        'дви' => ['Подготовка к ДВИ', 'Подготовка к ДВИ %s'],
        'впр' => ['Подготовка к ВПР', 'Подготовка к ВПР %s'],
        'начальная школа' => ['Репетиторы для начальной школы', 'Репетитор %s для начальной школы'],
        'язык с нуля' => ['Иностранный язык с нуля', 'Репетитор %s с нуля'],
        'успеваемость' => ['Помощь с учёбой и успеваемостью', 'Репетитор %s для успеваемости в школе'],
        'хобби' => ['Занятия для себя', 'Занятия %s для себя'],
        'it-наставничество' => ['IT-наставники', null],
        'компьютерная грамотность' => ['Обучение компьютерной грамотности', null],
    ];

    /** «по математике», «по английскому языку», «начальных классов». */
    public static function subjectTopic(string $name): string
    {
        $key = Str::lower(trim($name));

        if (isset(self::SUBJECT_TOPICS[$key])) {
            return self::SUBJECT_TOPICS[$key];
        }

        $dative = self::dative($key);

        return $dative !== null ? 'по ' . $dative : 'по предмету «' . trim($name) . '»';
    }

    /** «Репетитор по математике». */
    public static function subjectHeading(string $name): string
    {
        return 'Репетитор ' . self::subjectTopic($name);
    }

    /** «Подготовка к ЕГЭ». */
    public static function directHeading(string $name): string
    {
        $key = Str::lower(trim($name));

        return self::DIRECTS[$key][0] ?? 'Репетиторы по направлению «' . trim($name) . '»';
    }

    /** Направления только для части предметов: «с нуля» учат языки, а не литературу. */
    private const DIRECT_SUBJECTS = [
        'язык с нуля' => '/язык/u',
    ];

    /** Сочетается ли направление с предметом (есть ли страница «предмет + направление»). */
    public static function combinable(string $directName, string $subjectName): bool
    {
        $key = Str::lower(trim($directName));

        if (array_key_exists($key, self::DIRECTS) && self::DIRECTS[$key][1] === null) {
            return false;
        }

        return !isset(self::DIRECT_SUBJECTS[$key]) || preg_match(self::DIRECT_SUBJECTS[$key], Str::lower($subjectName));
    }

    /** «Подготовка к ЕГЭ по математике». */
    public static function comboHeading(string $subjectName, string $directName): string
    {
        $key = Str::lower(trim($directName));
        $topic = self::subjectTopic($subjectName);
        $pattern = self::DIRECTS[$key][1] ?? null;

        if ($pattern !== null) {
            return sprintf($pattern, $topic);
        }

        return 'Репетитор ' . $topic . ', направление «' . trim($directName) . '»';
    }

    /** Адрес страницы по названию: «Русский язык» → russkiy-yazyk. */
    public static function slug(string $name): string
    {
        return Str::slug(trim($name), '-', 'ru');
    }

    /**
     * Дательный падеж названия предмета: математика → математике, русский язык → русскому языку.
     * null — правило не знает этого слова.
     */
    private static function dative(string $name): ?string
    {
        $words = preg_split('/\s+/u', $name);

        // «английский язык», «русский язык»: прилагательное + «язык»
        if (count($words) === 2 && $words[1] === 'язык') {
            $adjective = preg_replace('/(ий|ый|ой)$/u', 'ому', $words[0], 1, $replaced);

            return $replaced ? $adjective . ' языку' : null;
        }

        if (count($words) !== 1) {
            return null;
        }

        $word = $words[0];

        return match (true) {
            (bool) preg_match('/ия$/u', $word) => mb_substr($word, 0, -2) . 'ии',   // химия → химии
            (bool) preg_match('/ие$/u', $word) => mb_substr($word, 0, -2) . 'ию',   // обществознание → обществознанию
            (bool) preg_match('/[^и]а$/u', $word) => mb_substr($word, 0, -1) . 'е', // физика → физике
            default => null,
        };
    }
}
