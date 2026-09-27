<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Today;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Message;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Notifications\TeacherAssignedLesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** «Сегодня» учителя в новом кабинете (/cabinet/teacher). */
class TeacherTodayTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->fixNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_is_redirected_and_student_is_forbidden(): void
    {
        $this->get(route('cabinet.teacher.today'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.teacher.today'))
            ->assertRedirect(route('cabinet.student.home'));
    }

    public function test_new_teacher_sees_first_steps_with_invitation_link(): void
    {
        $teacher = $this->teacher();
        \App\Models\LessonType::create(['user_id' => $teacher->id, 'type' => 'individual', 'price' => 1500, 'payment_type' => 'per_lesson', 'duration' => 60]);

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertSee('Добро пожаловать')
            ->assertSee('Цены указаны')
            ->assertSee('1 500 ₽ за 60 минут')
            ->assertSee('Первые шаги')
            ->assertSee('Пригласите первого ученика')
            ->assertSee('Скопировать ссылку')
            ->assertSee('register/invite', false)
            ->assertDontSee('Занятия сегодня');
    }

    public function test_today_lessons_with_start_button_for_nearest(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $this->onceAt($room, '2026-09-24 16:00');

        $past = $this->room($teacher, [$this->studentOf($teacher, 'Иван Петров')], ['name' => 'Математика']);
        $this->onceAt($past, '2026-09-24 09:00');
        $this->completedSession($past, '2026-09-24 09:02', 58, [$past->participants->first()->id]);

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertSee('Занятия сегодня')
            ->assertSee('Английский язык · Алина Смирнова')
            ->assertSee('Начнётся через 4 часа')
            ->assertSee('Начать занятие')
            ->assertSee(route('rooms.start', $room), false)
            ->assertSee('Математика · Иван Петров')
            ->assertSee('Завершено');
    }

    public function test_running_lesson_offers_to_return_to_class(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)], ['is_running' => true]);
        $this->onceAt($room, '2026-09-24 11:50');

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertSee('Идёт сейчас')
            ->assertSee('Вернуться в класс')
            ->assertSee(route('rooms.connect', $room), false);
    }

    public function test_start_is_blocked_without_subscription(): void
    {
        $teacher = $this->teacher(subscribed: false);
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $this->onceAt($room, '2026-09-24 16:00');

        Livewire::actingAs($teacher)
            ->test(Today::class)
            ->assertSee('Подписка закончилась')
            ->assertDontSee(route('rooms.start', $room), false)
            ->set('startBlockedOpen', true)
            ->assertSee('Нет активной подписки')
            ->assertSee('Выбрать тариф');
    }

    public function test_review_payments_and_messages_blocks(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher, 'Павел Ким');
        $room = $this->room($teacher, [$student]);

        $homework = Homework::create(['teacher_id' => $teacher->id, 'room_id' => $room->id, 'title' => 'Задачи на проценты', 'is_visible' => true]);
        HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'status' => HomeworkSubmission::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(3),
        ]);

        $session = $this->completedSession($room, '2026-09-18 16:00', 60, [$student->id]);
        PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => PaymentRecord::TYPE_PER_LESSON,
            'meeting_session_id' => $session->id,
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => '2026-09-21',
        ]);

        Message::create(['room_id' => $room->id, 'user_id' => $student->id, 'content' => 'Можно перенести субботу на 12:00?']);

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertSee('Нужно проверить · 1')
            ->assertSee('Задачи на проценты')
            ->assertSee('ждёт 3 дня')
            ->assertSee('Ждут оплаты')
            ->assertSee('1 занятие · 1 500 ₽')
            ->assertSee('Просрочено')
            ->assertSee('Сообщения')
            ->assertSee('Можно перенести субботу на 12:00?')
            ->assertSee(e(route('cabinet.teacher.messages', ['room' => $room->id])), false);
    }

    public function test_review_waiting_a_week_is_red(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher, 'Павел Ким');
        $room = $this->room($teacher, [$student]);
        $homework = Homework::create(['teacher_id' => $teacher->id, 'room_id' => $room->id, 'title' => 'Задачи на проценты', 'is_visible' => true]);
        HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'status' => HomeworkSubmission::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(HomeworkSubmission::REVIEW_OVERDUE_DAYS),
        ]);

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertSee('<span class="font-semibold text-danger-fg">ждёт 7 дней</span>', false);
    }

    public function test_mark_paid_and_undo(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $session = $this->completedSession($room, '2026-09-22 16:00', 60, [$student->id]);
        $record = PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => PaymentRecord::TYPE_PER_LESSON,
            'meeting_session_id' => $session->id,
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => '2026-09-25',
        ]);

        $component = Livewire::actingAs($teacher)
            ->test(Today::class)
            ->call('markPaid', $student->id)
            ->assertDispatched('toast')
            ->assertSee('Оплачено')
            ->assertSee('Отменить');

        $this->assertSame(PaymentRecord::STATUS_PAID, $record->fresh()->status);

        $component->call('undoPaid', $student->id);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $record->fresh()->status);
    }

    public function test_mark_paid_with_several_records_opens_choice(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $ids = collect(['2026-09-15', '2026-09-22'])->map(function ($day) use ($teacher, $student, $room) {
            $session = $this->completedSession($room, $day . ' 16:00', 60, [$student->id]);

            return PaymentRecord::create([
                'teacher_id' => $teacher->id,
                'student_id' => $student->id,
                'type' => PaymentRecord::TYPE_PER_LESSON,
                'meeting_session_id' => $session->id,
                'status' => PaymentRecord::STATUS_UNPAID,
                'due_date' => '2026-09-28',
            ])->id;
        });

        Livewire::actingAs($teacher)
            ->test(Today::class)
            ->call('markPaid', $student->id)
            ->assertSet('markStudentId', $student->id)
            ->assertSee('Итого')
            ->set('markSelected', [$ids[0]])
            ->call('confirmMarkPaid')
            ->assertSet('markStudentId', null);

        $this->assertSame(PaymentRecord::STATUS_PAID, PaymentRecord::find($ids[0])->status);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, PaymentRecord::find($ids[1])->status);
    }

    public function test_teacher_cannot_mark_foreign_payments(): void
    {
        $teacher = $this->teacher();
        $other = $this->teacher();
        $student = $this->studentOf($other);
        $record = PaymentRecord::create([
            'teacher_id' => $other->id,
            'student_id' => $student->id,
            'type' => PaymentRecord::TYPE_MONTHLY,
            'period' => '2026-09',
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => '2026-09-05',
        ]);

        Livewire::actingAs($teacher)->test(Today::class)->call('markPaid', $student->id, [$record->id]);

        $this->assertSame(PaymentRecord::STATUS_UNPAID, $record->fresh()->status);
    }

    public function test_plan_lesson_creates_room_with_schedule_and_notifies_student(): void
    {
        Notification::fake();
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);

        Livewire::actingAs($teacher)
            ->test(Today::class)
            ->call('openPlan')
            ->set('planStudentId', (string) $student->id)
            ->set('planName', 'Английский язык')
            ->set('planDate', '2026-09-28')
            ->set('planTime', '17:00')
            ->set('planDuration', 60)
            ->set('planRepeat', 'weekly')
            ->set('planDays', [1, 3])
            ->call('savePlan')
            ->assertHasNoErrors()
            ->assertSet('planOpen', false)
            ->assertDispatched('toast');

        $room = Room::where('user_id', $teacher->id)->first();
        $this->assertSame('Английский язык', $room->name);
        $this->assertSame('individual', $room->type);
        $this->assertTrue($room->participants->contains($student));
        $schedule = $room->schedules()->first();
        $this->assertSame('weekly', $schedule->recurrence_type);
        $this->assertSame([1, 3], $schedule->recurrence_days);
        $this->assertSame('2026-09-28 17:00', $room->fresh()->next_start->format('Y-m-d H:i'));
        Notification::assertSentTo($student, TeacherAssignedLesson::class);
    }

    public function test_plan_requires_student_and_name(): void
    {
        $teacher = $this->teacher();
        $foreign = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($teacher)
            ->test(Today::class)
            ->call('openPlan')
            ->set('planStudentId', (string) $foreign->id)
            ->call('savePlan')
            ->assertHasErrors(['planStudentId', 'planName']);

        $this->assertSame(0, Room::count());
    }
}
