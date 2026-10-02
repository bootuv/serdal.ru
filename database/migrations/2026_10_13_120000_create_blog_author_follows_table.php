<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подписки на авторов блога: follower_id подписан на author_id. Дают вкладку «Моя лента» и уведомление
 * о новой статье автора (BlogService::toggleFollow, notifyReaders).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_author_follows', function (Blueprint $table) {
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['follower_id', 'author_id']);
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_author_follows');
    }
};
