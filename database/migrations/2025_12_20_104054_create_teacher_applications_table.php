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
        Schema::create('teacher_applications', function (Blueprint $table) {
            $table->id();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('whatsup')->nullable();
            $table->string('instagram')->nullable();
            $table->string('telegram')->nullable();
            $table->text('about')->nullable();
            $table->json('subjects')->nullable(); // Массив ID предметов
            $table->json('directs')->nullable();  // Массив ID направлений
            $table->json('grade')->nullable();    // Массив классов

            // Статус заявки: pending, approved, rejected
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_applications');
    }
};
