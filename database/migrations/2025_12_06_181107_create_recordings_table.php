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
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->string('meeting_id')->index(); // Link to Room
            $table->string('record_id')->unique(); // BBB Record ID
            $table->string('name');
            $table->boolean('published')->default(false);
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->integer('participants')->default(0);
            $table->text('url')->nullable(); // Helper to store playback URL
            $table->json('raw_data')->nullable(); // Store everything else
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
