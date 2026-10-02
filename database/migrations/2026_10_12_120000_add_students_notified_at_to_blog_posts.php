<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ученики узнают о новой статье своего учителя (BlogService::notifyStudents) — один раз, когда статья вышла.
 * Уже опубликованные статьи считаем разосланными: при деплое ученикам не придет пачка старых уведомлений.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->timestamp('students_notified_at')->nullable()->after('submitted_at');
        });

        DB::table('blog_posts')->whereNotNull('published_at')->where('published_at', '<=', now())->update(['students_notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('students_notified_at');
        });
    }
};
