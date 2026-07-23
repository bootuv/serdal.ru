<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teacher_student', function (Blueprint $table) {
            // Бесплатный ученик: записи об оплате не создаются, напоминания не приходят
            $table->boolean('is_free')->default(false)->after('student_id');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_student', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};
