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

    /** Счёт за месяц, выставленный после 1-го (ученика добавили позже), — не меньше стольких дней на оплату. */
    const MONTHLY_MIN_DUE_DAYS = 3;

    /**
     * Сколько занятий ученик может посетить с просроченным долгом, прежде чем
     * доступ к занятиям этого преподавателя закроется. До этого ученик видит
     * предупреждение с количеством оставшихся занятий.
     */
    const BLOCK_AFTER_LESSONS = 3;

    /**
     * Вызывается при завершении сессии: создаёт поурочные начисления для
     * посетивших учеников.
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

            // Помесячным ученикам поурочные записи не создаём — у них один счёт за месяц (generateMonthlyRecords)
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
    }

    /**
     * Создаёт помесячные начисления за текущий месяц. Запускается каждый день (payments:generate-monthly):
     * 1-го счёт получают все, у кого в месяце есть занятия, позже — те, у кого занятия появились
     * (добавили в расписание, назначили новое занятие).
     *
     * Правило: ученик получает счёт за месяц, только если у него с сегодняшнего дня до конца месяца есть
     * хотя бы одно занятие с этим учителем с оплатой за месяц — по расписанию, без отменённых (перенесённые
     * считаются в месяце, куда их перенесли). Нет занятий — нет счёта. Сумма — полная цена за месяц
     * (monthlyAmount), даже если ученик начал в середине месяца: скидку учитель договаривает сам.
     * Срок — до числа из «Цен на занятия», но не раньше чем через MONTHLY_MIN_DUE_DAYS дней.
     * Повторный запуск не создаёт дублей: одно начисление на учителя, ученика и месяц.
     */
    public static function generateMonthlyRecords(): int
    {
        $month = today()->startOfMonth();
        $period = $month->format('Y-m');
        $created = 0;

        // Все учителя, у которых есть занятия с участниками
        $teacherIds = Room::whereHas('participants')->distinct()->pluck('user_id');

        foreach (User::whereIn('id', $teacherIds)->with('lessonTypes')->get() as $teacher) {
            $lessonTypes = $teacher->lessonTypes->keyBy('type');

            // Срок оплаты — из помесячной базовой цены учителя (если есть)
            $dueDay = (int) ($teacher->lessonTypes->firstWhere('payment_type', PaymentRecord::TYPE_MONTHLY)?->payment_due_day
                ?? self::MONTHLY_DUE_DAY);
            $dueDate = $month->copy()->addDays($dueDay - 1)->max(today()->addDays(self::MONTHLY_MIN_DUE_DAYS));

            $freeIds = self::freeStudentIds($teacher->id);
            $overrides = self::paymentTypeOverrides($teacher->id);

            // Занятия учителя, у которых до конца месяца есть хотя бы одно неотменённое вхождение
            // (с сегодняшнего дня: ученику, добавленному 28-го в группу, счёт за прошедшие занятия не нужен)
            $roomIdsWithLessons = app(StudentScheduleService::class)
                ->occurrences(app(TeacherScheduleService::class)->schedules($teacher->id), today(), $month->copy()->endOfMonth())
                ->pluck('room_id')
                ->unique()
                ->all();

            if ($roomIdsWithLessons === []) {
                continue;
            }

            // Ученики с помесячной оплатой и их занятия в этом месяце:
            // персональное переопределение → иначе настройка формата занятия
            $roomsByStudent = [];

            foreach ($teacher->rooms()->whereIn('id', $roomIdsWithLessons)->with('participants')->get() as $room) {
                $roomPaymentType = $lessonTypes[$room->type ?? 'individual']?->payment_type ?? PaymentRecord::TYPE_PER_LESSON;

                foreach ($room->participants as $participant) {
                    $studentId = (int) $participant->id;

                    if (in_array($studentId, $freeIds)) {
                        continue;
                    }

                    if (($overrides[$studentId] ?? $roomPaymentType) === PaymentRecord::TYPE_MONTHLY) {
                        $roomsByStudent[$studentId][] = $room;
                    }
                }
            }

            foreach ($roomsByStudent as $studentId => $rooms) {
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
                            'amount' => self::monthlyAmount($teacher->lessonTypes, $studentId, collect($rooms)),
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
     * Цена ученика за месяц, ₽, по его занятиям с оплатой за месяц ($rooms): сумма цен этих занятий.
     * Цена занятия с помесячным типом — цена ученика в занятии (личная → цена занятия → «Цены на занятия»).
     * Если за месяц платит только этот ученик (условия оплаты), а занятие оплачивается за каждое, —
     * помесячная цена учителя, если она у него одна. null — хотя бы одну цену не определить.
     *
     * @param  \Illuminate\Support\Collection<int, LessonType>  $lessonTypes  цены учителя
     * @param  \Illuminate\Support\Collection<int, Room>  $rooms
     */
    public static function monthlyAmount(\Illuminate\Support\Collection $lessonTypes, int $studentId, \Illuminate\Support\Collection $rooms): ?int
    {
        if ($rooms->isEmpty()) {
            return null;
        }

        $monthlyTypes = $lessonTypes->filter(fn (LessonType $lt) => $lt->isMonthly())->values();

        $prices = $rooms->map(function (Room $room) use ($lessonTypes, $monthlyTypes, $studentId) {
            $roomType = $lessonTypes->firstWhere('type', $room->type ?? LessonType::TYPE_INDIVIDUAL);

            if ($roomType?->isMonthly()) {
                return $room->getEffectivePrice($studentId);
            }

            return $monthlyTypes->count() === 1 ? $monthlyTypes->first()->price : null;
        });

        if ($prices->contains(fn ($price) => $price === null || (int) $price <= 0)) {
            return null;
        }

        return (int) $prices->sum();
    }

    /**
     * Занятия учителя, в которых ученик платит за месяц (персональные условия → тип занятия в ценах).
     *
     * @return \Illuminate\Support\Collection<int, Room>
     */
    public static function monthlyRooms(User $teacher, int $studentId): \Illuminate\Support\Collection
    {
        $lessonTypes = $teacher->lessonTypes()->get();
        $override = self::paymentTypeOverrides($teacher->id)[$studentId] ?? null;

        return Room::where('user_id', $teacher->id)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $studentId))
            ->with('participants')
            ->get()
            ->filter(fn (Room $room) => ($override
                ?? $lessonTypes->firstWhere('type', $room->type ?? LessonType::TYPE_INDIVIDUAL)?->payment_type
                ?? PaymentRecord::TYPE_PER_LESSON) === PaymentRecord::TYPE_MONTHLY)
            ->values();
    }

    /**
     * Сумма для неоплаченных помесячных начислений, созданных без неё (до появления поля amount):
     * текущая цена за месяц, если у ученика у этого учителя ровно одно занятие с оплатой за месяц
     * и его цену можно определить. Иначе сумма остаётся пустой.
     *
     * @return int сколько начислений получили сумму
     */
    public static function backfillMonthlyAmounts(): int
    {
        $filled = 0;

        PaymentRecord::unpaid()
            ->where('type', PaymentRecord::TYPE_MONTHLY)
            ->whereNull('amount')
            ->with('teacher')
            ->get()
            ->each(function (PaymentRecord $record) use (&$filled) {
                if (! $record->teacher) {
                    return;
                }

                $rooms = self::monthlyRooms($record->teacher, (int) $record->student_id);

                if ($rooms->count() !== 1) {
                    return;
                }

                $amount = self::monthlyAmount($record->teacher->lessonTypes()->get(), (int) $record->student_id, $rooms);

                if ($amount !== null) {
                    $record->update(['amount' => $amount]);
                    $filled++;
                }
            });

        return $filled;
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

    /*
     |--------------------------------------------------------------------------
     | Блокировка занятий за неоплату
     |--------------------------------------------------------------------------
     | Блокировка нигде не хранится — она вычисляется из записей об оплате и
     | истории занятий. Ученик не допускается к занятиям преподавателя, если
     | перед ним есть просроченный долг И ученик уже посетил BLOCK_AFTER_LESSONS
     | занятий этого преподавателя после срока оплаты самого старого долга.
     | Пока занятий меньше — только предупреждение. Поэтому блокировка действует
     | строго на одного преподавателя и снимается сама, как только долг оплачен,
     | отменён, продлён или удалён — каким угодно способом.
     */

    /**
     * Состояние долга ученика перед преподавателем.
     *
     * @return array{
     *   overdue_count: int,        сколько просроченных записей
     *   debt_since: ?\Illuminate\Support\Carbon,  срок оплаты самого старого долга
     *   lessons_with_debt: int,    сколько занятий посещено после этого срока
     *   lessons_left: int,         сколько занятий осталось до блокировки
     *   blocked: bool
     * }
     */
    public static function debtStatus(int $studentId, int $teacherId): array
    {
        $overdue = PaymentRecord::overdue()
            ->where('student_id', $studentId)
            ->where('teacher_id', $teacherId)
            ->orderBy('due_date')
            ->get(['id', 'due_date']);

        if ($overdue->isEmpty()) {
            return [
                'overdue_count' => 0,
                'debt_since' => null,
                'lessons_with_debt' => 0,
                'lessons_left' => self::BLOCK_AFTER_LESSONS,
                'blocked' => false,
            ];
        }

        $debtSince = $overdue->first()->due_date;
        $lessons = self::lessonsAttendedSince($studentId, $teacherId, $debtSince);

        return [
            'overdue_count' => $overdue->count(),
            'debt_since' => $debtSince,
            'lessons_with_debt' => $lessons,
            'lessons_left' => max(0, self::BLOCK_AFTER_LESSONS - $lessons),
            'blocked' => $lessons >= self::BLOCK_AFTER_LESSONS,
        ];
    }

    /**
     * Сколько завершённых занятий преподавателя ученик посетил после указанной даты
     * (занятие в сам день срока оплаты не считается — в этот день ещё можно оплатить).
     */
    public static function lessonsAttendedSince(int $studentId, int $teacherId, \Illuminate\Support\Carbon $since): int
    {
        return MeetingSession::where('status', 'completed')
            ->where('ended_at', '>', $since->copy()->endOfDay())
            ->whereHas('room', fn($q) => $q->where('user_id', $teacherId))
            ->get(['id', 'room_id', 'pricing_snapshot', 'analytics_data'])
            ->filter(fn(MeetingSession $session) => $session->attendedBy($studentId))
            ->count();
    }

    /**
     * Состояния долгов ученика перед всеми преподавателями, у которых есть просрочка.
     *
     * @return \Illuminate\Support\Collection<int, array{teacher: User, status: array}> ключ — ID преподавателя
     */
    public static function debtStatuses(int $studentId): \Illuminate\Support\Collection
    {
        $teacherIds = PaymentRecord::overdue()
            ->where('student_id', $studentId)
            ->distinct()
            ->pluck('teacher_id')
            ->map(fn($id) => (int) $id);

        if ($teacherIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $teacherIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn(User $teacher) => [
                $teacher->id => [
                    'teacher' => $teacher,
                    'status' => self::debtStatus($studentId, $teacher->id),
                ],
            ]);
    }

    /**
     * Закрыт ли ученику доступ к занятиям конкретного преподавателя.
     */
    public static function isBlockedForTeacher(int $studentId, int $teacherId): bool
    {
        return self::debtStatus($studentId, $teacherId)['blocked'];
    }

    /**
     * ID преподавателей, к чьим занятиям ученик сейчас не допускается.
     *
     * @return int[]
     */
    public static function blockedTeacherIds(int $studentId): array
    {
        return self::debtStatuses($studentId)
            ->filter(fn($item) => $item['status']['blocked'])
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Преподаватели, к чьим занятиям ученик сейчас не допускается (для баннеров).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function blockedTeachers(int $studentId): \Illuminate\Support\Collection
    {
        return self::debtStatuses($studentId)
            ->filter(fn($item) => $item['status']['blocked'])
            ->map(fn($item) => $item['teacher'])
            ->values();
    }

    /**
     * ID учеников преподавателя, которым сейчас закрыт доступ к его занятиям.
     *
     * @return int[]
     */
    public static function blockedStudentIds(int $teacherId): array
    {
        return PaymentRecord::overdue()
            ->where('teacher_id', $teacherId)
            ->distinct()
            ->pluck('student_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn(int $studentId) => self::isBlockedForTeacher($studentId, $teacherId))
            ->values()
            ->all();
    }

    /**
     * ID всех учеников платформы, которым сейчас закрыт доступ хотя бы к одному преподавателю.
     *
     * @return int[]
     */
    public static function allBlockedStudentIds(): array
    {
        return PaymentRecord::overdue()
            ->select('student_id', 'teacher_id')
            ->distinct()
            ->get()
            ->filter(fn(PaymentRecord $r) => self::isBlockedForTeacher((int) $r->student_id, (int) $r->teacher_id))
            ->pluck('student_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
