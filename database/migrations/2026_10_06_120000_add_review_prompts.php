<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Приглашения оставить отзыв (ReviewPromptService).
 * teacher_student: учитель попросил отзыв; ученик закрыл приглашение на главной («Позже») — сколько раз,
 * когда и после какого числа занятий показать снова; когда ученику пришло уведомление после третьего занятия.
 * users.platform_review_dismissals — сколько раз учитель закрыл приглашение «Как вам Serdal?».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_student', function (Blueprint $table) {
            $table->timestamp('review_requested_at')->nullable();
            $table->unsignedTinyInteger('review_prompt_dismissals')->default(0);
            $table->timestamp('review_prompt_dismissed_at')->nullable();
            $table->unsignedSmallInteger('review_prompt_resume_lessons')->nullable();
            $table->timestamp('review_prompt_notified_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('platform_review_dismissals')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('teacher_student', function (Blueprint $table) {
            $table->dropColumn([
                'review_requested_at', 'review_prompt_dismissals', 'review_prompt_dismissed_at',
                'review_prompt_resume_lessons', 'review_prompt_notified_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platform_review_dismissals');
        });
    }
};
