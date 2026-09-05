<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Единая точка для SEO-данных публичных страниц: заголовки по умолчанию,
 * канонические адреса и JSON-LD разметка (schema.org).
 */
class Seo
{
    public const SITE_NAME = 'Serdal';

    public const DEFAULT_ROBOTS = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    public const NOINDEX_ROBOTS = 'noindex, nofollow';

    /** Название сайта из настроек админки. */
    public static function siteName(): string
    {
        return SeoSettings::get('seo_site_name') ?: self::SITE_NAME;
    }

    public static function defaultTitle(): string
    {
        return SeoSettings::get('seo_default_title');
    }

    public static function defaultDescription(): string
    {
        return SeoSettings::get('seo_default_description');
    }

    /** Значение meta robots: если индексация выключена в админке, весь сайт закрыт. */
    public static function robots(?string $pageValue = null): string
    {
        if (!SeoSettings::enabled('seo_indexing_enabled')) {
            return self::NOINDEX_ROBOTS;
        }

        return $pageValue ?: self::DEFAULT_ROBOTS;
    }

    /** Публичный адрес сайта без завершающего слэша. */
    public static function baseUrl(): string
    {
        return rtrim(OfferSettings::platform()['url'], '/');
    }

    /** Абсолютный адрес по относительному пути (например, route(..., absolute: false)). */
    public static function url(string $path = '/'): string
    {
        return self::baseUrl() . '/' . ltrim($path, '/');
    }

    /** Канонический адрес текущей страницы: без query-строки, на публичном домене. */
    public static function canonical(): string
    {
        $path = trim(request()->path(), '/');

        return self::baseUrl() . '/' . $path;
    }

    /** Картинка для соцсетей по умолчанию (из админки или images/og-default.png). */
    public static function defaultImage(): string
    {
        return SeoSettings::fileUrl('seo_og_image');
    }

    /** Чистый текст для description: без HTML, лишних пробелов и длиннее лимита. */
    public static function text(?string $html, int $limit = 160): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return Str::limit($text, $limit, '…');
    }

    /** Тег <script type="application/ld+json"> с данными. */
    public static function jsonLd(array $data): string
    {
        if (!isset($data['@context'])) {
            $data = ['@context' => 'https://schema.org'] + $data;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Защита от закрытия тега внутри строк
        $json = str_replace('</', '<\/', $json);

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    public static function organization(): array
    {
        $legal = OfferSettings::legal();

        $data = [
            '@type' => 'Organization',
            '@id' => self::url('#organization'),
            'name' => self::siteName(),
            'url' => self::url('/'),
            'logo' => SeoSettings::fileUrl('seo_logo'),
            'description' => 'Платформа для онлайн-занятий с репетиторами и менторами: виртуальные комнаты, '
                . 'интерактивная доска, расписание, домашние задания и учёт оплат.',
            'email' => $legal['legal_email'],
            'areaServed' => 'RU',
            'knowsLanguage' => 'ru',
        ];

        if (!empty($legal['legal_name'])) {
            $data['legalName'] = $legal['legal_name'];
        }

        if ($links = SeoSettings::socialLinks()) {
            $data['sameAs'] = $links;
        }

        if (!empty($legal['legal_phone'])) {
            $data['telephone'] = $legal['legal_phone'];
        }

        if (!empty($legal['legal_address'])) {
            $data['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $legal['legal_address'],
                'addressCountry' => 'RU',
            ];
        }

        return $data;
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::url('#website'),
            'name' => self::siteName(),
            'url' => self::url('/'),
            'inLanguage' => 'ru-RU',
            'publisher' => ['@id' => self::url('#organization')],
        ];
    }

    /**
     * BreadcrumbList из списка [['name' => ..., 'url' => ...], ...].
     * Первым элементом всегда идёт главная страница.
     */
    public static function breadcrumbs(array $items): array
    {
        $list = [['name' => 'Главная', 'url' => self::url('/')]];

        foreach ($items as $item) {
            $list[] = $item;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($list)->values()->map(fn ($item, $index) => array_filter([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'] ?? null,
            ]))->all(),
        ];
    }
}
