<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Файлы, загруженные в редактор (картинки, видео с обложкой, обложки статей): чей текст их использует.
 * Нужна, чтобы удалять с хранилища то, что из текста убрали или так и не сохранили (EditorMediaService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editor_media', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500)->unique();
            $table->json('paths');
            $table->nullableMorphs('owner');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editor_media');
    }
};
