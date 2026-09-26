<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Роль «ментор» убрана: все менторы становятся учителями (tutor) — права у них и так были учительские.
 * Откат невозможен (не знаем, кто был ментором), поэтому down ничего не делает.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'mentor')->update(['role' => 'tutor']);
    }

    public function down(): void
    {
    }
};
