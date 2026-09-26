<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Задания и работы:
 * - file_names — исходные имена загруженных файлов (путь на s3 → «Тетрадь, стр. 1.jpg»);
 * - annotations — пометки учителя отдельным файлом (путь фото ученика → путь фото с пометками),
 *   оригинал фото больше не перезаписывается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('homeworks', 'file_names')) {
            Schema::table('homeworks', function (Blueprint $table) {
                $table->json('file_names')->nullable()->after('attachments');
            });
        }

        Schema::table('homework_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('homework_submissions', 'file_names')) {
                $table->json('file_names')->nullable()->after('attachments');
            }
            if (! Schema::hasColumn('homework_submissions', 'annotations')) {
                $table->json('annotations')->nullable()->after('annotated_files');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('homeworks', 'file_names')) {
            Schema::table('homeworks', fn (Blueprint $table) => $table->dropColumn('file_names'));
        }

        foreach (['file_names', 'annotations'] as $column) {
            if (Schema::hasColumn('homework_submissions', $column)) {
                Schema::table('homework_submissions', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
