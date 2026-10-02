<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Занятия, начисленные учителю администратором (на users.extra_lessons_balance).
        // Например, чтобы провести занятие, пока тариф не продлён
        Schema::create('lesson_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('lessons');
            $table->string('note', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_grants');
    }
};
