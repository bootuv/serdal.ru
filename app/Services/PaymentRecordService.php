<?php

namespace App\Services;

use App\Models\LessonType;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PaymentRecordService
{
    /**
     * Дефолт: через сколько дней после занятия наступает срок поурочной оплаты.
     * Учитель может изменить в «Базовых ценах» (lesson_types.payment_due_days).
     */
    const PER_LESSON_DUE_DAYS = 3;

    /**
     * Дефолт: до какого числа месяца нужно внести помесячную оплату.
     * Учитель может изменить в «Базовых ценах» (lesson_types.payment_due_day).
     */
    const MONTHLY_DUE_DAY = 5;

    /**
     * Грейс-период (в днях) после срока оплаты, прежде чем посещение
     * занятия с долгом приведёт к блокировке кабинета.
     */
    const BLOCK_GRACE_DAYS = 1;

    /**
     * Вызывается при завершении сессии: создаёт поурочные начисления для
     * посетивших учеников и блокирует тех, кто пришёл с просроченным долгом.
     */
    public static function handleCompletedSession(MeetingSession $session): void
    {
        $snapshot = $session->pricing_snapshot ?? [];
        $room = $session->room;

        if (!$room) {
            return;
        }

        $teacherId = $room->user_id;
        $attendedIds = collect($snapshot['participants'] ?? [])
            ->filter(fn($p) => $p['attended'] ?? false)
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (empty($attendedIds)) {
            return;
        }

        // Бесплатные ученики: оплата не отслеживается
        $freeIds = self::freeStudentIds($teacherId);
        $attendedIds = array_values(array_diff($attendedIds, $freeIds));

        if (empty($attendedIds)) {
            return;
        }

        // Тип оплаты занятия по настройке учителя; у ученика может быть
        // персональное переопределение (teacher_student.payment_type_override)
        $roomPaymentType = $snapshot['payment_type'] ?? 'per_lesson';
        $overrides = self::paymentTypeOverrides($teacherId);

        // Срок оплаты — из настроек учителя в «Базовых ценах»
        $dueDays = (int) (LessonType::where('user_id', $teacherId)
            ->where('type', $room->type ?? 'individual')
            ->value('payment_due_days') ?? self::PER_LESSON_DUE_DAYS);

        foreach ($attendedIds as $studentId) {
            $effectiveType = $overrides[$studentId] ?? $roomPaymentType;

            // Помесячным ученикам поурочные записи не создаём — их запись создаётся 1-го числа
            if ($effectiveType !== PaymentRecord::TYPE_PER_LESSON) {
                continue;
            }

            try {
                PaymentRecord::firstOrCreate(
                    [
                        'student_id' => $studentId,
                        'meeting_session_id' => $session->id,
                    ],
                    [
                        'teacher_id' => $teacherId,
                        'type' => PaymentRecord::TYPE_PER_LESSON,
                        'status' => PaymentRecord::STATUS_UNPAID,
                        'due_date' => today()->addDays($dueDays),
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("[Payments] Failed to create per-lesson record for student {$studentId}, session {$session->id}: " . $e->getMessage());
            }
        }

        // Блокировка: ученик посетил занятие, имея просроченный долг перед этим учителем
        $blockThreshold = today()->subDays(self::BLOCK_GRACE_DAYS);

        $debtors = PaymentRecord::unpaid()
            ->where('teacher_id', $teacherId)
            ->whereIn('student_id', $attendedIds)
            ->whereDate('due_date', '<', $blockThreshold)
            ->pluck('student_id')
            ->unique();

        if ($debtors->isNotEmpty()) {
            User::whereIn('id', $debtors)
                ->whereNull('payment_blocked_at')
                ->update(['payment_blocked_at' => now()]);
        }
    }

    /**
     * Создаёт помесячные начисления за текущий месяц.
     * Запускается командой 1-го числа каждого месяца.
     */
    public static function generateMonthlyRecords(): int
    {
        $period = today()->format('Y-m');
        $created = 0;

        // Все учителя, у которых есть занятия с участниками
        $teacherIds = Room::whereHas('participants')->distinct()->pluck('user_id');

        foreach (User::whereIn('id', $teacherIds)->with('lessonTypes')->get() as $teacher) {
            $lessonTypes = $teacher->lessonTypes->keyBy('type');

            // Срок оплаты — из помесячной базовой цены учителя (если есть)
            $dueDay = (int) ($teacher->lessonTypes->firstWhere('payment_type', PaymentRecord::TYPE_MONTHLY)?->payment_due_day
                ?? self::MONTHLY_DUE_DAY);
            $dueDate = today()->startOfMonth()->addDays($dueDay - 1);

            $freeIds = self::freeStudentIds($teacher->id);
            $overrides = self::paymentTypeOverrides($teacher->id);

            // Собираем учеников, для которых действует помесячная оплата:
            // персональное переопределение → иначе настройка формата занятия
            $monthlyStudentIds = collect();

            foreach ($teacher->rooms()->with('participants:users.id')->get() as $room) {
                $roomPaymentType = $lessonTypes[$room->type ?? 'individual']?->payment_type ?? PaymentRecord::TYPE_PER_LESSON;

                foreach ($room->participants as $participant) {
                    $studentId = (int) $participant->id;

                    if (in_array($studentId, $freeIds)) {
                        continue;
                    }

                    $effectiveType = $overrides[$studentId] ?? $roomPaymentType;

                    if ($effectiveType === PaymentRecord::TYPE_MONTHLY) {
                        $monthlyStudentIds->push($studentId);
                    }
                }
            }

            foreach ($monthlyStudentIds->unique() as $studentId) {
                try {
                    $record = PaymentRecord::firstOrCreate(
                        [
                            'teacher_id' => $teacher->id,
                            'student_id' => $studentId,
                            'period' => $period,
                        ],
                        [
                            'type' => PaymentRecord::TYPE_MONTHLY,
                            'status' => PaymentRecord::STATUS_UNPAID,
                            'due_date' => $dueDate,
                        ]
                    );

                    if ($record->wasRecentlyCreated) {
                        $created++;
                    }
                } catch (\Throwable $e) {
                    Log::error("[Payments] Failed to create monthly record for student {$studentId}, teacher {$teacher->id}: " . $e->getMessage());
                }
            }
        }

        return $created;
    }

    /**
     * ID бесплатных учеников учителя (оплата для них не отслеживается).
     */
    public static function freeStudentIds(int $teacherId): array
    {
        return \Illuminate\Support\Facades\DB::table('teacher_student')
            ->where('teacher_id', $teacherId)
            ->where('is_free', true)
            ->pluck('student_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * Персональные типы оплаты учеников учителя: [student_id => per_lesson|monthly].
     * Ученики без переопределения в массив не попадают.
     */
    public static function paymentTypeOverrides(int $teacherId): array
    {
        return \Illuminate\Support\Facades\DB::table('teacher_student')
            ->where('teacher_id', $teacherId)
            ->whereNotNull('payment_type_override')
            ->pluck('payment_type_override', 'student_id')
            ->mapWithKeys(fn($type, $id) => [(int) $id => $type])
            ->all();
    }

    /**
     * Снимает блокировку, если у ученика не осталось просроченных долгов.
     */
    public static function recalculateBlock(?User $student): void
    {
        if (!$student || !$student->payment_blocked_at) {
            return;
        }

        $hasOverdue = PaymentRecord::overdue()
            ->where('student_id', $student->id)
            ->exists();

        if (!$hasOverdue) {
            $student->update(['payment_blocked_at' => null]);
        }
    }
}
