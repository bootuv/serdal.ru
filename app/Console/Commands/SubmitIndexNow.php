<?php

namespace App\Console\Commands;

use App\Services\IndexNowService;
use Illuminate\Console\Command;

/** Отправить изменённые публичные страницы в IndexNow (Яндекс, Bing). По расписанию — раз в сутки. */
class SubmitIndexNow extends Command
{
    protected $signature = 'seo:indexnow {--all : Отправить все адреса из карты сайта, а не только изменённые}';

    protected $description = 'Сообщить Яндексу и Bing об изменённых страницах сайта (IndexNow)';

    public function handle(IndexNowService $indexNow): int
    {
        if (!$indexNow->enabled()) {
            $this->info('IndexNow не отправляется: индексация выключена в админке или это не прод.');

            return self::SUCCESS;
        }

        $urls = $indexNow->changedUrls((bool) $this->option('all'));
        if ($urls === []) {
            $this->info('Изменённых страниц нет.');

            return self::SUCCESS;
        }

        foreach ($indexNow->submit($urls) as $endpoint => $status) {
            $accepted = in_array($status, [200, 202], true);
            $this->{$accepted ? 'info' : 'warn'}("{$endpoint}: " . ($accepted ? 'принято' : "ответ {$status}"));
        }
        $this->info('Отправлено адресов: ' . count($urls));

        return self::SUCCESS;
    }
}
