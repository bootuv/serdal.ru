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
        Schema::table('rooms', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('is_running');
            $table->index('created_at');
        });

        Schema::table('meeting_sessions', function (Blueprint $table) {
            $table->index('started_at');
            $table->index('deletion_requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_running']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('meeting_sessions', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
            $table->dropIndex(['deletion_requested_at']);
        });
    }
};
