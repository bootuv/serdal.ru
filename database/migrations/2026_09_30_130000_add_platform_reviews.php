<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Отзывы учителей о платформе: отзыв без teacher_id (автор — учитель, user_id).
 * approved_at — админ проверил и опубликовал на /reviews; show_on_site — автор согласен показать отзыв с именем и фото.
 * users.platform_review_dismissed_at — учитель закрыл приглашение оставить отзыв на «Сегодня».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->change();
            $table->timestamp('approved_at')->nullable()->after('hidden_at');
            $table->boolean('show_on_site')->default(true)->after('approved_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('platform_review_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platform_review_dismissed_at');
        });

        \Illuminate\Support\Facades\DB::table('reviews')->whereNull('teacher_id')->delete();

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'show_on_site']);
            $table->foreignId('teacher_id')->nullable(false)->change();
        });
    }
};
