<?php

namespace App\Console\Commands;

use App\Services\AnnouncementService;
use Illuminate\Console\Command;

/** Запланированные новости: время публикации наступило — рассылаем уведомления. */
class PublishAnnouncements extends Command
{
    protected $signature = 'news:publish';

    protected $description = 'Разослать уведомления о новостях, время публикации которых наступило';

    public function handle(AnnouncementService $service): int
    {
        $sent = $service->publishDue();
        if ($sent) {
            $this->info("Разослано новостей: {$sent}");
        }

        return self::SUCCESS;
    }
}
