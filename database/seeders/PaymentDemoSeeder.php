<?php

namespace Database\Seeders;

use App\Models\LessonType;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Демо-данные для проверки механизма отметок оплаты.
 * Запуск: php artisan db:seed --class=PaymentDemoSeeder
 *
 * Логины (пароль у всех: password):
 *   Учитель:  pay-teacher@demo.ru  → /tutor  (список «Ученики» — все статусы оплаты)
 *   Ученики:  pay-paid@demo.ru            — всё оплачено (+ одно отменённое в истории)
 *             pay-pending@demo.ru         — ожидает оплаты, срок не прошёл (баннера нет)
 *             pay-overdue@demo.ru         — просрочка → баннер в кабинете
 *             pay-blocked@demo.ru         — заблокирован → редирект на страницу «Оплата»
 *             pay-monthly-paid@demo.ru    — помесячная, месяц оплачен
 *             pay-monthly-overdue@demo.ru — помесячная, месяц просрочен → баннер
 *             pay-free@demo.ru            — бесплатный ученик, оплата не отслеживается
 *             pay-override@demo.ru        — персональная помесячная оплата (override)
 *             pay-extra-1..6@demo.ru      — обычная история: оплачено + свежее начисление
 */
class PaymentDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Пересоздаём демо-данные при повторном запуске
        User::where('email', 'like', 'pay-%@demo.ru')->get()->each->delete();

        $teacher = $this->createUser('pay-teacher@demo.ru', 'Магомед Евлоев', User::ROLE_TUTOR);
        $teacher->update(['is_profile_completed' => true]);

        LessonType::create([
            'user_id' => $teacher->id,
            'type' => LessonType::TYPE_INDIVIDUAL,
            'price' => 1000,
            'payment_type' => PaymentRecord::TYPE_PER_LESSON,
            'count_per_week' => 2,
            'duration' => 60,
        ]);
        LessonType::create([
            'user_id' => $teacher->id,
            'type' => LessonType::TYPE_GROUP,
            'price' => 3000,
            'payment_type' => PaymentRecord::TYPE_MONTHLY,
            'count_per_week' => 2,
            'duration' => 60,
        ]);

        $groupRoom = $this->createRoom($teacher, 'Группа (помесячно)', 'group');

        // ── Кейс 1: всё оплачено + одно отменённое начисление в истории ──
        $student = $this->createStudent($teacher, 'pay-paid@demo.ru', 'Хава Оздоева');
        $room = $this->createRoom($teacher, 'Математика — Хава', 'individual', $student);
        foreach ([10, 5] as $daysAgo) {
            $this->createLessonRecord($teacher, $student, $room, $daysAgo, PaymentRecord::STATUS_PAID);
        }
        $this->createLessonRecord($teacher, $student, $room, 3, PaymentRecord::STATUS_CANCELLED);

        // ── Кейс 2: ожидает оплаты, срок ещё не прошёл (баннера нет) ──
        $student = $this->createStudent($teacher, 'pay-pending@demo.ru', 'Адам Мальсагов');
        $room = $this->createRoom($teacher, 'Физика — Адам', 'individual', $student);
        $this->createLessonRecord($teacher, $student, $room, 1, PaymentRecord::STATUS_UNPAID); // срок через 2 дня

        // ── Кейс 3: просрочка → баннер в кабинете, но ещё не заблокирован ──
        $student = $this->createStudent($teacher, 'pay-overdue@demo.ru', 'Ибрагим Костоев');
        $room = $this->createRoom($teacher, 'Химия — Ибрагим', 'individual', $student);
        $this->createLessonRecord($teacher, $student, $room, 8, PaymentRecord::STATUS_UNPAID);  // просрочено 5 дней
        $this->createLessonRecord($teacher, $student, $room, 6, PaymentRecord::STATUS_UNPAID);  // просрочено 3 дня

        // ── Кейс 4: продолжил ходить с долгом → кабинет заблокирован ──
        $student = $this->createStudent($teacher, 'pay-blocked@demo.ru', 'Муса Плиев');
        $room = $this->createRoom($teacher, 'История — Муса', 'individual', $student);
        $this->createLessonRecord($teacher, $student, $room, 15, PaymentRecord::STATUS_UNPAID); // старый долг
        $this->createLessonRecord($teacher, $student, $room, 2, PaymentRecord::STATUS_UNPAID);  // пришёл с долгом
        $student->update(['payment_blocked_at' => now()->subDay()]);

        // ── Кейс 5: помесячная оплата, месяц оплачен ──
        $monthlyPaid = $this->createStudent($teacher, 'pay-monthly-paid@demo.ru', 'Марем Аушева');
        // ── Кейс 6: помесячная оплата, месяц просрочен → баннер ──
        $monthlyOverdue = $this->createStudent($teacher, 'pay-monthly-overdue@demo.ru', 'Иса Цечоев');

        $groupRoom->participants()->attach([$monthlyPaid->id, $monthlyOverdue->id]);
        // RoomObserver пересчитывает тип по числу участников на событии saved
        $groupRoom->save();

        $this->createMonthlyRecord($teacher, $monthlyPaid, now()->subMonth(), PaymentRecord::STATUS_PAID);
        $this->createMonthlyRecord($teacher, $monthlyPaid, now(), PaymentRecord::STATUS_PAID);
        $this->createMonthlyRecord($teacher, $monthlyOverdue, now()->subMonth(), PaymentRecord::STATUS_PAID);
        $this->createMonthlyRecord($teacher, $monthlyOverdue, now(), PaymentRecord::STATUS_UNPAID);

        // ── Кейс 7: бесплатный ученик — оплата не отслеживается ──
        $student = $this->createStudent($teacher, 'pay-free@demo.ru', 'Аминат Котиева');
        $teacher->students()->updateExistingPivot($student->id, ['is_free' => true]);
        $this->createRoom($teacher, 'Биология — Аминат', 'individual', $student);

        // ── Кейс 8: индивидуальный ученик с персональной помесячной оплатой ──
        // (у учителя индивидуальные занятия поурочные, но для Мадины — помесячно)
        $student = $this->createStudent($teacher, 'pay-override@demo.ru', 'Мадина Барахоева');
        $teacher->students()->updateExistingPivot($student->id, ['payment_type_override' => PaymentRecord::TYPE_MONTHLY]);
        $this->createRoom($teacher, 'Английский — Мадина', 'individual', $student);
        $this->createMonthlyRecord($teacher, $student, now()->subMonth(), PaymentRecord::STATUS_PAID);
        $this->createMonthlyRecord($teacher, $student, now(), PaymentRecord::STATUS_UNPAID);

        // ── Остальные ученики: обычная история — пара оплаченных занятий и одно свежее начисление ──
        $extras = [
            ['pay-extra-1@demo.ru', 'Танзила Хамхоева', 'Алгебра — Танзила'],
            ['pay-extra-2@demo.ru', 'Ахмед Точиев', 'Геометрия — Ахмед'],
            ['pay-extra-3@demo.ru', 'Заира Медова', 'Русский язык — Заира'],
            ['pay-extra-4@demo.ru', 'Алихан Албаков', 'Информатика — Алихан'],
            ['pay-extra-5@demo.ru', 'Луиза Гагиева', 'Обществознание — Луиза'],
            ['pay-extra-6@demo.ru', 'Дауд Ужахов', 'География — Дауд'],
        ];
        foreach ($extras as $i => [$email, $studentName, $roomName]) {
            $student = $this->createStudent($teacher, $email, $studentName);
            $room = $this->createRoom($teacher, $roomName, 'individual', $student);
            $this->createLessonRecord($teacher, $student, $room, 12 + $i, PaymentRecord::STATUS_PAID);
            $this->createLessonRecord($teacher, $student, $room, 7 + $i, PaymentRecord::STATUS_PAID);
            $this->createLessonRecord($teacher, $student, $room, 1 + ($i % 2), PaymentRecord::STATUS_UNPAID); // срок не прошёл
        }

        $this->command?->info('Демо-данные оплаты созданы. Учитель: pay-teacher@demo.ru, пароль: password');
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

    private function createRoom(User $teacher, string $name, string $type, ?User $participant = null): Room
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'type' => $type,
            'meeting_id' => (string) Str::uuid(),
            'moderator_pw' => Str::random(8),
            'attendee_pw' => Str::random(8),
        ]);

        if ($participant) {
            $room->participants()->attach($participant->id);
        }

        return $room;
    }

    /**
     * Проведённое занятие N дней назад + поурочное начисление по нему.
     * Срок оплаты — как в реальной генерации: дата занятия + 3 дня.
     */
    private function createLessonRecord(User $teacher, User $student, Room $room, int $daysAgo, string $status): void
    {
        $start = now()->subDays($daysAgo)->setTime(rand(10, 18), 0);

        $session = MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes(60),
            'status' => 'completed',
            'participant_count' => 1,
            'analytics_data' => ['participants' => [['user_id' => (string) $student->id]]],
        ]);

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

    private function createMonthlyRecord(User $teacher, User $student, \Illuminate\Support\Carbon $month, string $status): void
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
