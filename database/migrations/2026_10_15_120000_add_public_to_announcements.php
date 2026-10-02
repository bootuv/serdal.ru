<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Новость можно показать и на сайте — serdal.ru/news (is_public), адрес — русская транслитерация заголовка. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('send_mail');
            $table->string('slug', 120)->nullable()->unique()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['is_public', 'slug']);
        });
    }
};
