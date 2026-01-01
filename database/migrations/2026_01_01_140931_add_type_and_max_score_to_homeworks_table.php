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
        Schema::table('homeworks', function (Blueprint $table) {
            $table->string('type')->default('homework')->after('room_id');
            $table->unsignedInteger('max_score')->nullable()->after('deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropColumn(['type', 'max_score']);
        });
    }
};
