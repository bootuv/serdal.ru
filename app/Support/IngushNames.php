<?php

namespace App\Support;

/**
 * Ингушские имена для тестовых и демо-данных (фабрика пользователей, команда demo:ingush-names).
 * Фамилия в женском роде — с окончанием «-а»; отчество — от мужского имени.
 */
class IngushNames
{
    public const SURNAMES = [
        'Евлоев', 'Мальсагов', 'Оздоев', 'Аушев', 'Котиев', 'Плиев', 'Цечоев', 'Дзейтов', 'Гагиев', 'Барахоев',
        'Ахриев', 'Хамхоев', 'Костоев', 'Яндиев', 'Мержоев', 'Гандалоев', 'Богатырев', 'Арсамаков', 'Точиев', 'Кодзоев',
        'Тумгоев', 'Нальгиев', 'Султыгов', 'Батыжев', 'Келигов', 'Бузуртанов', 'Хашагульгов', 'Картоев', 'Гайтукиев', 'Албогачиев',
        'Дакиев', 'Льянов', 'Чахкиев', 'Зязиков', 'Беков', 'Тутаев', 'Эсмурзиев', 'Ужахов', 'Матиев', 'Хаутиев',
    ];

    public const MALE = [
        'Магомед', 'Ахмед', 'Ибрагим', 'Ислам', 'Адам', 'Муса', 'Хасан', 'Хусейн', 'Руслан', 'Тимур',
        'Беслан', 'Мурад', 'Алихан', 'Мовсар', 'Батыр', 'Умар', 'Юсуп', 'Башир', 'Зелимхан', 'Ильяс',
        'Амир', 'Иса', 'Али', 'Аслан', 'Султан', 'Якуб', 'Мухаммад', 'Рамзан', 'Багаудин', 'Микаил',
    ];

    public const FEMALE = [
        'Мадина', 'Зарема', 'Фатима', 'Хава', 'Лейла', 'Марем', 'Аминат', 'Хадижат', 'Седа', 'Танзила',
        'Зулихан', 'Петимат', 'Макка', 'Лиана', 'Асет', 'Тамила', 'Айна', 'Луиза', 'Раяна', 'Зайнаб',
        'Милана', 'Элина', 'Аза', 'Роза', 'Мариам', 'Радима', 'Ясмина', 'Хеди', 'Залина', 'Тоита',
    ];

    /** Отчество от мужского имени: Магомед → Магомедович / Магомедовна, Иса → Исаевич / Исаевна. */
    public static function patronymic(string $father, bool $female): string
    {
        // На гласную (Иса, Муса, Али) — «-ев» к полному имени: Исаевич, Алиевич; на шипящую или «й» — «-ев», иначе «-ов»
        $base = $father . (preg_match('/[аяиежшчщцй]$/u', $father) ? 'ев' : 'ов');

        return $base . ($female ? 'на' : 'ич');
    }

    public static function surname(string $base, bool $female): string
    {
        return $female ? $base . 'а' : $base;
    }

    /** ['last_name', 'first_name', 'middle_name'] — middle_name только при $withPatronymic. */
    public static function random(?bool $female = null, bool $withPatronymic = true): array
    {
        $female ??= (bool) random_int(0, 1);

        return [
            'last_name' => self::surname(self::SURNAMES[array_rand(self::SURNAMES)], $female),
            'first_name' => ($female ? self::FEMALE : self::MALE)[array_rand($female ? self::FEMALE : self::MALE)],
            'middle_name' => $withPatronymic ? self::patronymic(self::MALE[array_rand(self::MALE)], $female) : null,
        ];
    }

    public static function full(?bool $female = null, bool $withPatronymic = true): string
    {
        return implode(' ', array_filter(self::random($female, $withPatronymic)));
    }
}
