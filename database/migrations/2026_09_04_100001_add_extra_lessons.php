<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Докупленные занятия сверх лимита тарифа. Принадлежат пользователю,
            // не сгорают и переносятся между периодами и тарифами.
            $table->unsignedInteger('extra_lessons_balance')->default(0)->after('auto_renew');
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            // Количество докупленных занятий (null — обычная оплата тарифа)
            $table->unsignedSmallInteger('extra_lessons')->nullable()->after('period_days');
        });

        Schema::table('meeting_sessions', function (Blueprint $table) {
            // Занятие проведено за счёт докупленного, а не лимита тарифа
            $table->boolean('extra_lesson')->default(false)->after('participant_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn(Blueprint $table) => $table->dropColumn('extra_lessons_balance'));
        Schema::table('subscription_payments', fn(Blueprint $table) => $table->dropColumn('extra_lessons'));
        Schema::table('meeting_sessions', fn(Blueprint $table) => $table->dropColumn('extra_lesson'));
    }
};
