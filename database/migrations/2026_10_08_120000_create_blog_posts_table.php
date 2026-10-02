<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Блог на сайте (serdal.ru/blog): статьи про образование для поиска. Пишутся в админке «Блог» (блочный редактор).
 * published_at: null — черновик, в будущем — выйдет по времени. excerpt — описание в списке и для поисковиков.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->string('cover_url', 1024)->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
