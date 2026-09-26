<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Решение по заявке учителя: причина отказа (уходит в письме) и когда решили. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('teacher_applications', function (Blueprint $table) {
            $table->text('reject_reason')->nullable()->after('status');
            $table->timestamp('decided_at')->nullable()->after('reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_applications', function (Blueprint $table) {
            $table->dropColumn(['reject_reason', 'decided_at']);
        });
    }
};
