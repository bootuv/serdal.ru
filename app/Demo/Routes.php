<?php

namespace App\Demo;

/**
 * Адреса демо-кабинета повторяют адреса настоящего: /cabinet/teacher/… ↔ /demo/teacher/….
 * Поэтому ссылки в шаблонах строятся обычным route('cabinet.teacher.…'), а в ответе
 * DemoController меняет префикс — ничего не нужно переписывать в самих шаблонах.
 *
 * Экран объявляет свой путь константой PATH ('students/{id}'); таблица собирается из app/Demo/{Role}/*.php.
 */
final class Routes
{
    /** Вложенные Livewire-компоненты, которые в демо всегда подменяются (Stub): по умолчанию — пусто. */
    public const STUBS = [
        'cabinet.news-banner',
        'cabinet.teacher.platform-review',
        'cabinet.referral-promo',
        'cabinet.notifications',
        'cabinet.push-prompt',
        'cabinet.tour',
    ];

    /** @return array<string, class-string<Screen>> путь => экран; пути без параметров — первыми */
    public static function table(string $role): array
    {
        $namespace = __NAMESPACE__ . '\\' . ucfirst($role);
        $table = [];

        foreach (glob(__DIR__ . '/' . ucfirst($role) . '/*.php') as $file) {
            $class = $namespace . '\\' . basename($file, '.php');
            if (is_subclass_of($class, Screen::class) && defined($class . '::PATH')) {
                $table[$class::PATH] = $class;
            }
        }

        uksort($table, fn ($a, $b) => [str_contains($a, '{'), $a] <=> [str_contains($b, '{'), $b]);

        return $table;
    }

    /** @return array{0: class-string<Screen>, 1: array}|null */
    public static function match(string $role, string $path): ?array
    {
        $path = trim($path, '/');

        foreach (self::table($role) as $pattern => $class) {
            $regex = '#^' . preg_replace('#\\\{(\w+)\\\}#', '(?<$1>[^/]+)', preg_quote($pattern, '#')) . '$#u';
            if (preg_match($regex, $path, $m)) {
                return [$class, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }

        return null;
    }
}
