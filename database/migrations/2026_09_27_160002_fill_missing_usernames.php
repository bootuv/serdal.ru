<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Карточка ученика в новом кабинете открывается по username (/cabinet/teacher/students/{username}).
 * У части старых учеников username пустой (имя без фамилии) — заполняем его, как это делает модель:
 * транслит имени, при совпадении — с номером; если имени нет — user-{id}.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where(fn ($q) => $q->whereNull('username')->orWhere('username', ''))
            ->orderBy('id')
            ->get(['id', 'name', 'first_name', 'last_name'])
            ->each(function ($user) {
                $source = trim(implode('-', array_filter([$user->first_name, $user->last_name]))) ?: (string) $user->name;
                $base = Str::slug(Str::transliterate($source)) ?: 'user-' . $user->id;
                $username = $base;
                $n = 1;

                while (DB::table('users')->where('username', $username)->where('id', '!=', $user->id)->exists()) {
                    $username = $base . '-' . $n++;
                }

                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            });
    }

    public function down(): void
    {
        // Заполненные username оставляем: пустые значения ничего не дают
    }
};
