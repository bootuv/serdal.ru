<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Исходные имена презентаций занятия (путь на s3 → «Present Perfect.pdf»):
 * сами файлы по-прежнему в rooms.presentations, их открывает класс при старте.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rooms', 'presentation_names')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->json('presentation_names')->nullable()->after('presentations');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rooms', 'presentation_names')) {
            Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('presentation_names'));
        }
    }
};
