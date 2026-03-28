<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn([
                'vk_video_id',
                'vk_video_url',
                'vk_access_key',
                'vk_uploaded_at'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('vk_video_id')->nullable();
            $table->string('vk_video_url')->nullable();
            $table->string('vk_access_key')->nullable();
            $table->timestamp('vk_uploaded_at')->nullable();
        });
    }
};
