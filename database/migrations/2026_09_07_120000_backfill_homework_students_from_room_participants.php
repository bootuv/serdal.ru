<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Бэкфилл: участники занятия, добавленные после создания привязанного
 * к нему домашнего задания, не были назначены на это задание.
 * Добавляем недостающие связи в homework_student (без уведомлений).
 */
return new class extends Migration {
    public function up(): void
    {
        $rows = DB::table('homeworks')
            ->join('room_user', 'room_user.room_id', '=', 'homeworks.room_id')
            ->leftJoin('homework_student', function ($join) {
                $join->on('homework_student.homework_id', '=', 'homeworks.id')
                    ->on('homework_student.student_id', '=', 'room_user.user_id');
            })
            ->whereNotNull('homeworks.room_id')
            ->whereNull('homework_student.homework_id')
            ->select('homeworks.id as homework_id', 'room_user.user_id as student_id')
            ->distinct()
            ->get()
            ->map(fn ($row) => ['homework_id' => $row->homework_id, 'student_id' => $row->student_id])
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('homework_student')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Данные не откатываем: невозможно отличить бэкфилл от ручных назначений
    }
};
