<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Когда человеку предложили тур по кабинету (прошёл, закрыл или отложил) — больше сам не открываем
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('tour_seen_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('tour_seen_at'));
    }
};
