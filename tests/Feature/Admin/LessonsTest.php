<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\Lessons;
use App\Models\MeetingSession;
use App\Models\Recording;
use App\Models\Room;
use App\Models\SessionDeletionDecision;
use App\Models\User;
use App\Notifications\LessonCreatedByAdmin;
use App\Notifications\SessionDeletionApproved;
use App\Notifications\SessionDeletionRejected;
use App\Notifications\TeacherAssignedLesson;
use App\Services\TeacherLessonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Livewire\Livewire;
use Tests\Feature\Cabinet\TeacherLessonFixtures;
use Tests\TestCase;

/** Админка · Занятия (/cabinet/admin/lessons): расписание, проведённые, запросы на удаление, записи, «Создать занятие». */
class LessonsTest extends TestCase
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

    private function admin(): User
    {
        return $this->user(User::ROLE_ADMIN, ['name' => 'Анна Куликова']);
    }

    public function test_access_by_role(): void
    {
        $this->get(route('cabinet.admin.lessons'))->assertRedirect(route('login'));

        $this->actingAs($this->user(User::ROLE_TUTOR))->get(route('cabinet.admin.lessons'))->assertRedirect();
        $this->actingAs($this->user(User::ROLE_STUDENT))->get(route('cabinet.admin.lessons'))->assertRedirect(route('cabinet.student.home'));

        $admin = $this->admin();
        foreach (['schedule', 'sessions', 'deletions', 'recordings'] as $tab) {
            $this->actingAs($admin)->get(route('cabinet.admin.lessons', ['tab' => $tab]))->assertOk()->assertSee('Занятия');
        }
        foreach (['week', 'month'] as $view) {
            $this->actingAs($admin)->get(route('cabinet.admin.lessons', ['view' => $view]))->assertOk();
        }
    }

    public function test_list_shows_states_filters_and_search(): void
    {
        $maria = $this->teacher();
        $maria->update(['name' => 'Мария Соколова']);
        $ivan = $this->teacher();
        $ivan->update(['name' => 'Иван Орлов']);

        $live = $this->room($ivan, [$this->studentOf($ivan, 'Дмитрий Волков')], ['name' => 'Физика · ОГЭ']);
        $live->updateQuietly(['is_running' => true]);
        MeetingSession::create(['user_id' => $ivan->id, 'room_id' => $live->id, 'meeting_id' => $live->meeting_id, 'started_at' => now()->subMinutes(10), 'status' => 'running']);

        $planned = $this->room($maria, [$this->studentOf($maria)], ['name' => 'Английский язык']);
        $this->weeklyAt($planned, [4], '16:00');

        $empty = $this->room($maria, [], ['name' => 'Химия без времени']);
        $archived = $this->room($maria, [], ['name' => 'Летний интенсив']);
        $archived->delete();

        Livewire::actingAs($this->admin())->test(Lessons::class)
            ->assertSeeInOrder(['Физика · ОГЭ', 'Идёт сейчас', 'Подключиться', 'Английский язык', 'Сегодня в 16:00', 'Химия без времени', 'Нет расписания', 'Летний интенсив', 'В архиве'])
            ->assertSee('Идут сейчас · 1')
            ->assertSee('Без расписания · 1')
            ->set('filter', 'arch')
            ->assertSee('Летний интенсив')
            ->assertDontSee('Английский язык')
            ->set('filter', 'all')
            ->set('teacher', (string) $ivan->id)
            ->assertSee('Физика · ОГЭ')
            ->assertDontSee('Английский язык')
            ->set('teacher', '')
            ->set('q', 'Алина')
            ->assertSee('Английский язык')
            ->assertDontSee('Физика · ОГЭ');
    }

    public function test_week_and_month_are_colored_by_teacher_and_show_cancelled(): void
    {
        $teacher = $this->teacher();
        $teacher->update(['name' => 'Мария Соколова']);
        $room = $this->room($teacher, [$this->studentOf($teacher)], ['name' => 'Английский язык']);
        $schedule = $this->weeklyAt($room, [2, 4], '16:00');
        app(TeacherLessonService::class)->cancelOccurrence($schedule, Carbon::parse('2026-09-22 16:00'), 'заболела', false, $teacher);

        $component = Livewire::actingAs($this->admin())->test(Lessons::class)->set('view', 'week');
        $component->assertSee('16:00 · Алина Смирнова')
            ->assertSee('Мария Соколова')
            ->assertSee('отменено')
            ->assertSee('bg-av-' . ($teacher->id % 3 + 1) . '/40', false);

        $component->set('view', 'month')->assertSee('16:00 Мария · отменено')->assertSee('Отменено');
    }

    public function test_sessions_tab_lists_held_and_running_lessons(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student], ['name' => 'Математика']);
        $this->completedSession($room, '2026-09-22 10:00', 9, [$student->id], ['deletion_requested_at' => now(), 'deletion_reason' => 'Дубль']);
        $this->completedSession($this->room($teacher, [$student], ['name' => 'Давнее']), '2026-06-01 10:00', 60, [$student->id]);

        Livewire::actingAs($this->admin())->test(Lessons::class)->set('tab', 'sessions')
            ->assertSee('Математика')
            ->assertSee('Запрос на удаление')
            ->assertSee('9 мин')
            ->assertSee('1 из 1')
            ->assertDontSee('Давнее')
            ->set('period', 'all')
            ->assertSee('Давнее');
    }

    public function test_approve_deletion_request_deletes_session_and_notifies_teacher(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student], ['name' => 'Математика']);
        $session = $this->completedSession($room, '2026-09-22 10:00', 9, [$student->id], ['deletion_requested_at' => now(), 'deletion_reason' => 'Связь прервалась — это дубль']);

        Livewire::actingAs($this->admin())->test(Lessons::class)->set('tab', 'deletions')
            ->assertSee('Связь прервалась — это дубль')
            ->assertSee('9 минут')
            ->call('askDeleteSession', $session->id)
            ->assertSee('Удалить занятие?')
            ->call('confirmDeleteSession')
            ->assertSee('Новых запросов нет')
            ->assertSee('Решено раньше')
            ->assertSee('Удалено сегодня');

        $this->assertModelMissing($session);
        $this->assertSame(SessionDeletionDecision::DELETED, SessionDeletionDecision::first()->decision);
        Notification::assertSentTo($teacher, SessionDeletionApproved::class);
    }

    public function test_reject_deletion_request_with_reply(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $room = $this->room($teacher, [$student], ['name' => 'Математика']);
        $session = $this->completedSession($room, '2026-09-22 10:00', 50, [$student->id], ['deletion_requested_at' => now(), 'deletion_reason' => 'Ошибка']);

        Livewire::actingAs($this->admin())->test(Lessons::class)->set('tab', 'deletions')
            ->call('askRejectSession', $session->id)
            ->assertSee('Отклонить запрос?')
            ->set('rejectReply', 'Занятие шло 50 минут')
            ->call('confirmRejectSession')
            ->assertSee('Ответ учителю: «Занятие шло 50 минут»')
            ->assertSee('Отклонено сегодня');

        $this->assertNull($session->fresh()->deletion_requested_at);
        Notification::assertSentTo($teacher, SessionDeletionRejected::class, fn ($n) => $n->reply === 'Занятие шло 50 минут'
            && str_contains($n->toDatabase($teacher)['body'], 'Занятие шло 50 минут'));
    }

    public function test_recordings_play_delete_and_sync(): void
    {
        $teacher = $this->teacher();
        $teacher->update(['name' => 'Мария Соколова']);
        $room = $this->room($teacher, [$this->studentOf($teacher)], ['name' => 'Английский язык']);
        $ready = Recording::create(['meeting_id' => $room->meeting_id, 'record_id' => 'rec-1', 'name' => 'x', 'participants' => 2,
            'start_time' => now()->subDay(), 'end_time' => now()->subDay()->addMinutes(55), 's3_url' => 'https://s3.example/rec-1.mp4']);
        Recording::create(['meeting_id' => $room->meeting_id, 'record_id' => 'rec-2', 'name' => 'x', 'start_time' => now()->subHour()]);

        Bigbluebutton::shouldReceive('deleteRecordings')->once()->andReturn(collect(['returncode' => 'SUCCESS']));
        Bigbluebutton::shouldReceive('getRecordings')->once()->andReturn(collect());

        Livewire::actingAs($this->admin())->test(Lessons::class)->set('tab', 'recordings')
            ->assertSee('Английский язык')
            ->assertSee('Обрабатывается')
            ->assertSee('55 мин')
            ->assertSee('2 человека')
            ->call('play', $ready->id)
            ->assertSee('https://s3.example/rec-1.mp4')
            ->call('askDeleteRecording', $ready->id)
            ->assertSee('Удалить запись?')
            ->call('deleteRecording')
            ->assertDispatched('toast')
            ->call('sync')
            ->assertDispatched('toast');

        $this->assertSoftDeleted($ready);
    }

    public function test_admin_creates_lesson_for_teacher(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $stranger = $this->user(User::ROLE_STUDENT);

        $component = Livewire::actingAs($this->admin())->test(Lessons::class)
            ->call('openCreate')
            ->assertSee('Создать занятие')
            ->set('cTeacher', (string) $teacher->id)
            ->set('cName', 'Английский язык')
            ->set('cAdd', (string) $stranger->id)
            ->set('cRepeat', 'weekly')
            ->set('cDate', '2026-10-01')
            ->set('cTime', '17:00')
            ->set('cDays', [4])
            ->call('saveCreate')
            ->assertHasErrors('cStudents.0');

        $component->set('cStudents', [])->set('cAdd', (string) $student->id)->call('saveCreate')->assertHasNoErrors();

        $room = Room::where('name', 'Английский язык')->firstOrFail();
        $this->assertSame($teacher->id, $room->user_id);
        $this->assertSame([$student->id], $room->participants->pluck('id')->all());
        $this->assertSame('weekly', $room->schedules->first()->recurrence_type);
        $component->assertRedirect(route('cabinet.admin.lesson', ['room' => $room->id]));

        Notification::assertSentTo($teacher, LessonCreatedByAdmin::class);
        Notification::assertSentTo($student, TeacherAssignedLesson::class);
    }

    public function test_only_teachers_can_be_chosen_when_creating(): void
    {
        $notTeacher = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($this->admin())->test(Lessons::class)
            ->call('openCreate')
            ->set('cTeacher', (string) $notTeacher->id)
            ->set('cName', 'Английский язык')
            ->call('saveCreate')
            ->assertHasErrors(['cTeacher', 'cStudents']);

        $this->assertSame(0, Room::count());
    }
}
