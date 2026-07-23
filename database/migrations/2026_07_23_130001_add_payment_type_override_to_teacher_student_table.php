<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teacher_student', function (Blueprint $table) {
            // Персональный тип оплаты ученика: null — как в базовых ценах,
            // per_lesson / monthly — переопределение для этого ученика
            $table->string('payment_type_override', 20)->nullable()->after('is_free');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_student', function (Blueprint $table) {
            $table->dropColumn('payment_type_override');
        });
    }
};
