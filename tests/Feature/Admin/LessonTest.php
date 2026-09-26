<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\Lesson;
use App\Models\MeetingSession;
use App\Models\Room;
use App\Models\User;
use App\Notifications\LessonStoppedByAdmin;
use App\Services\TeacherLessonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Livewire\Livewire;
use Tests\Feature\Cabinet\TeacherLessonFixtures;
use Tests\TestCase;

/** Админка · страница занятия (/cabinet/admin/lessons/{room}): кто занимается, расписание, завершение, архив, удаление. */
class LessonTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Event::fake([\App\Events\RoomStatusUpdated::class]);
        $this->fixNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->user(User::ROLE_ADMIN);
    }

    private function liveRoom(): array
    {
        $teacher = $this->teacher();
        $student = $this->studentOf($teacher);
        $absent = $this->studentOf($teacher, 'Павел Ким');
        $room = $this->room($teacher, [$student, $absent], ['name' => 'Химия · 10 класс']);
        $room->updateQuietly(['is_running' => true]);
        $session = MeetingSession::create([
            'user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id,
            'started_at' => now()->subMinutes(33), 'status' => 'running',
            'analytics_data' => ['participants' => [
                ['user_id' => (string) $student->id, 'full_name' => $student->name, 'joined_at' => now()->subMinutes(30)->toIso8601String()],
            ]],
        ]);

        return [$teacher, $room->fresh(), $session, $student, $absent];
    }

    public function test_access_by_role_and_missing_lesson(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, []);

        $this->get(route('cabinet.admin.lesson', ['room' => $room->id]))->assertRedirect(route('login'));
        $this->actingAs($teacher)->get(route('cabinet.admin.lesson', ['room' => $room->id]))->assertRedirect();
        $this->actingAs($this->user(User::ROLE_STUDENT))->get(route('cabinet.admin.lesson', ['room' => $room->id]))->assertRedirect(route('cabinet.student.home'));

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('cabinet.admin.lesson', ['room' => $room->id]))->assertOk()->assertSee($room->name);
        $this->actingAs($admin)->get(route('cabinet.admin.lesson', ['room' => 999999]))->assertNotFound();
        $this->actingAs($admin)->get(route('cabinet.admin.lesson', ['room' => 'abc']))->assertNotFound();
    }

    public function test_live_lesson_shows_who_is_in_class_and_admin_stops_it(): void
    {
        [$teacher, $room, $session, $student, $absent] = $this->liveRoom();

        Bigbluebutton::shouldReceive('getMeetingInfo')->andThrow(new \RuntimeException('closed'));
        Bigbluebutton::shouldReceive('close')->once()->andReturn(true);

        Livewire::actingAs($this->admin())->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Идёт сейчас')
            ->assertSee('идёт 33 минуты')
            ->assertSee('Подключиться')
            ->assertSee('Не в классе')
            ->call('ask', 'stop')
            ->assertSee('Завершить занятие?')
            ->call('stop')
            ->assertDispatched('toast');

        $this->assertFalse($room->fresh()->is_running);
        $this->assertSame('completed', $session->fresh()->status);
        Notification::assertSentTo($teacher, LessonStoppedByAdmin::class);
    }

    public function test_stop_route_works_for_admin_instead_of_server_error(): void
    {
        [$teacher, $room] = $this->liveRoom();

        Bigbluebutton::shouldReceive('getMeetingInfo')->andReturn(null);
        Bigbluebutton::shouldReceive('close')->andThrow(new \RuntimeException('unreachable'));

        $this->actingAs($this->admin())->from('/cabinet/admin')->get(route('rooms.stop', $room))->assertRedirect('/cabinet/admin');

        $this->assertFalse($room->fresh()->is_running);
        Notification::assertSentTo($teacher, LessonStoppedByAdmin::class);
    }

    public function test_schedule_shows_rules_and_upcoming_with_exceptions(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $schedule = $this->weeklyAt($room, [2, 4], '16:00');
        app(TeacherLessonService::class)->cancelOccurrence($schedule, Carbon::parse('2026-09-29 16:00'), 'праздник', false, $teacher);
        $this->completedSession($room, '2026-09-22 16:00', 58, [$room->participants->first()->id]);

        Livewire::actingAs($this->admin())->test(Lesson::class, ['room' => $room->id])
            ->assertSee('Ближайшее — сегодня в 16:00')
            ->assertSee('По вторникам и четвергам в 16:00 · 60 минут')
            ->assertSee('через 4 часа')
            ->assertSee('отменено · праздник')
            ->assertSee('58 минут · пришли 1 из 1')
            ->assertSee('Все 1')
            ->assertSee('Учитель не загружал презентации.');
    }

    public function test_archive_restore_and_delete_forever(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $this->weeklyAt($room, [4], '16:00');
        $this->completedSession($room, '2026-09-17 16:00', 60, []);

        $component = Livewire::actingAs($this->admin())->test(Lesson::class, ['room' => $room->id])
            ->call('ask', 'archive')
            ->assertSee('Перенести в архив?')
            ->call('archive')
            ->assertSee('В архиве');

        $this->assertSoftDeleted($room);
        $this->assertSame(0, $room->schedules()->count());

        $component->call('restore')->assertSee('Нет расписания');
        $this->assertNotSoftDeleted($room);

        $component->call('ask', 'archive')->call('archive')
            ->call('ask', 'delete')
            ->assertSee('Удалятся 1 проведённое занятие со статистикой')
            ->call('forceDelete')
            ->assertRedirect(route('cabinet.admin.lessons'));

        $this->assertNull(Room::withTrashed()->find($room->id));
        $this->assertSame(0, MeetingSession::count());
    }

    public function test_active_lesson_cannot_be_deleted_forever(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, []);

        Livewire::actingAs($this->admin())->test(Lesson::class, ['room' => $room->id])
            ->call('ask', 'delete')
            ->assertSet('confirm', null)
            ->call('forceDelete')
            ->assertStatus(422);

        $this->assertNotNull(Room::find($room->id));
    }
}
