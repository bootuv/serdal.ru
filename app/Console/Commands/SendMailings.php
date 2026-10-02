<?php

namespace App\Console\Commands;

use App\Services\MailingService;
use Illuminate\Console\Command;

/** Рассылки (админка «Рассылки»): запускает запланированные письма и отправляет следующую порцию. */
class SendMailings extends Command
{
    protected $signature = 'mailings:send {--limit= : Сколько писем отправить за запуск (по умолчанию NEWSLETTER_PER_MINUTE)}';

    protected $description = 'Отправить следующую порцию писем рассылки';

    public function handle(MailingService $service): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $sent = $service->sendDue($limit);
        if ($sent) {
            $this->info("Отправлено писем: {$sent}");
        }

        return self::SUCCESS;
    }
}
