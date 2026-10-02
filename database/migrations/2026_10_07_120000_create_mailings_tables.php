<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Почтовые рассылки на внешние адреса (школы): админка «Рассылки». Логика — App\Services\MailingService.
 *
 * mailing_contacts — адрес один на всю систему (отписка действует на все списки); user_id — если адрес взят из пользователей
 * платформы (для {имя} и подписи «учитель / ученик»); mailing_lists — группы адресов.
 * mailing_campaigns — письмо: черновик → запланировано / отправляется → отправлено (или остановлено).
 * mailing_deliveries — письмо конкретному адресату: статус отправки, открытия и переходы; token — для ссылок в письме.
 * mailing_links — ссылки письма (счётчик переходов по каждой).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('mailing_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();   // название школы
            $table->string('city')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mailing_contact_list', function (Blueprint $table) {
            $table->foreignId('mailing_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailing_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['mailing_list_id', 'mailing_contact_id']);
            $table->index('mailing_contact_id');
        });

        Schema::create('mailing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->longText('body')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->string('status', 16)->default('draft'); // draft | scheduled | sending | sent | stopped
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('error', 500)->nullable(); // почему отправка стоит (сервер почты не отвечает и т. п.)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('mailing_campaign_list', function (Blueprint $table) {
            $table->foreignId('mailing_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailing_list_id')->constrained()->cascadeOnDelete();
            $table->primary(['mailing_campaign_id', 'mailing_list_id']);
        });

        Schema::create('mailing_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailing_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });

        Schema::create('mailing_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailing_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailing_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('token', 40)->unique();
            $table->string('status', 16)->default('queued'); // queued | sent | failed | skipped
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('opens')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['mailing_campaign_id', 'mailing_contact_id']);
            $table->index(['mailing_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_deliveries');
        Schema::dropIfExists('mailing_links');
        Schema::dropIfExists('mailing_campaign_list');
        Schema::dropIfExists('mailing_campaigns');
        Schema::dropIfExists('mailing_contact_list');
        Schema::dropIfExists('mailing_contacts');
        Schema::dropIfExists('mailing_lists');
    }
};
