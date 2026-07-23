<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // per_lesson | monthly
            $table->foreignId('meeting_session_id')->nullable()->constrained('meeting_sessions')->nullOnDelete();
            $table->string('period', 7)->nullable(); // 'YYYY-MM' для помесячной оплаты
            $table->string('status', 20)->default('unpaid'); // unpaid | paid | cancelled
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            // Одна помесячная запись на связку учитель-ученик-месяц
            $table->unique(['teacher_id', 'student_id', 'period']);
            // Одна поурочная запись на ученика за сессию
            $table->unique(['student_id', 'meeting_session_id']);
            $table->index(['student_id', 'status', 'due_date']);
            $table->index(['teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};
