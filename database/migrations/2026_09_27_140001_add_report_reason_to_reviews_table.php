<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Жалоба учителя на отзыв: причина (Review::REPORT_REASONS), пояснение и когда отправлена. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('report_reason', 20)->nullable()->after('is_reported');
            $table->text('report_note')->nullable()->after('report_reason');
            $table->timestamp('reported_at')->nullable()->after('report_note');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['report_reason', 'report_note', 'reported_at']);
        });
    }
};
