<?php

namespace App\Console\Commands;

use App\Services\PaymentRecordService;
use Illuminate\Console\Command;

class GenerateMonthlyPaymentRecords extends Command
{
    protected $signature = 'payments:generate-monthly';

    protected $description = 'Создать помесячные начисления за текущий месяц для учеников с помесячной оплатой';

    public function handle(): int
    {
        $created = PaymentRecordService::generateMonthlyRecords();

        $this->info("Создано помесячных начислений: {$created}");

        return self::SUCCESS;
    }
}
