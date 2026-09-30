<?php

namespace App\Console\Commands;

use App\Services\Bbb\BbbServerMonitor;
use Illuminate\Console\Command;

/** Проверка серверов видеосвязи и сверка идущих занятий (раз в минуту). */
class CheckBbbServers extends Command
{
    protected $signature = 'bbb:check-servers';

    protected $aliases = ['bbb:sync'];

    protected $description = 'Проверить серверы видеосвязи: связь, версия, нагрузка; сверить идущие занятия';

    public function handle(BbbServerMonitor $monitor): int
    {
        $monitor->checkAll();

        return self::SUCCESS;
    }
}
