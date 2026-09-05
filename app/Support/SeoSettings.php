<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Базовые SEO-настройки сайта: заголовки, описания, картинки для соцсетей,
 * индексация и коды подтверждения. Favicon в настройки не входит — это файлы проекта. Редактируются в админке
 * («Настройки» → вкладка «SEO»), здесь — ключи, значения по умолчанию и чтение.
 */
class SeoSettings
{
    public const DEFAULTS = [
        'seo_site_name' => 'Serdal',
        'seo_default_title' => 'Serdal — платформа для онлайн-занятий с репетиторами',
        'seo_default_description' => 'Serdal — платформа онлайн-занятий с репетиторами и менторами: видеоуроки в браузере, '
            . 'интерактивная доска, записи занятий, расписание, домашние задания и учёт оплат в одном кабинете. '
            . 'Найдите преподавателя и занимайтесь онлайн.',
        'seo_home_title' => 'Serdal — найти репетитора и заниматься онлайн',
        'seo_home_description' => 'Каталог репетиторов и менторов Serdal: подготовка к ЕГЭ и ОГЭ, школьные предметы, языки, '
            . 'олимпиады. Онлайн-занятия в браузере с интерактивной доской и записями уроков. '
            . 'Выберите преподавателя и свяжитесь с ним напрямую.',
        'seo_og_image' => '',
        'seo_apple_touch_icon' => '',
        'seo_logo' => '',
        'seo_indexing_enabled' => '1',
        'seo_ai_crawlers_enabled' => '1',
        'seo_yandex_verification' => '',
        'seo_google_verification' => '',
        'seo_social_links' => '',
        'seo_head_extra' => '',
        'seo_llms_description' => 'Serdal — российская платформа для онлайн-занятий с репетиторами и менторами. '
            . 'Преподаватели ведут уроки в браузере: виртуальные комнаты с видео, интерактивная доска, '
            . 'демонстрация экрана, записи занятий, расписание с напоминаниями, домашние задания, материалы, '
            . 'чат, учёт оплат и отзывы учеников. Ученики и родители находят преподавателя в каталоге на главной '
            . 'странице и связываются с ним напрямую.',
    ];

    /** Ключи, в которых хранится путь к файлу на диске s3. */
    public const FILE_KEYS = ['seo_og_image', 'seo_apple_touch_icon', 'seo_logo'];

    /** Картинки из public/images, которые используются, пока в админке ничего не загружено. */
    public const FILE_DEFAULTS = [
        'seo_og_image' => 'images/og-default.png',
        'seo_apple_touch_icon' => 'images/webclip.png',
        'seo_logo' => 'images/logo.png',
    ];

    /** Ключи кэшей, которые зависят от этих настроек. */
    public const CACHE_KEYS = ['seo.sitemap', 'seo.llms', 'seo.llms-full'];

    /** @var array<string, string>|null */
    protected static ?array $values = null;

    /** Все значения с подстановкой значений по умолчанию (один запрос на HTTP-запрос). */
    public static function all(): array
    {
        if (self::$values === null) {
            $stored = Setting::whereIn('key', array_keys(self::DEFAULTS))
                ->pluck('value', 'key')
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->all();

            self::$values = $stored + self::DEFAULTS;
        }

        return self::$values;
    }

    public static function get(string $key): string
    {
        return (string) (self::all()[$key] ?? self::DEFAULTS[$key] ?? '');
    }

    public static function enabled(string $key): bool
    {
        return self::get($key) !== '0';
    }

    /** Абсолютный адрес картинки: загруженный файл с s3 или файл проекта по умолчанию. */
    public static function fileUrl(string $key): string
    {
        $path = self::get($key);

        if ($path !== '') {
            try {
                return Storage::disk('s3')->url($path);
            } catch (\Throwable $e) {
                // Диск не настроен (например, локально) — показываем стандартный файл
                report($e);
            }
        }

        return Seo::url(self::FILE_DEFAULTS[$key] ?? '');
    }

    /** Ссылки на соцсети и каталоги: по одной в строке, для sameAs в разметке Organization. */
    public static function socialLinks(): array
    {
        return collect(preg_split('/\R+/', self::get('seo_social_links')))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '' && filter_var($line, FILTER_VALIDATE_URL))
            ->values()
            ->all();
    }

    /** Сбросить локальный кэш значений и кэш служебных файлов (после сохранения в админке). */
    public static function flush(): void
    {
        self::$values = null;

        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }
}
