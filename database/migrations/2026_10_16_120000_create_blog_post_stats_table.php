<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Просмотры статей блога по дням и откуда пришли читатели (BlogStatsService): одна строка на статью в день. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('search')->default(0);   // Яндекс, Google и другие поисковики
            $table->unsignedInteger('social')->default(0);   // соцсети и мессенджеры
            $table->unsignedInteger('site')->default(0);     // переходы внутри serdal.ru
            $table->unsignedInteger('other')->default(0);    // другие сайты
            $table->unsignedInteger('direct')->default(0);   // без источника: закладка, ссылка из приложения
            $table->unique(['blog_post_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_stats');
    }
};
