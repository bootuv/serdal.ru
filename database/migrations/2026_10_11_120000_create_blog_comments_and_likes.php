<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Обсуждения и лайки статей блога (App\Services\BlogCommentService, BlogService::toggleLike).
 * blog_comments: parent_id — корневой комментарий ветки (ответы — один уровень), reply_to_user_id — кому ответили;
 *   удаленный комментарий с ответами остается заглушкой (deleted_at, deleted_by: author | moderator).
 * blog_comment_reports — жалобы (админка «Блог» → «Жалобы»); blog_comment_bans — кто не может комментировать:
 *   author_id — в статьях этого автора (заблокировал автор статьи), null — везде (заблокировал админ).
 * blog_posts: счетчики лайков и комментариев, hot_score — вес для «Популярных», comments_closed — обсуждение закрыто.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();
            $table->foreignId('reply_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('deleted_by', 16)->nullable();
            $table->timestamps();
            $table->index(['blog_post_id', 'parent_id', 'created_at']);
        });

        Schema::create('blog_post_likes', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['blog_post_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('blog_comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['blog_comment_id', 'user_id']);
        });

        Schema::create('blog_comment_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'author_id']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unsignedInteger('likes_count')->default(0)->after('views_count');
            $table->unsignedInteger('comments_count')->default(0)->after('likes_count');
            $table->double('hot_score')->default(0)->after('comments_count')->index();
            $table->boolean('comments_closed')->default(false)->after('hot_score');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex(['hot_score']);
            $table->dropColumn(['likes_count', 'comments_count', 'hot_score', 'comments_closed']);
        });
        Schema::dropIfExists('blog_comment_bans');
        Schema::dropIfExists('blog_comment_reports');
        Schema::dropIfExists('blog_post_likes');
        Schema::dropIfExists('blog_comments');
    }
};
