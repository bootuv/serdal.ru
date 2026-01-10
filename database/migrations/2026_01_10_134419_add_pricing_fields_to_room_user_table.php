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
        Schema::table('room_user', function (Blueprint $table) {
            $table->unsignedInteger('custom_price')->nullable()->after('user_id');
            $table->string('price_note')->nullable()->after('custom_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_user', function (Blueprint $table) {
            $table->dropColumn(['custom_price', 'price_note']);
        });
    }
};
