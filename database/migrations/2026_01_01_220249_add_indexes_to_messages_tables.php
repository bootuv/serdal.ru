<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Adds indexes for optimizing chat message queries:
     * - Composite index on (room_id, user_id, read_at) for markAsRead() queries
     * - Index on (support_chat_id, user_id, read_at) for support chat markAsRead()
     */
    public function up(): void
    {
        // Messages table indexes
        Schema::table('messages', function (Blueprint $table) {
            // For markAsRead() query: WHERE room_id = ? AND user_id != ? AND read_at IS NULL
            $table->index(['room_id', 'user_id', 'read_at'], 'messages_read_status_index');
        });

        // Support messages table indexes
        Schema::table('support_messages', function (Blueprint $table) {
            // For markAsRead() query in support chat
            $table->index(['support_chat_id', 'user_id', 'read_at'], 'support_messages_read_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_read_status_index');
        });

        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropIndex('support_messages_read_status_index');
        });
    }
};
