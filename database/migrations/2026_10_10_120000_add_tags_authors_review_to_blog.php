<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Блог: теги (вместо категорий), автор-учитель и проверка статей учителей.
 * author_id — кого показываем автором (учитель или null — «Команда Serdal»); created_by — кто создал статью.
 * review_status у статей учителей: draft (пишет) → pending (на проверке) → returned (вернули с review_note) или опубликована.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_post_id', 'blog_tag_id']);
            $table->index('blog_tag_id');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->string('review_status', 16)->nullable()->after('published_at');
            $table->text('review_note')->nullable()->after('review_status');
            $table->timestamp('submitted_at')->nullable()->after('review_note');
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex(['review_status']);
            $table->dropConstrainedForeignId('author_id');
            $table->dropColumn(['review_status', 'review_note', 'submitted_at']);
        });
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_tags');
    }
};
