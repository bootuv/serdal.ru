<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\Session;
use App\Models\MeetingSession;
use App\Models\SessionDeletionDecision;
use App\Models\User;
use App\Notifications\SessionDeletedByAdmin;
use App\Notifications\SessionDeletionApproved;
use App\Notifications\SessionDeletionRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Cabinet\TeacherLessonFixtures;
use Tests\TestCase;

/** Админка · отчёт о проведённом занятии (/cabinet/admin/sessions/{session}). */
class SessionTest extends TestCase
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

    /** Занятие вт, 22 сентября 10:00–10:58: Алина была (с микрофоном и сообщениями), Павел не пришёл. */
    private function held(array $extra = []): array
    {
        $teacher = $this->teacher();
        $alina = $this->studentOf($teacher);
        $pavel = $this->studentOf($teacher, 'Павел Ким');
        $room = $this->room($teacher, [$alina, $pavel], ['name' => 'ЕГЭ · математика']);
        $session = $this->completedSession($room, '2026-09-22 10:00', 58, [$alina->id], $extra + [
            'analytics_data' => [
                'poll_count' => 1,
                'timeline' => [['type' => 'poll', 'timestamp' => Carbon::parse('2026-09-22 10:20')->toIso8601String()]],
                'participants' => [[
                    'user_id' => (string) $alina->id, 'full_name' => $alina->name,
                    'joined_at' => Carbon::parse('2026-09-22 10:02')->toIso8601String(),
                    'left_at' => Carbon::parse('2026-09-22 10:40')->toIso8601String(),
                    'talking_time' => 600, 'message_count' => 4, 'emoji_count' => 2, 'raise_hand_count' => 1,
                ]],
            ],
        ]);

        return [$teacher, $session];
    }

    public function test_access_by_role_and_missing_session(): void
    {
        [$teacher, $session] = $this->held();

        $this->get(route('cabinet.admin.session', ['session' => $session->id]))->assertRedirect(route('login'));
        $this->actingAs($teacher)->get(route('cabinet.admin.session', ['session' => $session->id]))->assertRedirect();

        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin)->get(route('cabinet.admin.session', ['session' => $session->id]))->assertOk()->assertSee('ЕГЭ · математика');
        $this->actingAs($admin)->get(route('cabinet.admin.session', ['session' => 999999]))->assertNotFound();
    }

    public function test_report_shows_stats_students_and_timeline(): void
    {
        [, $session] = $this->held();

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Session::class, ['session' => $session->id])
            ->assertSee('Итог занятия')
            ->assertSee('58 мин')
            ->assertSee('1 из 2')
            ->assertSee('38 мин')      // в классе 10:02–10:40
            ->assertSee('10 мин')      // с микрофоном
            ->assertSee('пропуск')
            ->assertSeeInOrder(['10:00', 'Занятие началось', '10:02', 'Вход в класс: Алина Смирнова', '10:20', 'Голосование', '10:40', 'Выход из класса: Алина Смирнова', '10:58', 'Занятие завершено'])
            ->assertSee('Удалить занятие');
    }

    public function test_delete_without_request_notifies_teacher(): void
    {
        [$teacher, $session] = $this->held();

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Session::class, ['session' => $session->id])
            ->call('askDeleteSession', $session->id)
            ->assertSee('Удалить занятие?')
            ->call('confirmDeleteSession')
            ->assertRedirect(route('cabinet.admin.lessons', ['tab' => 'sessions']));

        $this->assertModelMissing($session);
        $this->assertSame(0, SessionDeletionDecision::count());
        Notification::assertSentTo($teacher, SessionDeletedByAdmin::class);
        Notification::assertNotSentTo($teacher, SessionDeletionApproved::class);
    }

    public function test_request_card_and_decisions(): void
    {
        [$teacher, $session] = $this->held(['deletion_requested_at' => now()->subHour(), 'deletion_reason' => 'Это был дубль']);

        $admin = $this->user(User::ROLE_ADMIN);
        Livewire::actingAs($admin)->test(Session::class, ['session' => $session->id])
            ->assertSee('просит удалить это занятие')
            ->assertSee('Это был дубль')
            ->assertSee('Запросы на удаление')
            ->call('askRejectSession', $session->id)
            ->call('confirmRejectSession')
            ->assertRedirect(route('cabinet.admin.lessons', ['tab' => 'deletions']));

        $this->assertNull($session->fresh()->deletion_requested_at);
        Notification::assertSentTo($teacher, SessionDeletionRejected::class);
        $this->assertSame(SessionDeletionDecision::REJECTED, SessionDeletionDecision::first()->decision);
    }

    public function test_running_session_cannot_be_deleted(): void
    {
        $teacher = $this->teacher();
        $room = $this->room($teacher, [$this->studentOf($teacher)]);
        $session = MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id, 'started_at' => now()->subMinutes(5), 'status' => 'running']);

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Session::class, ['session' => $session->id])
            ->assertSee('Пока идёт')
            ->assertDontSee('Удалить занятие')
            ->call('askDeleteSession', $session->id)
            ->call('confirmDeleteSession')
            ->assertStatus(422);

        $this->assertModelExists($session);
    }
}
