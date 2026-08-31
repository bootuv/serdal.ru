<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price')->default(0); // ₽ в месяц
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->unsignedSmallInteger('lessons_per_month')->nullable(); // null = без лимита
            $table->unsignedSmallInteger('max_participants')->default(2); // с учителем
            $table->unsignedSmallInteger('max_duration_minutes')->nullable(); // null = без лимита
            $table->unsignedSmallInteger('recording_retention_days')->nullable(); // null = записи недоступны
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable(); // список "что входит"
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariffs');
    }
};
