<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пауза отправки рассылки до времени: исчерпан дневной лимит сервиса почты — не стучимся каждую минуту,
 * пробуем снова в resume_at (App\Services\MailingService::sendBatch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->timestamp('resume_at')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->dropColumn('resume_at');
        });
    }
};
