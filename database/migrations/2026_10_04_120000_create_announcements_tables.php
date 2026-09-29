<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Новости от администрации (админка «Новости», раздел «Новости» в кабинетах учителя и ученика).
 * published_at: null — черновик, в будущем — запланирована. notified_at — когда разослали уведомления
 * (повторно не рассылаем, даже если новость сняли с публикации и вернули).
 * announcement_reads — кто открыл новость (счётчик непрочитанных и «Прочитали N из M»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('audience', 16)->default('teachers'); // teachers | students | all
            $table->boolean('is_important')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('send_mail')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['announcement_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
