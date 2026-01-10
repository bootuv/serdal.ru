<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Force the column to be VARCHAR(255) to support all status strings
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE homework_submissions MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT 'pending'");
        } else {
            dump('Skipping MODIFY for sqlite');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We generally don't want to revert this as it fixes a bug, 
        // but if needed we could revert to a smaller varchar or enum if we knew what it was.
        // For now, doing nothing or reverting to a likely previous state is acceptable.
        // But since we don't know the exact previous state (it was broken), we'll skip down logic 
        // to avoid breaking data again.
    }
};
