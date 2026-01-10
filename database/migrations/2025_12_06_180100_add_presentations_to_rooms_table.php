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
        if (!Schema::hasColumn('rooms', 'presentations')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->json('presentations')->nullable()->after('welcome_msg');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('rooms', 'presentations')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('presentations');
            });
        }
    }
};
