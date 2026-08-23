<?php

namespace Database\Seeders;

use App\Models\Direct;
use App\Models\LessonType;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Демо-профиль преподавателя химии + все состояния оплаты.
 * Запуск: php artisan db:seed --class=PaymentDemoSeeder
 *
 * Логины (пароль у всех: password):
 *   Учитель:  pay-teacher@demo.ru  → /tutor
 *   Ученики:  pay-paid@demo.ru            — всё оплачено (+ одно отменённое в истории)
 *             pay-pending@demo.ru         — ожидает оплаты, срок не прошёл (баннера нет)
 *             pay-overdue@demo.ru         — просрочка → баннер в кабинете
 *             pay-blocked@demo.ru         — заблокирован → редирект на страницу «Оплата»
 *             pay-monthly-paid@demo.ru    — помесячная, месяц оплачен
 *             pay-monthly-overdue@demo.ru — помесячная, месяц просрочен → баннер
 *             pay-free@demo.ru            — бесплатный ученик, оплата не отслеживается
 *             pay-override@demo.ru        — персональная помесячная оплата (override)
 *             pay-extra-1..6@demo.ru      — обычная история: оплачено + свежее начисление
 *
 * Занятия: индивидуальные по темам химии + две группы (7 и 5 учеников).
 *
 * Почему групповые занятия поурочные, а не помесячные:
 * тип оплаты берётся из типа занятия комнаты, а личное переопределение
 * (teacher_student.payment_type_override) его перебивает. Сделав группы поурочными,
 * мы можем набирать в них любых учеников, не ломая их сценарии оплаты, —
 * помесячные ученики остаются помесячными за счёт переопределения.
 */
class PaymentDemoSeeder extends Seeder
{
    /** Ученики, которым поурочные начисления не создаются */
    private array $skipPerLessonIds = [];

    public function run(): void
    {
        // Пересоздаём демо-данные при повторном запуске
        // (удаление пользователя каскадом уносит комнаты, сессии и начисления)
        User::where('email', 'like', 'pay-%@demo.ru')->get()->each->delete();

        $teacher = $this->createTeacher();

        // ── Кейс 1: всё оплачено + одно отменённое начисление в истории ──
        $hawa = $this->createStudent($teacher, 'pay-paid@demo.ru', 'Хава Оздоева');
        $room = $this->createRoom($teacher, 'Органическая химия — Хава', $hawa);
        foreach ([10, 5] as $daysAgo) {
            $this->createLessonRecord($teacher, $hawa, $room, $daysAgo, PaymentRecord::STATUS_PAID);
        }
        $this->createLessonRecord($teacher, $hawa, $room, 3, PaymentRecord::STATUS_CANCELLED);

        // ── Кейс 2: ожидает оплаты, срок ещё не прошёл (баннера нет) ──
        $adam = $this->createStudent($teacher, 'pay-pending@demo.ru', 'Адам Мальсагов');
        $room = $this->createRoom($teacher, 'Неорганическая химия — Адам', $adam);
        $this->createLessonRecord($teacher, $adam, $room, 1, PaymentRecord::STATUS_UNPAID); // срок через 2 дня

        // ── Кейс 3: просрочка → баннер в кабинете, но ещё не заблокирован ──
        $ibragim = $this->createStudent($teacher, 'pay-overdue@demo.ru', 'Ибрагим Костоев');
        $room = $this->createRoom($teacher, 'Химия ЕГЭ — Ибрагим', $ibragim);
        $this->createLessonRecord($teacher, $ibragim, $room, 8, PaymentRecord::STATUS_UNPAID);  // просрочено 5 дней
        $this->createLessonRecord($teacher, $ibragim, $room, 6, PaymentRecord::STATUS_UNPAID);  // просрочено 3 дня

        // ── Кейс 4: продолжил ходить с долгом → кабинет заблокирован ──
        $musa = $this->createStudent($teacher, 'pay-blocked@demo.ru', 'Муса Плиев');
        $room = $this->createRoom($teacher, 'Химия ОГЭ — Муса', $musa);
        $this->createLessonRecord($teacher, $musa, $room, 15, PaymentRecord::STATUS_UNPAID); // старый долг
        $this->createLessonRecord($teacher, $musa, $room, 2, PaymentRecord::STATUS_UNPAID);  // пришёл с долгом
        $musa->update(['payment_blocked_at' => now()->subDay()]);

        // ── Кейсы 5 и 6: помесячная оплата ──
        $marem = $this->createStudent($teacher, 'pay-monthly-paid@demo.ru', 'Марем Аушева');
        $isa = $this->createStudent($teacher, 'pay-monthly-overdue@demo.ru', 'Иса Цечоев');

        foreach ([$marem, $isa] as $monthly) {
            $teacher->students()->updateExistingPivot($monthly->id, [
                'payment_type_override' => PaymentRecord::TYPE_MONTHLY,
            ]);
            $this->skipPerLessonIds[] = $monthly->id;
        }

        $this->createMonthlyRecord($teacher, $marem, now()->subMonth(), PaymentRecord::STATUS_PAID);
        $this->createMonthlyRecord($teacher, $marem, now(), PaymentRecord::STATUS_PAID);
        $this->createMonthlyRecord($teacher, $isa, now()->subMonth(), PaymentRecord::STATUS_PAID);
        $this->createMonthlyRecord($teacher, $isa, now(), PaymentRecord::STATUS_UNPAID);

        // ── Кейс 7: бесплатный ученик — оплата не отслеживается ──
        $aminat = $this->createStudent($teacher, 'pay-free@demo.ru', 'Аминат Котиева');
        $teacher->students()->updateExistingPivot($aminat->id, ['is_free' => true]);
        $this->skipPerLessonIds[] = $aminat->id;
        $this->createRoom($teacher, 'Химия с нуля — Аминат', $aminat);

        // ── Кейс 8: индивидуальный ученик с персональной помесячной оплатой ──
        $madina = $this->createStudent($teacher, 'pay-override@demo.ru', 'Мадина Барахоева');
        $teacher->students()->updateExistingPivot($madina->id, ['payment_type_override' => PaymentRecord::TYPE_MONTHLY]);
        $this->skipPerLessonIds[] = $madina->id;
        $this->createRoom($teacher, 'Химия ЕГЭ — Мадина', $madina);
        $this->createMonthlyRecord($teacher, $madina, now()->subMonth(), PaymentRecord::STATUS_PAID);
        $this->createMonthlyRecord($teacher, $madina, now(), PaymentRecord::STATUS_UNPAID);

        // ── Остальные ученики: обычная история — пара оплаченных занятий и одно свежее начисление ──
        $extras = [
            ['pay-extra-1@demo.ru', 'Танзила Хамхоева', 'Задачи на растворы — Танзила'],
            ['pay-extra-2@demo.ru', 'Ахмед Точиев', 'Окислительно-восстановительные реакции — Ахмед'],
            ['pay-extra-3@demo.ru', 'Заира Медова', 'Химическая кинетика — Заира'],
            ['pay-extra-4@demo.ru', 'Алихан Албаков', 'Термохимия — Алихан'],
            ['pay-extra-5@demo.ru', 'Луиза Гагиева', 'Электролиз — Луиза'],
            ['pay-extra-6@demo.ru', 'Дауд Ужахов', 'Качественные реакции — Дауд'],
        ];

        $extraStudents = [];
        foreach ($extras as $i => [$email, $studentName, $roomName]) {
            $student = $this->createStudent($teacher, $email, $studentName);
            $extraStudents[] = $student;

            $room = $this->createRoom($teacher, $roomName, $student);
            $this->createLessonRecord($teacher, $student, $room, 12 + $i, PaymentRecord::STATUS_PAID);
            $this->createLessonRecord($teacher, $student, $room, 7 + $i, PaymentRecord::STATUS_PAID);
            $this->createLessonRecord($teacher, $student, $room, 1 + ($i % 2), PaymentRecord::STATUS_UNPAID); // срок не прошёл
        }

        // ── Групповые занятия ──
        $egeGroup = $this->createGroupRoom($teacher, 'Химия ЕГЭ — группа 11 класса', [
            $hawa, $adam, $marem, $madina, $extraStudents[0], $extraStudents[1], $extraStudents[2],
        ]);
        $ogeGroup = $this->createGroupRoom($teacher, 'Химия ОГЭ — группа 9 класса', [
            $ibragim, $musa, $isa, $extraStudents[3], $extraStudents[4],
        ]);

        foreach ([14, 7] as $daysAgo) {
            $this->createGroupLesson($teacher, $egeGroup, $daysAgo);
            $this->createGroupLesson($teacher, $ogeGroup, $daysAgo + 1);
        }

        // ── Расписание: заполняет календарь на месяц назад и на месяц вперёд ──
        $this->createSchedules($teacher, $egeGroup, $ogeGroup);

        $this->command?->info('Демо-данные созданы. Преподаватель химии: pay-teacher@demo.ru, пароль: password');
    }

    /**
     * Недельная сетка занятий + пара разовых событий.
     * Календарь в кабинете рисует диапазон «месяц назад — два месяца вперёд»,
     * поэтому расписание ограничиваем ровно месяцем в каждую сторону.
     */
    private function createSchedules(User $teacher, Room $egeGroup, Room $ogeGroup): void
    {
        $rooms = $teacher->rooms()->get()->keyBy('name');
        $from = now()->subMonth()->startOfMonth();
        $to = now()->addMonth()->endOfMonth();

        // [комната, дни недели (1 = Пн … 6 = Сб), время, минут]
        $timetable = [
            ['Химия ЕГЭ — группа 11 класса', [1, 4], '17:00', 90],
            ['Химия ОГЭ — группа 9 класса', [2, 5], '16:00', 90],

            ['Органическая химия — Хава', [1], '14:00', 60],
            ['Органическая химия — Хава', [6], '11:00', 60],
            ['Неорганическая химия — Адам', [1], '15:00', 60],
            ['Химия ЕГЭ — Ибрагим', [2], '14:00', 60],
            ['Химия ЕГЭ — Ибрагим', [6], '12:00', 60],
            ['Химия ОГЭ — Муса', [2], '15:00', 60],
            ['Химия ЕГЭ — Мадина', [3], '14:00', 60],
            ['Химия с нуля — Аминат', [3], '15:00', 60],
            ['Задачи на растворы — Танзила', [3], '16:00', 60],
            ['Окислительно-восстановительные реакции — Ахмед', [3], '17:00', 60],
            ['Химическая кинетика — Заира', [4], '14:00', 60],
            ['Термохимия — Алихан', [4], '15:00', 60],
            ['Электролиз — Луиза', [5], '14:00', 60],
            ['Качественные реакции — Дауд', [5], '15:00', 60],
        ];

        foreach ($timetable as [$roomName, $days, $time, $duration]) {
            $room = $rooms->get($roomName);

            if (!$room) {
                continue;
            }

            RoomSchedule::create([
                'room_id' => $room->id,
                'type' => 'recurring',
                'recurrence_type' => 'weekly',
                'recurrence_days' => $days,
                'recurrence_time' => $time,
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                'duration_minutes' => $duration,
                'is_active' => true,
            ]);
        }

        // Разовые события: в календаре они видны и в прошлом, и в будущем
        $consultation = $this->createGroupRoom($teacher, 'Консультация перед ОГЭ', $ogeGroup->participants->all());
        RoomSchedule::create([
            'room_id' => $consultation->id,
            'type' => 'once',
            // воскресенье — единственный день без занятий, накладок не будет
            'scheduled_at' => now()->subDays(7)->previous(Carbon::SUNDAY)->setTime(12, 0),
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $trial = $this->createGroupRoom($teacher, 'Пробный ЕГЭ по химии', $egeGroup->participants->all());
        RoomSchedule::create([
            'room_id' => $trial->id,
            'type' => 'once',
            'scheduled_at' => now()->addDays(7)->next(Carbon::SUNDAY)->setTime(10, 0),
            'duration_minutes' => 180,
            'is_active' => true,
        ]);
    }

    private function createTeacher(): User
    {
        $teacher = $this->createUser('pay-teacher@demo.ru', 'Магомед Евлоев', User::ROLE_TUTOR);

        $teacher->update([
            'is_profile_completed' => true,
            'about' => 'Преподаватель химии. Готовлю к ЕГЭ и ОГЭ, разбираю олимпиадные задачи. '
                . 'Занятия строю от задач: сначала разбираем механизм реакции, потом закрепляем на вариантах.',
        ]);

        $teacher->subjects()->sync([Subject::firstOrCreate(['name' => 'Химия'])->id]);
        $teacher->directs()->sync(
            collect(['ЕГЭ', 'ОГЭ', 'Олимпиады'])
                ->map(fn (string $name) => Direct::firstOrCreate(['name' => $name])->id)
                ->all()
        );

        // Базовые цены: и индивидуальные, и групповые занятия поурочные
        LessonType::create([
            'user_id' => $teacher->id,
            'type' => LessonType::TYPE_INDIVIDUAL,
            'price' => 1500,
            'payment_type' => PaymentRecord::TYPE_PER_LESSON,
            'count_per_week' => 2,
            'duration' => 60,
        ]);
        LessonType::create([
            'user_id' => $teacher->id,
            'type' => LessonType::TYPE_GROUP,
            'price' => 900,
            'payment_type' => PaymentRecord::TYPE_PER_LESSON,
            'count_per_week' => 2,
            'duration' => 90,
        ]);

        return $teacher;
    }

    private function createUser(string $email, string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'username' => 'pay-demo-' . Str::random(6),
            'password' => Hash::make('password'),
            'role' => $role,
            'phone' => '+79990000000',
            'whatsup' => '79990000000',
            'telegram' => 'example',
        ]);
    }

    private function createStudent(User $teacher, string $email, string $name): User
    {
        $student = $this->createUser($email, $name, User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);

        return $student;
    }

    /** Индивидуальная комната: тип проставит RoomObserver по числу участников */
    private function createRoom(User $teacher, string $name, ?User $participant = null): Room
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'type' => 'individual',
            'meeting_id' => (string) Str::uuid(),
            'moderator_pw' => Str::random(8),
            'attendee_pw' => Str::random(8),
        ]);

        if ($participant) {
            $room->participants()->attach($participant->id);
            $room->save(); // RoomObserver пересчитывает тип на событии saved
        }

        return $room;
    }

    /** @param User[] $students */
    private function createGroupRoom(User $teacher, string $name, array $students): Room
    {
        $room = $this->createRoom($teacher, $name);
        $room->participants()->attach(collect($students)->pluck('id')->all());
        $room->save();

        return $room;
    }

    /**
     * Проведённое занятие N дней назад + поурочное начисление по нему.
     * Срок оплаты — как в реальной генерации: дата занятия + 3 дня.
     */
    private function createLessonRecord(User $teacher, User $student, Room $room, int $daysAgo, string $status): void
    {
        $session = $this->createSession($teacher, $room, [$student], $daysAgo, 60);
        $this->createRecordForSession($teacher, $student, $session, $status);
    }

    /**
     * Групповое занятие: одна сессия на всех участников и поурочное начисление
     * каждому, кроме бесплатных и помесячных — ровно как это делает
     * PaymentRecordService при завершении реальной встречи.
     */
    private function createGroupLesson(User $teacher, Room $room, int $daysAgo): void
    {
        $students = $room->participants()->get();
        $session = $this->createSession($teacher, $room, $students->all(), $daysAgo, 90);

        foreach ($students as $student) {
            if (in_array($student->id, $this->skipPerLessonIds, true)) {
                continue;
            }

            $this->createRecordForSession($teacher, $student, $session, PaymentRecord::STATUS_PAID);
        }
    }

    /** @param User[] $students */
    private function createSession(User $teacher, Room $room, array $students, int $daysAgo, int $minutes): MeetingSession
    {
        $start = now()->subDays($daysAgo)->setTime(rand(10, 18), 0);

        return MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes($minutes),
            'status' => 'completed',
            'participant_count' => count($students),
            'analytics_data' => [
                'participants' => collect($students)
                    ->map(fn (User $s) => ['user_id' => (string) $s->id])
                    ->all(),
            ],
        ]);
    }

    private function createRecordForSession(User $teacher, User $student, MeetingSession $session, string $status): void
    {
        $start = $session->started_at;

        PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => PaymentRecord::TYPE_PER_LESSON,
            'meeting_session_id' => $session->id,
            'status' => $status,
            'due_date' => $start->copy()->addDays(\App\Services\PaymentRecordService::PER_LESSON_DUE_DAYS)->toDateString(),
            'paid_at' => $status === PaymentRecord::STATUS_PAID ? $start->copy()->addDay() : null,
            'marked_by' => $status !== PaymentRecord::STATUS_UNPAID ? $teacher->id : null,
        ]);
    }

    private function createMonthlyRecord(User $teacher, User $student, Carbon $month, string $status): void
    {
        PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => PaymentRecord::TYPE_MONTHLY,
            'period' => $month->format('Y-m'),
            'status' => $status,
            'due_date' => $month->copy()->startOfMonth()->addDays(\App\Services\PaymentRecordService::MONTHLY_DUE_DAY - 1)->toDateString(),
            'paid_at' => $status === PaymentRecord::STATUS_PAID ? $month->copy()->startOfMonth()->addDays(2) : null,
            'marked_by' => $status !== PaymentRecord::STATUS_UNPAID ? $teacher->id : null,
        ]);
    }
}
