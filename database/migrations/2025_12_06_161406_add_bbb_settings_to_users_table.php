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
        Schema::table('users', function (Blueprint $table) {
            $table->string('bbb_url')->nullable();
            $table->string('bbb_secret')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'bbb_url')) {
                $table->dropColumn('bbb_url');
            }
            if (Schema::hasColumn('users', 'bbb_secret')) {
                $table->dropColumn('bbb_secret');
            }
        });
    }
};
