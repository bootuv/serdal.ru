<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Статьи базы знаний из database/help/*.md (`php artisan help:sync` при деплое):
 * source — «раздел/файл::заголовок», source_hash — отпечаток загруженного текста, чтобы не затирать правки из админки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->string('source')->nullable()->unique();
            $table->string('source_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropUnique(['source']);
            $table->dropColumn(['source', 'source_hash']);
        });
    }
};
