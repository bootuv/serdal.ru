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
        DB::table('lesson_types')
            ->where('type', 'Индивидуальный')
            ->update(['type' => 'individual']);

        DB::table('lesson_types')
            ->where('type', 'Групповой')
            ->update(['type' => 'group']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('lesson_types')
            ->where('type', 'individual')
            ->update(['type' => 'Индивидуальный']);

        DB::table('lesson_types')
            ->where('type', 'group')
            ->update(['type' => 'Групповой']);
    }
};
