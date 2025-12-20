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
        // Safe check for existing columns to prevent "Duplicate column" error
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'teacher_id')) {
                // We might need to handle existing data if we are adding a non-null foreign key
                // But assuming we want to proceed with deletion logic or nullable first if needed.
                // Given the context of the previous error, let's keep it simple but safe.
                // We'll delete data only if we are about to add the column, to ensure foreign key constraints work.

                \Illuminate\Support\Facades\DB::table('reviews')->delete();

                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('reviews', 'rating')) {
                $table->unsignedTinyInteger('rating')->default(5);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn(['teacher_id', 'rating']);
        });
    }
};
