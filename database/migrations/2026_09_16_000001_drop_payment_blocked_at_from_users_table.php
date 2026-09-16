<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Блокировка за неоплату больше не хранится в users: она вычисляется из
 * payment_records и действует только на занятия конкретного преподавателя
 * (см. PaymentRecordService::blockedTeacherIds).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('payment_blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('payment_blocked_at')->nullable()->after('is_blocked');
        });
    }
};
