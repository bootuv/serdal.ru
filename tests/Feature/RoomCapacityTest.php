<?php

namespace Tests\Feature;

use App\Models\MeetingSession;
use App\Models\Room;
use App\Models\User;
use App\Notifications\RoomFullRefused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Tests\TestCase;

/**
 * Места в классе: лимит участников набран — в BBB не отправляем, показываем экран
 * «В классе нет свободных мест» и сообщаем учителю (гостю и ученику одинаково).
 */
class RoomCapacityTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->teacher = User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_profile_completed' => true,
        ]);

        $this->room = Room::create([
            'user_id' => $this->teacher->id,
            'name' => 'Химия',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'is_running' => true,
        ]);
    }

    private function meeting(int $max, array $userIds): void
    {
        Bigbluebutton::shouldReceive('isMeetingRunning')->andReturn(true);
        Bigbluebutton::shouldReceive('getMeetingInfo')->andReturn(collect([
            'maxUsers' => (string) $max,
            'participantCount' => (string) count($userIds),
            'attendees' => ['attendee' => array_map(fn ($id) => ['userID' => (string) $id, 'fullName' => 'x'], $userIds)],
        ]));
    }

    private function student(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'username' => 'student' . uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_guest_is_stopped_when_room_is_full_and_teacher_is_notified(): void
    {
        $this->meeting(2, [$this->teacher->id, 999]);
        Bigbluebutton::shouldReceive('join')->never();

        $this->withSession(['guest_name' => 'Хава'])
            ->get(route('rooms.connect', $this->room))
            ->assertRedirect(route('rooms.join', $this->room))
            ->assertSessionHas('room_full', 2);

        Notification::assertSentTo($this->teacher, RoomFullRefused::class, function (RoomFullRefused $n) {
            $data = $n->toDatabase($this->teacher);

            return $data['title'] === 'В классе нет свободных мест'
                && str_contains($data['body'], 'Хава пытается войти на занятие «Химия»')
                && str_contains($data['body'], 'до 2 участников, считая вас');
        });
    }

    public function test_student_sees_full_screen(): void
    {
        $student = $this->student();
        $this->meeting(2, [$this->teacher->id, 999]);

        $this->actingAs($student)->get(route('rooms.connect', $this->room))
            ->assertRedirect(route('rooms.join', $this->room));

        $this->actingAs($student)->withSession(['room_full' => 2])
            ->get(route('rooms.join', $this->room))
            ->assertOk()
            ->assertSee('В классе нет свободных мест')
            ->assertSee('до 2 участников, считая учителя', false)
            ->assertSee('Попробовать ещё раз');

        Notification::assertSentTo($this->teacher, RoomFullRefused::class);
    }

    public function test_repeated_attempts_notify_teacher_once(): void
    {
        $student = $this->student();
        $this->meeting(2, [$this->teacher->id, 999]);

        $this->actingAs($student)->get(route('rooms.connect', $this->room));
        $this->actingAs($student)->get(route('rooms.connect', $this->room));

        Notification::assertSentToTimes($this->teacher, RoomFullRefused::class, 1);
    }

    public function test_rejoining_participant_and_free_room_are_let_in(): void
    {
        $student = $this->student();
        $this->meeting(2, [$this->teacher->id, $student->id]);
        Bigbluebutton::shouldReceive('join')->once()
            ->withArgs(fn ($p) => $p['errorRedirectUrl'] === route('rooms.join-failed', $this->room))
            ->andReturn('https://bbb.test/join');

        $this->actingAs($student)->get(route('rooms.connect', $this->room))
            ->assertRedirect('https://bbb.test/join');

        Notification::assertNothingSent();
    }

    public function test_bbb_refusal_returns_to_full_screen(): void
    {
        MeetingSession::create([
            'user_id' => $this->teacher->id,
            'room_id' => $this->room->id,
            'meeting_id' => $this->room->meeting_id,
            'started_at' => now(),
            'status' => 'running',
            'settings_snapshot' => ['maxParticipants' => 3],
        ]);

        $this->withSession(['guest_name' => 'Хава'])
            ->get(route('rooms.join-failed', $this->room) . '?error=maxParticipantsReached')
            ->assertRedirect(route('rooms.join', $this->room))
            ->assertSessionHas('room_full', 3);

        Notification::assertSentTo($this->teacher, RoomFullRefused::class);
    }
}
