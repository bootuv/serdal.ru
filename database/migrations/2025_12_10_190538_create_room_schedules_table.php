<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('room_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');

            // Schedule Type
            $table->enum('type', ['once', 'recurring'])->default('once');

            // One-time schedule
            $table->dateTime('scheduled_at')->nullable();

            // Recurring schedule
            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly'])->nullable();
            $table->json('recurrence_days')->nullable(); // For weekly: [1,3,5] = Mon, Wed, Fri
            $table->integer('recurrence_day_of_month')->nullable(); // For monthly: 15 = 15th day
            $table->time('recurrence_time')->nullable(); // Time of day for recurring events

            // Date range for recurring schedules
            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Meeting duration
            $table->integer('duration_minutes')->default(60);

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_schedules');
    }
};
