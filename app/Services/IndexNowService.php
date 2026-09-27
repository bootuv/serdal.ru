<?php

namespace App\Services;

use App\Support\Seo;
use App\Support\SeoSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * IndexNow — протокол мгновенного оповещения поисковиков об изменённых страницах.
 * Его поддерживают Яндекс и Bing (Bing передаёт адреса дальше, в том числе поиску ChatGPT и Copilot).
 * Ключ подтверждает владение сайтом: он лежит по адресу /indexnow.txt.
 */
class IndexNowService
{
    /** Куда отправлять: Яндекс напрямую и общая точка (Bing, Seznam, Naver и другие). */
    public const ENDPOINTS = [
        'https://yandex.com/indexnow',
        'https://api.indexnow.org/indexnow',
    ];

    /** За один запрос протокол принимает до 10 000 адресов. */
    private const BATCH = 10000;

    private const LAST_RUN_KEY = 'seo.indexnow.last_run';

    /** Ключ из INDEXNOW_KEY или постоянный, выведенный из ключа приложения (32 символа a-f0-9). */
    public static function key(): string
    {
        $key = (string) config('services.indexnow.key');

        return $key !== '' ? $key : substr(hash('sha256', 'indexnow|' . config('app.key')), 0, 32);
    }

    /** Можно ли отправлять: индексация сайта включена в админке и это не тестовый стенд. */
    public function enabled(): bool
    {
        return SeoSettings::enabled('seo_indexing_enabled') && app()->environment('production');
    }

    /**
     * Адреса из карты сайта, изменившиеся с прошлой отправки (или все при $all).
     *
     * @return array<int, string>
     */
    public function changedUrls(bool $all = false): array
    {
        // Первая отправка (или сброшенный кэш) — все адреса; дальше — только с датой изменения не раньше прошлой
        $since = $all ? null : Cache::get(self::LAST_RUN_KEY);

        return collect(app(SitemapService::class)->urls())
            ->filter(fn ($url) => $since === null || ($url['lastmod'] !== null && $url['lastmod'] >= $since))
            ->pluck('loc')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Отправить адреса во все точки IndexNow.
     *
     * @param  array<int, string>  $urls
     * @return array<string, int> код ответа каждой точки (200/202 — принято)
     */
    public function submit(array $urls): array
    {
        $results = [];
        if ($urls === []) {
            return $results;
        }

        $host = parse_url(Seo::baseUrl(), PHP_URL_HOST);

        foreach (self::ENDPOINTS as $endpoint) {
            foreach (array_chunk($urls, self::BATCH) as $batch) {
                try {
                    $response = Http::timeout(20)->acceptJson()->post($endpoint, [
                        'host' => $host,
                        'key' => self::key(),
                        'keyLocation' => Seo::url(route('seo.indexnow', [], false)),
                        'urlList' => $batch,
                    ]);
                    $results[$endpoint] = $response->status();
                } catch (\Throwable $e) {
                    report($e);
                    $results[$endpoint] = 0;
                }
            }
        }

        Cache::forever(self::LAST_RUN_KEY, now()->toDateString());

        return $results;
    }
}
