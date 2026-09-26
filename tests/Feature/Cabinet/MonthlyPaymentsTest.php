<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Payments;
use App\Livewire\Cabinet\Teacher\Profile;
use App\Livewire\Cabinet\Teacher\Student;
use App\Models\LessonType;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\RoomScheduleException;
use App\Models\User;
use App\Services\PaymentClaimService;
use App\Services\PaymentRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Оплата за месяц: счёт 1-го числа (или позже, когда появились занятия) только при занятиях в этом месяце, сумма в начислении,
 * суммы в кабинетах и тексты правил о закрытом входе.
 */
class MonthlyPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-01 06:00'));
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    private function teacher(string $payment = LessonType::PAYMENT_MONTHLY, int $price = 8000, string $type = LessonType::TYPE_INDIVIDUAL): User
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $this->price($teacher, $payment, $price, $type);

        return $teacher;
    }

    private function price(User $teacher, string $payment, int $price, string $type = LessonType::TYPE_INDIVIDUAL): LessonType
    {
        return LessonType::create([
            'user_id' => $teacher->id,
            'type' => $type,
            'price' => $price,
            'payment_type' => $payment,
            'count_per_week' => $payment === LessonType::PAYMENT_MONTHLY ? 2 : null,
            'payment_due_day' => 5,
            'payment_due_days' => 3,
            'duration' => 60,
        ]);
    }

    private function student(User $teacher, string $name = 'Алина'): User
    {
        $student = $this->user(User::ROLE_STUDENT, ['first_name' => $name, 'last_name' => 'Смирнова']);
        $teacher->students()->attach($student->id);

        return $student;
    }

    private function room(User $teacher, array $students, string $type = LessonType::TYPE_INDIVIDUAL, string $name = 'Математика'): Room
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'type' => $type,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        $room->participants()->attach(collect($students)->pluck('id')->all());
        // Тип занятия RoomObserver выводит из числа участников при сохранении; в тестах задаём явно
        $room->updateQuietly(['type' => $type]);

        return $room;
    }

    private function lessonOn(Room $room, string $at): RoomSchedule
    {
        return RoomSchedule::create([
            'room_id' => $room->id,
            'type' => 'once',
            'scheduled_at' => Carbon::parse($at),
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    private function exception(RoomSchedule $schedule, string $status, ?string $movedTo = null): void
    {
        RoomScheduleException::create([
            'room_id' => $schedule->room_id,
            'room_schedule_id' => $schedule->id,
            'original_date' => $schedule->scheduled_at->toDateString(),
            'original_starts_at' => $schedule->scheduled_at,
            'status' => $status,
            'starts_at' => $movedTo ? Carbon::parse($movedTo) : null,
        ]);
    }

    public function test_monthly_charge_carries_price_and_is_created_once(): void
    {
        $teacher = $this->teacher();
        $student = $this->student($teacher);
        $this->lessonOn($this->room($teacher, [$student]), '2026-10-15 16:00');

        $this->assertSame(1, PaymentRecordService::generateMonthlyRecords());
        $this->assertSame(0, PaymentRecordService::generateMonthlyRecords());

        $record = PaymentRecord::sole();
        $this->assertSame(PaymentRecord::TYPE_MONTHLY, $record->type);
        $this->assertSame('2026-10', $record->period);
        $this->assertSame('2026-10-05', $record->due_date->toDateString());
        $this->assertSame(8000, $record->amount);
        $this->assertSame(8000, $record->amount());
    }

    public function test_no_charge_without_lessons_in_the_month(): void
    {
        $teacher = $this->teacher();
        $noSchedule = $this->student($teacher, 'Вера');
        $cancelled = $this->student($teacher, 'Глеб');
        $movedAway = $this->student($teacher, 'Дина');
        $nextMonth = $this->student($teacher, 'Егор');

        $this->room($teacher, [$noSchedule], name: 'Без расписания');
        $this->exception($this->lessonOn($this->room($teacher, [$cancelled], name: 'Отменено'), '2026-10-10 16:00'), RoomScheduleException::STATUS_CANCELLED);
        $this->exception($this->lessonOn($this->room($teacher, [$movedAway], name: 'Перенесено'), '2026-10-30 16:00'), RoomScheduleException::STATUS_MOVED, '2026-11-02 16:00');
        $this->lessonOn($this->room($teacher, [$nextMonth], name: 'В ноябре'), '2026-11-03 16:00');

        $this->assertSame(0, PaymentRecordService::generateMonthlyRecords());
        $this->assertSame(0, PaymentRecord::count());
    }

    public function test_lesson_moved_into_the_month_counts(): void
    {
        $teacher = $this->teacher();
        $student = $this->student($teacher);
        $this->exception($this->lessonOn($this->room($teacher, [$student]), '2026-09-29 16:00'), RoomScheduleException::STATUS_MOVED, '2026-10-02 16:00');

        $this->assertSame(1, PaymentRecordService::generateMonthlyRecords());
        $this->assertSame($student->id, PaymentRecord::sole()->student_id);
    }

    public function test_student_added_mid_month_gets_full_charge_next_morning_with_time_to_pay(): void
    {
        $teacher = $this->teacher();
        $old = $this->student($teacher, 'Вера');
        $room = $this->room($teacher, [$old], LessonType::TYPE_INDIVIDUAL);
        $this->lessonOn($room, '2026-10-06 16:00');
        $this->lessonOn($room, '2026-10-27 16:00');
        $this->assertSame(1, PaymentRecordService::generateMonthlyRecords());

        // 20-го учитель добавил нового ученика в то же занятие — утром 21-го ему приходит полный счёт
        $this->travelTo(Carbon::parse('2026-10-21 06:00'));
        $new = $this->student($teacher, 'Глеб');
        $room->participants()->attach($new->id);

        $this->assertSame(1, PaymentRecordService::generateMonthlyRecords());
        $record = PaymentRecord::where('student_id', $new->id)->sole();
        $this->assertSame('2026-10', $record->period);
        $this->assertSame(8000, $record->amount);
        // До 5-го уже прошло — срок не меньше 3 дней
        $this->assertSame('2026-10-24', $record->due_date->toDateString());
        $this->assertSame(1, PaymentRecord::where('student_id', $old->id)->count(), 'Старому ученику второй счёт не выставляем');
    }

    public function test_no_charge_mid_month_when_remaining_lessons_are_next_month(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 06:00'));
        $teacher = $this->teacher();
        $student = $this->student($teacher);
        $room = $this->room($teacher, [$student]);
        // Занятия группы в этом месяце уже прошли, следующее — в ноябре
        $this->lessonOn($room, '2026-10-14 16:00');
        $this->lessonOn($room, '2026-11-04 16:00');

        $this->assertSame(0, PaymentRecordService::generateMonthlyRecords());
    }

    public function test_per_lesson_and_free_students_get_no_monthly_charge_and_price_follows_student_terms(): void
    {
        $teacher = $this->teacher(LessonType::PAYMENT_PER_LESSON, 1500);
        $this->price($teacher, LessonType::PAYMENT_MONTHLY, 6000, LessonType::TYPE_GROUP);

        $perLesson = $this->student($teacher, 'Вера');
        $override = $this->student($teacher, 'Глеб');
        $free = $this->student($teacher, 'Дина');
        $custom = $this->student($teacher, 'Егор');
        DB::table('teacher_student')->where('student_id', $override->id)->update(['payment_type_override' => PaymentRecord::TYPE_MONTHLY]);
        DB::table('teacher_student')->where('student_id', $free->id)->update(['is_free' => true]);

        $this->lessonOn($this->room($teacher, [$perLesson]), '2026-10-12 16:00');
        $this->lessonOn($this->room($teacher, [$override], name: 'Физика'), '2026-10-13 16:00');
        $group = $this->room($teacher, [$free, $custom], LessonType::TYPE_GROUP, 'Группа');
        $group->participants()->updateExistingPivot($custom->id, ['custom_price' => 5000]);
        $this->lessonOn($group, '2026-10-20 18:00');

        $this->assertSame(2, PaymentRecordService::generateMonthlyRecords());

        // Помесячно по условиям ученика в индивидуальном занятии — единственная помесячная цена учителя
        $this->assertSame(6000, PaymentRecord::where('student_id', $override->id)->sole()->amount());
        // Личная цена ученика в группе
        $this->assertSame(5000, PaymentRecord::where('student_id', $custom->id)->sole()->amount());
        $this->assertFalse(PaymentRecord::whereIn('student_id', [$perLesson->id, $free->id])->exists());
    }

    public function test_backfill_fills_only_unambiguous_unpaid_monthly_charges(): void
    {
        $teacher = $this->teacher();
        $this->price($teacher, LessonType::PAYMENT_MONTHLY, 6000, LessonType::TYPE_GROUP);
        $single = $this->student($teacher, 'Вера');
        $twoRooms = $this->student($teacher, 'Глеб');
        $this->room($teacher, [$single, $twoRooms]);
        $this->room($teacher, [$twoRooms], LessonType::TYPE_GROUP, 'Группа');

        $make = fn (User $s, string $status = PaymentRecord::STATUS_UNPAID) => PaymentRecord::create([
            'teacher_id' => $teacher->id, 'student_id' => $s->id, 'type' => PaymentRecord::TYPE_MONTHLY,
            'period' => $status === PaymentRecord::STATUS_PAID ? '2026-08' : '2026-09', 'status' => $status, 'due_date' => '2026-09-05',
        ]);
        $unpaid = $make($single);
        $paid = $make($single, PaymentRecord::STATUS_PAID);
        $ambiguous = $make($twoRooms);

        $this->assertSame(1, PaymentRecordService::backfillMonthlyAmounts());
        $this->assertSame(8000, $unpaid->fresh()->amount());
        $this->assertNull($paid->fresh()->amount());
        $this->assertNull($ambiguous->fresh()->amount());
    }

    public function test_monthly_sums_are_shown_to_student_and_teacher(): void
    {
        $teacher = $this->teacher();
        $student = $this->student($teacher);
        $this->room($teacher, [$student]);
        $monthly = PaymentRecord::create([
            'teacher_id' => $teacher->id, 'student_id' => $student->id, 'type' => PaymentRecord::TYPE_MONTHLY,
            'period' => '2026-10', 'amount' => 8000, 'status' => PaymentRecord::STATUS_UNPAID, 'due_date' => '2026-10-05',
        ]);

        $this->assertSame(8000, PaymentClaimService::knownSum(collect([$monthly])));

        $this->actingAs($student)->get(route('cabinet.student.payments'))
            ->assertOk()
            ->assertSee('Оплата за октябрь')
            ->assertSee('8 000 ₽');

        $this->actingAs($student)->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('8 000 ₽');

        Livewire::actingAs($student)->test(Payments::class)
            ->call('openReport', $teacher->id)
            ->set('reportSelected', [$monthly->id])
            ->assertSee('Итого')
            ->assertSee('8 000 ₽');

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->set('tab', 'pay')
            ->assertSee("8\u{00A0}000\u{00A0}₽")
            ->assertSee('За месяц')
            ->assertSee('счёт за месяц, если в нём есть занятия');
    }

    public function test_texts_describe_real_block_rule(): void
    {
        $teacher = $this->teacher(LessonType::PAYMENT_PER_LESSON, 1500);
        $student = $this->student($teacher);
        $this->room($teacher, [$student]);

        Livewire::actingAs($teacher)->test(Profile::class)
            ->set('tab', 'prices')
            ->assertSee('побывал ещё на')
            ->assertSee('продлите срок')
            ->assertDontSee('пока вы не отметите оплату');

        Livewire::actingAs($teacher)->test(Student::class, ['student' => $student])
            ->set('tab', 'pay')
            ->assertSee('За каждое занятие')
            ->assertDontSee('Поурочно')
            ->assertSee('продлите срок');
    }
}
