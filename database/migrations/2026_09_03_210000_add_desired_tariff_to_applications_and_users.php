<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Тариф, выбранный на публичной странице до регистрации:
        // заявка → пользователь → оплата после онбординга
        Schema::table('teacher_applications', function (Blueprint $table) {
            $table->foreignId('desired_tariff_id')->nullable()->constrained('tariffs')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('desired_tariff_id')->nullable()->constrained('tariffs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desired_tariff_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desired_tariff_id');
        });
    }
};
