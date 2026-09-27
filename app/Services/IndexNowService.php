<?php

namespace App\Services;

use App\Support\Seo;
use App\Support\SeoSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Что уже отправлено: адреса с датой изменения на момент отправки. Лежит в файле, а не в кэше —
     * деплой чистит кэш (optimize:clear), и тогда каждый деплой слал бы весь сайт заново.
     */
    private const STATE_FILE = 'indexnow.json';

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
     * Адреса из карты сайта, которые стоит отправить: новые или с другой датой изменения, чем при прошлой
     * отправке. При $all — все.
     *
     * @return array<int, string>
     */
    public function changedUrls(bool $all = false): array
    {
        $sent = $all ? [] : $this->sent();

        return collect(app(SitemapService::class)->urls())
            ->filter(fn ($url) => !array_key_exists($url['loc'], $sent) || $sent[$url['loc']] !== $url['lastmod'])
            ->pluck('loc')
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, ?string> адрес => дата изменения, с которой он отправлен */
    private function sent(): array
    {
        $disk = Storage::disk('local');
        $state = $disk->exists(self::STATE_FILE) ? json_decode((string) $disk->get(self::STATE_FILE), true) : null;

        return is_array($state['urls'] ?? null) ? $state['urls'] : [];
    }

    /** @param  array<int, string>  $urls */
    private function remember(array $urls): void
    {
        $lastmod = collect(app(SitemapService::class)->urls())->pluck('lastmod', 'loc');
        $sent = $this->sent();
        foreach ($urls as $url) {
            $sent[$url] = $lastmod[$url] ?? null;
        }

        Storage::disk('local')->put(self::STATE_FILE, json_encode([
            'sent_at' => now()->toDateTimeString(),
            'urls' => $sent,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Отправляют и деплой (пользователь SSH), и планировщик (www-data): файл должен быть доступен обоим.
        // Внутри только публичные адреса сайта.
        @chmod(Storage::disk('local')->path(self::STATE_FILE), 0666);
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

        // Запоминаем, только если хотя бы один поисковик принял адреса — иначе попробуем в следующий раз
        if (array_intersect($results, [200, 202]) !== []) {
            $this->remember($urls);
        }

        return $results;
    }
}
