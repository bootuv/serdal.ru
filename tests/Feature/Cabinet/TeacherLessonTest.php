<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Lesson;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Notifications\SessionDeletionRequested;
use App\Notifications\TeacherUpdatedSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Экран занятия учителя (/cabinet/teacher/lessons/{room}): предстоящее, идущее, отчёт и окна. */
class TeacherLessonTest extends TestCase
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

    public function test_access_rules(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, []);

        $this->get(route('cabinet.teacher.lesson', $room))->assertRedirect();
        $this->actingAs($this->user(User::ROLE_STUDENT))->get(route('cabinet.teacher.lesson', $room))->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->teacher())->get(route('cabinet.teacher.lesson', $room))->assertNotFound();
        $this->actingAs($teacher)->get(route('cabinet.teacher.lesson', 999999))->assertNotFound();
    }

    public function test_upcoming_lesson_overview(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $this->weeklyAt($room, [4], '16:00');

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.lesson', $room))
            ->assertOk()
            ->assertSee('Английский язык')
            ->assertSee('Сегодня, 16:00–17:00')
            ->assertSee('начнётся через 4 часа')
            ->assertSee('каждый четверг')
            ->assertSee('Перенести')
            ->assertSee('Начать занятие')
            ->assertSee(route('rooms.start', $room), false)
            ->assertSee('Домашнее задание')
            ->assertSee('Алина Смирнова')
            ->assertSee('Обзор')
            ->assertSee('Записи и история');
    }

    public function test_reschedule_updates_rule_and_notifies_students(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $schedule = $this->weeklyAt($room, [4], '16:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openReschedule')
            ->assertSet('rsScheduleId', $schedule->id)
            ->assertSet('rsDays', [4])
            ->assertSet('rsTime', '16:00')
            ->call('toggleRsDay', 4)
            ->call('toggleRsDay', 5)
            ->set('rsTime', '17:30')
            ->call('saveReschedule')
            ->assertHasNoErrors()
            ->assertSet('rescheduleOpen', false)
            ->assertDispatched('toast');

        $schedule->refresh();
        $this->assertSame([5], $schedule->recurrence_days);
        $this->assertSame('17:30', substr($schedule->recurrence_time, 0, 5));
        Notification::assertSentTo($student, TeacherUpdatedSchedule::class);
    }

    public function test_cancel_single_schedule_archives_lesson(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $this->onceAt($room, '2026-09-26 11:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openCancel')
            ->assertSet('cancelScope', 'room')
            ->assertSee('Отменить занятие?')
            ->call('confirmCancel')
            ->assertRedirect(route('cabinet.teacher.schedule'));

        $this->assertSoftDeleted($room);
        $this->assertSame(0, RoomSchedule::where('room_id', $room->id)->count());
        Notification::assertSentTo($student, TeacherUpdatedSchedule::class);
    }

    public function test_cancel_one_of_several_schedules(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $keep = $this->weeklyAt($room, [2], '18:30');
        $drop = $this->onceAt($room, '2026-09-25 10:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openCancel')
            ->assertSet('cancelScope', 'schedule')
            ->assertSet('cancelScheduleId', $drop->id)
            ->call('confirmCancel')
            ->assertDispatched('toast');

        $this->assertNotSoftDeleted($room);
        $this->assertNull(RoomSchedule::find($drop->id));
        $this->assertNotNull(RoomSchedule::find($keep->id));
    }

    public function test_live_lesson_and_stop_confirmation(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student], ['is_running' => true]);
        $running = MeetingSession::create([
            'user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id,
            'started_at' => now()->subMinutes(12), 'status' => 'running',
            'analytics_data' => ['participants' => [['user_id' => (string) $student->id, 'full_name' => $student->name, 'joined_at' => now()->subMinutes(9)->toIso8601String()]]],
        ]);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Идёт 12 минут')
            ->assertSee('Вернуться в класс')
            ->assertSee(route('rooms.connect', $room), false)
            ->assertSee('Сейчас в классе')
            ->assertSee('Подключился в 11:51')
            ->assertSee('Позвать гостя')
            ->assertSee(route('rooms.join', $room), false)
            ->call('askStop')
            ->assertSet('session', $running->id)
            ->assertSee('Завершить занятие?')
            ->assertSee(route('rooms.stop', $room), false)
            ->call('keepRunning')
            ->assertSet('session', null);
    }

    public function test_report_of_completed_lesson_with_payment(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $this->weeklyAt($room, [4], '16:00');
        $session = $this->completedSession($room, '2026-09-17 16:02', 60, [$student->id]);
        $record = PaymentRecord::create([
            'teacher_id' => $teacher->id, 'student_id' => $student->id, 'type' => PaymentRecord::TYPE_PER_LESSON,
            'meeting_session_id' => $session->id, 'status' => PaymentRecord::STATUS_UNPAID, 'due_date' => '2026-09-20',
        ]);

        Livewire::withQueryParams(['session' => $session->id])
            ->actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Итог занятия')
            ->assertSee('60 мин')
            ->assertSee('1 из 1')
            ->assertSee('Был на занятии')
            ->assertSee('1 500 ₽')
            ->assertSee('Просрочено')
            ->assertSee('срок был до 20 сентября')
            ->assertSee('Следующее занятие')
            ->call('markPaid', $student->id, [$record->id])
            ->assertSee('Отменить');

        $this->assertSame(PaymentRecord::STATUS_PAID, $record->fresh()->status);
    }

    public function test_request_and_revoke_deletion(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $session = $this->completedSession($room, '2026-09-19 12:00', 9, [$student->id]);

        $component = Livewire::withQueryParams(['session' => $session->id])
            ->actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Запросить удаление')
            ->call('openDeleteRequest')
            ->call('sendDeleteRequest')
            ->assertHasErrors(['deletionReason'])
            ->set('deletionReason', 'Связь прервалась, это дубль')
            ->call('sendDeleteRequest')
            ->assertHasNoErrors()
            ->assertSee('Вы попросили удалить это занятие')
            ->assertSee('Отозвать запрос');

        $this->assertNotNull($session->fresh()->deletion_requested_at);
        Notification::assertSentTo($admin, SessionDeletionRequested::class);

        $component->call('revokeDeleteRequest')->assertSee('Запросить удаление');
        $this->assertNull($session->fresh()->deletion_requested_at);
    }

    public function test_session_of_another_room_is_ignored(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, []);
        $other = $this->room($teacher, [], ['name' => 'Другое']);
        $session = $this->completedSession($other, '2026-09-19 12:00', 30, []);

        Livewire::withQueryParams(['session' => $session->id])
            ->actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSet('session', null)
            ->assertDontSee('Итог занятия');
    }

    public function test_history_tab_lists_past_lessons(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $session = $this->completedSession($room, '2026-09-17 16:00', 58, [$student->id]);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->set('tab', 'history')
            ->assertSee('Прошедшие занятия')
            ->assertSee('Чт, 17 сентября в 16:00')
            ->assertSee('58 минут')
            ->assertSee(route('cabinet.teacher.lesson', ['room' => $room->id, 'session' => $session->id]), false);
    }

    public function test_foreign_teacher_cannot_act_on_lesson(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, []);
        $this->onceAt($room, '2026-09-26 11:00');

        Livewire::actingAs($this->teacher())
            ->test(Lesson::class, ['room' => $room->id])
            ->assertNotFound();

        $this->assertNotSoftDeleted($room);
    }
}
