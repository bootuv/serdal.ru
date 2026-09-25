<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Партнёрская программа: учитель приглашает коллег по ссылке /r/{code},
        // пригласивший сохраняется в заявке и переносится на пользователя при одобрении
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique()->after('extra_lessons_balance');
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
        });

        Schema::table('teacher_applications', function (Blueprint $table) {
            $table->foreignId('referred_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // Бонус пригласившему за оплату конкретного тарифа (пусто — общее значение из настроек)
        Schema::table('tariffs', function (Blueprint $table) {
            $table->unsignedSmallInteger('referral_bonus')->nullable()->after('recording_retention_days');
        });

        // Начисления за приглашения: одно на каждого приглашённого (за его первую оплату)
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('subscription_payments')->nullOnDelete();
            $table->unsignedSmallInteger('referrer_lessons')->default(0);
            $table->unsignedSmallInteger('referred_lessons')->default(0);
            $table->string('status', 20); // credited | limit | rejected | revoked
            $table->string('note')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');

        Schema::table('tariffs', fn(Blueprint $table) => $table->dropColumn('referral_bonus'));

        Schema::table('teacher_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_id');
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
