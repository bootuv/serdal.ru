<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Сохранённый способ оплаты ЮKassa для автопродления подписки
            $table->string('yookassa_payment_method_id')->nullable()->after('commission_rate');
            $table->string('payment_method_title')->nullable()->after('yookassa_payment_method_id'); // «Bank card *4477»
            $table->boolean('auto_renew')->default(false)->after('payment_method_title');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['yookassa_payment_method_id', 'payment_method_title', 'auto_renew']);
        });
    }
};
