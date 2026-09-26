<?php

use App\Services\PaymentRecordService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_records', function (Blueprint $table) {
            // Сумма начисления, ₽, зафиксированная при создании (помесячные: цена за месяц).
            // У поурочных пусто — сумма берётся из снимка цен занятия.
            $table->unsignedInteger('amount')->nullable()->after('period');
        });

        // Неоплаченным помесячным — текущая цена за месяц, если её можно определить однозначно
        PaymentRecordService::backfillMonthlyAmounts();
    }

    public function down(): void
    {
        Schema::table('payment_records', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
