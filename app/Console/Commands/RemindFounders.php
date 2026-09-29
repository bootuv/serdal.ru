<?php

namespace App\Console\Commands;

use App\Services\FounderService;
use Illuminate\Console\Command;

/**
 * Напоминания основателям о ежемесячном сборе на расходы: за N дней до дня сбора, в день сбора
 * и через несколько дней после, если взнос не отмечен. Заодно заводит взносы текущего месяца для истории. Каждому — не больше одного письма в день. Запуск — раз в день.
 */
class RemindFounders extends Command
{
    protected $signature = 'founders:remind';

    protected $description = 'Напомнить основателям о взносе на расходы платформы';

    public function handle(FounderService $service): int
    {
        // Взносы текущего месяца записываются каждый день, даже без напоминаний, — чтобы в истории не было пропусков
        $service->sync($service->currentPeriod());
        $sent = $service->sendReminders();
        $this->info('Отправлено напоминаний: ' . $sent);

        return self::SUCCESS;
    }
}
