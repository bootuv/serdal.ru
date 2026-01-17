<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add VK fields to recordings table
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('vk_video_id')->nullable()->after('url');
            $table->string('vk_video_url')->nullable()->after('vk_video_id');
            $table->timestamp('vk_uploaded_at')->nullable()->after('vk_video_url');
        });

        // Add VK album ID to users table (for teachers' playlists)
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('vk_album_id')->nullable()->after('google_calendar_id');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['vk_video_id', 'vk_video_url', 'vk_uploaded_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vk_album_id');
        });
    }
};
