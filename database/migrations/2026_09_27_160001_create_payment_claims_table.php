<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Сообщить об оплате»: ученик сообщает учителю, что оплатил занятия (с чеком и комментарием),
 * учитель подтверждает (начисления становятся оплаченными) или отклоняет.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // pending — ждёт учителя, confirmed — подтверждена, rejected — отклонена
            $table->string('status', 16)->default('pending');
            $table->text('comment')->nullable();
            // Чеки на s3: [{path, name, size}]
            $table->json('files')->nullable();
            // Сумма выбранных начислений на момент заявки (если известна), ₽
            $table->unsignedInteger('amount')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('payment_claim_record', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_claim_id')->constrained('payment_claims')->cascadeOnDelete();
            $table->foreignId('payment_record_id')->constrained('payment_records')->cascadeOnDelete();

            $table->unique(['payment_claim_id', 'payment_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_claim_record');
        Schema::dropIfExists('payment_claims');
    }
};
