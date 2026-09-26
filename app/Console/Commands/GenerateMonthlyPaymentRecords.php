<?php

namespace App\Console\Commands;

use App\Services\PaymentRecordService;
use Illuminate\Console\Command;

class GenerateMonthlyPaymentRecords extends Command
{
    protected $signature = 'payments:generate-monthly';

    protected $description = 'Создать счета за текущий месяц ученикам с помесячной оплатой, у которых до конца месяца есть занятия (запускается каждый день)';

    public function handle(): int
    {
        $created = PaymentRecordService::generateMonthlyRecords();

        $this->info("Создано помесячных начислений: {$created}");

        return self::SUCCESS;
    }
}
