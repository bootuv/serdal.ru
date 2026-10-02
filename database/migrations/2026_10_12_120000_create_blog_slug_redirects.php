<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Прежние адреса статей блога: сменили адрес у опубликованной статьи — старая ссылка ведет на новую (301),
 * поисковики переносят страницу, а ссылки из соцсетей и мессенджеров не ломаются (BlogService::save, BlogController::show).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_slug_redirects');
    }
};
