<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            // Цена при оплате за год; null = годовая оплата недоступна
            $table->unsignedInteger('yearly_price')->nullable()->after('price');
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            // За какой период оплачено: 30 = месяц, 365 = год
            $table->unsignedSmallInteger('period_days')->default(30)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropColumn('yearly_price');
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn('period_days');
        });
    }
};
