<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Исключения из расписания: одно занятие серии (или разовое) отменено или перенесено, правило остаётся.
 * Вхождение определяется правилом и исходной датой (original_date), для переноса — новое время и длительность.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('room_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('original_date');
            $table->dateTime('original_starts_at');
            $table->string('status', 16); // cancelled | moved
            $table->dateTime('starts_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('reason', 500)->nullable();
            $table->boolean('notified')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('google_event_id')->nullable();
            $table->timestamps();

            $table->unique(['room_schedule_id', 'original_date']);
            $table->index(['room_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_schedule_exceptions');
    }
};
