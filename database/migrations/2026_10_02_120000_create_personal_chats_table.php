<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Личные чаты учителя и ученика — не зависят от занятий.
 * Сообщения лежат в messages: у сообщения заполнено либо room_id (чат занятия), либо personal_chat_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id', 'student_id']);
            $table->index('student_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->change();
            $table->foreignId('personal_chat_id')->nullable()->after('room_id')->constrained()->cascadeOnDelete();
            $table->index(['personal_chat_id', 'user_id', 'read_at'], 'messages_personal_read_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_personal_read_status_index');
            $table->dropConstrainedForeignId('personal_chat_id');
        });

        Schema::dropIfExists('personal_chats');
    }
};
