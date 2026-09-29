<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Раздел админки «Основатели»: доли основателей, расходы на инфраструктуру и ежемесячные взносы.
 * founder_contributions — взнос основателя за месяц (period — первое число месяца): сумма фиксируется
 * при оплате, пока не внесено — пересчитывается от текущих расходов и долей.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('founders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->decimal('share', 5, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('founder_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('note')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('period', 8)->default('month'); // month | year
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('founder_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('founder_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['founder_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('founder_contributions');
        Schema::dropIfExists('founder_expenses');
        Schema::dropIfExists('founders');
    }
};
