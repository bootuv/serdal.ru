<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Lesson;
use App\Models\Homework;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\LessonPlan;
use App\Models\RoomSchedule;
use App\Models\RoomScheduleException;
use App\Models\User;
use App\Notifications\NewHomework;
use App\Notifications\SessionDeletionRequested;
use App\Notifications\TeacherAssignedLesson;
use App\Notifications\TeacherUpdatedSchedule;
use App\Services\TeacherScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee(route('cabinet.teacher.student', $student), false) // карточка ученика открывается по username
            ->assertSee('Обзор')
            ->assertSee('Записи и история');
    }

    public function test_reschedule_following_splits_series_and_notifies_students(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $schedule = $this->weeklyAt($room, [4], '16:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openReschedule')
            ->assertSet('rsScheduleId', $schedule->id)
            ->assertSet('rsAt', '2026-09-24T16:00')
            ->assertSet('rsScope', 'one')
            ->assertSee('Только это занятие')
            ->assertSee('Это и все следующие')
            ->assertDontSee('Не повторять')
            ->set('rsScope', 'following')
            ->call('toggleRsDay', 4)
            ->call('toggleRsDay', 5)
            ->set('rsDate', '2026-09-25')
            ->set('rsTime', '17:30')
            ->call('saveReschedule')
            ->assertHasNoErrors()
            ->assertSet('rescheduleOpen', false)
            ->assertSet('at', '')
            ->assertDispatched('toast');

        // Прошедшие четверги остаются за старым правилом, с пятницы — новое
        $schedule->refresh();
        $this->assertSame([4], $schedule->recurrence_days);
        $this->assertSame('2026-09-23', $schedule->end_date->format('Y-m-d'));
        $new = $room->schedules()->where('id', '!=', $schedule->id)->first();
        $this->assertSame([5], $new->recurrence_days);
        $this->assertSame('17:30', substr($new->recurrence_time, 0, 5));
        $this->assertSame('2026-09-25', $new->start_date->format('Y-m-d'));
        $this->assertSame('2026-09-25 17:30', $room->fresh()->next_start->format('Y-m-d H:i'));
        Notification::assertSentTo($student, TeacherUpdatedSchedule::class, fn ($n) => $n->title === 'Расписание изменено');
    }

    public function test_reschedule_following_changes_rule_in_place_when_nothing_was_before(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $schedule = $this->weeklyAt($room, [4], '16:00');
        $schedule->update(['start_date' => '2026-09-24']);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openReschedule')
            ->set('rsScope', 'following')
            ->set('rsTime', '18:00')
            ->set('rsNotify', false)
            ->call('saveReschedule')
            ->assertHasNoErrors();

        $this->assertSame(1, $room->schedules()->count());
        $this->assertSame('18:00', substr($schedule->fresh()->recurrence_time, 0, 5));
        Notification::assertNothingSent();
    }

    public function test_move_one_occurrence_keeps_series(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $schedule = $this->weeklyAt($room, [4], '16:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openReschedule')
            ->set('rsDate', '2026-09-25')
            ->set('rsTime', '15:00')
            ->call('saveReschedule')
            ->assertHasNoErrors()
            ->assertSet('at', '2026-09-24T16:00')
            ->assertSee('Завтра, 15:00–16:00')
            ->assertSee('перенесено с четверга, 16:00')
            ->assertSee('остальные — каждый четверг');

        $schedule->refresh();
        $this->assertSame([4], $schedule->recurrence_days);
        $this->assertNull($schedule->end_date);
        $exception = RoomScheduleException::sole();
        $this->assertSame(RoomScheduleException::STATUS_MOVED, $exception->status);
        $this->assertSame('2026-09-25 15:00', $exception->starts_at->format('Y-m-d H:i'));

        $events = app(TeacherScheduleService::class)->events($teacher->id, Carbon::parse('2026-09-21'), Carbon::parse('2026-10-04 23:59'));
        $this->assertSame(['2026-09-25 15:00', '2026-10-01 16:00'], $events->map(fn ($e) => $e['start']->format('Y-m-d H:i'))->all());
        $this->assertSame('2026-09-25 15:00', $room->fresh()->next_start->format('Y-m-d H:i'));
        Notification::assertSentTo($student, TeacherUpdatedSchedule::class, fn ($n) => $n->title === 'Занятие перенесено');
    }

    public function test_move_into_the_past_is_rejected(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $this->weeklyAt($room, [4], '16:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openReschedule')
            ->set('rsDate', '2026-09-23')
            ->call('saveReschedule')
            ->assertHasErrors('rsDate')
            ->assertSet('rescheduleOpen', true);

        $this->assertSame(0, RoomScheduleException::count());
    }

    public function test_cancel_one_occurrence_keeps_series_and_shows_reason(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $schedule = $this->weeklyAt($room, [4], '16:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openCancel')
            ->assertSet('cancelScope', 'one')
            ->assertSee('Только это занятие')
            ->assertSee('Всю серию')
            ->assertSee('Сообщить об отмене')
            ->assertDontSee('Убрать занятие в архив')
            ->set('cancelReason', 'Алина заболела')
            ->call('confirmCancel')
            ->assertHasNoErrors()
            ->assertSet('at', '2026-09-24T16:00')
            ->assertDispatched('toast')
            ->assertSee('отменено · Алина заболела')
            ->assertSee('следующее — чт, 1 октября')
            ->assertSee('Запланировать другое')
            ->assertSee('Занятие отменено — платить не нужно');

        $this->assertNotSoftDeleted($room);
        $schedule->refresh();
        $this->assertNull($schedule->end_date);
        $this->assertTrue($schedule->is_active);
        $exception = RoomScheduleException::sole();
        $this->assertTrue($exception->isCancelled());
        $this->assertSame('Алина заболела', $exception->reason);
        $this->assertSame('2026-10-01 16:00', $room->fresh()->next_start->format('Y-m-d H:i'));
        Notification::assertSentTo($student, TeacherUpdatedSchedule::class,
            fn ($n) => $n->title === 'Занятие отменено' && str_contains($n->body, 'Причина: Алина заболела'));
    }

    public function test_cancel_without_notify_sends_nothing(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $this->weeklyAt($room, [4], '16:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openCancel')
            ->set('cancelNotify', false)
            ->call('confirmCancel')
            ->assertHasNoErrors();

        $this->assertSame(1, RoomScheduleException::count());
        Notification::assertNothingSent();
    }

    public function test_cancel_series_ends_rule_without_archiving(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $schedule = $this->weeklyAt($room, [4], '16:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openCancel')
            ->set('cancelScope', 'series')
            ->assertSee('Отменить серию')
            ->assertSee('Убрать занятие в архив')
            ->set('cancelReason', 'Курс окончен')
            ->call('confirmCancel')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSee('серия отменена · Курс окончен')
            ->assertSee('больше не повторяется');

        $this->assertNotSoftDeleted($room);
        $this->assertSame('2026-09-24', $schedule->fresh()->end_date->format('Y-m-d'));
        $this->assertNull($room->fresh()->next_start);
        Notification::assertSentTo($student, TeacherUpdatedSchedule::class, fn ($n) => $n->title === 'Занятия отменены');
    }

    public function test_cancel_last_lesson_archives_only_when_chosen(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $this->onceAt($room, '2026-09-26 11:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openCancel')
            ->assertDontSee('Всю серию')
            ->assertSee('Убрать занятие в архив')
            ->set('cancelArchive', true)
            ->call('confirmCancel')
            ->assertRedirect(route('cabinet.teacher.schedule'));

        $this->assertSoftDeleted($room);

        // Без галочки занятие остаётся, отменено только время
        $other = $this->room($teacher, [$student], ['name' => 'Математика']);
        $this->onceAt($other, '2026-09-26 11:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $other->id])
            ->call('openCancel')
            ->call('confirmCancel')
            ->assertNoRedirect()
            ->assertSee('отменено');

        $this->assertNotSoftDeleted($other);
        $this->assertSame(1, RoomSchedule::where('room_id', $other->id)->count());
    }

    public function test_cancel_one_of_several_schedules_keeps_others(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $keep = $this->weeklyAt($room, [2], '18:30');
        $drop = $this->onceAt($room, '2026-09-25 10:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openCancel')
            ->assertSet('cancelScheduleId', $drop->id)
            ->assertDontSee('Убрать занятие в архив')
            ->call('confirmCancel')
            ->assertDispatched('toast');

        $this->assertNotSoftDeleted($room);
        $this->assertNotNull(RoomSchedule::find($drop->id));
        $this->assertNotNull(RoomSchedule::find($keep->id));
        $this->assertSame('2026-09-29 18:30', $room->fresh()->next_start->format('Y-m-d H:i'));
    }

    public function test_lesson_plan_is_kept_per_occurrence_and_shown_live(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $this->weeklyAt($room, [4], '12:00');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSee('План занятия')
            ->assertSee('Пока пусто — запишите, что разобрать на занятии.')
            ->call('openLessonPlan')
            ->set('lessonPlanBody', "- Разобрать ошибки в тесте\n\n2. Диалог «В аэропорту»")
            ->call('saveLessonPlan')
            ->assertHasNoErrors()
            ->assertSee('Разобрать ошибки в тесте')
            ->assertSee('Диалог «В аэропорту»');

        $this->assertSame('2026-09-24 12:00', LessonPlan::sole()->starts_at->format('Y-m-d H:i'));

        // Во время занятия план виден рядом с «Сейчас в классе»
        $room->update(['is_running' => true]);
        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Сейчас в классе')
            ->assertSee('Разобрать ошибки в тесте');

        // У следующего занятия серии свой план
        $this->assertNull(app(\App\Services\TeacherLessonService::class)->lessonPlan($room, Carbon::parse('2026-10-01 12:00')));
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

    public function test_history_tab_shows_cancelled_lessons_with_reason(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student]);
        $schedule = $this->weeklyAt($room, [4], '16:00');
        $this->completedSession($room, '2026-09-17 16:00', 58, [$student->id]);
        app(\App\Services\TeacherLessonService::class)->cancelOccurrence($schedule, Carbon::parse('2026-09-10 16:00'), 'ученица заболела', false, $teacher);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->set('tab', 'history')
            ->assertSee('Чт, 17 сентября в 16:00')
            ->assertSee('Чт, 10 сентября в 16:00')
            ->assertSee('Отменено: ученица заболела');
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

    /*
     |--------------------------------------------------------------------------
     | Ученики и название
     |--------------------------------------------------------------------------
     */

    public function test_edit_renames_lesson_and_new_student_gets_lesson_homework_and_notification(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $ivan = $this->studentOf($teacher, 'Иван Петров');
        $room = $this->room($teacher, [$alina]);
        $homework = Homework::create([
            'teacher_id' => $teacher->id, 'room_id' => $room->id, 'title' => 'Эссе «My last holiday»',
            'is_visible' => true, 'deadline' => now()->addWeek(),
        ]);
        $homework->students()->attach($alina->id);

        $this->actingAs($teacher)->get(route('cabinet.teacher.lesson', $room))
            ->assertOk()
            ->assertSee('Ученики и название')
            ->assertDontSee('/tutor/rooms/' . $room->id . '/edit', false);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openEdit')
            ->assertSet('editName', 'Английский язык')
            ->assertSet('editKind', 'individual')
            ->assertSet('editStudents', [$alina->id])
            ->assertSee('Иван Петров')
            ->set('editKind', 'group')
            ->call('pickEditStudent', $ivan->id)
            ->assertSee('Новый ученик получит уведомление и задания занятия')
            ->set('editName', 'Английский · разговорный клуб')
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSet('editOpen', false)
            ->assertDispatched('toast', message: 'Иван Петров в занятии, получит уведомление')
            ->assertSee('Английский · разговорный клуб');

        $room->refresh();
        $this->assertSame('Английский · разговорный клуб', $room->name);
        $this->assertSame('group', $room->type);
        $this->assertEqualsCanonicalizing([$alina->id, $ivan->id], $room->participants()->pluck('users.id')->all());
        $this->assertTrue($homework->students()->where('users.id', $ivan->id)->exists());

        Notification::assertSentTo($ivan, TeacherAssignedLesson::class);
        Notification::assertSentTo($ivan, NewHomework::class);
        Notification::assertNotSentTo($alina, TeacherAssignedLesson::class);
    }

    public function test_edit_removes_student_and_individual_lesson_keeps_one(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $ivan = $this->studentOf($teacher, 'Иван Петров');
        $room = $this->room($teacher, [$alina, $ivan]);
        $room->participants()->updateExistingPivot($alina->id, ['custom_price' => 1200]);

        $component = Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openEdit')
            ->assertSet('editKind', 'group')
            ->call('pickEditStudent', $ivan->id)
            ->assertSet('editStudents', [$alina->id])
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Иван Петров больше не в этом занятии');

        $room->refresh();
        $this->assertSame([$alina->id], $room->participants()->pluck('users.id')->all());
        $this->assertSame('individual', $room->type);
        $this->assertSame(1200, (int) $room->participants()->first()->pivot->custom_price); // цена оставшегося ученика сохранилась
        Notification::assertNothingSentTo($ivan);

        // Индивидуальное: выбор другого ученика заменяет текущего, без ученика не сохранить
        $component->call('openEdit')
            ->assertSet('editKind', 'individual')
            ->call('pickEditStudent', $ivan->id)
            ->assertSet('editStudents', [$ivan->id])
            ->call('pickEditStudent', $ivan->id)
            ->assertSet('editStudents', [])
            ->call('saveEdit')
            ->assertHasErrors(['editStudents'])
            ->set('editName', '')
            ->call('pickEditStudent', $ivan->id)
            ->call('saveEdit')
            ->assertHasErrors(['editName']);

        $this->assertSame([$alina->id], $room->participants()->pluck('users.id')->all());
    }

    public function test_edit_does_not_add_foreign_student(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $stranger = $this->studentOf($this->teacher(), 'Чужой Ученик');
        $room = $this->room($teacher, [$alina]);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openEdit')
            ->assertDontSee('Чужой Ученик')
            ->set('editKind', 'group')
            ->set('editStudents', [$alina->id, $stranger->id])
            ->call('saveEdit')
            ->assertHasErrors(['editStudents.1']);

        // Сервис тоже отбрасывает чужих
        app(\App\Services\TeacherLessonService::class)->updateLesson($room, $teacher, 'Английский язык', [$alina->id, $stranger->id]);

        $this->assertSame([$alina->id], $room->participants()->pluck('users.id')->all());
        Notification::assertNothingSentTo($stranger);
    }

    public function test_edit_of_foreign_or_archived_lesson_is_refused(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $room = $this->room($teacher, [$alina]);

        Livewire::actingAs($this->teacher())
            ->test(Lesson::class, ['room' => $room->id])
            ->assertNotFound();

        $room->delete();

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertDontSee('Ученики и название')
            ->call('openEdit')
            ->assertForbidden();
    }

    public function test_old_cabinet_edit_uses_shared_participants_logic(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $room = $this->room($teacher, []);
        $homework = Homework::create(['teacher_id' => $teacher->id, 'room_id' => $room->id, 'title' => 'Тест', 'is_visible' => true]);

        $room->participants()->attach($alina->id);
        app(\App\Services\TeacherLessonService::class)->participantsChanged($room, [(string) $alina->id], $teacher);

        $this->assertSame('individual', $room->fresh()->type);
        $this->assertTrue($homework->students()->where('users.id', $alina->id)->exists());
        Notification::assertSentTo($alina, TeacherAssignedLesson::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Презентации к занятию
     |--------------------------------------------------------------------------
     */

    public function test_presentation_upload_and_delete(): void
    {
        Storage::fake('s3');
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);

        $component = Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->set('tab', 'materials')
            ->assertSee('Пока пусто — добавьте презентацию')
            ->call('openPresentations')
            ->assertSee('Презентации к занятию')
            ->set('presentationUploads', [UploadedFile::fake()->create('Present Perfect.pdf', 300, 'application/pdf')])
            ->set('presentationUploadNames', ['Present Perfect.pdf'])
            ->assertHasNoErrors()
            ->call('savePresentations')
            ->assertHasNoErrors()
            ->assertSet('presentationsOpen', false)
            ->assertDispatched('toast', message: 'Презентация добавлена, откроется в классе при старте')
            ->assertSee('Present Perfect.pdf')
            ->assertSee('Откроется в классе при старте');

        $room->refresh();
        $this->assertCount(1, $room->presentations);
        $path = $room->presentations[0];
        $this->assertStringStartsWith('presentations/' . $teacher->id . '/', $path);
        $this->assertSame('Present Perfect.pdf', $room->presentation_names[$path]);
        Storage::disk('s3')->assertExists($path);

        $component->call('askDeletePresentation', md5($path))
            ->assertSee('Удалить презентацию?')
            ->call('deletePresentation')
            ->assertDispatched('toast', message: 'Презентация удалена')
            ->assertDontSee('Present Perfect.pdf');

        $this->assertSame([], $room->fresh()->presentations);
        Storage::disk('s3')->assertMissing($path);
    }

    public function test_presentation_format_and_size_are_checked(): void
    {
        Storage::fake('s3');
        $teacher = $this->teacher();
        $room = $this->room($teacher, []);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openPresentations')
            ->set('presentationUploads', [UploadedFile::fake()->create('setup.exe', 100, 'application/x-msdownload')])
            ->assertHasErrors(['presentationUploads'])
            ->assertSee('Подойдут PDF, PowerPoint, Word, Excel или фото JPG и PNG.')
            ->assertSet('presentationUploads', [])
            // Больше 200 МБ не пропускает уже загрузка во временное хранилище
            ->set('presentationUploads', [UploadedFile::fake()->create('big.pdf', \App\Services\TeacherLessonService::PRESENTATION_MAX_KB + 1, 'application/pdf')])
            ->assertHasErrors(['presentationUploads.0'])
            ->assertSet('presentationUploads', [])
            ->call('savePresentations')
            ->assertHasErrors(['presentationUploads']);

        $this->assertEmpty($room->fresh()->presentations);
    }

    public function test_presentation_of_foreign_lesson_cannot_be_deleted(): void
    {
        Storage::fake('s3');
        $teacher = $this->teacher();
        $room = $this->room($teacher, [], ['presentations' => ['presentations/1/a.pdf']]);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->set('tab', 'materials')
            ->assertSee('a.pdf') // загруженные в старом кабинете — по имени файла
            ->call('askDeletePresentation', md5('presentations/1/other.pdf'))
            ->assertNotFound();

        Livewire::actingAs($this->teacher())
            ->test(Lesson::class, ['room' => $room->id])
            ->assertNotFound();

        $this->assertSame(['presentations/1/a.pdf'], $room->fresh()->presentations);
    }

    /*
     |--------------------------------------------------------------------------
     | Отчёт о проведённом занятии: активность по событиям класса
     |--------------------------------------------------------------------------
     */

    public function test_report_shows_activity_from_class_events(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $ivan = $this->studentOf($teacher, 'Иван Петров');
        $oleg = $this->studentOf($teacher, 'Олег Васильев');
        $room = $this->room($teacher, [$alina, $ivan, $oleg]);
        $start = Carbon::parse('2026-09-17 16:02');

        $session = $this->completedSession($room, '2026-09-17 16:02', 60, [$alina->id, $ivan->id], [
            'analytics_data' => [
                'poll_count' => 2,
                'participants' => [
                    ['user_id' => (string) $teacher->id, 'full_name' => $teacher->name, 'joined_at' => $start->toIso8601String()],
                    [
                        'user_id' => (string) $alina->id, 'full_name' => $alina->name,
                        'joined_at' => $start->copy()->addMinutes(2)->toIso8601String(),
                        'left_at' => $start->copy()->addMinutes(60)->toIso8601String(),
                        'talking_time' => 21 * 60, 'webcam_time' => 50 * 60, 'message_count' => 3, 'emoji_count' => 1,
                    ],
                    [
                        // Вышел и вернулся, микрофон так и не выключил — считаем до конца занятия
                        'user_id' => (string) $ivan->id, 'full_name' => $ivan->name,
                        'joined_at' => $start->copy()->addMinutes(10)->toIso8601String(),
                        'left_at' => $start->copy()->addMinutes(20)->toIso8601String(),
                        'last_joined_at' => $start->copy()->addMinutes(25)->toIso8601String(),
                        'audio_started_at' => $start->copy()->addMinutes(58)->toIso8601String(),
                    ],
                ],
            ],
        ]);

        Livewire::withQueryParams(['session' => $session->id])
            ->actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Итог занятия')
            ->assertSee('2 из 3')
            ->assertSeeInOrder(['2', 'голосования'])
            ->assertSee('В классе 58 минут из 60 · с микрофоном 21 минуту · камера была включена')
            ->assertSee('10 из 10') // 21 мин с микрофоном × 2 + сообщения — больше 10
            ->assertSee('В классе 50 минут из 60 · с микрофоном 2 минуты · без камеры')
            ->assertSee('4 из 10')
            ->assertSee('Не был на занятии')
            ->assertSee('активность')
            ->assertSee('Вели занятие 60 минут');
    }

    public function test_report_without_class_events_shows_attendance_only(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $room = $this->room($teacher, [$alina]);
        $session = $this->completedSession($room, '2026-09-17 16:02', 60, [$alina->id]);

        Livewire::withQueryParams(['session' => $session->id])
            ->actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Был на занятии')
            ->assertDontSee('голосован')
            ->assertDontSee('активность');
    }

    public function test_links_lead_to_new_cabinet(): void
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $ivan = $this->studentOf($teacher, 'Иван Петров');
        $room = $this->room($teacher, [$alina, $ivan]);
        $homework = Homework::create(['teacher_id' => $teacher->id, 'room_id' => $room->id, 'title' => 'Эссе', 'is_visible' => true]);
        $homework->students()->attach([$alina->id, $ivan->id]);

        $this->actingAs($teacher)->get(route('cabinet.teacher.lesson', $room))
            ->assertOk()
            ->assertSee(route('cabinet.teacher.task', $homework), false)
            ->assertSee(\App\Services\TeacherStudentsService::studentUrl($ivan), false)
            ->assertDontSee('/tutor/homework/', false)
            ->assertDontSee('/tutor/students/', false);
    }

    public function test_reschedule_following_rejects_time_of_another_rule_of_same_lesson(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $this->weeklyAt($room, [4], '16:00');
        $this->weeklyAt($room, [5], '17:30');

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openReschedule')
            ->set('rsScope', 'following')
            ->call('toggleRsDay', 4)
            ->call('toggleRsDay', 5)
            ->set('rsDate', '2026-09-25')
            ->set('rsTime', '17:30')
            ->call('saveReschedule')
            ->assertHasErrors('rsTime')
            ->assertSet('rescheduleOpen', true);

        $this->assertSame(2, $room->schedules()->count());
    }

    public function test_second_save_of_new_time_does_not_add_second_rule(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);

        Livewire::actingAs($teacher)
            ->test(Lesson::class, ['room' => $room->id])
            ->call('openReschedule')
            ->set('rsDate', '2026-09-28')
            ->set('rsTime', '16:00')
            ->set('rsDays', [1, 3])
            ->call('saveReschedule')
            ->assertHasNoErrors()
            ->call('saveReschedule');

        $this->assertSame(1, $room->schedules()->count());
    }

    public function test_dedupe_command_removes_repeating_rules_only_with_apply(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $first = $this->weeklyAt($room, [1, 3], '16:00');
        $same = $this->weeklyAt($room, [1, 3], '16:00');
        $partial = $this->weeklyAt($room, [3, 6], '16:00');
        $other = $this->weeklyAt($room, [1], '18:00');

        $this->artisan('schedules:dedupe')->assertSuccessful();
        $this->assertSame(4, $room->schedules()->count());
        $this->assertSame([3, 6], $partial->fresh()->recurrence_days);

        $this->artisan('schedules:dedupe', ['--apply' => true])->assertSuccessful();

        $this->assertNull(RoomSchedule::find($same->id));
        $this->assertSame([1, 3], $first->fresh()->recurrence_days);
        $this->assertSame([6], $partial->fresh()->recurrence_days);
        $this->assertSame([1], $other->fresh()->recurrence_days);
        Notification::assertNothingSent();
    }
}
