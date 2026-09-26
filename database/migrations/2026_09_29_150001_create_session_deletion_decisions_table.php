<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Решения администратора по запросам учителей на удаление проведённого занятия («Решено раньше» в админке).
 * Проведённое занятие при одобрении удаляется целиком, поэтому название, время и причина копируются сюда.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_deletion_decisions', function (Blueprint $table) {
            $table->id();
            // Занятие удаляется при одобрении — ссылка остаётся только у отклонённых
            $table->unsignedBigInteger('meeting_session_id')->nullable()->index();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('room_name')->nullable();
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('session_ended_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->text('reason')->nullable();
            // deleted — занятие удалено по запросу, rejected — запрос отклонён
            $table->string('decision', 16);
            // Ответ учителю при отказе (необязательно)
            $table->text('reply')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_deletion_decisions');
    }
};
