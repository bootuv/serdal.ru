<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tariff_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active'); // active | expired | cancelled
            $table->unsignedInteger('price')->default(0); // снимок цены на момент покупки, ₽
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable(); // null = бессрочно (бесплатный тариф)
            $table->timestamp('cancelled_at')->nullable();
            $table->string('comment')->nullable(); // примечание администратора
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
