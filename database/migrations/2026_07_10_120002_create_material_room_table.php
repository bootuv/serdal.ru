<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('material_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('teacher_materials')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->unique(['material_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_room');
    }
};
