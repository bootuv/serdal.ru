<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(['room_id', 'created_at']);
            });
        } catch (QueryException $e) {
            // Ignore duplicate index errors
            if (!str_contains($e->getMessage(), 'Duplicate key name') && !str_contains($e->getMessage(), '1061')) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex(['room_id', 'created_at']);
            });
        } catch (QueryException $e) {
            // Ignore if index doesn't exist
        }
    }
};
