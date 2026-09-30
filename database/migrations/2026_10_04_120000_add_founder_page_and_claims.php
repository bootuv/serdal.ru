<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Личная страница основателя (/founder) — только после входа под привязанным профилем (founders.user_id):
 * у основателя может быть профиль администратора или учителя. Отметка «Я перевёл»:
 * claimed_at — основатель сообщил о переводе, взнос ждёт подтверждения админа.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('founders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('email')->constrained()->nullOnDelete();
        });

        // Уже добавленных основателей привязываем по совпадению почты с профилем администратора или учителя
        $taken = [];
        foreach (DB::table('founders')->whereNotNull('email')->orderBy('id')->get(['id', 'email']) as $founder) {
            $userId = DB::table('users')->whereIn('role', ['admin', 'tutor'])->where('email', $founder->email)->whereNotIn('id', $taken)->value('id');
            if ($userId) {
                DB::table('founders')->where('id', $founder->id)->update(['user_id' => $userId]);
                $taken[] = $userId;
            }
        }

        Schema::table('founder_contributions', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('founder_contributions', function (Blueprint $table) {
            $table->dropColumn('claimed_at');
        });

        Schema::table('founders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
