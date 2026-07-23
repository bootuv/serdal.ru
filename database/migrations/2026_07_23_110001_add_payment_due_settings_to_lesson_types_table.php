<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lesson_types', function (Blueprint $table) {
            // Поурочная: через сколько дней после занятия наступает срок оплаты
            $table->unsignedTinyInteger('payment_due_days')->default(3)->after('payment_type');
            // Помесячная: до какого числа месяца нужно оплатить
            $table->unsignedTinyInteger('payment_due_day')->default(5)->after('payment_due_days');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_types', function (Blueprint $table) {
            $table->dropColumn(['payment_due_days', 'payment_due_day']);
        });
    }
};
