<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // До какого момента учитель скрыл баннер партнёрской программы на инфопанели
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('referral_banner_hidden_until')->nullable()->after('referred_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn(Blueprint $table) => $table->dropColumn('referral_banner_hidden_until'));
    }
};
